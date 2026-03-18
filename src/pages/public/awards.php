<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Awards');

if (!isLoggedIn()) {
    redirect('login');
}

// Fetch all categories
$stmt_cats = $pdo->query("SELECT * FROM award_categories ORDER BY order_index ASC, name ASC");
$categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

// Fetch all awards and group them by category
$stmt_awards = $pdo->query("SELECT * FROM awards ORDER BY category_id ASC, order_index ASC, name ASC");
$awards_flat = $stmt_awards->fetchAll(PDO::FETCH_ASSOC);

$awards_by_category = [];
foreach ($awards_flat as $award) {
    if (!isset($awards_by_category[$award['category_id']])) {
        $awards_by_category[$award['category_id']] = [];
    }
    $awards_by_category[$award['category_id']][] = $award;
}

if (isAdmin()) {
    include 'admin/includes/admin_header.php';
} else {
    include ROOT_PATH . '/includes/profile_header.php';
}
?>

<style>
    .awards-container {
        padding: 20px;
    }

    .awards-header {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .awards-header-icon {
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

    .awards-header-text h2 {
        margin: 0;
        font-size: 1.5rem;
        color: #fff;
    }

    .awards-header-text p {
        margin: 5px 0 0 0;
        color: #aaa;
        font-size: 0.9rem;
    }

    /* Search Bar */
    .awards-search-bar {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
    }

    .awards-search-bar input {
        background: transparent;
        border: none;
        color: #fff;
        width: 100%;
        font-size: 0.95rem;
        margin-left: 10px;
    }

    .awards-search-bar input:focus {
        outline: none;
    }

    .awards-search-bar i {
        color: #777;
    }

    /* Category Blocks */
    .award-category {
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

    /* Individual Award Row */
    .award-row {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
    }

    .award-row:last-child {
        border-bottom: none;
    }

    .award-row:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .award-image-col {
        width: 80px;
        display: flex;
        justify-content: center;
        margin-right: 20px;
    }

    .award-image-col img {
        max-width: 100%;
        max-height: 40px;
        object-fit: contain;
    }

    .award-info-col {
        flex: 1;
    }

    .award-info-col h4 {
        margin: 0 0 5px 0;
        color: #fff;
        font-size: 1.05rem;
    }

    .award-info-col p {
        margin: 0;
        color: #888;
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .award-action-col {
        margin-left: 20px;
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
    <div class="awards-container">

        <div class="awards-header">
            <div class="awards-header-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="awards-header-text">
                <h2>Awards</h2>
                <p>All Awards & Categories relating to this Community</p>
            </div>
        </div>

        <div class="awards-search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="awardSearchInput" placeholder="Search by name..." onkeyup="filterAwards()">
        </div>

        <?php if (empty($categories)): ?>
            <div style="text-align: center; padding: 50px; color: #888;">
                <i class="fas fa-medal" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No awards configured yet.</p>
            </div>
        <?php else: ?>
            <div id="awardsListContainer">
                <?php foreach ($categories as $cat): ?>
                    <?php
                    $cat_awards = isset($awards_by_category[$cat['id']]) ? $awards_by_category[$cat['id']] : [];
                    ?>
                    <div class="award-category">
                        <div class="category-header" onclick="toggleCategory(this)">
                            <h3>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="category-content">
                            <?php if (empty($cat_awards)): ?>
                                <div style="padding: 20px; color: #666; font-size: 0.9rem; font-style: italic;">
                                    No awards in this category.
                                </div>
                            <?php else: ?>
                                <?php foreach ($cat_awards as $award): ?>
                                    <div class="award-row">
                                        <div class="award-image-col">
                                            <img src="assets/images/awards/<?php echo htmlspecialchars($award['image'] ?: 'default_award.png'); ?>"
                                                onerror="this.onerror=null; this.src='assets/images/logo.png';"
                                                alt="<?php echo htmlspecialchars($award['name']); ?>">
                                        </div>
                                        <div class="award-info-col">
                                            <h4>
                                                <?php echo htmlspecialchars($award['name']); ?>
                                            </h4>
                                            <?php if ($award['description']): ?>
                                                <p>
                                                    <?php echo htmlspecialchars($award['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="award-action-col">
                                            <a href="award_detail.php?id=<?php echo $award['id']; ?>" class="btn-see-more">
                                                <i class="fas fa-search"></i> See More
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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

    function filterAwards() {
        var input, filter, container, categories, i, j;
        input = document.getElementById('awardSearchInput');
        filter = input.value.toUpperCase();
        container = document.getElementById('awardsListContainer');
        categories = container.getElementsByClassName('award-category');

        for (i = 0; i < categories.length; i++) {
            var category = categories[i];
            var header = category.getElementsByClassName('category-header')[0];
            var content = category.getElementsByClassName('category-content')[0];
            var awardRows = content.getElementsByClassName('award-row');

            var hasVisibleAward = false;

            if (awardRows.length === 0) {
                // Keep categories with no awards visible only if search is empty
                category.style.display = filter === "" ? "" : "none";
                continue;
            }

            for (j = 0; j < awardRows.length; j++) {
                var row = awardRows[j];
                var nameCol = row.getElementsByClassName('award-info-col')[0];
                var nameText = nameCol.getElementsByTagName("h4")[0].textContent || nameCol.getElementsByTagName("h4")[0].innerText;

                if (nameText.toUpperCase().indexOf(filter) > -1) {
                    row.style.display = "";
                    hasVisibleAward = true;
                } else {
                    row.style.display = "none";
                }
            }

            if (hasVisibleAward) {
                category.style.display = "";
                // Optionally auto-expand if search is active
                if (filter !== "") {
                    header.classList.remove('collapsed');
                    content.classList.remove('collapsed');
                }
            } else {
                category.style.display = "none";
            }
        }
    }
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
