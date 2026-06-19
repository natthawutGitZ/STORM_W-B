<?php require_once 'functions.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | S.T.O.R.M.⚡</title>
    <link rel="icon" href="/assets/images/logo.png">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: { preflight: false },
            theme: {
                extend: {
                    colors: {
                        gold: { DEFAULT: '#c5a059', light: '#e8c97a', dim: '#c5a05926' },
                        surface: { DEFAULT: '#0f0f12', card: '#111114', hover: '#1a1a1e' },
                        zinc: { 850: '#1c1c22', 750: '#2a2a32' }
                    },
                    borderRadius: { xl: '12px', '2xl': '16px' }
                }
            }
        }
    </script>
</head>

<body>
    <link rel="stylesheet" href="/assets/css/admin_sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/css/admin_topbar.css?v=<?php echo time(); ?>">
    <script src="/assets/js/admin_sidebar.js" defer></script>
    <script src="/assets/js/admin_topbar.js" defer></script>
    
    <!-- Override: restore original user page background -->
    <style>
        .storm-admin-layout {
            background: var(--primary-color, #0a0a0a);
        }
        .storm-admin-page-body {
            background: var(--primary-color, #0a0a0a);
        }
        /* Remove old navbar padding-top since sidebar layout handles it */
        .storm-admin-page-body .dashboard-container.member-view {
            padding-top: 10px !important;
        }
        .storm-admin-page-body .dashboard-container {
            padding-top: 10px;
        }
    </style>
    
    <div class="storm-admin-layout">
        <?php include ROOT_PATH . '/includes/user_sidebar.php'; ?>
        
        <main class="storm-admin-content">
            <?php include ROOT_PATH . '/includes/user_topbar.php'; ?>
            <div class="storm-admin-page-body">