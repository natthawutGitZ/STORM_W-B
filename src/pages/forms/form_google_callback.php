<?php
// src/form_google_callback.php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

// session_start(); // Already started in functions.php

$token = $_POST['credential'] ?? '';
$redirect = $_POST['redirect'] ?? 'index.php';

if (!$token) {
    die("No token provided");
}

// Verify Token
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$payload = json_decode($response, true);

if (!isset($payload['email_verified']) || !$payload['email_verified']) {
    die("Invalid Token");
}

$google_id = $payload['sub'];
$email = $payload['email'];
$name = $payload['name'];
$picture = $payload['picture'];

// Session-only login for form submissions - no database interaction

// Don't save to database - just use session for form identification
// Create a temporary user object for the session only
$user = [
    'id' => 'google_' . $google_id, // Temporary ID (not in DB)
    'email' => $email,
    'google_id' => $google_id,
    'personaname' => $name,
    'avatar' => $picture,
    'username' => explode('@', $email)[0],
    'role' => 'guest' // Mark as guest, not a real member
];

// SET SEPARATE SESSION
$_SESSION['google_form_user'] = $user;

header("Location: " . $redirect);
exit;
?>
