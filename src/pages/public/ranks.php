<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Ranks');

if (!isLoggedIn()) {
    redirect('login');
}

// Create rank_categories if needed
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rank_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        order_index INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (PDOException $e) {
}

// Fetch rank categories
$rankCategories = $pdo->query("SELECT * FROM rank_categories ORDER BY order_index ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all ranks ordered by category then rank order
$ranksRaw = $pdo->query("SELECT r.* FROM ranks r
    LEFT JOIN rank_categories rc ON r.category = rc.name
    ORDER BY COALESCE(rc.order_index, 999) ASC, r.order_index ASC")->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$categories = [];
foreach ($ranksRaw as $rank) {
    $categories[$rank['category']][] = $rank;
}

if (isAdmin()) {
    include 'admin/includes/admin_header.php';
} else {
    include ROOT_PATH . '/includes/profile_header.php';
}
?>

<style>
    .ranks-container {
        padding: 20px;
    }

    .ranks-header {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .ranks-header-icon {
        background: rgba(197, 160, 89, 0.15);
        color: var(--accent-color);
        width: 50px;
        height: 50px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 15px;
        border: 1px solid rgba(197, 160, 89, 0.3);
    }

    .ranks-header-text h2 {
        margin: 0;
        font-size: 1.5rem;
        color: #fff;
    }

    .ranks-header-text p {
        margin: 5px 0 0 0;
        color: #aaa;
        font-size: 0.9rem;
    }

    .ranks-search-bar {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
    }

    .ranks-search-bar input {
        background: transparent;
        border: none;
        color: #fff;
        width: 100%;
        font-size: 0.95rem;
        margin-left: 10px;
    }

    .ranks-search-bar input:focus {
        outline: none;
    }

    .ranks-search-bar i {
        color: #777;
    }

    .rank-category {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .category-header {
        padding: 15px 20px;
        background: rgba(0, 0, 0, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .category-header h3 {
        margin: 0;
        font-size: 1.1rem;
        color: #ddd;
        font-weight: 500;
    }

    .category-header i {
        color: #777;
        transition: transform 0.3s ease;
    }

    .category-header.collapsed i {
        transform: rotate(-90deg);
    }

    .category-content {
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
    }

    .category-content.collapsed {
        display: none;
    }

    .rank-row {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
    }

    .rank-row:last-child {
        border-bottom: none;
    }

    .rank-row:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .rank-image-col {
        width: 60px;
        display: flex;
        justify-content: center;
        margin-right: 20px;
    }

    .rank-image-col img {
        max-width: 100%;
        max-height: 50px;
        object-fit: contain;
    }

    .rank-info-col {
        flex: 1;
    }

    .rank-info-col h4 {
        margin: 0 0 3px 0;
        color: #fff;
        font-size: 1.05rem;
    }

    .rank-info-col p {
        margin: 0;
        color: #888;
        font-size: 0.85rem;
    }

    .rank-meta {
        display: flex;
        gap: 15px;
        margin-left: 20px;
        align-items: center;
    }

    .rank-badge {
        background: rgba(197, 160, 89, 0.1);
        color: var(--accent-color);
        border: 1px solid rgba(197, 160, 89, 0.3);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    .nato-badge {
        background: rgba(100, 149, 237, 0.1);
        color: #6495ed;
        border: 1px solid rgba(100, 149, 237, 0.3);
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .btn-see-more {
        background: rgba(255, 255, 255, 0.1);
        color: #ddd;
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 6px 15px;
        border-radius: 4px;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-see-more:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
    }
</style>

<div class="dashboard-container <?php echo isAdmin() ? 'admin-view' : 'member-view'; ?>">
    <?php if (!isAdmin())
        include ROOT_PATH . '/includes/user_tabs.php'; ?>
    <div class="ranks-container">
        <div class="ranks-header">
            <div class="ranks-header-icon">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="ranks-header-text">
                <h2>Ranks</h2>
                <p>All Ranks & Grade Structure</p>
            </div>
        </div>

        <div class="ranks-search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="rankSearchInput" placeholder="Search by name..." onkeyup="filterRanks()">
        </div>

        <?php if (empty($categories)): ?>
            <div style="text-align: center; padding: 50px; color: #888;">
                <i class="fas fa-layer-group" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No ranks configured yet.</p>
            </div>
        <?php else: ?>
            <div id="ranksListContainer">
                <?php foreach ($categories as $catName => $catRanks): ?>
                    <div class="rank-category">
                        <div class="category-header" onclick="toggleCategory(this)">
                            <h3>
                                <?php echo htmlspecialchars($catName); ?>
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="category-content">
                            <?php foreach ($catRanks as $rank): ?>
                                <div class="rank-row">
                                    <div class="rank-image-col">
                                        <?php
                                        $imgFile = !empty($rank['image']) ? $rank['image'] : 'default_rank.png';
                                        $imgSrc = 'assets/images/ranks/' . $imgFile;
                                        ?>
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                            onerror="this.onerror=null; this.src='assets/images/logo.png';"
                                            alt="<?php echo htmlspecialchars($rank['name']); ?>">
                                    </div>
                                    <div class="rank-info-col">
                                        <h4><?php echo htmlspecialchars($rank['name']); ?></h4>
                                        <div style="color: #bbb; font-size: 0.85rem; margin-top: 3px; font-weight: bold;">
                                            <?php echo htmlspecialchars($rank['nato_code'] ?: ''); ?>
                                        </div>
                                        <div style="color: #888; font-size: 0.8rem; letter-spacing: 0.5px;">
                                            <?php echo htmlspecialchars($rank['abbreviation']); ?>
                                        </div>
                                    </div>
                                    <div class="rank-meta">
                                        <a href="rank_detail.php?id=<?php echo $rank['id']; ?>" class="btn-see-more">
                                            <i class="fas fa-search"></i> See More
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleCategory(element) {
        element.classList.toggle('collapsed');
        var content = element.nextElementSibling;
        content.classList.toggle('collapsed');
    }
    function filterRanks() {
        var input = document.getElementById('rankSearchInput');
        var filter = input.value.toUpperCase();
        var container = document.getElementById('ranksListContainer');
        var categories = container.getElementsByClassName('rank-category');
        for (var i = 0; i < categories.length; i++) {
            var category = categories[i];
            var rows = category.getElementsByClassName('rank-row');
            var hasVisible = false;
            for (var j = 0; j < rows.length; j++) {
                var name = rows[j].getElementsByTagName('h4')[0].textContent;
                if (name.toUpperCase().indexOf(filter) > -1) {
                    rows[j].style.display = '';
                    hasVisible = true;
                } else {
                    rows[j].style.display = 'none';
                }
            }
            category.style.display = hasVisible || filter === '' ? '' : 'none';
        }
    }
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
