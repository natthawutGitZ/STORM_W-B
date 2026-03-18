<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;
    $albumFilter = isset($_GET['album_filter']) ? $_GET['album_filter'] : '';

    // Build WHERE clauses
    $whereAlbum = [];
    $whereMedia = [];
    $params = [];

    // Search filter
    if ($search) {
        $searchPattern = "%$search%";
        $whereAlbum[] = "(a.title LIKE ? OR a.description LIKE ?)";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
    }

    // Category filter
    if ($categoryId) {
        $whereAlbum[] = "a.category_id = ?";
        $params[] = $categoryId;
    }

    // Build album query
    $albumWhere = !empty($whereAlbum) ? 'AND ' . implode(' AND ', $whereAlbum) : '';

    // Album filter logic
    $includeAlbums = ($albumFilter === '' || $albumFilter === 'no-album') ? true : false;
    $includeStandalone = ($albumFilter === '' || $albumFilter === 'no-album') ? true : false;
    $specificAlbum = ($albumFilter !== '' && $albumFilter !== 'no-album') ? (int) $albumFilter : null;

    $sql = "";
    $allParams = [];

    // Include albums in results
    if ($includeAlbums && !$specificAlbum) {
        $sql .= "
            SELECT 
                'album' as item_type,
                a.id, 
                a.title, 
                a.description, 
                m.filename, 
                a.created_at as sort_date,
                (SELECT COUNT(*) FROM media_gallery WHERE album_id = a.id) as item_count,
                0 as is_video
            FROM media_albums a
            LEFT JOIN media_gallery m ON a.cover_image_id = m.id
            WHERE a.status = 'published' $albumWhere
        ";
        $allParams = array_merge($allParams, $params);
    }

    // Include standalone media
    if ($includeStandalone) {
        if ($sql !== "")
            $sql .= " UNION ALL ";

        $mediaWhere = [];
        $mediaParams = [];

        if ($search) {
            $searchPattern = "%$search%";
            $mediaWhere[] = "(m.title LIKE ? OR m.description LIKE ?)";
            $mediaParams[] = $searchPattern;
            $mediaParams[] = $searchPattern;
        }

        if ($categoryId) {
            $mediaWhere[] = "m.category_id = ?";
            $mediaParams[] = $categoryId;
        }

        $mediaWhereStr = !empty($mediaWhere) ? 'AND ' . implode(' AND ', $mediaWhere) : '';

        $sql .= "
            SELECT 
                'media' as item_type,
                m.id, 
                m.title, 
                m.description, 
                m.filename, 
                m.uploaded_at as sort_date,
                0 as item_count,
                m.is_video
            FROM media_gallery m
            WHERE m.album_id IS NULL $mediaWhereStr
        ";
        $allParams = array_merge($allParams, $mediaParams);
    }

    // Specific album filter
    if ($specificAlbum) {
        $sql = "
            SELECT 
                'album' as item_type,
                a.id, 
                a.title, 
                a.description, 
                m.filename, 
                a.created_at as sort_date,
                (SELECT COUNT(*) FROM media_gallery WHERE album_id = a.id) as item_count,
                0 as is_video
            FROM media_albums a
            LEFT JOIN media_gallery m ON a.cover_image_id = m.id
            WHERE a.status = 'published' AND a.id = ?
        ";
        $allParams = [$specificAlbum];
    }

    if ($sql !== "") {
        $sql .= " ORDER BY sort_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($allParams);
        $items = $stmt->fetchAll();
    } else {
        $items = [];
    }

    echo json_encode(['success' => true, 'items' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
