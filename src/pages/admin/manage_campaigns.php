<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/admin_log.php';

if (!isAdmin()) {
    redirect('/');
}

$message = '';
$error = '';

// Helper to upload image
function uploadCampaignImage($file)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = ROOT_PATH . '/assets/uploads/campaigns/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('campaign_') . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'assets/uploads/campaigns/' . $filename;
    } else {
        throw new Exception("Failed to save uploaded file.");
    }
}

// Create Campaign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $status = sanitize($_POST['status']);
    $author_id = $_SESSION['user']['id'];
    $image_path = null;

    if (empty($title)) {
        $error = "Title is required.";
    } else {
        try {
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_path = uploadCampaignImage($_FILES['image']);
            }

            $stmt = $pdo->prepare("INSERT INTO campaigns (title, description, image_path, status, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $image_path, $status, $author_id]);
            $message = "Campaign created successfully.";
            logAdminAction($pdo, 'create_campaign', 'campaigns', $pdo->lastInsertId(), ['title' => $title]);
        } catch (Exception $e) {
            $error = "Error creating campaign: " . $e->getMessage();
        }
    }
}

// Update Campaign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_campaign'])) {
    $id = (int) $_POST['campaign_id'];
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $status = sanitize($_POST['status']);

    if (empty($title)) {
        $error = "Title is required.";
    } else {
        try {
            $image_sql = "";
            $params = [$title, $description, $status];

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_path = uploadCampaignImage($_FILES['image']);
                $image_sql = ", image_path = ?";
                $params[] = $image_path;
            }

            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE campaigns SET title = ?, description = ?, status = ? $image_sql WHERE id = ?");
            $stmt->execute($params);

            $message = "Campaign updated successfully.";
            logAdminAction($pdo, 'update_campaign', 'campaigns', $id, ['title' => $title]);
        } catch (Exception $e) {
            $error = "Error updating campaign: " . $e->getMessage();
        }
    }
}

// Delete Campaign
if (isset($_POST['delete_campaign'])) {
    $id = (int) $_POST['campaign_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Campaign deleted successfully.";
        logAdminAction($pdo, 'delete_campaign', 'campaigns', $id, []);
    } catch (PDOException $e) {
        $error = "Error deleting campaign: " . $e->getMessage();
    }
}

