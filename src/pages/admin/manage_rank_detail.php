<?php
require_once ROOT_PATH . '/admin/includes/admin_header.php';

if (!isset($_GET['id'])) {
    redirect('manage_ranks');
}

$rank_id = (int) $_GET['id'];

// Fetch Rank Data
$stmt = $pdo->prepare("SELECT * FROM ranks WHERE id = ?");
$stmt->execute([$rank_id]);
$rank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rank) {
    redirect('manage_ranks');
}

// Handle Assign/Remove Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_user_id'])) {
        $user_id_to_assign = (int) $_POST['assign_user_id'];
        $stmt_assign = $pdo->prepare("UPDATE users SET `rank` = ? WHERE id = ?");
        $stmt_assign->execute([$rank['name'], $user_id_to_assign]);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({icon: 'success', title: 'Assigned!', text: 'Player has been assigned to this rank.', timer: 1500, showConfirmButton: false})
                .then(() => { window.location.href = 'manage_rank_detail?id=" . $rank_id . "'; });
            });
        </script>";
        exit;
    } elseif (isset($_POST['remove_user_id'])) {
        $user_id_to_remove = (int) $_POST['remove_user_id'];
        $stmt_remove = $pdo->prepare("UPDATE users SET `rank` = '' WHERE id = ?");
        $stmt_remove->execute([$user_id_to_remove]);
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({icon: 'success', title: 'Removed!', text: 'Player has been removed from this rank.', timer: 1500, showConfirmButton: false})
                .then(() => { window.location.href = 'manage_rank_detail?id=" . $rank_id . "'; });
            });
        </script>";
        exit;
    }
}

// Fetch Users with this rank
// Note: Assumes `users` table has a string `rank` column that matches `ranks.name` or `ranks.abbreviation`.
// We will look for an exact match of the rank's name.
$stmt_users = $pdo->prepare("SELECT id, personaname, avatar, status, `rank` FROM users WHERE `rank` = ? ORDER BY personaname ASC");
$stmt_users->execute([$rank['name']]);
$players = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Fetch Users NOT in this rank for the assignment dropdown
$stmt_other_users = $pdo->prepare("SELECT id, personaname FROM users WHERE `rank` != ? OR `rank` IS NULL ORDER BY personaname ASC");
$stmt_other_users->execute([$rank['name']]);
$other_users = $stmt_other_users->fetchAll(PDO::FETCH_ASSOC);

?>

<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <!-- Styles -->
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
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #aaa;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.4);
        }

        .rank-banner {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .rank-icon {
            width: 120px;
            height: 120px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5));
        }

        .rank-info h2 {
            margin: 0 0 5px 0;
            font-size: 2rem;
            color: #fff;
        }

        .rank-info p {
            margin: 0;
            color: #aaa;
            font-size: 1.1rem;
        }

        .rank-info .divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 15px 0;
            width: 100%;
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-dossier:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.4);
        }
    </style>

    <div class="header-actions">
        <a href="manage_ranks" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Ranks</a>
    </div>

    <!-- Rank Overview Banner -->
    <div class="admin-card">
        <h3>Rank Overview</h3>
        <div class="rank-banner">
            <img src="/assets/images/ranks/<?php echo htmlspecialchars($rank['image']); ?>"
                onerror="this.src='/assets/images/logo.png'" class="rank-icon" alt="Rank Icon">

            <div class="rank-info" style="flex: 1;">
                <h2>
                    <?php echo htmlspecialchars($rank['name']); ?>
                </h2>
                <p>"
                    <?php echo htmlspecialchars($rank['abbreviation']); ?>",
                    <?php echo htmlspecialchars($rank['category']); ?>
                </p>
                <div class="divider"></div>
                <p style="font-size: 0.95rem;">NATO Code:
                    <?php echo htmlspecialchars($rank['nato_code'] ?? 'None'); ?>
                    <span style="opacity: 0.3; font-size: 0.75rem; margin-left:10px;">(Index:
                        <?php echo $rank['order_index']; ?>)</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Players Section -->
    <div class="admin-card">
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 15px; flex-wrap: wrap; gap: 15px;">
            <h3 style="margin: 0; padding-bottom: 0; border-bottom: none;">Players <span
                    style="font-size: 0.8rem; opacity: 0.6; margin-left: 10px;">
                    <?php echo count($players); ?> Total
                </span></h3>

            <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                <select name="assign_user_id" required
                    style="padding: 10px 15px; border-radius: 8px; background: rgba(0,0,0,0.4); color: #fff; border: 1px solid rgba(255,255,255,0.1); min-width: 250px; font-family: var(--font-main);">
                    <option value="">-- Select Player to Assign --</option>
                    <?php foreach ($other_users as $ou): ?>
                        <option value="<?php echo $ou['id']; ?>"><?php echo htmlspecialchars($ou['personaname']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn" style="padding: 10px 20px; border-radius: 8px;">
                    <i class="fas fa-user-plus"></i> Assign
                </button>
            </form>
        </div>

        <?php if (empty($players)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No players currently hold this rank.</p>
            </div>
        <?php else: ?>
            <div class="players-grid">
                <?php foreach ($players as $player): ?>
                    <?php
                    // Determine status badge class
                    $statusClass = 'status-inactive';
                    $statusText = strtoupper($player['status'] ?? 'UNKNOWN');
                    if (strtolower($player['status']) === 'active') {
                        $statusClass = 'status-active';
                    } elseif (strtolower($player['status']) === 'retired') {
                        $statusClass = 'status-retired';
                    }
                    ?>
                    <div class="player-card">
                        <!-- Top section: Avatar and Rank Badge -->
                        <div class="card-top">
                            <!-- Remove button subtly in top right -->
                            <form method="POST" style="position: absolute; top: 10px; right: 10px; z-index: 10;"
                                onsubmit="return confirm('Are you sure you want to remove this player from the rank?');">
                                <input type="hidden" name="remove_user_id" value="<?php echo $player['id']; ?>">
                                <button type="submit"
                                    style="background: none; border: none; color: #555; cursor: pointer; padding: 5px; transition: color 0.2s;"
                                    onmouseover="this.style.color='#ff4444'" onmouseout="this.style.color='#555'"
                                    title="Remove from Rank">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>

                            <div class="avatar-wrapper">
                                <img src="<?php echo htmlspecialchars($player['avatar']); ?>"
                                    onerror="this.src='/assets/images/default_avatar.png'" class="player-avatar-large"
                                    alt="Avatar">
                                <?php if (strtolower($player['status']) === 'active'): ?>
                                    <div class="status-indicator"></div>
                                <?php endif; ?>
                            </div>
                            <!-- Rank Badge -->
                            <div class="rank-badge">
                                <?php echo htmlspecialchars($rank['name']); ?>
                            </div>
                        </div>

                        <!-- Middle section: Name and Role -->
                        <div class="card-middle">
                            <h4 class="player-name-large">
                                <?php echo htmlspecialchars($rank['abbreviation'] . '. ' . $player['personaname']); ?>
                            </h4>
                            <!-- Assuming 'role' or position isn't in DB yet, using placeholder or omitting. The screenshot says 'Detachment Commander'. We can hardcode or leave blank. Let's just put the category or leave it generic for now -->
                            <p class="player-role">Member</p>
                        </div>

                        <!-- Bottom section: Status and Button -->
                        <div class="card-bottom">
                            <span class="status-text <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                            <button onclick="loadResumeData(<?php echo $player['id']; ?>)" class="btn-dossier"
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