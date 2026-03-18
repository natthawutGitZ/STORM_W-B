<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

$clientID = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/login_google_callback.php';

if (!isset($_GET['code'])) {
    redirect('/?login_error=Google Login Cancelled');
}

$code = $_GET['code'];

// Exchange Code for Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code' => $code,
    'client_id' => $clientID,
    'client_secret' => $clientSecret,
    'redirect_uri' => $redirectUri,
    'grant_type' => 'authorization_code'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    redirect('/?login_error=Failed to retrieve access token from Google');
}

// Get User Info
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/oauth2/v3/userinfo");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userInfo = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($userInfo['sub'])) {
    redirect('/?login_error=Failed to retrieve user info from Google');
}

$googleId = $userInfo['sub'];
$email = $userInfo['email'];
// $picture = $userInfo['picture'];

// Check if Google ID exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$googleId]);
$user = $stmt->fetch();

if ($user) {
    // Login
    $_SESSION['user'] = $user;
    if ($user['role'] === 'admin') {
        redirect('admin/dashboard');
    }
    else {
        redirect('profile');
    }
}
else {
    // Check if email match? (Secondary link?) No, Steam ID is master.
    // Redirect to Link Steam Page
    $_SESSION['google_auth'] = [
        'id' => $googleId,
        'email' => $email,
        // 'name' => $userInfo['name']
    ];
    redirect('link_steam');
}
?>
