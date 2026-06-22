<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    redirect('campaigns.php');
}

try {
    // Campaign Info
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
    $stmt->execute([$id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        redirect('campaigns.php');
    }

    trackPageView($pdo, 'Campaign: ' . $campaign['title']);

    // Chapters
    $stmt = $pdo->prepare("SELECT * FROM campaign_chapters WHERE campaign_id = ? ORDER BY sort_order ASC, event_date ASC");
    $stmt->execute([$id]);
    $chapters = $stmt->fetchAll();

    // Docs
    $stmt = $pdo->prepare("SELECT * FROM campaign_docs WHERE campaign_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    $docs = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error loading campaign details.");
}

include ROOT_PATH . '/includes/header.php';
?>

<style>
    .campaign-detail-container {
        max-width: 1400px;
        margin: 40px auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 30px;
    }

    @media(max-width: 900px) {
        .campaign-detail-container {
            grid-template-columns: 1fr;
        }
    }

    /* Panel Styles */
    .detail-panel {
        background: rgba(20, 20, 20, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 8px;
        overflow: hidden;
    }

    .panel-header {
        background: rgba(0, 0, 0, 0.3);
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        font-size: 1.1rem;
        color: #fff;
    }

    .panel-body {
        padding: 20px;
    }

    /* Left Panel: Info */
    .info-image-container {
        width: 100%;
        max-width: 250px;
        margin: 0 auto 20px;
        display: block;
    }

    .info-image {
        width: 100%;
        border-radius: 8px;
        border: 1px solid rgba(197, 160, 89, 0.3);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
    }

    .info-title {
        text-align: center;
        font-size: 1.5rem;
        color: #fff;
        font-weight: 400;
        margin: 10px 0 15px;
    }

    .status-bar {
        background: #f1b332;
        /* Yellowish warning color for active like in screenshot */
        color: #000;
        text-align: center;
        font-weight: 700;
        text-transform: uppercase;
        padding: 8px;
        border-radius: 4px;
        margin-bottom: 20px;
        letter-spacing: 1px;
    }

    .status-bar.completed {
        background: #555;
        color: #ccc;
    }

    .info-desc {
        color: #aaa;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    /* Right Panel: Events & Docs Lists */
    .list-container {
        display: flex;
        flex-direction: column;
    }

    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
    }

    .list-item:hover {
        background: rgba(255, 255, 255, 0.02);
    }

    .list-item:last-child {
        border-bottom: none;
    }

    .item-left {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .item-title {
        color: #fff;
        font-weight: 600;
        font-size: 1.1rem;
        margin: 0;
    }

    .item-date {
        color: #aaa;
        font-size: 0.85rem;
    }

    .see-more-btn {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 6px 15px;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }

    .see-more-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #c5a059;
        border-color: #c5a059;
    }

    /* Page Title */
    .page-main-header {
        max-width: 1400px;
        margin: 40px auto 0;
        padding: 0 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        color: #fff;
    }

    .page-main-header h1 {
        margin: 0;
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        font-size: 1.8rem;
    }

    /* Modal styles */
    .doc-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(5px);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .doc-modal-content {
        background: #151515;
        border: 1px solid rgba(197, 160, 89, 0.3);
        width: 90%;
        max-width: 700px;
        max-height: 80vh;
        border-radius: 8px;
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .doc-modal-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .doc-modal-header h2 {
        margin: 0;
        color: #c5a059;
        font-size: 1.4rem;
        font-family: "Teko", sans-serif;
        text-transform: uppercase;
    }

    .doc-modal-close {
        color: #aaa;
        cursor: pointer;
        font-size: 1.5rem;
        transition: color 0.3s;
    }

    .doc-modal-close:hover {
        color: #fff;
    }

    .doc-modal-body {
        padding: 25px;
        overflow-y: auto;
        color: #ccc;
        line-height: 1.6;
    }
</style>

<div class="page-main-header">
    <i class="far fa-calendar-alt" style="font-size: 1.5rem; color: #4b8b8b;"></i>
    <h1>
        <?php echo htmlspecialchars($campaign['title']); ?>
    </h1>
</div>

<div class="campaign-detail-container">

    <!-- LEFT PANEL: Information -->
    <div class="detail-panel" style="align-self: start;">
        <div class="panel-header">Information</div>
        <div class="panel-body" style="display:flex; flex-direction: column;">

            <div class="info-image-container">
                <img src="<?php echo !empty($campaign['image_path']) ? htmlspecialchars($campaign['image_path']) : 'assets/images/placeholder.jpg'; ?>"
                    class="info-image" alt="Campaign Logo">
            </div>

            <h2 class="info-title">
                <?php echo htmlspecialchars($campaign['title']); ?>
            </h2>

            <div class="status-bar <?php echo strtolower($campaign['status']); ?>">
                <?php echo $campaign['status']; ?>
            </div>

            <div class="info-desc">
                <?php echo nl2br(htmlspecialchars($campaign['description'])); ?>
            </div>
        </div>
    </div>

    <!-- RIGHT PANELS -->
    <div style="display:flex; flex-direction:column; gap: 30px;">

        <!-- Events (Chapters) -->
        <div class="detail-panel">
            <div class="panel-header">Events</div>
            <div class="list-container">
                <?php if (empty($chapters)): ?>
                    <div style="padding: 20px; color: #666; text-align:center;">No chapters scheduled yet.</div>
                <?php else: ?>
                    <?php foreach ($chapters as $chap): ?>
                        <div class="list-item">
                            <div class="item-left">
                                <h3 class="item-title">
                                    <?php echo htmlspecialchars($chap['title']); ?>
                                </h3>
                                <div class="item-date">
                                    <?php echo !empty($chap['event_date']) ? date('d/m/Y, H:i', strtotime($chap['event_date'])) : 'TBA'; ?>
                                </div>
                            </div>
                            <button class="see-more-btn" onclick='showChapterModal(<?php echo json_encode($chap); ?>)'>
                                <i class="fas fa-search"></i> See More
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Intel / Docs -->
        <div class="detail-panel">
            <div class="panel-header">Intel Directory</div>
            <div class="list-container">
                <?php if (empty($docs)): ?>
                    <div style="padding: 20px; color: #666; text-align:center;">No documents available.</div>
                <?php else: ?>
                    <?php foreach ($docs as $doc): ?>
                        <div class="list-item">
                            <div class="item-left">
                                <h3 class="item-title">
                                    <?php echo htmlspecialchars($doc['title']); ?> <span
                                        style="font-size: 0.75rem; background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:3px; margin-left: 10px; color:#aaa; font-weight:normal;">
                                        <?php echo htmlspecialchars($doc['doc_type']); ?>
                                    </span>
                                </h3>
                                <div class="item-date">Published:
                                    <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?>
                                </div>
                            </div>
                            <button class="see-more-btn" onclick='showDocModal(<?php echo json_encode($doc); ?>)'>
                                <i class="fas fa-file-alt"></i> Read
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- Reading Modal -->
<div id="readModal" class="doc-modal-overlay">
    <div class="doc-modal-content">
        <div class="doc-modal-header">
            <h2 id="modalTitle">Title</h2>
            <span class="doc-modal-close" onclick="closeDocModal()">&times;</span>
        </div>
        <div class="doc-modal-body" id="modalBody">
            Content goes here...
        </div>
    </div>
</div>

<script>
    function showChapterModal(chapter) {
        document.getElementById('modalTitle').innerText = chapter.title;
        let content = chapter.description || 'No additional information available for this chapter.';
        // Convert line breaks to HTML for display
        document.getElementById('modalBody').innerHTML = content.replace(/\n/g, '<br>');
        document.getElementById('readModal').style.display = 'flex';
    }

    function showDocModal(doc) {
        document.getElementById('modalTitle').innerText = doc.title;
        let content = doc.content || 'Document content is empty.';
        document.getElementById('modalBody').innerHTML = content.replace(/\n/g, '<br>');
        document.getElementById('readModal').style.display = 'flex';
    }

    function closeDocModal() {
        document.getElementById('readModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        if (event.target.classList.contains('doc-modal-overlay')) {
            event.target.style.display = "none";
        }
    }
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
</body>

</html>
