<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isset($_SESSION['google_auth'])) {
    redirect('/');
}

$googleAuth = $_SESSION['google_auth'];
$pageTitle = 'Link Steam Account';
include ROOT_PATH . '/includes/header.php';
?>

<div class="section" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div
        style="background: rgba(30, 30, 35, 0.9); padding: 40px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); max-width: 500px; width: 100%;">
        <h2 style="color: #fff; text-align: center; margin-bottom: 20px;">Complete Registration</h2>
        <p style="color: #aaa; text-align: center; margin-bottom: 30px;">
            You are signing in with Google (
            <?php echo htmlspecialchars($googleAuth['email']); ?>).<br>
            Please provide your <b>Steam ID</b> to link or create your account.
        </p>

        <form action="link_steam_process.php" method="POST">
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #fff; margin-bottom: 8px;">Steam ID (64-bit)</label>
                <input type="text" name="steamid" class="login-input"
                    style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #444; background: #222; color: #fff;"
                    required placeholder="7656119...">
                <small style="color: #666; display: block; margin-top: 5px;">Must be a valid Steam ID used for Arma
                    Reforger.</small>
            </div>
            <button type="submit" class="btn" style="width: 100%; padding: 12px;">Link & Login</button>
        </form>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
