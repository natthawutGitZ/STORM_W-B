<?php
require_once ROOT_PATH . '/admin/includes/admin_header.php';

if (!isset($_GET['id'])) {
    redirect('manage_qualifications');
}

$qualification_id = (int) $_GET['id'];

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_qualifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        qualification_id INT NOT NULL,
        date_awarded DATE DEFAULT NULL,
        awarded_by INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id),
        KEY qualification_id (qualification_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
}

// Fetch Qualification Data
$stmt = $pdo->prepare("SELECT q.*, qc.name as category_name FROM qualifications q LEFT JOIN qualification_categories qc ON q.category_id = qc.id WHERE q.id = ?");
$stmt->execute([$qualification_id]);
$qualification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$qualification) {
    redirect('manage_qualifications');
}

// Handle Assign/Remove Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_user_id'])) {
        $user_id_to_assign = (int) $_POST['assign_user_id'];
        $date_awarded = !empty($_POST['date_awarded']) ? $_POST['date_awarded'] : date('Y-m-d');

        $check_stmt = $pdo->prepare("SELECT id FROM user_qualifications WHERE user_id = ? AND qualification_id = ?");
        $check_stmt->execute([$user_id_to_assign, $qualification_id]);

        if ($check_stmt->rowCount() == 0) {
            $stmt_assign = $pdo->prepare("INSERT INTO user_qualifications (user_id, qualification_id, date_awarded, awarded_by) VALUES (?, ?, ?, ?)");
            $stmt_assign->execute([$user_id_to_assign, $qualification_id, $date_awarded, $_SESSION['user']['id']]);
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({icon: 'success', title: 'Awarded!', text: 'Qualification has been awarded.', timer: 1500, showConfirmButton: false})
                    .then(() => { window.location.href = 'manage_qualification_detail?id=" . $qualification_id . "'; });
                });
            </script>";
        } else {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({icon: 'error', title: 'Error', text: 'This member already has this qualification.'});
                });
            </script>";
        }
    } elseif (isset($_POST['remove_user_id'])) {
        $user_id_to_remove = (int) $_POST['remove_user_id'];
        $stmt_remove = $pdo->prepare("DELETE FROM user_qualifications WHERE user_id = ? AND qualification_id = ?");
        $stmt_remove->execute([$user_id_to_remove, $qualification_id]);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({icon: 'success', title: 'Revoked!', text: 'Qualification has been removed from this member.', timer: 1500, showConfirmButton: false})
                .then(() => { window.location.href = 'manage_qualification_detail?id=" . $qualification_id . "'; });
            });
        </script>";
    }
}