// Fetch Campaigns
try {
    $stmt = $pdo->query("SELECT c.*, u.personaname FROM campaigns c LEFT JOIN users u ON c.created_by = u.id ORDER BY c.created_at DESC");
    $campaigns = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    $campaigns = [];
}

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<style>
    .campaign-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .campaign-card {
        background: rgba(15, 15, 15, 0.7);
        border: 1px solid rgba(197, 160, 89, 0.2);
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.3s ease, border-color 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .campaign-card:hover {
        transform: translateY(-5px);
        border-color: rgba(197, 160, 89, 0.5);
    }

    .campaign-image {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: rgba(0, 0, 0, 0.5);
        border-bottom: 2px solid rgba(197, 160, 89, 0.3);
    }

    .campaign-content {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .campaign-title {
        color: #c5a059;
        font-size: 1.3rem;
        margin: 0 0 10px 0;
        font-family: 'Teko', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .campaign-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 15px;
    }

    .status-active {
        background: rgba(197, 160, 89, 0.2);
        color: #eecfa1;
        border: 1px solid rgba(197, 160, 89, 0.4);
    }

    .status-completed {
        background: rgba(100, 100, 100, 0.2);
        color: #aaa;
        border: 1px solid rgba(100, 100, 100, 0.4);
    }

    .campaign-desc {
        color: #aaa;
        font-size: 0.9rem;
        margin: 0 0 20px 0;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .campaign-actions {
        display: flex;
        gap: 10px;
        margin-top: auto;
    }

    .btn-manage {
        flex-grow: 1;
        text-align: center;
        text-decoration: none;
        padding: 8px;
        background: var(--accent-color);
        color: #000;
        border-radius: 4px;
        font-weight: bold;
        transition: opacity 0.3s;
    }

    .btn-manage:hover {
        opacity: 0.9;
    }

    /* Staff Modal Styles Reused */
    .staff-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(5px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .staff-modal-content {
        background: rgba(15, 15, 15, 0.7);
        backdrop-filter: blur(25px);
        border: 1px solid rgba(197, 160, 89, 0.2);
        width: 90%;
        max-width: 500px;
        border-radius: 16px;
        position: relative;
        overflow: hidden;
    }

    .staff-modal-header {
        background: rgba(0, 0, 0, 0.3);
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .staff-modal-header h2 {
        margin: 0;
        color: #c5a059;
        font-size: 1.2rem;
        font-family: "Teko", sans-serif;
        text-transform: uppercase;
    }

    .staff-modal-close {
        color: #aaa;
        cursor: pointer;
        transition: color 0.3s;
    }

    .staff-modal-close:hover {
        color: #fff;
    }

    .staff-modal-body {
        padding: 25px;
    }

    .staff-form-group {
        margin-bottom: 15px;
    }

    .staff-form-group label {
        display: block;
        color: #c5a059;
        font-size: 0.9rem;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .staff-form-control {
        width: 100%;
        padding: 10px;
        background: rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        border-radius: 4px;
        box-sizing: border-box;
    }

    .staff-form-control:focus {
        outline: none;
        border-color: #c5a059;
    }
</style>

<div class="dashboard-container">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="color: #c5a059; font-family: 'Teko', sans-serif; font-size: 2rem; margin: 0;"><i
                    class="fas fa-globe"></i> Campaigns Manager</h1>
            <p style="color: #aaa; margin: 0;">Manage operations and campaigns.</p>
        </div>
        <button onclick="openModal('createModal')"
            style="background: transparent; color: #fff; border: 1px solid #c5a059; padding: 5px 15px; cursor: pointer; border-radius: 4px; transition: all 0.3s;"
            onmouseover="this.style.background='#c5a059'; this.style.color='#000';"
            onmouseout="this.style.background='transparent'; this.style.color='#fff';">
            + New Campaign
        </button>
    </div>

    <?php if ($message): ?>
        <script>Swal.fire({ title: 'Success!', text: '<?php echo addslashes($message); ?>', icon: 'success', background: '#151515', color: '#fff' });</script>
    <?php endif; ?>
    <?php if ($error): ?>
        <script>Swal.fire({ title: 'Error!', text: '<?php echo addslashes($error); ?>', icon: 'error', background: '#151515', color: '#fff' });</script>
    <?php endif; ?>

    <div class="campaign-grid">
        <?php if (empty($campaigns)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #aaa;">
                <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No campaigns found. Create one to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($campaigns as $camp): ?>
                <div class="campaign-card">
                    <img src="../<?php echo !empty($camp['image_path']) ? htmlspecialchars($camp['image_path']) : 'assets/images/placeholder.jpg'; ?>"
                        class="campaign-image" alt="Campaign Image">
                    <div class="campaign-content">
                        <h3 class="campaign-title">
                            <?php echo htmlspecialchars($camp['title']); ?>
                        </h3>
                        <span class="campaign-status status-<?php echo strtolower($camp['status']); ?>">
                            <?php echo $camp['status']; ?>
                        </span>
                        <p class="campaign-desc">
                            <?php echo nl2br(htmlspecialchars($camp['description'])); ?>
                        </p>

                        <div class="campaign-actions">
                            <a href="manage_campaign_details.php?id=<?php echo $camp['id']; ?>" class="btn-manage">
                                <i class="fas fa-cogs"></i> Manage Assets
                            </a>
                            <button onclick='openEditModal(<?php echo json_encode($camp); ?>)' class="btn-secondary"
                                style="padding: 8px;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST"
                                onsubmit="return confirm('Are you sure you want to delete this campaign? All chapters and docs will be lost.');"
                                style="margin:0;">
                                <input type="hidden" name="campaign_id" value="<?php echo $camp['id']; ?>">
                                <button type="submit" name="delete_campaign" class="btn-secondary"
                                    style="padding: 8px; color: #ff5252; border-color: rgba(255,82,82,0.3);">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="staff-modal-overlay">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2><i class="fas fa-plus-circle"></i> Create Campaign</h2>
            <span class="staff-modal-close" onclick="closeModal('createModal')">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="create_campaign" value="1">

                <div class="staff-form-group">
                    <label>Campaign Title *</label>
                    <input type="text" name="title" class="staff-form-control" required
                        placeholder="e.g. Operation Salamander">
                </div>

                <div class="staff-form-group">
                    <label>Description</label>
                    <textarea name="description" class="staff-form-control" rows="4"
                        placeholder="Campaign story or details..."></textarea>
                </div>

                <div class="staff-form-group">
                    <label>Cover Image (Optional)</label>
                    <input type="file" name="image" class="staff-form-control" accept="image/*">
                </div>

                <div class="staff-form-group">
                    <label>Status</label>
                    <select name="status" class="staff-form-control">
                        <option value="Active">Active</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">
                    Create Campaign
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="staff-modal-overlay">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2><i class="fas fa-edit"></i> Edit Campaign</h2>
            <span class="staff-modal-close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_campaign" value="1">
                <input type="hidden" name="campaign_id" id="edit_campaign_id">

                <div class="staff-form-group">
                    <label>Campaign Title *</label>
                    <input type="text" name="title" id="edit_title" class="staff-form-control" required>
                </div>

                <div class="staff-form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" class="staff-form-control" rows="4"></textarea>
                </div>

                <div class="staff-form-group">
                    <label>Cover Image (Leave empty to keep current)</label>
                    <input type="file" name="image" class="staff-form-control" accept="image/*">
                </div>

                <div class="staff-form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="staff-form-control">
                        <option value="Active">Active</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">
                    Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    function openEditModal(campaign) {
        document.getElementById('edit_campaign_id').value = campaign.id;
        document.getElementById('edit_title').value = campaign.title;
        document.getElementById('edit_description').value = campaign.description || '';
        document.getElementById('edit_status').value = campaign.status;
        openModal('editModal');
    }

    // Close modals when clicking outside
    window.onclick = function (event) {
        if (event.target.classList.contains('staff-modal-overlay')) {
            event.target.style.display = "none";
        }
    }
</script>

</body>

</html>