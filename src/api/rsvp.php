<?php
// src/api/rsvp.php
header('Content-Type: application/json');

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

// This API handles RSVP updates from the Bot
// and RSVP queries from the Web Panel

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // BOT -> PHP: Update RSVP
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['event_id'], $data['user_id'], $data['choice'])) {
        echo json_encode(['error' => 'Missing data']);
        exit;
    }

    // Since the Bot has its own PostgreSQL database, 
    // we might just want to forward this OR the bot already handled it.
    // If we want the PHP app to store a cache or notify something, we do it here.

    // For now, let's acknowledge
    echo json_encode(['success' => true, 'message' => 'RSVP received']);

} elseif ($method === 'GET') {
    // WEB -> PHP -> BOT: Query RSVP
    $event_id = $_GET['event_id'] ?? '';

    if (empty($event_id)) {
        echo json_encode(['error' => 'Missing event_id']);
        exit;
    }

    // Query from Bot (which has access to Postgres)
    $botUrl = 'http://bot:5000/event/rsvp?event_id=' . $event_id;

    $response = file_get_contents($botUrl);
    echo $response;
}
?>
