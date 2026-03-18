<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Register');

// Optional: Require login if you still want only logged-in users to see this
// if (!isLoggedIn()) {
//     redirect('login.php');
// }

$user = getUser();
include ROOT_PATH . '/includes/header.php';
?>

<div class="section"
    style="text-align: center; min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center;">
    <h2 style="font-size: 3rem; margin-bottom: 1rem; color: #4caf50;">APPLICATIONS ARE OPEN</h2>
    <p style="font-size: 1.2rem; margin-bottom: 3rem; max-width: 600px; color: var(--text-muted);">
        We are currently looking for new operators to join our ranks. If you think you have what it takes, click the
        button below to fill out the application form.
    </p>

    <?php
    // Fetch Main Form ID
    $mainFormId = null;
    $stmt = $pdo->query("SELECT id FROM forms WHERE is_main_application = 1 LIMIT 1");
    if ($row = $stmt->fetch()) {
        $mainFormId = $row['id'];
    }

    // Default Link if no main form set (or handle as hidden/disabled)
    $applyLink = $mainFormId ? "view_form.php?id=$mainFormId" : "#";
    $onclick = $mainFormId ? "" : "Swal.fire('Notice', 'No application form is currently active.', 'info'); return false;";
    ?>

    <a href="<?php echo $applyLink; ?>" onclick="<?php echo $onclick; ?>" class="btn"
        style="padding: 1.5rem 4rem; font-size: 1.2rem;">
        APPLY NOW
    </a>

    <div style="margin-top: 5rem; text-align: center;">
        <div style="display: flex; gap: 30px; justify-content: center; margin-bottom: 2rem;">
            <a href="https://discord.gg/djtw8g9tDC" target="_blank" class="social-box">
                <i class="fab fa-discord"></i>
            </a>
            <a href="https://www.youtube.com/channel/UC-zlNj2GKY46E--L7uP4V6g" target="_blank" class="social-box">
                <i class="fab fa-youtube"></i>
            </a>
            <a href="https://www.facebook.com/profile.php?id=100086319649339" target="_blank" class="social-box">
                <i class="fas fa-globe"></i>
            </a>
        </div>

        <p style="color: #ccc; font-size: 1.1rem; margin-bottom: 0.5rem; letter-spacing: 1px;">
            join our discord to get started!
        </p>
        <h2
            style="font-size: 2.5rem; color: #fff; letter-spacing: 3px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; text-shadow: 0 0 10px rgba(0,0,0,0.5);">
            WHERE TO FIND US
        </h2>
    </div>

    <style>
        .social-box {
            width: 80px;
            height: 80px;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            text-decoration: none;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.3);
        }

        .social-box:hover {
            background: white;
            color: #000;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.2);
        }
    </style>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
