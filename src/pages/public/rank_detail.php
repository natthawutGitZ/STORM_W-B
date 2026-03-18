<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';

if (!isLoggedIn()) {
    redirect('login');
}

if (!isset($_GET['id'])) {
    redirect('ranks');
}
$rank_id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM ranks WHERE id = ?");
$stmt->execute([$rank_id]);
$rank = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rank) {
    redirect('ranks');
}

// Fetch users with this rank (by matching rank name/abbreviation)
$stmt_users = $pdo->prepare("SELECT id, personaname, avatar, `rank`, status FROM users WHERE `rank` = ? OR `rank` = ? ORDER BY personaname ASC");
$stmt_users->execute([$rank['abbreviation'], $rank['name']]);
$players = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

trackPageView($pdo, 'Rank: ' . $rank['name']);

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

        .detail-banner img {
            max-width: 120px;
            max-height: 120px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5));
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

        .rank-meta-badges {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .meta-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .meta-badge.nato {
            background: rgba(100, 149, 237, 0.1);
            color: #6495ed;
            border: 1px solid rgba(100, 149, 237, 0.3);
        }

        .meta-badge.category {
            background: rgba(197, 160, 89, 0.1);
            color: var(--accent-color);
            border: 1px solid rgba(197, 160, 89, 0.3);
        }

        .meta-badge.abbr {
            background: rgba(255, 255, 255, 0.05);
            color: #aaa;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>

    <a href="ranks" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Ranks</a>

    <div class="detail-banner">
        <?php if (!empty($rank['image'])): ?>
            <div
                style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; border: 1px dashed rgba(255,255,255,0.2);">
                <img src="assets/images/ranks/<?php echo htmlspecialchars($rank['image']); ?>"
                    onerror="this.onerror=null; this.parentElement.style.display='none';"
                    alt="<?php echo htmlspecialchars($rank['name']); ?>">
            </div>
        <?php endif; ?>
        <div>
            <div
                style="color: var(--accent-color); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">
                <?php echo htmlspecialchars($rank['category']); ?>
            </div>
            <h1 style="margin: 0 0 5px 0; color: #fff; font-size: 2rem;">
                <?php echo htmlspecialchars($rank['name']); ?>
            </h1>
            <div class="rank-meta-badges">
                <?php if (!empty($rank['abbreviation'])): ?>
                    <span class="meta-badge abbr">
                        <?php echo htmlspecialchars($rank['abbreviation']); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($rank['nato_code'])): ?>
                    <span class="meta-badge nato">NATO
                        <?php echo htmlspecialchars($rank['nato_code']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div
        style="background: rgba(20, 20, 20, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 25px;">
        <h3
            style="color: var(--accent-color); margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 1.1rem;">
            <i class="fas fa-users"></i> Personnel with this Rank <span
                style="opacity: 0.5; font-size: 0.85rem; margin-left: 8px;">
                <?php echo count($players); ?>
            </span>
        </h3>
        <?php if (empty($players)): ?>
            <div style="text-align: center; padding: 30px; color: #777;">
                <i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No personnel currently hold this rank.</p>
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
                        <p
                            style="color: <?php echo strtolower($m['status']) === 'active' ? '#4CAF50' : '#f44336'; ?>; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">
                            <?php echo htmlspecialchars($m['status'] ?? 'Unknown'); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
