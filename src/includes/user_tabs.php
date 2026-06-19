<?php
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
<div class="dashboard-container" style="padding-top: 10px; padding-bottom: 0;">

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