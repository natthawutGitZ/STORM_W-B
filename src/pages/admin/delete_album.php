<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $albumId = (int) ($data['id'] ?? 0);

    if ($albumId <= 0) {
        throw new Exception('Invalid album ID');
    }

    // 1. Get all media files in this album
    $stmt = $pdo->prepare("SELECT filename FROM media_gallery WHERE album_id = ?");
    $stmt->execute([$albumId]);
    $mediaFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Delete physical files
    $fullDir = ROOT_PATH . '/assets/images/gallery/full/';
    $thumbDir = ROOT_PATH . '/assets/images/gallery/thumbs/';
    $deletedCount = 0;

    foreach ($mediaFiles as $filename) {
        $fullPath = $fullDir . $filename;
        $thumbPath = $thumbDir . $filename;

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
        $deletedCount++;
    }

    // 3. Delete media records from database
    // Note: If you have foreign keys with ON DELETE CASCADE, this might happen automatically when album is deleted,
    // but explicit deletion is safer if constraints aren't perfect.
    $stmt = $pdo->prepare("DELETE FROM media_gallery WHERE album_id = ?");
    $stmt->execute([$albumId]);



    // 5. Delete the album itself
    $stmt = $pdo->prepare("DELETE FROM media_albums WHERE id = ?");
    $stmt->execute([$albumId]);

    // Log admin action
    require_once ROOT_PATH . '/includes/admin_log.php';
    logAdminAction($pdo, 'delete_album', 'album', $albumId, ['deleted_files' => $deletedCount]);

    echo json_encode([
        'success' => true,
        'message' => "Album and $deletedCount media files deleted successfully."
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>