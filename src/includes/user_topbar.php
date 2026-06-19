<?php
/**
 * STORM Admin Topbar
 * Top navigation bar for admin panel
 * Features: Breadcrumbs, Search, Notifications, Profile
 * Design: Matches sidebar (#141414 bg, #12b886 teal accent)
 */

// Build breadcrumbs from URI
$breadcrumb_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$breadcrumb_segments = explode('/', $breadcrumb_path);
$breadcrumb_items = [];
$breadcrumb_url = '';

foreach ($breadcrumb_segments as $segment) {
    if (empty($segment)) continue;
    $breadcrumb_url .= '/' . $segment;
    $label = ucwords(str_replace(['_', '-'], ' ', $segment));
    $breadcrumb_items[] = ['label' => $label, 'url' => $breadcrumb_url];
}

// Current page title
$topbar_page_title = !empty($breadcrumb_items) ? end($breadcrumb_items)['label'] : 'Dashboard';

// User info (already loaded from admin_header.php)
$topbar_user = function_exists('getUser') ? getUser() : null;
$topbar_user_name = htmlspecialchars($topbar_user['personaname'] ?? 'Admin');
$topbar_user_role = htmlspecialchars($topbar_user['role'] ?? 'User');
$topbar_user_avatar = function_exists('get_avatar') ? get_avatar($topbar_user['avatar'] ?? null) : '/assets/images/default_avatar.png';

// Notification count (placeholder — wire up to real data)
$notification_count = 0;
try {
    if (isset($pdo)) {
        $stmt_notif = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'pending'");
        if ($stmt_notif) {
            $notification_count = (int) $stmt_notif->fetchColumn();
        }
    }
} catch (Exception $e) {
    // Silently fail
}
?>

<header id="adminTopbar" class="storm-topbar">
    <div class="storm-topbar__left">
        <!-- Breadcrumbs -->
        <nav class="storm-topbar__breadcrumbs" aria-label="Breadcrumb">
            <ol>
                <li>
                    <a href="/profile" class="storm-topbar__breadcrumb-home" title="Dashboard">
                        <i class="fas fa-th-large"></i>
                    </a>
                </li>
                <?php foreach ($breadcrumb_items as $i => $crumb): ?>
                    <?php if ($crumb['label'] === 'Admin') continue; ?>
                    <li>
                        <span class="storm-topbar__breadcrumb-sep">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        <?php if ($i < count($breadcrumb_items) - 1): ?>
                            <a href="<?php echo $crumb['url']; ?>"><?php echo $crumb['label']; ?></a>
                        <?php else: ?>
                            <span class="storm-topbar__breadcrumb-current"><?php echo $crumb['label']; ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <!-- Page Title -->
        <h1 class="storm-topbar__title"><?php echo $topbar_page_title; ?></h1>
    </div>

    <div class="storm-topbar__right">
        <!-- Search -->
        <button id="topbarSearchBtn" class="storm-topbar__action-btn" title="Search (Ctrl+K)">
            <i class="fas fa-search"></i>
            <span class="storm-topbar__search-label">Search...</span>
            <kbd class="storm-topbar__kbd">⌘K</kbd>
        </button>

        <!-- Notifications -->
        <div class="storm-topbar__notif-wrapper">
            <button id="topbarNotifBtn" class="storm-topbar__icon-btn" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($notification_count > 0): ?>
                    <span class="storm-topbar__notif-badge"><?php echo $notification_count > 9 ? '9+' : $notification_count; ?></span>
                <?php endif; ?>
            </button>
            <!-- Notification Dropdown -->
            <div id="topbarNotifDropdown" class="storm-topbar__notif-dropdown">
                <div class="storm-topbar__notif-header">
                    <span>Notifications</span>
                    <?php if ($notification_count > 0): ?>
                        <span class="storm-topbar__notif-count"><?php echo $notification_count; ?> pending</span>
                    <?php endif; ?>
                </div>
                <div class="storm-topbar__notif-body">
                    <?php if ($notification_count > 0): ?>
                        <a href="/applications" class="storm-topbar__notif-item">
                            <div class="storm-topbar__notif-icon storm-topbar__notif-icon--pending">
                                <i class="fas fa-file-signature"></i>
                            </div>
                            <div class="storm-topbar__notif-content">
                                <strong><?php echo $notification_count; ?> pending application<?php echo $notification_count > 1 ? 's' : ''; ?></strong>
                                <span>Review awaiting submissions</span>
                            </div>
                            <i class="fas fa-chevron-right storm-topbar__notif-arrow"></i>
                        </a>
                    <?php else: ?>
                        <div class="storm-topbar__notif-empty">
                            <i class="fas fa-check-circle"></i>
                            <p>All caught up!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Divider -->
        <div class="storm-topbar__divider"></div>

        <!-- Profile -->
        <div class="storm-topbar__profile-wrapper">
            <button id="topbarProfileBtn" class="storm-topbar__profile-btn">
                <img src="<?php echo $topbar_user_avatar; ?>" alt="<?php echo $topbar_user_name; ?>" class="storm-topbar__profile-avatar">
                <div class="storm-topbar__profile-info">
                    <span class="storm-topbar__profile-name"><?php echo $topbar_user_name; ?></span>
                    <span class="storm-topbar__profile-role"><?php echo $topbar_user_role; ?></span>
                </div>
                <i class="fas fa-chevron-down storm-topbar__profile-arrow"></i>
            </button>
            <!-- Profile Dropdown -->
            <div id="topbarProfileDropdown" class="storm-topbar__profile-dropdown">
                <div class="storm-topbar__profile-dropdown-header">
                    <img src="<?php echo $topbar_user_avatar; ?>" alt="<?php echo $topbar_user_name; ?>">
                    <div>
                        <strong><?php echo $topbar_user_name; ?></strong>
                        <span><?php echo strtoupper($topbar_user_role); ?></span>
                    </div>
                </div>
                <div class="storm-topbar__profile-dropdown-body">
                    <a href="/profile" class="storm-topbar__dropdown-item">
                        <i class="fas fa-user"></i>
                        <span>View Profile</span>
                    </a>
                    <a href="/admin/bot_controls?tab=bot" class="storm-topbar__dropdown-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="/" class="storm-topbar__dropdown-item">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Back to Site</span>
                    </a>
                </div>
                <div class="storm-topbar__profile-dropdown-footer">
                    <a href="/logout" class="storm-topbar__dropdown-item storm-topbar__dropdown-item--danger">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Log Out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Search Modal (Spotlight) -->
