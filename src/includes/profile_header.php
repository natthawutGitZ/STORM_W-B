<?php require_once 'functions.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | S.T.O.R.M.⚡</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <link rel="stylesheet" href="<?php echo $rootPath ?? '/'; ?>assets/css/admin_sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $rootPath ?? '/'; ?>assets/css/admin_topbar.css?v=<?php echo time(); ?>">
    <script src="<?php echo $rootPath ?? '/'; ?>assets/js/admin_sidebar.js" defer></script>
    <script src="<?php echo $rootPath ?? '/'; ?>assets/js/admin_topbar.js" defer></script>

    <?php $useAdminLayout = true; ?>
    <div class="storm-admin-layout">
        <?php include ROOT_PATH . '/includes/user_sidebar.php'; ?>
        
        <main class="storm-admin-content">
            <?php include ROOT_PATH . '/includes/user_topbar.php'; ?>
            <div class="storm-admin-page-body">
                <!-- Main content continues here -->