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
function isSidebarActive($check, $active_path, $tab = '', $current_tab = '') {
    if ($tab !== '' && $current_tab !== '') {
        return strpos($active_path, $check) !== false && $current_tab === $tab;
    }
    if ($check === 'admin/dashboard' && $active_path === 'admin/dashboard') return true;
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
        <a href="/admin/dashboard" class="storm-sidebar__logo">
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

        <!-- ===== MEMBERS ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Members</div>

            <button data-submenu="submenu-members" class="storm-sidebar__item <?php echo (isSidebarActive('admin/dashboard', $active_path) || isSidebarActive('admin/member_management', $active_path) || isSidebarActive('admin/Unit_Structure', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-users-cog"></i></span>
                <span class="storm-sidebar__item-label">Manage Users</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Manage Users</span>
            </button>
            <div id="submenu-members" class="storm-sidebar__submenu <?php echo (isSidebarActive('admin/dashboard', $active_path) || isSidebarActive('admin/member_management', $active_path) || isSidebarActive('admin/Unit_Structure', $active_path)) ? 'open' : ''; ?>">
                <a href="/admin/dashboard" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/dashboard', $active_path) ? 'active' : ''; ?>">
                    <span>Dashboard</span>
                </a>
                <a href="/admin/member_management" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/member_management', $active_path) ? 'active' : ''; ?>">
                    <span>Member Management</span>
                </a>
                <a href="/admin/Unit_Structure" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/Unit_Structure', $active_path) ? 'active' : ''; ?>">
                    <span>Unit Structure</span>
                </a>
            </div>
        </div>

        <!-- ===== MEDIA ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Media</div>

            <button data-submenu="submenu-media" class="storm-sidebar__item <?php echo (isSidebarActive('admin/manage_media', $active_path) || isSidebarActive('admin/manage_categories_tags', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-photo-video"></i></span>
                <span class="storm-sidebar__item-label">Assets</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Assets</span>
            </button>
            <div id="submenu-media" class="storm-sidebar__submenu <?php echo (isSidebarActive('admin/manage_media', $active_path) || isSidebarActive('admin/manage_categories_tags', $active_path)) ? 'open' : ''; ?>">
                <a href="/admin/manage_media" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/manage_media', $active_path) ? 'active' : ''; ?>">
                    <span>Manage Gallery</span>
                </a>
                <a href="/admin/manage_categories_tags" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/manage_categories_tags', $active_path) ? 'active' : ''; ?>">
                    <span>Manage Tags</span>
                </a>
            </div>
        </div>

        <!-- ===== APPLICATIONS ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Forms & Data</div>

            <button data-submenu="submenu-apps" class="storm-sidebar__item <?php echo isSidebarActive('admin/applications', $active_path) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-file-signature"></i></span>
                <span class="storm-sidebar__item-label">Applications</span>
                <?php if ($badge_counts['applications'] > 0): ?>
                    <span class="storm-sidebar__badge storm-sidebar__badge--green"><?php echo $badge_counts['applications']; ?></span>
                <?php endif; ?>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Applications</span>
            </button>
            <div id="submenu-apps" class="storm-sidebar__submenu <?php echo isSidebarActive('admin/applications', $active_path) ? 'open' : ''; ?>">
                <a href="/admin/applications" class="storm-sidebar__subitem <?php echo (isSidebarActive('admin/applications', $active_path) && $current_view === '') ? 'active' : ''; ?>">
                    <span>Application List</span>
                </a>
                <a href="/admin/applications?view=forms" class="storm-sidebar__subitem <?php echo ($current_view === 'forms') ? 'active' : ''; ?>">
                    <span>Custom Forms</span>
                </a>
                <a href="/admin/applications?view=summary" class="storm-sidebar__subitem <?php echo ($current_view === 'summary') ? 'active' : ''; ?>">
                    <span>Summary</span>
                </a>
            </div>
        </div>

        <!-- ===== EVENTS ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Activities</div>

            <button data-submenu="submenu-events" class="storm-sidebar__item <?php echo (isSidebarActive('admin/manage_operations', $active_path) || isSidebarActive('campaigns', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-calendar-alt"></i></span>
                <span class="storm-sidebar__item-label">Events</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Events</span>
            </button>
            <div id="submenu-events" class="storm-sidebar__submenu <?php echo (isSidebarActive('admin/manage_operations', $active_path) || isSidebarActive('campaigns', $active_path)) ? 'open' : ''; ?>">
                <a href="/admin/manage_operations" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/manage_operations', $active_path) ? 'active' : ''; ?>">
                    <span>Operations</span>
                </a>
                <a href="/campaigns" class="storm-sidebar__subitem <?php echo isSidebarActive('campaigns', $active_path) ? 'active' : ''; ?>">
                    <span>Campaigns</span>
                </a>
            </div>
        </div>

        <!-- ===== BOT CONTROLS ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Discord</div>

            <button data-submenu="submenu-bot" class="storm-sidebar__item <?php echo isSidebarActive('admin/bot_controls', $active_path) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fab fa-discord"></i></span>
                <span class="storm-sidebar__item-label">Bot Controls</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Bot Controls</span>
            </button>
            <div id="submenu-bot" class="storm-sidebar__submenu <?php echo isSidebarActive('admin/bot_controls', $active_path) ? 'open' : ''; ?>">
                <a href="/admin/bot_controls?tab=embed" class="storm-sidebar__subitem <?php echo ($current_tab === 'embed') ? 'active' : ''; ?>">
                    <span>Embed Builder</span>
                </a>
                <a href="/admin/bot_controls?tab=bot" class="storm-sidebar__subitem <?php echo ($current_tab === 'bot') ? 'active' : ''; ?>">
                    <span>Bot Settings</span>
                </a>
                <a href="/admin/bot_controls?tab=welcome" class="storm-sidebar__subitem <?php echo ($current_tab === 'welcome') ? 'active' : ''; ?>">
                    <span>Welcome Voice</span>
                </a>
                <a href="/admin/bot_controls?tab=permissions" class="storm-sidebar__subitem <?php echo ($current_tab === 'permissions') ? 'active' : ''; ?>">
                    <span>Permissions</span>
                </a>
                <a href="/admin/bot_controls?tab=tickets" class="storm-sidebar__subitem <?php echo ($current_tab === 'tickets') ? 'active' : ''; ?>">
                    <span>Tickets</span>
                    <?php if ($badge_counts['tickets'] > 0): ?>
                        <span class="storm-sidebar__badge storm-sidebar__badge--green" style="margin-left:auto;"><?php echo $badge_counts['tickets']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- ===== OTHER ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Miscellaneous</div>

            <button data-submenu="submenu-other" class="storm-sidebar__item <?php echo isSidebarActive('admin/admin_donate', $active_path) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-star"></i></span>
                <span class="storm-sidebar__item-label">Other</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Other</span>
            </button>
            <div id="submenu-other" class="storm-sidebar__submenu <?php echo isSidebarActive('admin/admin_donate', $active_path) ? 'open' : ''; ?>">
                <a href="/admin/admin_donate" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/admin_donate', $active_path) ? 'active' : ''; ?>">
                    <span>Donate Manager</span>
                </a>
            </div>
        </div>

    </div><!-- end nav-scroll -->



</aside>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="storm-sidebar__overlay"></div>
