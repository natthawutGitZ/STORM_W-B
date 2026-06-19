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

<div class="dashboard-container <?php echo isAdmin() ? 'admin-view' : 'member-view'; ?>" style="padding: 20px;">

    <?php if (!isAdmin()): ?>
        <?php include ROOT_PATH . '/includes/user_tabs.php'; ?>
    <?php endif; ?>

    <div class="admin-grid">

        <!-- Left Column: Profile Edit -->
        <div class="profile-section">
            <div class="glass-panel modern-form" style="padding: 28px;">
                <h3
                    style="border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 15px; margin-bottom: 24px; color: var(--accent-color); font-size: 1.05rem; font-weight: 600; letter-spacing: 0.3px;">
                    <i class="fas fa-user-edit" style="margin-right: 8px;"></i>Edit Profile
                </h3>

                <?php if ($message): ?>
                    <div
                        style="background: rgba(76, 175, 80, 0.1); color: #69f0ae; padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; border: 1px solid rgba(76, 175, 80, 0.2); font-size: 0.85rem;">
                        <i class="fas fa-check-circle" style="margin-right: 6px;"></i><?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div
                        style="background: rgba(244, 67, 54, 0.1); color: #ff5252; padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; border: 1px solid rgba(244, 67, 54, 0.2); font-size: 0.85rem;">
                        <i class="fas fa-exclamation-circle" style="margin-right: 6px;"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <!-- Avatar Section -->
                    <div style="text-align: center; margin-bottom: 28px;">
                        <div
                            style="width: 100px; height: 100px; border-radius: 50%; border: 3px solid var(--accent-color); padding: 3px; margin: 0 auto; background: linear-gradient(135deg, rgba(197,160,89,0.3), rgba(197,160,89,0.1)); box-shadow: 0 0 25px rgba(197,160,89,0.15);">
                            <img src="<?php echo get_avatar($_SESSION['user']['avatar']); ?>" alt="Profile Avatar"
                                style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        </div>
                        <div
                            style="margin-top: 12px; font-size: 0.95rem; font-weight: 600; color: #fff; letter-spacing: 0.3px;">
                            <?php echo htmlspecialchars($_SESSION['user']['personaname']); ?>
                        </div>
                        <div style="margin-top: 4px;">
                            <span
                                style="display: inline-block; background: linear-gradient(135deg, rgba(197,160,89,0.2), rgba(197,160,89,0.1)); color: var(--accent-color); padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; border: 1px solid rgba(197,160,89,0.25);">
                                <?php echo htmlspecialchars($_SESSION['user']['rank'] ?? 'Recruit'); ?>
                            </span>
                        </div>
                        <?php if (!empty($_SESSION['user']['position'])): ?>
                            <div style="margin-top: 6px; color: rgba(255,255,255,0.4); font-size: 0.8rem;">
                                <?php echo htmlspecialchars($_SESSION['user']['position']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info Cards -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 22px;">
                        <div
                            style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 12px; text-align: center;">
                            <div
                                style="font-size: 0.65rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                                Status</div>
                            <div
                                style="font-size: 0.85rem; font-weight: 600; color: <?php echo ($_SESSION['user']['status'] ?? 'Active') === 'Active' ? '#69f0ae' : (($_SESSION['user']['status'] ?? '') === 'LOA' ? '#ffab40' : '#ff5252'); ?>;">
                                <?php echo htmlspecialchars($_SESSION['user']['status'] ?? 'Active'); ?>
                            </div>
                        </div>
                        <div
                            style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 12px; text-align: center;">
                            <div
                                style="font-size: 0.65rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                                Role</div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: rgba(255,255,255,0.8);">
                                <?php echo ucfirst($_SESSION['user']['role'] ?? 'User'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Form Fields -->
                    <div style="margin-bottom: 16px;">
                        <label
                            style="display: block; font-size: 0.72rem; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">
                            <i class="fas fa-id-badge"
                                style="margin-right: 5px; color: var(--accent-color); font-size: 0.75rem;"></i>Display
                            Name
                        </label>
                        <input type="text" id="personaname" name="personaname"
                            value="<?php echo htmlspecialchars($_SESSION['user']['personaname']); ?>"
                            style="width: 100%; padding: 11px 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #f0f0f0; font-family: 'Inter', sans-serif; font-size: 0.88rem; transition: all 0.25s ease; box-sizing: border-box;"
                            onfocus="this.style.borderColor='rgba(197,160,89,0.5)';this.style.boxShadow='0 0 0 3px rgba(197,160,89,0.1)'"
                            onblur="this.style.borderColor='rgba(255,255,255,0.1)';this.style.boxShadow='none'">
                    </div>

                    <div style="margin-bottom: 22px;">
                        <label
                            style="display: block; font-size: 0.72rem; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">
                            <i class="fas fa-camera"
                                style="margin-right: 5px; color: var(--accent-color); font-size: 0.75rem;"></i>Change
                            Avatar
                        </label>
                        <div style="position: relative;">
                            <input type="file" id="avatar_file" name="avatar_file" accept="image/*"
                                style="width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: rgba(255,255,255,0.6); font-size: 0.82rem; box-sizing: border-box; cursor: pointer;">
                        </div>
                    </div>

                    <button type="submit" name="update_profile"
                        style="width: 100%; background: linear-gradient(135deg, var(--accent-color), #b8944d); color: #0a0a0c; padding: 12px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 0.9rem; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px;"
                        onmouseover="this.style.boxShadow='0 4px 20px rgba(197,160,89,0.3)';this.style.transform='translateY(-1px)'"
                        onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                        <i class="fas fa-save" style="margin-right: 6px;"></i>Save Changes
                    </button>
                </form>

                <!-- Action Buttons -->
                <div style="margin-top: 20px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.06);">
                    <div style="display: flex; gap: 10px;">
                        <?php if (!empty($_SESSION['user']['generated_password'])): ?>
                            <button onclick="showCredentials()"
                                style="flex: 1; background: transparent; color: var(--accent-color); padding: 10px 16px; border: 1px solid rgba(197,160,89,0.3); border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.82rem; transition: all 0.3s ease; letter-spacing: 0.3px;"
                                onmouseover="this.style.background='rgba(197,160,89,0.1)';this.style.borderColor='rgba(197,160,89,0.5)'"
                                onmouseout="this.style.background='transparent';this.style.borderColor='rgba(197,160,89,0.3)'">
                                <i class="fas fa-key" style="margin-right: 5px;"></i>Credentials
                            </button>
                        <?php endif; ?>
                        <button onclick="openResume()"
                            style="flex: 1; background: transparent; color: #42a5f5; padding: 10px 16px; border: 1px solid rgba(66,165,245,0.3); border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.82rem; transition: all 0.3s ease; letter-spacing: 0.3px;"
                            onmouseover="this.style.background='rgba(66,165,245,0.1)';this.style.borderColor='rgba(66,165,245,0.5)'"
                            onmouseout="this.style.background='transparent';this.style.borderColor='rgba(66,165,245,0.3)'">
                            <i class="fas fa-file-alt" style="margin-right: 5px;"></i>Resume
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Unit Roster (or Admin Table) -->
        <?php if (isAdmin()): ?>
            <!-- Admin Member Management Table -->
            <div class="members-section">
                <?php include 'admin/includes/member_table.php'; ?>
            </div>
        <?php else: ?>
            <div class="members-section">
                <div class="glass-panel" style="height: 100%; padding: 20px;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 12px;">
                        <h3 style="margin: 0; color: var(--accent-color); font-size: 1rem; font-weight: 600;"><i
                                class="fas fa-users" style="margin-right: 8px;"></i>Unit Roster</h3>
                        <span
                            style="background: rgba(255,255,255,0.08); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; color: rgba(255,255,255,0.7); font-weight: 500;">
                            <?php echo count($members); ?> Personnel
                        </span>
                    </div>

                    <!-- Search Bar -->
                    <div
                        style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px 16px; margin-bottom: 15px; display: flex; align-items: center;">
                        <i class="fas fa-search" style="color: #777;"></i>
                        <input type="text" id="rosterSearchInput" placeholder="Search by name or username..."
                            style="background: transparent; border: none; color: #fff; width: 100%; font-size: 0.88rem; margin-left: 10px; outline: none;"
                            oninput="filterRoster()">
                    </div>

                    <style>
                        .roster-grid-compact {
                            display: grid;
                            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                            gap: 10px;
                        }

                        .roster-card-compact {
                            background: rgba(255, 255, 255, 0.03);
                            border-radius: 10px;
                            padding: 12px 14px;
                            border: 1px solid rgba(255, 255, 255, 0.06);
                            transition: all 0.2s ease;
                            display: flex;
                            align-items: center;
                            gap: 12px;
                        }

                        .roster-card-compact:hover {
                            background: rgba(255, 255, 255, 0.06);
                            border-color: rgba(197, 160, 89, 0.2);
                        }

                        .rcc-avatar {
                            position: relative;
                            flex-shrink: 0;
                        }

                        .rcc-avatar img {
                            width: 44px;
                            height: 44px;
                            border-radius: 50%;
                            object-fit: cover;
                            border: 2px solid rgba(197, 160, 89, 0.4);
                        }

                        .rcc-status-dot {
                            position: absolute;
                            bottom: 1px;
                            right: 1px;
                            width: 10px;
                            height: 10px;
                            border-radius: 50%;
                            border: 2px solid #1a1a1a;
                            background: #f44336;
                        }

                        .rcc-status-dot.active {
                            background: #4caf50;
                        }

                        .rcc-status-dot.loa {
                            background: #ff9800;
                        }

                        .rcc-info {
                            flex: 1;
                            min-width: 0;
                        }

                        .rcc-name {
                            font-size: 0.88rem;
                            font-weight: 600;
                            color: #fff;
                            margin: 0 0 2px 0;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        }

                        .rcc-rank {
                            font-size: 0.72rem;
                            color: var(--accent-color);
                            opacity: 0.85;
                        }

                        .rcc-position {
                            font-size: 0.7rem;
                            color: #777;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        }

                        .rcc-action {
                            flex-shrink: 0;
                        }

                        .rcc-btn {
                            background: rgba(255, 255, 255, 0.06);
                            border: 1px solid rgba(255, 255, 255, 0.1);
                            color: #aaa;
                            padding: 5px 10px;
                            border-radius: 6px;
                            font-size: 0.7rem;
                            cursor: pointer;
                            transition: all 0.2s;
                            white-space: nowrap;
                        }

                        .rcc-btn:hover {
                            background: rgba(197, 160, 89, 0.15);
                            border-color: rgba(197, 160, 89, 0.3);
                            color: var(--accent-color);
                        }
                    </style>

                    <div class="roster-grid-compact" id="rosterGrid">
                        <?php foreach ($members as $idx => $member): ?>
                            <div class="roster-card-compact" data-roster-idx="<?php echo $idx; ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($member['personaname'])); ?>">
                                <div class="rcc-avatar">
                                    <img src="<?php echo get_avatar($member['avatar']); ?>" alt="Avatar"
                                        onerror="this.src='assets/images/default_avatar.png'">
                                    <div class="rcc-status-dot <?php echo strtolower($member['status']); ?>"></div>
                                </div>
                                <div class="rcc-info">
                                    <div class="rcc-name"><?php echo htmlspecialchars($member['personaname']); ?></div>
                                    <div class="rcc-rank"><?php echo htmlspecialchars($member['rank'] ?? 'Recruit'); ?></div>
                                    <div class="rcc-position"><?php echo htmlspecialchars($member['position'] ?? 'Operator'); ?>
                                    </div>
                                </div>
                                <div class="rcc-action">
                                    <button onclick="loadResumeData(<?php echo $member['id']; ?>)" class="rcc-btn">
                                        <i class="fas fa-file-alt"></i> Dossier
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Roster Pagination -->
                    <?php if (count($members) > 18): ?>
                        <div id="rosterPagination"
                            style="display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 15px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.06);">
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Credentials Modal -->
<div id="credentialsModal" class="modal-overlay">
    <div class="glass-panel modal-content" style="max-width: 400px; text-align: center;">
        <div class="modal-header"
            style="justify-content: flex-end; border: none; padding-bottom: 0; margin-bottom: 10px;">
            <button type="button" class="modal-close" onclick="closeCredentials()">&times;</button>
        </div>

        <h3 style="color: var(--accent-color); margin-bottom: 20px;">Login Credentials</h3>
        <p style="color: #ccc; margin-bottom: 20px; font-size: 0.9rem;">Use these details to login without Steam.</p>

        <div
            style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: left; border: 1px solid rgba(255,255,255,0.05);">
            <label style="color: #666; font-size: 0.8rem; display: block; margin-bottom: 5px;">USERNAME</label>
            <div style="color: #fff; font-family: monospace; font-size: 1.2rem; letter-spacing: 1px;">
                <?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'N/A'); ?>
            </div>
        </div>

        <div
            style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: left; border: 1px solid rgba(255,255,255,0.05);">
            <label style="color: #666; font-size: 0.8rem; display: block; margin-bottom: 5px;">PASSWORD</label>
            <div style="color: #fff; font-family: monospace; font-size: 1.2rem; letter-spacing: 1px;">
                <?php echo htmlspecialchars($_SESSION['user']['generated_password'] ?? 'N/A'); ?>
            </div>
        </div>

        <button onclick="closeCredentials()" class="btn" style="width: 100%;">Close</button>
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
