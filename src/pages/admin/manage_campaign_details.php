<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/admin_log.php';

if (!isAdmin()) {
    redirect('/');
}

$message = '';
$error = '';

$campaign_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$campaign_id) {
    redirect('manage_campaigns.php');
}

// Fetch Campaign Info
try {
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        redirect('manage_campaigns.php');
    }
} catch (Exception $e) {
    $error = "DB Error: " . $e->getMessage();
}

// --- HANDLE ACTIONS ---

// Create Chapter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_chapter'])) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $sort_order = (int) $_POST['sort_order'];

    if (empty($title)) {
        $error = "Chapter title is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO campaign_chapters (campaign_id, title, description, event_date, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$campaign_id, $title, $description, $event_date, $sort_order]);
            $message = "Chapter added successfully.";
            logAdminAction($pdo, 'create_campaign_chapter', 'campaign_chapters', $pdo->lastInsertId(), ['title' => $title]);
        } catch (Exception $e) {
            $error = "Error adding chapter: " . $e->getMessage();
        }
    }
}

// Update Chapter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_chapter'])) {
    $chapter_id = (int) $_POST['chapter_id'];
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
    $sort_order = (int) $_POST['sort_order'];

    if (empty($title)) {
        $error = "Chapter title is required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE campaign_chapters SET title = ?, description = ?, event_date = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$title, $description, $event_date, $sort_order, $chapter_id]);
            $message = "Chapter updated successfully.";
            logAdminAction($pdo, 'update_campaign_chapter', 'campaign_chapters', $chapter_id, ['title' => $title]);
        } catch (Exception $e) {
            $error = "Error updating chapter: " . $e->getMessage();
        }
    }
}

// Delete Chapter
if (isset($_POST['delete_chapter'])) {
    $chapter_id = (int) $_POST['chapter_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM campaign_chapters WHERE id = ?");
        $stmt->execute([$chapter_id]);
        $message = "Chapter deleted successfully.";
        logAdminAction($pdo, 'delete_campaign_chapter', 'campaign_chapters', $chapter_id, []);
    } catch (Exception $e) {
        $error = "Error deleting chapter: " . $e->getMessage();
    }
}

// Create Doc
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_doc'])) {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $doc_type = sanitize($_POST['doc_type']);

    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO campaign_docs (campaign_id, title, content, doc_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$campaign_id, $title, $content, $doc_type]);
            $message = "Document added successfully.";
            logAdminAction($pdo, 'create_campaign_doc', 'campaign_docs', $pdo->lastInsertId(), ['title' => $title]);
        } catch (Exception $e) {
            $error = "Error adding document: " . $e->getMessage();
        }
    }
}

// Update Doc
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doc'])) {
    $doc_id = (int) $_POST['doc_id'];
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $doc_type = sanitize($_POST['doc_type']);

    if (empty($title) || empty($content)) {
        $error = "Title and Content are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE campaign_docs SET title = ?, content = ?, doc_type = ? WHERE id = ?");
            $stmt->execute([$title, $content, $doc_type, $doc_id]);
            $message = "Document updated successfully.";
            logAdminAction($pdo, 'update_campaign_doc', 'campaign_docs', $doc_id, ['title' => $title]);
        } catch (Exception $e) {
            $error = "Error updating document: " . $e->getMessage();
        }
    }
}

// Delete Doc
if (isset($_POST['delete_doc'])) {
    $doc_id = (int) $_POST['doc_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM campaign_docs WHERE id = ?");
        $stmt->execute([$doc_id]);
        $message = "Document deleted successfully.";
        logAdminAction($pdo, 'delete_campaign_doc', 'campaign_docs', $doc_id, []);
    } catch (Exception $e) {
        $error = "Error deleting document: " . $e->getMessage();
    }
}

// --- FETCH DATA FOR RENDER ---
try {
    $stmt = $pdo->prepare("SELECT * FROM campaign_chapters WHERE campaign_id = ? ORDER BY sort_order ASC, event_date ASC");
    $stmt->execute([$campaign_id]);
    $chapters = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM campaign_docs WHERE campaign_id = ? ORDER BY created_at DESC");
    $stmt->execute([$campaign_id]);
    $docs = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "DB Error Fetching Data: " . $e->getMessage();
}

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<!-- Flatpickr for Date -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    .split-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 30px;
    }

    @media (max-width: 900px) {
        .split-layout {
            grid-template-columns: 1fr;
        }
    }

    .manage-panel {
        background: rgba(15, 15, 15, 0.7);
        border: 1px solid rgba(197, 160, 89, 0.2);
        border-radius: 12px;
        padding: 20px;
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding-bottom: 15px;
    }

    .panel-title {
        color: #c5a059;
        font-family: 'Teko', sans-serif;
        font-size: 1.5rem;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .item-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .list-item {
        background: rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: transform 0.2s;
    }

    .list-item:hover {
        transform: translateY(-2px);
        border-color: rgba(197, 160, 89, 0.3);
    }

    .item-info h4 {
        margin: 0 0 5px 0;
        color: #fff;
        font-size: 1.1rem;
    }

    .item-meta {
        color: #aaa;
        font-size: 0.85rem;
        margin: 0;
    }

    .item-actions {
        display: flex;
        gap: 8px;
    }

    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        background: rgba(197, 160, 89, 0.2);
        color: #eecfa1;
    }

    /* Reused Modal Styles */
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
        max-height: 70vh;
        overflow-y: auto;
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
        font-family: 'Inter', sans-serif;
    }

    .staff-form-control:focus {
        outline: none;
        border-color: #c5a059;
    }
