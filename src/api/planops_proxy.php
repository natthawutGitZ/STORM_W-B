<?php
/**
 * Proxy for PLANOPS images (atlas.plan-ops.fr)
 * Solves CORS issue when loading marker/map images from external origin.
 * 
 * Usage: api/planops_proxy.php?url=https://atlas.plan-ops.fr/data/1/markers/1.webp
 *    or: api/planops_proxy.php?path=data/1/markers/1.webp
 *    or: api/planops_proxy.php?tile=1/maps/118/118/3/4/5.webp
 */

// Only allow requests from our own domain
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=86400'); // Cache 24 hours

$allowedHost = 'atlas.plan-ops.fr';

// Determine URL to fetch
$url = '';

if (isset($_GET['url'])) {
    $url = $_GET['url'];
} elseif (isset($_GET['path'])) {
    $url = 'https://' . $allowedHost . '/' . ltrim($_GET['path'], '/');
} elseif (isset($_GET['tile'])) {
    $url = 'https://' . $allowedHost . '/data/' . ltrim($_GET['tile'], '/');
} else {
    http_response_code(400);
    echo 'Missing url, path, or tile parameter';
    exit;
}

// Validate URL - only allow atlas.plan-ops.fr
$parsed = parse_url($url);
if (!$parsed || !isset($parsed['host']) || $parsed['host'] !== $allowedHost) {
    http_response_code(403);
    echo 'Forbidden: only atlas.plan-ops.fr is allowed';
    exit;
}

// Only allow image file extensions
$path = $parsed['path'] ?? '';
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$allowedExtensions = ['webp', 'png', 'jpg', 'jpeg', 'gif', 'svg'];
if (!in_array($ext, $allowedExtensions)) {
    http_response_code(403);
    echo 'Forbidden: only image files allowed';
    exit;
}

// Content type mapping
$contentTypes = [
    'webp' => 'image/webp',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
];

// Local cache directory
$cacheDir = __DIR__ . '/../tmp/planops_cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Cache key based on URL
$cacheKey = md5($url) . '.' . $ext;
$cacheFile = $cacheDir . '/' . $cacheKey;
$cacheTTL = 86400 * 7; // 7 days

// Serve from cache if available
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
    header('Content-Type: ' . ($contentTypes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($cacheFile));
    header('X-Cache: HIT');
    readfile($cacheFile);
    exit;
}

// Fetch from remote
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'STORMSURGE-Proxy/1.0');

$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200 || !$data) {
    http_response_code(502);
    echo 'Failed to fetch remote image (HTTP ' . $httpCode . ')';
    exit;
}

// Save to cache
file_put_contents($cacheFile, $data);

// Serve
header('Content-Type: ' . ($contentTypes[$ext] ?? $contentType ?? 'application/octet-stream'));
header('Content-Length: ' . strlen($data));
header('X-Cache: MISS');
echo $data;
