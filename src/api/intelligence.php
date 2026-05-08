<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
header('Content-Type: application/json');

$S2_PASS = 'S2';

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

    // Seed sample data if empty
    $c = $pdo->query("SELECT COUNT(*) FROM intel_operations")->fetchColumn();
    if ($c == 0) {
        $pdo->exec("INSERT INTO intel_operations (codename,status,priority,brief,commander) VALUES
            ('IRON VANGUARD','ACTIVE','CRITICAL','Secure northern perimeter and establish FOB Alpha.','CDR. Reaper'),
            ('SILENT DAGGER','COMPLETED','HIGH','Infiltrate enemy comms relay and extract intel package.','CPT. Phantom'),
            ('CRIMSON TIDE','PENDING','MEDIUM','Coordinate naval blockade for supply interdiction.','ADM. Storm')");
        $pdo->exec("INSERT INTO intel_reports (title,classification,content,source) VALUES
            ('Enemy Force Disposition Update','SECRET','Hostile forces repositioning along grid reference 4427. Estimated battalion-strength element.','SIGINT'),
            ('Supply Route Compromised','CONFIDENTIAL','Main supply route ALPHA is under surveillance. Recommend alternate route BRAVO.','HUMINT'),
            ('Cyber Threat Advisory','TOP SECRET','Advanced persistent threat detected targeting C2 infrastructure. Immediate patching required.','CYBER')");
        $pdo->exec("INSERT INTO intel_sorties (callsign,mission_type,location,status,personnel) VALUES
            ('EAGLE-6','RECON','Grid 4427-NE','DEPLOYED',4),
            ('SHADOW-2','STRIKE','Sector BRAVO','STANDBY',8),
            ('VIPER-1','EXTRACTION','LZ DELTA','RTB',6)");
    }
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
