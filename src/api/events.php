<?php
// src/api/events.php
header('Content-Type: application/json');

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

// Only allow authenticated admins
if (!isAdmin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Forward to Discord Bot
// Bot is in the same docker network at http://bot:5000
$botUrl = 'http://bot:5000/event/create';

$ch = curl_init($botUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo $response;
} else {
    echo json_encode([
        'error' => 'Bot service error',
        'code' => $httpCode,
        'response' => json_decode($response, true)
    ]);
}
?>
