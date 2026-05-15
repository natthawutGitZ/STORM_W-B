<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/includes/db.php';
header('Content-Type: application/json');

$S2_PASS = 'Storm888';

// Auto-create deployment table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `personnel_deployment` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `unit_name` VARCHAR(100) NOT NULL,
        `unit_type` VARCHAR(50) DEFAULT '',
        `role_name` VARCHAR(100) NOT NULL,
        `slot_index` INT DEFAULT 0,
        `player_name` VARCHAR(100) DEFAULT '',
        `signed_at` DATETIME DEFAULT NULL,
        `sort_order` INT DEFAULT 0,
        INDEX idx_unit (`unit_name`),
        INDEX idx_slot (`unit_name`, `slot_index`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ---- SEED default slots if table is empty ----
function seedDeploymentSlots($pdo) {
    $count = $pdo->query("SELECT COUNT(*) FROM personnel_deployment")->fetchColumn();
    if ($count > 0) return;

    $units = [
        ['Troop A [1st SFOD-D]', 'SFOD-D', 1, [
            'Troop Commander', 'Troop Command Sergeant Major', 'RTO/JTAC',
            'Team Leader Alpha', 'Assaulter', 'Assaulter', 'Breacher', 'Team Medic',
            'Team Leader Bravo', 'Assaulter', 'Assaulter', 'Breacher', 'Team Medic',
            'Team Leader Zulu', 'Assaulter', 'Assaulter', 'Breacher', 'Team Medic'
        ]],
        ['Troop B', 'SFOD-D', 2, [
            'Team Leader Kilo', 'RTO/JTAC', 'Assaulter', 'Assaulter', 'Breacher', 'Team Medic',
            'Team Leader Zulu', 'Assaulter', 'Assaulter', 'Breacher', 'Team Medic'
        ]],
        ['Thuder 1-1', 'ODA', 3, [
            'Detachment Commander', 'Assis Detachment Commander',
            'Operations Sergeants', 'Assis Operations and Intelligence Sergeant',
            'Medical Sergeants', 'Weapons Sergeants',
            'Medical Sergeants', 'Weapons Sergeants',
            'Communications Sergeants', 'Engineering Sergeants',
            'Communications Sergeants', 'Engineering Sergeants'
        ]],
        ['Tempest 1-2', 'ODA', 4, [
            'Detachment Commander', 'Assis Detachment Commander',
            'Operations Sergeants', 'Assis Operations and Intelligence Sergeant',
            'Medical Sergeants', 'Weapons Sergeants',
            'Medical Sergeants', 'Weapons Sergeants',
            'Communications Sergeants', 'Engineering Sergeants',
            'Communications Sergeants', 'Engineering Sergeants'
        ]],
        ['Viking 1-1 [75th Ranger]', 'RANGER', 5, [
            'Squad Leader', 'Combat Medic',
            'Team Leader', 'Automatic Rifleman', 'Grenadier', 'Rifleman',
            'Team Leader', 'Automatic Rifleman', 'Grenadier', 'Rifleman'
        ]],
        ['Viking 1-2 [75th Ranger]', 'RANGER', 6, [
            'Squad Leader', 'Combat Medic',
            'Team Leader', 'Automatic Rifleman', 'Grenadier', 'Rifleman',
            'Team Leader', 'Automatic Rifleman', 'Grenadier', 'Rifleman'
        ]],
        ['Viking 1-3 [75th Ranger]', 'RANGER', 7, [
            'Squad Leader',
            'Gun Team Leader', 'Machine Gunner',
            'Gun Team Leader', 'Machine Gunner',
            'Gun Team Leader', 'AT Specialist'
        ]],
        ['Thor [F-35]', 'AIR', 8, [
            'Pilot Thor 1', 'Pilot Thor 2'
        ]],
        ['Cyclone [SOAR]', 'AIR', 9, [
            'Pilot', 'Pilot', 'Pilot', 'Pilot'
        ]]
    ];

    $stmt = $pdo->prepare("INSERT INTO personnel_deployment (unit_name, unit_type, role_name, slot_index, sort_order) VALUES (?, ?, ?, ?, ?)");
    foreach ($units as $u) {
        $unitName = $u[0];
        $unitType = $u[1];
        $sortOrder = $u[2];
        $roles = $u[3];
        foreach ($roles as $idx => $role) {
            $stmt->execute([$unitName, $unitType, $role, $idx, $sortOrder]);
        }
    }
}

// ---- LIST all slots ----
if ($method === 'GET' && $action === 'list') {
    seedDeploymentSlots($pdo);
    try {
        $rows = $pdo->query("SELECT * FROM personnel_deployment ORDER BY sort_order ASC, slot_index ASC")->fetchAll(PDO::FETCH_ASSOC);
        // Group by unit
        $units = [];
        foreach ($rows as $row) {
            $name = $row['unit_name'];
            if (!isset($units[$name])) {
                $units[$name] = [
                    'unit_name' => $name,
                    'unit_type' => $row['unit_type'],
                    'sort_order' => $row['sort_order'],
                    'slots' => []
                ];
            }
            $units[$name]['slots'][] = [
                'id' => (int)$row['id'],
                'role_name' => $row['role_name'],
                'slot_index' => (int)$row['slot_index'],
                'player_name' => $row['player_name'] ?: '',
                'signed_at' => $row['signed_at']
            ];
        }
        echo json_encode(['success' => true, 'data' => array_values($units)]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- SIGNUP: assign player name to a slot ----
if ($method === 'POST' && $action === 'signup') {
    $input = json_decode(file_get_contents('php://input'), true);
    $slotId = intval($input['slot_id'] ?? 0);
    $playerName = trim($input['player_name'] ?? '');

    if (!$slotId || !$playerName) {
        echo json_encode(['success' => false, 'error' => 'Missing slot_id or player_name']);
        exit;
    }

    // Check if slot is already taken
    $check = $pdo->prepare("SELECT player_name FROM personnel_deployment WHERE id = ?");
    $check->execute([$slotId]);
    $existing = $check->fetch();
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Slot not found']);
        exit;
    }
    if (!empty($existing['player_name'])) {
        echo json_encode(['success' => false, 'error' => 'Slot already occupied by ' . $existing['player_name']]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE personnel_deployment SET player_name = ?, signed_at = NOW() WHERE id = ?");
        $stmt->execute([$playerName, $slotId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- WITHDRAW: remove player name from a slot ----
if ($method === 'POST' && $action === 'withdraw') {
    $input = json_decode(file_get_contents('php://input'), true);
    $slotId = intval($input['slot_id'] ?? 0);

    if (!$slotId) {
        echo json_encode(['success' => false, 'error' => 'Missing slot_id']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE personnel_deployment SET player_name = '', signed_at = NULL WHERE id = ?");
        $stmt->execute([$slotId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- RESET: clear all player assignments (admin only) ----
if ($method === 'POST' && $action === 'reset') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pass = $input['password'] ?? '';
    if ($pass !== $S2_PASS) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ACCESS DENIED']);
        exit;
    }
    try {
        $pdo->exec("UPDATE personnel_deployment SET player_name = '', signed_at = NULL");
        echo json_encode(['success' => true, 'message' => 'All assignments cleared']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---- RESEED: drop and recreate all slots (admin only) ----
if ($method === 'POST' && $action === 'reseed') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pass = $input['password'] ?? '';
    if ($pass !== $S2_PASS) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ACCESS DENIED']);
        exit;
    }
    try {
        $pdo->exec("DELETE FROM personnel_deployment");
        $pdo->exec("ALTER TABLE personnel_deployment AUTO_INCREMENT = 1");
        seedDeploymentSlots($pdo);
        echo json_encode(['success' => true, 'message' => 'All slots reseeded']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
