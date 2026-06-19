<?php
/**
 * STORM User Topbar
 * Top navigation bar for normal user panel
 * Features: Breadcrumbs, Search, Profile
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

// User info
$topbar_user = function_exists('getUser') ? getUser() : null;
$topbar_user_name = htmlspecialchars($topbar_user['personaname'] ?? 'User');
$topbar_user_role = htmlspecialchars($topbar_user['role'] ?? 'User');
$topbar_user_avatar = function_exists('get_avatar') ? get_avatar($topbar_user['avatar'] ?? null) : '/assets/images/default_avatar.png';

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
            <input type="text" id="topbarSearchInput" placeholder="Search pages..." autocomplete="off" autofocus>
            <kbd>ESC</kbd>
        </div>
        <div id="topbarSearchResults" class="storm-topbar__search-results">
            <div class="storm-topbar__search-group">
                <div class="storm-topbar__search-group-label">Quick Navigation</div>
                <a href="/profile" class="storm-topbar__search-item" data-search="dashboard overview analytics profile">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                    <small>My Profile</small>
                </a>
                <a href="/ranks" class="storm-topbar__search-item" data-search="ranks promotions levels">
                    <i class="fas fa-medal"></i>
                    <span>Ranks</span>
                    <small>View Ranks</small>
                </a>
                <a href="/awards" class="storm-topbar__search-item" data-search="awards medals achievements ribbons">
                    <i class="fas fa-award"></i>
                    <span>Awards</span>
                    <small>View Awards</small>
                </a>
                <a href="/positions" class="storm-topbar__search-item" data-search="positions roles jobs billets">
                    <i class="fas fa-id-badge"></i>
                    <span>Positions</span>
                    <small>View Positions</small>
                </a>
                <a href="/qualifications" class="storm-topbar__search-item" data-search="qualifications certifications training">
                    <i class="fas fa-certificate"></i>
                    <span>Qualifications</span>
                    <small>View Qualifications</small>
                </a>
                <a href="/admin/manage_operations" class="storm-topbar__search-item" data-search="operations events calendar schedule">
                    <i class="fas fa-calendar-check"></i>
                    <span>Operations</span>
                    <small>Active events</small>
                </a>
                <a href="/campaigns" class="storm-topbar__search-item" data-search="campaigns world missions">
                    <i class="fas fa-globe-americas"></i>
                    <span>Campaigns</span>
                    <small>Active campaigns</small>
                </a>
                <a href="/media" class="storm-topbar__search-item" data-search="media gallery images photos">
                    <i class="fas fa-photo-video"></i>
                    <span>Media Gallery</span>
                    <small>View images</small>
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
