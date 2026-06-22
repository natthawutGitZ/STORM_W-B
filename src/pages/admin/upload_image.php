<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

// Simple admin check
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Try finfo first (safer on Windows XAMPP)
    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    
    // Fallback to mime_content_type if available and finfo failed
    if (empty($mimeType) && function_exists('mime_content_type')) {
        $mimeType = @mime_content_type($file['tmp_name']);
    }
    
    // Ultimate fallback to browser type
    if (empty($mimeType) || $mimeType === 'application/octet-stream') {
        $mimeType = $file['type']; 
    }

    if (!in_array($mimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.']);
        exit();
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Upload failed with error code ' . $file['error']]);
        exit();
    }

    $uploadDir = ROOT_PATH . '/assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return the URL relative to the web root
        // Assuming /admin/ is one level deep from root
        // Current script is in /admin/, uploads are in /assets/
        // URL should be ../assets/uploads/filename for relative, or /assets/uploads/filename

        $protocol = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];
        // Construct full URL so Discord can reach it (if accessible)
        // For local docker, it might be localhost:8080. 
        // Note: Bot might not be able to reach localhost:8080 if it's in a container.
        // But for now let's return the relative path for the UI preview, and full path for backend?
        // Actually, the preview needs a URL that the browser can resolve.

        $webPath = '/assets/uploads/' . $filename;
        $fullUrl = "$protocol://$host$webPath";

        echo json_encode(['url' => $fullUrl, 'web_path' => $webPath]);
    } else {
        echo json_encode(['error' => 'Failed to move uploaded file.']);
    }
} else {
    echo json_encode(['error' => 'No file uploaded.']);
}

