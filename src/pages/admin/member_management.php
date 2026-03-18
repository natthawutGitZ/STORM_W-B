<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/admin_log.php';

if (!isAdmin()) {
    if (isset($_GET['ajax'])) {
        http_response_code(403);
        exit;
    }
    redirect('/');
}

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'members';
$ajax_mode = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Dispatch to standalone management pages while keeping unified URL
$tab_pages = [
    'ranks' => '/pages/admin/manage_ranks.php',
    'awards' => '/pages/admin/manage_awards.php',
    'qualifications' => '/pages/admin/manage_qualifications.php',
    'positions' => '/pages/admin/manage_positions.php',
];
if (array_key_exists($active_tab, $tab_pages)) {
    require ROOT_PATH . $tab_pages[$active_tab];
    exit;
}

// --- PROFILE UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = sanitize($_POST['personaname']);

    // Handle File Upload
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = ROOT_PATH . '/assets/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['avatar_file']['tmp_name'];
        $fileName = $_FILES['avatar_file']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $avatar_url = 'assets/uploads/avatars/' . $newFileName;
            } else {
                $error = 'There was some error moving the file to upload directory.';
            }
        } else {
            $error = 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions);
        }
    } else {
        $avatar_url = $_SESSION['user']['avatar'];
    }

    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET personaname = ?, avatar = ? WHERE id = ?");
            $stmt->execute([$new_name, $avatar_url, $user_id]);

            $_SESSION['user']['personaname'] = $new_name;
            $_SESSION['user']['avatar'] = $avatar_url;

            $message = "Profile updated successfully!";

            // Log admin action
            logAdminAction($pdo, 'update_profile', 'user', $user_id, [
                'name' => $new_name,
                'avatar_updated' => ($avatar_url !== $_SESSION['user']['avatar'])
            ]);
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// --- MEMBERS LOGIC ---
include ROOT_PATH . '/admin/includes/member_logic.php';

// --- TAGS LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    $tag_name = sanitize($_POST['tag_name']);
    $tag_color = isset($_POST['tag_color']) ? sanitize($_POST['tag_color']) : '#2196f3';

    if (!empty($tag_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stmt->execute([$tag_name, $tag_color]);
            $message = "Tag added successfully!";
            $active_tab = 'tags';
            logAdminAction($pdo, 'add_member_tag', 'tag', $pdo->lastInsertId(), ['name' => $tag_name]);
        } catch (PDOException $e) {
            $error = "Error adding tag: " . $e->getMessage();
            $active_tab = 'tags';
        }
    } else {
        $error = "Tag name is required.";
        $active_tab = 'tags';
    }
}

if (isset($_GET['delete_tag_id'])) {
    $delete_id = $_GET['delete_tag_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
        $stmt->execute([$delete_id]);
        $message = "Tag deleted successfully!";
        $active_tab = 'tags';
        logAdminAction($pdo, 'delete_member_tag', 'tag', $delete_id);
    } catch (PDOException $e) {
        $error = "Error deleting tag: " . $e->getMessage();
        $active_tab = 'tags';
    }
}

$stmt = $pdo->query("SELECT * FROM tags ORDER BY id ASC");
$tags = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM positions ORDER BY category_id ASC, order_index ASC, name ASC");
$all_positions = $stmt->fetchAll();

