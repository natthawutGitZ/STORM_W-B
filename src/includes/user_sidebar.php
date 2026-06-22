<?php
/**
 * STORM Admin Sidebar v3
 * Dark sidebar navigation for SaaS HR dashboard
 * Design: #141414 bg, #12b886 teal accent
 * Widths: 240px expanded / 72px collapsed
 */

$req_uri = $_SERVER['REQUEST_URI'];
$active_path = trim(parse_url($req_uri, PHP_URL_PATH), '/');
$current_tab = $_GET['tab'] ?? '';
$current_view = $_GET['view'] ?? '';

$user = function_exists('getUser') ? getUser() : null;
$user_name = htmlspecialchars($user['personaname'] ?? 'Admin');
$user_role = htmlspecialchars($user['role'] ?? 'User');
$user_avatar = function_exists('get_avatar') ? get_avatar($user['avatar'] ?? null) : '/assets/images/default_avatar.png';

// Helper: check if a nav path is active
function isSidebarActive($check, $active_path, $tab = '', $current_tab = '')
{
    if ($tab !== '' && $current_tab !== '') {
        return strpos($active_path, $check) !== false && $current_tab === $tab;
    }
    if ($check === 'profile' && $active_path === 'profile')
        return true;
    return $check !== '' && strpos($active_path, $check) !== false;
}

// Badge counts (placeholder — replace with real queries if needed)
$badge_counts = [
    'applications' => 0,
    'tickets' => 0,
];

// Try to get live badge data
try {
    if (isset($pdo)) {
        // Pending applications count
        $stmt_pending = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'pending'");
        if ($stmt_pending) {
            $badge_counts['applications'] = (int) $stmt_pending->fetchColumn();
        }
    }
} catch (Exception $e) {
    // Silently fail — badges will show 0
}
?>

<!-- Mobile Toggle Button -->
<button id="sidebarMobileToggle" class="storm-sidebar__mobile-toggle" aria-label="Toggle Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<aside id="stormSidebar" class="storm-sidebar">

    <!-- Brand Header -->
    <div class="storm-sidebar__brand">
        <a href="/profile" class="storm-sidebar__logo">
            <div class="storm-sidebar__logo-icon">
                <i class="fas fa-bolt"></i>
            </div>
            <span class="storm-sidebar__logo-text">S.T.O.R.M</span>
        </a>
        <button id="sidebarCollapseBtn" class="storm-sidebar__collapse-btn" title="Collapse Sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- Scrollable Nav -->
    <div class="storm-sidebar__nav-scroll">

        <!-- ===== MY ACCOUNT =====
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">My Account</div>

            <a href="/profile"
                class="storm-sidebar__item <?php echo (isSidebarActive('profile', $active_path)) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-user-circle"></i></span>
                <span class="storm-sidebar__item-label">Profile</span>
                <span class="storm-sidebar__tooltip">Profile</span>
            </a>
        </div> -->

        <!-- ===== EXPLORE ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Explore</div>

            <a href="/"
                class="storm-sidebar__item <?php echo ($active_path === '' || $active_path === 'index') ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-home"></i></span>
                <span class="storm-sidebar__item-label">Home</span>
                <span class="storm-sidebar__tooltip">Home</span>
            </a>

            <button data-submenu="submenu-events"
                class="storm-sidebar__item <?php echo (isSidebarActive('admin/manage_operations', $active_path) || isSidebarActive('campaigns', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-calendar-alt"></i></span>
                <span class="storm-sidebar__item-label">Events</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Events</span>
            </button>
            <div id="submenu-events"
                class="storm-sidebar__submenu <?php echo (isSidebarActive('admin/manage_operations', $active_path) || isSidebarActive('campaigns', $active_path)) ? 'open' : ''; ?>">
                <a href="/admin/manage_operations"
                    class="storm-sidebar__subitem <?php echo isSidebarActive('admin/manage_operations', $active_path) ? 'active' : ''; ?>">
                    <span>Operations</span>
                </a>
                <a href="/campaigns"
                    class="storm-sidebar__subitem <?php echo isSidebarActive('campaigns', $active_path) ? 'active' : ''; ?>">
                    <span>Campaigns</span>
                </a>
            </div>

            <a href="/media"
                class="storm-sidebar__item <?php echo isSidebarActive('media', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-photo-video"></i></span>
                <span class="storm-sidebar__item-label">Media</span>
                <span class="storm-sidebar__tooltip">Media</span>
            </a>

            <a href="/donate"
                class="storm-sidebar__item <?php echo isSidebarActive('donate', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-donate"></i></span>
                <span class="storm-sidebar__item-label">Donate</span>
                <span class="storm-sidebar__tooltip">Donate</span>
            </a>
        </div>

        <!-- ===== RESOURCES ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Resources</div>

            <a href="/intelligence"
                class="storm-sidebar__item <?php echo isSidebarActive('intelligence', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-satellite-dish"></i></span>
                <span class="storm-sidebar__item-label">Intelligence</span>
                <span class="storm-sidebar__tooltip">Intelligence</span>
            </a>

            <a href="/chain"
                class="storm-sidebar__item <?php echo isSidebarActive('chain', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-sitemap"></i></span>
                <span class="storm-sidebar__item-label">Chain of Command</span>
                <span class="storm-sidebar__tooltip">Chain of Command</span>
            </a>

            <a href="/applications"
                class="storm-sidebar__item <?php echo isSidebarActive('applications', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-file-signature"></i></span>
                <span class="storm-sidebar__item-label">Apply Now</span>
                <span class="storm-sidebar__tooltip">Apply Now</span>
            </a>
        </div>

    </div><!-- end nav-scroll -->



</aside>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="storm-sidebar__overlay"></div>