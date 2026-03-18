<?php
/**
 * Handle donation slip upload and create donation record
 */
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$amount = floatval($_POST['amount'] ?? 0);
$donorName = trim($_POST['donor_name'] ?? 'Anonymous');
$message = trim($_POST['message'] ?? '');

// Validate
if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'จำนวนเงินไม่ถูกต้อง']);
    exit;
}

if ($amount > 999999) {
    echo json_encode(['success' => false, 'error' => 'จำนวนเงินเกินขีดจำกัด']);
    exit;
}

$donorName = mb_substr($donorName, 0, 100);
$message = mb_substr($message, 0, 500);

// Handle slip upload
$slipImage = null;
if (isset($_FILES['slip']) && $_FILES['slip']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['slip'];

    // Validate file type
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'รองรับเฉพาะไฟล์ JPG, PNG, WEBP']);
        exit;
    }

    // Max 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'ไฟล์มีขนาดเกิน 5MB']);
        exit;
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/assets/uploads/donate/slips/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    $filename = 'slip_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $slipImage = 'slips/' . $filename;
    } else {
        echo json_encode(['success' => false, 'error' => 'อัปโหลดไฟล์ล้มเหลว']);
        exit;
    }
}

if (!$slipImage) {
    echo json_encode(['success' => false, 'error' => 'กรุณาอัปโหลดสลิป']);
    exit;
}

// Thunder API slip verification
$verifyStatus = null;
$verifyData = null;
$transactionRef = null;
$autoApprove = false;

$settings = $pdo->query("SELECT thunder_api_key FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
$thunderKey = trim($settings['thunder_api_key'] ?? '');

if ($thunderKey && $slipImage) {
    try {
        $slipPath = __DIR__ . '/assets/uploads/donate/' . $slipImage;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.thunder.in.th/v2/verify/bank',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $thunderKey,
            ],
            CURLOPT_POSTFIELDS => [
                'image' => new CURLFile($slipPath, mime_content_type($slipPath), basename($slipPath)),
                'checkDuplicate' => 'true',
                'matchAmount' => $amount,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            $verifyData = $response;

            if (!empty($result['success']) && isset($result['data'])) {
                $data = $result['data'];
                $rawSlip = $data['rawSlip'] ?? [];
                $transactionRef = $rawSlip['transRef'] ?? null;
                $slipAmount = floatval($rawSlip['amount']['amount'] ?? 0);
                $isDuplicate = $data['isDuplicate'] ?? false;

                // Check duplicate from Thunder API
                if ($isDuplicate) {
                    @unlink($slipPath);
                    echo json_encode(['success' => false, 'error' => 'สลิปนี้เคยใช้แล้ว กรุณาใช้สลิปใหม่']);
                    exit;
                }

                // Also check local DB for duplicates
                if ($transactionRef) {
                    $dupStmt = $pdo->prepare("SELECT id FROM donations WHERE transaction_ref = ?");
                    $dupStmt->execute([$transactionRef]);
                    if ($dupStmt->fetch()) {
                        @unlink($slipPath);
                        echo json_encode(['success' => false, 'error' => 'สลิปนี้เคยใช้แล้ว กรุณาใช้สลิปใหม่']);
                        exit;
                    }
                }

                // Verify amount matches
                if ($slipAmount > 0 && abs($slipAmount - $amount) < 0.01) {
                    $verifyStatus = 'verified';
                    $autoApprove = true;
                } elseif ($slipAmount > 0) {
                    $verifyStatus = 'amount_mismatch';
                    $amount = $slipAmount; // Use actual amount from slip
                } else {
                    $verifyStatus = 'checked';
                }
            } else {
                $verifyStatus = 'invalid';
            }
        } else {
            $verifyStatus = 'api_error';
        }
    } catch (Exception $e) {
        $verifyStatus = 'api_error';
        $verifyData = json_encode(['error' => $e->getMessage()]);
    }
}

// Insert donation
try {
    $status = $autoApprove ? 'approved' : 'pending';
    $stmt = $pdo->prepare("INSERT INTO donations (donor_name, amount, message, slip_image, ip_address, status, transaction_ref, verify_status, verify_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $donorName,
        $amount,
        $message,
        $slipImage,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $status,
        $transactionRef,
        $verifyStatus,
        $verifyData,
    ]);

    $donationId = $pdo->lastInsertId();

    // Send Discord notification for auto-approved donations
    if ($autoApprove) {
        try {
            $donateSettings = $pdo->query("SELECT discord_webhook FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            if (!empty($donateSettings['discord_webhook'])) {
                $donation = $pdo->prepare("SELECT * FROM donations WHERE id = ?");
                $donation->execute([$donationId]);
                $donationRow = $donation->fetch(PDO::FETCH_ASSOC);
                if ($donationRow) {
                    sendDonateNotification($donateSettings['discord_webhook'], $donationRow, 'approved');
                }
            }
        } catch (Exception $e) {
            // Don't fail the donation if notification fails
        }
    }

    $responseData = ['success' => true, 'id' => $donationId];
    if ($autoApprove) {
        $responseData['auto_approved'] = true;
        $responseData['message'] = 'สลิปถูกตรวจสอบแล้ว อนุมัติอัตโนมัติ!';
    } elseif ($verifyStatus === 'amount_mismatch') {
        $responseData['message'] = 'จำนวนเงินในสลิปไม่ตรงกับที่เลือก ใช้ยอดจากสลิปแทน';
    }
    echo json_encode($responseData);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

