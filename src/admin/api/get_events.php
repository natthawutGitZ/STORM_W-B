<?php
header('Content-Type: application/json');
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/bot_api.php';

// Ensure user is admin (security)
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$api = new BotAPI();
$events = $api->getActiveEvents();

echo json_encode(['events' => $events]);

