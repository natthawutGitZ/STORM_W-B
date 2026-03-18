<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$username = sanitize($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$steamid = trim($_POST['steamid'] ?? '');

// Validation
if (empty($username) || empty($password) || empty($steamid)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Check if Steam ID is numeric and starts with 7656 (basic check)
if (!is_numeric($steamid) || strlen($steamid) != 17 || substr($steamid, 0, 4) != '7656') {
    echo json_encode(['success' => false, 'message' => 'Invalid Steam ID format (Must be 64-bit ID starting with 7656).']);
    exit;
}

try {
    // 1. Check Duplicates (Username or Steam ID)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR steamid = ?");
    $stmt->execute([$username, $steamid]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or Steam ID already registered.']);
        exit;
    }

    // 2. Verify Steam ID via API
    $apiKey = 'B94B9BCB6D873EDCE062274CC3E52AA9'; // Hardcoded from login.php
    $apiUrl = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid";

    $response = @file_get_contents($apiUrl);
    if (!$response) {
        echo json_encode(['success' => false, 'message' => 'Failed to connect to Steam API for verification.']);
        exit;
    }

    $json = json_decode($response, true);
    $players = $json['response']['players'] ?? [];

    if (empty($players)) {
        echo json_encode(['success' => false, 'message' => 'Steam ID does not exist or profile is private/not found.']);
        exit;
    }

    $steamUser = $players[0];

    // 3. Create User
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (steamid, personaname, avatar, profileurl, username, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $steamUser['steamid'],
        $steamUser['personaname'],
        $steamUser['avatarfull'],
        $steamUser['profileurl'],
        $username,
        $hashed_password
    ]);

    // 4. Auto Login
    $userId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $_SESSION['user'] = $user;

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>