<div id="topbarSearchModal" class="storm-topbar__search-modal">
    <div class="storm-topbar__search-modal-overlay"></div>
    <div class="storm-topbar__search-modal-content">
        <div class="storm-topbar__search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="topbarSearchInput" placeholder="Search pages, members, settings..." autocomplete="off" autofocus>
            <kbd>ESC</kbd>
        </div>
        <div id="topbarSearchResults" class="storm-topbar__search-results">
            <div class="storm-topbar__search-group">
                <div class="storm-topbar__search-group-label">Quick Navigation</div>
                <a href="/profile" class="storm-topbar__search-item" data-search="dashboard overview profile account">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile Dashboard</span>
                    <small>Your account overview</small>
                </a>
                <a href="/edit_profile" class="storm-topbar__search-item" data-search="edit profile settings update">
                    <i class="fas fa-user-edit"></i>
                    <span>Edit Profile</span>
                    <small>Update your info</small>
                </a>
                <a href="/update_resume" class="storm-topbar__search-item" data-search="resume cv dossier record">
                    <i class="fas fa-file-alt"></i>
                    <span>Update Resume</span>
                    <small>Manage your record</small>
                </a>
                <a href="/upload_slip" class="storm-topbar__search-item" data-search="slip receipt payment donate">
                    <i class="fas fa-receipt"></i>
                    <span>Upload Slip</span>
                    <small>Submit payment slip</small>
                </a>
                <a href="/applications" class="storm-topbar__search-item" data-search="applications forms apply join">
                    <i class="fas fa-file-signature"></i>
                    <span>Apply Now</span>
                    <small>Submit an application</small>
                </a>
                <a href="/media" class="storm-topbar__search-item" data-search="media gallery images photos">
                    <i class="fas fa-photo-video"></i>
                    <span>Media</span>
                    <small>View gallery</small>
                </a>
                <a href="/donate" class="storm-topbar__search-item" data-search="donate support premium">
                    <i class="fas fa-donate"></i>
                    <span>Donate</span>
                    <small>Support the community</small>
                </a>
                <a href="/campaigns" class="storm-topbar__search-item" data-search="campaigns missions world">
                    <i class="fas fa-globe-americas"></i>
                    <span>Campaigns</span>
                    <small>View active campaigns</small>
                </a>
                <a href="/intelligence" class="storm-topbar__search-item" data-search="intelligence intel map">
                    <i class="fas fa-satellite-dish"></i>
                    <span>Intelligence</span>
                    <small>Intel dashboard</small>
                </a>
                <a href="/chain" class="storm-topbar__search-item" data-search="chain of command hierarchy structure">
                    <i class="fas fa-sitemap"></i>
                    <span>Chain of Command</span>
                    <small>Unit hierarchy</small>
                </a>
            </div>
        </div>
        <div class="storm-topbar__search-footer">
            <span><kbd>↑↓</kbd> Navigate</span>
            <span><kbd>↵</kbd> Open</span>
            <span><kbd>ESC</kbd> Close</span>
        </div>
    </div>
</div>
