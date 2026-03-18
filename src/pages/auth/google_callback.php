<?php
// src/google_callback.php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

// Get the token from POST
$token = $_POST['credential'] ?? ''; // Google sends 'credential'

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'No token provided']);
    exit;
}

// Verify Token via Google API
// We use CURL to call the tokeninfo endpoint
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$payload = json_decode($response, true);

if (!isset($payload['email_verified']) || !$payload['email_verified']) {
    echo json_encode(['success' => false, 'message' => 'Invalid or unverified token']);
    exit;
}

// Token is valid
$google_id = $payload['sub'];
$email = $payload['email'];
$name = $payload['name'];
$picture = $payload['picture'];

// Check if user exists by Google ID
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$google_id]);
$user = $stmt->fetch();

if (!$user) {
    // Check by email (link accounts if email exists)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Link Account
        $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
        $stmt->execute([$google_id, $user['id']]);

        // Refresh User
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
    } else {
        // Create New User
        // Generate a username based on email name or random
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;

        // Ensure unique username
        while (true) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetchColumn() == 0)
                break;
            $username = $baseUsername . '_' . $counter++;
        }

        $password = bin2hex(random_bytes(8)); // Random password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id, password, personaname, avatar, role, status) VALUES (?, ?, ?, ?, ?, ?, 'user', 'Active')");
        $stmt->execute([
            $username,
            $email,
            $google_id,
            $hashed_password,
            $name,
            $picture
        ]);

        // Get New User
        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$google_id]);
        $user = $stmt->fetch();
    }
}

// Log In
$_SESSION['user'] = $user;

// Respond with Success (Frontend will redirect)
echo json_encode(['success' => true]);
?>
