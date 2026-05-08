<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
header('Content-Type: application/json');

$S2_PASS = 'S2';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `intel_drawings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `map_name` VARCHAR(50) NOT NULL DEFAULT 'altis',
        `geojson` TEXT NOT NULL,
        `user_id` VARCHAR(50) DEFAULT 'Anonymous',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Ignore if exists
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $map = $_GET['map'] ?? 'altis';
    try {
        $stmt = $pdo->prepare("SELECT * FROM intel_drawings WHERE map_name = ? ORDER BY id ASC");
        $stmt->execute([$map]);
        $rows = $stmt->fetchAll();
        $features = [];
        foreach ($rows as $r) {
            $f = json_decode($r['geojson'], true);
            if ($f) {
                // Add DB ID to feature properties so we can delete specific ones if needed later
                if (!isset($f['properties'])) $f['properties'] = [];
                $f['properties']['db_id'] = $r['id'];
                $f['properties']['user_id'] = $r['user_id'];
                $features[] = $f;
            }
        }
        $featureCollection = [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
        echo json_encode(['success' => true, 'data' => $featureCollection]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';
    
    // Auth check for clearing map
    if ($action === 'clear') {
        if (($input['password'] ?? '') !== $S2_PASS) {
            http_response_code(403);
            echo json_encode(['error' => 'ACCESS DENIED']); exit;
        }
        $map = $input['map'] ?? 'altis';
        try {
            $pdo->prepare("DELETE FROM intel_drawings WHERE map_name = ?")->execute([$map]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // Save drawn feature (no hard auth required for drawing, or we can use S2_PASS)
    // The user requested that we add `user_id` so we track who draws.
    // For now, anyone can draw, but clearing requires S2.
    if ($action === 'save') {
        $map = $input['map'] ?? 'altis';
        $geojson = $input['geojson'] ?? '';
        $user_id = $input['user_id'] ?? 'Anonymous';
        
        if (empty($geojson)) {
            echo json_encode(['error' => 'Empty geojson']); exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO intel_drawings (map_name, geojson, user_id) VALUES (?, ?, ?)");
            $stmt->execute([$map, is_string($geojson) ? $geojson : json_encode($geojson), $user_id]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['error' => 'Invalid action']);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
