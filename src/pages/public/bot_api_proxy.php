<?php
/**
 * Bot API Proxy for AJAX requests
 * Handles async requests from admin panel to bot API
 */
require_once 'db.php';
require_once 'bot_api.php';

header('Content-Type: application/json');

$api = new BotAPI();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_welcome_sound':
        $result = $api->request('/settings/welcome-sound', [], 'GET');
        echo json_encode($result);
        break;

    case 'set_welcome_sound':
        $data = json_decode(file_get_contents('php://input'), true);

        // Prepare payload for bot
        $payload = [
            'enabled' => $data['enabled'] ?? false,
            'channel_id' => $data['channel_id'] ?? '',
            'delay' => $data['delay'] ?? 2,
            'message_text' => $data['message_text'] ?? '',
            'dropdown_options' => $data['dropdown_options'] ?? [],
            'link_buttons' => $data['link_buttons'] ?? [],
            'delete_file' => $data['delete_file'] ?? false,
        ];

        // Include file data if present
        if (!empty($data['sound_base64'])) {
            $payload['sound_base64'] = $data['sound_base64'];
            $payload['filename'] = $data['filename'] ?? 'welcome.mp3';
        }

        $result = $api->request('/settings/welcome-sound', $payload, 'POST');
        echo json_encode($result);
        break;

    case 'get_server_welcome':
        $result = $api->request('/settings/server-welcome', [], 'GET');
        echo json_encode($result);
        break;

    case 'set_server_welcome':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->request('/settings/server-welcome', $data, 'POST');
        echo json_encode($result);
        break;

    case 'test_server_welcome':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->request('/settings/server-welcome/test', $data, 'POST');
        echo json_encode($result);
        break;

    case 'join_voice':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->joinVoice($data['channel_id'] ?? '');
        echo json_encode($result);
        break;

    case 'leave_voice':
        $result = $api->leaveVoice();
        echo json_encode($result);
        break;

    case 'update_bot_settings':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->updateBotSettings($data);
        echo json_encode($result);
        break;

    case 'send_embed':
        $data = json_decode(file_get_contents('php://input'), true);
        // Use sendEmbed method which forwards to /embed/send endpoint
        $channelId = $data['channel_id'] ?? '';
        $result = $api->sendEmbed($channelId, $data);
        echo json_encode($result);
        break;

    case 'get_permissions':
        $result = $api->request('/settings/permissions', [], 'GET');
        echo json_encode($result);
        break;

    case 'save_permissions':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->request('/settings/permissions', $data, 'POST');
        echo json_encode($result);
        break;

    // Message Builder Actions
    case 'list_messages':
        try {
            // Check if is_pinned column exists
            try {
                $stmt = $pdo->query("SELECT 1 FROM saved_messages WHERE is_pinned = 0 LIMIT 1");
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "Unknown column") !== false) {
                    $pdo->exec("ALTER TABLE saved_messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0");
                }
            }

            $stmt = $pdo->query("SELECT id, name, channel_id, channel_name, message_id, status, is_pinned, created_at, updated_at FROM saved_messages ORDER BY is_pinned DESC, updated_at DESC");
            $messages = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $messages]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'toggle_pin':
        $id = $_GET['id'] ?? '';
        try {
            $stmt = $pdo->prepare("UPDATE saved_messages SET is_pinned = NOT is_pinned WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            // Handle if column missing during toggle (though list should have fixed it, maybe direct call?)
            if (strpos($e->getMessage(), "Unknown column") !== false) {
                $pdo->exec("ALTER TABLE saved_messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0");
                // Retry
                $stmt = $pdo->prepare("UPDATE saved_messages SET is_pinned = NOT is_pinned WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
        }
        break;

    case 'get_message':
        $id = $_GET['id'] ?? '';
        try {
            $stmt = $pdo->prepare("SELECT * FROM saved_messages WHERE id = ?");
            $stmt->execute([$id]);
            $message = $stmt->fetch();

            if ($message) {
                // Decode JSON fields manually since we are bypassing the API which might have done it
                $message['embed_data'] = json_decode($message['embed_data'], true);
                $message['buttons_data'] = json_decode($message['buttons_data'], true);
                echo json_encode(['success' => true, 'data' => $message]);
            } else {
                echo json_encode(['error' => 'Message not found']);
            }
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'save_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->request('/messages', $data, 'POST');
        echo json_encode($result);
        break;

    case 'update_message':
        $id = $_GET['id'] ?? '';
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->request("/messages/{$id}", $data, 'PUT');
        echo json_encode($result);
        break;

    // Event Manager Actions
    case 'get_events':
        // Forward to bot to get active events from DB
        $result = $api->getActiveEvents();
        // Wrap in standard response format
        echo json_encode(['success' => true, 'events' => $result]);
        break;

    case 'delete_event':
        $data = json_decode(file_get_contents('php://input'), true);
        $eventId = $data['event_id'] ?? '';
        if (!$eventId) {
            echo json_encode(['error' => 'Missing event_id']);
            break;
        }
        // Use cancelEvent which usually deletes or marks cancelled
        $success = $api->cancelEvent($eventId);
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to delete event']);
        }
        break;

    case 'delete_message':
        // DEBUG V1 - Direct DB delete instead of going through bot
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($_GET['id'] ?? ($data['id'] ?? 0));

        error_log("[DELETE_MESSAGE V1] Starting delete for ID: $id");

        if (!$id) {
            echo json_encode(['error' => 'Missing ID', 'debug' => 'V1']);
            break;
        }

        try {
            // If user wants to delete from Discord too, call the bot
            if (!empty($data['delete_from_discord']) && !empty($data['message_id']) && !empty($data['channel_id'])) {
                error_log("[DELETE_MESSAGE V1] Also deleting from Discord: msg={$data['message_id']} ch={$data['channel_id']}");
                // Call bot to delete from Discord (fire and forget)
                $api->request("/messages/{$id}", $data, 'DELETE');
            }

            // Direct database delete
            $stmt = $pdo->prepare("DELETE FROM saved_messages WHERE id = ?");
            $result = $stmt->execute([$id]);
            $rowCount = $stmt->rowCount();

            error_log("[DELETE_MESSAGE V1] DB delete result: success=$result, rows=$rowCount");

            if ($rowCount > 0) {
                echo json_encode(['success' => true, 'debug' => 'V1', 'deleted_id' => $id]);
            } else {
                echo json_encode(['success' => true, 'debug' => 'V1', 'warning' => 'No rows deleted, message may not exist']);
            }
        } catch (PDOException $e) {
            error_log("[DELETE_MESSAGE V1] Error: " . $e->getMessage());
            echo json_encode(['error' => 'Database error: ' . $e->getMessage(), 'debug' => 'V1']);
        }
        break;

    // --- DISCORD FEED SYSTEM ---

    case 'get_channels':
        // Get list of Discord text channels from bot
        $result = $api->request('/channels', [], 'GET');
        echo json_encode($result);
        break;

    case 'get_feed_settings':
        // Get feed channel settings for all sections
        $result = $api->request('/feed/settings', [], 'GET');
        echo json_encode($result);
        break;

    case 'save_feed_settings':
        // Save feed channel settings for a section
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $api->request('/feed/settings', $data, 'POST');

        // Also save to database for persistence
        if (isset($result['success']) && $result['success']) {
            try {
                $section = $data['section'] ?? '';
                $channel_id = $data['channel_id'] ?? '';
                $channel_name = $result['channel_name'] ?? '';
                $enabled = $data['enabled'] ?? true;

                // Upsert to database
                $stmt = $pdo->prepare("
                    INSERT INTO discord_feed_settings (section, channel_id, channel_name, enabled)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        channel_id = VALUES(channel_id),
                        channel_name = VALUES(channel_name),
                        enabled = VALUES(enabled),
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$section, $channel_id, $channel_name, $enabled ? 1 : 0]);
            } catch (PDOException $e) {
                // Log but don't fail - Redis is primary store
                error_log("[FEED] DB save error: " . $e->getMessage());
            }
        }
        echo json_encode($result);
        break;

    case 'get_feed_messages':
        // Get feed messages for a section
        $section = $_GET['section'] ?? '';
        $limit = (int) ($_GET['limit'] ?? 20);

        $result = $api->request("/feed/messages?section={$section}&limit={$limit}", [], 'GET');
        echo json_encode($result);
        break;

    case 'sync_feed_messages':
        // Sync messages from Redis queue to database (called periodically or on-demand)
        try {
            // This would be called by a cron job or admin action
            // For now, just return success - the bot handles real-time storage
            echo json_encode(['success' => true, 'message' => 'Feed sync not needed - real-time storage active']);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    // --- DISCORD USER PICKER FOR FORMS ---
    case 'get_roles':
        // Get all Discord roles for role filter configuration
        $result = $api->request('/roles', [], 'GET');
        echo json_encode($result);
        break;

    case 'get_filtered_users':
        // Get Discord users filtered by roles for form applicant selection
        // Default: users with no roles, Optional: additional_roles parameter
        $additionalRoles = $_GET['additional_roles'] ?? '';
        $result = $api->getFilteredUsers($additionalRoles);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
        break;
}
