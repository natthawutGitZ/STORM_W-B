<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['google_auth'])) {
    redirect('/');
}

$googleAuth = $_SESSION['google_auth'];
$steamid = trim($_POST['steamid'] ?? '');

if (empty($steamid)) {
    // Should be handling post form, but let's just error
    die("Steam ID Required");
}

// Check Steam ID
if (!is_numeric($steamid) || strlen($steamid) != 17 || substr($steamid, 0, 4) != '7656') {
    die("Invalid Steam ID");
}

try {
    // Check if Steam ID exists in DB
    $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid = ?");
    $stmt->execute([$steamid]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists -> Link Google ID
        $stmt = $pdo->prepare("UPDATE users SET google_id = ?, email = ? WHERE id = ?");
        $stmt->execute([$googleAuth['id'], $googleAuth['email'], $user['id']]);

        // Refresh User
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();

        $_SESSION['user'] = $user;
    } else {
        // User does not exist -> Create New User
        // Verify Steam ID first via API
        $apiKey = 'B94B9BCB6D873EDCE062274CC3E52AA9';
        $apiUrl = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$apiKey&steamids=$steamid";

        $response = @file_get_contents($apiUrl);
        if (!$response)
            die("Steam API Error");

        $json = json_decode($response, true);
        $players = $json['response']['players'] ?? [];

        if (empty($players))
            die("Steam ID not found");

        $steamUser = $players[0];

        // Generate partial credentials (no password needed really if google auth, but good to have)
        $gen_username = 'User_' . substr($steamid, -6);
        $gen_password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($gen_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (steamid, google_id, email, personaname, avatar, profileurl, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $steamid,
            $googleAuth['id'],
            $googleAuth['email'],
            $steamUser['personaname'],
            $steamUser['avatarfull'],
            $steamUser['profileurl'],
            $gen_username,
            $hashed_password
        ]);

        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $_SESSION['user'] = $user;
    }

    unset($_SESSION['google_auth']);
    redirect('profile');

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