// Fetch Users with this qualification
$stmt_users = $pdo->prepare("
    SELECT u.id, u.personaname, u.avatar, u.rank, u.status, uq.date_awarded 
    FROM user_qualifications uq 
    INNER JOIN users u ON uq.user_id = u.id 
    WHERE uq.qualification_id = ? 
    ORDER BY uq.date_awarded DESC, u.personaname ASC
");
$stmt_users->execute([$qualification_id]);
$players = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Fetch Users NOT having this qualification for the assignment dropdown
$stmt_other_users = $pdo->prepare("
    SELECT id, personaname, `rank` 
    FROM users 
    WHERE id NOT IN (SELECT user_id FROM user_qualifications WHERE qualification_id = ?) 
    ORDER BY personaname ASC
");
$stmt_other_users->execute([$qualification_id]);
$other_users = $stmt_other_users->fetchAll(PDO::FETCH_ASSOC);

?>

<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <style>
        .admin-card {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .admin-card h3 {
            color: var(--accent-color);
            margin: 0 0 20px 0;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ccc;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        .qual-banner {
            display: flex;
            align-items: center;
            gap: 30px;
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.8), rgba(20, 20, 20, 0.9));
            padding: 30px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 30px;
        }

        .qual-banner img {
            max-width: 120px;
            max-height: 120px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5));
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }

        .player-card {
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .player-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .card-top {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 20px 20px 20px;
            background: linear-gradient(180deg, rgba(30, 30, 30, 1) 0%, rgba(20, 20, 20, 1) 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
        }

        .avatar-wrapper {
            position: relative;
            margin-bottom: 15px;
        }

        .player-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
            background: #222;
        }

        .status-indicator {
            position: absolute;
            top: 0;
            right: -20px;
            width: 10px;
            height: 10px;
            background-color: #4CAF50;
            border-radius: 50%;
            box-shadow: 0 0 8px #4CAF50;
        }

        .rank-badge {
            border: 1px solid var(--accent-color);
            color: var(--accent-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: rgba(197, 160, 89, 0.1);
        }

        .card-middle {
            padding: 20px;
            text-align: center;
            flex-grow: 1;
        }

        .player-name-large {
            margin: 0 0 5px 0;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .player-role {
            margin: 0;
            color: #888;
            font-size: 0.85rem;
        }

        .card-bottom {
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-text {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-text.status-active {
            color: #4CAF50;
        }

        .status-text.status-inactive {
            color: #f44336;
        }

        .status-text.status-retired {
            color: #FFC107;
        }

        .btn-dossier {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .btn-dossier:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
        }
    </style>

    <div class="header-actions">
        <a href="manage_qualifications" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Qualifications
        </a>
    </div>

    <!-- Qualification Info Banner -->
    <div class="qual-banner">
        <div
            style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; border: 1px dashed rgba(255,255,255,0.2);">
            <img src="/assets/images/qualifications/<?php echo htmlspecialchars($qualification['image'] ?: 'default_qualification.png'); ?>"
                onerror="this.onerror=null; this.src='/assets/images/logo.png';"
                alt="<?php echo htmlspecialchars($qualification['name']); ?>"
                style="max-width: 120px; max-height: 120px; object-fit: contain;">
        </div>
        <div>
            <div
                style="color: var(--accent-color); font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">
                <?php echo htmlspecialchars($qualification['category_name'] ?? 'Uncategorized'); ?>
            </div>
            <h1 style="margin: 0 0 10px 0; color: #fff; font-size: 2.2rem;">
                <?php echo htmlspecialchars($qualification['name']); ?>
            </h1>
            <p style="margin: 0; color: #aaa; max-width: 800px; line-height: 1.5;">
                <?php echo nl2br(htmlspecialchars($qualification['description'] ?: 'No description available.')); ?>
            </p>
        </div>
    </div>

    <!-- Players Section -->
    <div class="admin-card">
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 15px; flex-wrap: wrap; gap: 15px;">
            <h3 style="margin: 0; padding-bottom: 0; border-bottom: none;">Qualified Personnel <span
                    style="font-size: 0.8rem; opacity: 0.6; margin-left: 10px;">
                    <?php echo count($players); ?> Total
                </span></h3>

            <form method="POST" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="assign_user_id" required
                    style="padding: 10px 15px; border-radius: 8px; background: rgba(0,0,0,0.4); color: #fff; border: 1px solid rgba(255,255,255,0.1); min-width: 200px; font-family: var(--font-main);">
                    <option value="">-- Choose Personnel --</option>
                    <?php foreach ($other_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo htmlspecialchars($u['personaname'] . ' (' . ($u['rank'] ?? 'No Rank') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_awarded" value="<?php echo date('Y-m-d'); ?>"
                    style="padding: 10px 15px; border-radius: 8px; background: rgba(0,0,0,0.4); color: #fff; border: 1px solid rgba(255,255,255,0.1); font-family: var(--font-main);">
                <button type="submit" class="btn" style="padding: 10px 20px; border-radius: 8px;">
                    <i class="fas fa-graduation-cap"></i> Award Qualification
                </button>
            </form>
        </div>

        <?php if (empty($players)): ?>
            <div style="text-align: center; padding: 40px; color: #777;">
                <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No personnel have been awarded this qualification yet.</p>
            </div>
        <?php else: ?>
            <div class="players-grid">
                <?php foreach ($players as $member): ?>
                    <?php
                    $statusClass = 'status-inactive';
                    $statusText = strtoupper($member['status'] ?? 'UNKNOWN');
                    if (strtolower($member['status']) === 'active') {
                        $statusClass = 'status-active';
                    } elseif (strtolower($member['status']) === 'retired') {
                        $statusClass = 'status-retired';
                    }

                    $avatarUrl = '/assets/images/default_avatar.png';
                    if (!empty($member['avatar'])) {
                        if (strpos($member['avatar'], 'http') === 0) {
                            $avatarUrl = $member['avatar'];
                        } else {
                            $avatarUrl = '../' . ltrim($member['avatar'], '/.');
                        }
                    }
                    ?>
                    <div class="player-card">
                        <div class="card-top">
                            <form method="POST" style="position: absolute; top: 10px; right: 10px; z-index: 10;"
                                onsubmit="return confirm('Revoke this qualification from the member?');">
                                <input type="hidden" name="remove_user_id" value="<?php echo $member['id']; ?>">
                                <button type="submit"
                                    style="background: none; border: none; color: #555; cursor: pointer; padding: 5px; transition: color 0.2s;"
                                    onmouseover="this.style.color='#ff4444'" onmouseout="this.style.color='#555'"
                                    title="Revoke Qualification">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                            <div class="avatar-wrapper">
                                <img src="<?php echo htmlspecialchars($avatarUrl); ?>"
                                    onerror="this.src='/assets/images/default_avatar.png'" class="player-avatar-large"
                                    alt="Avatar">
                                <?php if (strtolower($member['status']) === 'active'): ?>
                                    <div class="status-indicator"></div>
                                <?php endif; ?>
                            </div>
                            <div class="rank-badge">
                                <?php echo htmlspecialchars($member['rank'] ?? 'Recruit'); ?>
                            </div>
                        </div>
                        <div class="card-middle">
                            <h4 class="player-name-large">
                                <?php echo htmlspecialchars($member['personaname']); ?>
                            </h4>
                            <p class="player-role">Awarded:
                                <?php echo date('M d, Y', strtotime($member['date_awarded'])); ?>
                            </p>
                        </div>
                        <div class="card-bottom">
                            <span class="status-text <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                            <button onclick="loadResumeData(<?php echo $member['id']; ?>)" class="btn-dossier"
                                style="cursor: pointer;">
                                <i class="fas fa-file-alt"></i> View Dossier
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/includes/resume_modal.php'; ?>
<?php include ROOT_PATH . '/includes/footer.php'; ?>