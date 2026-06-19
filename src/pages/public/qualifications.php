<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Qualifications');

if (!isLoggedIn()) {
    redirect('login');
}

// Fetch all categories
$stmt_cats = $pdo->query("SELECT * FROM qualification_categories ORDER BY order_index ASC, name ASC");
$categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

// Fetch all qualifications and group by category
$stmt_quals = $pdo->query("SELECT * FROM qualifications ORDER BY category_id ASC, order_index ASC, name ASC");
$quals_flat = $stmt_quals->fetchAll(PDO::FETCH_ASSOC);

$quals_by_category = [];
foreach ($quals_flat as $qual) {
    if (!isset($quals_by_category[$qual['category_id']])) {
        $quals_by_category[$qual['category_id']] = [];
    }
    $quals_by_category[$qual['category_id']][] = $qual;
}

if (isAdmin()) {
    include 'admin/includes/admin_header.php';
} else {
    include ROOT_PATH . '/includes/profile_header.php';
}
?>

<style>
    .quals-container {
        padding: 20px;
    }

    .quals-header {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .quals-header-icon {
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

    .quals-header-text h2 {
        margin: 0;
        font-size: 1.5rem;
        color: #fff;
    }

    .quals-header-text p {
        margin: 5px 0 0 0;
        color: #aaa;
        font-size: 0.9rem;
    }

    .quals-search-bar {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
    }

    .quals-search-bar input {
        background: transparent;
        border: none;
        color: #fff;
        width: 100%;
        font-size: 0.95rem;
        margin-left: 10px;
    }

    .quals-search-bar input:focus {
        outline: none;
    }

    .quals-search-bar i {
        color: #777;
    }

    .qual-category {
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

    .qual-row {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
    }

    .qual-row:last-child {
        border-bottom: none;
    }

    .qual-row:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .qual-image-col {
        width: 80px;
        display: flex;
        justify-content: center;
        margin-right: 20px;
    }

    .qual-image-col img {
        max-width: 100%;
        max-height: 50px;
        object-fit: contain;
    }

    .qual-info-col {
        flex: 1;
    }

    .qual-info-col h4 {
        margin: 0 0 5px 0;
        color: #fff;
        font-size: 1.05rem;
    }

    .qual-info-col p {
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
    <?php if (!isAdmin()): ?>
    <?php endif; ?>
    <div class="quals-container">
        <div class="quals-header">
            <div class="quals-header-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="quals-header-text">
                <h2>Qualifications</h2>
                <p>All Qualifications & Certifications</p>
            </div>
        </div>

        <div class="quals-search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="qualSearchInput" placeholder="Search by name..." onkeyup="filterQuals()">
        </div>

        <?php if (empty($categories)): ?>
            <div style="text-align: center; padding: 50px; color: #888;">
                <i class="fas fa-graduation-cap" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No qualifications configured yet.</p>
            </div>
        <?php else: ?>
            <div id="qualsListContainer">
                <?php foreach ($categories as $cat): ?>
                    <?php $cat_quals = isset($quals_by_category[$cat['id']]) ? $quals_by_category[$cat['id']] : []; ?>
                    <div class="qual-category">
                        <div class="category-header" onclick="toggleCategory(this)">
                            <h3>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="category-content">
                            <?php if (empty($cat_quals)): ?>
                                <div style="padding: 20px; color: #666; font-size: 0.9rem; font-style: italic;">
                                    No qualifications in this category.
                                </div>
                            <?php else: ?>
                                <?php foreach ($cat_quals as $qual): ?>
                                    <div class="qual-row">
                                        <div class="qual-image-col">
                                            <img src="assets/images/qualifications/<?php echo htmlspecialchars($qual['image'] ?: 'default_qualification.png'); ?>"
                                                onerror="this.onerror=null; this.src='assets/images/logo.png';"
                                                alt="<?php echo htmlspecialchars($qual['name']); ?>">
                                        </div>
                                        <div class="qual-info-col">
                                            <h4>
                                                <?php echo htmlspecialchars($qual['name']); ?>
                                            </h4>
                                            <?php if ($qual['description']): ?>
                                                <p>
                                                    <?php echo htmlspecialchars($qual['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div style="margin-left: 20px;">
                                            <a href="qualification_detail.php?id=<?php echo $qual['id']; ?>" class="btn-see-more">
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
    function filterQuals() {
        var input = document.getElementById('qualSearchInput');
        var filter = input.value.toUpperCase();
        var container = document.getElementById('qualsListContainer');
        var categories = container.getElementsByClassName('qual-category');
        for (var i = 0; i < categories.length; i++) {
            var category = categories[i];
            var rows = category.getElementsByClassName('qual-row');
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

<?php
if (isAdmin()) {
    include 'admin/includes/admin_footer.php';
} else {
    include ROOT_PATH . '/includes/profile_footer.php';
}
?>
