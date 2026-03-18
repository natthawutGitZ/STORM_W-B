<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

// Fetch public campaigns
try {
    $stmt = $pdo->query("SELECT * FROM campaigns ORDER BY created_at DESC");
    $campaigns = $stmt->fetchAll();
} catch (PDOException $e) {
    $campaigns = [];
}

include ROOT_PATH . '/includes/header.php';
?>

<style>
    .campaigns-hero {
        background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.9)), url('assets/images/background.jpg') center/cover;
        padding: 100px 20px 60px;
        text-align: center;
        border-bottom: 2px solid rgba(197, 160, 89, 0.3);
    }

    .campaigns-hero h1 {
        font-family: 'Teko', sans-serif;
        font-size: 4rem;
        color: #c5a059;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .campaigns-hero p {
        color: #aaa;
        font-size: 1.2rem;
        max-width: 600px;
        margin: 10px auto 0;
    }

    .campaigns-container {
        max-width: 1400px;
        margin: 60px auto;
        padding: 0 20px;
    }

    .public-campaign-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 30px;
    }

    .public-campaign-card {
        background: rgba(15, 15, 15, 0.8);
        border: 1px solid rgba(197, 160, 89, 0.15);
        border-radius: 4px;
        /* Military style sharper corners */
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        position: relative;
    }

    .public-campaign-card:hover {
        transform: translateY(-5px);
        border-color: rgba(197, 160, 89, 0.6);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .public-campaign-image {
        width: 100%;
        height: 220px;
        object-fit: cover;
        border-bottom: 2px solid #c5a059;
        transition: transform 0.5s ease;
    }

    .public-campaign-card:hover .public-campaign-image {
        transform: scale(1.05);
    }

    .img-wrapper {
        overflow: hidden;
        position: relative;
    }

    .status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 5px 12px;
        font-size: 0.8rem;
        font-family: 'Teko', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-radius: 3px;
        z-index: 2;
        background: rgba(0, 0, 0, 0.8);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
    }

    .status-badge.active {
        color: #eecfa1;
        border: 1px solid #c5a059;
    }

    .status-badge.completed {
        color: #aaa;
        border: 1px solid #666;
    }

    .public-campaign-content {
        padding: 25px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        position: relative;
        z-index: 2;
        background: rgba(15, 15, 15, 0.95);
    }

    .public-campaign-title {
        color: #fff;
        font-size: 1.8rem;
        margin: 0 0 15px 0;
        font-family: 'Teko', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: color 0.3s;
    }

    .public-campaign-card:hover .public-campaign-title {
        color: #c5a059;
    }

    .public-campaign-desc {
        color: #aaa;
        font-size: 0.95rem;
        line-height: 1.6;
        margin: 0 0 25px 0;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .view-btn {
        margin-top: auto;
        display: inline-block;
        padding: 10px 20px;
        border: 1px solid #c5a059;
        color: #c5a059;
        text-decoration: none;
        text-transform: uppercase;
        font-family: 'Teko', sans-serif;
        font-size: 1.1rem;
        letter-spacing: 1px;
        text-align: center;
        transition: all 0.3s;
        background: transparent;
    }

    .public-campaign-card:hover .view-btn {
        background: #c5a059;
        color: #000;
    }
</style>

<div class="campaigns-hero">
    <h1><i class="fas fa-globe-americas"></i> Operation Campaigns</h1>
    <p>Explore ongoing and past strategic operations of our unit.</p>
</div>

<div class="campaigns-container">
    <?php if (empty($campaigns)): ?>
        <div
            style="text-align: center; padding: 60px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px;">
            <i class="fas fa-satellite-dish" style="font-size: 4rem; color: #444; margin-bottom: 20px;"></i>
            <h2 style="color: #666; font-family: 'Teko'; font-size: 2rem; margin:0;">No Intel Available</h2>
            <p style="color: #555;">There are currently no operations or campaigns listed.</p>
        </div>
    <?php else: ?>
        <div class="public-campaign-grid">
            <?php foreach ($campaigns as $camp): ?>
                <a href="campaign_detail.php?id=<?php echo $camp['id']; ?>" class="public-campaign-card">
                    <div class="img-wrapper">
                        <span class="status-badge <?php echo strtolower($camp['status']); ?>">
                            <?php echo $camp['status'] === 'Active' ? '<i class="fas fa-circle" style="font-size:0.6rem; color:#4caf50;"></i> Active' : '<i class="fas fa-check"></i> Completed'; ?>
                        </span>
                        <img src="<?php echo !empty($camp['image_path']) ? htmlspecialchars($camp['image_path']) : 'assets/images/placeholder.jpg'; ?>"
                            class="public-campaign-image" alt="Campaign Cover">
                    </div>
                    <div class="public-campaign-content">
                        <h3 class="public-campaign-title">
                            <?php echo htmlspecialchars($camp['title']); ?>
                        </h3>
                        <p class="public-campaign-desc">
                            <?php echo nl2br(htmlspecialchars($camp['description'])); ?>
                        </p>
                        <span class="view-btn">View Intel Directory <i class="fas fa-arrow-right"
                                style="margin-left: 5px; font-size:0.8em;"></i></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
</body>

</html>
