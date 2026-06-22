<?php
// Calculate statistics
$stmt_all = $pdo->query("SELECT status FROM users");
$all_users_tab = $stmt_all->fetchAll();

$member_stats = [
    'total' => count($all_users_tab),
    'active' => 0,
    'inactive' => 0,
    'loa' => 0
];

foreach ($all_users_tab as $u) {
    if ($u['status'] === 'Active')
        $member_stats['active']++;
    elseif ($u['status'] === 'Inactive')
        $member_stats['inactive']++;
    elseif ($u['status'] === 'LOA')
        $member_stats['loa']++;
}

$current_page = basename($_SERVER['PHP_SELF']);

// Map page filenames to tab names
$tab_map = [
    'profile.php' => 'members',
    'ranks.php' => 'ranks',
    'awards.php' => 'awards',
    'qualifications.php' => 'qualifications',
    'positions.php' => 'positions',
];
$active_tab = isset($tab_map[$current_page]) ? $tab_map[$current_page] : 'members';
?>
<div class="dashboard-container" style="padding-top: 20px; padding-bottom: 0;">
    <!-- Statistics Cards -->
    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px;">
        <div class="stat-card-modern">
            <div class="stat-content" style="flex: 1;">
                <h3>
                    <?php echo $member_stats['total']; ?>
                </h3>
                <p>Total Members</p>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(33, 150, 243, 0.1); color: #2196f3;">
                <i class="fas fa-users"></i>
            </div>
        </div>
        <div class="stat-card-modern">
            <div class="stat-content" style="flex: 1;">
                <h3>
                    <?php echo $member_stats['active']; ?>
                </h3>
                <p>Active Duty</p>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(76, 175, 80, 0.1); color: #4caf50;">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-card-modern">
            <div class="stat-content" style="flex: 1;">
                <h3>
                    <?php echo $member_stats['inactive']; ?>
                </h3>
                <p>Inactive</p>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(244, 67, 54, 0.1); color: #f44336;">
                <i class="fas fa-times-circle"></i>
            </div>
        </div>
        <div class="stat-card-modern">
            <div class="stat-content" style="flex: 1;">
                <h3>
                    <?php echo $member_stats['loa']; ?>
                </h3>
                <p>Leave of Absence</p>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(255, 152, 0, 0.1); color: #ff9800;">
                <i class="fas fa-pause-circle"></i>
            </div>
        </div>
    </div>

    <div style="margin: 0 0 20px 0; border-bottom: 1px solid #333; display: flex; gap: 10px; overflow-x: auto; white-space: nowrap; padding-bottom: 5px;"
        class="member-nav-tabs">
        <a href="profile" class="tab-link <?php echo $active_tab === 'members' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'members' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-users"></i> Members
        </a>
        <a href="ranks" class="tab-link <?php echo $active_tab === 'ranks' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'ranks' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-layer-group"></i> Ranks
        </a>
        <a href="awards" class="tab-link <?php echo $active_tab === 'awards' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'awards' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-trophy"></i> Awards
        </a>
        <a href="qualifications" class="tab-link <?php echo $active_tab === 'qualifications' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'qualifications' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-graduation-cap"></i> Qualifications
        </a>
        <a href="positions" class="tab-link <?php echo $active_tab === 'positions' ? 'active' : ''; ?>"
            style="padding: 10px 15px; color: #aaa; text-decoration: none; border-bottom: 2px solid transparent; transition: all 0.3s; flex-shrink: 0; <?php echo $active_tab === 'positions' ? 'color: var(--accent-color); border-bottom-color: var(--accent-color);' : ''; ?>">
            <i class="fas fa-user-tag"></i> Positions
        </a>
    </div>
</div>
<style>
    .member-nav-tabs::-webkit-scrollbar {
        height: 4px;
    }

    .member-nav-tabs::-webkit-scrollbar-track {
        background: transparent;
    }

    .member-nav-tabs::-webkit-scrollbar-thumb {
        background: rgba(197, 160, 89, 0.3);
        border-radius: 4px;
    }

    .member-nav-tabs::-webkit-scrollbar-thumb:hover {
        background: rgba(197, 160, 89, 0.6);
    }

    .tab-link:hover {
        color: #fff !important;
    }
</style>