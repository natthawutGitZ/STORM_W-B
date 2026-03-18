<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Edit Profile');

if (!isLoggedIn()) {
    redirect('login');
}

$user_id = $_SESSION['user']['id'];
$message = '';
$error = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $personaname = sanitize($_POST['personaname']);
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $unit = sanitize($_POST['unit']);
    $position = sanitize($_POST['position']);
    $mos = sanitize($_POST['mos']);
    $trainings = sanitize($_POST['trainings']);
    $background = sanitize($_POST['background']);

    // Handle File Upload
    $avatar_url = $user['avatar'];
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/uploads/avatars/';
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
                $avatar_url = $dest_path;
            } else {
                $error = 'Error moving uploaded file.';
            }
        } else {
            $error = 'Invalid file type.';
        }
    }

    if (empty($error)) {
        try {
            $sql = "UPDATE users SET personaname = ?, avatar = ?, dob = ?, unit = ?, position = ?, mos = ?, trainings = ?, background = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$personaname, $avatar_url, $dob, $unit, $position, $mos, $trainings, $background, $user_id]);

            // Update Session
            $_SESSION['user']['personaname'] = $personaname;
            $_SESSION['user']['avatar'] = $avatar_url;

            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $message = "Profile updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDIT PROFILE | TACTICAL DASHBOARD</title>
    <link rel="stylesheet" href="assets/css/military.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="military-theme">

    <div class="scanline"></div>

    <div class="hud-container">
        <!-- Sidebar Navigation -->
        <div class="hud-sidebar" style="width: 80px; align-items: center; padding-top: 20px;">
            <div style="margin-bottom: 40px; color: var(--mil-accent); font-size: 1.5rem;">
                <i class="fas fa-shield-alt"></i>
            </div>

            <a href="profile" style="color: #666; font-size: 1.2rem; margin-bottom: 30px;"><i
                    class="fas fa-home"></i></a>
            <a href="edit_profile" style="color: var(--mil-accent); font-size: 1.2rem; margin-bottom: 30px;"><i
                    class="fas fa-user-edit"></i></a>
            <?php if (isAdmin()): ?>
                <a href="admin/dashboard" style="color: #666; font-size: 1.2rem; margin-bottom: 30px;"><i
                        class="fas fa-cog"></i></a>
            <?php endif; ?>
            <a href="logout" style="color: #ff4444; font-size: 1.2rem; margin-top: auto;"><i
                    class="fas fa-power-off"></i></a>
        </div>

        <!-- Main Content -->
        <div class="hud-center"
            style="flex-direction: column; justify-content: flex-start; padding: 20px; overflow-y: auto;">

            <!-- Header -->
            <div
                style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--mil-accent-dim); padding-bottom: 10px;">
                <div style="font-size: 1.2rem; color: #fff;">
                    <i class="fas fa-user-edit"></i> EDIT PERSONNEL RECORD
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="text-align: right;">
                        <div style="color: #fff; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($user['personaname']); ?>
                        </div>
                        <div style="color: var(--mil-accent); font-size: 0.7rem;">
                            <?php echo htmlspecialchars($user['rank']); ?>
                        </div>
                    </div>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>"
                        style="width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--mil-accent);">
                </div>
            </div>

            <div class="mil-card" style="width: 100%; max-width: 900px;">

                <?php if ($message): ?>
                    <div
                        style="background: rgba(0, 255, 157, 0.1); color: var(--mil-accent); padding: 15px; border: 1px solid var(--mil-accent); margin-bottom: 20px; font-family: var(--mil-font-mono);">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div
                        style="background: rgba(255, 68, 68, 0.1); color: #ff4444; padding: 15px; border: 1px solid #ff4444; margin-bottom: 20px; font-family: var(--mil-font-mono);">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">

                    <div style="display: flex; gap: 30px; margin-bottom: 30px;">
                        <!-- Avatar Upload -->
                        <div style="flex: 0 0 200px; text-align: center;">
                            <div style="position: relative; width: 150px; height: 150px; margin: 0 auto 15px;">
                                <img src="<?php echo htmlspecialchars($user['avatar']); ?>"
                                    style="width: 100%; height: 100%; object-fit: cover; border: 2px solid var(--mil-accent); border-radius: 4px;">
                                <div
                                    style="position: absolute; bottom: -10px; right: -10px; background: var(--mil-bg); border: 1px solid var(--mil-accent); padding: 5px;">
                                    <i class="fas fa-camera" style="color: var(--mil-accent);"></i>
                                </div>
                            </div>
                            <label for="avatar_file" class="mil-btn"
                                style="padding: 5px 10px; font-size: 0.8rem; display: inline-block; width: auto;">CHANGE
                                PHOTO</label>
                            <input type="file" id="avatar_file" name="avatar_file" style="display: none;"
                                onchange="document.getElementById('file-name').textContent = this.files[0].name">
                            <div id="file-name"
                                style="margin-top: 5px; color: var(--mil-text-muted); font-size: 0.8rem; font-family: var(--mil-font-mono);">
                            </div>
                        </div>

                        <!-- Basic Info -->
                        <div style="flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <label
                                    style="display: block; color: var(--mil-text-muted); font-size: 0.8rem; margin-bottom: 5px; font-family: var(--mil-font-mono);">DISPLAY
                                    NAME</label>
                                <input type="text" name="personaname"
                                    value="<?php echo htmlspecialchars($user['personaname']); ?>" class="mil-input"
                                    style="margin-bottom: 0;">
                            </div>
                            <div>
                                <label
                                    style="display: block; color: var(--mil-text-muted); font-size: 0.8rem; margin-bottom: 5px; font-family: var(--mil-font-mono);">DATE
                                    OF BIRTH</label>
                                <input type="date" name="dob"
                                    value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>" class="mil-input"
                                    style="margin-bottom: 0; color-scheme: dark;">
                            </div>
                            <div>
                                <label
                                    style="display: block; color: var(--mil-text-muted); font-size: 0.8rem; margin-bottom: 5px; font-family: var(--mil-font-mono);">UNIT</label>
                                <input type="text" name="unit"
                                    value="<?php echo htmlspecialchars($user['unit'] ?? ''); ?>" class="mil-input"
                                    style="margin-bottom: 0;">
                            </div>
                            <div>
                                <label
                                    style="display: block; color: var(--mil-text-muted); font-size: 0.8rem; margin-bottom: 5px; font-family: var(--mil-font-mono);">POSITION</label>
                                <input type="text" name="position"
                                    value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" class="mil-input"
                                    style="margin-bottom: 0;">
                            </div>
                            <div style="grid-column: span 2;">
                                <label
                                    style="display: block; color: var(--mil-text-muted); font-size: 0.8rem; margin-bottom: 5px; font-family: var(--mil-font-mono);">MOS
                                    (MILITARY OCCUPATIONAL SPECIALTY)</label>
                                <input type="text" name="mos"
                                    value="<?php echo htmlspecialchars($user['mos'] ?? ''); ?>" class="mil-input"
                                    style="margin-bottom: 0;">
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label
                            style="display: block; color: var(--mil-text-muted); font-size: 0.8rem; margin-bottom: 5px; font-family: var(--mil-font-mono);">TRAININGS
                            & SCHOOLS (ONE PER LINE)</label>
                        <textarea name="trainings" rows="5" class="mil-input"
                            style="margin-bottom: 0; resize: vertical;"><?php echo htmlspecialchars($user['trainings'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-bottom: 30px;">
                        <label
                            style="display: block; color: var(--mil-text-muted); font-size: 0.8rem; margin-bottom: 5px; font-family: var(--mil-font-mono);">BACKGROUND
                            / BIOGRAPHY</label>
                        <textarea name="background" rows="6" class="mil-input"
                            style="margin-bottom: 0; resize: vertical;"><?php echo htmlspecialchars($user['background'] ?? ''); ?></textarea>
                    </div>

                    <div style="display: flex; gap: 20px;">
                        <button type="submit" name="update_profile" class="mil-btn">SAVE CHANGES</button>
                        <a href="profile" class="mil-btn"
                            style="text-align: center; text-decoration: none; border-color: #666; color: #aaa;">CANCEL</a>
                    </div>

                </form>
            </div>

        </div>
    </div>

</body>

</html>
