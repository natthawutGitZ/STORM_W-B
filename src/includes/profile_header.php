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

<body class="bg-surface text-gray-200">
    <?php include ROOT_PATH . '/includes/navbar_pill.php'; ?>
    <div style="height: 100px;"></div>