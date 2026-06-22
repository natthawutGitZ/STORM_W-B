<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    redirect('/');
}

// --- ANALYTICS DATA ---

// Member Statistics
$stmt_all = $pdo->query("SELECT status FROM users");
$all_users = $stmt_all->fetchAll();

$memberStats = [
    'total' => count($all_users),
    'active' => 0,
    'inactive' => 0,
    'loa' => 0
];

foreach ($all_users as $user) {
    if ($user['status'] === 'Active')
        $memberStats['active']++;
    elseif ($user['status'] === 'Inactive')
        $memberStats['inactive']++;
    elseif ($user['status'] === 'LOA')
        $memberStats['loa']++;
}

// Application Statistics
$pendingApps = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'pending'")->fetchColumn();
$acceptedApps = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'accepted'")->fetchColumn();
$rejectedApps = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'rejected'")->fetchColumn();

// Media Statistics
$totalMedia = $pdo->query("SELECT COUNT(*) FROM media_gallery")->fetchColumn();
$totalAlbums = $pdo->query("SELECT COUNT(*) FROM media_albums")->fetchColumn();

// Recent Members (last 7 days)
$recentMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Get page views for all periods
$pageViewsAllData = [];
$todayViews = 0;
$weekViews = 0;

try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'page_views'")->rowCount() > 0;

    if ($tableExists) {
        // Get ALL 30 days data (we'll filter in JS)
        $stmt = $pdo->query("
            SELECT DATE(viewed_at) as date, COUNT(*) as views 
            FROM page_views 
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(viewed_at)
            ORDER BY date ASC
        ");
        $pageViewsAllData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $todayViews = $pdo->query("SELECT COUNT(*) FROM page_views WHERE DATE(viewed_at) = CURDATE()")->fetchColumn();
        $weekViews = $pdo->query("SELECT COUNT(*) FROM page_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    }
} catch (PDOException $e) {
}

// Recent Activity — include reviewer admin name
// Check if reviewed_by column exists (MySQL 8.0 doesn't support IF NOT EXISTS for ADD COLUMN)
$hasReviewedBy = false;
try {
    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_responses' AND COLUMN_NAME = 'reviewed_by'");
    $colCheck->execute();
    if ($colCheck->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE form_responses ADD COLUMN reviewed_by INT(11) DEFAULT NULL");
        $pdo->exec("ALTER TABLE form_responses ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL");
    }
    $hasReviewedBy = true;
} catch (PDOException $e) {
    $hasReviewedBy = false;
}

if ($hasReviewedBy) {
    $recentActivity = $pdo->query("
        SELECT fr.id, fr.status, fr.submitted_at, f.title as form_title,
               u_reviewer.personaname as reviewer_name
        FROM form_responses fr
        JOIN forms f ON fr.form_id = f.id
        LEFT JOIN users u_reviewer ON fr.reviewed_by = u_reviewer.id
        ORDER BY fr.submitted_at DESC
        LIMIT 4
    ")->fetchAll();
} else {
    $recentActivity = $pdo->query("
        SELECT fr.id, fr.status, fr.submitted_at, f.title as form_title,
               NULL as reviewer_name
        FROM form_responses fr
        JOIN forms f ON fr.form_id = f.id
        ORDER BY fr.submitted_at DESC
        LIMIT 4
    ")->fetchAll();
}

// All Activity Log — create table if not exists and fetch
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_log (
        id INT(11) NOT NULL AUTO_INCREMENT,
        admin_id INT(11) NOT NULL,
        action VARCHAR(100) NOT NULL,
        target_type VARCHAR(50) DEFAULT NULL,
        target_id INT(11) DEFAULT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_admin_id (admin_id),
        KEY idx_created_at (created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Pagination for activity log
    $logPage = isset($_GET['log_page']) ? max(1, (int) $_GET['log_page']) : 1;
    $logPerPage = 6;
    $logOffset = ($logPage - 1) * $logPerPage;
    $logFilter = $_GET['log_filter'] ?? '';

    $logWhereClause = '';
    $logParams = [];
    if (!empty($logFilter)) {
        $logWhereClause = 'WHERE al.action LIKE ? OR u_admin.personaname LIKE ? OR al.target_type LIKE ?';
        $logParams = ["%$logFilter%", "%$logFilter%", "%$logFilter%"];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_log al LEFT JOIN users u_admin ON al.admin_id = u_admin.id $logWhereClause");
    $countStmt->execute($logParams);
    $totalLogs = $countStmt->fetchColumn();
    $totalLogPages = max(1, ceil($totalLogs / $logPerPage));

    $logStmt = $pdo->prepare("
        SELECT al.*, u_admin.personaname as admin_name, u_admin.avatar as admin_avatar
        FROM admin_activity_log al
        LEFT JOIN users u_admin ON al.admin_id = u_admin.id
        $logWhereClause
        ORDER BY al.created_at DESC
        LIMIT $logPerPage OFFSET $logOffset
    ");
    $logStmt->execute($logParams);
    $activityLogs = $logStmt->fetchAll();
} catch (PDOException $e) {
    $activityLogs = [];
    $totalLogPages = 1;
    $logPage = 1;
    $totalLogs = 0;
}

// Top Pages
$topPages = [];
$topPagesDailyData = [];
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'page_views'")->rowCount() > 0;
    if ($tableExists) {
        $topPages = $pdo->query("
            SELECT page_url, page_title, COUNT(*) as views
            FROM page_views
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY page_url, page_title
            ORDER BY views DESC
        ")->fetchAll();

        // Get daily breakdown for each top page (30 days)
        if (!empty($topPages)) {
            $topPageTitles = array_map(function ($p) {
                return $p['page_title'] ?: $p['page_url'];
            }, $topPages);
            $placeholders = implode(',', array_fill(0, count($topPageTitles), '?'));
            $dailyStmt = $pdo->prepare("
                SELECT DATE(viewed_at) as date, COALESCE(page_title, page_url) as page_name, COUNT(*) as views
                FROM page_views
                WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND COALESCE(page_title, page_url) IN ($placeholders)
                GROUP BY DATE(viewed_at), page_name
                ORDER BY date ASC
            ");
            $dailyStmt->execute($topPageTitles);
            $rawDaily = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

            // Structure: { pageName: { date: views, ... }, ... }
            foreach ($rawDaily as $row) {
                $topPagesDailyData[$row['page_name']][$row['date']] = (int) $row['views'];
            }
        }
    }
} catch (PDOException $e) {
}

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

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
                borderRadius: { xl: '12px', '2xl': '16px' },
                animation: { 'fade-in': 'fadeIn 0.5s ease-out forwards', 'slide-up': 'slideUp 0.6s ease-out forwards' },
                keyframes: {
                    fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                    slideUp: { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } }
                }
            }
        }
    }
</script>

<style>
    /* ========== COMPACT DASHBOARD STYLES ========== */

    body {
        background-color: #080808 !important;
        background-image: radial-gradient(circle at 50% 0%, rgba(20, 20, 20, 1) 0%, rgba(5, 5, 5, 1) 100%);
    }

    .analytics-grid {
        display: grid;
        gap: 18px;
        padding: 0 20px 25px;
        max-width: 1500px;
        margin: 0 auto;
    }

    /* Dashboard Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 20px;
        max-width: 1500px;
        margin: 0 auto;
    }

    .dashboard-title {
        font-size: 1.6rem;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #fff 0%, #c5a059 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dashboard-title .icon-glow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        background: rgba(197, 160, 89, 0.1);
        border-radius: 10px;
        border: 1px solid rgba(197, 160, 89, 0.2);
    }

    .dashboard-title .icon-glow i {
        font-size: 1rem;
        color: #c5a059;
        -webkit-text-fill-color: #c5a059;
    }

    .header-date {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.06);
        border-radius: 25px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
    }

    .header-date i {
        color: #c5a059;
    }

    /* ========== STAT CARDS ========== */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 15px;
    }

    @media (max-width: 1200px) {
        .stats-row {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 500px) {
        .stats-row {
            grid-template-columns: 1fr;
        }
    }

    .stat-card {
        background: linear-gradient(145deg, rgba(20, 20, 20, 0.95) 0%, rgba(10, 10, 10, 0.98) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 14px;
        padding: 20px;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, rgba(197, 160, 89, 0.5) 0%, transparent 100%);
    }

    .stat-card:hover {
        transform: translateY(-3px);
        border-color: rgba(197, 160, 89, 0.15);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
    }

    .stat-card .stat-icon {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 2.8rem;
        color: rgba(255, 255, 255, 0.8);
        opacity: 0.15;
    }

    .stat-card h3 {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0 0 4px;
        color: #fff;
    }

    .stat-card p {
        color: rgba(255, 255, 255, 0.45);
        font-size: 0.75rem;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 500;
    }

    .stat-card .stat-change {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 10px;
    }

    .stat-change.positive {
        background: rgba(76, 175, 80, 0.12);
        color: #69f0ae;
    }

    .stat-change.neutral {
        background: rgba(255, 152, 0, 0.12);
        color: #ffab40;
    }

    /* ========== CHARTS GRID ========== */
    .charts-grid {
        display: grid;
        grid-template-columns: 1.6fr 1fr;
        gap: 18px;
    }

    @media (max-width: 1100px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }

    .chart-card {
        background: linear-gradient(145deg, rgba(20, 20, 20, 0.95) 0%, rgba(10, 10, 10, 0.98) 100%);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 14px;
        padding: 18px;
        min-width: 0; /* Prevent grid column blowout */
    }

    .chart-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .chart-card h4 {
        color: #fff;
        font-size: 0.9rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-card h4 i {
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(197, 160, 89, 0.12);
        border-radius: 8px;
        color: #c5a059;
        font-size: 0.8rem;
    }

    /* Day Selector */
    .day-selector {
        display: flex;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        padding: 3px;
        gap: 2px;
    }

    .day-btn {
        padding: 6px 12px;
        border: none;
        background: transparent;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .day-btn:hover {
        color: rgba(255, 255, 255, 0.8);
    }

    .day-btn.active {
        background: rgba(197, 160, 89, 0.25);
        color: #c5a059;
    }

    .chart-container {
        position: relative;
        height: 200px;
        width: 100%;
    }

    /* ========== ACTIVITY LIST ========== */
    .activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .activity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        margin-bottom: 8px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.03);
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .activity-item:hover {
        background: rgba(255, 255, 255, 0.04);
        transform: translateX(3px);
    }

    .activity-item:last-child {
        margin-bottom: 0;
    }

    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .activity-icon.pending {
        background: rgba(255, 152, 0, 0.12);
        color: #ffab40;
    }

    .activity-icon.accepted {
        background: rgba(76, 175, 80, 0.12);
        color: #69f0ae;
    }

    .activity-icon.rejected {
        background: rgba(244, 67, 54, 0.12);
        color: #ff5252;
    }

    .activity-content strong {
        color: #fff;
        font-size: 0.85rem;
        font-weight: 500;
        display: block;
    }

    .activity-content span {
        color: rgba(255, 255, 255, 0.35);
        font-size: 0.75rem;
        margin-top: 2px;
        display: block;
    }

    /* ========== QUICK ACTIONS ========== */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 18px 12px;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: 12px;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .quick-action-btn i {
        font-size: 1.4rem;
        color: #c5a059;
    }

    .quick-action-btn:hover {
        background: rgba(197, 160, 89, 0.1);
        border-color: rgba(197, 160, 89, 0.25);
        color: #fff;
        transform: translateY(-2px);
    }

    /* ========== TOP PAGES ========== */
    .top-pages-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .top-page-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        margin-bottom: 6px;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 8px;
    }

    .top-page-item:last-child {
        margin-bottom: 0;
    }

    .page-name {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.8rem;
        max-width: 160px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .page-views-count {
        background: rgba(197, 160, 89, 0.15);
        color: #c5a059;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* ========== EMPTY STATE ========== */
    .empty-chart {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 200px;
        color: rgba(255, 255, 255, 0.25);
        text-align: center;
    }

    .empty-chart i {
        font-size: 3rem;
        margin-bottom: 12px;
        opacity: 0.3;
    }

    .empty-chart p {
        font-size: 0.85rem;
        line-height: 1.5;
    }

    /* Doughnut Container */
    .doughnut-container {
        height: 180px;
    }

    /* Analytics Tabs */
    .analytics-tab {
        padding: 5px 14px;
        border: none;
        border-radius: 6px;
        background: transparent;
        color: rgba(255, 255, 255, 0.4);
        font-size: 0.72rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .analytics-tab:hover {
        color: rgba(255, 255, 255, 0.7);
        background: rgba(255, 255, 255, 0.04);
    }

    .analytics-tab.active {
        background: rgba(197, 160, 89, 0.2);
        color: #c5a059;
    }

    /* ========== SHADCN/UI ENHANCEMENTS ========== */
    .stat-card {
        background: rgba(15, 15, 18, 0.9) !important;
        border: 1px solid rgba(63, 63, 70, 0.5) !important;
        border-radius: 12px !important;
        backdrop-filter: blur(12px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .stat-card:hover {
        border-color: rgba(197, 160, 89, 0.3) !important;
        box-shadow: 0 8px 32px rgba(197, 160, 89, 0.06), 0 0 0 1px rgba(197, 160, 89, 0.1) !important;
    }

    .stat-card::before {
        height: 1px !important;
        background: linear-gradient(90deg, rgba(197, 160, 89, 0.4), rgba(197, 160, 89, 0.1), transparent) !important;
    }

    .chart-card {
        background: rgba(15, 15, 18, 0.9) !important;
        border: 1px solid rgba(63, 63, 70, 0.5) !important;
        backdrop-filter: blur(12px);
    }

    .quick-action-btn {
        background: rgba(15, 15, 18, 0.6) !important;
        border: 1px solid rgba(63, 63, 70, 0.4) !important;
        border-radius: 12px !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    .quick-action-btn:hover {
        background: rgba(197, 160, 89, 0.08) !important;
        border-color: rgba(197, 160, 89, 0.3) !important;
        box-shadow: 0 4px 20px rgba(197, 160, 89, 0.08) !important;
    }

    .activity-item {
        background: rgba(15, 15, 18, 0.5) !important;
        border: 1px solid rgba(63, 63, 70, 0.3) !important;
        border-radius: 10px !important;
    }

    .activity-item:hover {
        border-color: rgba(63, 63, 70, 0.6) !important;
        background: rgba(25, 25, 30, 0.8) !important;
    }

    .day-btn.active {
        background: rgba(197, 160, 89, 0.15) !important;
        color: #c5a059 !important;
        box-shadow: 0 0 0 1px rgba(197, 160, 89, 0.3);
    }

    .top-page-item {
        background: rgba(15, 15, 18, 0.5) !important;
        border: 1px solid rgba(63, 63, 70, 0.2);
        transition: all 0.2s ease;
    }

    .top-page-item:hover {
        background: rgba(25, 25, 30, 0.8) !important;
        border-color: rgba(63, 63, 70, 0.4);
    }

    @keyframes countUp {
        from {
            opacity: 0;
            transform: translateY(8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stat-card h3 {
        animation: countUp 0.5s ease-out forwards;
    }

    .stat-card:nth-child(2) h3 {
        animation-delay: 0.08s;
    }

    .stat-card:nth-child(3) h3 {
        animation-delay: 0.16s;
    }

    .stat-card:nth-child(4) h3 {
        animation-delay: 0.24s;
    }

    .stat-card:nth-child(5) h3 {
        animation-delay: 0.32s;
    }

    @keyframes pulse-glow {

        0%,
        100% {
            opacity: 1
        }

        50% {
            opacity: 0.4
        }
    }

    .overview-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-radius: 10px;
        transition: all 0.2s;
    }

    .overview-row:hover {
        filter: brightness(1.3);
    }

    @media (max-width: 900px) {
        .bottom-grid-2col {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-header">
    <div>
        <h1 class="dashboard-title">
            <span class="icon-glow"><i class="fas fa-bolt"></i></span>
            Command Center
        </h1>
        <p style="margin: 4px 0 0 48px; font-size: 0.8rem; color: rgba(255,255,255,0.35); font-weight: 400;">Welcome
            back, <?php echo htmlspecialchars($_SESSION['user']['personaname']); ?></p>
    </div>
    <div style="display: flex; align-items: center; gap: 12px;">
        <div
            style="display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: rgba(76,175,80,0.08); border: 1px solid rgba(76,175,80,0.2); border-radius: 20px;">
            <div
                style="width: 7px; height: 7px; border-radius: 50%; background: #4caf50; box-shadow: 0 0 8px rgba(76,175,80,0.6); animation: pulse-glow 2s infinite;">
            </div>
            <span
                style="font-size: 0.72rem; color: #69f0ae; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Online</span>
        </div>
        <div class="header-date">
            <i class="fas fa-calendar-alt"></i>
            <?php echo date('l, F j, Y'); ?>
        </div>
    </div>
</div>

<div class="analytics-grid">
    <!-- Statistics Cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div
                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(197,160,89,0.1); border: 1px solid rgba(197,160,89,0.2); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-users" style="color: #c5a059; font-size: 0.9rem;"></i>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> +<?php echo $recentMembers; ?>
                </div>
            </div>
            <h3><?php echo $memberStats['total']; ?></h3>
            <p>Total Personnel</p>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div
                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(105,240,174,0.08); border: 1px solid rgba(105,240,174,0.15); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-shield-alt" style="color: #69f0ae; font-size: 0.9rem;"></i>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-signal"></i> Online
                </div>
            </div>
            <h3><?php echo $memberStats['active']; ?></h3>
            <p>Active Duty</p>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div
                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(66,165,245,0.08); border: 1px solid rgba(66,165,245,0.15); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-eye" style="color: #42a5f5; font-size: 0.9rem;"></i>
                </div>
                <div class="stat-change neutral">
                    <i class="fas fa-chart-line"></i> <?php echo number_format($todayViews); ?> today
                </div>
            </div>
            <h3><?php echo number_format($weekViews); ?></h3>
            <p>Weekly Views</p>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div
                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(255,171,64,0.08); border: 1px solid rgba(255,171,64,0.15); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-file-alt" style="color: #ffab40; font-size: 0.9rem;"></i>
                </div>
                <div class="stat-change neutral">
                    <i class="fas fa-clock"></i> Review
                </div>
            </div>
            <h3><?php echo $pendingApps; ?></h3>
            <p>Pending Apps</p>
        </div>

        <div class="stat-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div
                    style="width: 40px; height: 40px; border-radius: 10px; background: rgba(171,71,188,0.08); border: 1px solid rgba(171,71,188,0.15); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-images" style="color: #ab47bc; font-size: 0.9rem;"></i>
                </div>
                <div class="stat-change positive">
                    <i class="fas fa-folder"></i> <?php echo $totalAlbums; ?>
                </div>
            </div>
            <h3><?php echo $totalMedia; ?></h3>
            <p>Media Files</p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <!-- Page Views Chart -->
        <div class="chart-card">
            <div class="chart-card-header">
                <h4><i class="fas fa-chart-area"></i> Visitor Analytics</h4>
                <div class="day-selector">
                    <button class="day-btn active" data-days="3">3D</button>
                    <button class="day-btn" data-days="7">7D</button>
                    <button class="day-btn" data-days="14">14D</button>
                    <button class="day-btn" data-days="30">30D</button>
                </div>
            </div>
            <!-- Traffic Chart -->
            <?php if (empty($pageViewsAllData)): ?>
                <div class="empty-chart">
                    <i class="fas fa-chart-bar"></i>
                    <p>No analytics data yet</p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <canvas id="pageViewsChart"></canvas>
                </div>
            <?php endif; ?>
            <!-- Top Pages Spline Chart -->
            <?php if (!empty($topPagesDailyData)): ?>
                <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.05);">
                    <h4 style="margin: 0 0 12px 0; font-size: 0.8rem; color: rgba(255,255,255,0.5); font-weight: 600;">
                        <i class="fas fa-fire" style="color: #c5a059; margin-right: 6px;"></i>Top Pages Trends
                    </h4>
                    <div style="position: relative; height: 220px; width: 100%;">
                        <canvas id="topPagesChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div style="display: flex; flex-direction: column; gap: 18px;">
            <!-- Recent Activity -->
            <div class="chart-card" style="flex: 1;">
                <h4 style="margin-bottom: 12px;"><i class="fas fa-bell"></i> Recent Activity</h4>
                <?php if (empty($recentActivity)): ?>
                    <p style="color: rgba(255,255,255,0.3); text-align: center; padding: 20px 0; font-size: 0.85rem;">
                        No recent activity
                    </p>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($recentActivity as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon <?php echo $activity['status']; ?>">
                                    <i class="fas fa-<?php
                                    echo $activity['status'] === 'pending' ? 'clock' :
                                        ($activity['status'] === 'accepted' ? 'check' : 'times');
                                    ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <strong>Application <?php echo ucfirst($activity['status']); ?></strong>
                                    <span><?php echo date('M j, H:i', strtotime($activity['submitted_at'])); ?><?php if (!empty($activity['reviewer_name']) && $activity['status'] !== 'pending'): ?>
                                            <span style="color: #c5a059; font-weight: 500;"> · by
                                                <?php echo htmlspecialchars($activity['reviewer_name']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="chart-card">
                <h4 style="margin-bottom: 12px;"><i class="fas fa-bolt"></i> Quick Actions</h4>
                <div class="quick-actions" style="grid-template-columns: repeat(3, 1fr) !important;">
                    <a href="member_management" class="quick-action-btn">
                        <i class="fas fa-users-cog"></i>
                        Members
                    </a>
                    <a href="view_application" class="quick-action-btn">
                        <i class="fas fa-file-signature"></i>
                        Applications
                    </a>
                    <a href="manage_media" class="quick-action-btn">
                        <i class="fas fa-photo-video"></i>
                        Media
                    </a>
                    <a href="admin_donate" class="quick-action-btn">
                        <i class="fas fa-hand-holding-heart"></i>
                        Donate
                    </a>
                    <a href="manage_ranks" class="quick-action-btn">
                        <i class="fas fa-medal"></i>
                        Ranks
                    </a>
                    <a href="bot_controls" class="quick-action-btn">
                        <i class="fas fa-robot"></i>
                        Bot
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div class="bottom-grid-2col"
        style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px;">
        <div class="chart-card">
            <h4 style="margin-bottom: 12px;"><i class="fas fa-users"></i> Personnel Status</h4>
            <div class="doughnut-container">
                <canvas id="memberStatusChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <h4 style="margin-bottom: 16px;"><i class="fas fa-chart-pie"></i> Overview</h4>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div class="overview-row"
                    style="background: rgba(105,240,174,0.06); border: 1px solid rgba(105,240,174,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #69f0ae;"></div>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Active Duty</span>
                    </div>
                    <span
                        style="color: #69f0ae; font-weight: 700; font-size: 1.1rem;"><?php echo $memberStats['active']; ?></span>
                </div>
                <div class="overview-row"
                    style="background: rgba(255,82,82,0.06); border: 1px solid rgba(255,82,82,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #ff5252;"></div>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Inactive</span>
                    </div>
                    <span
                        style="color: #ff5252; font-weight: 700; font-size: 1.1rem;"><?php echo $memberStats['inactive']; ?></span>
                </div>
                <div class="overview-row"
                    style="background: rgba(255,171,64,0.06); border: 1px solid rgba(255,171,64,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #ffab40;"></div>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Leave of Absence</span>
                    </div>
                    <span
                        style="color: #ffab40; font-weight: 700; font-size: 1.1rem;"><?php echo $memberStats['loa']; ?></span>
                </div>
                <div class="overview-row"
                    style="background: rgba(197,160,89,0.06); border: 1px solid rgba(197,160,89,0.1);">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 8px; height: 8px; border-radius: 50%; background: #c5a059;"></div>
                        <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem;">Applications Accepted</span>
                    </div>
                    <span
                        style="color: #c5a059; font-weight: 700; font-size: 1.1rem;"><?php echo $acceptedApps; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== ALL ACTIVITY LOG SECTION (AJAX) ========== -->
    <div class="chart-card" style="margin-top: 0;" id="activityLogCard">
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
            <h4 style="margin: 0;"><i class="fas fa-history"></i> All Activity Log</h4>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="logTotalEntries" style="color: rgba(255,255,255,0.4); font-size: 0.75rem;"></span>
                <div style="display: flex; gap: 8px; margin: 0;">
                    <input type="text" id="logFilterInput" placeholder="Search logs..."
                        style="padding: 7px 14px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: #fff; font-size: 0.8rem; width: 180px; outline: none; transition: border-color 0.2s;"
                        onfocus="this.style.borderColor='rgba(197,160,89,0.4)'"
                        onblur="this.style.borderColor='rgba(255,255,255,0.08)'">
                    <button onclick="logCurrentPage=1; loadActivityLog()"
                        style="padding: 7px 14px; background: rgba(197,160,89,0.2); border: 1px solid rgba(197,160,89,0.3); border-radius: 8px; color: #c5a059; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: all 0.2s;"
                        onmouseover="this.style.background='rgba(197,160,89,0.3)'"
                        onmouseout="this.style.background='rgba(197,160,89,0.2)'">
                        <i class="fas fa-search"></i>
                    </button>
                    <button id="logClearBtn"
                        onclick="document.getElementById('logFilterInput').value=''; logCurrentPage=1; loadActivityLog();"
                        style="display:none; padding: 7px 14px; background: rgba(255,82,82,0.15); border: 1px solid rgba(255,82,82,0.3); border-radius: 8px; color: #ff5252; cursor: pointer; font-size: 0.8rem;"
                        title="Clear filter">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="logContent">
            <div style="text-align: center; padding: 30px; color: rgba(255,255,255,0.3);">
                <i class="fas fa-spinner fa-spin" style="font-size: 1.5rem;"></i>
            </div>
        </div>
    </div>
</div>

<script>
    let logCurrentPage = 1;
    const actionIcons = {
        'approve_application': { icon: 'check-circle', color: '#69f0ae', bg: 'rgba(76,175,80,0.12)' },
        'reject_application': { icon: 'times-circle', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'update_application_status': { icon: 'sync-alt', color: '#ffab40', bg: 'rgba(255,152,0,0.12)' },
        'edit_member': { icon: 'user-edit', color: '#42a5f5', bg: 'rgba(33,150,243,0.12)' },
        'delete_member': { icon: 'user-minus', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'update_profile': { icon: 'user-cog', color: '#42a5f5', bg: 'rgba(33,150,243,0.12)' },
        'update_resume': { icon: 'file-alt', color: '#ab47bc', bg: 'rgba(171,71,188,0.12)' },
        'login': { icon: 'sign-in-alt', color: '#69f0ae', bg: 'rgba(76,175,80,0.12)' },
        'login_steam': { icon: 'steam', color: '#69f0ae', bg: 'rgba(76,175,80,0.12)' },
        'login_password': { icon: 'sign-in-alt', color: '#69f0ae', bg: 'rgba(76,175,80,0.12)' },
        'add_member_tag': { icon: 'tag', color: '#26c6da', bg: 'rgba(38,198,218,0.12)' },
        'delete_member_tag': { icon: 'tag', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'create_unit': { icon: 'shield-alt', color: '#69f0ae', bg: 'rgba(76,175,80,0.12)' },
        'update_unit': { icon: 'shield-alt', color: '#42a5f5', bg: 'rgba(33,150,243,0.12)' },
        'delete_unit': { icon: 'shield-alt', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'upload_media': { icon: 'cloud-upload-alt', color: '#66bb6a', bg: 'rgba(102,187,106,0.12)' },
        'upload_video': { icon: 'video', color: '#66bb6a', bg: 'rgba(102,187,106,0.12)' },
        'delete_media': { icon: 'trash', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'delete_album': { icon: 'folder-minus', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'add_category': { icon: 'folder-plus', color: '#66bb6a', bg: 'rgba(102,187,106,0.12)' },
        'edit_category': { icon: 'folder-open', color: '#42a5f5', bg: 'rgba(33,150,243,0.12)' },
        'delete_category': { icon: 'folder-minus', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'add_tag': { icon: 'tags', color: '#26c6da', bg: 'rgba(38,198,218,0.12)' },
        'edit_tag': { icon: 'tags', color: '#42a5f5', bg: 'rgba(33,150,243,0.12)' },
        'delete_tag': { icon: 'tags', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'send_embed': { icon: 'paper-plane', color: '#7c4dff', bg: 'rgba(124,77,255,0.12)' },
        'update_embed': { icon: 'pen-fancy', color: '#7c4dff', bg: 'rgba(124,77,255,0.12)' },
        'send_dm': { icon: 'envelope', color: '#7c4dff', bg: 'rgba(124,77,255,0.12)' },
        'create_event': { icon: 'calendar-plus', color: '#ffab40', bg: 'rgba(255,171,64,0.12)' },
        'cancel_event': { icon: 'calendar-times', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'update_bot_settings': { icon: 'robot', color: '#42a5f5', bg: 'rgba(33,150,243,0.12)' },
        'join_voice': { icon: 'volume-up', color: '#69f0ae', bg: 'rgba(76,175,80,0.12)' },
        'leave_voice': { icon: 'volume-mute', color: '#ffab40', bg: 'rgba(255,171,64,0.12)' },
        'create_staff_post': { icon: 'plus-square', color: '#66bb6a', bg: 'rgba(102,187,106,0.12)' },
        'update_staff_post': { icon: 'edit', color: '#42a5f5', bg: 'rgba(33,150,243,0.12)' },
        'delete_staff_post': { icon: 'minus-square', color: '#ff5252', bg: 'rgba(244,67,54,0.12)' },
        'auto_post_resume': { icon: 'share-square', color: '#ab47bc', bg: 'rgba(171,71,188,0.12)' },
        'debug_test_action': { icon: 'bug', color: '#ffab40', bg: 'rgba(255,171,64,0.12)' },
    };
    const defaultIcon = { icon: 'cog', color: '#c5a059', bg: 'rgba(197,160,89,0.12)' };

    function escHtml(str) {
        if (!str) return '';
        const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function loadActivityLog() {
        const filter = document.getElementById('logFilterInput').value;
        const clearBtn = document.getElementById('logClearBtn');
        clearBtn.style.display = filter ? 'inline-flex' : 'none';

        const container = document.getElementById('logContent');
        container.innerHTML = '<div style="text-align:center;padding:30px;color:rgba(255,255,255,0.3);"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;"></i></div>';

        fetch('ajax_activity_log.php?page=' + logCurrentPage + '&filter=' + encodeURIComponent(filter))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.logs.length) {
                    document.getElementById('logTotalEntries').textContent = '0 total entries';
                    container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:rgba(255,255,255,0.25);"><i class="fas fa-clipboard-list" style="font-size:2.5rem;margin-bottom:12px;opacity:0.3;"></i><p style="font-size:0.9rem;margin:0;">No activity logs found</p></div>';
                    return;
                }

                document.getElementById('logTotalEntries').textContent = Number(data.totalLogs).toLocaleString() + ' total entries';

                let html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:separate;border-spacing:0 4px;">';
                html += '<thead><tr>';
                html += '<th style="padding:10px 12px;text-align:left;color:rgba(255,255,255,0.4);font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Admin</th>';
                html += '<th style="padding:10px 12px;text-align:left;color:rgba(255,255,255,0.4);font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Action</th>';
                html += '<th style="padding:10px 12px;text-align:left;color:rgba(255,255,255,0.4);font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Target</th>';
                html += '<th style="padding:10px 12px;text-align:left;color:rgba(255,255,255,0.4);font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Details</th>';
                html += '<th style="padding:10px 12px;text-align:left;color:rgba(255,255,255,0.4);font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;">IP</th>';
                html += '<th style="padding:10px 12px;text-align:right;color:rgba(255,255,255,0.4);font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Time</th>';
                html += '</tr></thead><tbody>';

                data.logs.forEach(log => {
                    const ai = actionIcons[log.action] || defaultIcon;
                    let avatarPath = log.admin_avatar || '/assets/images/default_avatar.png';
                    if (avatarPath.indexOf('assets/') === 0) avatarPath = '/' + avatarPath;

                    let detailStr = '';
                    let dtAdminName = null;
                    try {
                        const det = JSON.parse(log.details);
                        if (det && det.status) detailStr = 'Status: ' + det.status.charAt(0).toUpperCase() + det.status.slice(1);
                        else if (det && det.name) detailStr = det.name;
                        else if (det && det.title) detailStr = det.title;

                        if (det && det.admin_name) dtAdminName = det.admin_name;
                    } catch (e) { detailStr = log.details || ''; }

                    const finalAdminName = log.admin_name || dtAdminName || 'Unknown';

                    const targetHtml = log.target_type
                        ? '<span style="background:rgba(197,160,89,0.12);color:#c5a059;padding:2px 8px;border-radius:4px;font-size:0.7rem;font-weight:500;">' + escHtml(log.target_type.charAt(0).toUpperCase() + log.target_type.slice(1)) + '</span> <span style="color:rgba(255,255,255,0.3);">#' + escHtml(log.target_id) + '</span>'
                        : '<span style="color:rgba(255,255,255,0.2);">—</span>';

                    const actionLabel = log.action.replace(/_/g, ' ').replace(/^./, c => c.toUpperCase());

                    html += '<tr style="background:rgba(255,255,255,0.02);transition:all 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.05)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.02)\'">';
                    html += '<td style="padding:10px 12px;border-radius:8px 0 0 8px;"><div style="display:flex;align-items:center;gap:8px;"><div style="width:28px;height:28px;border-radius:50%;overflow:hidden;flex-shrink:0;border:1px solid rgba(197,160,89,0.3);"><img src="' + escHtml(avatarPath) + '" alt="" style="width:100%;height:100%;object-fit:cover;" onerror="this.src=\'/assets/images/default_avatar.png\'"></div><span style="color:#fff;font-size:0.8rem;font-weight:500;">' + escHtml(finalAdminName) + '</span></div></td>';
                    html += '<td style="padding:10px 12px;"><div style="display:flex;align-items:center;gap:8px;"><div style="width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;background:' + ai.bg + ';flex-shrink:0;"><i class="fas fa-' + ai.icon + '" style="font-size:0.7rem;color:' + ai.color + ';"></i></div><span style="color:rgba(255,255,255,0.8);font-size:0.8rem;">' + escHtml(actionLabel) + '</span></div></td>';
                    html += '<td style="padding:10px 12px;color:rgba(255,255,255,0.5);font-size:0.78rem;">' + targetHtml + '</td>';
                    html += '<td style="padding:10px 12px;color:rgba(255,255,255,0.45);font-size:0.78rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escHtml(detailStr) + '</td>';
                    html += '<td style="padding:10px 12px;color:rgba(255,255,255,0.25);font-size:0.72rem;font-family:monospace;">' + escHtml(log.ip_address || '—') + '</td>';
                    html += '<td style="padding:10px 12px;border-radius:0 8px 8px 0;text-align:right;"><span style="color:rgba(255,255,255,0.4);font-size:0.75rem;">' + formatDate(log.created_at) + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';

                // Pagination
                if (data.totalPages > 1) {
                    html += '<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:18px;padding-top:15px;border-top:1px solid rgba(255,255,255,0.05);">';
                    if (data.page > 1) {
                        html += '<a href="javascript:void(0)" onclick="logCurrentPage=' + (data.page - 1) + ';loadActivityLog();" style="padding:6px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:6px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:0.75rem;"><i class="fas fa-chevron-left"></i></a>';
                    }
                    const startP = Math.max(1, data.page - 2);
                    const endP = Math.min(data.totalPages, data.page + 2);
                    for (let p = startP; p <= endP; p++) {
                        const isActive = p === data.page;
                        const style = isActive
                            ? 'background:rgba(197,160,89,0.25);color:#c5a059;border:1px solid rgba(197,160,89,0.3);'
                            : 'background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.5);border:1px solid rgba(255,255,255,0.08);';
                        html += '<a href="javascript:void(0)" onclick="logCurrentPage=' + p + ';loadActivityLog();" style="padding:6px 12px;border-radius:6px;text-decoration:none;font-size:0.75rem;font-weight:600;transition:all 0.2s;' + style + '">' + p + '</a>';
                    }
                    if (data.page < data.totalPages) {
                        html += '<a href="javascript:void(0)" onclick="logCurrentPage=' + (data.page + 1) + ';loadActivityLog();" style="padding:6px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:6px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:0.75rem;"><i class="fas fa-chevron-right"></i></a>';
                    }
                    html += '<span style="color:rgba(255,255,255,0.3);font-size:0.72rem;margin-left:8px;">Page ' + data.page + ' of ' + data.totalPages + '</span>';
                    html += '</div>';
                }

                container.innerHTML = html;
            })
            .catch(err => {
                container.innerHTML = '<div style="text-align:center;padding:30px;color:#ff5252;">Error loading logs</div>';
                console.error(err);
            });
    }

    // Enter key in search
    document.getElementById('logFilterInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { logCurrentPage = 1; loadActivityLog(); }
    });

    // Load on page ready
    document.addEventListener('DOMContentLoaded', function () { loadActivityLog(); });
</script>

<script>
    // Fix Chart.js tooltip coordinates when body has CSS zoom
    (function() {
        const zoomFixPlugin = {
            id: 'cssZoomFix',
            beforeEvent(chart, args) {
                const zoom = parseFloat(getComputedStyle(document.body).zoom) || 1;
                if (zoom !== 1 && args.event) {
                    args.event.x = args.event.x / zoom;
                    args.event.y = args.event.y / zoom;
                }
            }
        };
        Chart.register(zoomFixPlugin);
    })();

    document.addEventListener('DOMContentLoaded', function () {
        Chart.defaults.font.family = "'Inter', sans-serif";

        const allPageViewsData = <?php echo json_encode($pageViewsAllData); ?>;
        let pageViewsChart = null;
        let topPagesSpline = null;

        function toLocalISODate(d) {
            const z = n => ('0' + n).slice(-2);
            return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate());
        }

        function filterDataByDays(days) {
            const arr = [];
            const now = new Date();
            const dateMap = {};
            
            // Map existing data by date string (YYYY-MM-DD)
            allPageViewsData.forEach(item => {
                dateMap[item.date] = parseInt(item.views);
            });

            // Generate exactly 'days' number of days ending today
            for (let i = days - 1; i >= 0; i--) {
                const d = new Date(now.getTime() - i * 86400000);
                const dateString = toLocalISODate(d);
                arr.push({
                    date: dateString,
                    views: dateMap[dateString] || 0
                });
            }
            return arr;
        }

        function updateChart(days) {
            const filteredData = filterDataByDays(days);
            const labels = filteredData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            const values = filteredData.map(d => parseInt(d.views));
            if (pageViewsChart) {
                pageViewsChart.data.labels = labels;
                pageViewsChart.data.datasets[0].data = values;
                pageViewsChart.update();
            }
        }

        // ========== Visitor Analytics (single gold line) ==========
        <?php if (!empty($pageViewsAllData)): ?>
                (function () {
                    const ctx = document.getElementById('pageViewsChart').getContext('2d');
                    const initialData = filterDataByDays(3);

                    const lineGrad = ctx.createLinearGradient(0, 0, 0, 200);
                    lineGrad.addColorStop(0, 'rgba(197, 160, 89, 0.35)');
                    lineGrad.addColorStop(0.5, 'rgba(197, 160, 89, 0.1)');
                    lineGrad.addColorStop(1, 'rgba(197, 160, 89, 0)');

                    const borderGrad = ctx.createLinearGradient(0, 0, ctx.canvas.width, 0);
                    borderGrad.addColorStop(0, '#c5a059');
                    borderGrad.addColorStop(0.5, '#e8c97a');
                    borderGrad.addColorStop(1, '#c5a059');

                    pageViewsChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: initialData.map(d => new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                            datasets: [{
                                data: initialData.map(d => parseInt(d.views)),
                                borderColor: borderGrad, backgroundColor: lineGrad,
                                borderWidth: 2.5, fill: true, tension: 0.4,
                                pointBackgroundColor: '#e8c97a', pointBorderColor: '#1a1c23',
                                pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 7,
                                pointHoverBackgroundColor: '#fff', pointHoverBorderColor: '#c5a059', pointHoverBorderWidth: 3
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { intersect: false, mode: 'index' },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(12,12,16,0.95)', titleColor: '#e8c97a', bodyColor: '#fff',
                                    borderColor: 'rgba(197,160,89,0.3)', borderWidth: 1, padding: 12, cornerRadius: 10,
                                    displayColors: false,
                                    callbacks: { label: function (c) { return c.parsed.y.toLocaleString() + ' views'; } }
                                }
                            },
                            scales: {
                                x: { grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false }, ticks: { color: 'rgba(255,255,255,0.35)', font: { size: 10 } } },
                                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false }, ticks: { color: 'rgba(255,255,255,0.35)', font: { size: 10 } } }
                            }
                        }
                    });
                })();
        <?php endif; ?>

        // ========== Top Pages — Multi-line Smooth Spline ==========
        <?php if (!empty($topPagesDailyData)): ?>
                (function () {
                    const tpCtx = document.getElementById('topPagesChart').getContext('2d');
                    const dailyRaw = <?php echo json_encode($topPagesDailyData); ?>;
                    const pageNames = Object.keys(dailyRaw);

                    const colors = [
                        { border: '#c5a059', bg: 'rgba(197,160,89,0.08)', point: '#e8c97a' },
                        { border: '#7c4dff', bg: 'rgba(124,77,255,0.06)', point: '#b388ff' },
                        { border: '#00bcd4', bg: 'rgba(0,188,212,0.06)', point: '#4dd0e1' },
                        { border: '#66bb6a', bg: 'rgba(102,187,106,0.06)', point: '#a5d6a7' },
                        { border: '#ff7043', bg: 'rgba(255,112,67,0.06)', point: '#ffab91' },
                        { border: '#42a5f5', bg: 'rgba(66,165,245,0.06)', point: '#90caf9' }
                    ];

                    function dateRange(days) {
                        const arr = [], now = new Date();
                        for (let i = days - 1; i >= 0; i--) {
                            const d = new Date(now.getTime() - i * 86400000);
                            arr.push(toLocalISODate(d));
                        }
                        return arr;
                    }

                    function buildData(days) {
                        const dates = dateRange(days);
                        const labels = dates.map(d => {
                            const dt = new Date(d + 'T00:00:00');
                            return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                        });
                        const datasets = pageNames.map((name, i) => {
                            const c = colors[i % colors.length];
                            const grad = tpCtx.createLinearGradient(0, 0, 0, 220);
                            grad.addColorStop(0, c.bg);
                            grad.addColorStop(1, 'rgba(0,0,0,0)');
                            return {
                                label: name,
                                data: dates.map(d => dailyRaw[name][d] || 0),
                                borderColor: c.border, backgroundColor: grad,
                                pointBackgroundColor: c.point, pointBorderColor: '#0f0f12', pointBorderWidth: 2,
                                pointRadius: days <= 7 ? 4 : 2, pointHoverRadius: 7,
                                pointHoverBackgroundColor: '#fff', pointHoverBorderColor: c.border, pointHoverBorderWidth: 3,
                                borderWidth: 2.5, fill: true, tension: 0.4
                            };
                        });
                        return { labels, datasets };
                    }

                    topPagesSpline = new Chart(tpCtx, {
                        type: 'line',
                        data: buildData(3),
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            interaction: { intersect: false, mode: 'index' },
                            plugins: {
                                legend: {
                                    display: true, position: 'bottom',
                                    labels: { color: 'rgba(255,255,255,0.55)', padding: 12, usePointStyle: true, pointStyle: 'circle', font: { size: 10, weight: '500' }, boxWidth: 6 }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(12,12,16,0.95)', titleColor: '#e8c97a', bodyColor: '#fff',
                                    borderColor: 'rgba(197,160,89,0.3)', borderWidth: 1, padding: 12, cornerRadius: 10,
                                    displayColors: true, usePointStyle: true,
                                    callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + c.parsed.y.toLocaleString() + ' views'; } }
                                }
                            },
                            scales: {
                                x: { grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false }, ticks: { color: 'rgba(255,255,255,0.35)', font: { size: 10 }, maxRotation: 0 } },
                                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false }, ticks: { color: 'rgba(255,255,255,0.35)', font: { size: 10 } } }
                            }
                        }
                    });

                    // Sync spline chart with day selector
                    window._updateTopPagesSpline = function (days) {
                        const nd = buildData(days);
                        topPagesSpline.data = nd;
                        topPagesSpline.update('none');
                    };
                })();
        <?php endif; ?>

        // ========== Day Selector Buttons ==========
        document.querySelectorAll('.day-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.day-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const days = parseInt(this.dataset.days);
                updateChart(days);
                if (window._updateTopPagesSpline) window._updateTopPagesSpline(days);
            });
        });

        // ========== Member Status Doughnut ==========
        const memberCtx = document.getElementById('memberStatusChart').getContext('2d');
        new Chart(memberCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Inactive', 'LOA'],
                datasets: [{
                    data: [<?php echo $memberStats['active']; ?>, <?php echo $memberStats['inactive']; ?>, <?php echo $memberStats['loa']; ?>],
                    backgroundColor: ['rgba(105,240,174,0.8)', 'rgba(255,82,82,0.8)', 'rgba(255,171,64,0.8)'],
                    borderColor: '#1a1c23', borderWidth: 3, hoverOffset: 5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: 'rgba(255,255,255,0.6)', padding: 15, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } }
                    }
                }
            }
        });
    });
</script>
<?php include ROOT_PATH . '/admin/includes/admin_footer.php'; ?>