</style>

<div class="dashboard-container">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <a href="manage_campaigns.php"
                style="color: #aaa; text-decoration: none; font-size: 0.9rem; margin-bottom: 10px; display: inline-block;">
                <i class="fas fa-arrow-left"></i> Back to Campaigns
            </a>
            <h1 style="color: #c5a059; font-family: 'Teko', sans-serif; font-size: 2rem; margin: 0;"><i
                    class="fas fa-cogs"></i> Manage:
                <?php echo htmlspecialchars($campaign['title']); ?>
            </h1>
            <p style="color: #aaa; margin: 0;">Add chapters/events and intel documentation.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <script>Swal.fire({ title: 'Success!', text: '<?php echo addslashes($message); ?>', icon: 'success', background: '#151515', color: '#fff' });</script>
    <?php endif; ?>
    <?php if ($error): ?>
        <script>Swal.fire({ title: 'Error!', text: '<?php echo addslashes($error); ?>', icon: 'error', background: '#151515', color: '#fff' });</script>
    <?php endif; ?>

    <div class="split-layout">

        <!-- CHAPTERS PANEL -->
        <div class="manage-panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="fas fa-calendar-alt"></i> Chapters / Events</h2>
                <button onclick="openModal('createChapterModal')" class="btn-primary"
                    style="padding: 6px 12px; font-size: 0.85rem;">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>

            <div class="item-list">
                <?php if (empty($chapters)): ?>
                    <p style="color: #666; text-align: center; margin: 20px 0;">No chapters added yet.</p>
                <?php else: ?>
                    <?php foreach ($chapters as $chap): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <h4>
                                    <?php echo htmlspecialchars($chap['title']); ?>
                                </h4>
                                <p class="item-meta">
                                    <i class="far fa-clock"></i>
                                    <?php echo !empty($chap['event_date']) ? date('M j, Y H:i', strtotime($chap['event_date'])) : 'TBA'; ?>
                                    | Order:
                                    <?php echo $chap['sort_order']; ?>
                                </p>
                            </div>
                            <div class="item-actions">
                                <button onclick='openEditChapterModal(<?php echo json_encode($chap); ?>)' class="btn-secondary"
                                    style="padding: 6px;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Delete this chapter?');" style="margin:0;">
                                    <input type="hidden" name="chapter_id" value="<?php echo $chap['id']; ?>">
                                    <button type="submit" name="delete_chapter" class="btn-secondary"
                                        style="padding: 6px; color: #ff5252;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- DOCS PANEL -->
        <div class="manage-panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="fas fa-folder-open"></i> Intel & Docs</h2>
                <button onclick="openModal('createDocModal')" class="btn-primary"
                    style="padding: 6px 12px; font-size: 0.85rem;">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>

            <div class="item-list">
                <?php if (empty($docs)): ?>
                    <p style="color: #666; text-align: center; margin: 20px 0;">No documents added yet.</p>
                <?php else: ?>
                    <?php foreach ($docs as $doc): ?>
                        <div class="list-item">
                            <div class="item-info">
                                <h4>
                                    <?php echo htmlspecialchars($doc['title']); ?>
                                </h4>
                                <p class="item-meta">
                                    <span class="badge">
                                        <?php echo htmlspecialchars($doc['doc_type']); ?>
                                    </span>
                                    Added:
                                    <?php echo date('M j, Y', strtotime($doc['created_at'])); ?>
                                </p>
                            </div>
                            <div class="item-actions">
                                <button onclick='openEditDocModal(<?php echo json_encode($doc); ?>)' class="btn-secondary"
                                    style="padding: 6px;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Delete this document?');" style="margin:0;">
                                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" name="delete_doc" class="btn-secondary"
                                        style="padding: 6px; color: #ff5252;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- CAPHTER MODALS -->
