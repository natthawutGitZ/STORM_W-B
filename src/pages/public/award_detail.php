<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';

if (!isLoggedIn()) {
    redirect('login');
}

$award_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$award_id) {
    redirect('awards');
}

// Fetch Award details
$stmt = $pdo->prepare("
    SELECT a.*, ac.name as category_name 
    FROM awards a 
    LEFT JOIN award_categories ac ON a.category_id = ac.id 
    WHERE a.id = ?
");
$stmt->execute([$award_id]);
$award = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$award) {
    redirect('awards');
}

trackPageView($pdo, 'Award Detail - ' . $award['name']);

// Fetch Users with this award
$stmt_users = $pdo->prepare("
    SELECT u.id, u.personaname, u.avatar, u.rank, u.status, ua.date_awarded 
    FROM user_awards ua 
    INNER JOIN users u ON ua.user_id = u.id 
    WHERE ua.award_id = ? 
    ORDER BY ua.date_awarded DESC, u.personaname ASC
");
$stmt_users->execute([$award_id]);
$players = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

if (isAdmin()) {
    include 'admin/includes/admin_header.php';
} else {
    include ROOT_PATH . '/includes/profile_header.php';
}
?>

<style>
    .award-detail-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .award-header-section {
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 30px;
        display: flex;
        align-items: flex-start;
        gap: 30px;
    }

    .award-image-large {
        width: 150px;
        height: 150px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 8px;
        border: 1px dashed rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 15px;
        flex-shrink: 0;
    }

    .award-image-large img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .award-info {
        flex: 1;
    }

    .award-info h1 {
        margin: 0 0 10px 0;
        font-size: 2rem;
        color: #fff;
    }

    .award-info .category-badge {
        display: inline-block;
        background: rgba(197, 160, 89, 0.15);
        color: var(--accent-color);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 15px;
        border: 1px solid rgba(197, 160, 89, 0.3);
    }

    .award-info p {
        color: #ddd;
        font-size: 1rem;
        line-height: 1.6;
    }

    .section-title {
        color: var(--accent-color);
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0 0 20px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Roster Grid (reused styles from profile.php) */
    .roster-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .roster-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, 0.05);
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, background 0.2s;
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .roster-card:hover {
        transform: translateY(-3px);
        background: rgba(255, 255, 255, 0.06);
    }

    .card-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 15px;
        position: relative;
    }

    .status-indicator {
        position: absolute;
        top: 0;
        right: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #f44336;
    }

    .status-indicator.active {
        background: #4caf50;
        box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
    }

    .avatar-wrapper {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 2px solid var(--accent-color);
        padding: 2px;
        background: rgba(0, 0, 0, 0.5);
        margin-bottom: 10px;
    }

    .avatar-wrapper img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .operator-name {
        margin: 0 0 5px 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #fff;
        text-align: center;
    }

    .rank-badge {
        font-size: 0.8rem;
        color: var(--accent-color);
        background: rgba(197, 160, 89, 0.1);
        padding: 3px 10px;
        border-radius: 12px;
        border: 1px solid rgba(197, 160, 89, 0.2);
        text-align: center;
    }

    .date-awarded {
        text-align: center;
        font-size: 0.8rem;
        color: #888;
        margin-top: 10px;
        border-top: 1px dashed rgba(255, 255, 255, 0.1);
        padding-top: 10px;
    }
</style>

<div class="dashboard-container <?php echo isAdmin() ? 'admin-view' : 'member-view'; ?>">
    <div class="award-detail-container">

        <div style="margin-bottom: 20px;">
            <a href="awards.php" class="btn-see-more" style="background: transparent; border: none; padding: 0;">
                <i class="fas fa-arrow-left"></i> Back to Awards
            </a>
        </div>

        <div class="award-header-section">
            <div class="award-image-large">
                <img src="assets/images/awards/<?php echo htmlspecialchars($award['image'] ?: 'default_award.png'); ?>"
                    onerror="this.onerror=null; this.src='assets/images/logo.png';"
                    alt="<?php echo htmlspecialchars($award['name']); ?>">
            </div>
            <div class="award-info">
                <h1>
                    <?php echo htmlspecialchars($award['name']); ?>
                </h1>
                <span class="category-badge">
                    <?php echo htmlspecialchars($award['category_name']); ?>
                </span>
                <p>
                    <?php echo nl2br(htmlspecialchars($award['description'] ?: 'No description available for this award.')); ?>
                </p>
            </div>
        </div>

        <h3 class="section-title">
            <span><i class="fas fa-users" style="margin-right: 10px;"></i>Recipients</span>
            <span
                style="background: rgba(255,255,255,0.08); padding: 5px 14px; border-radius: 20px; font-size: 0.85rem; color: rgba(255,255,255,0.7); font-weight: 500;">
                <?php echo count($players); ?> Personnel
            </span>
        </h3>

        <?php if (empty($players)): ?>
            <div
                style="text-align: center; padding: 50px; background: rgba(0,0,0,0.2); border-radius: 8px; border: 1px dashed rgba(255,255,255,0.1);">
                <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 15px; color: #555;"></i>
                <p style="color: #aaa;">No personnel have been awarded this decoration yet.</p>
            </div>
        <?php else: ?>
            <div class="roster-grid">
                <?php foreach ($players as $member): ?>
                    <div class="roster-card">
                        <div class="card-header">
                            <div class="status-indicator <?php echo strtolower($member['status']); ?>"
                                title="<?php echo htmlspecialchars($member['status']); ?>"></div>
                            <div class="avatar-wrapper">
                                <img src="<?php echo get_avatar($member['avatar']); ?>" alt="Avatar">
                            </div>
                            <div class="rank-badge">
                                <?php echo htmlspecialchars($member['rank'] ?? 'Recruit'); ?>
                            </div>
                        </div>
                        <h4 class="operator-name">
                            <?php echo htmlspecialchars($member['personaname']); ?>
                        </h4>
                        <?php if ($member['date_awarded']): ?>
                            <div class="date-awarded">
                                <i class="far fa-calendar-check" style="margin-right: 5px;"></i>
                                Awarded:
                                <?php echo date('M j, Y', strtotime($member['date_awarded'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
