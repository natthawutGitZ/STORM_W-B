<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Members Area');

if (!isLoggedIn()) {
    redirect('login');
}

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = sanitize($_POST['personaname']);

    // Handle File Upload
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['avatar_file']['tmp_name'];
        $fileName = $_FILES['avatar_file']['name'];
        $fileSize = $_FILES['avatar_file']['size'];
        $fileType = $_FILES['avatar_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $avatar_url = $dest_path;
            } else {
                $error = 'There was some error moving the file to upload directory.';
            }
        } else {
            $error = 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions);
        }
    } else {
        // Keep existing avatar if no new file uploaded
        $avatar_url = $_SESSION['user']['avatar'];
    }

    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET personaname = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$new_name, $avatar_url, $user_id]);

            // Update Session
            $_SESSION['user']['personaname'] = $new_name;
            $_SESSION['user']['avatar'] = $avatar_url;

            $message = "Profile updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Fetch Stats
$total_members = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_members = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn();
$inactive_members = $pdo->query("SELECT COUNT(*) FROM users WHERE status != 'Active'")->fetchColumn();

// Fetch all members for the list with tags
if (isAdmin()) {
    // Admin uses shared logic which handles filters and updates
    include 'admin/includes/member_logic.php';
} else {
    // Standard User: Fetch Unit Roster sorted by Rank
    $stmt = $pdo->query("SELECT u.*, 
        GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR '||') as tag_names,
        GROUP_CONCAT(t.color ORDER BY t.name SEPARATOR '||') as tag_colors
        FROM users u 
        LEFT JOIN user_tags ut ON u.id = ut.user_id 
        LEFT JOIN tags t ON ut.tag_id = t.id 
        GROUP BY u.id
        ORDER BY 
        CASE 
            WHEN u.rank = 'General of the Army (GA)' THEN 1
            WHEN u.rank = 'General (GEN)' THEN 2
            WHEN u.rank = 'Lieutenant General (LTG)' THEN 3
            WHEN u.rank = 'Major General (MG)' THEN 4
            WHEN u.rank = 'Brigadier General (BG)' THEN 5
            WHEN u.rank = 'Colonel (COL)' THEN 6
            WHEN u.rank = 'Lieutenant Colonel (LTC)' THEN 7
            WHEN u.rank = 'Major (MAJ)' THEN 8
            WHEN u.rank = 'Captain (CPT)' THEN 9
            WHEN u.rank = 'First Lieutenant (1LT)' THEN 10
            WHEN u.rank = 'Second Lieutenant (2LT)' THEN 11
            WHEN u.rank = 'Chief Warrant Officer 5 (CW5)' THEN 12
            WHEN u.rank = 'Chief Warrant Officer 4 (CW4)' THEN 13
            WHEN u.rank = 'Chief Warrant Officer 3 (CW3)' THEN 14
            WHEN u.rank = 'Chief Warrant Officer 2 (CW2)' THEN 15
            WHEN u.rank = 'Warrant Officer 1 (WO1)' THEN 16
            WHEN u.rank = 'Sergeant Major of the Army (SMA)' THEN 17
            WHEN u.rank = 'Command Sergeant Major (CSM)' THEN 18
            WHEN u.rank = 'Sergeant Major (SGM)' THEN 19
            WHEN u.rank = 'First Sergeant (1SG)' THEN 20
            WHEN u.rank = 'Master Sergeant (MSG)' THEN 21
            WHEN u.rank = 'Sergeant First Class (SFC)' THEN 22
            WHEN u.rank = 'Staff Sergeant (SSG)' THEN 23
            WHEN u.rank = 'Sergeant (SGT)' THEN 24
            WHEN u.rank = 'Corporal (CPL)' THEN 25
            WHEN u.rank = 'Specialist (SPC)' THEN 26
            WHEN u.rank = 'Private First Class (PFC)' THEN 27
            WHEN u.rank = 'Private (PV2)' THEN 28
            WHEN u.rank = 'Private (PV1)' THEN 29
            ELSE 30 
        END, u.personaname ASC");
    $members = $stmt->fetchAll();
}

if (isAdmin()) {
    // Admin header handles its own paths now
    include 'admin/includes/admin_header.php';
} else {
    include ROOT_PATH . '/includes/profile_header.php';
}
?>

