<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/admin_log.php';

if (!isAdmin()) {
    redirect('../login');
}

$message = '';
$error = '';

// Handle Category Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['cat_name']);
        $slug = strtolower(str_replace(' ', '-', $name));
        $description = sanitize($_POST['cat_description']);

        try {
            $stmt = $pdo->prepare("INSERT INTO media_categories (name, slug, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $description]);
            logAdminAction($pdo, 'add_category', 'category', $pdo->lastInsertId(), ['name' => $name]);
            $message = "Category added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding category: " . $e->getMessage();
        }
    }

    if (isset($_POST['edit_category'])) {
        $id = (int) $_POST['cat_id'];
        $name = sanitize($_POST['cat_name']);
        $slug = strtolower(str_replace(' ', '-', $name));
        $description = sanitize($_POST['cat_description']);

        try {
            $stmt = $pdo->prepare("UPDATE media_categories SET name = ?, slug = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $description, $id]);
            logAdminAction($pdo, 'edit_category', 'category', $id, ['name' => $name]);
            $message = "Category updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating category: " . $e->getMessage();
        }
    }

    if (isset($_POST['add_tag'])) {
        $name = sanitize($_POST['tag_name']);
        $slug = strtolower(str_replace(' ', '-', $name));

        try {
            $stmt = $pdo->prepare("INSERT INTO media_tags (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            logAdminAction($pdo, 'add_tag', 'tag', $pdo->lastInsertId(), ['name' => $name]);
            $message = "Tag added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding tag: " . $e->getMessage();
        }
    }

    if (isset($_POST['edit_tag'])) {
        $id = (int) $_POST['tag_id'];
        $name = sanitize($_POST['tag_name']);
        $slug = strtolower(str_replace(' ', '-', $name));

        try {
            $stmt = $pdo->prepare("UPDATE media_tags SET name = ?, slug = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $id]);
            logAdminAction($pdo, 'edit_tag', 'tag', $id, ['name' => $name]);
            $message = "Tag updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating tag: " . $e->getMessage();
        }
    }
}

// Handle Delete
if (isset($_GET['delete_category'])) {
    $id = (int) $_GET['delete_category'];
    try {
        $stmt = $pdo->prepare("DELETE FROM media_categories WHERE id = ?");
        $stmt->execute([$id]);
        logAdminAction($pdo, 'delete_category', 'category', $id);
        $message = "Category deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting category: " . $e->getMessage();
    }
}

if (isset($_GET['delete_tag'])) {
    $id = (int) $_GET['delete_tag'];
    try {
        $stmt = $pdo->prepare("DELETE FROM media_tags WHERE id = ?");
        $stmt->execute([$id]);
        logAdminAction($pdo, 'delete_tag', 'tag', $id);
        $message = "Tag deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting tag: " . $e->getMessage();
    }
}

// Fetch all categories
$categories = $pdo->query("SELECT c.*, COUNT(m.id) as media_count 
                           FROM media_categories c 
                           LEFT JOIN media_gallery m ON c.id = m.category_id 
                           GROUP BY c.id 
                           ORDER BY c.name")->fetchAll();

// Fetch all tags
$tags = $pdo->query("SELECT t.*, COUNT(mgt.media_id) as media_count 
                     FROM media_tags t 
                     LEFT JOIN media_gallery_tags mgt ON t.id = mgt.tag_id 
                     GROUP BY t.id 
                     ORDER BY t.name")->fetchAll();

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<div style="max-width: 1400px; margin: 0 auto; padding: 20px;">

    <!-- Page Header -->
    <!-- Page Header -->
    <div style="margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 style="color: var(--accent-color); margin: 0 0 10px 0; font-size: 1.8rem; letter-spacing: 1px;">
                <i class="fas fa-folder-open"></i> Categories & Tags
            </h2>
            <p style="color: #aaa; margin: 0; font-size: 0.95rem;">Manage organization structure for your media library
            </p>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div
            style="background: rgba(76, 175, 80, 0.15); color: #81c784; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(76, 175, 80, 0.3); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div
            style="background: rgba(244, 67, 54, 0.15); color: #e57373; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(244, 67, 54, 0.3); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

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

        .item-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .list-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .list-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.1);
            transform: translateX(2px);
        }

        .tag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }

        /* Scrollbar styles */
        .item-list::-webkit-scrollbar {
            width: 6px;
        }

        .item-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }

        .item-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
    </style>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">

        <!-- Categories Section -->
        <div>
            <!-- Add Category -->
            <div
                style="background: rgba(20, 20, 20, 0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 0 12px 12px 12px; padding: 25px; margin-bottom: 25px; height: auto; position: relative; margin-top: 50px;">

                <!-- Back Button Bookmark -->
                <a href="manage_media.php"
                    style="position: absolute; top: -38px; left: -1px; background: #c5a059; padding: 10px 24px; border: 1px solid #b38f4a; border-radius: 8px 8px 0 0; color: #000; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; font-weight: 700; height: 38px; z-index: 2; box-shadow: 0 -4px 10px rgba(197, 160, 89, 0.2); letter-spacing: 0.5px;">
                    <i class="fas fa-arrow-left"></i> BACK TO MEDIA MANAGEMENT
                </a>

                <h3
                    style="color: var(--accent-color); margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-plus-circle"></i> Add New Category
                </h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="cat_name" required class="form-input" placeholder="e.g., Operations">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="cat_description" rows="3" class="form-input"
                            placeholder="Brief description of this category..."></textarea>
                    </div>

                    <button type="submit" name="add_category" class="btn"
                        style="width: 100%; padding: 12px; display: flex; justify-content: center; align-items: center; gap: 8px;">
                        <i class="fas fa-plus"></i> Create Category
                    </button>
                </form>
            </div>

            <!-- List Categories -->
            <div class="admin-card">
                <h3><i class="fas fa-list"></i> Existing Categories <span
                        style="font-size: 0.8em; opacity: 0.6; margin-left: auto;"><?php echo count($categories); ?></span>
                </h3>

                <?php if (empty($categories)): ?>
                    <p style="color: #666; text-align: center; padding: 30px;">No categories created yet.</p>
                <?php else: ?>
                    <div class="item-list">
                        <?php foreach ($categories as $cat): ?>
                            <div class="list-item">
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 4px 0; color: #fff; font-size: 1rem;">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </h4>
                                    <p style="margin: 0; color: #666; font-size: 0.8rem;">
                                        <?php echo htmlspecialchars($cat['description'] ?: 'No description'); ?>
                                    </p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <span
                                        style="background: rgba(197, 160, 89, 0.1); color: var(--accent-color); padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; border: 1px solid rgba(197, 160, 89, 0.2);">
                                        <?php echo $cat['media_count']; ?>
                                    </span>
                                    <div style="display: flex; gap: 8px;">
                                        <button
                                            onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($cat['description'], ENT_QUOTES); ?>')"
                                            style="background: none; border: none; color: #64b5f6; cursor: pointer; transition: color 0.2s;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete_category=<?php echo $cat['id']; ?>"
                                            onclick="return confirmAction(event, 'Delete Category', 'Delete this category? Media will not be deleted.')"
                                            style="color: #e57373; cursor: pointer; transition: color 0.2s;">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tags Section -->
        <div>
            <!-- Add Tag -->
            <div class="admin-card" style="margin-bottom: 25px; height: auto; margin-top: 50px;">
                <h3><i class="fas fa-tag"></i> Add New Tag</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Tag Name</label>
                        <input type="text" name="tag_name" required class="form-input" placeholder="e.g., night-ops">
                    </div>

                    <button type="submit" name="add_tag" class="btn"
                        style="width: 100%; padding: 12px; display: flex; justify-content: center; align-items: center; gap: 8px;">
                        <i class="fas fa-plus"></i> Create Tag
                    </button>
                </form>
            </div>

            <!-- List Tags -->
            <div class="admin-card">
                <h3><i class="fas fa-tags"></i> Existing Tags <span
                        style="font-size: 0.8em; opacity: 0.6; margin-left: auto;"><?php echo count($tags); ?></span>
                </h3>

                <?php if (empty($tags)): ?>
                    <p style="color: #666; text-align: center; padding: 30px;">No tags created yet.</p>
                <?php else: ?>
                    <div class="tag-grid">
                        <?php foreach ($tags as $tag): ?>
                            <div
                                style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); padding: 12px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s; hover: {background: rgba(255,255,255,0.08);}">
                                <div>
                                    <h4 style="margin: 0; color: #ddd; font-size: 0.9rem; font-weight: 500;">
                                        #<?php echo htmlspecialchars($tag['name']); ?>
                                    </h4>
                                    <span style="font-size: 0.7rem; color: #666;">
                                        <?php echo $tag['media_count']; ?> uses
                                    </span>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button
                                        onclick="editTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['name'], ENT_QUOTES); ?>')"
                                        style="background: none; border: none; color: #64b5f6; cursor: pointer; padding: 4px;">
                                        <i class="fas fa-edit" style="font-size: 0.9rem;"></i>
                                    </button>
                                    <a href="?delete_tag=<?php echo $tag['id']; ?>"
                                        onclick="return confirmAction(event, 'Delete Tag', 'Delete this tag?')"
                                        style="color: #e57373; cursor: pointer; padding: 4px;">
                                        <i class="fas fa-trash" style="font-size: 0.9rem;"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Styles Reuse -->
