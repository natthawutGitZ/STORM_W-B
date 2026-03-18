<?php require_once 'functions.php'; ?>
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
</head>

<body>
    <?php include ROOT_PATH . '/includes/navbar_pill.php'; ?>
    <!-- Spacer to offset the fixed floating navbar on non-home pages -->
    <?php if ($active_path !== '' && $active_path !== 'index' && $active_path !== '/'): ?>
        <div style="height: 100px;"></div>
    <?php endif; ?>
    <?php if (!isLoggedIn())
        include ROOT_PATH . '/includes/login_modal.php'; ?>
    <?php include ROOT_PATH . '/includes/register_modal.php'; ?>
