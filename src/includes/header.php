<?php require_once 'functions.php'; ?>
<?php
// Determine barba namespace from current route
$barba_ns = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$barba_ns = str_replace('.php', '', $barba_ns);
if ($barba_ns === '' || $barba_ns === 'index') $barba_ns = 'home';
$barba_ns = preg_replace('/[^a-zA-Z0-9_-]/', '-', $barba_ns);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> | S.T.O.R.M.⚡</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- GSAP & Barba.js for page transitions -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@barba/core@2.10.3/dist/barba.umd.min.js" defer></script>
</head>

<body>
<div data-barba="wrapper">
    <?php include ROOT_PATH . '/includes/navbar_pill.php'; ?>
    <!-- Transition overlay -->
    <div class="barba-overlay">
        <div class="barba-overlay-stripe"></div>
    </div>
    <div data-barba="container" data-barba-namespace="<?php echo htmlspecialchars($barba_ns); ?>">
    <!-- Spacer to offset the fixed floating navbar on non-home pages -->
    <?php if ($active_path !== '' && $active_path !== 'index' && $active_path !== '/'): ?>
        <div style="height: 100px;"></div>
    <?php endif; ?>
    <?php if (!isLoggedIn())
        include ROOT_PATH . '/includes/login_modal.php'; ?>
    <?php include ROOT_PATH . '/includes/register_modal.php'; ?>
