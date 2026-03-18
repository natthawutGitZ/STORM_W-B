<?php
$ajax_mode = isset($_GET['ajax']) && $_GET['ajax'] == '1';
if ($ajax_mode) {
    require_once ROOT_PATH . '/includes/db.php';
    require_once ROOT_PATH . '/includes/functions.php';
    if (!isAdmin()) {
        http_response_code(403);
        exit;
    }
} else {
    require_once ROOT_PATH . '/admin/includes/admin_header.php';
}

// Auto-create ranks table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ranks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        abbreviation VARCHAR(50) NOT NULL,
        category VARCHAR(100) NOT NULL,
        image VARCHAR(255) NOT NULL DEFAULT '',
        order_index INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Create rank_categories table for ordering
    $pdo->exec("CREATE TABLE IF NOT EXISTS rank_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        order_index INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Auto-seed rank_categories from existing ranks
    $existingCats = $pdo->query("SELECT DISTINCT category FROM ranks WHERE category != '' ORDER BY CASE category
        WHEN 'Army Officer' THEN 1
        WHEN 'Warrant Officer' THEN 2
        WHEN 'Army Enlisted' THEN 3
        ELSE 4
    END ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($existingCats as $idx => $catName) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO rank_categories (name, order_index) VALUES (?, ?)");
        $stmt->execute([$catName, $idx + 1]);
    }
} catch (PDOException $e) {
    // Suppress error or log it
}

