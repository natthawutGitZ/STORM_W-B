<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}





// Google Credentials (loaded from environment)

$clientID = getenv('GOOGLE_CLIENT_ID');

$clientSecret = getenv('GOOGLE_CLIENT_SECRET');

$redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/login_google_callback.php';



// Params

$params = [
    'client_id' => $clientID,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'email profile openid',
    'access_type' => 'online'

];



$url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);



header('Location: ' . $url);

exit;

?>
