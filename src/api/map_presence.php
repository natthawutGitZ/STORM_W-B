<?php
/**
 * Real-Time Map Presence API
 * Tracks which users are currently viewing the map and their cursor positions.
 * Uses a DB table with heartbeat-based presence detection.
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
header('Content-Type: application/json');

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `map_presence` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` VARCHAR(64) NOT NULL,
        `display_name` VARCHAR(64) NOT NULL DEFAULT 'Operator',
        `user_color` VARCHAR(10) NOT NULL DEFAULT '#00ff41',
        `map_name` VARCHAR(50) NOT NULL DEFAULT 'colombia',
        `cursor_lat` DOUBLE DEFAULT NULL,
        `cursor_lng` DOUBLE DEFAULT NULL,
        `last_heartbeat` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_map` (`user_id`, `map_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Ignore if exists
}

$method = $_SERVER['REQUEST_METHOD'];

// GET: List active users on a map (heartbeat within last 15 seconds)
if ($method === 'GET') {
    $map = $_GET['map'] ?? 'colombia';
    try {
        // Clean up stale entries (older than 30 seconds)
        $pdo->prepare("DELETE FROM map_presence WHERE last_heartbeat < DATE_SUB(NOW(), INTERVAL 30 SECOND)")->execute();

        $stmt = $pdo->prepare("SELECT user_id, display_name, user_color, cursor_lat, cursor_lng, last_heartbeat 
                               FROM map_presence 
                               WHERE map_name = ? AND last_heartbeat >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
                               ORDER BY display_name");
        $stmt->execute([$map]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// POST: Send heartbeat / update cursor
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? 'heartbeat';

    if ($action === 'heartbeat') {
        $userId = $input['user_id'] ?? '';
        $displayName = $input['display_name'] ?? 'Operator';
        $userColor = $input['user_color'] ?? '#00ff41';
        $map = $input['map'] ?? 'colombia';
        $cursorLat = $input['cursor_lat'] ?? null;
        $cursorLng = $input['cursor_lng'] ?? null;

        if (empty($userId)) {
            echo json_encode(['success' => false, 'error' => 'user_id required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO map_presence (user_id, display_name, user_color, map_name, cursor_lat, cursor_lng, last_heartbeat)
                                   VALUES (?, ?, ?, ?, ?, ?, NOW())
                                   ON DUPLICATE KEY UPDATE 
                                   display_name = VALUES(display_name),
                                   user_color = VALUES(user_color),
                                   cursor_lat = VALUES(cursor_lat),
                                   cursor_lng = VALUES(cursor_lng),
                                   last_heartbeat = NOW()");
            $stmt->execute([$userId, $displayName, $userColor, $map, $cursorLat, $cursorLng]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'leave') {
        $userId = $input['user_id'] ?? '';
        $map = $input['map'] ?? 'colombia';
        if ($userId) {
            try {
                $pdo->prepare("DELETE FROM map_presence WHERE user_id = ? AND map_name = ?")->execute([$userId, $map]);
            } catch (PDOException $e) {}
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
