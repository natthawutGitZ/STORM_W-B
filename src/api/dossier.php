<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
header('Content-Type: application/json');

$S2_PASS = 'S2';

// Auto-create dossier table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `intel_dossiers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `callsign` VARCHAR(100) DEFAULT '',
        `full_name` VARCHAR(200) DEFAULT '',
        `category` ENUM('primary','secondary','poi') DEFAULT 'poi',
        `threat_level` ENUM('HIGH','MEDIUM','LOW','UNKNOWN') DEFAULT 'UNKNOWN',
        `status` VARCHAR(50) DEFAULT 'AT LARGE',
        `task_directive` VARCHAR(50) DEFAULT 'NONE',
        `last_loi` VARCHAR(200) DEFAULT '',
        `grid_ref` VARCHAR(100) DEFAULT '',
        `asset_tag` VARCHAR(100) DEFAULT '',
        `photo_url` VARCHAR(500) DEFAULT '',
        `stat_ma` VARCHAR(50) DEFAULT '',
        `stat_fog` VARCHAR(50) DEFAULT '',
        `stat_fr` VARCHAR(50) DEFAULT '',
        `stat_int` VARCHAR(50) DEFAULT '',
        `summary` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ---- READ (no auth) ----
if ($method === 'GET' && $action === 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM `intel_dossiers` ORDER BY FIELD(category,'primary','secondary','poi'), callsign ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($method === 'GET' && $action === 'get') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }
    try {
        $stmt = $pdo->prepare("SELECT * FROM `intel_dossiers` WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        echo json_encode($row ? ['success' => true, 'data' => $row] : ['success' => false, 'error' => 'Not found']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- WRITE (auth required) ----
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || ($input['password'] ?? '') !== $S2_PASS) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $fields = $input['fields'] ?? [];
    $allowed = ['callsign','full_name','category','threat_level','status','task_directive',
                'last_loi','grid_ref','asset_tag','photo_url','stat_ma','stat_fog','stat_fr','stat_int','summary'];

    // ---- CREATE ----
    if ($action === 'create') {
        $cols = [];
        $vals = [];
        $params = [];
        foreach ($allowed as $col) {
            if (isset($fields[$col])) {
                $cols[] = "`$col`";
                $vals[] = '?';
                $params[] = $fields[$col];
            }
        }
        if (empty($cols)) {
            echo json_encode(['success' => false, 'error' => 'No fields provided']);
            exit;
        }
        try {
            $sql = "INSERT INTO `intel_dossiers` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ---- UPDATE ----
    if ($action === 'update') {
        $id = intval($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (isset($fields[$col])) {
                $sets[] = "`$col` = ?";
                $params[] = $fields[$col];
            }
        }
        if (empty($sets)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }
        $params[] = $id;
        try {
            $sql = "UPDATE `intel_dossiers` SET " . implode(',', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ---- DELETE ----
    if ($action === 'delete') {
        $id = intval($input['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }
        try {
            $stmt = $pdo->prepare("DELETE FROM `intel_dossiers` WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ---- RESET (clear all dossiers for re-seed) ----
    if ($action === 'reset') {
        try {
            $pdo->exec("DELETE FROM `intel_dossiers`");
            $pdo->exec("ALTER TABLE `intel_dossiers` AUTO_INCREMENT = 1");
            echo json_encode(['success' => true, 'message' => 'All dossiers cleared']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
