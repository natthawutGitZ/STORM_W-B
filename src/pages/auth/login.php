<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/steam_auth.php';
require_once ROOT_PATH . '/includes/admin_log.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Login');

// If already logged in, redirect
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard');
    } else {
        redirect('profile');
    }
}

// REPLACE WITH YOUR ACTUAL API KEY AND DOMAIN
$apiKey = 'B94B9BCB6D873EDCE062274CC3E52AA9';
$domain = 'http://' . $_SERVER['HTTP_HOST'] . '/';

$steam = new SteamAuth($apiKey, $domain);

// Handle Steam Login
if (isset($_GET['openid_mode'])) {
    $steamid = $steam->validate();
    if ($steamid) {
        $userInfo = $steam->getUserInfo($steamid);

        if ($userInfo) {
            // Check if user exists, if not create
            $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid = ?");
            $stmt->execute([$steamid]);
            $user = $stmt->fetch();

            if (!$user) {
                try {
                    // Auto-generate credentials
                    $gen_username = 'User_' . substr($userInfo['steamid'], -6);

                    // Generate random password (compatible with older PHP versions)
                    if (function_exists('random_bytes')) {
                        $gen_password = bin2hex(random_bytes(4));
                    } elseif (function_exists('openssl_random_pseudo_bytes')) {
                        $gen_password = bin2hex(openssl_random_pseudo_bytes(4));
                    } else {
                        $gen_password = substr(md5(mt_rand()), 0, 8);
                    }

                    $hashed_password = password_hash($gen_password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("INSERT INTO users (steamid, personaname, avatar, profileurl, username, password, generated_password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $userInfo['steamid'],
                        $userInfo['personaname'],
                        $userInfo['avatarfull'],
                        $userInfo['profileurl'],
                        $gen_username,
                        $hashed_password,
                        $gen_password
                    ]);
                    // Fetch the newly created user
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid = ?");
                    $stmt->execute([$steamid]);
                    $user = $stmt->fetch();
                } catch (PDOException $e) {
                    redirect('/?login_error=Database Error: ' . urlencode($e->getMessage()));
                    exit;
                }
            } else {
                // Update user info - ONLY update profile URL
                $stmt = $pdo->prepare("UPDATE users SET profileurl = ? WHERE steamid = ?");
                $stmt->execute([
                    $userInfo['profileurl'],
                    $steamid
                ]);
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE steamid = ?");
                $stmt->execute([$steamid]);
                $user = $stmt->fetch();
            }

            $_SESSION['user'] = $user;
            logAdminAction($pdo, 'login_steam', 'user', $user['id'], ['name' => $user['personaname']]);
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard');
            } else {
                redirect('profile?login=success');
            }
        } else {
            redirect('/?login_error=Failed to get user info from Steam.');
        }
    } else {
        redirect('/?login_error=Steam login failed.');
    }
}

// Handle Username/Password Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        redirect('/?login_error=Please enter both username and password.');
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && $user['password'] && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user;
                logAdminAction($pdo, 'login_password', 'user', $user['id'], ['name' => $user['personaname']]);
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard');
                } else {
                    redirect('profile?login=success');
                }
            } else {
                redirect('/?login_error=Invalid username or password.');
            }
        } catch (PDOException $e) {
            redirect('/?login_error=Database error: ' . urlencode($e->getMessage()));
        }
    }
} else {
    // If accessed directly without params, redirect to index
    if (!isset($_GET['openid_mode'])) {
        redirect('/');
    }
}
?>
