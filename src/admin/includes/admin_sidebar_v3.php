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

    <!-- Profile Header -->
    <div class="storm-sidebar__profile">
        <a href="/profile" class="storm-sidebar__avatar" title="View Profile">
            <img src="<?php echo $user_avatar; ?>" alt="<?php echo $user_name; ?>">
        </a>
        <div class="storm-sidebar__user-info">
            <div class="storm-sidebar__user-name"><?php echo $user_name; ?></div>
            <div class="storm-sidebar__user-role"><?php echo strtoupper($user_role); ?></div>
        </div>
        <button id="sidebarCollapseBtn" class="storm-sidebar__collapse-btn" title="Collapse Sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- Workspace Switcher -->
    <div class="storm-sidebar__workspace">
        <div class="storm-sidebar__workspace-icon">
            <i class="fas fa-bolt"></i>
        </div>
        <span class="storm-sidebar__workspace-label">S.T.O.R.M</span>
        <span class="storm-sidebar__workspace-badge"><i class="fas fa-chevron-down" style="font-size:9px;opacity:0.4;"></i></span>
    </div>

    <!-- Scrollable Nav -->
    <div class="storm-sidebar__nav-scroll">

        <!-- ===== FEATURES ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Features</div>

            <!-- Dashboard -->
            <a href="/admin/dashboard" class="storm-sidebar__item <?php echo isSidebarActive('admin/dashboard', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-th-large"></i></span>
                <span class="storm-sidebar__item-label">Dashboard</span>
                <span class="storm-sidebar__tooltip">Dashboard</span>
            </a>

            <!-- Member Management (expandable) -->
            <button data-submenu="submenu-members" class="storm-sidebar__item <?php echo (isSidebarActive('admin/member_management', $active_path) || isSidebarActive('admin/Unit_Structure', $active_path) || isSidebarActive('admin/edit_member', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-users"></i></span>
                <span class="storm-sidebar__item-label">Members</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Members</span>
            </button>
            <div id="submenu-members" class="storm-sidebar__submenu <?php echo (isSidebarActive('admin/member_management', $active_path) || isSidebarActive('admin/Unit_Structure', $active_path) || isSidebarActive('admin/edit_member', $active_path)) ? 'open' : ''; ?>">
                <a href="/admin/member_management" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/member_management', $active_path) ? 'active' : ''; ?>">
                    <span>Member Management</span>
                </a>
                <a href="/admin/Unit_Structure" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/Unit_Structure', $active_path) ? 'active' : ''; ?>">
                    <span>Unit Structure</span>
                </a>
            </div>

            <!-- Applications (expandable) -->
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

            <!-- Operations -->
            <a href="/admin/manage_operations" class="storm-sidebar__item <?php echo isSidebarActive('admin/manage_operations', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-calendar-check"></i></span>
                <span class="storm-sidebar__item-label">Operations</span>
                <span class="storm-sidebar__tooltip">Operations</span>
            </a>

            <!-- Campaigns -->
            <a href="/campaigns" class="storm-sidebar__item <?php echo isSidebarActive('campaigns', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-globe-americas"></i></span>
                <span class="storm-sidebar__item-label">Campaigns</span>
                <span class="storm-sidebar__tooltip">Campaigns</span>
            </a>

            <!-- Ranks -->
            <a href="/admin/manage_ranks" class="storm-sidebar__item <?php echo isSidebarActive('admin/manage_ranks', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-medal"></i></span>
                <span class="storm-sidebar__item-label">Ranks</span>
                <span class="storm-sidebar__tooltip">Ranks</span>
            </a>

            <!-- Awards -->
            <a href="/admin/manage_awards" class="storm-sidebar__item <?php echo isSidebarActive('admin/manage_awards', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-award"></i></span>
                <span class="storm-sidebar__item-label">Awards</span>
                <span class="storm-sidebar__tooltip">Awards</span>
            </a>

            <!-- Positions -->
            <a href="/admin/manage_positions" class="storm-sidebar__item <?php echo isSidebarActive('admin/manage_positions', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-id-badge"></i></span>
                <span class="storm-sidebar__item-label">Positions</span>
                <span class="storm-sidebar__tooltip">Positions</span>
            </a>

            <!-- Qualifications -->
            <a href="/admin/manage_qualifications" class="storm-sidebar__item <?php echo isSidebarActive('admin/manage_qualifications', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-certificate"></i></span>
                <span class="storm-sidebar__item-label">Qualifications</span>
                <span class="storm-sidebar__tooltip">Qualifications</span>
            </a>
        </div>

        <!-- ===== APPS ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Apps</div>

            <!-- Bot Controls (expandable) -->
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

            <!-- Media (expandable) -->
            <button data-submenu="submenu-media" class="storm-sidebar__item <?php echo (isSidebarActive('admin/manage_media', $active_path) || isSidebarActive('admin/manage_categories_tags', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-photo-video"></i></span>
                <span class="storm-sidebar__item-label">Media</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Media</span>
            </button>
            <div id="submenu-media" class="storm-sidebar__submenu <?php echo (isSidebarActive('admin/manage_media', $active_path) || isSidebarActive('admin/manage_categories_tags', $active_path)) ? 'open' : ''; ?>">
                <a href="/admin/manage_media" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/manage_media', $active_path) ? 'active' : ''; ?>">
                    <span>Manage Gallery</span>
                </a>
                <a href="/admin/manage_categories_tags" class="storm-sidebar__subitem <?php echo isSidebarActive('admin/manage_categories_tags', $active_path) ? 'active' : ''; ?>">
                    <span>Manage Tags</span>
                </a>
            </div>

            <!-- Donate Manager -->
            <a href="/admin/admin_donate" class="storm-sidebar__item <?php echo isSidebarActive('admin/admin_donate', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-donate"></i></span>
                <span class="storm-sidebar__item-label">Donate Manager</span>
                <span class="storm-sidebar__tooltip">Donate Manager</span>
            </a>
        </div>

        <!-- ===== SUPPORT ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Support</div>

            <!-- Intelligence -->
            <a href="/intelligence" class="storm-sidebar__item <?php echo isSidebarActive('intelligence', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-satellite-dish"></i></span>
                <span class="storm-sidebar__item-label">Intelligence</span>
                <span class="storm-sidebar__tooltip">Intelligence</span>
            </a>

            <!-- Chain of Command -->
            <a href="/chain" class="storm-sidebar__item <?php echo isSidebarActive('chain', $active_path) ? 'active' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-sitemap"></i></span>
                <span class="storm-sidebar__item-label">Chain of Command</span>
                <span class="storm-sidebar__tooltip">Chain of Command</span>
            </a>
        </div>

    </div><!-- end nav-scroll -->



</aside>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="storm-sidebar__overlay"></div>
