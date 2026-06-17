<?php
/**
 * discord_verify_status.php
 * Polling endpoint for frontend to check the status of a verification request.
 */
require_once ROOT_PATH . '/includes/db.php';

header('Content-Type: application/json');

$verify_id = $_GET['verify_id'] ?? '';

if (empty($verify_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing verify_id']);
    exit;
}

// Fetch the verification record
$stmt = $pdo->prepare("SELECT status, target_number, attempts, expires_at FROM discord_verifications WHERE id = ?");
$stmt->execute([$verify_id]);
$verify = $stmt->fetch();

if (!$verify) {
    echo json_encode(['success' => false, 'error' => 'Verification not found']);
    exit;
}

// Check expiration on polling as well
if ($verify['status'] === 'pending' && strtotime($verify['expires_at']) < time()) {
    $pdo->prepare("UPDATE discord_verifications SET status = 'expired' WHERE id = ?")->execute([$verify_id]);
    $verify['status'] = 'expired';
}

echo json_encode([
    'success' => true,
    'status' => $verify['status'],
    'attempts' => $verify['attempts']
]);