<div class="max-w-[1500px] mx-auto px-5 pb-10 mt-6">

    <?php if (!isAdmin()): ?>
        <?php include ROOT_PATH . '/includes/user_tabs.php'; ?>
    <?php else: ?>
        <!-- Stats Row (Admin only - tabs handled elsewhere) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Total Members -->
            <div class="bg-surface-card border border-zinc-850 rounded-xl p-5 flex items-center justify-between hover:border-zinc-750 transition-colors group">
                <div>
                    <h3 class="text-3xl font-bold text-white"><?php echo $total_members; ?></h3>
                    <p class="text-sm font-medium text-gray-400 mt-1 uppercase tracking-wider">Total Members</p>
                </div>
                <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-blue-500/10 text-blue-400 group-hover:scale-110 transition-transform">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
            <!-- Active Duty -->
            <div class="bg-surface-card border border-zinc-850 rounded-xl p-5 flex items-center justify-between hover:border-zinc-750 transition-colors group">
                <div>
                    <h3 class="text-3xl font-bold text-white"><?php echo $active_members; ?></h3>
                    <p class="text-sm font-medium text-gray-400 mt-1 uppercase tracking-wider">Active Duty</p>
                </div>
                <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-green-500/10 text-green-400 group-hover:scale-110 transition-transform">
                    <i class="fas fa-user-check text-xl"></i>
                </div>
            </div>
            <!-- Inactive -->
            <div class="bg-surface-card border border-zinc-850 rounded-xl p-5 flex items-center justify-between hover:border-zinc-750 transition-colors group">
                <div>
                    <h3 class="text-3xl font-bold text-white"><?php echo $inactive_members; ?></h3>
                    <p class="text-sm font-medium text-gray-400 mt-1 uppercase tracking-wider">Discharged/Inactive</p>
                </div>
                <div class="w-12 h-12 rounded-lg flex items-center justify-center bg-red-500/10 text-red-400 group-hover:scale-110 transition-transform">
                    <i class="fas fa-user-slash text-xl"></i>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-[350px_1fr] gap-8">

        <!-- Left Column: Profile Edit -->
        <div>
            <div class="bg-surface-card border border-zinc-850 rounded-2xl p-7 shadow-xl">
                <h3 class="border-b border-zinc-800 pb-4 mb-6 text-gold font-bold text-lg flex items-center gap-2">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </h3>

                <?php if ($message): ?>
                    <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-lg mb-6 flex items-center gap-2 text-sm">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6 flex items-center gap-2 text-sm">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <!-- Avatar Section -->
                    <div class="text-center mb-8">
                        <div class="w-28 h-28 mx-auto rounded-full border-4 border-zinc-800 p-1 mb-4 shadow-[0_0_25px_rgba(197,160,89,0.15)] relative">
                            <img src="<?php echo get_avatar($_SESSION['user']['avatar']); ?>" alt="Profile Avatar" class="w-full h-full rounded-full object-cover">
                        </div>
                        <div class="text-lg font-bold text-white tracking-wide">
                            <?php echo htmlspecialchars($_SESSION['user']['personaname']); ?>
                        </div>
                        <div class="mt-1">
                            <span class="inline-block bg-gold/10 text-gold px-4 py-1 rounded-full text-xs font-bold tracking-widest border border-gold/20">
                                <?php echo htmlspecialchars($_SESSION['user']['rank'] ?? 'Recruit'); ?>
                            </span>
                        </div>
                        <?php if (!empty($_SESSION['user']['position'])): ?>
                            <div class="mt-2 text-gray-400 text-sm">
                                <?php echo htmlspecialchars($_SESSION['user']['position']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info Cards -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-black/20 border border-zinc-800 rounded-xl p-3 text-center">
                            <div class="text-[0.65rem] text-gray-500 uppercase tracking-widest mb-1">Status</div>
                            <div class="text-sm font-bold <?php echo ($_SESSION['user']['status'] ?? 'Active') === 'Active' ? 'text-green-400' : (($_SESSION['user']['status'] ?? '') === 'LOA' ? 'text-orange-400' : 'text-red-400'); ?>">
                                <?php echo htmlspecialchars($_SESSION['user']['status'] ?? 'Active'); ?>
                            </div>
                        </div>
                        <div class="bg-black/20 border border-zinc-800 rounded-xl p-3 text-center">
                            <div class="text-[0.65rem] text-gray-500 uppercase tracking-widest mb-1">Role</div>
                            <div class="text-sm font-bold text-gray-300">
                                <?php echo ucfirst($_SESSION['user']['role'] ?? 'User'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Form Fields -->
                    <div class="mb-5">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <i class="fas fa-id-badge text-gold"></i> Display Name
                        </label>
                        <input type="text" id="personaname" name="personaname"
                            value="<?php echo htmlspecialchars($_SESSION['user']['personaname']); ?>"
                            class="w-full bg-black/40 border border-zinc-800 rounded-lg px-4 py-3 text-gray-200 text-sm focus:border-gold/50 focus:ring-2 focus:ring-gold/10 outline-none transition-all">
                    </div>

                    <div class="mb-8">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <i class="fas fa-camera text-gold"></i> Change Avatar
                        </label>
                        <input type="file" id="avatar_file" name="avatar_file" accept="image/*"
                            class="w-full bg-black/40 border border-zinc-800 rounded-lg px-4 py-2 text-gray-400 text-sm cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-gold/10 file:text-gold hover:file:bg-gold/20 file:transition-colors">
                    </div>

                    <button type="submit" name="update_profile"
                        class="w-full bg-gradient-to-r from-[#c5a059] to-[#b8944d] text-[#0a0a0c] font-bold py-3 px-4 rounded-xl shadow-[0_4px_20px_rgba(197,160,89,0.2)] hover:shadow-[0_4px_25px_rgba(197,160,89,0.4)] hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2 text-sm tracking-wide uppercase">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>

                <!-- Action Buttons -->
                <div class="mt-6 pt-6 border-t border-zinc-800">
                    <div class="flex gap-3">
                        <?php if (!empty($_SESSION['user']['generated_password'])): ?>
                            <button onclick="showCredentials()"
                                class="flex-1 bg-transparent border border-gold/30 text-gold py-2.5 px-4 rounded-lg text-sm font-bold hover:bg-gold/10 hover:border-gold/50 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-key"></i> Credentials
                            </button>
                        <?php endif; ?>
                        <button onclick="openResume()"
                            class="flex-1 bg-transparent border border-blue-500/30 text-blue-400 py-2.5 px-4 rounded-lg text-sm font-bold hover:bg-blue-500/10 hover:border-blue-500/50 transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-file-alt"></i> Resume
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Unit Roster (or Admin Table) -->
        <?php if (isAdmin()): ?>
            <!-- Admin Member Management Table -->
            <div>
                <?php include 'admin/includes/member_table.php'; ?>
            </div>
        <?php else: ?>
            <div>
                <div class="bg-surface-card border border-zinc-850 rounded-2xl p-7 shadow-xl h-full flex flex-col">
                    <div class="flex justify-between items-center border-b border-zinc-800 pb-4 mb-6">
                        <h3 class="text-gold font-bold text-lg m-0 flex items-center gap-2">
                            <i class="fas fa-users"></i> Unit Roster
                        </h3>
                        <span class="bg-black/40 border border-zinc-800 px-3 py-1 rounded-full text-xs font-semibold text-gray-300">
                            <?php echo count($members); ?> Personnel
                        </span>
                    </div>

                    <!-- Search Bar -->
                    <div class="bg-black/40 border border-zinc-800 rounded-lg px-4 py-3 mb-6 flex items-center">
                        <i class="fas fa-search text-gray-500"></i>
                        <input type="text" id="rosterSearchInput" placeholder="Search by name or username..."
                            class="bg-transparent border-none text-white w-full text-sm ml-3 outline-none placeholder-gray-600"
                            oninput="filterRoster()">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 flex-1 content-start" id="rosterGrid">
                        <?php foreach ($members as $idx => $member): ?>
                            <div class="roster-card-compact bg-black/20 border border-zinc-800 hover:bg-zinc-850 hover:border-zinc-700 transition-colors rounded-xl p-3 flex items-center gap-3 cursor-pointer" data-roster-idx="<?php echo $idx; ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($member['personaname'])); ?>"
                                onclick="loadResumeData(<?php echo $member['id']; ?>)">
                                
                                <div class="relative shrink-0">
                                    <img src="<?php echo get_avatar($member['avatar']); ?>" alt="Avatar"
                                        class="w-11 h-11 rounded-full object-cover border-2 border-zinc-700"
                                        onerror="this.src='assets/images/default_avatar.png'">
                                    <?php 
                                        $statusColor = 'bg-red-500';
                                        if($member['status'] === 'Active') $statusColor = 'bg-green-500';
                                        if($member['status'] === 'LOA') $statusColor = 'bg-orange-500';
                                    ?>
                                    <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-[#111114] <?php echo $statusColor; ?>"></div>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-bold text-gray-100 truncate"><?php echo htmlspecialchars($member['personaname']); ?></div>
                                    <div class="text-[0.65rem] text-gold uppercase tracking-wider truncate"><?php echo htmlspecialchars($member['rank'] ?? 'Recruit'); ?></div>
                                    <div class="text-[0.7rem] text-gray-500 truncate"><?php echo htmlspecialchars($member['position'] ?? 'Operator'); ?></div>
                                </div>
                                
                                <div class="shrink-0">
                                    <button class="bg-black/30 text-gray-400 hover:text-gold w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                                        <i class="fas fa-file-alt"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Roster Pagination -->
                    <?php if (count($members) > 18): ?>
                        <div id="rosterPagination" class="flex justify-center items-center gap-2 mt-6 pt-4 border-t border-zinc-800">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Credentials Modal -->
<div id="credentialsModal" class="modal-overlay z-[9999] fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface-card border border-zinc-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
        <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-white transition-colors" onclick="closeCredentials()">
            <i class="fas fa-times text-xl"></i>
        </button>

        <h3 class="text-gold font-bold text-xl mb-2">Login Credentials</h3>
        <p class="text-gray-400 text-sm mb-6">Use these details to login without Steam.</p>

        <div class="bg-black/40 border border-zinc-800 p-4 rounded-xl mb-4">
            <label class="text-xs font-bold text-gray-500 tracking-wider mb-1 block">USERNAME</label>
            <div class="text-white font-mono text-lg tracking-wide">
                <?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'N/A'); ?>
            </div>
        </div>

        <div class="bg-black/40 border border-zinc-800 p-4 rounded-xl mb-8">
            <label class="text-xs font-bold text-gray-500 tracking-wider mb-1 block">PASSWORD</label>
            <div class="text-white font-mono text-lg tracking-wide">
                <?php echo htmlspecialchars($_SESSION['user']['generated_password'] ?? 'N/A'); ?>
            </div>
        </div>

        <button onclick="closeCredentials()" class="w-full bg-zinc-800 hover:bg-zinc-700 text-white font-bold py-3 px-4 rounded-xl transition-colors">
            Close
        </button>
    </div>
</div>

<script>
    function showCredentials() {
        const modal = document.getElementById('credentialsModal');
        modal.style.display = 'flex';
        // Small delay to allow display:flex to apply before adding opacity class
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
    }

    function closeCredentials() {
        const modal = document.getElementById('credentialsModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    // Close on outside click (Delegated listener to handle multiple modals)
    window.addEventListener('click', function (event) {
        const credModal = document.getElementById('credentialsModal');
        const alertModal = document.getElementById('alertModal');
        if (event.target == credModal) {
            closeCredentials();
        }
        if (event.target == alertModal) {
            alertModal.style.display = 'none';
        }
    });
</script>



<!-- Generic Alert Modal -->
<div id="alertModal" class="modal-overlay">
    <div class="glass-panel modal-content"
        style="max-width: 400px; text-align: center; border: 1px solid var(--accent-color);">
        <div
            style="width: 60px; height: 60px; background: rgba(76, 175, 80, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
            <i class="fas fa-check" style="font-size: 30px; color: #4caf50;"></i>
        </div>
        <h3 id="alertTitle" style="color: #fff; margin: 0 0 10px; font-family: 'Inter', sans-serif; font-size: 1.5rem;">
            Success!</h3>
        <p id="alertMessage" style="color: #aaa; margin: 0; font-family: 'Inter', sans-serif;">Operation successful.</p>
    </div>
</div>

<script>
    function showAlert(title, message) {
        document.getElementById('alertTitle').innerText = title;
        document.getElementById('alertMessage').innerText = message;
        const modal = document.getElementById('alertModal');
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);

        setTimeout(() => {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }, 2000);
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Check for login success param
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('login') && urlParams.get('login') === 'success') {
            showAlert('Welcome Back!', 'Login successful.');
            // Clean URL
            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({
                path: newUrl
            }, '', newUrl);
        }

        // Check for PHP message (Profile Update)
        <?php if (!empty($message)): ?>
            showAlert('Success!', '<?php echo addslashes($message); ?>');
        <?php endif; ?>
    });
</script>

<script>
    // Roster search filter
    function filterRoster() {
        const input = document.getElementById('rosterSearchInput');
        const filter = input.value.toLowerCase();
        const grid = document.getElementById('rosterGrid');
        const cards = grid.querySelectorAll('.roster-card-compact');
        cards.forEach(card => {
            const name = card.getAttribute('data-name') || '';
            const text = card.textContent.toLowerCase();
            if (name.indexOf(filter) > -1 || text.indexOf(filter) > -1) {
                card.style.display = '';
                card.removeAttribute('data-hidden');
            } else {
                card.style.display = 'none';
                card.setAttribute('data-hidden', '1');
            }
        });
        // Reset pagination
        if (window.rosterGoPage) window.rosterGoPage(1);
    }

    // Unit Roster Pagination (8 per page for compact)
    document.addEventListener('DOMContentLoaded', function () {
        const grid = document.getElementById('rosterGrid');
        const pagDiv = document.getElementById('rosterPagination');
        if (!grid || !pagDiv) return;

        const allCards = Array.from(grid.querySelectorAll('.roster-card-compact'));
        const perPage = 18;
        let currentPage = 1;

        function getVisibleCards() {
            return allCards.filter(c => !c.hasAttribute('data-hidden'));
        }

        function showPage(page) {
            const visible = getVisibleCards();
            const totalPages = Math.ceil(visible.length / perPage);
            if (page > totalPages) page = totalPages || 1;
            currentPage = page;
            const start = (page - 1) * perPage;
            const end = start + perPage;
            // Hide all first
            allCards.forEach(c => { if (!c.hasAttribute('data-hidden')) c.style.display = 'none'; });
            visible.forEach((card, i) => {
                card.style.display = (i >= start && i < end) ? '' : 'none';
            });
            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            if (totalPages <= 1) { pagDiv.style.display = 'none'; return; }
            pagDiv.style.display = 'flex';
            let html = '';
            if (currentPage > 1) {
                html += '<button onclick="rosterGoPage(' + (currentPage - 1) + ')" style="display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:6px;color:rgba(255,255,255,0.6);cursor:pointer;font-size:0.75rem;"><i class="fas fa-chevron-left"></i></button>';
            }
            for (let p = 1; p <= totalPages; p++) {
                const isActive = p === currentPage;
                const bg = isActive ? 'background:linear-gradient(135deg,#c5a059,#b8944d);color:#0a0a0c;border:1px solid #c5a059;font-weight:600;' : 'background:rgba(255,255,255,0.05);color:rgba(255,255,255,0.6);border:1px solid rgba(255,255,255,0.12);';
                html += '<button onclick="rosterGoPage(' + p + ')" style="display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;border-radius:6px;cursor:pointer;font-size:0.75rem;' + bg + '">' + p + '</button>';
            }
            if (currentPage < totalPages) {
                html += '<button onclick="rosterGoPage(' + (currentPage + 1) + ')" style="display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:6px;color:rgba(255,255,255,0.6);cursor:pointer;font-size:0.75rem;"><i class="fas fa-chevron-right"></i></button>';
            }
            html += '<span style="color:rgba(255,255,255,0.3);font-size:0.68rem;margin-left:6px;">Page ' + currentPage + ' of ' + totalPages + '</span>';
            pagDiv.innerHTML = html;
        }

        window.rosterGoPage = showPage;
        showPage(1);
    });
</script>

<?php include ROOT_PATH . '/includes/resume_modal.php'; ?>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
