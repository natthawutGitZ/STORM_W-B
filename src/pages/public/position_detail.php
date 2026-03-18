<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';

if (!isLoggedIn()) {
    redirect('login');
}

if (!isset($_GET['id'])) {
    redirect('positions');
}
$position_id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT p.*, pc.name as category_name FROM positions p LEFT JOIN position_categories pc ON p.category_id = pc.id WHERE p.id = ?");
$stmt->execute([$position_id]);
$position = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$position) {
    redirect('positions');
}

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_positions (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, position_id INT NOT NULL,
        date_assigned DATE DEFAULT NULL, assigned_by INT DEFAULT NULL, notes TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id), KEY position_id (position_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
}

$stmt_users = $pdo->prepare("SELECT u.id, u.personaname, u.avatar, u.rank, u.status, up.date_assigned FROM user_positions up INNER JOIN users u ON up.user_id = u.id WHERE up.position_id = ? ORDER BY up.date_assigned DESC, u.personaname ASC");
$stmt_users->execute([$position_id]);
$players = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

trackPageView($pdo, 'Position: ' . $position['name']);

if (isAdmin()) {
    include 'admin/includes/admin_header.php';
} else {
    include ROOT_PATH . '/includes/profile_header.php';
}
?>

<div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <style>
        .detail-banner {
            display: flex;
            align-items: center;
            gap: 30px;
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.8), rgba(20, 20, 20, 0.9));
            padding: 30px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 30px;
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
            margin-bottom: 20px;
        }

        .btn-back:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .player-mini {
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
        }

        .player-mini:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .player-mini img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #fff;
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
            margin-bottom: 10px;
        }

        .player-mini h4 {
            color: #fff;
            margin: 0 0 4px 0;
            font-size: 0.95rem;
        }

        .player-mini p {
            color: #888;
            margin: 0;
            font-size: 0.8rem;
        }
    </style>

    <a href="positions" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Positions</a>

    <div class="detail-banner">
        <div>
            <div
                style="color: var(--accent-color); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">
                <?php echo htmlspecialchars($position['category_name'] ?? 'Uncategorized'); ?>
            </div>
            <h1 style="margin: 0 0 10px 0; color: #fff; font-size: 2rem;">
                <i class="fas fa-user-tag" style="color: var(--accent-color); margin-right: 10px;"></i>
                <?php echo htmlspecialchars($position['name']); ?>
            </h1>
            <p style="margin: 0; color: #aaa; max-width: 800px; line-height: 1.5;">
                <?php echo nl2br(htmlspecialchars(html_entity_decode($position['description'] ?: 'No description available.', ENT_QUOTES))); ?>
            </p>
        </div>
    </div>

    <div
        style="background: rgba(20, 20, 20, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 25px;">
        <h3
            style="color: var(--accent-color); margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 1.1rem;">
            <i class="fas fa-users"></i> Assigned Personnel <span
                style="opacity: 0.5; font-size: 0.85rem; margin-left: 8px;">
                <?php echo count($players); ?>
            </span>
        </h3>
        <?php if (empty($players)): ?>
            <div style="text-align: center; padding: 30px; color: #777;"><i class="fas fa-user-slash"
                    style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No personnel assigned to this position yet.</p>
            </div>
        <?php else: ?>
            <div class="players-grid">
                <?php foreach ($players as $m): ?>
                    <?php $av = !empty($m['avatar']) ? (strpos($m['avatar'], 'http') === 0 ? $m['avatar'] : '../' . ltrim($m['avatar'], '/.')) : 'assets/images/default_avatar.png'; ?>
                    <div class="player-mini">
                        <img src="<?php echo htmlspecialchars($av); ?>" onerror="this.src='assets/images/default_avatar.png'"
                            alt="Avatar">
                        <h4>
                            <?php echo htmlspecialchars($m['personaname']); ?>
                        </h4>
                        <p>
                            <?php echo htmlspecialchars($m['rank'] ?? 'Recruit'); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
