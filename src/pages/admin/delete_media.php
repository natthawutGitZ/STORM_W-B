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
    $mediaId = (int) ($data['id'] ?? 0);

    if ($mediaId <= 0) {
        throw new Exception('Invalid media ID');
    }

    // Get media info
    $stmt = $pdo->prepare("SELECT filename FROM media_gallery WHERE id = ?");
    $stmt->execute([$mediaId]);
    $media = $stmt->fetch();

    if (!$media) {
        throw new Exception('Media not found');
    }

    // Delete files
    $fullPath = ROOT_PATH . '/assets/images/gallery/full/' . $media['filename'];
    $thumbPath = ROOT_PATH . '/assets/images/gallery/thumbs/' . $media['filename'];

    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    if (file_exists($thumbPath)) {
        unlink($thumbPath);
    }

    // Delete from database (cascade will handle tags)
    $stmt = $pdo->prepare("DELETE FROM media_gallery WHERE id = ?");
    $stmt->execute([$mediaId]);

    // Log admin action
    require_once ROOT_PATH . '/includes/admin_log.php';
    logAdminAction($pdo, 'delete_media', 'media', $mediaId, ['filename' => $media['filename']]);

    echo json_encode([
        'success' => true,
        'message' => 'Media deleted successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
