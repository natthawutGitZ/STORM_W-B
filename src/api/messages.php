<?php
/**
 * Messages API - Handles CRUD operations for saved messages (Message Builder)
 */

require_once ROOT_PATH . '/includes/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // List all saved messages
            $stmt = $pdo->query("SELECT id, name, channel_id, channel_name, message_id, status, created_at, updated_at FROM saved_messages ORDER BY updated_at DESC");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $messages]);
            break;

        case 'get':
            // Get a specific message
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing ID']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM saved_messages WHERE id = ?");
            $stmt->execute([$id]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($message) {
                // Decode JSON fields
                $message['embed_data'] = json_decode($message['embed_data'], true);
                $message['buttons_data'] = json_decode($message['buttons_data'], true);
                echo json_encode(['success' => true, 'data' => $message]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Message not found']);
            }
            break;

        case 'save':
            // Create a new message
            $input = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("INSERT INTO saved_messages (name, channel_id, channel_name, message_id, content, embed_data, buttons_data, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['name'] ?? 'Untitled Message',
                $input['channel_id'] ?? '',
                $input['channel_name'] ?? '',
                $input['message_id'] ?? null,
                $input['content'] ?? '',
                json_encode($input['embed_data'] ?? []),
                json_encode($input['buttons_data'] ?? []),
                $input['status'] ?? 'draft'
            ]);

            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        case 'update':
            // Update an existing message
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing ID']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare("UPDATE saved_messages SET name = ?, channel_id = ?, channel_name = ?, message_id = ?, content = ?, embed_data = ?, buttons_data = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $input['name'] ?? 'Untitled Message',
                $input['channel_id'] ?? '',
                $input['channel_name'] ?? '',
                $input['message_id'] ?? null,
                $input['content'] ?? '',
                json_encode($input['embed_data'] ?? []),
                json_encode($input['buttons_data'] ?? []),
                $input['status'] ?? 'draft',
                $id
            ]);

            echo json_encode(['success' => true]);
            break;

        case 'delete':
            // Delete a message
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing ID']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM saved_messages WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

