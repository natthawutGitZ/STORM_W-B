<?php
/**
 * STORM User Sidebar
 * Dark sidebar navigation for normal users
 * Widths: 240px expanded / 72px collapsed
 */

$req_uri = $_SERVER['REQUEST_URI'];
$active_path = trim(parse_url($req_uri, PHP_URL_PATH), '/');

// Helper: check if a nav path is active
function isUserSidebarActive($check, $active_path) {
    if ($check === '' && ($active_path === '' || $active_path === 'index')) return true;
    return $check !== '' && strpos($active_path, $check) !== false;
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
        <a href="/" class="storm-sidebar__logo">
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

        <!-- ===== DASHBOARD ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">My Profile</div>

            <button data-submenu="submenu-dashboard" class="storm-sidebar__item <?php echo (isUserSidebarActive('profile', $active_path) || isUserSidebarActive('ranks', $active_path) || isUserSidebarActive('awards', $active_path) || isUserSidebarActive('qualifications', $active_path) || isUserSidebarActive('positions', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-id-card"></i></span>
                <span class="storm-sidebar__item-label">Dashboard</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Dashboard</span>
            </button>
            <div id="submenu-dashboard" class="storm-sidebar__submenu <?php echo (isUserSidebarActive('profile', $active_path) || isUserSidebarActive('ranks', $active_path) || isUserSidebarActive('awards', $active_path) || isUserSidebarActive('qualifications', $active_path) || isUserSidebarActive('positions', $active_path)) ? 'open' : ''; ?>">
                <a href="/profile" class="storm-sidebar__subitem <?php echo isUserSidebarActive('profile', $active_path) ? 'active' : ''; ?>">
                    <span>Profile (Members)</span>
                </a>
                <a href="/ranks" class="storm-sidebar__subitem <?php echo isUserSidebarActive('ranks', $active_path) ? 'active' : ''; ?>">
                    <span>Ranks</span>
                </a>
                <a href="/awards" class="storm-sidebar__subitem <?php echo isUserSidebarActive('awards', $active_path) ? 'active' : ''; ?>">
                    <span>Awards</span>
                </a>
                <a href="/qualifications" class="storm-sidebar__subitem <?php echo isUserSidebarActive('qualifications', $active_path) ? 'active' : ''; ?>">
                    <span>Qualifications</span>
                </a>
                <a href="/positions" class="storm-sidebar__subitem <?php echo isUserSidebarActive('positions', $active_path) ? 'active' : ''; ?>">
                    <span>Positions</span>
                </a>
            </div>
        </div>

        <!-- ===== PUBLIC PAGES ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Website</div>

            <a href="/" class="storm-sidebar__item <?php echo isUserSidebarActive('', $active_path) && $active_path !== 'profile' ? 'active' : ''; ?>" style="text-decoration:none;">
                <span class="storm-sidebar__item-icon"><i class="fas fa-home"></i></span>
                <span class="storm-sidebar__item-label">Home</span>
                <span class="storm-sidebar__tooltip">Home</span>
            </a>

            <a href="/media" class="storm-sidebar__item <?php echo isUserSidebarActive('media', $active_path) ? 'active' : ''; ?>" style="text-decoration:none;">
                <span class="storm-sidebar__item-icon"><i class="fas fa-photo-video"></i></span>
                <span class="storm-sidebar__item-label">Media</span>
                <span class="storm-sidebar__tooltip">Media</span>
            </a>

            <a href="/register" class="storm-sidebar__item <?php echo isUserSidebarActive('register', $active_path) ? 'active' : ''; ?>" style="text-decoration:none;">
                <span class="storm-sidebar__item-icon"><i class="fas fa-file-signature"></i></span>
                <span class="storm-sidebar__item-label">Create Application</span>
                <span class="storm-sidebar__tooltip">Apply</span>
            </a>
        </div>

        <!-- ===== EVENTS ===== -->
        <div class="storm-sidebar__section">
            <div class="storm-sidebar__section-label">Activities</div>

            <button data-submenu="submenu-events" class="storm-sidebar__item <?php echo (isUserSidebarActive('admin/manage_operations', $active_path) || isUserSidebarActive('campaigns', $active_path)) ? 'active expanded' : ''; ?>">
                <span class="storm-sidebar__item-icon"><i class="fas fa-calendar-alt"></i></span>
                <span class="storm-sidebar__item-label">Events</span>
                <span class="storm-sidebar__item-arrow"><i class="fas fa-chevron-right"></i></span>
                <span class="storm-sidebar__tooltip">Events</span>
            </button>
            <div id="submenu-events" class="storm-sidebar__submenu <?php echo (isUserSidebarActive('admin/manage_operations', $active_path) || isUserSidebarActive('campaigns', $active_path)) ? 'open' : ''; ?>">
                <a href="/admin/manage_operations" class="storm-sidebar__subitem <?php echo isUserSidebarActive('admin/manage_operations', $active_path) ? 'active' : ''; ?>">
                    <span>Operations</span>
                </a>
                <a href="/campaigns" class="storm-sidebar__subitem <?php echo isUserSidebarActive('campaigns', $active_path) ? 'active' : ''; ?>">
                    <span>Campaigns</span>
                </a>
            </div>
        </div>

    </div>

    <!-- User Mini Profile (Bottom) -->
    <?php if (isset($_SESSION['user'])): ?>
    <div class="storm-sidebar__footer">
        <a href="/logout" class="storm-sidebar__user" title="Logout">
            <div class="storm-sidebar__avatar">
                <img src="<?php echo function_exists('get_avatar') ? get_avatar($_SESSION['user']['avatar'] ?? null) : '/assets/images/default_avatar.png'; ?>" alt="User Avatar">
                <div class="storm-sidebar__status storm-sidebar__status--online"></div>
            </div>
            <div class="storm-sidebar__user-info">
                <div class="storm-sidebar__user-name"><?php echo htmlspecialchars($_SESSION['user']['personaname'] ?? 'User'); ?></div>
                <div class="storm-sidebar__user-role">Logout</div>
            </div>
        </a>
    </div>
    <?php endif; ?>

</aside>
