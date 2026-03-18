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

$message = '';
$error = '';

// Ensure upload directory exists
$uploadDir = ROOT_PATH . '/assets/images/awards/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // --- AWARD MANAGEMENT ---
        if (in_array($_POST['action'], ['create_award', 'update_award'])) {
            $id = isset($_POST['award_id']) ? (int) $_POST['award_id'] : 0;
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $order_index = (int) $_POST['order_index'];

            // Resolve category by name — auto-create if new
            $category_name = sanitize($_POST['category_name'] ?? '');
            $category_id = 0;
            if (!empty($category_name)) {
                $cat_stmt = $pdo->prepare("SELECT id FROM award_categories WHERE name = ?");
                $cat_stmt->execute([$category_name]);
                $existing_cat = $cat_stmt->fetch();
                if ($existing_cat) {
                    $category_id = (int) $existing_cat['id'];
                } else {
                    // Auto-create category
                    $cat_insert = $pdo->prepare("INSERT INTO award_categories (name, order_index) VALUES (?, 0)");
                    $cat_insert->execute([$category_name]);
                    $category_id = (int) $pdo->lastInsertId();
                }
            }

            // Handle Image Upload
            $imageFile = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Remove spaces and special chars for filename
                $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $imageFile = $cleanName . '_' . time() . '.' . $ext;
                $destPath = $uploadDir . $imageFile;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                    $error = "Failed to upload image.";
                }
            }

            if (!$error) {
                try {
                    if ($_POST['action'] === 'create_award') {
                        $stmt = $pdo->prepare("INSERT INTO awards (category_id, name, description, image, order_index) VALUES (?, ?, ?, ?, ?)");
                        $img = $imageFile ?: 'default_award.png';
                        $stmt->execute([$category_id, $name, $description, $img, $order_index]);
                        $message = "Award created successfully!";
                    } else { // update_award
                        if ($imageFile) {
                            $stmt = $pdo->prepare("UPDATE awards SET category_id = ?, name = ?, description = ?, image = ?, order_index = ? WHERE id = ?");
                            $stmt->execute([$category_id, $name, $description, $imageFile, $order_index, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE awards SET category_id = ?, name = ?, description = ?, order_index = ? WHERE id = ?");
                            $stmt->execute([$category_id, $name, $description, $order_index, $id]);
                        }
                        $message = "Award updated successfully!";
                    }
                } catch (PDOException $e) {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'delete_award') {
            $id = (int) $_POST['award_id'];
            try {
                // Delete image if not default
                $stmt = $pdo->prepare("SELECT image FROM awards WHERE id = ?");
                $stmt->execute([$id]);
                $award = $stmt->fetch();
                if ($award && $award['image'] && $award['image'] !== 'default_award.png') {
                    $imgPath = $uploadDir . $award['image'];
                    if (file_exists($imgPath)) {
                        unlink($imgPath);
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM awards WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Award deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting Award: " . $e->getMessage();
            }
        }

        // --- CATEGORY MANAGEMENT ---
        elseif (in_array($_POST['action'], ['create_category', 'update_category'])) {
            $id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            $name = sanitize($_POST['name']);
            $order_index = (int) $_POST['order_index'];

            try {
                if ($_POST['action'] === 'create_category') {
                    $stmt = $pdo->prepare("INSERT INTO award_categories (name, order_index) VALUES (?, ?)");
                    $stmt->execute([$name, $order_index]);
                    $message = "Category created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE award_categories SET name = ?, order_index = ? WHERE id = ?");
                    $stmt->execute([$name, $order_index, $id]);
                    $message = "Category updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'delete_category') {
            $id = (int) $_POST['category_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM award_categories WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Category deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete category if there are awards assigned to it. " . $e->getMessage();
            }
        }
    }
}

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM award_categories ORDER BY order_index ASC, name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categoriesById = [];
foreach ($categories as $cat) {
    $categoriesById[$cat['id']] = $cat;
}

// Fetch Awards grouped by Category
$stmt = $pdo->query("SELECT * FROM awards ORDER BY category_id ASC, order_index ASC, name ASC");
$awardsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
$awardsByCategory = [];
foreach ($awardsList as $award) {
    if (!isset($awardsByCategory[$award['category_id']])) {
        $awardsByCategory[$award['category_id']] = [];
    }
    $awardsByCategory[$award['category_id']][] = $award;
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

        .award-list-item {
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

        .award-list-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .award-list-item:last-child {
            border-bottom: none;
        }

        .award-image {
            width: 80px;
            height: 40px;
            object-fit: contain;
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
    </style>

    <div style="margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 style="color: var(--accent-color); margin: 0 0 10px 0; font-size: 1.8rem; letter-spacing: 1px;">
                <i class="fas fa-medal"></i> Manage Awards
            </h2>
            <p style="color: #aaa; margin: 0; font-size: 0.95rem;">Create and manage community awards, medals, ribbons,
                and badges.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="openAwardModal()" class="btn" style="padding: 10px 20px; border-radius: 8px;">
                <i class="fas fa-plus"></i> Add Award
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div
        style="background: rgba(20,20,20,0.6); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.05);">
        <i class="fas fa-search" style="color: #888; margin-right: 15px;"></i>
        <input type="text" id="adminAwardSearch" placeholder="Search by name..."
            style="background: transparent; border: none; color: #fff; width: 100%; font-size: 1rem; outline: none;">
    </div>

    <script>
        document.getElementById('adminAwardSearch').addEventListener('keyup', function () {
            let filter = this.value.toLowerCase();
            let categories = document.querySelectorAll('.admin-card');

            categories.forEach(function (category) {
                let items = category.querySelectorAll('.award-list-item');
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

    <?php if ($message): ?>
        <script>
            Swal.fire({ icon: 'success', title: 'Success', text: '<?php echo $message; ?>', timer: 3000, showConfirmButton: false });
        </script>
    <?php endif; ?>
    <?php if ($error): ?>
        <script>
            Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo addslashes($error); ?>' });
        </script>
    <?php endif; ?>

    <div id="categoriesSortable" style="display: grid; gap: 20px;">
        <?php if (empty($categories)): ?>
            <div class="admin-card" style="text-align: center; padding: 50px;">
                <i class="fas fa-folder-open" style="font-size: 3rem; color: #444; margin-bottom: 15px;"></i>
                <p style="color: #666; font-size: 1.1rem; margin: 0;">No Categories Found. You need to create an award
                    category first.</p>
                <button class="btn btn-secondary" style="margin-top: 15px; padding: 10px 20px; border-radius: 8px;"
                    onclick="openCategoryModal()">Create Category</button>
            </div>
        <?php else: ?>
            <?php foreach ($categories as $cat): ?>
                <div class="admin-card" style="padding: 0; overflow: hidden;" data-category-id="<?php echo $cat['id']; ?>">
                    <div class="category-header" onclick="toggleCategory(this)"
                        style="margin: 0; border-radius: 0; border: none; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer;">
                        <span style="display: flex; align-items: center; gap: 10px;">
                            <span class="category-drag-handle"
                                style="cursor: grab; color: #666; display: flex; align-items: center;" title="Drag to reorder">
                                <i class="fas fa-grip-vertical"></i>
                            </span>
                            <i class="fas fa-folder" style="color: var(--accent-color);"></i>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </span>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button class="action-btn"
                                onclick="event.stopPropagation(); editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', <?php echo $cat['order_index']; ?>)"
                                title="Edit Category" style="color: #64b5f6;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn" style="color: #e57373;"
                                onclick="event.stopPropagation(); confirmDeleteCategory(<?php echo $cat['id']; ?>)"
                                title="Delete Category">
                                <i class="fas fa-trash"></i>
                            </button>
                            <i class="fas fa-chevron-down"
                                style="color: #666; transition: transform 0.3s ease; margin-left: 10px;"></i>
                        </div>
                    </div>
                    <div class="award-list-body">
                        <?php
                        $awards = isset($awardsByCategory[$cat['id']]) ? $awardsByCategory[$cat['id']] : [];
                        if (empty($awards)): ?>
                            <div style="padding: 20px; text-align: center; color: #888; font-style: italic;">
                                No awards in this category.
                            </div>
                        <?php else: ?>
                            <?php foreach ($awards as $award): ?>
                                <div class="award-list-item" data-id="<?php echo $award['id']; ?>">
                                    <div class="drag-handle">
                                        <i class="fas fa-grip-vertical"></i>
                                    </div>
                                    <?php
                                    $imgFile = !empty($award['image']) ? $award['image'] : 'default_award.png';
                                    $imgSrc = '/assets/images/awards/' . $imgFile;
                                    ?>
                                    <div style="width: 80px; display: flex; justify-content: center; margin-right: 20px;">
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                            onerror="this.onerror=null; this.src='/assets/images/logo.png';" class="award-image"
                                            alt="Award Icon">
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="color: #fff; font-weight: 500; font-size: 1.05rem; margin-bottom: 2px;">
                                            <?php echo htmlspecialchars($award['name']); ?>
                                        </div>
                                        <div style="color: #888; font-size: 0.8rem; letter-spacing: 0.5px;">
                                            <?php echo htmlspecialchars(mb_substr($award['description'], 0, 100)) . (mb_strlen($award['description']) > 100 ? '...' : ''); ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <button class="action-btn" style="color: #64b5f6;" title="Edit" onclick="editAward(
                                                <?php echo $award['id']; ?>, 
                                                '<?php echo htmlspecialchars($award['name'], ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($categoriesById[$award['category_id']]['name'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($award['description'], ENT_QUOTES); ?>', 
                                                <?php echo $award['order_index']; ?>, 
                                                '<?php echo htmlspecialchars($award['image'], ENT_QUOTES); ?>'
                                            )">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn" style="color: #e57373;" title="Delete"
                                            onclick="confirmDeleteAward(<?php echo $award['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="manage_award_detail?id=<?php echo $award['id']; ?>" class="btn-see-more">
                                            <i class="fas fa-search"></i> See More
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="catModalTitle" style="color: var(--accent-color); margin: 0 0 20px 0;">Add Category</h3>
        <form method="POST">
            <input type="hidden" name="action" id="catFormAction" value="create_category">
            <input type="hidden" name="category_id" id="cat_id">

            <div class="form-group">
                <label class="form-label">Category Name (e.g., Personal Decorations, Badges)</label>
                <input type="text" name="name" id="cat_name" required class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input type="number" name="order_index" id="cat_order" value="0" class="form-input">
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="button" onclick="closeModal('categoryModal')" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #fff;">Cancel</button>
                <button type="submit" class="btn" style="flex: 1;">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Award Modal -->
<div id="awardModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <h3 id="awardModalTitle" style="color: var(--accent-color); margin: 0 0 20px 0; font-size: 1.5rem;">Add Award
        </h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" id="awardFormAction" value="create_award">
            <input type="hidden" name="award_id" id="award_id">

            <!-- Image Preview -->
            <div style="text-align: center; margin-bottom: 20px;">
                <div
                    style="width: 150px; height: 80px; margin: 0 auto 15px auto; border: 1px dashed rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 10px;">
                    <img id="image_preview" src=""
                        onerror="this.onerror=null; this.style.display='none'; document.getElementById('image_placeholder').style.display='block';"
                        style="max-width: 100%; max-height: 100%; display: none;">
                    <i id="image_placeholder" class="fas fa-medal" style="font-size: 2rem; color: #555;"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Award Name</label>
                <input type="text" name="name" id="award_name" required class="form-input"
                    placeholder="e.g., Medal Of Honor">
            </div>

            <div class="form-group">
                <label class="form-label">Category (e.g., Personal Decorations)</label>
                <input type="text" name="category_name" id="award_category" required class="form-input"
                    placeholder="Personal Decorations" list="awardCategoriesList">
                <datalist id="awardCategoriesList">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label class="form-label">Award Description (Shown on detail page)</label>
                <textarea name="description" id="award_desc" class="form-input" rows="3"
                    placeholder="Awarded for conspicuous gallantry..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Ribbon / Medal Image (PNG/JPG)</label>
                <input type="file" name="image" accept="image/*" class="form-input" style="padding-top: 10px;">
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="button" onclick="closeModal('awardModal')" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #fff;">Cancel</button>
                <button type="submit" class="btn" style="flex: 1;">Save Award</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Forms -->
<form id="deleteCatForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_category">
    <input type="hidden" name="category_id" id="deleteCatId">
</form>

<form id="deleteAwardForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_award">
    <input type="hidden" name="award_id" id="deleteAwardId">
</form>

<script>
    function toggleCategory(element) {
        // Toggle the icon
        const icon = element.querySelector('.fa-chevron-down');
        const content = element.nextElementSibling;

        if (content.style.display === "none") {
            content.style.display = "block";
            icon.style.transform = "rotate(0deg)";
        } else {
            content.style.display = "none";
            icon.style.transform = "rotate(-90deg)";
        }
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    /* Category Functions */
    function openCategoryModal() {
        document.getElementById('catModalTitle').innerText = 'Add Category';
        document.getElementById('catFormAction').value = 'create_category';
        document.getElementById('cat_id').value = '';
        document.getElementById('cat_name').value = '';
        document.getElementById('cat_order').value = '0';
        document.getElementById('categoryModal').classList.add('active');
    }

    function editCategory(id, name, order) {
        document.getElementById('catModalTitle').innerText = 'Edit Category';
        document.getElementById('catFormAction').value = 'update_category';
        document.getElementById('cat_id').value = id;
        document.getElementById('cat_name').value = name;
        document.getElementById('cat_order').value = order;
        document.getElementById('categoryModal').classList.add('active');
    }

    function confirmDeleteCategory(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You can only delete this if there are no awards assigned to it!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff6b6b',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteCatId').value = id;
                document.getElementById('deleteCatForm').submit();
            }
        });
    }

    /* Award Functions */
    function openAwardModal() {
        document.getElementById('awardModalTitle').innerText = 'Add New Award';
        document.getElementById('awardFormAction').value = 'create_award';
        document.getElementById('award_id').value = '';
        document.getElementById('award_name').value = '';
        document.getElementById('award_category').value = '';
        document.getElementById('award_desc').value = '';

        document.getElementById('image_preview').style.display = 'none';
        document.getElementById('image_placeholder').style.display = 'block';

        document.getElementById('awardModal').classList.add('active');
    }

    function editAward(id, name, catName, desc, order, img) {
        document.getElementById('awardModalTitle').innerText = 'Edit Award';
        document.getElementById('awardFormAction').value = 'update_award';
        document.getElementById('award_id').value = id;
        document.getElementById('award_name').value = name;
        document.getElementById('award_category').value = catName;
        document.getElementById('award_desc').value = desc;

        if (img && img !== 'default_award.png') {
            document.getElementById('image_preview').src = '/assets/images/awards/' + img;
            document.getElementById('image_preview').style.display = 'block';
            document.getElementById('image_placeholder').style.display = 'none';
        } else {
            document.getElementById('image_preview').style.display = 'none';
            document.getElementById('image_placeholder').style.display = 'block';
        }

        document.getElementById('awardModal').classList.add('active');
    }

    function confirmDeleteAward(id) {
        Swal.fire({
            title: 'Delete Award?',
            text: "This will remove the award from all users. This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff6b6b',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteAwardId').value = id;
                document.getElementById('deleteAwardForm').submit();
            }
        });
    }

    // Modal click outside to close
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
                        fetch('update_award_category_order.php', {
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
        const awardLists = document.querySelectorAll('.award-list-body');

        awardLists.forEach(list => {
            new Sortable(list, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    const itemEl = evt.item;
                    const parentUl = itemEl.parentNode;

                    // Get new order of IDs in this category
                    const newOrder = Array.from(parentUl.querySelectorAll('.award-list-item')).map(el => el.dataset.id);

                    if (newOrder.length > 0) {
                        // Send AJAX request
                        fetch('update_award_order.php', {
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