// Handle Form Submissions (Create/Update/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $name = $_POST['rank_name'] ?? '';
            $abbreviation = $_POST['abbreviation'] ?? '';
            $category = $_POST['category'] ?? '';
            $order_index = $_POST['order_index'] ?? 0;
            $nato_code = $_POST['nato_code'] ?? '';

            if ($name && $category) {
                // 1. Insert Rank
                $stmt = $pdo->prepare("INSERT INTO ranks (name, abbreviation, category, order_index, nato_code, image) VALUES (?, ?, ?, ?, ?, '')");
                $stmt->execute([$name, $abbreviation, $category, (int) $order_index, $nato_code]);
                $rank_id = $pdo->lastInsertId();

                // 2. Handle Image Upload
                if (isset($_FILES['rank_image']) && $_FILES['rank_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_PATH . '/assets/images/ranks/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileInfo = pathinfo($_FILES['rank_image']['name']);
                    $ext = strtolower($fileInfo['extension']);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

                    if (in_array($ext, $allowed)) {
                        $newFileName = 'rank_' . $rank_id . '_' . time() . '.' . $ext;
                        $destPath = $uploadDir . $newFileName;

                        // We do a simple move_uploaded_file instead of processUnitImage as ranks might use png/svg directly transparently
                        if (move_uploaded_file($_FILES['rank_image']['tmp_name'], $destPath)) {
                            // Update DB with new path
                            $stmt = $pdo->prepare("UPDATE ranks SET image = ? WHERE id = ?");
                            $stmt->execute([$newFileName, $rank_id]);
                        }
                    }
                }

                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Created!',
                        text: 'New rank created successfully',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        iconColor: '#2ecc71',
                        willClose: () => {
                            const popup = Swal.getPopup();
                            popup.style.animation = 'glassFadeOut 0.5s forwards';
                        }
                    }).then(() => {
                        window.location.href = 'manage_ranks';
                    });
                </script>";
            }
        } elseif ($action === 'update') {
            $rank_id = $_POST['rank_id'] ?? null;
            $name = $_POST['rank_name'] ?? '';
            $abbreviation = $_POST['abbreviation'] ?? '';
            $category = $_POST['category'] ?? '';
            $order_index = $_POST['order_index'] ?? 0;
            $nato_code = $_POST['nato_code'] ?? '';

            if ($rank_id && $name && $category) {
                $stmt = $pdo->prepare("UPDATE ranks SET name = ?, abbreviation = ?, category = ?, order_index = ?, nato_code = ? WHERE id = ?");
                $stmt->execute([$name, $abbreviation, $category, (int) $order_index, $nato_code, $rank_id]);

                // Handle Image Upload
                if (isset($_FILES['rank_image']) && $_FILES['rank_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_PATH . '/assets/images/ranks/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileInfo = pathinfo($_FILES['rank_image']['name']);
                    $ext = strtolower($fileInfo['extension']);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

                    if (in_array($ext, $allowed)) {
                        $newFileName = 'rank_' . $rank_id . '_' . time() . '.' . $ext;
                        $destPath = $uploadDir . $newFileName;

                        if (move_uploaded_file($_FILES['rank_image']['tmp_name'], $destPath)) {
                            $stmt = $pdo->prepare("UPDATE ranks SET image = ? WHERE id = ?");
                            $stmt->execute([$newFileName, $rank_id]);
                        }
                    }
                }
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'Rank updated successfully',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        willClose: () => {
                            const popup = Swal.getPopup();
                            popup.style.animation = 'glassFadeOut 0.5s forwards';
                        }
                    }).then(() => {
                        window.location.href = 'manage_ranks';
                    });
                </script>";
            }
        } elseif ($action === 'delete') {
            $rank_id = $_POST['rank_id'] ?? null;
            if ($rank_id) {
                $stmt = $pdo->prepare("SELECT image FROM ranks WHERE id = ?");
                $stmt->execute([$rank_id]);
                $rank = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($rank && $rank['image']) {
                    $imagePath = ROOT_PATH . '/assets/images/ranks/' . $rank['image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM ranks WHERE id = ?");
                $stmt->execute([$rank_id]);

                echo "<script>
                    Swal.fire({
                        iconHtml: '<i class=\"fas fa-trash-alt trash-bounce\" style=\"font-size: 4rem; color: #ff6b6b;\"></i>',
                        customClass: { icon: 'no-border' },
                        title: 'Deleted!',
                        text: 'Rank deleted successfully',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        willClose: () => {
                            const popup = Swal.getPopup();
                            popup.style.animation = 'glassFadeOut 0.5s forwards';
                        }
                    }).then(() => {
                        window.location.href = 'manage_ranks';
                    });
                </script>";
            }
        }
    } catch (PDOException $e) {
        echo "<script>Swal.fire('Error', 'Database error: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch rank categories
$rankCategories = $pdo->query("SELECT * FROM rank_categories ORDER BY order_index ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$rankCategoriesById = [];
foreach ($rankCategories as $rc) {
    $rankCategoriesById[$rc['name']] = $rc;
}

// Fetch Ranks ordered by category order then rank order
$ranksRaw = $pdo->query("SELECT r.* FROM ranks r
    LEFT JOIN rank_categories rc ON r.category = rc.name
    ORDER BY COALESCE(rc.order_index, 999) ASC, r.order_index ASC")->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$categories = [];
foreach ($ranksRaw as $rank) {
    $categories[$rank['category']][] = $rank;
}
?>

<?php if (!$ajax_mode) {
    include ROOT_PATH . '/admin/includes/member_tabs.php';
} ?>

<div class="dashboard-container" style="padding-top: 0; padding-bottom: 20px;">
    <!-- Styles (Inheriting concepts from units.php) -->
    <style>
        .admin-card {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 25px;
            height: 100%;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            color: #aaa;
            font-size: 0.85rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            border-radius: 6px;
            transition: all 0.3s;
            font-family: var(--font-main);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.1);
            background: rgba(0, 0, 0, 0.5);
        }

        .category-header {
            background: rgba(25, 25, 25, 0.8);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-left: 3px solid var(--accent-color);
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 600;
        }

        .rank-list-item {
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            transition: all 0.2s;
            position: relative;
        }

        .drag-handle {
            cursor: grab;
            color: #666;
            padding: 10px;
            margin-right: 15px;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .drag-handle:hover {
            color: var(--accent-color);
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .sortable-ghost {
            opacity: 0.4;
            background: rgba(197, 160, 89, 0.1);
        }

        .rank-list-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .rank-list-item:last-child {
            border-bottom: none;
        }

        .rank-image {
            width: 40px;
            height: 40px;
            object-fit: contain;
            margin-right: 20px;
            filter: drop-shadow(0px 2px 4px rgba(0, 0, 0, 0.5));
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: color 0.2s;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-see-more {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-see-more:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.02);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-content {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            transform: translateY(20px);
            transition: transform 0.3s;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
    </style>

    <div style="margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 style="color: var(--accent-color); margin: 0 0 10px 0; font-size: 1.8rem; letter-spacing: 1px;">
                <i class="fas fa-layer-group"></i> Ranks Overview
            </h2>
            <p style="color: #aaa; margin: 0; font-size: 0.95rem;">Manage unit ranks and see their roster.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="openAddModal()" class="btn" style="padding: 10px 20px; border-radius: 8px;">
                <i class="fas fa-plus"></i> Add New Rank
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div
        style="background: rgba(20,20,20,0.6); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.05);">
        <i class="fas fa-search" style="color: #888; margin-right: 15px;"></i>
        <input type="text" id="adminRankSearch" placeholder="Search by name..."
            style="background: transparent; border: none; color: #fff; width: 100%; font-size: 1rem; outline: none;">
    </div>

    <script>
        document.getElementById('adminRankSearch').addEventListener('keyup', function () {
            let filter = this.value.toLowerCase();
            let categories = document.querySelectorAll('.admin-card');

            categories.forEach(function (category) {
                let items = category.querySelectorAll('.rank-list-item');
                let hasVisibleItems = false;

                items.forEach(function (item) {
                    let text = item.textContent.toLowerCase();
                    if (text.indexOf(filter) > -1) {
                        item.style.display = '';
                        hasVisibleItems = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Hide category if no items match
                if (hasVisibleItems || filter === '') {
                    category.style.display = '';
                } else {
                    category.style.display = 'none';
                }
            });
        });
    </script>

    <div id="categoriesSortable" style="display: grid; gap: 20px;">
        <?php if (empty($categories)): ?>
            <div class="admin-card" style="text-align: center; padding: 50px;">
                <i class="fas fa-layer-group" style="font-size: 3rem; color: #444; margin-bottom: 15px;"></i>
                <p style="color: #666; font-size: 1.1rem; margin: 0;">No ranks found. Click "Add New Rank" to get started.
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $catName => $catRanks): ?>
                <?php $catData = $rankCategoriesById[$catName] ?? null; ?>
                <div class="admin-card" style="padding: 0; overflow: hidden;"
                    data-category-id="<?php echo $catData ? $catData['id'] : 0; ?>">
                    <div class="category-header" onclick="toggleCategory(this)"
                        style="margin: 0; border-radius: 0; border: none; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer;">
                        <span style="display: flex; align-items: center; gap: 10px;">
                            <span class="category-drag-handle"
                                style="cursor: grab; color: #666; display: flex; align-items: center;" title="Drag to reorder">
                                <i class="fas fa-grip-vertical"></i>
                            </span>
                            <?php echo htmlspecialchars($catName); ?>
                        </span>
                        <i class="fas fa-chevron-down" style="color: #666; transition: transform 0.3s ease;"></i>
                    </div>
                    <div class="rank-list-body">
                        <?php foreach ($catRanks as $rank): ?>
                            <div class="rank-list-item" data-id="<?php echo $rank['id']; ?>">
                                <div class="drag-handle">
                                    <i class="fas fa-grip-vertical"></i>
                                </div>
                                <?php
                                $imgFile = !empty($rank['image']) ? $rank['image'] : 'default_rank.png';
                                $imgSrc = '/assets/images/ranks/' . $imgFile;
                                ?>
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                    onerror="this.onerror=null; this.src='/assets/images/logo.png';" class="rank-image"
                                    alt="Rank Icon">
                                <div style="flex: 1;">
                                    <div style="color: #fff; font-weight: 500; font-size: 1.05rem; margin-bottom: 2px;">
                                        <?php echo htmlspecialchars($rank['name']); ?>
                                    </div>
                                    <div style="color: #bbb; font-size: 0.85rem; margin-top: 5px; font-weight: bold;">
                                        <?php echo htmlspecialchars($rank['nato_code'] ?: 'No NATO Code'); ?>
                                    </div>
                                    <div style="color: #888; font-size: 0.8rem; letter-spacing: 0.5px;">
                                        <?php echo htmlspecialchars($rank['abbreviation']); ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <button class="action-btn" style="color: #64b5f6;" title="Edit"
                                        onclick="editRank(<?php echo $rank['id']; ?>, '<?php echo htmlspecialchars($rank['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($rank['abbreviation'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($rank['category'], ENT_QUOTES); ?>', <?php echo $rank['order_index']; ?>, '<?php echo htmlspecialchars($rank['nato_code'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($rank['image']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn" style="color: #e57373;" title="Delete"
                                        onclick="confirmDelete(<?php echo $rank['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <a href="manage_rank_detail?id=<?php echo $rank['id']; ?>" class="btn-see-more">
                                        <i class="fas fa-search"></i> See More
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal (Combined) -->
<div id="rankModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="modalTitle" style="color: var(--accent-color); margin: 0 0 20px 0; font-size: 1.5rem;">Add New Rank</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="rank_id" id="rank_id">

            <div style="text-align: center; margin-bottom: 20px;">
                <div
                    style="width: 80px; height: 80px; margin: 0 auto 15px auto; border: 1px dashed rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 10px;">
                    <img id="image_preview" src=""
                        onerror="this.onerror=null; this.style.display='none'; document.getElementById('image_placeholder').style.display='block';"
                        style="max-width: 100%; max-height: 100%; display: none;">
                    <i id="image_placeholder" class="fas fa-image" style="font-size: 2rem; color: #555;"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Category (e.g. Army Officer Ranks)</label>
                <input type="text" name="category" id="rank_category" required class="form-input"
                    placeholder="Army Officer Ranks" list="categoriesList">
                <datalist id="categoriesList">
                    <?php foreach (array_keys($categories) as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Rank Name</label>
                    <input type="text" name="rank_name" id="rank_name" required class="form-input" placeholder="Major">
                </div>
                <div class="form-group">
                    <label class="form-label">Abbreviation</label>
                    <input type="text" name="abbreviation" id="rank_abbreviation" required class="form-input"
                        placeholder="MAJ">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Rank Image</label>
                    <input type="file" name="rank_image" accept="image/*" class="form-input" style="padding-top: 10px;">
                </div>
                <div class="form-group">
                    <label class="form-label">NATO Code</label>
                    <input type="text" name="nato_code" id="rank_nato" value="" class="form-input" placeholder="OR-9">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 15px;">
                <button type="button" onclick="closeModal()" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #aaa;">Cancel</button>
                <button type="submit" class="btn" style="flex: 1;">Save Rank</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="rank_id" id="deleteRankId">
</form>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Add New Rank';
        document.getElementById('formAction').value = 'create';
        document.getElementById('rank_id').value = '';
        document.getElementById('rank_name').value = '';
        document.getElementById('rank_abbreviation').value = '';
        document.getElementById('rank_category').value = '';
        document.getElementById('rank_nato').value = '';
        document.getElementById('image_preview').style.display = 'none';
        document.getElementById('image_placeholder').style.display = 'block';
        document.getElementById('rankModal').classList.add('active');
    }

    function editRank(id, name, abbr, cat, order, nato, img) {
        document.getElementById('modalTitle').innerText = 'Edit Rank';
        document.getElementById('formAction').value = 'update';
        document.getElementById('rank_id').value = id;
        document.getElementById('rank_name').value = name;
        document.getElementById('rank_abbreviation').value = abbr;
        document.getElementById('rank_category').value = cat;
        document.getElementById('rank_nato').value = nato;
        if (img) {
            document.getElementById('image_preview').src = '/assets/images/ranks/' + img + '?v=' + new Date().getTime();
            document.getElementById('image_preview').style.display = 'block';
            document.getElementById('image_placeholder').style.display = 'none';
        } else {
            document.getElementById('image_preview').style.display = 'none';
            document.getElementById('image_placeholder').style.display = 'block';
        }

        document.getElementById('rankModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('rankModal').classList.remove('active');
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This rank will be deleted permanently!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff6b6b',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteRankId').value = id;
                document.getElementById('deleteForm').submit();
            }
        });
    }

    function toggleCategory(header) {
        const body = header.nextElementSibling;
        const icon = header.querySelector('.fa-chevron-down');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            icon.style.transform = 'rotate(0deg)';
        } else {
            body.style.display = 'none';
            icon.style.transform = 'rotate(-90deg)';
        }
    }

    window.onclick = function (event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
        }
    }

    // Initialize SortableJS for drag and drop reordering
    document.addEventListener('DOMContentLoaded', function () {
        // Category drag-and-drop
        const catContainer = document.getElementById('categoriesSortable');
        if (catContainer) {
            new Sortable(catContainer, {
                handle: '.category-drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    const newOrder = Array.from(catContainer.querySelectorAll('.admin-card[data-category-id]')).map(el => el.dataset.categoryId);
                    if (newOrder.length > 0) {
                        fetch('update_rank_category_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order: newOrder })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true, background: 'rgba(20,20,20,0.9)', color: '#fff' });
                                    Toast.fire({ icon: 'success', title: 'Category order saved' });
                                } else {
                                    Swal.fire('Error', data.message || 'Failed to update category order', 'error');
                                }
                            })
                            .catch(error => { Swal.fire('Error', 'An error occurred.', 'error'); console.error(error); });
                    }
                }
            });
        }

        // Item drag-and-drop
        const rankLists = document.querySelectorAll('.rank-list-body');

        rankLists.forEach(list => {
            new Sortable(list, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    const itemEl = evt.item;
                    const parentUl = itemEl.parentNode;

                    // Get new order of IDs in this category
                    const newOrder = Array.from(parentUl.querySelectorAll('.rank-list-item')).map(el => el.dataset.id);

                    if (newOrder.length > 0) {
                        // Send AJAX request
                        fetch('update_rank_order.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ order: newOrder })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const Toast = Swal.mixin({
                                        toast: true,
                                        position: 'top-end',
                                        showConfirmButton: false,
                                        timer: 1500,
                                        timerProgressBar: true,
                                        background: 'rgba(20,20,20,0.9)',
                                        color: '#fff'
                                    });
                                    Toast.fire({
                                        icon: 'success',
                                        title: 'Order saved'
                                    });
                                } else {
                                    Swal.fire('Error', data.message || 'Failed to update order', 'error');
                                }
                            })
                            .catch(error => {
                                Swal.fire('Error', 'An error occurred while saving the new order.', 'error');
                                console.error('Error:', error);
                            });
                    }
                },
            });
        });
    });
</script>

<?php if (!$ajax_mode) { ?>
    </div><!-- #tab-content -->
    <?php include ROOT_PATH . '/includes/footer.php';
} ?>