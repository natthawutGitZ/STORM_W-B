<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Positions');

if (!isLoggedIn()) {
    redirect('login');
}

// Fetch all categories
$stmt_cats = $pdo->query("SELECT * FROM position_categories ORDER BY order_index ASC, name ASC");
$categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

// Fetch all positions and group by category
$stmt_pos = $pdo->query("SELECT * FROM positions ORDER BY category_id ASC, order_index ASC, name ASC");
$pos_flat = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);

$pos_by_category = [];
foreach ($pos_flat as $pos) {
    if (!isset($pos_by_category[$pos['category_id']])) {
        $pos_by_category[$pos['category_id']] = [];
    }
    $pos_by_category[$pos['category_id']][] = $pos;
}

if (isAdmin()) {
    include 'admin/includes/admin_header.php';
} else {
    include ROOT_PATH . '/includes/profile_header.php';
}
?>

<style>
    .positions-container {
        padding: 20px;
    }

    .positions-header {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .positions-header-icon {
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

    .positions-header-text h2 {
        margin: 0;
        font-size: 1.5rem;
        color: #fff;
    }

    .positions-header-text p {
        margin: 5px 0 0 0;
        color: #aaa;
        font-size: 0.9rem;
    }

    .positions-search-bar {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
    }

    .positions-search-bar input {
        background: transparent;
        border: none;
        color: #fff;
        width: 100%;
        font-size: 0.95rem;
        margin-left: 10px;
    }

    .positions-search-bar input:focus {
        outline: none;
    }

    .positions-search-bar i {
        color: #777;
    }

    .pos-category {
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

    .pos-row {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
    }

    .pos-row:last-child {
        border-bottom: none;
    }

    .pos-row:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .pos-info-col {
        flex: 1;
    }

    .pos-info-col h4 {
        margin: 0 0 5px 0;
        color: #fff;
        font-size: 1.05rem;
    }

    .pos-info-col p {
        margin: 0;
        color: #888;
        font-size: 0.85rem;
        line-height: 1.4;
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
    <div class="positions-container">
        <div class="positions-header">
            <div class="positions-header-icon">
                <i class="fas fa-user-tag"></i>
            </div>
            <div class="positions-header-text">
                <h2>Positions</h2>
                <p>All MOS Positions & Job Roles</p>
            </div>
        </div>

        <div class="positions-search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="posSearchInput" placeholder="Search by name..." onkeyup="filterPositions()">
        </div>

        <?php if (empty($categories)): ?>
            <div style="text-align: center; padding: 50px; color: #888;">
                <i class="fas fa-user-tag" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No positions configured yet.</p>
            </div>
        <?php else: ?>
            <div id="positionsListContainer">
                <?php foreach ($categories as $cat): ?>
                    <?php $cat_positions = isset($pos_by_category[$cat['id']]) ? $pos_by_category[$cat['id']] : []; ?>
                    <div class="pos-category">
                        <div class="category-header" onclick="toggleCategory(this)">
                            <h3>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="category-content">
                            <?php if (empty($cat_positions)): ?>
                                <div style="padding: 20px; color: #666; font-size: 0.9rem; font-style: italic;">
                                    No positions in this category.
                                </div>
                            <?php else: ?>
                                <?php foreach ($cat_positions as $pos): ?>
                                    <div class="pos-row">
                                        <div
                                            style="width: 30px; margin-right: 15px; color: #555; display: flex; justify-content: center;">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <div class="pos-info-col">
                                            <h4>
                                                <?php echo htmlspecialchars($pos['name']); ?>
                                            </h4>
                                            <?php if ($pos['description']): ?>
                                                <p>
                                                    <?php 
                                                    $desc_decoded = html_entity_decode($pos['description'], ENT_QUOTES);
                                                    echo htmlspecialchars(mb_substr($desc_decoded, 0, 100)) . (mb_strlen($desc_decoded) > 100 ? '...' : ''); 
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div style="margin-left: 20px;">
                                            <a href="position_detail.php?id=<?php echo $pos['id']; ?>" class="btn-see-more">
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
    function filterPositions() {
        var input = document.getElementById('posSearchInput');
        var filter = input.value.toUpperCase();
        var container = document.getElementById('positionsListContainer');
        var categories = container.getElementsByClassName('pos-category');
        for (var i = 0; i < categories.length; i++) {
            var category = categories[i];
            var rows = category.getElementsByClassName('pos-row');
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
