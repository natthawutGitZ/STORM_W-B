<?php
if (session_status() === PHP_SESSION_NONE) {
    // Session Configuration - Keep users logged in for 30 days (2592000 seconds)
    ini_set('session.gc_maxlifetime', 2592000);
    ini_set('session.cookie_lifetime', 2592000);

    // Secure cookie settings
    session_set_cookie_params([
        'lifetime' => 2592000,  // 30 days
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user']);
}

function getUser()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags($data));
}

function isAdmin()
{
    return isset($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

function createThumbnail($source, $destination, $width = 300)
{
    list($originalWidth, $originalHeight, $type) = getimagesize($source);

    // Calculate Ratio
    $ratio = $originalWidth / $originalHeight;
    $newWidth = $width;
    $newHeight = $width / $ratio;

    // Create Canvas
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Handle Transparency for PNG/WEBP/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP || $type == IMAGETYPE_GIF) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // Load Source
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $sourceImage = imagecreatefromwebp($source);
            } else {
                // WebP not supported by GD, skip thumbnail
                return false;
            }
            break;
        default:
            return false;
    }

    // Resize
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    // Save
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($newImage, $destination, 80);
            break;
        case IMAGETYPE_PNG:
            imagepng($newImage, $destination, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($newImage, $destination);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                imagewebp($newImage, $destination, 80);
            } else {
                // Fallback: save as PNG if WebP not supported
                $destination = preg_replace('/\.webp$/i', '.png', $destination);
                imagepng($newImage, $destination, 8);
            }
            break;
    }

    imagedestroy($newImage);
    imagedestroy($sourceImage);
    return true;
}

function get_avatar($path)
{
    if (empty($path) || !file_exists(__DIR__ . '/../' . $path)) {
        return '/assets/images/default_avatar.png';
    }
    // Ensure the path starts with / so it's always an absolute URL
    if (strpos($path, 'http') === 0) {
        return $path;
    }
    return '/' . ltrim($path, '/');
}

function processUnitImage($source, $destination, $targetSize = 500)
{
    list($width, $height, $type) = getimagesize($source);

    // Load Image
    switch ($type) {
        case IMAGETYPE_JPEG:
            $img = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $img = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $img = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $img = imagecreatefromwebp($source);
            break;
        default:
            return false; // Unsupported
    }

    if (!$img)
        return false;

    // 1. Remove White Background (Simple Heuristic: Flood Fill from top-left)
    // Convert logic: Check top-left pixel. If white-ish, replace with transparent.
    $rgb = imagecolorat($img, 0, 0);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;

    // Check if "White-ish" (Tolerance)
    if ($r > 240 && $g > 240 && $b > 240) {
        // Enable alpha blending
        imagealphablending($img, false);
        imagesavealpha($img, true);

        // Define transparent color
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);

        // Define White to remove (Exact color at 0,0)
        // Note: For better results, we might loop, but flood fill is safer for "outside" background
        imagefill($img, 0, 0, $transparent);
        imagecolortransparent($img, $transparent);
    }

    // 2. Resize / Fit to Square
    $square = imagecreatetruecolor($targetSize, $targetSize);

    // Preserve Transparency
    imagealphablending($square, false);
    imagesavealpha($square, true);
    $transparentCanvas = imagecolorallocatealpha($square, 0, 0, 0, 127);
    imagefilledrectangle($square, 0, 0, $targetSize, $targetSize, $transparentCanvas);

    // Calculate Aspect Ratio
    $ratio = $width / $height;
    if ($ratio > 1) {
        // Wide: Scale height to fit
        $newWidth = $targetSize;
        $newHeight = $targetSize / $ratio;
        $x = 0;
        $y = ($targetSize - $newHeight) / 2;
    } else {
        // Tall: Scale width to fit
        $newWidth = $targetSize * $ratio;
        $newHeight = $targetSize;
        $x = ($targetSize - $newWidth) / 2;
        $y = 0;
    }

    imagecopyresampled($square, $img, $x, $y, 0, 0, $newWidth, $newHeight, $width, $height);

    // Save as PNG (Best for logos)
    $success = imagepng($square, $destination, 8); // Compression 8

    imagedestroy($img);
    imagedestroy($square);

    return $success;
}

function thai_date($timestamp)
{
    $days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];

    $dayOfWeek = $days[date('w', $timestamp)];
    $day = date('j', $timestamp);
    $month = $months[(int) date('n', $timestamp)];
    $year = date('Y', $timestamp) + 543; // Buddhist Era
    $time = date('H:i', $timestamp);

    return "วัน$dayOfWeek" . "ที่ $day $month $year $time น.";
}

