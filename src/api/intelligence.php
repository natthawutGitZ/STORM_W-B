<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
header('Content-Type: application/json');

$S2_PASS = 'Storm888';

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `intel_operations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `codename` VARCHAR(100) NOT NULL,
        `status` ENUM('ACTIVE','COMPLETED','FAILED','PENDING') DEFAULT 'PENDING',
        `priority` ENUM('CRITICAL','HIGH','MEDIUM','LOW') DEFAULT 'MEDIUM',
        `brief` TEXT,
        `commander` VARCHAR(100) DEFAULT '',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `intel_reports` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(200) NOT NULL,
        `classification` ENUM('TOP SECRET','SECRET','CONFIDENTIAL','UNCLASSIFIED') DEFAULT 'UNCLASSIFIED',
        `content` TEXT,
        `source` VARCHAR(100) DEFAULT 'HUMINT',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `intel_sorties` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `callsign` VARCHAR(50) NOT NULL,
        `mission_type` VARCHAR(50) DEFAULT 'RECON',
        `location` VARCHAR(100) DEFAULT '',
        `status` ENUM('DEPLOYED','RTB','STANDBY','MIA') DEFAULT 'STANDBY',
        `personnel` INT DEFAULT 0,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `intel_brief` (
        `id` INT PRIMARY KEY DEFAULT 1,
        `doc_title` VARCHAR(200) DEFAULT 'OPERATION STORMSURGE // BRIEFING DOCUMENT',
        `op_name` VARCHAR(200) DEFAULT 'OPERATION STORMSURGE',
        `classification` VARCHAR(50) DEFAULT 'CRITICAL',
        `status` VARCHAR(50) DEFAULT 'ACTIVE',
        `ao_location` VARCHAR(100) DEFAULT 'COLOMBIA',
        `team` VARCHAR(100) DEFAULT 'ODA 0121',
        `start_date` VARCHAR(50) DEFAULT '',
        `opord` TEXT,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO `intel_brief` (`id`) VALUES (1)");

} catch (PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// READ - no auth required
if ($method === 'GET' && $action === 'list') {
    $type = $_GET['type'] ?? 'operations';
    try {
        if ($type === 'operations') {
            $rows = $pdo->query("SELECT * FROM intel_operations ORDER BY FIELD(priority,'CRITICAL','HIGH','MEDIUM','LOW'), updated_at DESC")->fetchAll();
        } elseif ($type === 'reports') {
            $rows = $pdo->query("SELECT * FROM intel_reports ORDER BY created_at DESC LIMIT 10")->fetchAll();
        } elseif ($type === 'sorties') {
            $rows = $pdo->query("SELECT * FROM intel_sorties ORDER BY FIELD(status,'DEPLOYED','RTB','STANDBY','MIA'), updated_at DESC")->fetchAll();
        } else {
            echo json_encode(['error' => 'Invalid type']); exit;
        }
        echo json_encode(['success' => true, 'data' => $rows]); exit;
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]); exit;
    }
}

// GET BRIEF
if ($method === 'GET' && $action === 'get_brief') {
    try {
        $row = $pdo->query("SELECT * FROM intel_brief WHERE id=1")->fetch();
        echo json_encode(['success' => true, 'data' => $row ?: null]); exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'data' => null]); exit;
    }
}

// SAVE BRIEF (auth required)
if ($method === 'POST' && $action === 'save_brief') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $pass = $input['password'] ?? '';
    if ($pass !== $S2_PASS) {
        http_response_code(403);
        echo json_encode(['error' => 'ACCESS DENIED — Invalid Authorization']); exit;
    }
    try {
        $fields = ['doc_title','op_name','classification','status','ao_location','team','start_date','opord'];
        $updates = [];
        $values = [];
        foreach ($fields as $f) {
            if (isset($input[$f])) {
                $updates[] = "`$f`=?";
                $values[] = $input[$f];
            }
        }
        if (!empty($updates)) {
            $sql = "UPDATE intel_brief SET " . implode(',', $updates) . " WHERE id=1";
            $pdo->prepare($sql)->execute($values);
        }
        echo json_encode(['success' => true]); exit;
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]); exit;
    }
}

// WRITE operations - require S2 password
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $pass = $input['password'] ?? '';
    if ($pass !== $S2_PASS) {
        http_response_code(403);
        echo json_encode(['error' => 'ACCESS DENIED — Invalid S2 Authorization']); exit;
    }

    $type = $input['type'] ?? '';
    $table = match($type) {
        'operations' => 'intel_operations',
        'reports' => 'intel_reports',
        'sorties' => 'intel_sorties',
        default => null
    };
    if (!$table) {
        echo json_encode(['error' => 'Invalid type']); exit;
    }

    try {
        if ($action === 'create') {
            $fields = $input['fields'] ?? [];
            if (empty($fields)) { echo json_encode(['error' => 'No fields']); exit; }
            $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($fields)));
            $phs = implode(',', array_fill(0, count($fields), '?'));
            $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($phs)");
            $stmt->execute(array_values($fields));
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } elseif ($action === 'update') {
            $id = intval($input['id'] ?? 0);
            $fields = $input['fields'] ?? [];
            if (!$id || empty($fields)) { echo json_encode(['error' => 'Missing id/fields']); exit; }
            $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($fields)));
            $stmt = $pdo->prepare("UPDATE $table SET $sets WHERE id=?");
            $stmt->execute([...array_values($fields), $id]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete') {
            $id = intval($input['id'] ?? 0);
            if (!$id) { echo json_encode(['error' => 'Missing id']); exit; }
            $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid request']);
