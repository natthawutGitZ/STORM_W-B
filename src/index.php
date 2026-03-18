<?php
define('ROOT_PATH', __DIR__);
$req = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($req, '/');
$path = str_replace('.php', '', $path);
if ($path === '' || $path === 'index') {
    require ROOT_PATH . '/pages/core/home.php';
    exit;
}
$r = [
    'login' => 'pages/auth/login.php',
    'register' => 'pages/auth/register.php',
    'register_process' => 'pages/auth/register_process.php',
    'logout' => 'pages/auth/logout.php',
    'link_steam' => 'pages/auth/link_steam.php',
    'link_steam_process' => 'pages/auth/link_steam_process.php',
    'login_google' => 'pages/auth/login_google.php',
    'login_google_callback' => 'pages/auth/login_google_callback.php',
    'login_modal' => 'pages/auth/login_modal.php',
    'form_login' => 'pages/auth/form_login.php',
    'form_logout' => 'pages/auth/form_logout.php',
    'google_callback' => 'pages/auth/google_callback.php',
    'profile' => 'pages/user/profile.php',
    'edit_profile' => 'pages/user/edit_profile.php',
    'update_resume' => 'pages/user/update_resume.php',
    'upload_slip' => 'pages/user/upload_slip.php',
    'campaigns' => 'pages/public/campaigns.php',
    'campaign_detail' => 'pages/public/campaign_detail.php',
    'awards' => 'pages/public/awards.php',
    'award_detail' => 'pages/public/award_detail.php',
    'ranks' => 'pages/public/ranks.php',
    'rank_detail' => 'pages/public/rank_detail.php',
    'positions' => 'pages/public/positions.php',
    'position_detail' => 'pages/public/position_detail.php',
    'qualifications' => 'pages/public/qualifications.php',
    'qualification_detail' => 'pages/public/qualification_detail.php',
    'donate' => 'pages/public/donate.php',
    'media' => 'pages/public/media.php',
    'chain' => 'pages/public/chain.php',
    'Unit_Structure' => 'pages/public/Unit_Structure.php',
    'bot_api_proxy' => 'pages/public/bot_api_proxy.php',
    'applications' => 'pages/public/applications.php',
    'view_form' => 'pages/forms/view_form.php',
    'form_google_callback' => 'pages/forms/form_google_callback.php',
    '404' => 'pages/core/404.php'
];
// Admin Routes (Mapped to pages/admin/)
if (strpos($path, 'admin/') === 0) {
    $adminPagePath = str_replace('admin/', 'pages/admin/', $path);
    if (file_exists(ROOT_PATH . '/' . $adminPagePath . '.php')) {
        require ROOT_PATH . '/' . $adminPagePath . '.php';
        exit;
    }
    if (file_exists(ROOT_PATH . '/' . $adminPagePath)) {
        require ROOT_PATH . '/' . $adminPagePath;
        exit;
    }

    // Fallback if not moved
    if (file_exists(ROOT_PATH . '/' . $path . '.php')) {
        require ROOT_PATH . '/' . $path . '.php';
        exit;
    }
    if (file_exists(ROOT_PATH . '/' . $path)) {
        require ROOT_PATH . '/' . $path;
        exit;
    }
}
if (strpos($path, 'api/') === 0) {
    if (file_exists(ROOT_PATH . '/' . $path . '.php')) {
        require ROOT_PATH . '/' . $path . '.php';
        exit;
    }
    if (file_exists(ROOT_PATH . '/' . $path)) {
        require ROOT_PATH . '/' . $path;
        exit;
    }
}
if (array_key_exists($path, $r)) {
    require_once ROOT_PATH . '/includes/db.php';
    require_once ROOT_PATH . '/includes/functions.php';
    require ROOT_PATH . '/' . $r[$path];
} else {
    http_response_code(404);
    require ROOT_PATH . '/pages/core/404.php';
}

