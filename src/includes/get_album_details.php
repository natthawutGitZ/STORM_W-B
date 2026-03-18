<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit;
}

$album_id = (int) $_GET['id'];

try {
    // Fetch Album Details
    $stmt = $pdo->prepare("SELECT * FROM media_albums WHERE id = ?");
    $stmt->execute([$album_id]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        echo json_encode(['success' => false, 'message' => 'Album not found']);
        exit;
    }

    // Fetch Media Items
    $stmt = $pdo->prepare("SELECT id, filename, title, description, is_video, video_platform, video_id, video_url, uploaded_at FROM media_gallery WHERE album_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$album_id]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format media URLS
    foreach ($media as &$item) {
        if ($item['is_video']) {
            // For videos, use thumbnail if available
            if ($item['filename']) {
                $item['thumb'] = '/assets/images/gallery/thumbs/' . $item['filename'];
                $item['src'] = '/assets/images/gallery/full/' . $item['filename'];
            } else {
                $item['thumb'] = '';
                $item['src'] = '';
            }
        } else {
            // For images
            $item['src'] = '/assets/images/gallery/full/' . $item['filename'];
            $item['thumb'] = '/assets/images/gallery/thumbs/' . $item['filename'];
        }
    }

    echo json_encode([
        'success' => true,
        'album' => $album,
        'media' => $media
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
