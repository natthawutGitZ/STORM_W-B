<?php
/**
 * Approve / Reject donation API
 * Sends Discord webhook notification on approval
 */
ob_start();
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
ob_end_clean();

// Ensure thunder_api_key column exists
try {
    $pdo->exec("ALTER TABLE `donate_settings` ADD COLUMN `thunder_api_key` VARCHAR(255) DEFAULT '' AFTER `thank_you_message`");
} catch (PDOException $e) {
}
// Ensure verify columns exist in donations
try {
    $pdo->exec("ALTER TABLE `donations` ADD COLUMN `transaction_ref` VARCHAR(100) DEFAULT NULL AFTER `reviewed_by`");
} catch (PDOException $e) {
}
try {
    $pdo->exec("ALTER TABLE `donations` ADD COLUMN `verify_status` VARCHAR(20) DEFAULT NULL AFTER `transaction_ref`");
} catch (PDOException $e) {
}
try {
    $pdo->exec("ALTER TABLE `donations` ADD COLUMN `verify_data` TEXT DEFAULT NULL AFTER `verify_status`");
} catch (PDOException $e) {
}

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

$currentUser = getUser();
$reviewerName = $currentUser ? ($currentUser['personaname'] ?? $currentUser['username'] ?? 'Admin') : 'Admin';

switch ($action) {
    case 'approve':
        $stmt = $pdo->prepare("UPDATE donations SET status = 'approved', reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$reviewerName, $id]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Donation not found or already reviewed']);
            break;
        }

        // Fetch donation details for webhook
        $donation = $pdo->prepare("SELECT * FROM donations WHERE id = ?");
        $donation->execute([$id]);
        $d = $donation->fetch(PDO::FETCH_ASSOC);

        // Send Discord notification to channel
        $settings = $pdo->query("SELECT * FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if ($d && !empty($settings['discord_webhook'])) {
            sendDonateNotification($settings['discord_webhook'], $d, 'approved');
        }

        echo json_encode(['success' => true]);
        break;

    case 'reject':
        $adminNote = trim($_POST['admin_note'] ?? '');
        $stmt = $pdo->prepare("UPDATE donations SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, admin_note = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$reviewerName, $adminNote, $id]);

        echo json_encode(['success' => true]);
        break;

    case 'delete':
        // Delete slip file first
        $stmt = $pdo->prepare("SELECT slip_image FROM donations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row && $row['slip_image']) {
            $filePath = ROOT_PATH . '/assets/uploads/donate/' . $row['slip_image'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM donations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'save_settings':
        $fields = [
            'page_title' => trim($_POST['page_title'] ?? 'Donate'),
            'page_description' => trim($_POST['page_description'] ?? ''),
            'promptpay_number' => trim($_POST['promptpay_number'] ?? ''),
            'bank_account' => trim($_POST['bank_account'] ?? ''),
            'bank_name' => trim($_POST['bank_name'] ?? ''),
            'account_name' => trim($_POST['account_name'] ?? ''),
            'discord_webhook' => trim($_POST['discord_webhook'] ?? ''),
            'goal_amount' => floatval($_POST['goal_amount'] ?? 0),
            'goal_label' => trim($_POST['goal_label'] ?? ''),
            'preset_amounts' => trim($_POST['preset_amounts'] ?? '20,50,100,200,500,1000'),
            'min_amount' => max(1, floatval($_POST['min_amount'] ?? 1)),
            'thank_you_message' => trim($_POST['thank_you_message'] ?? ''),
            'thunder_api_key' => trim($_POST['thunder_api_key'] ?? ''),
        ];

        // Handle QR image upload
        if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['qr_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            if (in_array($mime, $allowed)) {
                $uploadDir = ROOT_PATH . '/assets/uploads/donate/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0755, true);

                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
                $filename = 'qr_' . time() . '.' . $ext;

                // Delete old QR
                $old = $pdo->query("SELECT qr_image FROM donate_settings WHERE id = 1")->fetch();
                if ($old && $old['qr_image'] && file_exists($uploadDir . $old['qr_image'])) {
                    unlink($uploadDir . $old['qr_image']);
                }

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $fields['qr_image'] = $filename;
                }
            }
        }

        // Build update query
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
        $sql = "UPDATE donate_settings SET " . implode(', ', $sets) . " WHERE id = 1";
        $pdo->prepare($sql)->execute($vals);

        echo json_encode(['success' => true]);
        break;

    case 'get_settings':
        $settings = $pdo->query("SELECT * FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;

    case 'test_notification':
        $channelId = trim($_POST['channel_id'] ?? '');
        if (!$channelId) {
            echo json_encode(['success' => false, 'error' => 'No channel selected']);
            break;
        }
        try {
            require_once ROOT_PATH . '/includes/bot_api.php';
            $api = new BotAPI();
            $api->request('/embed/send', [
                'channel_id' => $channelId,
                'content' => [
                    'title' => '🔔 Test Notification',
                    'description' => "**ทดสอบการแจ้งเตือน Donate System**\n\nถ้าคุณเห็นข้อความนี้ แสดงว่าระบบแจ้งเตือนทำงานปกติ ✅",
                ],
                'style' => ['color' => '#c5a059'],
            ], 'POST');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list_donations':
        $status = trim($_POST['status'] ?? $_GET['status'] ?? '');
        $sql = "SELECT * FROM donations WHERE 1=1";
        $params = [];
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'donations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'resend_notification':
        $donation = $pdo->prepare("SELECT * FROM donations WHERE id = ?");
        $donation->execute([$id]);
        $d = $donation->fetch(PDO::FETCH_ASSOC);
        if (!$d) {
            echo json_encode(['success' => false, 'error' => 'Donation not found']);
            break;
        }
        $settings = $pdo->query("SELECT * FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if (empty($settings['discord_webhook'])) {
            echo json_encode(['success' => false, 'error' => 'ยังไม่ได้ตั้งค่า Discord Notification Channel']);
            break;
        }
        try {
            sendDonateNotification($settings['discord_webhook'], $d, $d['status']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

