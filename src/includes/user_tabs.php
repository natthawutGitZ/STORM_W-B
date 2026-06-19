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
<div class="dashboard-container max-w-[1500px] mx-auto pt-5 pb-0 px-5">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <!-- Total Members -->
        <div class="bg-surface-card border border-zinc-850 rounded-xl p-5 flex items-center justify-between hover:border-zinc-750 transition-colors group">
            <div>
                <h3 class="text-3xl font-bold text-white"><?php echo $member_stats['total']; ?></h3>
                <p class="text-sm font-medium text-gray-400 mt-1 uppercase tracking-wider">Total Members</p>
            </div>
            <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-blue-500/10 text-blue-400 group-hover:scale-110 transition-transform">
                <i class="fas fa-users text-xl"></i>
            </div>
        </div>
        <!-- Active Duty -->
        <div class="bg-surface-card border border-zinc-850 rounded-xl p-5 flex items-center justify-between hover:border-zinc-750 transition-colors group">
            <div>
                <h3 class="text-3xl font-bold text-white"><?php echo $member_stats['active']; ?></h3>
                <p class="text-sm font-medium text-gray-400 mt-1 uppercase tracking-wider">Active Duty</p>
            </div>
            <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-green-500/10 text-green-400 group-hover:scale-110 transition-transform">
                <i class="fas fa-check-circle text-xl"></i>
            </div>
        </div>
        <!-- Inactive -->
        <div class="bg-surface-card border border-zinc-850 rounded-xl p-5 flex items-center justify-between hover:border-zinc-750 transition-colors group">
            <div>
                <h3 class="text-3xl font-bold text-white"><?php echo $member_stats['inactive']; ?></h3>
                <p class="text-sm font-medium text-gray-400 mt-1 uppercase tracking-wider">Inactive</p>
            </div>
            <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-red-500/10 text-red-400 group-hover:scale-110 transition-transform">
                <i class="fas fa-times-circle text-xl"></i>
            </div>
        </div>
        <!-- Leave of Absence -->
        <div class="bg-surface-card border border-zinc-850 rounded-xl p-5 flex items-center justify-between hover:border-zinc-750 transition-colors group">
            <div>
                <h3 class="text-3xl font-bold text-white"><?php echo $member_stats['loa']; ?></h3>
                <p class="text-sm font-medium text-gray-400 mt-1 uppercase tracking-wider">Leave of Absence</p>
            </div>
            <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-orange-500/10 text-orange-400 group-hover:scale-110 transition-transform">
                <i class="fas fa-pause-circle text-xl"></i>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex gap-2 overflow-x-auto whitespace-nowrap border-b border-zinc-800 pb-2 mb-6 member-nav-tabs">
        <a href="profile" class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all <?php echo $active_tab === 'members' ? 'bg-gold/10 text-gold border border-gold/20' : 'text-gray-400 hover:text-white hover:bg-white/5 border border-transparent'; ?>">
            <i class="fas fa-users"></i> Members
        </a>
        <a href="ranks" class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all <?php echo $active_tab === 'ranks' ? 'bg-gold/10 text-gold border border-gold/20' : 'text-gray-400 hover:text-white hover:bg-white/5 border border-transparent'; ?>">
            <i class="fas fa-layer-group"></i> Ranks
        </a>
        <a href="awards" class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all <?php echo $active_tab === 'awards' ? 'bg-gold/10 text-gold border border-gold/20' : 'text-gray-400 hover:text-white hover:bg-white/5 border border-transparent'; ?>">
            <i class="fas fa-trophy"></i> Awards
        </a>
        <a href="qualifications" class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all <?php echo $active_tab === 'qualifications' ? 'bg-gold/10 text-gold border border-gold/20' : 'text-gray-400 hover:text-white hover:bg-white/5 border border-transparent'; ?>">
            <i class="fas fa-graduation-cap"></i> Qualifications
        </a>
        <a href="positions" class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all <?php echo $active_tab === 'positions' ? 'bg-gold/10 text-gold border border-gold/20' : 'text-gray-400 hover:text-white hover:bg-white/5 border border-transparent'; ?>">
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