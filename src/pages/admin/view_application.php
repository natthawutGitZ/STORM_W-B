<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    redirect('../login');
}

if (!isset($_GET['id'])) {
    redirect('applications');
}

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT a.*, u.personaname, u.steamid, u.profileurl, u.avatar FROM applications a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
$stmt->execute([$id]);
$app = $stmt->fetch();

if (!$app) {
    echo "Application not found.";
    exit();
}

// Handle Actions
if (isset($_POST['action'])) {
    $status = $_POST['action']; // 'accepted' or 'rejected'
    if (in_array($status, ['accepted', 'rejected'])) {
        $updateStmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $updateStmt->execute([$status, $id]);

        // --- RECORD ADMIN ACTION ---
        $currentAdmin = getUser();
        $adminId = $currentAdmin ? $currentAdmin['id'] : null;
        $adminName = $currentAdmin ? ($currentAdmin['personaname'] ?? $currentAdmin['username'] ?? 'Unknown') : 'Unknown';

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_log (
                id INT(11) NOT NULL AUTO_INCREMENT,
                admin_id INT(11) NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(50) DEFAULT NULL,
                target_id INT(11) DEFAULT NULL,
                details TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_admin_id (admin_id),
                KEY idx_created_at (created_at DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $actionName = $status === 'accepted' ? 'approve_application' : 'reject_application';
            $logStmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, 'application', ?, ?, ?)");
            $logStmt->execute([
                $adminId,
                $actionName,
                $id,
                json_encode(['status' => $status, 'admin_name' => $adminName]),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Activity log insert failed: " . $e->getMessage());
        }

        // Refresh data
        header("Location: view_application.php?id=$id");
        exit();
    }
}

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">

    <!-- Back Button -->
    <div style="margin-bottom: 30px;">
        <a href="applications.php"
            style="color: #aaa; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: color 0.2s;">
            <i class="fas fa-arrow-left"></i> Back to Applications
        </a>
    </div>

    <!-- Application Header -->
    <div
        style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(33, 150, 243, 0.05)); padding: 25px; border-radius: 10px; border: 1px solid rgba(33, 150, 243, 0.3); margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="margin: 0 0 5px 0; color: var(--accent-color);">
                    <i class="fas fa-file-alt"></i> Application #<?php echo $app['id']; ?>
                </h2>
                <p style="margin: 0; color: #aaa; font-size: 0.9rem;">
                    Submitted on <?php echo date('F j, Y \a\t g:i A', strtotime($app['created_at'])); ?>
                </p>
            </div>
            <?php
            $badge_colors = [
                'pending' => 'background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.4);',
                'accepted' => 'background: rgba(76, 175, 80, 0.2); color: #4caf50; border: 1px solid rgba(76, 175, 80, 0.4);',
                'rejected' => 'background: rgba(244, 67, 54, 0.2); color: #f44336; border: 1px solid rgba(244, 67, 54, 0.4);'
            ];
            $badge_style = $badge_colors[$app['status']] ?? '';
            ?>
            <span
                style="<?php echo $badge_style; ?> padding: 8px 16px; border-radius: 20px; font-size: 1rem; font-weight: 500;">
                <?php echo ucfirst($app['status']); ?>
            </span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">

        <!-- Left Column: Application Details -->
        <div>
            <!-- Applicant Information -->
            <div
                style="background: rgba(20, 20, 20, 0.8); padding: 25px; border-radius: 10px; border: 1px solid #333; margin-bottom: 20px;">
                <h3
                    style="color: var(--accent-color); margin: 0 0 20px 0; border-bottom: 1px solid #333; padding-bottom: 15px;">
                    <i class="fas fa-user"></i> Applicant Information
                </h3>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 5px;">Full
                        Name</label>
                    <div style="font-size: 1.3rem; font-weight: 500; color: #fff;">
                        <?php echo htmlspecialchars($app['name']); ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 5px;">Age</label>
                        <div
                            style="padding: 10px; background: #111; border: 1px solid #333; border-radius: 5px; color: #fff;">
                            <?php echo htmlspecialchars($app['age']); ?> years old
                        </div>
                    </div>
                    <div>
                        <label
                            style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 5px;">Timezone</label>
                        <div
                            style="padding: 10px; background: #111; border: 1px solid #333; border-radius: 5px; color: #fff;">
                            <?php echo htmlspecialchars($app['timezone']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Experience -->
            <div
                style="background: rgba(20, 20, 20, 0.8); padding: 25px; border-radius: 10px; border: 1px solid #333; margin-bottom: 20px;">
                <h3
                    style="color: var(--accent-color); margin: 0 0 15px 0; border-bottom: 1px solid #333; padding-bottom: 15px;">
                    <i class="fas fa-medal"></i> Milsim Experience
                </h3>
                <div
                    style="padding: 15px; background: #111; border: 1px solid #333; border-radius: 5px; color: #e0e0e0; white-space: pre-wrap; line-height: 1.6;">
                    <?php echo htmlspecialchars($app['experience']); ?>
                </div>
            </div>

            <!-- Reason for Joining -->
            <div style="background: rgba(20, 20, 20, 0.8); padding: 25px; border-radius: 10px; border: 1px solid #333;">
                <h3
                    style="color: var(--accent-color); margin: 0 0 15px 0; border-bottom: 1px solid #333; padding-bottom: 15px;">
                    <i class="fas fa-comment-dots"></i> Reason for Joining
                </h3>
                <div
                    style="padding: 15px; background: #111; border: 1px solid #333; border-radius: 5px; color: #e0e0e0; white-space: pre-wrap; line-height: 1.6;">
                    <?php echo htmlspecialchars($app['reason']); ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Steam Profile & Actions -->
        <div>
            <!-- Steam Profile Card -->
            <div
                style="background: rgba(20, 20, 20, 0.8); padding: 25px; border-radius: 10px; border: 1px solid #333; margin-bottom: 20px; position: sticky; top: 100px;">
                <h3
                    style="color: var(--accent-color); margin: 0 0 20px 0; border-bottom: 1px solid #333; padding-bottom: 15px;">
                    <i class="fab fa-steam"></i> Steam Profile
                </h3>

                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="<?php echo htmlspecialchars($app['avatar']); ?>" alt="Avatar"
                        style="width: 120px; height: 120px; border-radius: 50%; border: 3px solid var(--accent-color); margin-bottom: 15px;">
                    <h4 style="margin: 0 0 10px 0; color: #fff; font-size: 1.2rem;">
                        <?php echo htmlspecialchars($app['personaname']); ?>
                    </h4>
                    <a href="<?php echo htmlspecialchars($app['profileurl']); ?>" target="_blank"
                        style="color: var(--accent-color); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 0.9rem;">
                        <i class="fas fa-external-link-alt"></i> View Steam Profile
                    </a>
                </div>

                <div
                    style="padding: 15px; background: #111; border: 1px solid #333; border-radius: 5px; margin-bottom: 10px;">
                    <div style="color: #aaa; font-size: 0.85rem; margin-bottom: 5px;">Steam ID</div>
                    <code
                        style="color: var(--accent-color); font-size: 0.9rem; word-break: break-all;"><?php echo $app['steamid']; ?></code>
                </div>

                <div style="padding: 15px; background: #111; border: 1px solid #333; border-radius: 5px;">
                    <div style="color: #aaa; font-size: 0.85rem; margin-bottom: 5px;">Applied</div>
                    <div style="color: #fff; font-size: 0.95rem;">
                        <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                    </div>
                    <div style="color: #666; font-size: 0.85rem;">
                        <?php echo date('g:i A', strtotime($app['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div style="background: rgba(20, 20, 20, 0.8); padding: 25px; border-radius: 10px; border: 1px solid #333;">
                <h3
                    style="color: var(--accent-color); margin: 0 0 20px 0; border-bottom: 1px solid #333; padding-bottom: 15px;">
                    <i class="fas fa-tasks"></i> Actions
                </h3>

                <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if ($app['status'] !== 'accepted'): ?>
                        <button type="submit" name="action" value="accepted"
                            onclick="return confirmAction(event, 'Approve Application', 'Are you sure you want to APPROVE this application?')"
                            style="width: 100%; padding: 12px; background: linear-gradient(135deg, #4caf50, #45a049); color: #fff; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s;">
                            <i class="fas fa-check-circle"></i> Approve Application
                        </button>
                    <?php endif; ?>

                    <?php if ($app['status'] !== 'rejected'): ?>
                        <button type="submit" name="action" value="rejected"
                            onclick="return confirmAction(event, 'Reject Application', 'Are you sure you want to REJECT this application?')"
                            style="width: 100%; padding: 12px; background: linear-gradient(135deg, #f44336, #e53935); color: #fff; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s;">
                            <i class="fas fa-times-circle"></i> Reject Application
                        </button>
                    <?php endif; ?>

                    <?php if ($app['status'] === 'accepted'): ?>
                        <div
                            style="padding: 15px; background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 5px; color: #4caf50; text-align: center;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <div style="font-weight: 500;">Application Approved</div>
                        </div>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                        <div
                            style="padding: 15px; background: rgba(244, 67, 54, 0.1); border: 1px solid rgba(244, 67, 54, 0.3); border-radius: 5px; color: #f44336; text-align: center;">
                            <i class="fas fa-times-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <div style="font-weight: 500;">Application Rejected</div>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    button:hover {
        transform: translateY(-2px);
    }

    @media (max-width: 900px) {
        div[style*="grid-template-columns: 2fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

</div> <!-- End Section -->
</body>

</html>