<div id="createChapterModal" class="staff-modal-overlay">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2>Add Chapter</h2>
            <span class="staff-modal-close" onclick="closeModal('createChapterModal')">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form method="POST">
                <input type="hidden" name="create_chapter" value="1">
                <div class="staff-form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="staff-form-control" required
                        placeholder="e.g. Chapter I: Infiltration">
                </div>
                <div class="staff-form-group">
                    <label>Event Date & Time</label>
                    <input type="text" name="event_date" class="staff-form-control datetime-picker"
                        placeholder="Select date and time">
                </div>
                <div class="staff-form-group">
                    <label>Description</label>
                    <textarea name="description" class="staff-form-control" rows="4"></textarea>
                </div>
                <div class="staff-form-group">
                    <label>Sort Order (1, 2, 3...)</label>
                    <input type="number" name="sort_order" class="staff-form-control" value="0">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">Add
                    Chapter</button>
            </form>
        </div>
    </div>
</div>

<div id="editChapterModal" class="staff-modal-overlay">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2>Edit Chapter</h2>
            <span class="staff-modal-close" onclick="closeModal('editChapterModal')">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form method="POST">
                <input type="hidden" name="update_chapter" value="1">
                <input type="hidden" name="chapter_id" id="edit_chapter_id">
                <div class="staff-form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="edit_chapter_title" class="staff-form-control" required>
                </div>
                <div class="staff-form-group">
                    <label>Event Date & Time</label>
                    <input type="text" name="event_date" id="edit_chapter_date"
                        class="staff-form-control datetime-picker">
                </div>
                <div class="staff-form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_chapter_desc" class="staff-form-control" rows="4"></textarea>
                </div>
                <div class="staff-form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="edit_chapter_order" class="staff-form-control">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">Save
                    Changes</button>
            </form>
        </div>
    </div>
</div>

<!-- DOC MODALS -->
<div id="createDocModal" class="staff-modal-overlay">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2>Add Document</h2>
            <span class="staff-modal-close" onclick="closeModal('createDocModal')">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form method="POST">
                <input type="hidden" name="create_doc" value="1">
                <div class="staff-form-group">
                    <label>Document Title *</label>
                    <input type="text" name="title" class="staff-form-control" required
                        placeholder="e.g. Area Map Intel">
                </div>
                <div class="staff-form-group">
                    <label>Type</label>
                    <select name="doc_type" class="staff-form-control">
                        <option value="Intel">Intel</option>
                        <option value="Briefing">Briefing</option>
                        <option value="After Action">After Action</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="staff-form-group">
                    <label>Content / Body *</label>
                    <textarea name="content" class="staff-form-control" rows="8" required
                        placeholder="Enter intel details, links, or text here..."></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">Add
                    Document</button>
            </form>
        </div>
    </div>
</div>

<div id="editDocModal" class="staff-modal-overlay">
    <div class="staff-modal-content">
        <div class="staff-modal-header">
            <h2>Edit Document</h2>
            <span class="staff-modal-close" onclick="closeModal('editDocModal')">&times;</span>
        </div>
        <div class="staff-modal-body">
            <form method="POST">
                <input type="hidden" name="update_doc" value="1">
                <input type="hidden" name="doc_id" id="edit_doc_id">
                <div class="staff-form-group">
                    <label>Document Title *</label>
                    <input type="text" name="title" id="edit_doc_title" class="staff-form-control" required>
                </div>
                <div class="staff-form-group">
                    <label>Type</label>
                    <select name="doc_type" id="edit_doc_type" class="staff-form-control">
                        <option value="Intel">Intel</option>
                        <option value="Briefing">Briefing</option>
                        <option value="After Action">After Action</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="staff-form-group">
                    <label>Content / Body *</label>
                    <textarea name="content" id="edit_doc_content" class="staff-form-control" rows="8"
                        required></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 12px;">Save
                    Changes</button>
            </form>
        </div>
    </div>
</div>

<script>
    flatpickr(".datetime-picker", {
        enableTime: true,
        dateFormat: "Y-m-d H:i:S",
        time_24hr: true
    });

    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    function openEditChapterModal(chapter) {
        document.getElementById('edit_chapter_id').value = chapter.id;
        document.getElementById('edit_chapter_title').value = chapter.title;
        document.getElementById('edit_chapter_date').value = chapter.event_date || '';
        document.getElementById('edit_chapter_desc').value = chapter.description || '';
        document.getElementById('edit_chapter_order').value = chapter.sort_order;

        // Update flatpickr instance if exists
        const fp = document.getElementById('edit_chapter_date')._flatpickr;
        if (fp && chapter.event_date) fp.setDate(chapter.event_date);

        openModal('editChapterModal');
    }

    function openEditDocModal(doc) {
        document.getElementById('edit_doc_id').value = doc.id;
        document.getElementById('edit_doc_title').value = doc.title;
        document.getElementById('edit_doc_type').value = doc.doc_type;
        document.getElementById('edit_doc_content').value = doc.content || '';
        openModal('editDocModal');
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