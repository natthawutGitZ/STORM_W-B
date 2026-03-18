<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/video_helpers.php';
require_once ROOT_PATH . '/includes/admin_log.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $videoUrl = trim($_POST['video_url'] ?? '');
    $albumId = isset($_POST['album_id']) && $_POST['album_id'] !== '' ? $_POST['album_id'] : null;
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    $customTitle = trim($_POST['title'] ?? '');
    $customDesc = trim($_POST['description'] ?? '');

    // Validate URL
    $validation = validateVideoUrl($videoUrl);
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }

    $platform = $validation['platform'];
    $videoId = $validation['video_id'];

    // Create New Album if requested
    if ($albumId === 'new') {
        $newTitle = trim($_POST['new_album_title']);
        $newDesc = trim($_POST['new_album_desc']);

        if (empty($newTitle)) {
            throw new Exception("Album title is required");
        }

        $stmt = $pdo->prepare("INSERT INTO media_albums (title, description, category_id, status) VALUES (?, ?, ?, 'published')");
        $stmt->execute([$newTitle, $newDesc, $categoryId]);
        $albumId = $pdo->lastInsertId();
    } elseif ($albumId && !is_numeric($albumId)) {
        $albumId = null;
    } elseif ($albumId === '') {
        $albumId = null;
    }

    // Get video metadata
    $metadata = getVideoMetadata($videoUrl);

    // Use custom title/description if provided, otherwise use metadata
    $title = !empty($customTitle) ? $customTitle : ($metadata['title'] ?: 'Untitled Video');
    $description = !empty($customDesc) ? $customDesc : ($metadata['description'] ?: '');

    // Get thumbnail URL
    $thumbnailUrl = getVideoThumbnail($videoId, $platform);
    if (!$thumbnailUrl && !empty($metadata['thumbnail'])) {
        $thumbnailUrl = $metadata['thumbnail'];
    }

    // Download and save thumbnail
    $filename = null;
    if ($thumbnailUrl) {
        try {
            $thumbDir = ROOT_PATH . "/assets/images/gallery/thumbs/";
            if (!file_exists($thumbDir)) {
                mkdir($thumbDir, 0777, true);
            }

            $filename = 'video_' . uniqid() . '.jpg';
            $thumbPath = $thumbDir . $filename;

            // Download thumbnail
            $imageData = @file_get_contents($thumbnailUrl);
            if ($imageData) {
                file_put_contents($thumbPath, $imageData);
            } else {
                $filename = null;
            }
        } catch (Exception $e) {
            $filename = null;
        }
    }

    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO media_gallery 
        (filename, original_filename, title, description, category_id, album_id, 
         file_size, mime_type, is_video, video_url, video_platform, video_id, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $userId = $_SESSION['user']['id'] ?? null;

    $stmt->execute([
        $filename,
        $platform . '_video',
        $title,
        $description,
        $categoryId,
        $albumId,
        0, // file_size
        'video/' . $platform,
        1, // is_video
        $videoUrl,
        $platform,
        $videoId,
        $userId
    ]);

    $mediaId = $pdo->lastInsertId();

    // Insert Tags
    if (!empty($tags)) {
        $tagStmt = $pdo->prepare("INSERT IGNORE INTO media_gallery_tags (media_id, tag_id) VALUES (?, ?)");
        foreach ($tags as $tagId) {
            $tagStmt->execute([$mediaId, $tagId]);
        }
    }

    // Set as album cover if creating new album
    if ($albumId && isset($_POST['album_id']) && $_POST['album_id'] === 'new') {
        $stmt = $pdo->prepare("UPDATE media_albums SET cover_image_id = ? WHERE id = ?");
        $stmt->execute([$mediaId, $albumId]);
    }

    logAdminAction($pdo, 'upload_video', 'media', $mediaId, ['title' => $_POST['title'] ?? '']);
    echo json_encode([
        'success' => true,
        'message' => 'Video link added successfully',
        'data' => [
            'id' => $mediaId,
            'title' => $title,
            'platform' => $platform,
            'thumbnail' => $filename
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

