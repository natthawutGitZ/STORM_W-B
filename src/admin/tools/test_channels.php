<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/bot_api.php';
header('Content-Type: application/json');
$api = new BotAPI();

// Check current settings
$settings = $pdo->query("SELECT discord_webhook FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Test embed send to first channel
$channels = $api->getChannels();
$testCh = $channels[0]['id'] ?? null;

$embedResult = null;
if ($testCh) {
    $embedResult = $api->request('/embed/send', [
        'channel_id' => $testCh,
        'content' => [
            'title' => 'Test',
            'description' => 'Test notification',
        ],
        'style' => ['color' => '#c5a059'],
    ], 'POST');
}

echo json_encode([
    'channel_count' => count($channels),
    'saved_webhook' => $settings['discord_webhook'] ?? 'NULL',
    'test_channel' => $testCh,
    'embed_result' => $embedResult,
]);