// --- UNITS LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unit_action'])) {
    $unit_action = $_POST['unit_action'];
    try {
        if ($unit_action === 'create') {
            $unit_name = $_POST['unit_name'] ?? '';
            if ($unit_name) {
                $stmt = $pdo->prepare("INSERT INTO resume_units (unit_name, image_path) VALUES (?, '')");
                $stmt->execute([$unit_name]);
                $new_unit_id = $pdo->lastInsertId();
                if (isset($_FILES['unit_image']) && $_FILES['unit_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_PATH . '/assets/images/units/';
                    if (!file_exists($uploadDir))
                        mkdir($uploadDir, 0777, true);
                    $ext = strtolower(pathinfo($_FILES['unit_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $newFileName = 'unit_' . $new_unit_id . '_' . time() . '.png';
                        if (processUnitImage($_FILES['unit_image']['tmp_name'], $uploadDir . $newFileName)) {
                            $pdo->prepare("UPDATE resume_units SET image_path = ? WHERE id = ?")->execute([$newFileName, $new_unit_id]);
                        }
                    }
                }
                $message = 'Unit created successfully!';
                $active_tab = 'units';
                logAdminAction($pdo, 'create_unit', 'unit', $new_unit_id, ['name' => $unit_name]);
            }
        } elseif ($unit_action === 'update') {
            $unit_id = $_POST['unit_id'] ?? null;
            $unit_name = $_POST['unit_name'] ?? '';
            if ($unit_id && $unit_name) {
                $pdo->prepare("UPDATE resume_units SET unit_name = ? WHERE id = ?")->execute([$unit_name, $unit_id]);
                if (isset($_FILES['unit_image']) && $_FILES['unit_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_PATH . '/assets/images/units/';
                    if (!file_exists($uploadDir))
                        mkdir($uploadDir, 0777, true);
                    $ext = strtolower(pathinfo($_FILES['unit_image']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $newFileName = 'unit_' . $unit_id . '_' . time() . '.png';
                        if (processUnitImage($_FILES['unit_image']['tmp_name'], $uploadDir . $newFileName)) {
                            $pdo->prepare("UPDATE resume_units SET image_path = ? WHERE id = ?")->execute([$newFileName, $unit_id]);
                        }
                    }
                }
                $message = 'Unit updated successfully!';
                $active_tab = 'units';
                logAdminAction($pdo, 'update_unit', 'unit', $unit_id, ['name' => $unit_name]);
            }
        } elseif ($unit_action === 'delete') {
            $unit_id = $_POST['unit_id'] ?? null;
            if ($unit_id) {
                $stmt = $pdo->prepare("SELECT image_path FROM resume_units WHERE id = ?");
                $stmt->execute([$unit_id]);
                $del_unit = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($del_unit && $del_unit['image_path']) {
                    $imgPath = ROOT_PATH . '/assets/images/units/' . $del_unit['image_path'];
                    if (file_exists($imgPath))
                        unlink($imgPath);
                }
                $pdo->prepare("DELETE FROM resume_units WHERE id = ?")->execute([$unit_id]);
                $message = 'Unit deleted successfully!';
                $active_tab = 'units';
                logAdminAction($pdo, 'delete_unit', 'unit', $unit_id);
            }
        }
    } catch (PDOException $e) {
        $error = 'Unit error: ' . $e->getMessage();
        $active_tab = 'units';
    }
}

$units = $pdo->query("SELECT * FROM resume_units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

if (!$ajax_mode) {
    include ROOT_PATH . '/admin/includes/admin_header.php';
}
?>

<style>
    /* New Dashboard Grid Layout */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 25px;
        padding: 0 20px 20px;
    }

    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Profile Sidebar */
    .profile-sidebar {
        height: fit-content;
    }

    .profile-card {
        position: relative;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 16px;
        padding: 25px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }

    .profile-card:hover {
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .profile-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, rgba(197, 160, 89, 0.5), transparent);
        border-radius: 20px 20px 0 0;
    }

    .profile-header {
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .profile-header h3 {
        color: var(--accent-color, #c5a059);
        font-size: 1rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .profile-avatar-container {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 20px auto;
    }

    .profile-avatar-container img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid var(--accent-color, #c5a059);
        box-shadow: 0 8px 30px rgba(197, 160, 89, 0.3);
        transition: all 0.3s ease;
    }

    .profile-avatar-container:hover img {
        transform: scale(1.05);
        box-shadow: 0 12px 40px rgba(197, 160, 89, 0.4);
    }

    .profile-rank {
        color: #fff;
        font-size: 0.95rem;
        font-weight: 500;
        margin-top: 15px;
        text-align: center;
    }

    .profile-form .form-group {
        margin-bottom: 20px;
    }

    .profile-form .form-group label {
        display: block;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.8rem;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .profile-form .form-group input[type="text"] {
        width: 100%;
        padding: 14px 18px;
        background: linear-gradient(145deg, rgba(0, 0, 0, 0.5) 0%, rgba(20, 20, 25, 0.6) 100%);
        border: 1px solid rgba(197, 160, 89, 0.15);
        border-radius: 12px;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .profile-form .form-group input[type="text"]:focus {
        outline: none;
        border-color: var(--accent-color, #c5a059);
        box-shadow: 0 0 20px rgba(197, 160, 89, 0.25), inset 0 0 10px rgba(197, 160, 89, 0.05);
        background: linear-gradient(145deg, rgba(0, 0, 0, 0.6) 0%, rgba(25, 25, 30, 0.7) 100%);
    }

    /* Custom File Input Styling */
    .file-input-wrapper {
        position: relative;
        overflow: hidden;
        display: block;
    }

    .file-input-wrapper input[type="file"] {
        position: absolute;
        left: 0;
        top: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
        z-index: 2;
    }

    .file-input-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 14px 18px;
        background: linear-gradient(145deg, rgba(30, 33, 40, 0.8) 0%, rgba(20, 23, 28, 0.9) 100%);
        border: 1px dashed rgba(197, 160, 89, 0.3);
        border-radius: 12px;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .file-input-btn i {
        color: var(--accent-color, #c5a059);
        font-size: 1rem;
    }

    .file-input-wrapper:hover .file-input-btn {
        border-color: var(--accent-color, #c5a059);
        background: linear-gradient(145deg, rgba(40, 43, 50, 0.9) 0%, rgba(30, 33, 38, 0.95) 100%);
        color: #fff;
        box-shadow: 0 5px 20px rgba(197, 160, 89, 0.15);
    }

    .file-input-btn .file-name {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Save Button - Modern Glassmorphism */
    .btn-save-profile {
        position: relative;
        width: 100%;
        padding: 16px 20px;
        background: linear-gradient(135deg, var(--accent-color, #c5a059) 0%, #b8944d 50%, #a88942 100%);
        border: none;
        border-radius: 14px;
        color: #0a0a0c;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(197, 160, 89, 0.3);
    }

    .btn-save-profile::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        transition: left 0.6s ease;
    }

    .btn-save-profile:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 10px 35px rgba(197, 160, 89, 0.5), 0 0 20px rgba(197, 160, 89, 0.2);
    }

    .btn-save-profile:hover::before {
        left: 100%;
    }

    .btn-save-profile:active {
        transform: translateY(-1px) scale(0.99);
    }

    .btn-save-profile i {
        margin-right: 8px;
    }

    .profile-actions {
        display: flex;
        gap: 10px;
        margin-top: 25px;
        padding-top: 25px;
        border-top: 1px solid rgba(197, 160, 89, 0.1);
    }

    /* Action Buttons - Modern Style */
    .profile-action-btn {
        position: relative;
        flex: 1;
        padding: 12px 10px;
        border-radius: 12px;
        border: 1px solid rgba(197, 160, 89, 0.25);
        background: linear-gradient(145deg, rgba(25, 28, 35, 0.8) 0%, rgba(15, 17, 21, 0.9) 100%);
        color: var(--accent-color, #c5a059);
        font-weight: 600;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        overflow: hidden;
        text-decoration: none;
        white-space: nowrap;
    }

    .profile-action-btn::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.15) 0%, transparent 50%, rgba(197, 160, 89, 0.1) 100%);
        opacity: 0;
        transition: opacity 0.4s ease;
    }

    .profile-action-btn:hover {
        border-color: var(--accent-color, #c5a059);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(197, 160, 89, 0.25), inset 0 1px 0 rgba(197, 160, 89, 0.2);
    }

    .profile-action-btn:hover::before {
        opacity: 1;
    }

    .profile-action-btn i {
        font-size: 1rem;
        transition: transform 0.3s ease;
    }

    .profile-action-btn:hover i {
        transform: scale(1.15);
    }

    .profile-action-btn.primary {
        background: linear-gradient(145deg, rgba(197, 160, 89, 0.12) 0%, rgba(197, 160, 89, 0.05) 100%);
        border-color: rgba(197, 160, 89, 0.35);
        color: var(--accent-color, #c5a059);
    }

    .profile-action-btn.primary::before {
        background: linear-gradient(135deg, rgba(197, 160, 89, 0.2) 0%, transparent 50%, rgba(197, 160, 89, 0.15) 100%);
    }

    .profile-action-btn.primary:hover {
        background: linear-gradient(145deg, rgba(197, 160, 89, 0.2) 0%, rgba(197, 160, 89, 0.1) 100%);
        border-color: var(--accent-color, #c5a059);
        box-shadow: 0 8px 30px rgba(197, 160, 89, 0.3), inset 0 1px 0 rgba(197, 160, 89, 0.3);
    }

    /* Main Content Area */
    .main-content-area {
        min-width: 0;
    }

    /* Credentials Modal */
    .credentials-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.9);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(10px);
    }

    .credentials-modal.show {
        display: flex;
    }

    .credentials-box {
        background: linear-gradient(145deg, rgba(30, 33, 40, 0.95) 0%, rgba(20, 23, 28, 0.98) 100%);
        border: 1px solid rgba(197, 160, 89, 0.3);
        border-radius: 20px;
        padding: 35px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.7);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .credentials-box h3 {
        color: var(--accent-color, #c5a059);
        margin-bottom: 20px;
        font-size: 1.3rem;
    }

    .credential-item {
        background: rgba(0, 0, 0, 0.4);
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        text-align: left;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .credential-item label {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: block;
        margin-bottom: 8px;
    }

    .credential-item .value {
        color: #fff;
        font-family: monospace;
        font-size: 1.2rem;
        letter-spacing: 1px;
    }

    .btn-close-modal {
        width: 100%;
        padding: 14px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .btn-close-modal:hover {
        background: rgba(255, 255, 255, 0.15);
    }
</style>



<!-- Tabs Navigation - Full Width -->
<?php if (!$ajax_mode) {
    include ROOT_PATH . '/admin/includes/member_tabs.php';
} ?>

<div class="dashboard-container" style="padding-top: 0; padding-bottom: 20px;">
    <!-- Search and Filter - Full Width (only show on members tab) -->
    <?php if ($active_tab === 'members'): ?>
        <div style="margin: 0 0 20px;">
            <div class="search-bar-container">
                <form method="GET" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="tab" value="members">

                    <div style="flex: 3; min-width: 200px;" class="modern-form">
                        <label>
                            <i class="fas fa-search"></i> Search
                        </label>
                        <input type="text" name="search" placeholder="Search by name or username..."
                            value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>

                    <div style="flex: 1; min-width: 150px;" class="modern-form">
                        <label>
                            <i class="fas fa-filter"></i> Status
                        </label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo ($status_filter ?? '') === 'Active' ? 'selected' : ''; ?>>Active
                            </option>
                            <option value="Inactive" <?php echo ($status_filter ?? '') === 'Inactive' ? 'selected' : ''; ?>>
                                Inactive</option>
                            <option value="LOA" <?php echo ($status_filter ?? '') === 'LOA' ? 'selected' : ''; ?>>LOA</option>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 150px;" class="modern-form">
                        <label>
                            <i class="fas fa-user-tag"></i> Role
                        </label>
                        <select name="role">
                            <option value="">All Roles</option>
                            <option value="user" <?php echo ($role_filter ?? '') === 'user' ? 'selected' : ''; ?>>User
                            </option>
                            <option value="admin" <?php echo ($role_filter ?? '') === 'admin' ? 'selected' : ''; ?>>Admin
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn" style="height: 42px;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Grid Layout -->
    <div class="dashboard-grid"
        style="padding: 0; <?php echo $active_tab !== 'members' ? 'grid-template-columns: 1fr;' : ''; ?>">

        <?php if ($active_tab === 'members'): ?>
            <!-- Left Sidebar: Profile -->
            <div class="profile-sidebar">
                <div class="profile-card">
                    <div class="profile-header">
                        <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="profile-form">
                        <div class="profile-avatar-container">
                            <img src="<?php echo get_avatar($_SESSION['user']['avatar']); ?>" alt="Profile Avatar">
                        </div>
                        <div class="profile-rank">
                            <?php echo htmlspecialchars($_SESSION['user']['rank'] ?? 'Recruit'); ?>
                        </div>

                        <div class="form-group" style="margin-top: 25px;">
                            <label for="personaname">Display Name</label>
                            <input type="text" id="personaname" name="personaname"
                                value="<?php echo htmlspecialchars($_SESSION['user']['personaname']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="avatar_file">Change Avatar</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="avatar_file" name="avatar_file" accept="image/*"
                                    onchange="updateFileName(this)">
                                <div class="file-input-btn">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span class="file-name">Choose Image File</span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn-save-profile">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>

                    <?php if (!empty($_SESSION['user']['generated_password'])): ?>
                        <div class="profile-actions">
                            <button onclick="showCredentials()" class="profile-action-btn">
                                <i class="fas fa-key"></i> Credentials
                            </button>
                            <button onclick="openResume()" class="profile-action-btn primary">
                                <i class="fas fa-file-alt"></i> Resume
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="profile-actions">
                            <button onclick="openResume()" class="profile-action-btn primary" style="width: 100%;">
                                <i class="fas fa-file-alt"></i> View Resume
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Right: Main Content -->
        <div class="main-content-area">
            <!-- MEMBERS TAB CONTENT -->
            <div id="members-tab" style="display: <?php echo $active_tab === 'members' ? 'block' : 'none'; ?>;">
                <?php include ROOT_PATH . '/admin/includes/member_table.php'; ?>
            </div>

            <!-- TAGS TAB CONTENT -->
            <div id="tags-tab" style="display: <?php echo $active_tab === 'tags' ? 'block' : 'none'; ?>;">
                <?php include ROOT_PATH . '/admin/includes/tags_tab_content.php'; ?>
            </div>

            <!-- UNITS TAB CONTENT -->
            <div id="units-tab" style="display: <?php echo $active_tab === 'units' ? 'block' : 'none'; ?>;">
                <?php include ROOT_PATH . '/admin/includes/units_tab_content.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Credentials Modal -->
<div id="credentialsModal" class="credentials-modal">
    <div class="credentials-box">
        <h3><i class="fas fa-key"></i> Login Credentials</h3>
        <p style="color: rgba(255,255,255,0.5); margin-bottom: 25px; font-size: 0.9rem;">
            Use these details to login without Steam.
        </p>

        <div class="credential-item">
            <label>Username</label>
            <div class="value"><?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'N/A'); ?></div>
        </div>

        <div class="credential-item">
            <label>Password</label>
            <div class="value"><?php echo htmlspecialchars($_SESSION['user']['generated_password'] ?? 'N/A'); ?>
            </div>
        </div>

        <button onclick="closeCredentials()" class="btn-close-modal">Close</button>
    </div>
</div>

<!-- Global Messages with SweetAlert2 -->
<?php if ($message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: <?php echo json_encode($message); ?>,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: 'linear-gradient(135deg, rgba(76, 175, 80, 0.95) 0%, rgba(56, 142, 60, 0.98) 100%)',
                color: '#fff'
            });
        });
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: <?php echo json_encode($error); ?>,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: 'linear-gradient(135deg, rgba(244, 67, 54, 0.95) 0%, rgba(211, 47, 47, 0.98) 100%)',
                color: '#fff'
            });
        });
    </script>
<?php endif; ?>

<script>
    function showCredentials() {
        document.getElementById('credentialsModal').classList.add('show');
    }

    function closeCredentials() {
        document.getElementById('credentialsModal').classList.remove('show');
    }



    function updateFileName(input) {
        const wrapper = input.closest('.file-input-wrapper');
        const fileNameSpan = wrapper.querySelector('.file-name');
        const icon = wrapper.querySelector('i');

        if (input.files && input.files.length > 0) {
            fileNameSpan.textContent = input.files[0].name;
            icon.className = 'fas fa-check-circle';
            icon.style.color = '#4caf50';
        } else {
            fileNameSpan.textContent = 'Choose Image File';
            icon.className = 'fas fa-cloud-upload-alt';
            icon.style.color = '';
        }
    }

    // Close modal on outside click
    document.getElementById('credentialsModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeCredentials();
        }
    });
</script>

<?php if (!$ajax_mode) { ?>
    </div><!-- #tab-content -->
    <?php
    include ROOT_PATH . '/includes/resume_modal.php';
} ?>

<?php if (!$ajax_mode): ?>
    </body>

    </html>
<?php endif; ?>