function send_html_email($to, $subject, $message)
{
    // Try SMTP via Bot API first (works reliably on EC2)
    try {
        require_once __DIR__ . '/bot_api.php';
        $botApi = new BotAPI();
        $result = $botApi->sendEmail($to, $subject, $message);

        if ($result['success']) {
            return true;
        }
        // If Bot API fails, fall through to PHP mail()
        error_log("Bot API Email failed, falling back to PHP mail(): " . ($result['error'] ?? 'Unknown'));
    } catch (Exception $e) {
        error_log("Bot API Email exception: " . $e->getMessage());
    }

    // Fallback: Basic PHP mail()
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: STORM Operations <noreply@storm-ops.com>" . "\r\n";

    // Simple Template
    $body = "
    <html>
    <head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #121212; color: #fff; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #1e1e1e; border-radius: 8px; overflow: hidden; border: 1px solid #333; }
        .header { background: linear-gradient(135deg, #1e1e1e 0%, #252525 100%); padding: 20px; text-align: center; border-bottom: 3px solid #c5a059; }
        .content { padding: 30px; color: #e0e0e0; line-height: 1.6; }
        .footer { background: #151515; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        a { color: #c5a059; text-decoration: none; }
        .btn { display: inline-block; padding: 10px 20px; background: #c5a059; color: #000; text-decoration: none; border-radius: 4px; margin-top: 15px; font-weight: bold; }
    </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin:0; color: #fff; font-size: 24px;'>STORM Operations</h1>
            </div>
            <div class='content'>
                $message
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " Strategic Tactical Operations. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";

    // Attempt to send
    return mail($to, $subject, $body, $headers);
}

/**
 * Send Discord notification via Bot API to a specific channel
 */
function sendDonateNotification($channelId, $donation, $status)
{
    require_once __DIR__ . '/bot_api.php';
    $api = new BotAPI();

    $isApproved = $status === 'approved';
    $color = $isApproved ? '#c5a059' : '#f04747';
    $donorName = $donation['donor_name'] ?: 'Anonymous';
    $amount = number_format($donation['amount'], 2);
    $date = date('d/m/Y H:i', strtotime($donation['created_at'] ?? 'now'));

    $fields = [
        ['name' => '👤 ผู้สนับสนุน', 'value' => $donorName, 'inline' => true],
        ['name' => '💰 จำนวนเงิน', 'value' => '฿' . $amount, 'inline' => true],
        ['name' => '📅 วันที่', 'value' => $date, 'inline' => true],
    ];

    if (!empty($donation['message'])) {
        $fields[] = ['name' => '💬 ข้อความ', 'value' => mb_strimwidth($donation['message'], 0, 200, '...'), 'inline' => false];
    }

    $verifyStatus = $donation['verify_status'] ?? null;
    if ($verifyStatus) {
        $verifyLabels = [
            'verified' => '✅ ตรวจสอบแล้ว (อัตโนมัติ)',
            'amount_mismatch' => '⚠️ ยอดไม่ตรง',
            'checked' => '🔍 ตรวจสอบแล้ว',
            'invalid' => '❌ สลิปไม่ถูกต้อง',
            'api_error' => '⚙️ API Error',
        ];
        $fields[] = ['name' => '🛡️ Slip Verify', 'value' => $verifyLabels[$verifyStatus] ?? $verifyStatus, 'inline' => true];
    }

    if (!empty($donation['transaction_ref'])) {
        $fields[] = ['name' => '🔗 Ref', 'value' => '`' . $donation['transaction_ref'] . '`', 'inline' => true];
    }

    $title = $isApproved ? '💝 Donation Received!' : '❌ Donation Rejected';
    $description = $isApproved
        ? "ได้รับการสนับสนุนจาก **{$donorName}** เรียบร้อยแล้ว!"
        : "การสนับสนุนจาก **{$donorName}** ถูกปฏิเสธ";

    $payload = [
        'channel_id' => $channelId,
        'content' => [
            'title' => $title,
            'description' => $description,
            'fields' => $fields,
        ],
        'style' => [
            'color' => $color,
            'timestamp' => true,
        ],
        'branding' => [
            'enabled' => true,
            'footer_text' => 'S.T.O.R.M Donate System',
        ],
    ];

    // Slip image attachment has been removed by request

    $api->request('/embed/send', $payload, 'POST');
}