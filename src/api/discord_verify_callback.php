<?php
/**
 * discord_verify_callback.php
 * Receives webhook callback from Discord Bot when a user clicks a verification button.
 */
require_once ROOT_PATH . '/includes/db.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Optional API Key check (you can implement X-API-Key checking here like in the bot API)
// For internal network calls from bot to web, we might rely on network isolation

$data = json_decode(file_get_contents('php://input'), true);

$verify_id = $data['verify_id'] ?? '';
$clicked_number = $data['clicked_number'] ?? '';
$discord_user_id = $data['discord_user_id'] ?? '';

if (empty($verify_id) || empty($clicked_number) || empty($discord_user_id)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Fetch the verification record
$stmt = $pdo->prepare("SELECT * FROM discord_verifications WHERE id = ? AND discord_user_id = ?");
$stmt->execute([$verify_id, $discord_user_id]);
$verify = $stmt->fetch();

if (!$verify) {
    echo json_encode(['success' => false, 'error' => 'Verification not found']);
    exit;
}

// If already handled (prevent multiple clicks)
if ($verify['status'] !== 'pending') {
    echo json_encode(['success' => false, 'error' => 'Verification is no longer pending', 'status' => $verify['status']]);
    exit;
}

// Check expiration
if (strtotime($verify['expires_at']) < time()) {
    $pdo->prepare("UPDATE discord_verifications SET status = 'expired' WHERE id = ?")->execute([$verify_id]);
    echo json_encode(['success' => false, 'error' => 'Verification expired', 'status' => 'expired']);
    exit;
}

// Increment attempts
$attempts = $verify['attempts'] + 1;
$pdo->prepare("UPDATE discord_verifications SET attempts = ? WHERE id = ?")->execute([$attempts, $verify_id]);

// Check if clicked number matches target
if ($clicked_number === $verify['target_number']) {
    // Success
    $pdo->prepare("UPDATE discord_verifications SET status = 'success' WHERE id = ?")->execute([$verify_id]);
    echo json_encode(['success' => true, 'status' => 'success', 'message' => 'Verification successful']);
} else {
    // Failed (wrong number)
    // We fail immediately on first wrong guess as per secure design, or allow 3 tries?
    // Let's fail immediately to prevent brute force guessing of 3 numbers
    $pdo->prepare("UPDATE discord_verifications SET status = 'failed' WHERE id = ?")->execute([$verify_id]);
    echo json_encode(['success' => true, 'status' => 'failed', 'message' => 'Incorrect number selected']);
}