<style>
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
        background: #1a1a1a;
        background: linear-gradient(145deg, #1a1a1a, #151515);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 30px;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        transform: translateY(20px);
        transition: transform 0.3s;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    }

    .modal-overlay.active {
        display: flex;
        opacity: 1;
    }

    .modal-overlay.active .modal-content {
        transform: translateY(0);
    }
</style>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0; font-size: 1.5rem;">Edit Category</h3>
        <form method="POST">
            <input type="hidden" name="cat_id" id="edit_cat_id">

            <div class="form-group">
                <label class="form-label">Category Name</label>
                <input type="text" name="cat_name" id="edit_cat_name" required class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="cat_description" id="edit_cat_description" rows="3" class="form-input"></textarea>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="button" onclick="closeEditCategory()" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #aaa;">Cancel</button>
                <button type="submit" name="edit_category" class="btn" style="flex: 1;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Tag Modal -->
<div id="editTagModal" class="modal-overlay">
    <div class="modal-content">
        <h3 style="color: var(--accent-color); margin: 0 0 20px 0; font-size: 1.5rem;">Edit Tag</h3>
        <form method="POST">
            <input type="hidden" name="tag_id" id="edit_tag_id">

            <div class="form-group">
                <label class="form-label">Tag Name</label>
                <input type="text" name="tag_name" id="edit_tag_name" required class="form-input">
            </div>

            <div style="display: flex; gap: 15px; margin-top: 25px;">
                <button type="button" onclick="closeEditTag()" class="btn"
                    style="flex: 1; background: transparent; border-color: #666; color: #aaa;">Cancel</button>
                <button type="submit" name="edit_tag" class="btn" style="flex: 1;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editCategory(id, name, description) {
        document.getElementById('edit_cat_id').value = id;
        document.getElementById('edit_cat_name').value = name;
        document.getElementById('edit_cat_description').value = description;

        const modal = document.getElementById('editCategoryModal');
        modal.classList.add('active');
    }

    function closeEditCategory() {
        document.getElementById('editCategoryModal').classList.remove('active');
    }

    function editTag(id, name) {
        document.getElementById('edit_tag_id').value = id;
        document.getElementById('edit_tag_name').value = name;

        const modal = document.getElementById('editTagModal');
        modal.classList.add('active');
    }

    function closeEditTag() {
        document.getElementById('editTagModal').classList.remove('active');
    }

    // Close modals on outside click
    window.onclick = function (event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.classList.remove('active');
        }
    }
</script>

</div> <!-- End Section -->
</body>

</html>

