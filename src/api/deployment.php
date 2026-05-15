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
            // If it's not a placeholder role
            if ($row['role_name'] !== '__EMPTY_UNIT_PLACEHOLDER__') {
                $units[$name]['slots'][] = [
                    'id' => (int)$row['id'],
                    'role_name' => $row['role_name'],
                    'slot_index' => (int)$row['slot_index'],
                    'player_name' => $row['player_name'] ?: '',
                    'signed_at' => $row['signed_at']
                ];
            }
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

    if (!$slotId || !$playerName) { echo json_encode(['success' => false, 'error' => 'Missing data']); exit; }

    $check = $pdo->prepare("SELECT player_name FROM personnel_deployment WHERE id = ?");
    $check->execute([$slotId]);
    $existing = $check->fetch();
    if (!$existing) { echo json_encode(['success' => false, 'error' => 'Slot not found']); exit; }
    if (!empty($existing['player_name'])) { echo json_encode(['success' => false, 'error' => 'Slot already occupied']); exit; }

    try {
        $stmt = $pdo->prepare("UPDATE personnel_deployment SET player_name = ?, signed_at = NOW() WHERE id = ?");
        $stmt->execute([$playerName, $slotId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}

// ---- WITHDRAW: remove player name from a slot ----
if ($method === 'POST' && $action === 'withdraw') {
    $input = json_decode(file_get_contents('php://input'), true);
    $slotId = intval($input['slot_id'] ?? 0);
    if (!$slotId) { echo json_encode(['success' => false, 'error' => 'Missing slot_id']); exit; }
    try {
        $stmt = $pdo->prepare("UPDATE personnel_deployment SET player_name = '', signed_at = NULL WHERE id = ?");
        $stmt->execute([$slotId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
    exit;
}

// =========================================================
// S2 PROTECTED ROUTES (Edit Roster)
// =========================================================

function verifyAuth($input) {
    global $S2_PASS;
    if (($input['password'] ?? '') !== $S2_PASS) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ACCESS DENIED']);
        exit;
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'reset') {
        verifyAuth($input);
        try {
            $pdo->exec("UPDATE personnel_deployment SET player_name = '', signed_at = NULL");
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'add_unit') {
        verifyAuth($input);
        $unitName = trim($input['unit_name'] ?? '');
        $unitType = trim($input['unit_type'] ?? 'OTHER');
        if (!$unitName) { echo json_encode(['success' => false, 'error' => 'Missing unit name']); exit; }
        try {
            $sortOrder = $pdo->query("SELECT MAX(sort_order) FROM personnel_deployment")->fetchColumn() + 1;
            // Create a placeholder slot so the unit exists
            $stmt = $pdo->prepare("INSERT INTO personnel_deployment (unit_name, unit_type, role_name, slot_index, sort_order) VALUES (?, ?, '__EMPTY_UNIT_PLACEHOLDER__', 0, ?)");
            $stmt->execute([$unitName, $unitType, $sortOrder]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'edit_unit') {
        verifyAuth($input);
        $oldUnitName = trim($input['old_unit_name'] ?? '');
        $newUnitName = trim($input['new_unit_name'] ?? '');
        $newUnitType = trim($input['new_unit_type'] ?? '');
        if (!$oldUnitName || !$newUnitName) { echo json_encode(['success' => false, 'error' => 'Missing names']); exit; }
        try {
            $stmt = $pdo->prepare("UPDATE personnel_deployment SET unit_name = ?, unit_type = ? WHERE unit_name = ?");
            $stmt->execute([$newUnitName, $newUnitType, $oldUnitName]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'delete_unit') {
        verifyAuth($input);
        $unitName = trim($input['unit_name'] ?? '');
        if (!$unitName) { echo json_encode(['success' => false, 'error' => 'Missing unit name']); exit; }
        try {
            $stmt = $pdo->prepare("DELETE FROM personnel_deployment WHERE unit_name = ?");
            $stmt->execute([$unitName]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'add_role') {
        verifyAuth($input);
        $unitName = trim($input['unit_name'] ?? '');
        $roleName = trim($input['role_name'] ?? '');
        if (!$unitName || !$roleName) { echo json_encode(['success' => false, 'error' => 'Missing data']); exit; }
        try {
            // Get unit details from an existing slot
            $unitDetails = $pdo->prepare("SELECT unit_type, sort_order, MAX(slot_index) as max_idx FROM personnel_deployment WHERE unit_name = ? GROUP BY unit_name");
            $unitDetails->execute([$unitName]);
            $res = $unitDetails->fetch();
            if (!$res) { echo json_encode(['success' => false, 'error' => 'Unit not found']); exit; }

            $stmt = $pdo->prepare("INSERT INTO personnel_deployment (unit_name, unit_type, role_name, slot_index, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$unitName, $res['unit_type'], $roleName, $res['max_idx'] + 1, $res['sort_order']]);

            // Remove placeholder if it exists
            $pdo->prepare("DELETE FROM personnel_deployment WHERE unit_name = ? AND role_name = '__EMPTY_UNIT_PLACEHOLDER__'")->execute([$unitName]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'edit_role') {
        verifyAuth($input);
        $slotId = intval($input['slot_id'] ?? 0);
        $roleName = trim($input['role_name'] ?? '');
        if (!$slotId || !$roleName) { echo json_encode(['success' => false, 'error' => 'Missing data']); exit; }
        try {
            $stmt = $pdo->prepare("UPDATE personnel_deployment SET role_name = ? WHERE id = ?");
            $stmt->execute([$roleName, $slotId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }

    if ($action === 'delete_role') {
        verifyAuth($input);
        $slotId = intval($input['slot_id'] ?? 0);
        if (!$slotId) { echo json_encode(['success' => false, 'error' => 'Missing data']); exit; }
        try {
            // Check if this is the last slot of the unit
            $slotQuery = $pdo->prepare("SELECT unit_name, unit_type, sort_order FROM personnel_deployment WHERE id = ?");
            $slotQuery->execute([$slotId]);
            $slot = $slotQuery->fetch();

            if ($slot) {
                $countQuery = $pdo->prepare("SELECT COUNT(*) FROM personnel_deployment WHERE unit_name = ?");
                $countQuery->execute([$slot['unit_name']]);
                $count = $countQuery->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM personnel_deployment WHERE id = ?");
                $stmt->execute([$slotId]);

                // If it was the last real role, insert a placeholder so the unit isn't lost
                if ($count <= 1) {
                    $insertPH = $pdo->prepare("INSERT INTO personnel_deployment (unit_name, unit_type, role_name, slot_index, sort_order) VALUES (?, ?, '__EMPTY_UNIT_PLACEHOLDER__', 0, ?)");
                    $insertPH->execute([$slot['unit_name'], $slot['unit_type'], $slot['sort_order']]);
                }
            }
            echo json_encode(['success' => true]);
        } catch (PDOException $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
