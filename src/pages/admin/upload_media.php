<?php
// === TEMPORARY DEBUG MODE - Remove after fixing ===
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Custom error handler to catch ALL errors and return as JSON
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Custom shutdown handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal Error: ' . $error['message'],
            'debug' => [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]
        ]);
    }
});
// === END DEBUG MODE ===

// Ensure ROOT_PATH is defined (should be defined by router /index.php)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/admin_log.php';

// Ensure clean JSON output
if (ob_get_level() > 0) {
    ob_clean();
}
header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $albumId = isset($_POST['album_id']) ? $_POST['album_id'] : null;
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int) $_POST['category_id'] : null;
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];

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

    // Check if files exist
    if (empty($_FILES['media_file']['name'][0])) {
        throw new Exception("No files selected");
    }

    $uploadedCount = 0;
    $firstImageId = null;
    $skippedFiles = []; // Track why files were skipped

    $targetDir = ROOT_PATH . "/assets/images/gallery/full/";
    $thumbDir = ROOT_PATH . "/assets/images/gallery/thumbs/";

    // Ensure directories exist
    if (!file_exists($targetDir))
        mkdir($targetDir, 0777, true);
    if (!file_exists($thumbDir))
        mkdir($thumbDir, 0777, true);

    // Check directory is writable
    if (!is_writable($targetDir)) {
        throw new Exception("Upload directory is not writable: $targetDir");
    }
    if (!is_writable($thumbDir)) {
        throw new Exception("Thumbnail directory is not writable: $thumbDir");
    }

    $files = $_FILES['media_file'];
    $fileCount = count($files['name']);

    // PHP upload error code descriptions
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by PHP extension',
    ];

    for ($i = 0; $i < $fileCount; $i++) {
        $originalName = $files['name'][$i] ?? 'unknown';

        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errCode = $files['error'][$i];
            $errMsg = $uploadErrors[$errCode] ?? "Unknown error (code: $errCode)";
            $skippedFiles[] = "$originalName: $errMsg";
            error_log("Upload error for $originalName: $errMsg");
            continue;
        }

        $tmpName = $files['tmp_name'][$i];
        $fileSize = $files['size'][$i];
        $fileType = $files['type'][$i];

        // Validation - accept common image types
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        // Detect MIME type safely
        $mime = $fileType;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if ($detectedMime && $detectedMime !== 'application/octet-stream') {
                    $mime = $detectedMime;
                }
            }
        }

        if (!in_array($mime, $allowed) && !in_array($fileType, $allowed)) {
            $skippedFiles[] = "$originalName: Unsupported type (detected: $mime, browser: $fileType)";
            error_log("Skipped $originalName: MIME '$mime' / browser '$fileType' not in allowed list");
            continue;
        }

        // Generate Extension and Filename
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!$ext) {
            switch ($mime) {
                case 'image/jpeg': $ext = 'jpg'; break;
                case 'image/png': $ext = 'png'; break;
                case 'image/gif': $ext = 'gif'; break;
                case 'image/webp': $ext = 'webp'; break;
            }
        }
        $filename = uniqid('media_') . '.' . $ext;

        // Move File
        if (move_uploaded_file($tmpName, $targetDir . $filename)) {

            // Create Thumbnail (wrapped to not break upload)
            try {
                $thumbResult = createThumbnail($targetDir . $filename, $thumbDir . $filename, 300);
                if (!$thumbResult) {
                    // Thumbnail creation failed (e.g. WebP not supported), copy original as thumbnail
                    copy($targetDir . $filename, $thumbDir . $filename);
                }
            } catch (Exception $thumbErr) {
                // Copy original as fallback thumbnail
                copy($targetDir . $filename, $thumbDir . $filename);
                error_log('Thumbnail creation failed: ' . $thumbErr->getMessage());
            }

            // Insert into DB
            $stmt = $pdo->prepare("
                INSERT INTO media_gallery (filename, original_filename, title, description, category_id, album_id, file_size, mime_type, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $mediaTitle = !empty($_POST['media_title']) ? trim($_POST['media_title']) : pathinfo($originalName, PATHINFO_FILENAME);
            $mediaDescription = !empty($_POST['media_description']) ? trim($_POST['media_description']) : '';
            $userId = $_SESSION['user']['id'] ?? null;

            $stmt->execute([$filename, $originalName, $mediaTitle, $mediaDescription, $categoryId, $albumId, $fileSize, $mime, $userId]);
            $mediaId = $pdo->lastInsertId();

            if (!$firstImageId)
                $firstImageId = $mediaId;

            // Insert Tags
            if (!empty($tags)) {
                $tagStmt = $pdo->prepare("INSERT IGNORE INTO media_gallery_tags (media_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tagId) {
                    $tagStmt->execute([$mediaId, $tagId]);
                }
            }

            $uploadedCount++;
        } else {
            $lastErr = error_get_last();
            $moveErrMsg = $lastErr ? $lastErr['message'] : 'Unknown reason';
            $skippedFiles[] = "$originalName: move_uploaded_file failed ($moveErrMsg)";
            error_log("move_uploaded_file failed for $originalName: $moveErrMsg");
        }
    }

    if ($uploadedCount === 0) {
        $detail = !empty($skippedFiles) ? " Details: " . implode('; ', $skippedFiles) : "";
        throw new Exception("Failed to upload files.$detail");
    }

    // Set Cover Image for New Album
    if ($albumId && isset($_POST['album_id']) && $_POST['album_id'] === 'new' && $firstImageId) {
        $stmt = $pdo->prepare("UPDATE media_albums SET cover_image_id = ? WHERE id = ?");
        $stmt->execute([$firstImageId, $albumId]);
    }

    logAdminAction($pdo, 'upload_media', 'media', $albumId, ['count' => $uploadedCount, 'album_id' => $albumId]);
    echo json_encode(['success' => true, 'message' => "Uploaded $uploadedCount files successfully"]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
