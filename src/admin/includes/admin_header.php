<?php

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../includes/functions.php';



// Determine Base Paths â€” use absolute paths so they work
// regardless of whether served directly or via central router.
$inAdmin = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;

$assetsPath = '/assets';
$rootPath = '/';
$adminPath = '/admin/';



if (!isAdmin()) {

    redirect($rootPath);

}



// Auto-track page views for all admin pages

require_once __DIR__ . '/../../includes/track_pageview.php';

$_pageName = basename($_SERVER['PHP_SELF'], '.php');

$_pageTitle = ucwords(str_replace('_', ' ', $_pageName));

if (!isset($GLOBALS['_page_tracked'])) {

    trackPageView($pdo, $_pageTitle);

    $GLOBALS['_page_tracked'] = true;

}

?>

<!DOCTYPE html>

<html lang="en">



<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Panel | S.T.O.R.M.⚡</title>

    <link rel="icon" href="<?php echo $assetsPath; ?>/images/logo.png">

    <link rel="stylesheet" href="<?php echo $assetsPath; ?>/css/style.css?v=<?php echo time(); ?>">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>


</head>



<body>

    <link rel="stylesheet" href="<?php echo $assetsPath; ?>/css/admin_sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo $assetsPath; ?>/css/admin_topbar.css?v=<?php echo time(); ?>">
    <script src="/assets/js/admin_sidebar.js" defer></script>
    <script src="/assets/js/admin_topbar.js" defer></script>
    <div class="storm-admin-layout">
        <?php include __DIR__ . '/admin_sidebar_v3.php'; ?>
        
        <main class="storm-admin-content">
            <?php include __DIR__ . '/admin_topbar.php'; ?>
            <div class="storm-admin-page-body">
                <?php include __DIR__ . '/modals.php'; ?>
                <!-- Main page content continues below, footer must close </main></div> -->