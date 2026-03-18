<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    redirect('../login');
}

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM media_categories ORDER BY name");
$categories = $stmt->fetchAll();

// Fetch Tags
$stmt = $pdo->query("SELECT * FROM media_tags ORDER BY name");
$tags = $stmt->fetchAll();

// Fetch Albums
$stmt = $pdo->query("SELECT * FROM media_albums ORDER BY created_at DESC");
$albums = $stmt->fetchAll();

// Handle Filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$filter_tag = isset($_GET['tag']) ? (int) $_GET['tag'] : 0;
$filter_album = isset($_GET['album']) ? (int) $_GET['album'] : 0;

// Build Query
$params = [];

if ($filter_album > 0) {
    // VIEWING SPECIFIC ALBUM: Fetch only media inside this album
    $sql = "
        SELECT 
            'media' as type,
            m.id, 
            m.title, 
            m.description, 
            m.filename, 
            m.is_video,
            m.video_platform,
            m.video_id,
            m.video_url,
            c.name as category_name,
            m.uploaded_at as sort_date,
            1 as item_count,
            a.title as album_title
        FROM media_gallery m
        LEFT JOIN media_categories c ON m.category_id = c.id
        LEFT JOIN media_albums a ON m.album_id = a.id
        WHERE m.album_id = ?
    ";
    $params[] = $filter_album;

    if ($search) {
        $sql .= " AND (m.title LIKE ? OR m.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter_category > 0) {
        $sql .= " AND m.category_id = ?";
        $params[] = $filter_category;
    }

    if ($filter_tag > 0) {
        $sql .= " AND EXISTS (SELECT 1 FROM media_gallery_tags mgt WHERE mgt.media_id = m.id AND mgt.tag_id = ?)";
        $params[] = $filter_tag;
    }

    $sql .= " ORDER BY m.uploaded_at DESC";

} else {
    // ROOT VIEW: Fetch Albums + Standalone Media
    $sql = "
        SELECT 
            'album' as type,
            a.id, 
            a.title, 
            a.description, 
            m.filename, -- Cover image
            0 as is_video,
            NULL as video_platform,
            NULL as video_id,
            NULL as video_url,
            c.name as category_name,
            a.created_at as sort_date,
            (SELECT COUNT(*) FROM media_gallery WHERE album_id = a.id) as item_count,
            NULL as album_title
        FROM media_albums a
        LEFT JOIN media_categories c ON a.category_id = c.id
        LEFT JOIN media_gallery m ON a.cover_image_id = m.id
        WHERE 1=1
    ";

    // Apply filters to Albums
    if ($search) {
        $sql .= " AND (a.title LIKE ? OR a.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter_category > 0) {
        $sql .= " AND a.category_id = ?";
        $params[] = $filter_category;
    }

    // Union with Standalone Media
    $sql .= " UNION ALL SELECT 
            'media' as type,
            m.id, 
            m.title, 
            m.description, 
            m.filename, 
            m.is_video,
            m.video_platform,
            m.video_id,
            m.video_url,
            c.name as category_name,
            m.uploaded_at as sort_date,
            1 as item_count,
            NULL as album_title
        FROM media_gallery m
        LEFT JOIN media_categories c ON m.category_id = c.id
        WHERE m.album_id IS NULL 
    ";

    // Apply filters to Standalone Media
    if ($search) {
        $sql .= " AND (m.title LIKE ? OR m.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter_category > 0) {
        $sql .= " AND m.category_id = ?";
        $params[] = $filter_category;
    }

    if ($filter_tag > 0) {
        $sql .= " AND EXISTS (SELECT 1 FROM media_gallery_tags mgt WHERE mgt.media_id = m.id AND mgt.tag_id = ?)";
        $params[] = $filter_tag;
    }

    // Global Order
    $sql .= " ORDER BY sort_date DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$media_items = $stmt->fetchAll();

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<div class="dashboard-container">
    <div style="max-width: 1600px; margin: 0 auto; margin-bottom: 30px;">
        <div>
            <h2 style="color: var(--accent-color); margin: 0 0 10px 0;">
                <i class="fas fa-photo-video"></i> Media Management
            </h2>
            <p style="color: #aaa; margin: 0;">Upload and manage gallery images</p>
        </div>
    </div>

    <?php include ROOT_PATH . '/admin/includes/media_tab_content.php'; ?>
</div>

</body>

</html>

