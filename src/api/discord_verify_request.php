<?php
/**
 * discord_verify_request.php
 * Initiates a Discord DM verification for form submission.
 * 
 * - Creates a record in discord_verifications (status: pending)
 * - Generates 1 target number and 2 decoy numbers  
 * - Calls Bot API to send DM with 3 buttons to the user
 * - Returns the target number to display on the web page
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/bot_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$discord_user_id = $_POST['discord_user_id'] ?? '';
$form_id = $_POST['form_id'] ?? '';
$form_title = $_POST['form_title'] ?? 'Form Verification';

if (empty($discord_user_id) || empty($form_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing discord_user_id or form_id']);
    exit;
}

// Ensure table exists (auto-migrate)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `discord_verifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `discord_user_id` VARCHAR(30) NOT NULL,
        `form_id` INT(11) NOT NULL,
        `target_number` VARCHAR(10) NOT NULL,
        `decoy_numbers` JSON NOT NULL,
        `status` ENUM('pending', 'success', 'failed', 'expired') DEFAULT 'pending',
        `attempts` INT(11) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `expires_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_discord_user` (`discord_user_id`),
        KEY `idx_status` (`status`),
        KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Table likely already exists
}

// Expire any old pending verifications for this user
try {
    $pdo->prepare("UPDATE discord_verifications SET status = 'expired' WHERE discord_user_id = ? AND status = 'pending'")->execute([$discord_user_id]);
} catch (PDOException $e) {
    // Ignore errors
}

// Generate numbers: 1 target + 2 decoys (format: "01" to "99")
$allNumbers = [];
while (count($allNumbers) < 3) {
    $num = str_pad(random_int(1, 99), 2, '0', STR_PAD_LEFT);
    if (!in_array($num, $allNumbers)) {
        $allNumbers[] = $num;
    }
}

$targetNumber = $allNumbers[0]; // First one is the target
shuffle($allNumbers); // Shuffle so target position is random

// Insert verification record
$expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
$stmt = $pdo->prepare("INSERT INTO discord_verifications (discord_user_id, form_id, target_number, decoy_numbers, status, expires_at) VALUES (?, ?, ?, ?, 'pending', ?)");
$stmt->execute([$discord_user_id, $form_id, $targetNumber, json_encode($allNumbers), $expiresAt]);
$verifyId = $pdo->lastInsertId();

// Send DM via Bot API
$botApi = new BotAPI();
$dmResult = $botApi->request('/dm/verify', [
    'user_id' => $discord_user_id,
    'verify_id' => $verifyId,
    'form_title' => $form_title,
    'target_number' => $targetNumber,
    'buttons' => $allNumbers
]);

if (!$dmResult['success']) {
    // Mark as failed if DM couldn't be sent
    $pdo->prepare("UPDATE discord_verifications SET status = 'failed' WHERE id = ?")->execute([$verifyId]);
    echo json_encode([
        'success' => false,
        'error' => 'ไม่สามารถส่งข้อความ DM ได้ กรุณาตรวจสอบว่าเปิด DM ใน Discord แล้ว',
        'detail' => $dmResult['error'] ?? 'Bot DM failed'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'verify_id' => $verifyId,
    'target_number' => $targetNumber,
    'expires_at' => $expiresAt
]);
