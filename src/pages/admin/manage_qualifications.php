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
$uploadDir = ROOT_PATH . '/assets/images/qualifications/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `qualification_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `order_index` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `qualifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `image` varchar(255) DEFAULT 'default_qualification.png',
        `order_index` int(11) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `category_id` (`category_id`),
        CONSTRAINT `qualifications_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `qualification_categories` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_qualifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `qualification_id` int(11) NOT NULL,
        `date_awarded` date DEFAULT NULL,
        `awarded_by` int(11) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `qualification_id` (`qualification_id`),
        CONSTRAINT `user_qualifications_ibfk_2` FOREIGN KEY (`qualification_id`) REFERENCES `qualifications` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
} catch (PDOException $e) {
    // Tables may already exist
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        // --- QUALIFICATION MANAGEMENT ---
        if (in_array($_POST['action'], ['create_qualification', 'update_qualification'])) {
            $id = isset($_POST['qualification_id']) ? (int) $_POST['qualification_id'] : 0;
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $order_index = (int) $_POST['order_index'];

            // Resolve category by name
            $category_name = sanitize($_POST['category_name'] ?? '');
            $category_id = 0;
            if (!empty($category_name)) {
                $cat_stmt = $pdo->prepare("SELECT id FROM qualification_categories WHERE name = ?");
                $cat_stmt->execute([$category_name]);
                $existing_cat = $cat_stmt->fetch();
                if ($existing_cat) {
                    $category_id = (int) $existing_cat['id'];
                } else {
                    $cat_insert = $pdo->prepare("INSERT INTO qualification_categories (name, order_index) VALUES (?, 0)");
                    $cat_insert->execute([$category_name]);
                    $category_id = (int) $pdo->lastInsertId();
                }
            }

            // Handle Image Upload
            $imageFile = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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
                    if ($_POST['action'] === 'create_qualification') {
                        $stmt = $pdo->prepare("INSERT INTO qualifications (category_id, name, description, image, order_index) VALUES (?, ?, ?, ?, ?)");
                        $img = $imageFile ?: 'default_qualification.png';
                        $stmt->execute([$category_id, $name, $description, $img, $order_index]);
                        $message = "Qualification created successfully!";
                    } else {
                        if ($imageFile) {
                            $stmt = $pdo->prepare("UPDATE qualifications SET category_id = ?, name = ?, description = ?, image = ?, order_index = ? WHERE id = ?");
                            $stmt->execute([$category_id, $name, $description, $imageFile, $order_index, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE qualifications SET category_id = ?, name = ?, description = ?, order_index = ? WHERE id = ?");
                            $stmt->execute([$category_id, $name, $description, $order_index, $id]);
                        }
                        $message = "Qualification updated successfully!";
                    }
                } catch (PDOException $e) {
                    $error = "Database Error: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'delete_qualification') {
            $id = (int) $_POST['qualification_id'];
            try {
                $stmt = $pdo->prepare("SELECT image FROM qualifications WHERE id = ?");
                $stmt->execute([$id]);
                $qual = $stmt->fetch();
                if ($qual && $qual['image'] && $qual['image'] !== 'default_qualification.png') {
                    $imgPath = $uploadDir . $qual['image'];
                    if (file_exists($imgPath))
                        unlink($imgPath);
                }
                $stmt = $pdo->prepare("DELETE FROM qualifications WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Qualification deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error deleting Qualification: " . $e->getMessage();
            }
        }

        // --- CATEGORY MANAGEMENT ---
        elseif ($_POST['action'] === 'delete_category') {
            $id = (int) $_POST['category_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM qualification_categories WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Category deleted successfully!";
            } catch (PDOException $e) {
                $error = "Cannot delete category if there are qualifications assigned to it. " . $e->getMessage();
            }
        }
    }
}

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM qualification_categories ORDER BY order_index ASC, name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categoriesById = [];
foreach ($categories as $cat) {
    $categoriesById[$cat['id']] = $cat;
}

// Fetch Qualifications grouped by Category
$stmt = $pdo->query("SELECT * FROM qualifications ORDER BY category_id ASC, order_index ASC, name ASC");
$qualList = $stmt->fetchAll(PDO::FETCH_ASSOC);
$qualsByCategory = [];
foreach ($qualList as $qual) {
    if (!isset($qualsByCategory[$qual['category_id']])) {
        $qualsByCategory[$qual['category_id']] = [];
    }
    $qualsByCategory[$qual['category_id']][] = $qual;
}
?>

<?php if (!$ajax_mode) {
    include ROOT_PATH . '/admin/includes/member_tabs.php';
} ?>

<div class="dashboard-container" style="padding-top: 0; padding-bottom: 20px;">
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

        .qual-list-item {
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

        .qual-list-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .qual-list-item:last-child {
            border-bottom: none;
        }

        .qual-image {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 6px;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            font-size: 1.1rem;
            transition: all 0.2s;
            opacity: 0.7;
        }

        .action-btn:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .btn-see-more {
            background: rgba(255, 255, 255, 0.05);
            color: #aaa;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-see-more:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

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
            max-width: 600px;
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
                <i class="fas fa-graduation-cap"></i> Manage Qualifications
            </h2>
            <p style="color: #aaa; margin: 0; font-size: 0.95rem;">Create and manage qualifications, schools, and tabs.
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="openQualModal()" class="btn" style="padding: 10px 20px; border-radius: 8px;">
                <i class="fas fa-plus"></i> Add Qualification
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div
        style="background: rgba(20,20,20,0.6); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.05);">
        <i class="fas fa-search" style="color: #888; margin-right: 15px;"></i>
        <input type="text" id="adminQualSearch" placeholder="Search by name..."
            style="background: transparent; border: none; color: #fff; width: 100%; font-size: 1rem; outline: none;">
    </div>

    <script>
        document.getElementById('adminQualSearch').addEventListener('keyup', function () {
            let filter = this.value.toLowerCase();
            let categories = document.querySelectorAll('.admin-card');
            categories.forEach(function (category) {
                let items = category.querySelectorAll('.qual-list-item');
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
                category.style.display = (hasVisibleItems || filter === '') ? '' : 'none';
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
                <i class="fas fa-graduation-cap" style="font-size: 3rem; color: #444; margin-bottom: 15px;"></i>
                <p style="color: #666; font-size: 1.1rem; margin: 0;">No qualifications found. Click "Add Qualification" to
                    get started.</p>
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
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button class="action-btn" style="color: #64b5f6;" title="Edit Category"
                                onclick="event.stopPropagation(); editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn" style="color: #e57373;" title="Delete Category"
                                onclick="event.stopPropagation(); confirmDeleteCategory(<?php echo $cat['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <i class="fas fa-chevron-down" style="color: #666; transition: transform 0.3s ease;"></i>
                        </div>
                    </div>
                    <div class="qual-list-body">
                        <?php if (isset($qualsByCategory[$cat['id']])): ?>
                            <?php foreach ($qualsByCategory[$cat['id']] as $qual): ?>
                                <div class="qual-list-item" data-id="<?php echo $qual['id']; ?>">
                                    <div class="drag-handle">
                                        <i class="fas fa-grip-vertical"></i>
                                    </div>
                                    <?php
                                    $imgFile = !empty($qual['image']) ? $qual['image'] : 'default_qualification.png';
                                    $imgSrc = '/assets/images/qualifications/' . $imgFile;
                                    ?>
                                    <div style="width: 80px; display: flex; justify-content: center; margin-right: 20px;">
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                            onerror="this.onerror=null; this.src='/assets/images/logo.png';" class="qual-image"
                                            alt="Qualification Icon">
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="color: #fff; font-weight: 500; font-size: 1.05rem; margin-bottom: 2px;">
                                            <?php echo htmlspecialchars($qual['name']); ?>
                                        </div>
                                        <div style="color: #888; font-size: 0.8rem; letter-spacing: 0.5px;">
                                            <?php echo htmlspecialchars(mb_substr($qual['description'], 0, 100)) . (mb_strlen($qual['description']) > 100 ? '...' : ''); ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <button class="action-btn" style="color: #64b5f6;" title="Edit" onclick="editQual(
                                                <?php echo $qual['id']; ?>, 
                                                '<?php echo htmlspecialchars($qual['name'], ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($categoriesById[$qual['category_id']]['name'] ?? '', ENT_QUOTES); ?>', 
                                                '<?php echo htmlspecialchars($qual['description'], ENT_QUOTES); ?>', 
                                                <?php echo $qual['order_index']; ?>, 
                                                '<?php echo htmlspecialchars($qual['image'], ENT_QUOTES); ?>'
                                            )">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn" style="color: #e57373;" title="Delete"
                                            onclick="confirmDeleteQual(<?php echo $qual['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="manage_qualification_detail?id=<?php echo $qual['id']; ?>" class="btn-see-more">
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

<!-- Category Edit Modal -->
<div id="categoryModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px;">
        <h3 id="catModalTitle" style="color: var(--accent-color); margin: 0 0 20px 0;">Edit Category</h3>
        <form method="POST">
            <input type="hidden" name="action" id="catFormAction" value="update_category">
            <input type="hidden" name="category_id" id="cat_id">
            <div class="form-group">
                <label class="form-label">Category Name</label>
                <input type="text" name="name" id="cat_name" required class="form-input">
            </div>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="button" onclick="closeModal('categoryModal')" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #fff;">Cancel</button>
                <button type="submit" class="btn" style="flex: 1;">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Qualification Modal -->
<div id="qualModal" class="modal-overlay">
    <div class="modal-content">
        <h3 id="qualModalTitle" style="color: var(--accent-color); margin: 0 0 20px 0; font-size: 1.5rem;">Add
            Qualification</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" id="qualFormAction" value="create_qualification">
            <input type="hidden" name="qualification_id" id="qual_id">

            <!-- Image Preview -->
            <div style="text-align: center; margin-bottom: 20px;">
                <div
                    style="width: 150px; height: 80px; margin: 0 auto 15px auto; border: 1px dashed rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 10px;">
                    <img id="image_preview" src=""
                        onerror="this.onerror=null; this.style.display='none'; document.getElementById('image_placeholder').style.display='block';"
                        style="max-width: 100%; max-height: 100%; display: none;">
                    <i id="image_placeholder" class="fas fa-graduation-cap" style="font-size: 2rem; color: #555;"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Qualification Name</label>
                <input type="text" name="name" id="qual_name" required class="form-input"
                    placeholder="e.g., Special Forces Tab">
            </div>

            <div class="form-group">
                <label class="form-label">Category (e.g., Tabs, Schools)</label>
                <input type="text" name="category_name" id="qual_category" required class="form-input"
                    placeholder="Tabs" list="qualCategoriesList">
                <datalist id="qualCategoriesList">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="qual_desc" class="form-input" rows="3"
                    placeholder="Description of this qualification..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Qualification Image (PNG/JPG)</label>
                <input type="file" name="image" accept="image/*" class="form-input" style="padding-top: 10px;">
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="button" onclick="closeModal('qualModal')" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #fff;">Cancel</button>
                <button type="submit" class="btn" style="flex: 1;">Save Qualification</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Forms -->
<form id="deleteCatForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_category">
    <input type="hidden" name="category_id" id="deleteCatId">
</form>

<form id="deleteQualForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_qualification">
    <input type="hidden" name="qualification_id" id="deleteQualId">
</form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    function toggleCategory(element) {
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

    function editCategory(id, name) {
        document.getElementById('catModalTitle').innerText = 'Edit Category';
        document.getElementById('catFormAction').value = 'update_category';
        document.getElementById('cat_id').value = id;
        document.getElementById('cat_name').value = name;
        document.getElementById('categoryModal').classList.add('active');
    }

    function confirmDeleteCategory(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You can only delete this if there are no qualifications assigned to it!",
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

    function openQualModal() {
        document.getElementById('qualModalTitle').innerText = 'Add New Qualification';
        document.getElementById('qualFormAction').value = 'create_qualification';
        document.getElementById('qual_id').value = '';
        document.getElementById('qual_name').value = '';
        document.getElementById('qual_category').value = '';
        document.getElementById('qual_desc').value = '';
        document.getElementById('image_preview').style.display = 'none';
        document.getElementById('image_placeholder').style.display = 'block';
        document.getElementById('qualModal').classList.add('active');
    }

    function editQual(id, name, catName, desc, order, img) {
        document.getElementById('qualModalTitle').innerText = 'Edit Qualification';
        document.getElementById('qualFormAction').value = 'update_qualification';
        document.getElementById('qual_id').value = id;
        document.getElementById('qual_name').value = name;
        document.getElementById('qual_category').value = catName;
        document.getElementById('qual_desc').value = desc;

        if (img && img !== 'default_qualification.png') {
            document.getElementById('image_preview').src = '/assets/images/qualifications/' + img;
            document.getElementById('image_preview').style.display = 'block';
            document.getElementById('image_placeholder').style.display = 'none';
        } else {
            document.getElementById('image_preview').style.display = 'none';
            document.getElementById('image_placeholder').style.display = 'block';
        }

        document.getElementById('qualModal').classList.add('active');
    }

    function confirmDeleteQual(id) {
        Swal.fire({
            title: 'Delete Qualification?',
            text: "This will remove the qualification from all users. This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff6b6b',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteQualId').value = id;
                document.getElementById('deleteQualForm').submit();
            }
        });
    }

    window.onclick = function (event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
        }
    }

    // SortableJS
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
                        fetch('update_qualification_category_order.php', {
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
        const qualLists = document.querySelectorAll('.qual-list-body');
        qualLists.forEach(list => {
            new Sortable(list, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function (evt) {
                    const parentUl = evt.item.parentNode;
                    const newOrder = Array.from(parentUl.querySelectorAll('.qual-list-item')).map(el => el.dataset.id);
                    if (newOrder.length > 0) {
                        fetch('update_qualification_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order: newOrder })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true, background: 'rgba(20,20,20,0.9)', color: '#fff' });
                                    Toast.fire({ icon: 'success', title: 'Order saved' });
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