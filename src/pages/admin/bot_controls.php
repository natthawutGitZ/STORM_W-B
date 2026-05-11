<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/bot_api.php';
require_once ROOT_PATH . '/includes/admin_log.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$api = new BotAPI();
$botStatus = $api->getStatus();
$channels = $api->getChannels(); // Fetch available channels
$voiceChannels = $api->getVoiceChannels(); // Fetch voice channels
$roles = $api->getRoles(); // Fetch available roles
$members = $api->getMembers(); // Fetch guild members for user mentions
$message = '';
$error = '';


/**
 * Helper function to check if URL is local and convert to base64
 */
function convertLocalImageToBase64($imageUrl)
{
    if (empty($imageUrl)) {
        return ['base64' => '', 'filename' => ''];
    }

    // Check if it's a local URL patterns
    $isLocal = (
        strpos($imageUrl, 'localhost') !== false ||
        strpos($imageUrl, '127.0.0.1') !== false ||
        strpos($imageUrl, '/assets/uploads/') !== false ||
        !preg_match('/^https?:\/\//', $imageUrl) // Relative path
    );

    if (!$isLocal) {
        return ['base64' => '', 'filename' => ''];
    }

    // 1. Try to find via direct path if it contains /assets/uploads/
    if (strpos($imageUrl, '/assets/uploads/') !== false) {
        $filename = basename($imageUrl);
        // Standard location relative to this script (src/admin/bot_controls.php)
        // We want src/assets/uploads/filename
        $directPath = dirname(__DIR__) . '/assets/uploads/' . $filename;

        if (file_exists($directPath)) {
            return [
                'base64' => base64_encode(file_get_contents($directPath)),
                'filename' => $filename
            ];
        }
    }

    // 2. Extract the path from URL and try document roots
    $urlPath = parse_url($imageUrl, PHP_URL_PATH);
    if (!$urlPath) {
        $urlPath = $imageUrl;
    }

    $possiblePaths = [
        $_SERVER['DOCUMENT_ROOT'] . $urlPath,
        '/var/www/html' . $urlPath,
        dirname(__DIR__) . $urlPath, // Parent of /admin
    ];

    foreach ($possiblePaths as $localPath) {
        if (file_exists($localPath)) {
            return [
                'base64' => base64_encode(file_get_contents($localPath)),
                'filename' => basename($localPath)
            ];
        }
    }

    return ['base64' => '', 'filename' => ''];
}

/**
 * Helper function to fetch dynamic ngrok public URL
 * @return string|null Current ngrok public URL or null if unavailable
 */
function getNgrokPublicUrl()
{
    static $cachedUrl = null;

    // Return cached URL if available
    if ($cachedUrl !== null) {
        return $cachedUrl;
    }

    try {
        // Ngrok API endpoint (from docker-compose service name)
        $apiUrl = 'http://ngrok:4040/api/tunnels';

        // Create context with short timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (!empty($data['tunnels'])) {
            // Find HTTPS tunnel
            foreach ($data['tunnels'] as $tunnel) {
                if ($tunnel['proto'] === 'https') {
                    $cachedUrl = $tunnel['public_url'];
                    return $cachedUrl;
                }
            }
        }

        return null;
    } catch (Exception $e) {
        error_log("Error fetching ngrok URL: " . $e->getMessage());
        return null;
    }
}

/**
 * Convert image URL to publicly accessible URL
 * Supports both Docker (ngrok) and Production (InfinityFree) environments
 * @param string $imageUrl Existing uploaded image URL
 * @return string Public URL or empty string
 */
function saveEventImageToPublic($imageUrl)
{
    if (empty($imageUrl)) {
        error_log("📷 saveEventImageToPublic: No image URL provided");
        return '';
    }

    error_log("📷 saveEventImageToPublic: Processing image: $imageUrl");

    // If already full URL, return as is
    if (strpos($imageUrl, 'http') === 0) {
        error_log("📷 saveEventImageToPublic: Already full URL, returning as is");
        return $imageUrl;
    }

    // Detect environment
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Check if Docker environment:
    // 1. localhost or 127.0.0.1
    // 2. Accessing via ngrok-free.dev domain
    // 3. DOCKER_CONTAINER env var
    // 4. /.dockerenv file exists
    $isDocker = (
        strpos($hostname, 'localhost') !== false ||
        strpos($hostname, '127.0.0.1') !== false ||
        strpos($hostname, 'ngrok-free.dev') !== false ||  // User accessing via ngrok
        strpos($hostname, 'ngrok.io') !== false ||        // Alternative ngrok domain
        getenv('DOCKER_CONTAINER') !== false ||
        file_exists('/.dockerenv')
    );

    error_log("📷 saveEventImageToPublic: hostname=$hostname, isDocker=" . ($isDocker ? 'true' : 'false'));

    if ($isDocker) {
        // Docker: Use dynamic ngrok URL
        $ngrokUrl = getNgrokPublicUrl();

        error_log("📷 saveEventImageToPublic: ngrokUrl=$ngrokUrl");

        if ($ngrokUrl) {
            $fullUrl = $ngrokUrl . $imageUrl;
            error_log("📷 saveEventImageToPublic: Returning full URL: $fullUrl");
            return $fullUrl;
        }

        // Fallback: Log error and return empty
        error_log("⚠️ WARNING: Could not fetch ngrok URL for image: $imageUrl");
        error_log("💡 TIP: Make sure ngrok service is running: docker-compose --profile tools up -d");
        return ''; // Return empty to avoid broken links
    } else {
        // Production (InfinityFree): Use production domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $fullUrl = "$protocol://$host" . $imageUrl;
        error_log("📷 saveEventImageToPublic: Production URL: $fullUrl");
        return $fullUrl;
    }
}

/**
 * Handle POST Requests
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        /* --- ACTION: SEND EMBED --- */
        if ($_POST['action'] === 'send_embed') {
            $channelId = $_POST['channel_id'];

            // Message Content
            $msgContent = $_POST['message_content'] ?? '';

            // Handle image uploads - convert to base64
            $imageBase64 = '';
            $imageFilename = '';
            $thumbnailBase64 = '';
            $thumbnailFilename = '';



            // Convert image URL
            $imageUrl = $_POST['image_url'] ?? '';
            $imageResult = convertLocalImageToBase64($imageUrl);
            $imageBase64 = $imageResult['base64'];
            $imageFilename = $imageResult['filename'];

            // Convert thumbnail URL
            $thumbnailUrl = $_POST['thumbnail_url'] ?? '';
            $thumbResult = convertLocalImageToBase64($thumbnailUrl);
            $thumbnailBase64 = $thumbResult['base64'];
            $thumbnailFilename = $thumbResult['filename'];

            // Convert Author Icon
            $authorIconUrl = $_POST['author_icon'] ?? '';
            $authorResult = convertLocalImageToBase64($authorIconUrl);

            // Convert Footer Icon
            $footerIconUrl = $_POST['footer_icon'] ?? '';
            $footerResult = convertLocalImageToBase64($footerIconUrl);

            // Convert External Image (outside embed)
            $externalImageUrl = $_POST['external_image_url'] ?? '';
            $externalResult = convertLocalImageToBase64($externalImageUrl);

            // Embed Data
            $embedData = [
                'message_content' => $msgContent,
                'channel_id' => $channelId,
                // External Image (outside embed)
                'external_image_url' => $externalResult['base64'] ? '' : $externalImageUrl,
                'external_image_base64' => $externalResult['base64'],
                'external_image_filename' => $externalResult['filename'],
                'branding' => [
                    'enabled' => true,
                    'author_name' => $_POST['author_name'] ?? '',
                    'author_icon' => $authorResult['base64'] ? '' : $authorIconUrl,
                    'author_icon_base64' => $authorResult['base64'],
                    'author_icon_filename' => $authorResult['filename'],
                    'footer_text' => $_POST['footer_text'] ?? '',
                    'footer_icon' => $footerResult['base64'] ? '' : $footerIconUrl,
                    'footer_icon_base64' => $footerResult['base64'],
                    'footer_icon_filename' => $footerResult['filename'],
                ],
                'style' => [
                    'color' => $_POST['embed_color'] ?? '#2196f3',
                    'image' => $imageBase64 ? '' : $imageUrl, // Only use URL if no base64
                    'image_base64' => $imageBase64,
                    'image_filename' => $imageFilename,
                    'thumbnail' => $thumbnailBase64 ? '' : $thumbnailUrl,
                    'thumbnail_base64' => $thumbnailBase64,
                    'thumbnail_filename' => $thumbnailFilename,
                ],
                'content' => [
                    'title' => $_POST['embed_title'] ?? '',
                    'description' => $_POST['embed_description'] ?? '',
                    'fields' => []
                ],
                'buttons' => []
            ];

            // Fields
            if (isset($_POST['field_name'])) {
                for ($i = 0; $i < count($_POST['field_name']); $i++) {
                    if (!empty($_POST['field_name'][$i])) {
                        $embedData['content']['fields'][] = [
                            'name' => $_POST['field_name'][$i],
                            'value' => $_POST['field_value'][$i] ?? '',
                            'inline' => isset($_POST['field_inline'][$i])
                        ];
                    }
                }
            }

            // Buttons
            if (isset($_POST['btn_label'])) {
                for ($i = 0; $i < count($_POST['btn_label']); $i++) {
                    if (!empty($_POST['btn_label'][$i]) && !empty($_POST['btn_url'][$i])) {
                        $embedData['buttons'][] = [
                            'label' => $_POST['btn_label'][$i],
                            'url' => $_POST['btn_url'][$i]
                        ];
                    }
                }
            }

            // Add delivery method
            $deliveryMethod = $_POST['delivery_method'] ?? 'channel';
            $dmRoleId = $_POST['dm_role_id'] ?? '';

            $embedData['delivery_method'] = $deliveryMethod;
            $embedData['dm_role_id'] = $dmRoleId;

            // CHECK MODE: Update or Create?
            $discordMsgId = $_POST['discord_message_id'] ?? '';
            $savedDbId = $_POST['saved_db_id'] ?? '';
            $isUpdate = (!empty($discordMsgId) && !empty($savedDbId));

            if ($isUpdate) {
                $result = $api->updateEmbed($channelId, $discordMsgId, $embedData);
            } else {
                $result = $api->sendEmbed($channelId, $embedData);
            }

            if ($result['success']) {
                $successCount = $result['data']['dm_count'] ?? 1;
                if ($deliveryMethod === 'channel') {
                    $publishedMsgId = $isUpdate ? $discordMsgId : ($result['data']['message_id'] ?? null);
                    $message = $isUpdate ? "Embed updated successfully!" : "Embed sent successfully! ID: " . $publishedMsgId;
                    logAdminAction($pdo, $isUpdate ? 'update_embed' : 'send_embed', 'embed', null, ['channel' => $channelId, 'title' => $_POST['embed_title'] ?? '']);

                    // --- AUTO-SAVE / UPDATE TO DATABASE ---
                    try {
                        // 1. Resolve Channel Name
                        $channelName = $channelId;
                        foreach ($channels as $c) {
                            if ($c['id'] == $channelId) {
                                $channelName = $c['name'];
                                break;
                            }
                        }

                        // 2. Resolve Message Title (Name)
                        $savedName = $_POST['embed_title'] ?? '';
                        if (empty($savedName)) {
                            $savedName = "[ Message - " . date("Y-m-d H:i") . " ]";
                        }

                        // 3. Map Data to Saved Messages storage format
                        $storageEmbedData = [
                            'title' => $embedData['content']['title'] ?? '',
                            'description' => $embedData['content']['description'] ?? '',
                            'url' => '',
                            'color' => $embedData['style']['color'] ?? '#5865f2',
                            'timestamp' => date('c'),
                            'image' => $embedData['style']['image'] ?? '',
                            'thumbnail' => $embedData['style']['thumbnail'] ?? '',
                            'footer' => $embedData['branding']['footer_text'] ?? '',
                            'author' => [
                                'name' => $embedData['branding']['author_name'] ?? '',
                                'icon' => $embedData['branding']['author_icon'] ?? ''
                            ]
                        ];

                        if ($isUpdate) {
                            // UPDATE Existing Record
                            $stmt = $pdo->prepare("UPDATE saved_messages SET name=?, channel_id=?, channel_name=?, message_id=?, content=?, embed_data=?, buttons_data=?, status='published' WHERE id=?");
                            $stmt->execute([
                                $savedName,
                                $channelId,
                                $channelName,
                                $publishedMsgId,
                                $msgContent,
                                json_encode($storageEmbedData),
                                json_encode($embedData['buttons'] ?? []),
                                $savedDbId
                            ]);
                        } else {
                            // INSERT New Record
                            $stmt = $pdo->prepare("INSERT INTO saved_messages (name, channel_id, channel_name, message_id, content, embed_data, buttons_data, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $savedName,
                                $channelId,
                                $channelName,
                                $publishedMsgId,
                                $msgContent,
                                json_encode($storageEmbedData),
                                json_encode($embedData['buttons'] ?? []),
                                'published'
                            ]);
                            $message .= " [Saved ID: " . $pdo->lastInsertId() . "]";
                        }

                    } catch (Exception $e) {
                        // SELF-HEALING: If table missing, create it and retry
                        if (strpos($e->getMessage(), "doesn't exist") !== false) {
                            try {
                                $pdo->exec("CREATE TABLE IF NOT EXISTS `saved_messages` (
                                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                                    `name` VARCHAR(100) NOT NULL COMMENT 'Display name',
                                    `channel_id` VARCHAR(50) NOT NULL COMMENT 'Discord channel ID',
                                    `channel_name` VARCHAR(100) DEFAULT NULL,
                                    `message_id` VARCHAR(50) DEFAULT NULL,
                                    `content` TEXT COMMENT 'Plain text content',
                                    `embed_data` JSON COMMENT 'Embed config',
                                    `buttons_data` JSON COMMENT 'Button config',
                                    `status` ENUM('draft', 'published') DEFAULT 'draft',
                                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    PRIMARY KEY (`id`),
                                    KEY `idx_channel_id` (`channel_id`),
                                    KEY `idx_status` (`status`)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

                                // Retry Insert (Assuming Create Mode because Update wouldn't fail on table missing if logic reached here? wait, Update DOES fail if table missing)
                                // If In Update Mode and table missing? That's weird.
                                // We'll assume if table missing, we should probably recreate and INSERT (since old record is gone).
                                // So we treat it as Insert retry.
                                $stmt = $pdo->prepare("INSERT INTO saved_messages (name, channel_id, channel_name, message_id, content, embed_data, buttons_data, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $savedName,
                                    $channelId,
                                    $channelName,
                                    $publishedMsgId,
                                    $msgContent,
                                    json_encode($storageEmbedData),
                                    json_encode($embedData['buttons'] ?? []),
                                    'published'
                                ]);
                                $message .= " [Auto-Created Table & Saved ID: " . $pdo->lastInsertId() . "]";

                            } catch (Exception $e2) {
                                $message .= " (Critical: Failed to auto-create table - " . $e2->getMessage() . ")";
                            }
                        } else {
                            error_log("Failed to auto-save message: " . $e->getMessage());
                            $message .= " (Warning: Auto-save failed - " . $e->getMessage() . ")";
                        }
                    }
                    // -----------------------------

                } else {
                    $message = "DM sent successfully to {$successCount} members!";
                    logAdminAction($pdo, 'send_dm', 'embed', null, ['dm_count' => $successCount]);
                }
            } else {
                $error = "Failed to send: " . $result['error'];
            }
        }

        /* --- ACTION: CREATE EVENT --- */ elseif ($_POST['action'] === 'create_event') {
            // Handle Event Image - Use direct URL approach
            // This works with ServBay/Cloudflare Tunnel (no interstitial page)
            $eventImageUrl = $_POST['event_image'] ?? '';
            $publicImageUrl = '';

            if (!empty($eventImageUrl)) {
                // Convert to public URL using ngrok/ServBay/production URL
                $publicImageUrl = saveEventImageToPublic($eventImageUrl);
                error_log("📷 Event Image: Using direct URL: $publicImageUrl");
            }

            // Helper to generate UUID v4
            function generate_uuid()
            {
                return sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff)
                );
            }

            // Event ID: Use existing if provided (Edit), else generate new UUID
            $eventId = !empty($_POST['event_id']) ? $_POST['event_id'] : generate_uuid();

            // Collect reminder minutes from checkboxes
            $reminderMinutes = [];
            if (!empty($_POST['reminder_15']))
                $reminderMinutes[] = '15';
            if (!empty($_POST['reminder_30']))
                $reminderMinutes[] = '30';
            if (!empty($_POST['reminder_60']))
                $reminderMinutes[] = '60';
            $reminderStr = implode(',', $reminderMinutes);

            // Basic Event Logic (Using same API or specialized one)
            $eventData = [
                'event_id' => $eventId,
                'title' => $_POST['event_title'],
                'story' => $_POST['event_story'] ?? '',
                'type' => $_POST['event_type'] ?? 'other',
                'location' => [
                    'platform' => 'Discord',
                    'detail' => $_POST['event_location'] ?? ''
                ],
                // Force +07:00 (Asia/Bangkok) as usage context implies Thai user
                'start_time' => ($_POST['event_date'] ?? date('Y-m-d')) . 'T' . ($_POST['event_time'] ?? '00:00') . ':00+07:00',
                'category' => $_POST['event_type'] ?? 'other',
                'requirements' => [
                    'mods' => $_POST['event_requirements'] ?? ''
                ],
                // Use direct URL for image (works with ServBay/Cloudflare Tunnel)
                'image' => $publicImageUrl,
                'visibility' => [
                    'channel_id' => $_POST['target_channel'],
                    'pin_message' => !empty($_POST['pin_message'])
                ],
                // Reminder and Role Ping Settings
                'reminder_minutes' => $reminderStr,
                'ping_role_id' => $_POST['ping_role_id'] ?? ''
            ];

            error_log("📷 Event Image Data: url=$publicImageUrl");

            $result = $api->createEvent($eventData);
            if ($result['success']) {
                $message = "Event saved! ID: " . $result['data']['event_id'];
                logAdminAction($pdo, 'create_event', 'event', null, ['title' => $_POST['event_title'] ?? '', 'event_id' => $result['data']['event_id']]);
            } else {
                $error = "Failed to create event: " . $result['error'];
            }

        }

        /* --- ACTION: CANCEL EVENT --- */ elseif ($_POST['action'] === 'cancel_event') {
            $eventId = $_POST['event_id'];
            if ($api->cancelEvent($eventId)) {
                $message = "Event cancelled successfully.";
                logAdminAction($pdo, 'cancel_event', 'event', null, ['event_id' => $eventId]);
            } else {
                $error = "Failed to cancel event.";
            }
        }

        /* --- ACTION: UPDATE BOT SETTINGS --- */ elseif ($_POST['action'] === 'update_bot_settings') {
            $settings = [
                'name' => $_POST['bot_name'] ?? '',
                'status' => $_POST['bot_status'] ?? 'online',
                'activity_type' => $_POST['activity_type'] ?? '',
                'activity_text' => $_POST['activity_text'] ?? ''
            ];

            // Handle avatar upload (base64)
            if (!empty($_POST['bot_avatar'])) {
                $settings['avatar_base64'] = $_POST['bot_avatar'];
            }

            $result = $api->updateBotSettings($settings);
            if ($result['success']) {
                $message = "Bot settings updated successfully!";
                logAdminAction($pdo, 'update_bot_settings', 'bot', null, ['name' => $settings['name'], 'status' => $settings['status']]);
            } else {
                $error = "Failed to update bot settings: " . ($result['error'] ?? 'Unknown error');
            }
        }

        /* --- ACTION: JOIN VOICE --- */ elseif ($_POST['action'] === 'join_voice') {
            $channelId = $_POST['voice_channel_id'];
            $result = $api->joinVoice($channelId);
            if ($result['success']) {
                $message = $result['data']['message'] ?? "Joined voice channel!";
                logAdminAction($pdo, 'join_voice', 'bot', null, ['channel_id' => $channelId]);
            } else {
                $error = "Failed to join: " . ($result['error'] ?? 'Unknown error');
            }
        }

        /* --- ACTION: LEAVE VOICE --- */ elseif ($_POST['action'] === 'leave_voice') {
            $result = $api->leaveVoice();
            if ($result['success']) {
                $message = $result['data']['message'] ?? "Left voice channel!";
                logAdminAction($pdo, 'leave_voice', 'bot', null);
            } else {
                $error = "Failed to leave: " . ($result['error'] ?? 'Unknown error');
            }
        }
    }
}

// Fetch Active Events
$activeEvents = $api->getActiveEvents();

include ROOT_PATH . '/admin/includes/admin_header.php';

// FAST DEBUG: Visual Indicator - Removed after verification


$activeTab = $_GET['tab'] ?? 'embed';
?>
<!-- Page Specific CSS -->
<link rel="stylesheet" href="/assets/css/bot_controls.css?v=<?php echo time(); ?>">
<style>
    /* Delivery Card Styles */
    .delivery-method-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .delivery-card {
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .delivery-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .delivery-card:active {
        transform: translateY(0);
    }

    /* Responsive adjustment */
    @media (max-width: 768px) {
        .delivery-method-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Vibrant Glass Role Selector */
    .role-option {
        padding: 14px 18px;
        /* Larger padding */
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 12px;

        /* Brighter, transparent glass background */
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);

        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        position: relative;
        overflow: hidden;
    }

    .role-option:hover {
        transform: translateY(-3px) scale(1.02);
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
        border-color: rgba(255, 255, 255, 0.4);
        /* Glowy white border on hover */
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }

    .role-option.selected {
        /* Distinct Active State - Blue Glow */
        background: linear-gradient(145deg, rgba(88, 101, 242, 0.3), rgba(88, 101, 242, 0.1));
        border: 2px solid #5865F2;
        /* Thicker selected border */
        box-shadow: 0 0 20px rgba(88, 101, 242, 0.4);
    }

    .role-option .checkmark {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        /* Circle checkmark */
        border: 2px solid rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.3);
        transition: all 0.3s;
        flex-shrink: 0;
    }

    .role-option.selected .checkmark {
        background: #5865f2;
        border-color: #fff;
        transform: scale(1.1);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
    }

    /* Collapsible Selector Styles */
    .role-selector-wrapper {
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        background: rgba(0, 0, 0, 0.2);
        overflow: hidden;
        margin-top: 10px;
    }

    .role-selector-trigger {
        padding: 15px 20px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.05);
        transition: background 0.2s;
        user-select: none;
    }

    .role-selector-trigger:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .role-selector-content {
        padding: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(0, 0, 0, 0.2);
        /* display: none; will be handled by JS or inline style */
    }

    .arrow-icon {
        transition: transform 0.3s;
        color: #aaa;
    }

    .role-selector-wrapper.open .arrow-icon {
        transform: rotate(180deg);
        color: #fff;
    }

    @keyframes popIn {
        0% {
            transform: scale(0.5);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* ====== FEED DROPDOWN STYLES (Discord Feed Settings Style) ====== */
    .feed-dropdown-container {
        position: relative;
        width: 100%;
    }

    .feed-dropdown-selected {
        background: linear-gradient(135deg, rgba(30, 30, 35, 0.95), rgba(20, 20, 25, 0.98));
        border: 1px solid rgba(88, 101, 242, 0.3);
        border-radius: 10px;
        padding: 12px 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .feed-dropdown-selected:hover {
        border-color: rgba(88, 101, 242, 0.6);
        box-shadow: 0 4px 20px rgba(88, 101, 242, 0.15);
    }

    .feed-dropdown-container.open .feed-dropdown-selected {
        border-color: #5865f2;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
    }

    .feed-selected-text {
        color: #fff;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .feed-dropdown-arrow {
        color: #5865f2;
        font-size: 0.8rem;
        transition: transform 0.3s ease;
    }

    .feed-dropdown-container.open .feed-dropdown-arrow {
        transform: rotate(180deg);
    }

    .feed-dropdown-menu {
        position: absolute;
        bottom: 100%;
        left: 0;
        right: 0;
        background: rgba(25, 25, 30, 0.98);
        border: 1px solid rgba(88, 101, 242, 0.4);
        border-bottom: none;
        border-radius: 10px 10px 0 0;
        max-height: 350px;
        overflow-y: auto;
        display: none;
        z-index: 1000;
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(20px);
    }

    .feed-dropdown-container.open .feed-dropdown-menu {
        display: block;
        animation: dropdownSlideUp 0.2s ease;
    }

    @keyframes dropdownSlideUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .feed-dropdown-search {
        padding: 10px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        position: sticky;
        top: 0;
        background: rgba(30, 30, 35, 0.98);
    }

    .feed-dropdown-search i {
        color: #666;
        font-size: 0.85rem;
    }

    .feed-dropdown-search input {
        flex: 1;
        background: transparent;
        border: none;
        color: #fff;
        font-size: 0.9rem;
        outline: none;
    }

    .feed-dropdown-search input::placeholder {
        color: #666;
    }

    .feed-dropdown-list {
        padding: 8px;
    }

    .feed-dropdown-category {
        color: #c5a059;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 8px 6px;
        margin-top: 8px;
    }

    .feed-dropdown-category:first-child {
        margin-top: 0;
    }

    .feed-dropdown-item {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        gap: 10px;
    }

    .feed-dropdown-item:hover {
        background: rgba(88, 101, 242, 0.15);
    }

    .feed-dropdown-item.selected {
        background: linear-gradient(135deg, rgba(88, 101, 242, 0.2), rgba(88, 101, 242, 0.3));
    }

    .feed-dropdown-item .channel-icon {
        color: #5865f2;
        font-size: 0.9rem;
        width: 20px;
        text-align: center;
    }

    .feed-dropdown-item .channel-name {
        flex: 1;
        color: #fff;
        font-size: 0.9rem;
    }

    .feed-dropdown-item .channel-badge {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        color: #aaa;
    }

    .feed-dropdown-loading,
    .feed-dropdown-empty {
        text-align: center;
        color: #666;
        padding: 20px;
        font-size: 0.85rem;
    }
</style>

<div class="admin-content" style="max-width: 1200px; margin: 0 auto; padding-bottom: 100px;">

    <!-- TAB NAVIGATION -->
    <div class="tabs-nav"
        style="margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
        <button class="tab-btn <?php echo $activeTab === 'embed' ? 'active' : ''; ?>"
            onclick="openTab(event, 'tab-embed')"><i class="fas fa-pen-fancy"></i> Embed Builder</button>
        <button class="tab-btn <?php echo $activeTab === 'bot' ? 'active' : ''; ?>"
            onclick="openTab(event, 'tab-bot')"><i class="fas fa-robot"></i> Bot Settings</button>
        <button class="tab-btn <?php echo $activeTab === 'welcome' ? 'active' : ''; ?>"
            onclick="openTab(event, 'tab-welcome')"><i class="fas fa-bullhorn"></i> Welcome Settings</button>
        <button class="tab-btn <?php echo $activeTab === 'permissions' ? 'active' : ''; ?>"
            onclick="openTab(event, 'tab-permissions')"><i class="fas fa-user-shield"></i> Permissions</button>
        <button class="tab-btn <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>"
            onclick="openTab(event, 'tab-tickets')"><i class="fas fa-ticket-alt"></i> Tickets</button>
        <button class="tab-btn <?php echo $activeTab === 'roles' ? 'active' : ''; ?>"
            onclick="openTab(event, 'tab-roles')"><i class="fas fa-id-badge"></i> Role Panels</button>
    </div>

    <!-- TAB 1: EMBED BUILDER -->
    <div id="tab-embed" class="tab-content <?php echo $activeTab === 'embed' ? 'active' : ''; ?>">
        <form method="POST" action="" id="embedForm">
            <input type="hidden" name="action" value="send_embed">
            <input type="hidden" name="discord_message_id" id="input_discord_message_id">
            <input type="hidden" name="saved_db_id" id="input_saved_db_id">

            <div class="top-bar-actions">
                <div>
                    <h2>Embed Message System</h2>
                </div>
            </div>

            <!-- SweetAlert2 -->
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <!-- Modern SweetAlert Theme - DEBUG V1 -->
            <style>
                /* Modern Glassmorphism Alert Theme */
                .swal2-popup.modern-alert {
                    background: linear-gradient(135deg, rgba(30, 33, 41, 0.95), rgba(45, 48, 58, 0.9)) !important;
                    backdrop-filter: blur(20px) !important;
                    -webkit-backdrop-filter: blur(20px) !important;
                    border: 1px solid rgba(255, 255, 255, 0.1) !important;
                    border-radius: 16px !important;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05),
                        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
                }

                .swal2-popup.modern-alert .swal2-title {
                    color: #fff !important;
                    font-weight: 600 !important;
                    font-size: 1.5em !important;
                }

                .swal2-popup.modern-alert .swal2-html-container {
                    color: rgba(255, 255, 255, 0.8) !important;
                }

                .swal2-popup.modern-alert .swal2-confirm {
                    background: linear-gradient(135deg, #5865F2, #7289da) !important;
                    border: none !important;
                    border-radius: 8px !important;
                    padding: 12px 28px !important;
                    font-weight: 600 !important;
                    box-shadow: 0 4px 15px rgba(88, 101, 242, 0.4) !important;
                    transition: all 0.2s ease !important;
                }

                .swal2-popup.modern-alert .swal2-confirm:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 6px 20px rgba(88, 101, 242, 0.5) !important;
                }

                .swal2-popup.modern-alert .swal2-cancel {
                    background: rgba(255, 255, 255, 0.1) !important;
                    border: 1px solid rgba(255, 255, 255, 0.2) !important;
                    border-radius: 8px !important;
                    color: #fff !important;
                    padding: 12px 28px !important;
                    font-weight: 500 !important;
                    transition: all 0.2s ease !important;
                }

                .swal2-popup.modern-alert .swal2-cancel:hover {
                    background: rgba(255, 255, 255, 0.15) !important;
                }

                .swal2-popup.modern-alert .swal2-deny {
                    background: linear-gradient(135deg, #f04747, #ff6b6b) !important;
                    border: none !important;
                    border-radius: 8px !important;
                    box-shadow: 0 4px 15px rgba(240, 71, 71, 0.4) !important;
                }

                /* Icon Styles */
                .swal2-popup.modern-alert .swal2-icon.swal2-success {
                    border-color: #43b581 !important;
                    color: #43b581 !important;
                }

                .swal2-popup.modern-alert .swal2-icon.swal2-success .swal2-success-line-tip,
                .swal2-popup.modern-alert .swal2-icon.swal2-success .swal2-success-line-long {
                    background-color: #43b581 !important;
                }

                .swal2-popup.modern-alert .swal2-icon.swal2-warning {
                    border-color: #faa61a !important;
                    color: #faa61a !important;
                }

                .swal2-popup.modern-alert .swal2-icon.swal2-error {
                    border-color: #f04747 !important;
                    color: #f04747 !important;
                }

                .swal2-popup.modern-alert .swal2-icon.swal2-error .swal2-x-mark-line-left,
                .swal2-popup.modern-alert .swal2-icon.swal2-error .swal2-x-mark-line-right {
                    background-color: #f04747 !important;
                }

                /* Checkbox for delete confirmation */
                .swal2-popup.modern-alert .swal2-checkbox {
                    color: rgba(255, 255, 255, 0.8) !important;
                }

                .swal2-popup.modern-alert .swal2-checkbox input {
                    accent-color: #5865F2 !important;
                }

                /* Ensure SweetAlert appears above custom modals */
                .swal2-container {
                    z-index: 99999 !important;
                }

                /* Modern Toast Notifications */
                .swal2-popup.modern-toast {
                    background: linear-gradient(135deg, rgba(30, 33, 41, 0.98), rgba(45, 48, 58, 0.95)) !important;
                    backdrop-filter: blur(15px) !important;
                    border: 1px solid rgba(255, 255, 255, 0.08) !important;
                    border-radius: 12px !important;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3) !important;
                }

                .swal2-popup.modern-toast .swal2-title {
                    color: #fff !important;
                    font-size: 0.95em !important;
                }

                .swal2-timer-progress-bar {
                    background: linear-gradient(90deg, #5865F2, #7289da) !important;
                }

                /* Discord Style Modal (Reference UI) */
                .swal2-popup.discord-modal {
                    background: #2b2d31 !important;
                    /* Discord dark theme modal bg */
                    border-radius: 5px !important;
                    padding: 24px !important;
                    width: 440px !important;
                    box-shadow: 0 2px 10px 0 rgba(0, 0, 0, 0.2) !important;
                    border: none !important;
                }

                .swal2-popup.discord-modal .swal2-title {
                    text-align: left !important;
                    color: #f2f3f5 !important;
                    font-size: 20px !important;
                    font-weight: 700 !important;
                    padding: 0 0 16px 0 !important;
                    display: block !important;
                    width: 100% !important;
                }

                .swal2-popup.discord-modal .swal2-html-container {
                    text-align: left !important;
                    color: #dbdee1 !important;
                    font-size: 16px !important;
                    margin: 0 !important;
                    line-height: 20px !important;
                }

                .swal2-popup.discord-modal .swal2-actions {
                    background: #2b2d31 !important;
                    width: 100% !important;
                    margin: 24px 0 0 0 !important;
                    padding: 16px 16px 16px 0 !important;
                    background: #232428 !important;
                    /* Footer darker bg */
                    border-radius: 0 0 5px 5px !important;
                    /* In SweetAlert2, actions are flex. We need to force them to footer look */
                    justify-content: flex-end !important;
                    position: absolute;
                    bottom: 0;
                    box-sizing: border-box;
                    left: 0;
                }

                /* Adjust popup height to accommodate footer */
                .swal2-popup.discord-modal {
                    padding-bottom: 80px !important;
                }

                .swal2-popup.discord-modal .swal2-cancel {
                    background: transparent !important;
                    color: #fff !important;
                    font-size: 14px !important;
                    font-weight: 500 !important;
                    padding: 10px 24px !important;
                    margin-right: 8px !important;
                    border-radius: 3px !important;
                }

                .swal2-popup.discord-modal .swal2-cancel:hover {
                    text-decoration: underline !important;
                }

                .swal2-popup.discord-modal .swal2-confirm {
                    background: #ed4245 !important;
                    color: #fff !important;
                    font-size: 14px !important;
                    font-weight: 500 !important;
                    border-radius: 3px !important;
                    padding: 10px 24px !important;
                    box-shadow: none !important;
                }

                .swal2-popup.discord-modal .swal2-confirm:hover {
                    background: #c03537 !important;
                }

                /* Custom Modal from manage_media.php */
                .custom-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    backdrop-filter: blur(8px);
                    z-index: 9999;
                    display: none;
                    justify-content: center;
                    align-items: center;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }

                .custom-modal-overlay.active {
                    opacity: 1;
                    display: flex;
                }

                .custom-modal {
                    background: rgba(30, 30, 30, 0.6);
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 16px;
                    width: 100%;
                    max-width: 400px;
                    padding: 0;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                    transform: scale(0.95);
                    transition: transform 0.2s ease;
                    overflow: hidden;
                }

                .custom-modal-overlay.active .custom-modal {
                    transform: scale(1);
                }

                .custom-modal-header {
                    padding: 1.5rem;
                    background: rgba(255, 255, 255, 0.03);
                    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                }

                .custom-modal-title {
                    margin: 0;
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #fff;
                }

                .custom-modal-body {
                    padding: 1.5rem;
                    color: #ddd;
                    font-size: 1rem;
                    line-height: 1.5;
                }

                .custom-modal-footer {
                    padding: 1rem 1.5rem;
                    border-top: 1px solid rgba(255, 255, 255, 0.05);
                    background: rgba(0, 0, 0, 0.2);
                    display: flex;
                    justify-content: flex-end;
                    gap: 0.75rem;
                }

                .c-btn {
                    padding: 0.6rem 1.25rem;
                    border-radius: 8px;
                    font-weight: 500;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                    font-size: 0.95rem;
                }

                .c-btn-cancel {
                    background: transparent;
                    color: #bbb;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                }

                .c-btn-cancel:hover {
                    background: rgba(255, 255, 255, 0.05);
                    color: #fff;
                }

                .c-btn-confirm {
                    background: #5865F2;
                    color: #fff;
                }

                .c-btn-confirm:hover {
                    background: #4752c4;
                    transform: translateY(-1px);
                }

                .c-btn-danger {
                    background: #ef4444;
                    color: #fff;
                }

                .c-btn-danger:hover {
                    background: #dc2626;
                }


                /* Checkbox styling adjustment */
                .swal2-popup.discord-modal .swal2-checkbox {
                    margin: 10px 0 0 0 !important;
                    justify-content: flex-start !important;
                    background: transparent !important;
                    border: none !important;
                }
            </style>

            <script>
                // Override default SweetAlert2 defaults for modern theme
                const modernAlertDefaults = {
                    customClass: {
                        popup: 'modern-alert',
                        confirmButton: 'modern-confirm',
                        cancelButton: 'modern-cancel'
                    },
                    background: 'transparent',
                    color: '#fff',
                    confirmButtonColor: '#5865F2',
                    cancelButtonColor: 'transparent',
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown animate__faster'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp animate__faster'
                    }
                };

                // Create modern Swal mixin
                const ModernSwal = Swal.mixin(modernAlertDefaults);
            </script>

            <?php if ($message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?php echo addslashes($message); ?>',
                            customClass: { popup: 'modern-alert' }
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: '<?php echo addslashes($error); ?>',
                            customClass: { popup: 'modern-alert' }
                        });
                    });
                </script>
            <?php endif; ?>



            <!-- EDITOR AREA -->

            <!-- MODERN TOOLBAR (Independent) -->


            <div class="editor-area">
                <div class="editor-header-flex">
                    <h4 class="card-title" style="margin-bottom:0;">Embed message builder</h4>
                    <!-- MODERN TOOLBAR (Inside Header) -->
                    <div class="custom-toolbar">
                        <button type="button" class="tool-btn" onclick="applyFormat('h1')" title="Heading 1"><i
                                class="fas fa-heading"></i></button>
                        <button type="button" class="tool-btn" onclick="applyFormat('bold')" title="Bold"><i
                                class="fas fa-bold"></i></button>
                        <button type="button" class="tool-btn" onclick="applyFormat('italic')" title="Italic"><i
                                class="fas fa-italic"></i></button>
                        <button type="button" class="tool-btn" onclick="applyFormat('underline')" title="Underline"><i
                                class="fas fa-underline"></i></button>
                    </div>
                </div>
                <p class="card-subtitle">Create your embed by editing the preview directly.</p>

                <div class="discord-message-group">
                    <!-- Avatar Column -->
                    <div class="discord-avatar-col">
                        <img src="/assets/images/logo.png" class="bot-avatar">
                    </div>

                    <!-- Content Column -->
                    <div class="discord-content-col">

                        <!-- Username Header -->
                        <div class="discord-header">
                            <span class="bot-name">Storm Bot</span>
                            <span class="bot-tag">BOT</span>
                            <span class="msg-timestamp">Today at <?php echo date('h:i A'); ?></span>
                        </div>

                        <!-- 1. Message Content Input (Contenteditable for styled mentions) -->
                        <div class="discord-msg-input-wrapper">
                            <input type="hidden" name="message_content" id="messageContentHidden">
                            <div id="messageContentEditor" class="msg-content-editor transparent-input msg-content"
                                contenteditable="true" data-placeholder="Write your message here!"
                                oninput="syncMessageContent(this)"></div>
                        </div>

                        <!-- 2. The Embed Box Wrapper -->

                        <div class="discord-embed-wrapper" id="embedWrapper" style="display: none;">

                            <!-- Left Tools (Color & Edit) -->
                            <div class="side-tools left">
                                <div class="tool-btn color-tool">
                                    <i class="fas fa-palette"></i>
                                    <input type="color" name="embed_color" value="#2196f3"
                                        onchange="updateEmbedColor(this.value)" title="Change Color">
                                </div>
                                <div class="tool-btn active">
                                    <i class="fas fa-pen"></i>
                                </div>
                            </div>

                            <div class="discord-embed-box-container" id="embedContainer">
                                <div class="color-stripe" id="embedStripe" style="background-color: #2196f3;"></div>

                                <!-- Hidden File Inputs for Uploads -->
                                <input type="file" id="file_author_icon" style="display:none"
                                    onchange="uploadImage(this, 'author_icon')">
                                <input type="file" id="file_thumbnail_url" style="display:none"
                                    onchange="uploadImage(this, 'thumbnail_url')">
                                <input type="file" id="file_image_url" style="display:none"
                                    onchange="uploadImage(this, 'image_url')">
                                <input type="file" id="file_footer_icon" style="display:none"
                                    onchange="uploadImage(this, 'footer_icon')">

                                <div class="embed-inner">

                                    <div style="display: flex; gap: 10px;">
                                        <div style="flex: 1;">
                                            <!-- Author -->
                                            <div class="embed-author-row">
                                                <div class="circle-placeholder" id="preview_author_icon"
                                                    onclick="triggerUpload('author_icon')">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                                <input type="hidden" name="author_icon" id="input_author_icon">
                                                <textarea name="author_name" class="transparent-input author-name"
                                                    placeholder="Header" rows="1" oninput="autoGrow(this)"></textarea>
                                            </div>

                                            <!-- Title -->
                                            <textarea name="embed_title" class="transparent-input embed-title"
                                                placeholder="Title" rows="1" oninput="autoGrow(this)"></textarea>

                                            <!-- Description -->
                                            <textarea name="embed_description" class="transparent-input embed-desc"
                                                placeholder="Write Your Message Here!" rows="2"
                                                oninput="autoGrow(this)"></textarea>
                                        </div>

                                        <!-- Thumbnail Placeholder (Top Right) -->
                                        <div class="visual-placeholder thumbnail-box" id="preview_thumbnail_url"
                                            onclick="triggerUpload('thumbnail_url')">
                                            <i class="fas fa-image"></i>
                                        </div>
                                        <input type="hidden" name="thumbnail_url" id="input_thumbnail_url">
                                    </div>

                                    <!-- Fields Container -->
                                    <div id="fields-area"></div>

                                    <!-- Add Field Link -->
                                    <div class="add-field-wrapper">
                                        <button type="button" class="btn-add-inline-link" onclick="addField()">+ Add new
                                            field</button>
                                    </div>

                                    <!-- Big Image Placeholder -->
                                    <div class="visual-placeholder image-box" id="preview_image_url"
                                        onclick="triggerUpload('image_url')">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <input type="hidden" name="image_url" id="input_image_url">

                                    <!-- Footer -->
                                    <div class="embed-footer-row">
                                        <div class="circle-placeholder small" id="preview_footer_icon"
                                            onclick="triggerUpload('footer_icon')">
                                            <i class="fas fa-image"></i>
                                        </div>
                                        <input type="hidden" name="footer_icon" id="input_footer_icon">
                                        <input type="text" name="footer_text" class="transparent-input footer-text"
                                            placeholder="Footer text">
                                    </div>

                                </div>
                            </div>

                            <!-- Right Tools (Delete) -->
                            <div class="side-tools right">
                                <div class="tool-btn danger" onclick="deleteEmbed()" title="Delete / Clear Embed">
                                    <i class="fas fa-trash"></i>
                                </div>
                            </div>

                        </div>

                        <!-- ADD EMBED BUTTON (Visible by default) -->
                        <button type="button" class="btn-add-block" id="btnAddEmbed" onclick="showEmbed()">
                            <i class="fas fa-plus"></i> Add embed
                        </button>

                        <!-- External Image Section (Outside Embed) -->
                        <div class="external-image-section" style="margin: 15px 0;">
                            <input type="file" id="file_external_image_url" style="display:none"
                                onchange="uploadImage(this, 'external_image_url')">
                            <input type="hidden" name="external_image_url" id="input_external_image_url">

                            <!-- External Image Preview (hidden by default) -->
                            <div id="external_image_preview_wrapper" style="display: none; margin-bottom: 10px;">
                                <div style="position: relative; display: inline-block; max-width: 100%;">
                                    <img id="preview_external_image" src=""
                                        style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                                    <button type="button" class="remove-external-img-btn"
                                        onclick="removeExternalImage()"
                                        style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.7); border: none; color: #fff; width: 24px; height: 24px; border-radius: 50%; cursor: pointer;">
                                        <i class="fas fa-times" style="font-size: 12px;"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Add External Image Button -->
                            <button type="button" class="btn-add-block" id="btnAddExternalImage"
                                onclick="triggerUpload('external_image_url')"
                                style="background: rgba(88, 101, 242, 0.1); border: 1px dashed rgba(88, 101, 242, 0.5);">
                                <i class="fas fa-image"></i> Add image (outside embed)
                            </button>
                        </div>

                        <!-- 3. Buttons Area -->

                        <!-- 3. BUTTONS SECTION -->

                        <!-- List of Added Buttons -->
                        <div id="active-buttons-list" class="active-buttons-list"></div>

                        <!-- Button Editor (Hidden by default) -->
                        <div id="button-editor" class="button-editor-panel" style="display: none;">
                            <h5
                                style="color:#b5bac1; font-size:0.75rem; font-weight:700; margin-bottom:10px; text-transform:uppercase;">
                                Button Editor</h5>

                            <!-- Row 1: Preview & URL -->
                            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                                <div class="btn-preview-container">
                                    <button type="button" id="btn-preview" class="btn-visualing">Your button</button>
                                </div>
                                <div style="flex: 1;">
                                    <input type="url" id="edit-btn-url" class="modern-input"
                                        placeholder="Type or Paste URL">
                                </div>
                                <!-- Flatpickr for Date/Time -->
                                <link rel="stylesheet"
                                    href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
                                <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
                                <style>
                                    /* Custom Glassmorphism Theme for Flatpickr */
                                    .flatpickr-calendar {
                                        background: rgba(20, 20, 25, 0.95) !important;
                                        backdrop-filter: blur(15px) !important;
                                        border: 1px solid rgba(255, 255, 255, 0.1) !important;
                                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
                                        border-radius: 12px !important;
                                        font-family: 'Inter', sans-serif !important;
                                    }

                                    .flatpickr-calendar.arrowTop:before,
                                    .flatpickr-calendar.arrowTop:after {
                                        border-bottom-color: rgba(20, 20, 25, 0.95) !important;
                                    }

                                    .flatpickr-months {
                                        background: transparent !important;
                                        padding-top: 10px !important;
                                    }

                                    .flatpickr-months .flatpickr-month {
                                        background: transparent !important;
                                        color: #fff !important;
                                        fill: #fff !important;
                                    }

                                    .flatpickr-current-month .flatpickr-monthDropdown-months,
                                    .flatpickr-current-month input.cur-year {
                                        color: #fff !important;
                                        font-weight: 700 !important;
                                    }

                                    /* Fix Month Dropdown Color */
                                    .flatpickr-current-month .flatpickr-monthDropdown-months {
                                        appearance: none;
                                        -webkit-appearance: none;
                                        background-color: #1e1f24 !important;
                                        border: 1px solid rgba(255, 255, 255, 0.1);
                                        border-radius: 4px;
                                        color: #fff !important;
                                        cursor: pointer;
                                        font-size: 1.1em !important;
                                        font-weight: bold;
                                        padding: 2px 8px;
                                        outline: none;
                                    }

                                    .flatpickr-current-month .flatpickr-monthDropdown-months:hover {
                                        background: #2f3136 !important;
                                    }

                                    .flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month {
                                        background-color: #1e1f24 !important;
                                        color: #fff !important;
                                    }

                                    .flatpickr-current-month input.cur-year {
                                        font-size: 1.1em !important;
                                        font-weight: bold !important;
                                    }

                                    /* Day Numbers Visibility Fix */
                                    .flatpickr-day {
                                        color: #ffffff !important;
                                        opacity: 1 !important;
                                        font-weight: 500 !important;
                                    }

                                    .flatpickr-day.prevMonthDay,
                                    .flatpickr-day.nextMonthDay {
                                        color: rgba(255, 255, 255, 0.3) !important;
                                    }

                                    span.flatpickr-weekday {
                                        color: rgba(255, 255, 255, 0.9) !important;
                                        font-weight: bold !important;
                                    }

                                    /* Time Picker Enhancements - Unified Capsule Design */
                                    .flatpickr-time {
                                        border: 1px solid rgba(255, 255, 255, 0.1) !important;
                                        background: rgba(20, 20, 25, 0.6) !important;
                                        border-radius: 50px !important;
                                        padding: 10px 0 !important;
                                        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
                                        margin: 15px auto !important;
                                        width: 80% !important;
                                        max-width: 300px !important;
                                        display: flex !important;
                                        justify-content: center;
                                        align-items: center;
                                        overflow: hidden !important;
                                    }

                                    .flatpickr-time .flatpickr-time-separator,
                                    .flatpickr-time .flatpickr-am-pm {
                                        color: rgba(255, 255, 255, 0.5) !important;
                                        font-size: 2.5em !important;
                                        font-weight: 300 !important;
                                        margin: 0 5px;
                                    }

                                    .flatpickr-time input {
                                        color: #fff !important;
                                        font-family: 'Inter', sans-serif !important;
                                        font-size: 3em !important;
                                        font-weight: 700 !important;
                                        background: transparent !important;
                                        border: none !important;
                                        box-shadow: none !important;
                                        border-radius: 0 !important;
                                        margin: 0 !important;
                                        height: auto !important;
                                        width: 80px !important;
                                        text-align: center !important;
                                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                                        caret-color: #faa61a;
                                        padding: 5px 0 !important;
                                    }

                                    /* Remove wrapper constraints to allow fluid text */
                                    .flatpickr-time .numInputWrapper {
                                        height: auto !important;
                                        width: auto !important;
                                        margin: 0;
                                        padding: 0 10px;
                                        border: none !important;
                                        background: transparent !important;
                                        box-shadow: none !important;
                                    }

                                    /* Active State (Focus/Hover) */
                                    .flatpickr-time input:hover,
                                    .flatpickr-time input:focus {
                                        color: #faa61a !important;
                                        text-shadow: 0 0 20px rgba(250, 166, 26, 0.4);
                                        transform: scale(1.1);
                                    }

                                    .flatpickr-time input::selection {
                                        background: transparent;
                                        color: #faa61a;
                                    }

                                    /* Hidden Arrows - appear on hover of the number */
                                    .flatpickr-time .numInputWrapper span.arrowUp,
                                    .flatpickr-time .numInputWrapper span.arrowDown {
                                        border: none !important;
                                        color: #faa61a !important;
                                        opacity: 0;
                                        transition: all 0.2s;
                                        right: -5px;
                                        /* Move arrows closer */
                                    }

                                    .flatpickr-time .numInputWrapper:hover span.arrowUp,
                                    .flatpickr-time .numInputWrapper:hover span.arrowDown {
                                        opacity: 1;
                                        transform: translateX(-5px);
                                    }

                                    .flatpickr-time .numInputWrapper span.arrowUp:after {
                                        border-bottom-color: #faa61a !important;
                                    }

                                    .flatpickr-time .numInputWrapper span.arrowDown:after {
                                        border-top-color: #faa61a !important;
                                    }
                                </style>

                            </div>

                            <!-- Row 2: Emoji & Label -->
                            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                <div class="emoji-picker-container">
                                    <label
                                        style="font-size:0.75rem; color:#b5bac1; font-weight:600; margin-bottom:4px; display:block;">Emoji</label>

                                    <!-- Trigger -->
                                    <div class="emoji-picker-trigger" onclick="toggleEmojiPicker()"
                                        id="emojiTriggerBtn">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <input type="hidden" id="edit-btn-emoji" onchange="updateBtnPreview()">

                                    <!-- Picker Modal -->
                                    <div class="emoji-picker-modal" id="emojiPickerModal">
                                        <div class="emoji-search-bar">
                                            <input type="text" class="emoji-search-input" placeholder="Search emoji..."
                                                oninput="filterEmojis(this.value)">
                                        </div>
                                        <div class="emoji-grid" id="emojiGrid">
                                            <!-- Emojis injected via JS -->
                                        </div>
                                    </div>
                                </div>

                                <div style="flex: 1;">
                                    <label
                                        style="font-size:0.75rem; color:#b5bac1; font-weight:600; margin-bottom:4px; display:block;">Label</label>
                                    <input type="text" id="edit-btn-label" class="modern-input"
                                        placeholder="Your button" oninput="updateBtnPreview()">
                                </div>
                            </div>

                            <!-- Actions -->
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="btn secondary sm"
                                    onclick="closeButtonEditor()">Cancel</button>
                                <button type="button" class="btn primary sm" onclick="saveButton()">Save link</button>
                            </div>
                        </div>

                        <!-- Trigger Button -->
                        <button type="button" id="btn-add-link-trigger" class="add-link-button"
                            onclick="openButtonEditor()">
                            <i class="fas fa-plus-circle"></i> Add link button
                        </button>
                    </div>
                </div>
                <!-- DELIVERY SETTINGS SECTION (Moved inside Embed Builder) -->
                <div style="margin-top: 30px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <!-- Delivery Method Selector -->
                    <div style="margin-bottom: 25px;">
                        <label
                            style="color: rgba(255,255,255,0.7); font-size: 0.9rem; display: block; margin-bottom: 12px; font-weight: 500;">
                            <i class="fas fa-paper-plane" style="margin-right: 6px;"></i> Delivery Method
                        </label>
                        <div class="delivery-method-grid"
                            style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                            <!-- Send to Channel -->
                            <label class="delivery-card" id="delivery-channel" style="
                            display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer;
                            padding: 20px 15px; border-radius: 12px;
                            background: linear-gradient(135deg, rgba(88, 101, 242, 0.25), rgba(88, 101, 242, 0.1));
                            border: 2px solid #5865f2;
                            backdrop-filter: blur(10px);
                            transition: all 0.3s ease;
                            text-align: center;
                        ">
                                <input type="radio" name="delivery_method" value="channel" checked
                                    onchange="toggleDeliveryMethod(this.value)" style="display: none;">
                                <div style="
                                width: 50px; height: 50px; border-radius: 12px;
                                background: linear-gradient(135deg, #5865f2, #7289da);
                                display: flex; align-items: center; justify-content: center;
                                box-shadow: 0 4px 15px rgba(88, 101, 242, 0.4);
                            ">
                                    <i class="fas fa-hashtag" style="color: #fff; font-size: 1.3rem;"></i>
                                </div>
                                <span style="color: #fff; font-weight: 500; font-size: 0.9rem;">Send to Channel</span>
                                <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">Post in a text
                                    channel</span>
                            </label>

                            <!-- DM by Role -->
                            <label class="delivery-card" id="delivery-dm_role" style="
                            display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer;
                            padding: 20px 15px; border-radius: 12px;
                            background: rgba(30, 33, 41, 0.6);
                            border: 2px solid transparent;
                            backdrop-filter: blur(10px);
                            transition: all 0.3s ease;
                            text-align: center;
                        ">
                                <input type="radio" name="delivery_method" value="dm_role"
                                    onchange="toggleDeliveryMethod(this.value)" style="display: none;">
                                <div style="
                                width: 50px; height: 50px; border-radius: 12px;
                                background: linear-gradient(135deg, #faa61a, #f8a400);
                                display: flex; align-items: center; justify-content: center;
                                box-shadow: 0 4px 15px rgba(250, 166, 26, 0.3);
                            ">
                                    <i class="fas fa-user-tag" style="color: #fff; font-size: 1.3rem;"></i>
                                </div>
                                <span style="color: #fff; font-weight: 500; font-size: 0.9rem;">DM by Role</span>
                                <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">Message role
                                    members</span>
                            </label>

                            <!-- DM Everyone -->
                            <label class="delivery-card" id="delivery-dm_everyone" style="
                            display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer;
                            padding: 20px 15px; border-radius: 12px;
                            background: rgba(30, 33, 41, 0.6);
                            border: 2px solid transparent;
                            backdrop-filter: blur(10px);
                            transition: all 0.3s ease;
                            text-align: center;
                        ">
                                <input type="radio" name="delivery_method" value="dm_everyone"
                                    onchange="toggleDeliveryMethod(this.value)" style="display: none;">
                                <div style="
                                width: 50px; height: 50px; border-radius: 12px;
                                background: linear-gradient(135deg, #43b581, #3ca374);
                                display: flex; align-items: center; justify-content: center;
                                box-shadow: 0 4px 15px rgba(67, 181, 129, 0.3);
                            ">
                                    <i class="fas fa-users" style="color: #fff; font-size: 1.3rem;"></i>
                                </div>
                                <span style="color: #fff; font-weight: 500; font-size: 0.9rem;">DM Everyone</span>
                                <span style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">Message all
                                    members</span>
                            </label>
                        </div>
                    </div>

                    <!-- Channel Selector (for "channel" delivery method) -->
                    <div id="channelSelectorWrapper" style="margin-bottom: 20px;">
                        <input type="hidden" name="channel_id" id="embedChannelInput" class="real-channel-input">
                        <div class="feed-dropdown-container" id="embedChannelDropdown">
                            <div class="feed-dropdown-selected" onclick="toggleFeedDropdown('embedChannelDropdown')">
                                <span class="feed-selected-text" id="embedChannelText">Select a channel</span>
                                <i class="fas fa-chevron-down feed-dropdown-arrow"></i>
                            </div>
                            <div class="feed-dropdown-menu">
                                <div class="feed-dropdown-search">
                                    <i class="fas fa-search"></i>
                                    <input type="text" placeholder="Search a channel"
                                        oninput="filterFeedChannels('embedChannelDropdown', this.value)">
                                </div>
                                <div class="feed-dropdown-list" id="embedChannelList">
                                    <?php renderFeedChannelOptions($channels); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Role Selector (for "dm_role" delivery method) -->
                    <div id="roleSelectorWrapper" style="margin-bottom: 20px; display: none;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                            <i class="fas fa-at"></i> Select Role to DM
                        </label>
                        <select name="dm_role_id" id="dmRoleSelect" class="modern-input"
                            style="width: 100%; padding: 12px; background: #2f3136; border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 6px;">
                            <option value="">-- Select a Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                    style="color: <?php echo $role['color'] ?? '#fff'; ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #72767d; margin-top: 5px; display: block;">
                            <i class="fas fa-info-circle"></i> Members with this role will receive a DM.
                        </small>
                    </div>

                    <!-- DM Everyone Warning -->
                    <div id="dmEveryoneWarning"
                        style="margin-bottom: 20px; display: none; background: rgba(250, 166, 26, 0.1); border: 1px solid #faa61a; border-radius: 8px; padding: 15px;">
                        <div style="display: flex; align-items: center; gap: 10px; color: #faa61a;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
                            <strong>Warning</strong>
                        </div>
                        <p style="color: #b5bac1; margin: 10px 0 0 0; font-size: 0.9rem;">
                            This will send a DM to <strong>all members</strong> in your server. Discord may rate-limit
                            messages on large servers.
                        </p>
                    </div>

                    <!-- Send Button -->
                    <div style="text-align: right; margin-top: 10px;">
                        <button type="submit" class="btn primary" id="btn-submit-embed"
                            style="padding: 12px 40px; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(88, 101, 242, 0.4);">
                            <i class="fas fa-paper-plane" style="margin-right:8px;"></i> Send
                        </button>
                    </div>
                </div>
            </div>


        </form>
        <!-- SAVED MESSAGES PANEL -->
        <div class="glass-panel" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="color: #fff; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-archive" style="color: #5865f2;"></i> Saved Messages
                </h3>
                <button type="button" class="btn secondary sm" onclick="loadSavedMessages()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <p style="color: #b5bac1; margin-bottom: 15px; font-size: 0.9em;">
                Manage messages you've sent. Click Edit to modify a published message directly in Discord.
            </p>

            <div id="savedMessagesList" style="display: grid; gap: 10px;">
                <div style="text-align: center; padding: 30px; color: #72767d;">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    No saved messages yet.
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: EVENT MANAGER (MOVED TO manage_operations.php) -->

    <!-- TAB 3: BOT SETTINGS -->
    <div id="tab-bot" class="tab-content <?php echo $activeTab === 'bot' ? 'active' : '' ?>">
        <?php
        $botStatus = $api->getBotStatus();
        $isOnline = $botStatus && isset($botStatus['online']) && $botStatus['online'];

        // Fetch Welcome Sound Settings
        $welcomeSettingsResp = $api->request('/settings/welcome-sound', [], 'GET');
        // Note: Response is nested as data->data due to BotAPI wrapper
        $welcomeData = [];
        if ($welcomeSettingsResp['success'] && isset($welcomeSettingsResp['data']['data'])) {
            $welcomeData = $welcomeSettingsResp['data']['data'];
        }
        $wsEnabled = $welcomeData['enabled'] ?? false;
        $wsChannelId = $welcomeData['channel_id'] ?? '';
        $wsDelay = $welcomeData['delay'] ?? 2;
        $wsHasFile = $welcomeData['has_file'] ?? false;
        $wsHasFile = $welcomeData['has_file'] ?? false;
        $wsFilename = $welcomeData['filename'] ?? '';
        $wsMessageText = $welcomeData['message_text'] ?? '';
        $wsDropdownOptions = $welcomeData['dropdown_options'] ?? [];
        $wsLinkButtons = $welcomeData['link_buttons'] ?? [];

        // Get current voice channel from bot status
        $currentVoiceChannelId = '';
        if ($botStatus && isset($botStatus['current_voice_channel']['id'])) {
            $currentVoiceChannelId = $botStatus['current_voice_channel']['id'];
        }
        ?>

        <!-- Bot Status Card -->
        <div class="glass-panel" style="margin-bottom: 25px;">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-robot"></i> Bot Status</h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <!-- Online Status -->
                <div class="stat-card"
                    style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 2em; margin-bottom: 10px;">
                        <?php if ($isOnline): ?>
                            <span style="color: #43b581;">🟢</span>
                        <?php else: ?>
                            <span style="color: #f04747;">🔴</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-weight: bold; color: <?php echo $isOnline ? '#43b581' : '#f04747'; ?>;">
                        <?php echo $isOnline ? 'ONLINE' : 'OFFLINE'; ?>
                    </div>
                </div>

                <!-- Bot Name -->
                <div class="stat-card"
                    style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="margin-bottom: 5px; opacity: 0.7; font-size: 0.85em;">Bot Name</div>
                    <div style="font-weight: bold; font-size: 1.2em;">
                        <?php echo htmlspecialchars($botStatus['name'] ?? 'Unknown'); ?>
                    </div>
                    <?php if ($botStatus && isset($botStatus['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($botStatus['avatar']); ?>"
                            style="width: 40px; height: 40px; border-radius: 50%; margin-top: 10px;">
                    <?php endif; ?>
                </div>

                <!-- Guilds -->
                <div class="stat-card"
                    style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="margin-bottom: 5px; opacity: 0.7; font-size: 0.85em;">Connected Servers</div>
                    <div style="font-weight: bold; font-size: 1.5em; color: #5865f2;">
                        <?php echo $botStatus['guilds'] ?? 0; ?>
                    </div>
                </div>

                <!-- Uptime -->
                <div class="stat-card"
                    style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="margin-bottom: 5px; opacity: 0.7; font-size: 0.85em;">Uptime</div>
                    <div style="font-weight: bold; font-size: 1.2em; color: #faa61a;">
                        <?php echo $botStatus['uptime'] ?? '-'; ?>
                    </div>
                </div>

                <!-- Latency -->
                <div class="stat-card"
                    style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="margin-bottom: 5px; opacity: 0.7; font-size: 0.85em;">Latency</div>
                    <div style="font-weight: bold; font-size: 1.2em; color: #00b0f4;">
                        <?php echo ($botStatus['latency_ms'] ?? 0) . ' ms'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Voice Control Panel -->
        <div class="glass-panel" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px; display:flex; align-items:center; gap:10px; color:#fff;"><i
                    class="fas fa-microphone-alt" style="color:#5865f2;"></i> Voice Control</h3>

            <div
                style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px;">
                <div style="display:flex; gap:10px; flex:1; align-items:center;">
                    <div style="flex:1; min-width: 200px;">
                        <input type="hidden" id="voiceChannelSelect"
                            value="<?php echo htmlspecialchars($currentVoiceChannelId); ?>">
                        <div class="feed-dropdown-container" id="voiceControlDropdown">
                            <div class="feed-dropdown-selected" onclick="toggleFeedDropdown('voiceControlDropdown')">
                                <span class="feed-selected-text" id="voiceControlText">
                                    <?php
                                    if (!empty($currentVoiceChannelId)) {
                                        foreach ($voiceChannels as $vc) {
                                            if ((string) $vc['id'] === (string) $currentVoiceChannelId) {
                                                echo '<i class="fas fa-volume-up" style="color: #43b581;"></i> ' . htmlspecialchars($vc['name']);
                                                break;
                                            }
                                        }
                                    } else {
                                        echo 'Select Voice Channel';
                                    }
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down feed-dropdown-arrow"></i>
                            </div>
                            <div class="feed-dropdown-menu">
                                <div class="feed-dropdown-search">
                                    <i class="fas fa-search"></i>
                                    <input type="text" placeholder="Search a channel"
                                        oninput="filterFeedChannels('voiceControlDropdown', this.value)">
                                </div>
                                <div class="feed-dropdown-list" id="voiceControlList">
                                    <?php renderFeedVoiceChannelOptions($voiceChannels); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn primary" onclick="joinVoiceChannel()"><i class="fas fa-plug"></i>
                        Join Voice</button>
                </div>

                <div style="width: 1px; height: 30px; background: rgba(255,255,255,0.1); margin: 0 10px;"></div>

                <button type="button" class="btn danger" onclick="leaveVoiceChannel()"><i
                        class="fas fa-phone-slash"></i> Leave Voice</button>
            </div>
        </div>





        <script>
            // Welcome Sound Management
            let pendingSoundFile = null; // Store file to be uploaded
            let shouldDeleteSound = false; // Flag to indicate if file should be deleted on server

            // Dropdown Options Management
            let dropdownOptions = <?php echo json_encode($wsDropdownOptions); ?>;
            let linkButtons = <?php echo json_encode($wsLinkButtons); ?>;

            // State to track collapsed items (sets of indices)
            let collapsedDropdowns = new Set();
            let collapsedButtons = new Set();

            // Initialize all as collapsed by default for cleaner UI? Or expanded?
            // Let's expand by default if empty, collapse if many. For now, empty set = all expanded.
            // User asked for "button to press to show content", implying COLLAPSED by default logic or toggle.
            // Let's initialize with all indices collapsed if they exist.
            dropdownOptions.forEach((_, i) => collapsedDropdowns.add(i));
            linkButtons.forEach((_, i) => collapsedButtons.add(i));

            // --- RENDERERS ---

            function renderDropdownOptions() {
                const container = document.getElementById('dropdownOptionsList');
                container.innerHTML = '';

                dropdownOptions.forEach((opt, index) => {
                    const isCollapsed = collapsedDropdowns.has(index);
                    const block = document.createElement('div');
                    block.className = 'field-block sortable-item';
                    block.draggable = true; // Enable Drag
                    block.dataset.index = index;
                    block.dataset.type = 'dropdown';

                    // Style
                    block.style.background = 'rgba(0,0,0,0.2)';
                    block.style.borderRadius = '8px';
                    block.style.marginBottom = '10px';
                    block.style.border = '1px solid rgba(255,255,255,0.05)';
                    block.style.overflow = 'hidden';

                    // Drag Events
                    block.addEventListener('dragstart', handleDragStart);
                    block.addEventListener('dragover', handleDragOver);
                    block.addEventListener('drop', handleDrop);
                    block.addEventListener('dragend', handleDragEnd);

                    const labelPreview = opt.label || '(No Label)';

                    block.innerHTML = `
                        <!-- Header (Visible when collapsed) -->
                        <div class="block-header" style="background: rgba(255,255,255,0.03); padding: 12px 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;" onclick="toggleCollapse('dropdown', ${index})">
                            <i class="fas fa-grip-vertical" style="color: #72767d; cursor: grab; padding-right: 5px;"></i>
                            <i class="fas fa-chevron-${isCollapsed ? 'right' : 'down'}" style="color: #b5bac1; font-size: 0.8em; width: 15px;"></i>
                            <span style="flex: 1; font-weight: 500; color: #fff;">${labelPreview}</span>
                            <div class="actions" onclick="event.stopPropagation()">
                                <button type="button" class="btn danger sm" style="padding: 4px 8px;" onclick="removeDropdownOption(${index})"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>

                        <!-- Body (Hidden when collapsed) -->
                        <div class="block-body" style="padding: 15px; border-top: 1px solid rgba(255,255,255,0.05); display: ${isCollapsed ? 'none' : 'block'};">
                            <div style="margin-bottom: 10px;">
                                <label style="font-size: 0.75rem; color: #b5bac1; display: block; margin-bottom: 5px;">Label</label>
                                <input type="text" class="modern-input" style="width: 100%;" placeholder="Menu Option Name" value="${opt.label}" oninput="updateDropdownOption(${index}, 'label', this.value)">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: #b5bac1; display: block; margin-bottom: 5px;">Response Message</label>
                                <textarea class="modern-input" style="width: 100%; font-family: 'Consolas', monospace; resize: vertical;" rows="4" placeholder="Message sent to user when selected..." oninput="updateDropdownOption(${index}, 'response_text', this.value)">${opt.response_text}</textarea>
                            </div>
                        </div>
                    `;
                    container.appendChild(block);
                });
            }

            function renderLinkButtons() {
                const container = document.getElementById('linkButtonsList');
                container.innerHTML = '';

                linkButtons.forEach((btn, index) => {
                    const isCollapsed = collapsedButtons.has(index);
                    const block = document.createElement('div');
                    block.className = 'field-block sortable-item';
                    block.draggable = true;
                    block.dataset.index = index;
                    block.dataset.type = 'button';

                    block.style.background = 'rgba(0,0,0,0.2)';
                    block.style.borderRadius = '8px';
                    block.style.marginBottom = '10px';
                    block.style.border = '1px solid rgba(255,255,255,0.05)';
                    block.style.overflow = 'hidden';

                    // Drag Events
                    block.addEventListener('dragstart', handleDragStart);
                    block.addEventListener('dragover', handleDragOver);
                    block.addEventListener('drop', handleDrop);
                    block.addEventListener('dragend', handleDragEnd);

                    const labelPreview = btn.label || '(No Label)';

                    block.innerHTML = `
                         <!-- Header -->
                        <div class="block-header" style="background: rgba(255,255,255,0.03); padding: 12px 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;" onclick="toggleCollapse('button', ${index})">
                            <i class="fas fa-grip-vertical" style="color: #72767d; cursor: grab; padding-right: 5px;"></i>
                            <i class="fas fa-link" style="color: #43b581; font-size: 0.9em;"></i>
                            <span style="flex: 1; font-weight: 500; color: #fff;">${labelPreview}</span>
                            <div class="actions" onclick="event.stopPropagation()">
                                <button type="button" class="btn danger sm" style="padding: 4px 8px;" onclick="removeLinkButton(${index})"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="block-body" style="padding: 15px; border-top: 1px solid rgba(255,255,255,0.05); display: ${isCollapsed ? 'none' : 'block'};">
                            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #b5bac1; display: block; margin-bottom: 5px;">Button Label</label>
                                    <input type="text" class="modern-input" style="width: 100%;" placeholder="e.g. Visit Website" value="${btn.label}" oninput="updateLinkButton(${index}, 'label', this.value)">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #b5bac1; display: block; margin-bottom: 5px;">URL</label>
                                    <input type="url" class="modern-input" style="width: 100%;" placeholder="https://..." value="${btn.url}" oninput="updateLinkButton(${index}, 'url', this.value)">
                                </div>
                            </div>
                        </div>
                    `;
                    container.appendChild(block);
                });
            }

            // --- DRAG AND DROP LOGIC ---
            let dragSrcEl = null;

            function handleDragStart(e) {
                dragSrcEl = this;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
                this.style.opacity = '0.4';
            }

            function handleDragOver(e) {
                if (e.preventDefault) {
                    e.preventDefault(); // Necessary. Allows us to drop.
                }
                e.dataTransfer.dropEffect = 'move';
                return false;
            }

            function handleDrop(e) {
                if (e.stopPropagation) {
                    e.stopPropagation(); // stops the browser from redirecting.
                }

                // Don't do anything if dropping the same column we're dragging.
                if (dragSrcEl !== this) {
                    // Check if we are dropping on the same type
                    if (dragSrcEl.dataset.type !== this.dataset.type) return;

                    const type = this.dataset.type;
                    const fromIndex = parseInt(dragSrcEl.dataset.index);
                    const toIndex = parseInt(this.dataset.index);

                    // Reorder Array
                    if (type === 'dropdown') {
                        const item = dropdownOptions.splice(fromIndex, 1)[0];
                        dropdownOptions.splice(toIndex, 0, item);
                        // Reset Collapse states (simple way: clear and default to collapsed)
                        collapsedDropdowns.clear();
                        dropdownOptions.forEach((_, i) => collapsedDropdowns.add(i)); // Collapse all after reorder
                        renderDropdownOptions();
                    } else if (type === 'button') {
                        const item = linkButtons.splice(fromIndex, 1)[0];
                        linkButtons.splice(toIndex, 0, item);
                        collapsedButtons.clear();
                        linkButtons.forEach((_, i) => collapsedButtons.add(i));
                        renderLinkButtons();
                    }
                }
                return false;
            }

            function handleDragEnd(e) {
                this.style.opacity = '1';
                // Remove visual cues if any
            }


            // --- ACTIONS ---

            function toggleCollapse(type, index) {
                if (type === 'dropdown') {
                    if (collapsedDropdowns.has(index)) collapsedDropdowns.delete(index);
                    else collapsedDropdowns.add(index);
                    renderDropdownOptions();
                } else {
                    if (collapsedButtons.has(index)) collapsedButtons.delete(index);
                    else collapsedButtons.add(index);
                    renderLinkButtons();
                }
            }

            function addDropdownOption(label = '', response_text = '') {
                dropdownOptions.push({ label: label, response_text: response_text });
                // Automatically expand the new item
                // The new item is at length-1. It is NOT in collapsed set, so it is expanded.
                renderDropdownOptions();
            }

            function removeDropdownOption(index) {
                dropdownOptions.splice(index, 1);
                // Re-calculate collapsed indices is tricky. 
                // Simplest: Clear collapse state or just render. 
                // If we remove index 0, index 1 becomes 0. If 1 was collapsed, 0 is now collapsed.
                // We should probably shift the set, but for MVP re-render works.
                // Indices shift, so we might lose collapse state accuracy. 
                // Let's reset collapse state to "Collapse All" for safety or keep as is?
                // Keeping as is might look weird. Let's reset.
                collapsedDropdowns.clear();
                dropdownOptions.forEach((_, i) => collapsedDropdowns.add(i));
                renderDropdownOptions();
            }

            function updateDropdownOption(index, field, value) {
                dropdownOptions[index][field] = value;
                // If label updates, we might want to update preview without full re-render to avoid losing focus
                // But for now full re-render is safer for logic, though it kills focus.
                // Wait, full render KILLS FOCUS on input. BAD.
                // We should ONLY update data. Render only on Add/Remove/Collapse.

                // Update Header preview specific logic if label changed
                if (field === 'label') {
                    // Find the header span
                    const block = document.querySelector(`#dropdownOptionsList .sortable-item[data-index="${index}"]`);
                    if (block) {
                        const span = block.querySelector('.block-header span');
                        if (span) span.textContent = value || '(No Label)';
                    }
                }
            }

            // Link Button Actions
            function addLinkButton(label = '', url = '') {
                linkButtons.push({ label: label, url: url });
                renderLinkButtons();
            }

            function removeLinkButton(index) {
                linkButtons.splice(index, 1);
                collapsedButtons.clear();
                linkButtons.forEach((_, i) => collapsedButtons.add(i));
                renderLinkButtons();
            }

            function updateLinkButton(index, field, value) {
                linkButtons[index][field] = value;
                if (field === 'label') {
                    const block = document.querySelector(`#linkButtonsList .sortable-item[data-index="${index}"]`);
                    if (block) {
                        const span = block.querySelector('.block-header span');
                        if (span) span.textContent = value || '(No Label)';
                    }
                }
            }

            function getDropdownOptions() { return dropdownOptions; }
            function getLinkButtons() { return linkButtons; }

            document.addEventListener('DOMContentLoaded', () => {
                setupDragAndDrop(); // For file upload
                renderDropdownOptions();
                renderLinkButtons();
                // Load Server Welcome Settings
                // loadServerWelcomeSettings(); // Moved to bottom to ensure function definition
            });


            // --- SERVER WELCOME SETTINGS JS MOVED TO BOTTOM ---


            function setupDragAndDrop() {
                const zone = document.getElementById('soundUploadZone');

                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zone.style.borderColor = '#5865f2';
                    zone.style.background = 'rgba(88, 101, 242, 0.1)';
                });

                zone.addEventListener('dragleave', () => {
                    zone.style.borderColor = 'rgba(255,255,255,0.2)';
                    zone.style.background = 'rgba(0,0,0,0.1)';
                });

                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    zone.style.borderColor = 'rgba(255,255,255,0.2)';
                    zone.style.background = 'rgba(0,0,0,0.1)';

                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        handleFile(files[0]);
                    }
                });
            }

            function handleSoundFileSelect(event) {
                const file = event.target.files[0];
                if (file) {
                    handleFile(file);
                }
            }

            function handleFile(file) {
                // Validate file type
                if (!file.type.includes('audio/mpeg') && !file.name.endsWith('.mp3')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid File Type',
                        text: 'Please upload an MP3 file only.',
                        customClass: { popup: 'modern-alert' }
                    });
                    return;
                }

                // Validate file size (5MB max)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Too Large',
                        text: 'Maximum file size is 5MB. Your file is ' + (file.size / 1024 / 1024).toFixed(2) + 'MB.',
                        customClass: { popup: 'modern-alert' }
                    });
                    return;
                }

                // Store file for upload
                pendingSoundFile = file;

                // Update UI
                document.getElementById('uploadPlaceholder').style.display = 'none';
                document.getElementById('uploadedFileInfo').style.display = 'block';
                document.getElementById('uploadedFileName').textContent = file.name;
                document.getElementById('uploadedFileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';

                // Show audio preview
                const audioPreview = document.getElementById('audioPreview');
                audioPreview.src = URL.createObjectURL(file);
                document.getElementById('audioPreviewContainer').style.display = 'block';

                // Show delete button
                document.getElementById('deleteSoundBtn').style.display = 'inline-flex';

                // Auto-save with file
                saveWelcomeSound();
            }

            async function loadWelcomeSoundSettings() {
                try {
                    const response = await fetch('../includes/bot_api_proxy.php?action=get_welcome_sound&t=' + new Date().getTime());
                    const result = await response.json();

                    if (!result || !result.data) return;
                    
                    // Handle double-wrapped response: {success, data: {success, data: {...}}}
                    let data = result.data;
                    if (data && data.data) {
                        data = data.data; // Unwrap second layer
                    }

                    document.getElementById('welcomeSoundEnabled').checked = data.enabled == true || data.enabled == 'true';
                    // Set channel ID
                    const channelId = data.channel_id || '';
                    document.getElementById('welcomeSoundChannel').value = channelId;
                    
                    // Update visual dropdown text if a channel is selected
                    if (channelId) {
                        const item = document.querySelector(`#welcomeSoundChannelList .feed-dropdown-item[data-id="${channelId}"]`);
                        if (item) {
                            const name = item.dataset.name;
                            const html = `<i class="fas fa-volume-up" style="color: #43b581;"></i> ${name}`;
                            document.getElementById('welcomeSoundChannelText').innerHTML = html;
                            // Also sync DM section dropdown
                            const dmText = document.getElementById('voiceWelcomeDmChannelText');
                            if (dmText) dmText.innerHTML = html;
                        } else {
                            document.getElementById('welcomeSoundChannelText').innerText = 'Unknown Channel';
                        }
                    } else {
                        const defaultHtml = '<i class="fas fa-volume-up" style="color: #43b581;"></i> All Voice Channels';
                        document.getElementById('welcomeSoundChannelText').innerHTML = defaultHtml;
                        const dmText = document.getElementById('voiceWelcomeDmChannelText');
                        if (dmText) dmText.innerHTML = defaultHtml;
                    }

                    // Set Delay
                    const delay = data.delay !== undefined ? data.delay : 2;
                    document.getElementById('welcomeSoundDelay').value = delay;
                    document.getElementById('delayValue').textContent = delay + 's';

                    updateToggleStyle();

                    // Show file info if file exists
                    if (data.has_file && data.filename) {
                        document.getElementById('uploadPlaceholder').style.display = 'none';
                        document.getElementById('uploadedFileInfo').style.display = 'block';
                        document.getElementById('uploadedFileName').textContent = data.filename;
                        document.getElementById('uploadedFileSize').textContent = 'Uploaded';
                        document.getElementById('deleteSoundBtn').style.display = 'inline-flex';
                    }
                    
                    // Load message text
                    if (data.message_text) {
                        const msgEl = document.getElementById('welcomeMessageText');
                        if (msgEl) msgEl.value = data.message_text;
                    }
                    
                    // Load dropdown options
                    if (data.dropdown_options && Array.isArray(data.dropdown_options)) {
                        dropdownOptions = [];
                        data.dropdown_options.forEach(opt => {
                            if (typeof addDropdownOption === 'function') {
                                addDropdownOption(opt.label || '', opt.response_text || '');
                            }
                        });
                    }
                    
                    // Load link buttons
                    if (data.link_buttons && Array.isArray(data.link_buttons)) {
                        linkButtons = [];
                        data.link_buttons.forEach(btn => {
                            if (typeof addLinkButton === 'function') {
                                addLinkButton(btn.label || '', btn.url || '');
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error loading welcome sound settings:', error);
                }
            }

            async function saveWelcomeSound() {
                const statusEl = document.getElementById('welcomeSoundStatus');
                statusEl.textContent = 'Saving...';
                statusEl.style.color = '#faa61a';

                try {
                    const payload = {
                        enabled: document.getElementById('welcomeSoundEnabled').checked,
                        channel_id: document.getElementById('welcomeSoundChannel').value,
                        delay: parseInt(document.getElementById('welcomeSoundDelay').value) || 0,
                        message_text: document.getElementById('welcomeMessageText').value,
                        message_text: document.getElementById('welcomeMessageText').value,
                        dropdown_options: getDropdownOptions(),
                        link_buttons: getLinkButtons(),
                        delete_file: shouldDeleteSound
                    };

                    // Reset delete flag immediately so subsequent saves don't keep deleting unless requested
                    shouldDeleteSound = false;

                    // Include file if pending
                    if (pendingSoundFile) {
                        const reader = new FileReader();
                        reader.onload = async function (e) {
                            // Get base64 without the data URL prefix
                            const base64 = e.target.result.split(',')[1];
                            payload.sound_base64 = base64;
                            payload.filename = pendingSoundFile.name;

                            await sendSaveRequest(payload, statusEl);
                            pendingSoundFile = null; // Clear pending file
                        };
                        reader.readAsDataURL(pendingSoundFile);
                    } else {
                        await sendSaveRequest(payload, statusEl);
                    }
                } catch (error) {
                    console.error('Error saving welcome sound:', error);
                    statusEl.textContent = '✗ Network error';
                    statusEl.style.color = '#f04747';
                }
            }

            async function sendSaveRequest(payload, statusEl) {
                const response = await fetch('../includes/bot_api_proxy.php?action=set_welcome_sound', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (data.success) {
                    statusEl.textContent = '✓ Saved!';
                    statusEl.style.color = '#43b581';
                    updateToggleStyle();
                } else {
                    console.error('Save error data:', data);
                    statusEl.textContent = '✗ Error: ' + (data.error || 'Unknown');
                    statusEl.style.color = '#f04747';
                }

                setTimeout(() => { if (statusEl.textContent.includes('Saved')) statusEl.textContent = ''; }, 3000);
            }

            function updateToggleStyle() {
                const checkbox = document.getElementById('welcomeSoundEnabled');
                const slider = checkbox.nextElementSibling;
                slider.style.background = checkbox.checked ? '#43b581' : '#72767d';
            }

            // --- Server Leave Settings ---
            function updateLeaveToggleStyle() {
                const checkbox = document.getElementById('serverLeaveEnabled');
                const slider = document.getElementById('serverLeaveSlider');
                slider.style.background = checkbox.checked ? '#43b581' : '#72767d';
            }

            function toggleServerLeaveEmbedOptions() {
                const useEmbed = document.getElementById('serverLeaveUseEmbed').checked;
                document.getElementById('serverLeaveEmbedOptions').style.display = useEmbed ? 'block' : 'none';
                const slider = document.getElementById('serverLeaveUseEmbed').nextElementSibling;
                slider.style.background = useEmbed ? '#43b581' : '#72767d';
            }

            async function loadServerLeaveSettings() {
                try {
                    const response = await fetch('../includes/bot_api_proxy.php?action=get_server_leave&t=' + new Date().getTime());
                    const result = await response.json();

                    if (!result || !result.data) return;
                    
                    // Handle double-wrapped response: {success, data: {success, data: {...}}}
                    let data = result.data;
                    if (data && data.data) {
                        data = data.data; // Unwrap second layer
                    }

                    document.getElementById('serverLeaveEnabled').checked = data.enabled == true || data.enabled == 'true';
                    document.getElementById('serverLeaveMessage').value = data.message_content || '';
                    
                    // Set channel ID
                    const channelId = data.channel_id || '';
                    document.getElementById('serverLeaveChannelId').value = channelId;
                    
                    // Update visual dropdown text
                    if (channelId) {
                        const item = document.querySelector(`#serverLeaveChannelList .feed-dropdown-item[data-id="${channelId}"]`);
                        if (item) {
                            document.getElementById('serverLeaveChannelText').innerHTML = `<i class="fas fa-hashtag" style="color: #5865f2;"></i> ${item.dataset.name}`;
                        }
                    }
                    
                    // Embed
                    const useEmbed = data.use_embed == true || data.use_embed == 'true';
                    document.getElementById('serverLeaveUseEmbed').checked = useEmbed;
                    
                    if (data.embed_data) {
                        document.getElementById('serverLeaveEmbedTitle').value = data.embed_data.title || '';
                        document.getElementById('serverLeaveEmbedDescription').value = data.embed_data.description || '';
                        document.getElementById('serverLeaveEmbedColor').value = data.embed_data.color || '#f04747';
                    }
                    
                    updateLeaveToggleStyle();
                    toggleServerLeaveEmbedOptions();
                } catch (error) {
                    console.error('Error loading server leave settings:', error);
                }
            }

            async function saveServerLeaveSettings() {
                const statusEl = document.getElementById('serverLeaveStatus');
                statusEl.textContent = 'Saving...';
                statusEl.style.color = '#faa61a';

                try {
                    const payload = {
                        enabled: document.getElementById('serverLeaveEnabled').checked,
                        channel_id: document.getElementById('serverLeaveChannelId').value,
                        message_content: document.getElementById('serverLeaveMessage').value,
                        use_embed: document.getElementById('serverLeaveUseEmbed').checked,
                        embed_data: {
                            title: document.getElementById('serverLeaveEmbedTitle').value,
                            description: document.getElementById('serverLeaveEmbedDescription').value,
                            color: document.getElementById('serverLeaveEmbedColor').value
                        }
                    };

                    const response = await fetch('../includes/bot_api_proxy.php?action=set_server_leave', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (data.success) {
                        statusEl.textContent = '✓ Saved!';
                        statusEl.style.color = '#43b581';
                    } else {
                        statusEl.textContent = '✗ Error: ' + (data.error || 'Unknown');
                        statusEl.style.color = '#f04747';
                    }

                    setTimeout(() => { if (statusEl.textContent.includes('Saved')) statusEl.textContent = ''; }, 3000);
                } catch (error) {
                    console.error('Save error:', error);
                    statusEl.textContent = '✗ Network error';
                    statusEl.style.color = '#f04747';
                }
            }

            // --- Voice Logs Settings ---
            function updateVoiceLogsToggleStyle() {
                const checkbox = document.getElementById('voiceLogsEnabled');
                const slider = document.getElementById('voiceLogsSlider');
                slider.style.background = checkbox.checked ? '#43b581' : '#72767d';
            }

            async function loadVoiceLogsSettings() {
                try {
                    const response = await fetch('../includes/bot_api_proxy.php?action=get_voice_logs&t=' + new Date().getTime());
                    const result = await response.json();

                    if (!result || !result.data) return;
                    
                    // Handle double-wrapped response: {success, data: {success, data: {...}}}
                    let data = result.data;
                    if (data && data.data) {
                        data = data.data; // Unwrap second layer
                    }

                    document.getElementById('voiceLogsEnabled').checked = data.enabled == true || data.enabled == 'true';
                    
                    // Set channel ID
                    const channelId = data.channel_id || '';
                    document.getElementById('voiceLogsChannelId').value = channelId;
                    
                    // Update visual dropdown text
                    if (channelId) {
                        const item = document.querySelector(`#voiceLogsChannelList .feed-dropdown-item[data-id="${channelId}"]`);
                        if (item) {
                            document.getElementById('voiceLogsChannelText').innerHTML = `<i class="fas fa-hashtag" style="color: #5865f2;"></i> ${item.dataset.name}`;
                        }
                    }
                    
                    updateVoiceLogsToggleStyle();
                } catch (error) {
                    console.error('Error loading voice logs settings:', error);
                }
            }

            async function saveVoiceLogsSettings() {
                const statusEl = document.getElementById('voiceLogsStatus');
                statusEl.textContent = 'Saving...';
                statusEl.style.color = '#faa61a';

                try {
                    const payload = {
                        enabled: document.getElementById('voiceLogsEnabled').checked,
                        channel_id: document.getElementById('voiceLogsChannelId').value
                    };

                    const response = await fetch('../includes/bot_api_proxy.php?action=set_voice_logs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (data.success) {
                        statusEl.textContent = '✓ Saved!';
                        statusEl.style.color = '#43b581';
                    } else {
                        statusEl.textContent = '✗ Error: ' + (data.error || 'Unknown');
                        statusEl.style.color = '#f04747';
                    }

                    setTimeout(() => { if (statusEl.textContent.includes('Saved')) statusEl.textContent = ''; }, 3000);
                } catch (error) {
                    console.error('Save error:', error);
                    statusEl.textContent = '✗ Network error';
                    statusEl.style.color = '#f04747';
                }
            }

            function testWelcomeSound() {
                const audioPreview = document.getElementById('audioPreview');
                if (audioPreview.src && audioPreview.src !== window.location.href) {
                    audioPreview.currentTime = 0;
                    audioPreview.play();

                    const btn = document.getElementById('testSoundBtn');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-volume-up fa-pulse"></i> Playing...';
                    btn.disabled = true;

                    audioPreview.onended = () => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    };

                    setTimeout(() => {
                        if (btn.disabled) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    }, 10000);
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'No Sound',
                        text: 'Please upload an MP3 file first.',
                        customClass: { popup: 'modern-alert' }
                    });
                }
            }

            function deleteWelcomeSound() {
                Swal.fire({
                    title: 'Remove Sound?',
                    text: 'This will remove the welcome sound file.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, remove it',
                    cancelButtonText: 'Cancel',
                    customClass: { popup: 'modern-alert' }
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        // Reset UI
                        document.getElementById('uploadPlaceholder').style.display = 'block';
                        document.getElementById('uploadedFileInfo').style.display = 'none';
                        document.getElementById('audioPreviewContainer').style.display = 'none';
                        document.getElementById('audioPreview').src = '';
                        document.getElementById('deleteSoundBtn').style.display = 'none';
                        document.getElementById('soundFileInput').value = '';
                        pendingSoundFile = null;

                        // Disable welcome sound
                        document.getElementById('welcomeSoundEnabled').checked = false;

                        // Set flag to delete file on server
                        shouldDeleteSound = true;
                        saveWelcomeSound();
                    }
                });
            }
        </script>


        <!-- Bot Settings Form -->
        <div class="glass-panel">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-cog"></i> Bot Settings</h3>

            <form id="botSettingsForm" enctype="multipart/form-data">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Bot Name -->
                    <div class="form-group">
                        <label><i class="fas fa-signature"></i> Bot Name</label>
                        <input type="text" id="botNameInput" class="modern-input"
                            value="<?php echo htmlspecialchars($botStatus['name'] ?? ''); ?>"
                            placeholder="Enter bot name">
                        <small style="color: #faa61a; margin-top: 5px; display: block;">
                            ⚠️ Discord limits name changes. Use sparingly.
                        </small>
                    </div>

                    <!-- Status -->
                    <?php $currentStatus = $botStatus['status'] ?? 'online'; ?>
                    <div class="form-group">
                        <label><i class="fas fa-circle"></i> Status</label>
                        <select id="botStatusSelect" class="modern-input">
                            <option value="online" <?php echo $currentStatus === 'online' ? 'selected' : ''; ?>>🟢 Online
                            </option>
                            <option value="idle" <?php echo $currentStatus === 'idle' ? 'selected' : ''; ?>>🌙 Idle
                            </option>
                            <option value="dnd" <?php echo $currentStatus === 'dnd' ? 'selected' : ''; ?>>⛔ Do Not Disturb
                            </option>
                            <option value="invisible" <?php echo $currentStatus === 'invisible' ? 'selected' : ''; ?>>👻
                                Invisible</option>
                        </select>
                    </div>
                </div>

                <div class="form-row"
                    style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-top: 15px;">
                    <!-- Activity Type -->
                    <?php $currentActivityType = $botStatus['activity_type'] ?? ''; ?>
                    <div class="form-group">
                        <label><i class="fas fa-gamepad"></i> Activity Type</label>
                        <select id="activityTypeSelect" class="modern-input">
                            <option value="" <?php echo $currentActivityType === '' ? 'selected' : ''; ?>>-- None --
                            </option>
                            <option value="playing" <?php echo $currentActivityType === 'playing' ? 'selected' : ''; ?>>🎮
                                Playing</option>
                            <option value="watching" <?php echo $currentActivityType === 'watching' ? 'selected' : ''; ?>>
                                👀 Watching</option>
                            <option value="listening" <?php echo $currentActivityType === 'listening' ? 'selected' : ''; ?>>🎧 Listening to</option>
                            <option value="competing" <?php echo $currentActivityType === 'competing' ? 'selected' : ''; ?>>🏆 Competing in</option>
                        </select>
                    </div>

                    <!-- Activity Text & Interval -->
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label><i class="fas fa-comment"></i> Activity Texts (Rotating)</label>
                        <div id="botActivityTextsList" style="margin-bottom: 10px;">
                            <!-- Activity texts will be injected here by JS -->
                        </div>
                        <button type="button" class="btn secondary sm" id="addActivityTextBtn" onclick="addBotActivityText()" style="margin-bottom: 15px;">
                            <i class="fas fa-plus"></i> Add Text
                        </button>
                        
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <label style="color: #b5bac1; font-size: 0.85rem; margin: 0;">Rotation Interval (Seconds):</label>
                            <input type="number" id="activityIntervalInput" class="modern-input" min="5" value="<?php echo htmlspecialchars($botStatus['activity_interval'] ?? '15'); ?>" style="width: 100px;">
                        </div>
                    </div>
                </div>

                <!-- Avatar Upload -->
                <div class="form-group" style="margin-top: 15px;">
                    <label><i class="fas fa-image"></i> Bot Avatar</label>
                    <div class="event-image-upload" onclick="triggerUpload('bot_avatar')"
                        style="cursor: pointer; max-width: 150px;">
                        <div class="visual-placeholder image-box" id="preview_bot_avatar"
                            style="width: 100px; height: 100px; border-radius: 50%;">
                            <?php if ($botStatus && isset($botStatus['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($botStatus['avatar']); ?>"
                                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <i class="fas fa-robot"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <input type="file" id="file_bot_avatar" style="display:none" accept="image/*"
                        onchange="uploadImage(this, 'bot_avatar')">
                    <input type="hidden" id="input_bot_avatar">
                </div>

                <div style="margin-top: 25px;">
                    <button type="button" class="btn primary" style="min-width: 200px;" onclick="saveBotSettings()">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- TAB 4: WELCOME VOICE -->
    <div id="tab-welcome" class="tab-content <?php echo $activeTab === 'welcome' ? 'active' : ''; ?>">

        <!-- ================= SERVER WELCOME SETTINGS (TEXT/EMBED) ================= -->
        <div class="glass-panel" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px; display:flex; align-items:center; gap:10px; color:#fff;">
                <i class="fas fa-comment-dots" style="color:#5865F2;"></i> Server Welcome Settings
            </h3>
            <p style="color: #b5bac1; margin-bottom: 15px; font-size: 0.9em;">
                Actions performed when a new member joins the server (Text Channel).
            </p>

            <div id="serverWelcomeSettings" style="background: rgba(0,0,0,0.2); padding: 20px; border-radius: 8px;">
                <!-- Enable Toggle -->
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <label class="toggle-switch"
                        style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="serverWelcomeEnabled" onchange="updateServerWelcomeToggleStyle()">
                        <span class="modern-toggle-slider" id="serverWelcomeSlider"></span>
                        <span style="color: #fff; font-weight: 500;">Enable Server Welcome</span>
                    </label>
                </div>

                <!-- Channel Selector -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-hashtag"></i> Welcome Channel
                    </label>
                    <input type="hidden" id="serverWelcomeChannelId" class="real-channel-input">
                    <div class="feed-dropdown-container" id="serverWelcomeChannelDropdown">
                        <div class="feed-dropdown-selected"
                            onclick="toggleFeedDropdown('serverWelcomeChannelDropdown')">
                            <span class="feed-selected-text" id="serverWelcomeChannelText">Select a channel</span>
                            <i class="fas fa-chevron-down feed-dropdown-arrow"></i>
                        </div>
                        <div class="feed-dropdown-menu">
                            <div class="feed-dropdown-search">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Search a channel"
                                    oninput="filterFeedChannels('serverWelcomeChannelDropdown', this.value)">
                            </div>
                            <div class="feed-dropdown-list" id="serverWelcomeChannelList">
                                <?php renderFeedChannelOptions($channels); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message Content (Contenteditable for Mentions) -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-pen"></i> Message Content
                    </label>
                    <input type="hidden" id="serverWelcomeMessageHidden">
                    <div class="discord-msg-input-wrapper">
                        <div id="serverWelcomeMessageEditor" class="msg-content-editor transparent-input msg-content"
                            contenteditable="true" data-placeholder="Welcome {user} to {server}!"
                            oninput="syncServerWelcomeMessage(this)"
                            style="min-height: 100px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 4px; color: #dcddde;">
                        </div>
                    </div>
                    <small style="color: #72767d; display: block; margin-top: 5px;">
                        Placeholders: <code>{user}</code>, <code>{server}</code>, <code>{count}</code>. You can use
                        <strong>@mentions</strong> here!
                    </small>
                </div>

                <!-- Banner Image (Upload) -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-image"></i> Banner Image (Outside Embed)
                    </label>

                    <!-- Upload UI -->
                    <div class="event-image-upload" onclick="triggerUpload('server_welcome_banner')"
                        style="cursor: pointer;">
                        <input type="file" id="file_server_welcome_banner" style="display:none" accept="image/*"
                            onchange="uploadImage(this, 'server_welcome_banner')">
                        <input type="hidden" id="input_server_welcome_banner">

                        <div class="visual-placeholder image-box" id="preview_server_welcome_banner"
                            style="height: 150px; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.2); border-radius: 8px; border: 2px dashed rgba(255,255,255,0.1);">
                            <i class="fas fa-image" style="font-size: 2rem; color: #72767d;"></i>
                            <span style="display: block; margin-top: 10px; color: #72767d;">Click to Upload
                                Image/GIF</span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div id="serverWelcomeBannerControls" style="display: none; margin-top: 10px;">
                        <button type="button" class="btn danger sm" onclick="removeServerWelcomeBanner()">
                            <i class="fas fa-trash"></i> Remove Image
                        </button>
                    </div>

                    <small style="color: #72767d; display: block; margin-top: 5px;">
                        This image will be displayed below the text/embed.
                    </small>
                </div>

                <!-- Embed Option -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="toggle-switch"
                        style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 10px;">
                        <input type="checkbox" id="serverWelcomeUseEmbed" onchange="toggleServerWelcomeEmbedOptions()">
                        <span class="modern-toggle-slider"></span>
                        <span style="color: #fff; font-weight: 500;">Send as Embed</span>
                    </label>

                    <div id="serverWelcomeEmbedOptions"
                        style="display: none; padding-left: 20px; border-left: 2px solid rgba(255,255,255,0.1);">
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="color: #b5bac1; font-size: 0.85rem;">Title</label>
                            <input type="text" id="serverWelcomeEmbedTitle" class="modern-input" placeholder="Welcome!"
                                style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="color: #b5bac1; font-size: 0.85rem;">Description</label>
                            <textarea id="serverWelcomeEmbedDescription" class="modern-input" rows="3"
                                placeholder="We are glad to have you here, {user}." style="width: 100%;"></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label style="color: #b5bac1; font-size: 0.85rem;">Image URL</label>
                            <input type="text" id="serverWelcomeImageUrl" class="modern-input" placeholder="https://..."
                                style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label style="color: #b5bac1; font-size: 0.85rem;">Color (Hex)</label>
                            <input type="color" id="serverWelcomeEmbedColor" class="modern-input" value="#5865f2"
                                style="height: 40px; width: 100%;">
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn primary" onclick="saveServerWelcomeSettings()">
                        <i class="fas fa-save"></i> Save Server Settings
                    </button>
                    <button type="button" class="btn secondary" onclick="testServerWelcome()">
                        <i class="fas fa-paper-plane"></i> Test Welcome
                    </button>
                    <span id="serverWelcomeStatus" style="color: #43b581; margin-left: auto;"></span>
                </div>
            </div>
        </div>

        <!-- ================= VOICE WELCOME SETTINGS (SOUND/DM) ================= -->
        <div class="glass-panel" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px; display:flex; align-items:center; gap:10px; color:#fff;">
                <i class="fas fa-bullhorn" style="color:#43b581;"></i> Voice Welcome Settings
            </h3>
            <p style="color: #b5bac1; margin-bottom: 15px; font-size: 0.9em;">
                Configure automated welcome actions when a user joins the bot's voice channel.
            </p>

            <div id="welcomeSoundSettings" style="background: rgba(0,0,0,0.2); padding: 20px; border-radius: 8px;">
                <!-- Enable/Disable Toggle -->
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <label class="toggle-switch"
                        style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="welcomeSoundEnabled" onchange="updateToggleStyle()" <?php echo $wsEnabled ? 'checked' : ''; ?>>
                        <span class="modern-toggle-slider"
                            style="background: <?php echo $wsEnabled ? '#43b581' : '#72767d'; ?>"></span>
                        <span style="color: #fff; font-weight: 500;">Enable Welcome System</span>
                    </label>
                </div>

                <!-- Target Voice Channel Selector -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-volume-up"></i> Target Voice Channel
                    </label>
                    <div class="feed-dropdown-container" id="voiceWelcomeDmChannelDropdown">
                        <div class="feed-dropdown-selected" onclick="toggleFeedDropdown('voiceWelcomeDmChannelDropdown')">
                            <span class="feed-selected-text" id="voiceWelcomeDmChannelText">
                                <?php
                                if (!empty($wsChannelId)) {
                                    $found = false;
                                    foreach ($voiceChannels as $vc) {
                                        if ((string)$vc['id'] === (string)$wsChannelId) {
                                            echo '<i class="fas fa-volume-up" style="color: #43b581;"></i> ' . htmlspecialchars($vc['name']);
                                            $found = true;
                                            break;
                                        }
                                    }
                                    if (!$found) echo '<i class="fas fa-volume-up" style="color: #43b581;"></i> All Voice Channels';
                                } else {
                                    echo '<i class="fas fa-volume-up" style="color: #43b581;"></i> All Voice Channels';
                                }
                                ?>
                            </span>
                            <i class="fas fa-chevron-down feed-dropdown-arrow"></i>
                        </div>
                        <div class="feed-dropdown-menu">
                            <div class="feed-dropdown-search">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Search a channel"
                                    oninput="filterFeedChannels('voiceWelcomeDmChannelDropdown', this.value)">
                            </div>
                            <div class="feed-dropdown-list" id="voiceWelcomeDmChannelList">
                                <?php renderFeedVoiceChannelOptions($voiceChannels, true); ?>
                            </div>
                        </div>
                    </div>
                    <small style="color: #72767d; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> เลือกห้อง Voice ที่ต้องการ — จะส่ง DM เมื่อ user เข้าห้องนี้เท่านั้น (ถ้าไม่เลือก = ทุกห้อง)
                    </small>
                </div>

                <!-- 1. Text Message Section -->
                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-comment-alt"></i> Welcome Message (Text)
                    </label>
                    <textarea id="welcomeMessageText" class="modern-input" rows="6"
                        placeholder="Hello {user}, welcome! Check out the options below..."
                        style="width: 100%; font-family: 'Consolas', monospace;"><?php echo htmlspecialchars($wsMessageText); ?></textarea>
                    <small style="color: #72767d; margin-top: 5px; display: block;">
                        Start with a greeting. Use <code>{user}</code> to mention the user. This message will be sent as
                        a <strong>Direct Message (DM)</strong> to the user.
                    </small>
                </div>

                <!-- 2. Dropdown Options Section -->
                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-list-ul"></i> Dropdown Menu Options
                    </label>

                    <div id="dropdownOptionsList">
                        <!-- Dynamic Options Generated via JS -->
                    </div>

                    <button type="button" class="btn secondary sm" onclick="addDropdownOption()">
                        <i class="fas fa-plus"></i> Add Option
                    </button>
                    <small style="color: #72767d; margin-top: 5px; display: block;">
                        Users can select these options to get immediate information (Only visible to them).
                    </small>
                </div>

                <!-- 3. Link Buttons Section -->
                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-link"></i> Link Buttons (Optional)
                    </label>

                    <div id="linkButtonsList">
                        <!-- Dynamic Buttons Generated via JS -->
                    </div>

                    <button type="button" class="btn secondary sm" onclick="addLinkButton()">
                        <i class="fas fa-plus"></i> Add Link Button
                    </button>
                    <small style="color: #72767d; margin-top: 5px; display: block;">
                        Add buttons with external links (Websites, Docs, etc.)
                    </small>
                </div>

                <!-- Save Button for Voice Welcome Message -->
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn primary" onclick="saveWelcomeSound()">
                        <i class="fas fa-save"></i> Save Message Settings
                    </button>
                    <span id="voiceWelcomeStatus" style="color: #43b581; margin-left: auto;"></span>
                </div>
            </div>
        </div>

        <!-- ================= WELCOME SOUND SETTINGS (OPTIONAL) ================= -->
        <div class="glass-panel" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px; display:flex; align-items:center; gap:10px; color:#fff;">
                <i class="fas fa-music" style="color:#faa61a;"></i> Welcome Sound Settings (Optional)
            </h3>
            <p style="color: #b5bac1; margin-bottom: 15px; font-size: 0.9em;">
                Configure a sound that plays when a user joins the bot's voice channel.
            </p>

            <div id="welcomeSoundUploadSettings"
                style="background: rgba(0,0,0,0.2); padding: 20px; border-radius: 8px;">
                <!-- Sound Upload Section -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-cloud-upload-alt"></i> Welcome Sound File
                    </label>

                    <!-- Upload Zone -->
                    <div id="soundUploadZone" style="
                        border: 2px dashed rgba(255,255,255,0.2);
                        border-radius: 8px;
                        padding: 30px;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.3s;
                        background: rgba(0,0,0,0.1);
                    " onclick="document.getElementById('soundFileInput').click()">
                        <input type="file" id="soundFileInput" accept=".mp3,audio/mpeg" style="display: none;"
                            onchange="handleSoundFileSelect(event)">

                        <!-- Placeholder (Show if NO file) -->
                        <div id="uploadPlaceholder" style="display: <?php echo $wsHasFile ? 'none' : 'block'; ?>;">
                            <i class="fas fa-cloud-upload-alt"
                                style="font-size: 2.5rem; color: #5865f2; margin-bottom: 10px;"></i>
                            <p style="color: #b5bac1; margin: 0;">Click or drag MP3 file here</p>
                            <small style="color: #72767d;">Maximum 5MB</small>
                        </div>

                        <!-- Info (Show if HAS file) -->
                        <div id="uploadedFileInfo" style="display: <?php echo $wsHasFile ? 'block' : 'none'; ?>;">
                            <i class="fas fa-file-audio"
                                style="font-size: 2rem; color: #43b581; margin-bottom: 10px;"></i>
                            <p id="uploadedFileName" style="color: #fff; margin: 5px 0; font-weight: 500;">
                                <?php echo $wsHasFile ? htmlspecialchars($wsFilename) : ''; ?>
                            </p>
                            <small id="uploadedFileSize"
                                style="color: #72767d;"><?php echo $wsHasFile ? 'Current File' : ''; ?></small>
                        </div>
                    </div>

                    <!-- Audio Preview -->
                    <div id="audioPreviewContainer" style="margin-top: 15px; display: none;">
                        <audio id="audioPreview" controls style="width: 100%; height: 40px;"></audio>
                    </div>

                    <small style="color: #72767d; margin-top: 10px; display: block;">
                        <i class="fas fa-info-circle"></i> Sound will play when someone joins the bot's voice channel.
                    </small>
                </div>

                <!-- Target Channel (Optional) -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-volume-up"></i> Target Voice Channel (Optional)
                    </label>
                    <input type="hidden" id="welcomeSoundChannel" value="<?php echo htmlspecialchars($wsChannelId); ?>">
                    <div class="feed-dropdown-container" id="welcomeSoundChannelDropdown">
                        <div class="feed-dropdown-selected" onclick="toggleFeedDropdown('welcomeSoundChannelDropdown')">
                            <span class="feed-selected-text" id="welcomeSoundChannelText">
                                <?php
                                if (!empty($wsChannelId)) {
                                    foreach ($voiceChannels as $vc) {
                                        if ((string) $vc['id'] === (string) $wsChannelId) {
                                            echo '<i class="fas fa-volume-up" style="color: #43b581;"></i> ' . htmlspecialchars($vc['name']);
                                            break;
                                        }
                                    }
                                } else {
                                    echo '<i class="fas fa-volume-up" style="color: #43b581;"></i> All Voice Channels';
                                }
                                ?>
                            </span>
                            <i class="fas fa-chevron-down feed-dropdown-arrow"></i>
                        </div>
                        <div class="feed-dropdown-menu">
                            <div class="feed-dropdown-search">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Search a channel"
                                    oninput="filterFeedChannels('welcomeSoundChannelDropdown', this.value)">
                            </div>
                            <div class="feed-dropdown-list" id="welcomeSoundChannelList">
                                <?php renderFeedVoiceChannelOptions($voiceChannels, true); ?>
                            </div>
                        </div>
                    </div>
                    <small style="color: #72767d; margin-top: 5px; display: block;">
                        Leave empty to play in any channel, or select a specific channel.
                    </small>
                </div>

                <!-- Delay Settings -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                        <i class="fas fa-hourglass-start"></i> Delay Before Playing (Seconds)
                    </label>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="range" id="welcomeSoundDelay" class="modern-range" min="0" max="10" step="1"
                            value="<?php echo htmlspecialchars($wsDelay); ?>"
                            oninput="document.getElementById('delayValue').textContent = this.value + 's'">
                        <span id="delayValue"
                            style="color: #fff; font-weight: bold; min-width: 30px;"><?php echo htmlspecialchars($wsDelay); ?>s</span>
                    </div>
                    <small style="color: #72767d; margin-top: 5px; display: block;">
                        Wait time after user joins before playing sound.
                    </small>
                </div>

                <!-- Status & Actions -->
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn primary" onclick="saveWelcomeSound()">
                        <i class="fas fa-save"></i> Save Sound Settings
                    </button>
                    <button type="button" class="btn secondary" onclick="testWelcomeSound()" id="testSoundBtn">
                        <i class="fas fa-play"></i> Test Sound
                    </button>
                    <button type="button" class="btn danger" onclick="deleteWelcomeSound()" id="deleteSoundBtn"
                        style="display: none;">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                    <span id="welcomeSoundStatus" style="color: #43b581; margin-left: auto;"></span>
                </div>
            </div>
        </div>
        
        <!-- ================= SERVER LEAVE SETTINGS (GOODBYE) ================= -->
        <div class="glass-panel" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                <div>
                    <h3 style="margin: 0; display:flex; align-items:center; gap:10px; color:#fff;">
                        <i class="fas fa-sign-out-alt" style="color:#f04747;"></i> Server Leave Settings (Goodbye)
                    </h3>
                    <p style="color: #b5bac1; margin-top: 5px; font-size: 0.9em;">
                        Send a message when someone leaves the server.
                    </p>
                </div>
                <label class="toggle-switch" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="serverLeaveEnabled" onchange="updateLeaveToggleStyle()">
                    <span class="modern-toggle-slider" id="serverLeaveSlider" style="background: #72767d;"></span>
                    <span style="color: #fff; font-weight: 500;">Enable</span>
                </label>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                    <i class="fas fa-hashtag"></i> Target Channel
                </label>
                <input type="hidden" id="serverLeaveChannelId" value="">
                <div class="feed-dropdown-container" id="serverLeaveChannelDropdown">
                    <div class="feed-dropdown-selected" onclick="toggleFeedDropdown('serverLeaveChannelDropdown')">
                        <span class="feed-selected-text" id="serverLeaveChannelText">
                            <i class="fas fa-hashtag" style="color: #5865f2;"></i> Select a channel...
                        </span>
                        <i class="fas fa-chevron-down feed-dropdown-arrow"></i>
                    </div>
                    <div class="feed-dropdown-menu">
                        <div class="feed-dropdown-search">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search a channel" oninput="filterFeedChannels('serverLeaveChannelDropdown', this.value)">
                        </div>
                        <div class="feed-dropdown-list" id="serverLeaveChannelList">
                            <?php renderFeedChannelOptions($channels); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                    <i class="fas fa-comment-alt"></i> Goodbye Message
                </label>
                <textarea id="serverLeaveMessage" class="modern-input" rows="3" placeholder="{user} just left the server." style="width: 100%; font-family: 'Consolas', monospace;"></textarea>
                <small style="color: #72767d; margin-top: 5px; display: block;">
                    Use <code>{user}</code> for their username, <code>{server}</code> for server name, and <code>{count}</code> for member count.
                </small>
            </div>

            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 15px;">
                <input type="checkbox" id="serverLeaveUseEmbed" onchange="toggleServerLeaveEmbedOptions()">
                <div class="modern-toggle-slider" style="width: 36px; height: 20px; border-radius: 20px; background: #72767d; position: relative;"></div>
                <span style="color: #fff; font-weight: 500;">Send as Embed</span>
            </label>

            <div id="serverLeaveEmbedOptions" style="display: none; background: rgba(0,0,0,0.2); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Embed Title</label>
                        <input type="text" id="serverLeaveEmbedTitle" class="modern-input" placeholder="User Left" style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Embed Color</label>
                        <input type="color" id="serverLeaveEmbedColor" class="modern-input" value="#f04747" style="width: 100%; height: 38px; padding: 0 5px;">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Embed Description</label>
                    <textarea id="serverLeaveEmbedDescription" class="modern-input" rows="2" placeholder="We will miss you {user}!" style="width: 100%;"></textarea>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn primary" onclick="saveServerLeaveSettings()">
                    <i class="fas fa-save"></i> Save Leave Settings
                </button>
                <span id="serverLeaveStatus" style="color: #43b581; margin-left: auto;"></span>
            </div>
        </div>
        
        <!-- ================= VOICE ACTION LOGGING ================= -->
        <div class="glass-panel" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                <div>
                    <h3 style="margin: 0; display:flex; align-items:center; gap:10px; color:#fff;">
                        <i class="fas fa-microphone" style="color:#5865F2;"></i> Voice Action Logs
                    </h3>
                    <p style="color: #b5bac1; margin-top: 5px; font-size: 0.9em;">
                        Log when users join or leave any voice channel.
                    </p>
                </div>
                <label class="toggle-switch" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="voiceLogsEnabled" onchange="updateVoiceLogsToggleStyle()">
                    <span class="modern-toggle-slider" id="voiceLogsSlider" style="background: #72767d;"></span>
                    <span style="color: #fff; font-weight: 500;">Enable</span>
                </label>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;">
                    <i class="fas fa-hashtag"></i> Log Channel
                </label>
                <input type="hidden" id="voiceLogsChannelId" value="">
                <div class="feed-dropdown-container" id="voiceLogsChannelDropdown">
                    <div class="feed-dropdown-selected" onclick="toggleFeedDropdown('voiceLogsChannelDropdown')">
                        <span class="feed-selected-text" id="voiceLogsChannelText">
                            <i class="fas fa-hashtag" style="color: #5865f2;"></i> Select a channel...
                        </span>
                        <i class="fas fa-chevron-down feed-dropdown-arrow"></i>
                    </div>
                    <div class="feed-dropdown-menu">
                        <div class="feed-dropdown-search">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search a channel" oninput="filterFeedChannels('voiceLogsChannelDropdown', this.value)">
                        </div>
                        <div class="feed-dropdown-list" id="voiceLogsChannelList">
                            <?php renderFeedChannelOptions($channels); ?>
                        </div>
                    </div>
                </div>
                <small style="color: #72767d; margin-top: 5px; display: block;">
                    The channel where voice join/leave logs will be posted.
                </small>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn primary" onclick="saveVoiceLogsSettings()">
                    <i class="fas fa-save"></i> Save Log Settings
                </button>
                <span id="voiceLogsStatus" style="color: #43b581; margin-left: auto;"></span>
            </div>
        </div>
    </div>

    <!-- TAB 5: PERMISSIONS -->
    <div id="tab-permissions" class="tab-content <?php echo $activeTab === 'permissions' ? 'active' : ''; ?>">
        <div class="glass-panel">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-user-shield"></i> Command Permissions</h3>
            <p style="color: #b5bac1; margin-bottom: 25px;">
                Control which roles can access specific bot commands. Administrators always have access.
                If no roles are selected, only Administrators can use the command.
            </p>

            <div id="permissions-container" style="display: grid; gap: 20px;">
                <div style="text-align: center; color: #b5bac1; padding: 40px;">
                    <i class="fas fa-circle-notch fa-spin"></i> Loading permissions...
                </div>
            </div>

            <div
                style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; display: flex; align-items: center;">
                <button class="btn primary" onclick="savePermissions()">
                    <i class="fas fa-save"></i> Save Permissions
                </button>
                <span id="permissionsStatus" style="margin-left: 15px; font-weight: 500;"></span>
            </div>
        </div>
    </div>

    <!-- TAB 6: TICKET SYSTEM -->
    <div id="tab-tickets" class="tab-content <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>">
        <!-- Panel Management -->
        <div class="glass-panel" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><i class="fas fa-ticket-alt" style="color: #5865F2;"></i> Ticket Panels</h3>
                <button class="btn primary" onclick="showPanelEditor()">
                    <i class="fas fa-plus"></i> New Panel
                </button>
            </div>
            <p style="color: #b5bac1; margin-bottom: 20px; font-size: 0.9em;">
                Create ticket panels that users can click to open support tickets. Each panel sends an embed with a
                button to a channel.
            </p>

            <div id="ticketPanelsList">
                <div style="text-align: center; color: #b5bac1; padding: 40px;">
                    <i class="fas fa-circle-notch fa-spin"></i> Loading panels...
                </div>
            </div>
        </div>

        <!-- Active Tickets -->
        <div class="glass-panel">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-inbox" style="color: #43b581;"></i> Active Tickets</h3>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button class="btn secondary sm" onclick="loadTickets('open')" id="btnFilterOpen"
                    style="background: rgba(67,181,129,0.2); border-color: #43b581; color: #43b581;">
                    <i class="fas fa-envelope-open"></i> Open
                </button>
                <button class="btn secondary sm" onclick="loadTickets('closed')" id="btnFilterClosed">
                    <i class="fas fa-archive"></i> Closed
                </button>
                <button class="btn secondary sm" onclick="loadTickets()" id="btnFilterAll">
                    <i class="fas fa-list"></i> All
                </button>
            </div>
            <div id="ticketsList">
                <div style="text-align: center; color: #b5bac1; padding: 30px;">
                    <i class="fas fa-circle-notch fa-spin"></i> Loading tickets...
                </div>
            </div>
        </div>

        <!-- Transcript Viewer Modal -->
        <div id="transcriptViewerOverlay" class="custom-modal-overlay">
            <div class="custom-modal" style="max-width: 750px; height: 85vh;">
                <div class="custom-modal-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h4 class="custom-modal-title" id="transcriptTitle"><i class="fas fa-scroll"></i> Ticket Transcript</h4>
                    <button onclick="closeTranscriptViewer()" style="background:none; border:none; color:#b5bac1; font-size:1.3rem; cursor:pointer; padding:5px;">&times;</button>
                </div>
                <div id="transcriptMeta" style="padding:10px 20px; background:rgba(0,0,0,0.15); border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.82rem; color:#b5bac1; display:flex; gap:15px; flex-wrap:wrap;"></div>
                <div id="transcriptMessages" style="flex:1; overflow-y:auto; padding:16px 20px; display:flex; flex-direction:column; gap:4px;">
                    <div style="text-align:center; color:#b5bac1; padding:60px;"><i class="fas fa-circle-notch fa-spin"></i> Loading transcript...</div>
                </div>
            </div>
        </div>

        <!-- Panel Editor Modal -->
        <div id="panelEditorOverlay" class="custom-modal-overlay">
            <div class="custom-modal" style="max-width: 600px;">
                <div class="custom-modal-header">
                    <h4 class="custom-modal-title" id="panelEditorTitle"><i class="fas fa-ticket-alt"></i> New Ticket
                        Panel</h4>
                </div>
                <div class="custom-modal-body" style="max-height: 65vh; overflow-y: auto;">
                    <input type="hidden" id="panelEditId">

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Panel
                            Title</label>
                        <input type="text" id="panelTitle" class="modern-input" placeholder="Support Ticket"
                            style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label
                            style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Description</label>
                        <textarea id="panelDescription" class="modern-input" rows="3"
                            placeholder="Click the button below to open a support ticket."
                            style="width: 100%;"></textarea>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;"><i
                                class="fas fa-hashtag"></i> Target Channel (Panel will be posted here)</label>
                        <select id="panelChannelId" class="modern-input" style="width: 100%;">
                            <option value="">-- Select Channel --</option>
                            <?php foreach ($channels as $c): ?>
                                <?php if ($c['type'] === 'text' || $c['type'] == 0): ?>
                                    <option value="<?php echo $c['id']; ?>"># <?php echo htmlspecialchars($c['name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;"><i
                                class="fas fa-folder"></i> Ticket Category (New tickets created here)</label>
                        <select id="panelCategoryId" class="modern-input" style="width: 100%;">
                            <option value="">-- No Category --</option>
                        </select>
                        <small style="color: #72767d; display: block; margin-top: 3px;">Tickets will be created in this
                            category folder</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;"><i
                                class="fas fa-user-shield"></i> Support Role</label>
                        <select id="panelSupportRoleId" class="modern-input" style="width: 100%;">
                            <option value="">-- No Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #72767d; display: block; margin-top: 3px;">Role that can view and respond
                            to tickets</small>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label
                                style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Button
                                Text</label>
                            <input type="text" id="panelButtonText" class="modern-input" placeholder="Open Ticket"
                                style="width: 100%;">
                        </div>
                        <div class="form-group">
                            <label
                                style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Button
                                Color</label>
                            <select id="panelButtonColor" class="modern-input" style="width: 100%;">
                                <option value="green">🟢 Green</option>
                                <option value="blue">🔵 Blue</option>
                                <option value="red">🔴 Red</option>
                                <option value="grey">⚪ Grey</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Embed
                                Color</label>
                            <input type="color" id="panelEmbedColor" class="modern-input" value="#5865F2"
                                style="width: 100%; height: 38px;">
                        </div>
                        <div class="form-group">
                            <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Max
                                Tickets Per User</label>
                            <input type="number" id="panelMaxTickets" class="modern-input" value="1" min="1" max="10"
                                style="width: 100%;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Welcome
                            Message (in ticket channel)</label>
                        <textarea id="panelWelcomeMessage" class="modern-input" rows="3"
                            placeholder="Welcome! Please describe your issue..." style="width: 100%;"></textarea>
                        <small style="color: #72767d; display: block; margin-top: 3px;">Leave empty for default
                            message</small>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button class="c-btn c-btn-cancel" onclick="closePanelEditor()">Cancel</button>
                    <button class="c-btn c-btn-confirm" onclick="savePanel()"><i class="fas fa-save"></i> Save
                        Panel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 7: ROLE PANELS -->
    <div id="tab-roles" class="tab-content <?php echo $activeTab === 'roles' ? 'active' : ''; ?>">
        <div class="glass-panel" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0;"><i class="fas fa-id-badge" style="color: #5865F2;"></i> Role Assignment Panels</h3>
                    <p style="color: #b5bac1; margin: 5px 0 0; font-size: 0.85em;">สร้างปุ่มรับยศให้สมาชิกกดรับ/ถอดยศได้เอง ระบบจะส่ง Embed พร้อมปุ่มไปยังห้องที่เลือก</p>
                </div>
                <button class="btn primary" onclick="showRolePanelEditor()">
                    <i class="fas fa-plus"></i> New Panel
                </button>
            </div>

            <div id="rolePanelsList">
                <div style="text-align: center; color: #b5bac1; padding: 40px;">
                    <i class="fas fa-circle-notch fa-spin"></i> Loading role panels...
                </div>
            </div>
        </div>

        <!-- Role Panel Editor Modal -->
        <div id="rolePanelEditorOverlay" class="custom-modal-overlay">
            <div class="custom-modal" style="max-width: 680px;">
                <div class="custom-modal-header">
                    <h4 class="custom-modal-title" id="rolePanelEditorTitle"><i class="fas fa-id-badge"></i> New Role Panel</h4>
                </div>
                <div class="custom-modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <input type="hidden" id="rpEditPanelId">

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Panel Title</label>
                        <input type="text" id="rpTitle" class="modern-input" placeholder="🎖️ Role Selection" style="width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;">Description</label>
                        <textarea id="rpDescription" class="modern-input" rows="3" placeholder="Click a button below to get/remove a role." style="width: 100%;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;"><i class="fas fa-hashtag"></i> Target Channel</label>
                            <select id="rpChannelId" class="modern-input" style="width: 100%;">
                                <option value="">-- Select Channel --</option>
                                <?php foreach ($channels as $c): ?>
                                    <?php if ($c['type'] === 'text' || $c['type'] == 0): ?>
                                        <option value="<?php echo $c['id']; ?>"># <?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;"><i class="fas fa-palette"></i> Embed Color</label>
                            <input type="color" id="rpEmbedColor" class="modern-input" value="#5865F2" style="width: 100%; height: 38px;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 5px;"><i class="fas fa-exchange-alt"></i> Mode</label>
                        <select id="rpMode" class="modern-input" style="width: 100%;">
                            <option value="toggle">Toggle (กดเพิ่ม/กดถอด)</option>
                            <option value="give">Give Only (กดรับเท่านั้น ถอดไม่ได้)</option>
                        </select>
                    </div>

                    <!-- Buttons Config -->
                    <div style="margin-bottom: 15px;">
                        <label style="color: #b5bac1; font-size: 0.85rem; display: block; margin-bottom: 8px;"><i class="fas fa-th-large"></i> Role Buttons</label>
                        <div id="rpButtonsList" style="display: flex; flex-direction: column; gap: 10px;"></div>
                        <button type="button" class="btn secondary sm" onclick="addRolePanelButton()" style="margin-top: 10px;">
                            <i class="fas fa-plus"></i> Add Button
                        </button>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button class="c-btn c-btn-cancel" onclick="closeRolePanelEditor()">Cancel</button>
                    <button class="c-btn c-btn-confirm" onclick="saveRolePanel()"><i class="fas fa-save"></i> Save Panel</button>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
// Helper to render channel list (legacy)
function renderChannelOptions($channels)
{
    echo '<div class="search-box-wrapper"><i class="fas fa-search"></i><input type="text" class="channel-search" placeholder="Search a channel" oninput="filterChannels(this)"></div>';
    echo '<div class="channel-list-items">';
    if (empty($channels)) {
        echo '<div class="channel-item disabled">No channels found</div>';
    } else {
        $currentCategory = '';
        foreach ($channels as $chan) {
            // Check for category change
            $category = $chan['category'] ?? 'General';
            if ($category !== $currentCategory) {
                echo '<div class="channel-category-header">' . htmlspecialchars($category) . '</div>';
                $currentCategory = $category;
            }

            echo '<div class="channel-item" onclick="selectChannel(this, \'' . $chan['id'] . '\', \'#' . htmlspecialchars($chan['name']) . '\')" data-name="' . htmlspecialchars($chan['name']) . '" data-category="' . htmlspecialchars($category) . '">';
            echo '<i class="fas fa-hashtag"></i> ' . htmlspecialchars($chan['name']);
            // Removed right-side badge as we have headers now
            echo '</div>';
        }
    }
    echo '</div>';
}

// Helper to render feed-style channel list (Discord Feed Settings style)
function renderFeedChannelOptions($channels)
{
    if (empty($channels)) {
        echo '<div class="feed-dropdown-empty">No channels found</div>';
    } else {
        $currentCategory = '';
        foreach ($channels as $chan) {
            // Check for category change
            $category = $chan['category'] ?? 'General';
            if ($category !== $currentCategory) {
                echo '<div class="feed-dropdown-category">' . htmlspecialchars($category) . '</div>';
                $currentCategory = $category;
            }

            // Data attributes for JavaScript selection
            $channelId = htmlspecialchars($chan['id']);
            $channelName = htmlspecialchars($chan['name']);
            $channelType = htmlspecialchars($chan['type'] ?? 'text');
            $categoryBadge = htmlspecialchars($category);

            echo '<div class="feed-dropdown-item" data-id="' . $channelId . '" data-name="' . $channelName . '" data-type="' . $channelType . '" data-category="' . $categoryBadge . '">';
            echo '<i class="fas fa-hashtag channel-icon"></i>';
            echo '<span class="channel-name">' . $channelName . '</span>';
            echo '<span class="channel-badge">' . $categoryBadge . '</span>';
            echo '</div>';
        }
    }
}

// Helper to render feed-style voice channel list
function renderFeedVoiceChannelOptions($voiceChannels, $includeAllOption = false)
{
    if ($includeAllOption) {
        echo '<div class="feed-dropdown-item" data-id="" data-name="All Voice Channels" data-type="voice" data-category="Default">';
        echo '<i class="fas fa-volume-up channel-icon" style="color: #43b581;"></i>';
        echo '<span class="channel-name">All Voice Channels</span>';
        echo '<span class="channel-badge">Default</span>';
        echo '</div>';
    }

    if (empty($voiceChannels)) {
        if (!$includeAllOption) {
            echo '<div class="feed-dropdown-empty">No voice channels found</div>';
        }
    } else {
        $currentGuild = '';
        foreach ($voiceChannels as $vc) {
            // Check for guild change (use guild as category)
            $guild = $vc['guild'] ?? 'Server';
            if ($guild !== $currentGuild) {
                echo '<div class="feed-dropdown-category">' . htmlspecialchars($guild) . '</div>';
                $currentGuild = $guild;
            }

            $channelId = htmlspecialchars($vc['id']);
            $channelName = htmlspecialchars($vc['name']);

            echo '<div class="feed-dropdown-item" data-id="' . $channelId . '" data-name="' . $channelName . '" data-type="voice" data-category="' . htmlspecialchars($guild) . '">';
            echo '<i class="fas fa-volume-up channel-icon" style="color: #43b581;"></i>';
            echo '<span class="channel-name">' . $channelName . '</span>';
            echo '<span class="channel-badge">' . htmlspecialchars($guild) . '</span>';
            echo '</div>';
        }
    }
}

// Helper to render feed-style role list
function renderFeedRoleOptions($roles, $includeNoneOption = true)
{
    if ($includeNoneOption) {
        echo '<div class="feed-dropdown-item" data-id="" data-name="No role ping" data-type="role" data-color="#72767d">';
        echo '<i class="fas fa-times-circle channel-icon" style="color: #72767d;"></i>';
        echo '<span class="channel-name">-- No role ping --</span>';
        echo '</div>';
    }

    if (empty($roles)) {
        if (!$includeNoneOption) {
            echo '<div class="feed-dropdown-empty">No roles found</div>';
        }
    } else {
        foreach ($roles as $role) {
            $roleId = htmlspecialchars($role['id']);
            $roleName = htmlspecialchars($role['name']);
            $roleColor = $role['color'] ?? '#99aab5';

            echo '<div class="feed-dropdown-item" data-id="' . $roleId . '" data-name="' . $roleName . '" data-type="role" data-color="' . htmlspecialchars($roleColor) . '">';
            echo '<i class="fas fa-at channel-icon" style="color: ' . htmlspecialchars($roleColor) . ';"></i>';
            echo '<span class="channel-name" style="color: ' . htmlspecialchars($roleColor) . ';">@' . $roleName . '</span>';
            echo '</div>';
        }
    }
}
?>

<!-- SCRIPTS -->
<script>
    // ====== AUTOCOMPLETE DATA & SYSTEM ======
    var autocompleteChannels = <?php echo json_encode($channels); ?>;
    var autocompleteRoles = <?php echo json_encode($roles); ?>;
    var autocompleteMembers = <?php echo json_encode($members); ?>;
    // Combined list for @ mentions (users first, then roles)
    var autocompleteMentions = [
        ...autocompleteMembers.map(m => ({ ...m, type: 'user' })),
        ...autocompleteRoles.map(r => ({ ...r, type: 'role' }))
    ];
    var activeAutocomplete = null;
    var autocompleteIndex = 0;

    function initMentionAutocomplete() {
        // Attach to all textareas in embed builder
        const form = document.getElementById('embedForm');
        if (!form) return;

        // Use Event Delegation to support dynamic fields (e.g., Added Fields)
        // Check for .transparent-input (embed fields), .msg-content (main msg), and .modern-input (button labels)
        const selector = '.transparent-input, .msg-content, .modern-input:not([type="url"])';

        form.addEventListener('input', (e) => {
            if (e.target.matches(selector)) {
                handleMentionInput(e);
            }
        });

        form.addEventListener('keydown', (e) => {
            if (e.target.matches(selector)) {
                handleAutocompleteNavigation(e);
            }
        });

        form.addEventListener('focusout', (e) => {
            if (e.target.matches(selector)) {
                handleAutocompleteBlur(e);
            }
        });

        // Handle Enter key for contenteditable elements to create new lines
        form.addEventListener('keydown', (e) => {
            // Only handle Enter key for contenteditable elements
            if (e.key === 'Enter' && e.target.isContentEditable) {
                // If autocomplete is showing, don't handle here (let autocomplete handle it)
                const dropdown = document.getElementById('mention-autocomplete');
                if (dropdown && dropdown.classList.contains('show')) {
                    return;
                }
                // Prevent default behavior (which might create unwanted elements or submit form)
                e.preventDefault();

                // Insert a <br> element at cursor position
                const selection = window.getSelection();
                if (!selection.rangeCount) return;

                const range = selection.getRangeAt(0);
                range.deleteContents();

                // Create and insert a br element
                const br = document.createElement('br');
                range.insertNode(br);

                // Move cursor after the br
                range.setStartAfter(br);
                range.setEndAfter(br);
                selection.removeAllRanges();
                selection.addRange(range);

                // Trigger input event to sync content
                e.target.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });

        // Close popup when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('mention-autocomplete');
            if (dropdown && dropdown.classList.contains('show')) {
                // Check if click is inside the dropdown
                if (!dropdown.contains(e.target)) {
                    // Check if click is on the triggering textarea
                    if (activeAutocomplete && activeAutocomplete.textarea !== e.target) {
                        hideAutocomplete();
                    }
                }
            }
        });
    }

    function handleAutocompleteBlur(e) {
        // Don't hide if clicking inside the autocomplete dropdown
        setTimeout(() => {
            const dropdown = document.getElementById('mention-autocomplete');
            if (dropdown && dropdown.contains(document.activeElement)) {
                return; // Focus is inside dropdown, don't hide
            }
            // Only hide if focus moved completely outside
            if (!dropdown || !dropdown.contains(document.activeElement)) {
                // Check if there's an active search input focused
                const searchInput = document.getElementById('mention-search-input');
                if (searchInput && searchInput === document.activeElement) {
                    return; // Don't hide if search input is focused
                }
            }
        }, 100);
    }

    function handleMentionInput(e) {
        const inputElement = e.target;
        const isContentEditable = inputElement.isContentEditable;

        let text, cursorPos;

        if (isContentEditable) {
            // For contenteditable: get text content up to cursor
            const selection = window.getSelection();
            if (!selection.rangeCount) return;

            const range = selection.getRangeAt(0);

            // Get all text before cursor
            const preCaretRange = range.cloneRange();
            preCaretRange.selectNodeContents(inputElement);
            preCaretRange.setEnd(range.endContainer, range.endOffset);
            text = preCaretRange.toString();
            cursorPos = text.length;
        } else {
            // For textarea
            cursorPos = inputElement.selectionStart;
            text = inputElement.value.substring(0, cursorPos);
        }

        // Check for # or @ triggers
        const hashMatch = text.match(/#([a-zA-Z0-9_-]*)$/);
        const atMatch = text.match(/@([a-zA-Z0-9_-]*)$/);

        if (hashMatch) {
            showAutocomplete(inputElement, 'channel', hashMatch[1], hashMatch.index);
        } else if (atMatch) {
            showAutocomplete(inputElement, 'role', atMatch[1], atMatch.index);
        } else {
            hideAutocomplete();
        }
    }

    function showAutocomplete(inputElement, type, query, triggerPos) {
        // For @ mentions, use combined roles + users list
        let items = type === 'channel' ? autocompleteChannels : autocompleteMentions;
        const lowerQuery = query.toLowerCase();

        // Filter items based on query
        items = items.filter(item => item.name.toLowerCase().includes(lowerQuery));

        if (items.length === 0) {
            hideAutocomplete();
            return;
        }

        // Create or get dropdown
        let dropdown = document.getElementById('mention-autocomplete');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.id = 'mention-autocomplete';
            dropdown.className = 'mention-autocomplete-dropdown';
            document.body.appendChild(dropdown);
        }

        // Populate dropdown with grouped sections for roles and users
        let itemsHtml = '';

        if (type === 'channel') {
            // Channel dropdown - simple list
            itemsHtml = items.map((item, i) => {
                return `<div class="mention-item ${i === 0 ? 'active' : ''}" data-type="channel" data-id="${item.id}" data-name="${item.name}">
                    <span class="mention-icon" style="color: #5865F2">#</span>
                    <span class="mention-name">${item.name}</span>
                    ${item.category ? `<span class="mention-category">${item.category}</span>` : ''}
                </div>`;
            }).join('');
        } else {
            // Role/User dropdown - grouped sections
            const users = items.filter(item => item.type === 'user');
            const roles = items.filter(item => item.type === 'role');

            let itemIndex = 0;

            // Users section
            if (users.length > 0) {
                itemsHtml += `<div class="mention-section-header"><i class="fas fa-user"></i> USERS</div>`;
                users.forEach((item) => {
                    itemsHtml += `<div class="mention-item ${itemIndex === 0 ? 'active' : ''}" data-type="user" data-id="${item.id}" data-name="${item.name}" data-color="#5865F2">
                        <span class="mention-icon" style="color: #5865F2">@</span>
                        <span class="mention-name">${item.name}</span>
                        <span class="mention-badge user-badge">User</span>
                    </div>`;
                    itemIndex++;
                });
            }

            // Roles section
            if (roles.length > 0) {
                itemsHtml += `<div class="mention-section-header"><i class="fas fa-at"></i> ROLES</div>`;
                roles.forEach((item) => {
                    const color = item.color || '#99aab5';
                    itemsHtml += `<div class="mention-item ${itemIndex === 0 ? 'active' : ''}" data-type="role" data-id="${item.id}" data-name="${item.name}" data-color="${color}">
                        <span class="mention-icon" style="color: ${color}">@</span>
                        <span class="mention-name" style="color: ${color}">${item.name}</span>
                        <span class="mention-badge role-badge">Role</span>
                    </div>`;
                    itemIndex++;
                });
            }
        }

        // Build full dropdown with header and search
        const headerText = type === 'channel' ? 'เลือกห้อง (Channels)' : 'เลือก Role / User';
        const headerIcon = type === 'channel' ? 'fa-hashtag' : 'fa-at';
        const placeholderText = type === 'channel' ? 'ค้นหาห้อง...' : 'ค้นหา Role / User...';
        dropdown.innerHTML = `
            <div class="mention-autocomplete-header">
                <h4><i class="fas ${headerIcon}"></i> ${headerText}</h4>
                <div class="mention-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="mention-search-input" placeholder="${placeholderText}" autocomplete="off">
                </div>
            </div>
            <div class="mention-items-container">
                ${itemsHtml}
            </div>
        `;

        // Show as centered popup (CSS handles positioning)
        dropdown.classList.add('show');

        // Focus search input
        setTimeout(() => {
            const searchInput = document.getElementById('mention-search-input');
            if (searchInput) {
                searchInput.focus();
                // Handle search input
                searchInput.addEventListener('input', (e) => {
                    filterMentionItems(e.target.value, type);
                });
                // Prevent blur from hiding popup when clicking search
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === 'Tab') {
                        handleAutocompleteNavigation(e);
                    } else if (e.key === 'Escape') {
                        hideAutocomplete();
                    }
                });
            }
        }, 50);

        // Attach click handlers
        dropdown.querySelectorAll('.mention-item').forEach(item => {
            item.addEventListener('click', () => selectMentionItem(inputElement, item, triggerPos));
        });

        activeAutocomplete = { textarea: inputElement, type, triggerPos };
        autocompleteIndex = 0;
    }

    function filterMentionItems(query, type) {
        const items = type === 'channel' ? autocompleteChannels : autocompleteMentions;
        const lowerQuery = query.toLowerCase();
        const filtered = items.filter(item => item.name.toLowerCase().includes(lowerQuery));

        const container = document.querySelector('.mention-items-container');
        if (!container) return;

        if (filtered.length === 0) {
            container.innerHTML = '<div class="mention-no-results"><i class="fas fa-search"></i> ไม่พบผลลัพธ์</div>';
            return;
        }

        container.innerHTML = filtered.map((item, i) => {
            const icon = type === 'channel' ? '#' : '@';
            const itemType = item.type || 'role';
            const color = itemType === 'role' ? (item.color || '#99aab5') : '#5865F2';
            const badge = itemType === 'user' ? '<span class="mention-badge user-badge">User</span>' : '<span class="mention-badge role-badge">Role</span>';
            return `<div class="mention-item ${i === 0 ? 'active' : ''}" data-type="${itemType}" data-id="${item.id}" data-name="${item.name}" data-color="${color}">
                <span class="mention-icon" style="color: ${color}">${icon}</span>
                <span class="mention-name">${item.name}</span>
                ${type !== 'channel' ? badge : ''}
                ${item.category ? `<span class="mention-category">${item.category}</span>` : ''}
            </div>`;
        }).join('');

        // Re-attach click handlers
        const textarea = activeAutocomplete?.textarea;
        const triggerPos = activeAutocomplete?.triggerPos;
        container.querySelectorAll('.mention-item').forEach(item => {
            item.addEventListener('click', () => selectMentionItem(textarea, item, triggerPos));
        });

        autocompleteIndex = 0;
    }

    function hideAutocomplete() {
        const dropdown = document.getElementById('mention-autocomplete');
        if (dropdown) dropdown.classList.remove('show');
        activeAutocomplete = null;
    }

    function handleAutocompleteNavigation(e) {
        if (!activeAutocomplete) return;

        const dropdown = document.getElementById('mention-autocomplete');
        if (!dropdown || !dropdown.classList.contains('show')) return;

        const items = dropdown.querySelectorAll('.mention-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            autocompleteIndex = Math.min(autocompleteIndex + 1, items.length - 1);
            updateActiveItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            autocompleteIndex = Math.max(autocompleteIndex - 1, 0);
            updateActiveItem(items);
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            if (items.length > 0) {
                e.preventDefault();
                selectMentionItem(activeAutocomplete.textarea, items[autocompleteIndex], activeAutocomplete.triggerPos);
            }
        } else if (e.key === 'Escape') {
            hideAutocomplete();
        }
    }

    function updateActiveItem(items) {
        items.forEach((item, i) => item.classList.toggle('active', i === autocompleteIndex));
    }

    function selectMentionItem(inputElement, item, triggerPos) {
        const type = item.dataset.type;
        const id = item.dataset.id;
        const name = item.dataset.name;

        // Get role color if it's a role mention
        let roleColor = '#5865f2'; // default blurple
        if (type === 'role') {
            const roleData = autocompleteRoles.find(r => r.id === id);
            if (roleData && roleData.color) {
                roleColor = roleData.color;
            }
        }

        // Check if it's a contenteditable div or textarea
        const isContentEditable = inputElement.isContentEditable;

        if (isContentEditable) {
            // For contenteditable: use Range API to preserve existing mentions
            const selection = window.getSelection();
            if (!selection.rangeCount) {
                hideAutocomplete();
                return;
            }

            const range = selection.getRangeAt(0);

            // Find the text node containing the @ trigger
            const walker = document.createTreeWalker(inputElement, NodeFilter.SHOW_TEXT, null, false);
            let currentPos = 0;
            let targetNode = null;
            let nodeStartPos = 0;

            while (walker.nextNode()) {
                const node = walker.currentNode;
                const nodeLength = node.textContent.length;
                if (currentPos + nodeLength > triggerPos) {
                    targetNode = node;
                    nodeStartPos = currentPos;
                    break;
                }
                currentPos += nodeLength;
            }

            if (!targetNode) {
                hideAutocomplete();
                return;
            }

            // Calculate positions within the text node
            const offsetInNode = triggerPos - nodeStartPos;
            const currentText = targetNode.textContent;

            // Get cursor position relative to node
            let cursorOffset = range.endOffset;
            if (range.endContainer === targetNode) {
                cursorOffset = range.endOffset;
            } else {
                // Cursor is after this node, use end of node
                cursorOffset = currentText.length;
            }

            // Create the text before trigger (keep existing)
            const textBefore = currentText.substring(0, offsetInNode);
            const textAfter = currentText.substring(cursorOffset);

            // Create mention element
            const mentionSpan = document.createElement('span');
            mentionSpan.className = type === 'channel' ? 'discord-mention discord-mention-channel' : `discord-mention discord-mention-${type}`;
            mentionSpan.dataset.mentionType = type;
            mentionSpan.dataset.mentionId = id;
            mentionSpan.dataset.mentionName = name;
            mentionSpan.contentEditable = 'false';

            if (type === 'channel') {
                mentionSpan.textContent = '#' + name;
            } else {
                mentionSpan.textContent = '@' + name;
                mentionSpan.style.color = roleColor;
                mentionSpan.style.backgroundColor = roleColor + '20';
            }

            // Replace text node with: textBefore + mentionSpan + space + textAfter
            const fragment = document.createDocumentFragment();
            if (textBefore) {
                fragment.appendChild(document.createTextNode(textBefore));
            }
            fragment.appendChild(mentionSpan);

            const spaceAndAfter = document.createTextNode(' ' + textAfter);
            fragment.appendChild(spaceAndAfter);

            // Replace the original text node
            targetNode.parentNode.replaceChild(fragment, targetNode);

            // Set cursor after the space
            const newRange = document.createRange();
            newRange.setStart(spaceAndAfter, 1);
            newRange.collapse(true);
            selection.removeAllRanges();
            selection.addRange(newRange);

            inputElement.focus();
            syncMessageContent(inputElement);
        } else {
            // For textarea: insert raw Discord format
            const mention = type === 'channel' ? `<#${id}>` : `<@&${id}>`;
            const before = inputElement.value.substring(0, triggerPos);
            const after = inputElement.value.substring(inputElement.selectionStart);
            inputElement.value = before + mention + ' ' + after;

            const newPos = before.length + mention.length + 1;
            inputElement.setSelectionRange(newPos, newPos);
            inputElement.focus();
            inputElement.dispatchEvent(new Event('input'));
        }

        hideAutocomplete();
    }

    // Sync contenteditable content to hidden input (convert styled mentions to raw format)
    function syncMessageContent(editor) {
        const hiddenInput = document.getElementById('messageContentHidden');
        if (!hiddenInput) return;

        let rawContent = '';

        const processNode = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                rawContent += node.textContent;
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                if (node.classList && node.classList.contains('discord-mention')) {
                    const type = node.getAttribute('data-mention-type');
                    const id = node.getAttribute('data-mention-id');
                    if (type === 'channel') {
                        rawContent += `<#${id}>`;
                    } else if (type === 'role') {
                        rawContent += `<@&${id}>`;
                    } else {
                        rawContent += `<@${id}>`;
                    }
                } else if (node.tagName === 'BR') {
                    rawContent += '\n';
                } else {
                    for (const child of node.childNodes) {
                        processNode(child);
                    }
                }
            }
        };

        for (const child of editor.childNodes) {
            processNode(child);
        }

        hiddenInput.value = rawContent.trim();
    }

    // Render raw Discord format to styled HTML (for loading saved messages)
    function renderMentionsInEditor(editor, rawText) {
        if (!rawText) {
            editor.innerHTML = '';
            return;
        }

        let html = rawText;

        // Replace channel mentions <#id>
        html = html.replace(/<#(\d+)>/g, (match, id) => {
            const channel = autocompleteChannels.find(c => c.id === id);
            const name = channel ? channel.name : id;
            return `<span class="discord-mention discord-mention-channel" data-mention-type="channel" data-mention-id="${id}" data-mention-name="${name}" contenteditable="false">#${name}</span>`;
        });

        // Replace role mentions <@&id>
        html = html.replace(/<@&(\d+)>/g, (match, id) => {
            const role = autocompleteRoles.find(r => r.id === id);
            const name = role ? role.name : id;
            const color = role && role.color ? role.color : '#5865f2';
            return `<span class="discord-mention discord-mention-role" data-mention-type="role" data-mention-id="${id}" data-mention-name="${name}" contenteditable="false" style="color: ${color}; background-color: ${color}20;">@${name}</span>`;
        });

        // Replace user mentions <@id>
        html = html.replace(/<@!?(\d+)>/g, (match, id) => {
            return `<span class="discord-mention discord-mention-user" data-mention-type="user" data-mention-id="${id}" data-mention-name="${id}" contenteditable="false">@${id}</span>`;
        });

        editor.innerHTML = html;
        syncMessageContent(editor);
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', initMentionAutocomplete);

    /* TABS LOGIC */
    function openTab(evt, tabName) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add('active');
        } else {
            // Find the correct button to highlight
            document.querySelectorAll('.tab-btn').forEach(b => {
                if (b.getAttribute('onclick') && b.getAttribute('onclick').includes(tabName)) {
                    b.classList.add('active');
                }
            });
        }
        // Update URL without reload
        const tabKey = tabName.replace('tab-', '');
        const newUrl = '/admin/bot_controls?tab=' + tabKey;
        if (evt && window.location.href !== window.location.origin + newUrl) {
            history.pushState({ tab: tabKey }, '', newUrl);
        }
    }

    // Handle browser back/forward for tabs
    window.addEventListener('popstate', function (e) {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab') || 'embed';
        openTab(null, 'tab-' + tab);
    });

    /* DELIVERY METHOD TOGGLE */
    function toggleDeliveryMethod(method) {
        const channelWrapper = document.getElementById('channelSelectorWrapper');
        const roleWrapper = document.getElementById('roleSelectorWrapper');
        const dmWarning = document.getElementById('dmEveryoneWarning');

        // Reset all cards
        const cards = ['channel', 'dm_role', 'dm_everyone'];
        cards.forEach(cardMethod => {
            const card = document.getElementById('delivery-' + cardMethod);
            if (card) {
                if (cardMethod === method) {
                    // Active card styling based on method
                    let gradient, borderColor;
                    switch (cardMethod) {
                        case 'channel':
                            gradient = 'linear-gradient(135deg, rgba(88, 101, 242, 0.25), rgba(88, 101, 242, 0.1))';
                            borderColor = '#5865f2';
                            break;
                        case 'dm_role':
                            gradient = 'linear-gradient(135deg, rgba(250, 166, 26, 0.25), rgba(250, 166, 26, 0.1))';
                            borderColor = '#faa61a';
                            break;
                        case 'dm_everyone':
                            gradient = 'linear-gradient(135deg, rgba(67, 181, 129, 0.25), rgba(67, 181, 129, 0.1))';
                            borderColor = '#43b581';
                            break;
                    }
                    card.style.background = gradient;
                    card.style.borderColor = borderColor;
                } else {
                    // Inactive card styling
                    card.style.background = 'rgba(30, 33, 41, 0.6)';
                    card.style.borderColor = 'transparent';
                }
            }
        });

        // Show/hide appropriate selectors
        if (method === 'channel') {
            channelWrapper.style.display = 'block';
            roleWrapper.style.display = 'none';
            dmWarning.style.display = 'none';
        } else if (method === 'dm_role') {
            channelWrapper.style.display = 'none';
            roleWrapper.style.display = 'block';
            dmWarning.style.display = 'none';
        } else if (method === 'dm_everyone') {
            channelWrapper.style.display = 'none';
            roleWrapper.style.display = 'none';
            dmWarning.style.display = 'block';
        }
    }

    // Expose function globally for inline event handlers
    window.syncMessageContent = syncMessageContent;
    window.initMentionAutocomplete = initMentionAutocomplete;

    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMentionAutocomplete);
    } else {
        initMentionAutocomplete();
    }

    /* EMBED FORM AJAX SUBMISSION WITH PROGRESS */
    document.addEventListener('DOMContentLoaded', function () {
        const embedForm = document.getElementById('embedForm');
        if (embedForm) {
            embedForm.addEventListener('submit', async function (e) {
                const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked')?.value || 'channel';

                // For DM methods, use AJAX with progress display
                if (deliveryMethod === 'dm_role' || deliveryMethod === 'dm_everyone') {
                    e.preventDefault();
                    await sendEmbedWithProgress(embedForm, deliveryMethod);
                }
                // For channel delivery, let the form submit normally
            });
        }
    });

    async function sendEmbedWithProgress(form, deliveryMethod) {
        // Show progress modal with glassmorphism style
        const progressHtml = `
            <div style="text-align: center; padding: 20px;">
                <div style="margin-bottom: 25px;">
                    <div style="width: 80px; height: 80px; margin: 0 auto; border-radius: 50%; background: linear-gradient(135deg, rgba(88, 101, 242, 0.3), rgba(67, 181, 129, 0.3)); display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-paper-plane fa-2x" style="color: #fff;"></i>
                    </div>
                </div>
                <h3 style="margin-bottom: 8px; color: #fff; font-weight: 600;">Sending DMs...</h3>
                <p id="dmProgressText" style="color: rgba(255,255,255,0.7); margin-bottom: 25px; font-size: 0.95rem;">
                    Preparing to send messages...
                </p>
                <div style="background: rgba(0,0,0,0.2); border-radius: 12px; height: 8px; overflow: hidden; margin-bottom: 15px; backdrop-filter: blur(5px);">
                    <div id="dmProgressBar" style="background: linear-gradient(90deg, #5865f2, #43b581); height: 100%; width: 0%; transition: width 0.5s ease; border-radius: 12px;"></div>
                </div>
                <p id="dmProgressDetail" style="color: rgba(255,255,255,0.5); font-size: 0.8rem;">
                    <i class="fas fa-circle-notch fa-spin"></i> Connecting to bot...
                </p>
            </div>
        `;

        Swal.fire({
            html: progressHtml,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            customClass: { popup: 'modern-alert' }
        });

        try {
            // Collect form data
            const formData = new FormData(form);
            const payload = {};

            // Convert FormData to object
            for (let [key, value] of formData.entries()) {
                if (key.endsWith('[]')) {
                    const cleanKey = key.slice(0, -2);
                    if (!payload[cleanKey]) payload[cleanKey] = [];
                    payload[cleanKey].push(value);
                } else {
                    payload[key] = value;
                }
            }

            // Build the embed data structure
            const embedData = {
                delivery_method: deliveryMethod,
                dm_role_id: payload.dm_role_id || '',
                channel_id: payload.channel_id || '',
                message_content: payload.message_content || '',
                branding: {
                    enabled: true,
                    author_name: payload.author_name || '',
                    author_icon: payload.author_icon || '',
                    footer_text: payload.footer_text || '',
                    footer_icon: payload.footer_icon || ''
                },
                style: {
                    color: payload.embed_color || '#2196f3',
                    image: payload.image_url || '',
                    thumbnail: payload.thumbnail_url || ''
                },
                content: {
                    title: payload.embed_title || '',
                    description: payload.embed_description || '',
                    fields: []
                },
                buttons: []
            };

            // Add fields
            if (payload.field_name) {
                for (let i = 0; i < payload.field_name.length; i++) {
                    if (payload.field_name[i]) {
                        embedData.content.fields.push({
                            name: payload.field_name[i],
                            value: payload.field_value?.[i] || '',
                            inline: payload.field_inline?.includes(i.toString()) || false
                        });
                    }
                }
            }

            // Add buttons
            if (payload.btn_label) {
                for (let i = 0; i < payload.btn_label.length; i++) {
                    if (payload.btn_label[i] && payload.btn_url?.[i]) {
                        embedData.buttons.push({
                            label: payload.btn_label[i],
                            url: payload.btn_url[i]
                        });
                    }
                }
            }

            // Update progress text
            document.getElementById('dmProgressText').textContent = 'Sending messages to members...';
            document.getElementById('dmProgressBar').style.width = '30%';

            // Send request
            const response = await fetch('../includes/bot_api_proxy.php?action=send_embed', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(embedData)
            });

            const result = await response.json();

            document.getElementById('dmProgressBar').style.width = '100%';

            if (result.success) {
                const dmCount = result.data?.dm_count || 0;
                const dmErrors = result.data?.dm_errors || 0;

                Swal.fire({
                    icon: 'success',
                    title: 'DMs Sent Successfully!',
                    html: `
                        <div style="text-align: center; color: rgba(255,255,255,0.8);">
                            <div style="display: flex; justify-content: center; gap: 40px; margin-top: 20px;">
                                <div style="padding: 20px; background: rgba(67, 181, 129, 0.15); border-radius: 12px; border: 1px solid rgba(67, 181, 129, 0.3);">
                                    <div style="font-size: 2.5rem; color: #43b581; font-weight: bold;">${dmCount}</div>
                                    <div style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-top: 5px;">Sent Successfully</div>
                                </div>
                                ${dmErrors > 0 ? `
                                <div style="padding: 20px; background: rgba(240, 71, 71, 0.15); border-radius: 12px; border: 1px solid rgba(240, 71, 71, 0.3);">
                                    <div style="font-size: 2.5rem; color: #f04747; font-weight: bold;">${dmErrors}</div>
                                    <div style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-top: 5px;">Failed (DMs Disabled)</div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `,
                    customClass: { popup: 'modern-alert' }
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Send DMs',
                    text: result.error || 'Unknown error occurred',
                    customClass: { popup: 'modern-alert' }
                });
            }
        } catch (error) {
            console.error('DM Send Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Network Error',
                text: error.message || 'Failed to connect to the bot',
                customClass: { popup: 'modern-alert' }
            });
        }
    }

    /* AUTO GROW TEXTAREA */
    function autoGrow(element) {
        element.style.height = "5px";
        element.style.height = (element.scrollHeight) + "px";
    }

    /* FIELD & BUTTON GENERATORS */
    function addField() {
        const div = document.createElement('div');
        div.className = 'field-block';
        div.innerHTML = `
        <input type="text" name="field_name[]" class="transparent-input field-name" placeholder="Field Name">
        <textarea name="field_value[]" class="transparent-input field-value" placeholder="Field Value" rows="1" oninput="autoGrow(this)"></textarea>
        <div class="field-controls">
            <label><input type="checkbox" name="field_inline[]"> Inline</label>
            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-danger">&times;</button>
        </div>
    `;
        document.getElementById('fields-area').appendChild(div);
    }

    /* EMOJI PICKER LOGIC */
    const commonEmojis = [
        // Smilies & Emotion
        "😀", "😃", "😄", "😁", "😆", "😅", "😂", "🤣", "😊", "😇", "🙂", "🙃", "😉", "😌", "😍", "🥰", "😘", "😗", "😙", "😚", "😋", "😛", "😝", "😜", "🤪", "🤨", "🧐", "🤓", "😎", "🥸", "🤩", "🥳", "😏", "😒", "😞", "😔", "😟", "😕", "🙁", "☹️", "😣", "😖", "😫", "😩", "🥺", "😢", "😭", "😤", "😠", "😡", "🤬", "🤯", "😳", "🥵", "🥶", "😱", "😨", "😰", "😥", "😓", "🤗", "🤔", "🤭", "🤫", "🤥", "😶", "😐", "😑", "😬", "🙄", "😯", "😦", "😧", "😮", "😲", "🥱", "😴", "🤤", "😪", "😵", "🤐", "🥴", "🤢", "🤮", "🤧", "😷", "🤒", "🤕", "🤑", "🤠", "😈", "👿", "👹", "👺", "🤡", "💩", "👻", "💀", "☠️", "👽", "👾", "🤖", "🎃",
        // People & Body
        "👋", "🤚", "🖐", "✋", "🖖", "👌", "🤌", "🤏", "✌️", "🤞", "🤟", "🤘", "🤙", "👈", "👉", "👆", "🖕", "👇", "☝️", "👍", "👎", "✊", "👊", "🤛", "🤜", "👏", "🙌", "👐", "🤲", "🤝", "🙏", "✍️", "💅", "🤳", "💪", "🦾", "🦿", "🦵", "🦶", "👂", "🦻", "👃", "🧠", "🫀", "🫁", "🦷", "🦴", "👀", "👁️", "👅", "👄", "👶", "🧒", "👦", "👧", "🧑", "👱", "👨", "🧔", "👨‍🦰", "👨‍🦱", "👨‍🦳", "👨‍🦲", "👩", "👩‍🦰", "👩‍🦱", "👩‍🦳", "👩‍🦲", "🧓", "👴", "👵", "🙍", "🙎", "🙅", "🙆", "💁", "🙋", "🧏", "🙇", "🤦", "🤷", "👮", "🕵️", "💂", "🥷", "👷", "🤴", "👸", "👳", "👲", "🧕", "🤵", "👰", "🤰", "🤱", "👼", "🎅", "🤶", "🦸", "🦹", "🧙", "🧚", "🧛", "🧜", "🧝", "🧞", "🧟", "💆", "💇", "🚶", "🏃", "💃", "🕺", "🕴️", "👯", "🧖", "🧘",
        // Animals & Nature
        "🐵", "🐒", "🦍", "🦧", "🐶", "🐕", "🦮", "🐕‍🦺", "🐩", "🐺", "🦊", "🦝", "🐱", "🐈", "🐈‍⬛", "🦁", "🐯", "🐅", "🐆", "🐴", "🐎", "🦄", "🦓", "🦌", "🦬", "🐮", "ox", "🐃", "🐄", "🐷", "🐖", "🐗", "🐽", "🐏", "🐑", "🐐", "🐪", "🐫", "🦙", "🦒", "🐘", "🦣", "🦏", "🦛", "🐭", "🐁", "🐀", "🐹", "🐰", "🐇", "🐿️", "🦫", "🦔", "🦇", "🐻", "🐻‍❄️", "🐨", "🐼", "🦥", "🦦", "🦨", "🦘", "🦡", "🐾", "🦃", "🐔", "🐓", "🐣", "🐤", "🐥", "🐦", "🐧", "🕊️", "🦅", "🦆", "🦢", "🦉", "🦤", "🪶", "🦩", "🦚", "🦜", "🐸", "🐊", "🐢", "🦎", "🐍", "🐲", "🐉", "🦕", "🦖", "🐳", "🐋", "🐬", "🦭", "🐟", "🐠", "🐡", "🦈", "🐙", "🐚", "🐌", "🦋", "🐛", "🐜", "🐝", "🪲", "🐞", "🦗", "🪳", "🕷️", "🕸️", "🦂", "🦟", "🪰", "🪱", "🦠", "💐", "🌸", "💮", "🏵️", "🌹", "🥀", "🌺", "🌻", "🌼", "🌷", "🌱", "🪴", "🌲", "🌳", "🌴", "🌵", "🌾", "🌿", "☘️", "🍀", "🍁", "🍂", "🍃",
        // Food & Drink
        "🍇", "🍈", "🍉", "🍊", "🍋", "🍌", "🍍", "🥭", "🍎", "🍏", "🍐", "🍑", "🍒", "🍓", "🫐", "🥝", "🍅", "🫒", "🥥", "🥑", "🍆", "🥔", "🥕", "🌽", "🌶️", "🫑", "🥒", "🥬", "🥦", "🧄", "🧅", "🍄", "🥜", "🌰", "🍞", "🥐", "🥖", "🫓", "🥨", "🥯", "🥞", "🧇", "🧀", "🍖", "🍗", "🥩", "🥓", "🍔", "🍟", "🍕", "🌭", "🥪", "🌮", "🌯", "🫔", "🥙", "🧆", "🥚", "🍳", "🥘", "🍲", "🫕", "🥣", "🥗", "🍿", "🧈", "🧂", "🥫", "🍱", "🍘", "🍙", "🍚", "🍛", "🍜", "🍝", "🍠", "🍢", "🍣", "🍤", "🍥", "🥮", "🍡", "🥟", "🥠", "🥡", "🦀", "🦞", "🦐", "🦑", "🦪", "🍦", "🍧", "🍨", "🍩", "🍪", "🎂", "🍰", "🧁", "🥧", "🍫", "🍬", "🍭", "🍮", "🍯", "🍼", "🥛", "☕", "🫖", "🍵", "🍶", "🍾", "🍷", "🍸", "🍹", "🍺", "🍻", "🥂", "🥃", "🥤", "🧋", "🧃", "🧉", "🧊", "🥢", "🍽️", "🍴", "🥄",
        // Activities
        "⚽", "🏀", "🏈", "⚾", "🥎", "🎾", "🏐", "🏉", "🥏", "🎳", "🏏", "🏑", "🏒", "🥍", "🏓", "🏸", "🥊", "🥋", "🥅", "⛳", "⛸️", "🎣", "🤿", "🎽", "🎿", "🛷", "🥌", "🎯", "🪀", "🪁", "🎱", "🔮", "🪄", "🧿", "🎮", "🕹️", "🎰", "🎲", "🧩", "🧸", "🪅", "🪆", "♠️", "♥️", "♦️", "♣️", "♟️", "🃏", "🀄", "🎴", "🎭", "🖼️", "🎨", "🧵", "🪡", "🧶", "🪢",
        // Travel & Places
        "🚗", "🚕", "🚙", "🚌", "🚎", "🏎️", "🚓", "🚑", "🚒", "🚐", "🛻", "🚚", "🚛", "🚜", "🏍️", "🛵", "🚲", "🦼", "🦽", "🛺", "🛹", "🛼", "🚨", "🚔", "🚍", "🚘", "🚖", "🚡", "🚠", "🚟", "🚃", "🚋", "🚞", "🚝", "🚄", "🚅", "🚈", "🚂", "🚆", "🚇", "🚊", "🚉", "✈️", "🛫", "🛬", "🛩️", "💺", "🛰️", "🚀", "🛸", "🚁", "🛶", "⛵", "🚤", "🛥️", "🛳️", "⛴️", "🚢", "⚓", "🪝", "⛽", "🚧", "🚦", "🚥", "🚏", "🗺️", "🗿", "🗽", "🗼", "🏰", "🏯", "🏟️", "🎡", "🎢", "🎠", "⛲", "⛱️", "🏖️", "🏝️", "🏜️", "🌋", "⛰️", "🏔️", "🗻", "🏕️", "⛺", "🏠", "🏡", "🏘️", "🏚️", "🏗️", "🏭", "🏢", "🏬", "🏣", "🏤", "🏥", "🏦", "🏨", "🏪", "🏫", "🏩", "💒", "🏛️", "⛪", "🕌", "🛕", "🕍", "🕋", "⛩️",
        // Objects
        "🔇", "🔈", "🔉", "🔊", "📢", "📣", "📯", "🔔", "🔕", "🎼", "🎵", "🎶", "🎙️", "🎚️", "🎛️", "🎤", "🎧", "📻", "🎷", "🪗", "🎸", "🎹", "🎺", "🎻", "🪕", "🥁", "🪘", "📱", "📲", "☎️", "📞", "📟", "📠", "🔋", "🔌", "💻", "🖥️", "🖨️", "⌨️", "🖱️", "🖲️", "💽", "💾", "💿", "📀", "🧮", "🎥", "🎞️", "📽️", "🎬", "📺", "📷", "📸", "📹", "📼", "🔍", "🔎", "🕯️", "💡", "🔦", "🏮", "🪔", "📔", "📕", "📖", "📗", "📘", "📙", "📚", "📓", "📒", "📃", "📜", "📄", "📰", "🗞️", "📑", "🔖", "🏷️", "💰", "🪙", "💴", "💵", "💶", "💷", "💸", "💳", "🧾", "✉️", "📧", "📨", "📩", "📤", "📥", "📦", "📫", "📪", "📬", "📭", "📮", "🗳️", "✏️", "✒️", "🖋️", "🖊️", "🖌️", "🖍️", "📝", "💼", "📁", "📂", "🗂️", "📅", "📆", "🗒️", "🗓️", "📇", "📈", "📉", "📊", "📋", "📌", "📍", "📎", "🖇️", "📏", "📐", "✂️", "🗃️", "🗄️", "🗑️", "🔒", "🔓", "🔏", "🔐", "🔑", "🗝️", "🔨", "🪓", "⛏️", "⚒️", "🛠️", "🗡️", "⚔️", "🔫", "🪃", "🏹", "🛡️", "🪚", "🔧", "🪛", "🔩", "⚙️", "🗜️", "⚖️", "🦯", "🔗", "⛓️", "🪝", "🧰", "🧲", "🪜", "⚗️", "🧪", "🧫", "🧬", "🔬", "🔭", "📡", "💉", "🩸", "💊", "🩹", "🩺", "🚪", "🛗", "🪞", "🪟", "🛏️", "🛋️", "🪑", "🚽", "🪠", "🚿", "🛁", "🪤", "🪒", "🧴", "🧷", "🧹", "🧺", "🧻", "🪣", "🧼", "🫧", "🪥", "🧽", "🧯", "🛒", "🚬", "⚰️", "🪦", "⚱️",
        // Symbols
        "🏧", "🚮", "🚰", "♿", "🚹", "🚺", "🚻", "🚼", "🚾", "🛂", "🛃", "🛄", "🛅", "⚠️", "🚸", "⛔", "🚫", "🚳", "🚭", "🚯", "🚱", "🚷", "📵", "🔞", "☢️", "☣️", "⬆️", "↗️", "➡️", "↘️", "⬇️", "↙️", "⬅️", "↖️", "↕️", "↔️", "↩️", "↪️", "⤴️", "⤵️", "🔃", "🔄", "🔙", "🔚", "🔛", "🔜", "🔝", "🛐", "⚛️", "🕉️", "✡️", "☸️", "☯️", "✝️", "☦️", "☪️", "☮️", "🕎", "🔯", "♈", "♉", "♊", "♋", "♌", "♍", "♎", "♏", "♐", "♑", "♒", "♓", "⛎", "🔀", "🔁", "🔂", "▶️", "⏩", "⏭️", "⏯️", "◀️", "⏪", "⏮️", "🔼", "⏫", "🔽", "⏬", "⏸️", "⏹️", "⏺️", "⏏️", "🎦", "🔅", "🔆", "📶", "📳", "📴", "♀️", "♂️", "⚧", "✖️", "➕", "➖", "➗", "♾️", "‼️", "⁉️", "❓", "❔", "❕", "❗", "〰️", "💱", "💲", "⚕️", "♻️", "⚜️", "🔱", "📛", "🔰", "⭕", "✅", "☑️", "✔️", "❌", "❎", "➰", "➿", "〽️", "✳️", "✴️", "❇️", "™️", "🔠", "🔡", "🔢", "🔣", "🔤", "🅰️", "🆎", "🅱️", "🆑", "🆒", "🆓", "ℹ️", "🆔", "Ⓜ️", "🆕", "🆖", "🅾️", "🆗", "🅿️", "🆘", "🆙", "🆚", "🈁", "🈂️", "🈷️", "🈶", "指", "🉐", "🈹", "🈚", "🈲", "🉑", "🈸", "🈴", "🈳", "㊗️", "㊙️", "🈺", "🈵", "🔴", "🟠", "🟡", "🟢", "🔵", "🟣", "🟤", "⚫", "⚪", "🟥", "🟧", "🟨", "🟩", "🟦", "🟪", "🟤", "⬛", "⬜", "◼️", "◻️", "◾", "◽", "▪️", "▫️", "🔶", "🔷", "🔸", "🔹", "🔺", "🔻", "💠", "🔘", "🔳", "🔲",
        // Flags
        "🏁", "🚩", "🎌", "🏴", "🏳️", "🏳️‍🌈", "🏳️‍⚧️", "🏴‍☠️"
    ];

    function renderEmojis(filter = "") {
        const grid = document.getElementById('emojiGrid');
        grid.innerHTML = "";
        const term = filter.toLowerCase();

        commonEmojis.forEach(emoji => {
            if (term === "" || emoji.includes(term)) {
                const div = document.createElement('div');
                div.className = 'emoji-item';
                div.innerText = emoji;
                div.onclick = () => selectEmoji(emoji);
                grid.appendChild(div);
            }
        });
    }

    function toggleEmojiPicker() {
        const modal = document.getElementById('emojiPickerModal');
        const isOpen = modal.classList.contains('active');

        if (!isOpen) {
            modal.classList.add('active');
            renderEmojis();
            document.querySelector('.emoji-search-input').focus();
        } else {
            modal.classList.remove('active');
        }
    }

    function filterEmojis(val) {
        renderEmojis(val);
    }

    function selectEmoji(emoji) {
        document.getElementById('edit-btn-emoji').value = emoji;
        document.getElementById('emojiTriggerBtn').innerHTML = `<span style="font-size:1.5rem;">${emoji}</span>`;
        updateBtnPreview();
        toggleEmojiPicker();
    }

    // Close picker when clicking outside
    document.addEventListener('click', function (e) {
        const picker = document.querySelector('.emoji-picker-container');
        if (picker && !picker.contains(e.target)) {
            document.getElementById('emojiPickerModal').classList.remove('active');
        }
    });

    /* BUTTON EDITOR LOGIC */
    function openButtonEditor() {
        document.getElementById('button-editor').style.display = 'block';
        document.getElementById('btn-add-link-trigger').style.display = 'none';

        // Reset inputs
        document.getElementById('edit-btn-url').value = '';
        document.getElementById('edit-btn-label').value = '';
        document.getElementById('edit-btn-emoji').value = '';
        document.getElementById('emojiTriggerBtn').innerHTML = '<i class="fas fa-plus"></i>';

        updateBtnPreview();
    }

    function closeButtonEditor() {
        document.getElementById('button-editor').style.display = 'none';
        document.getElementById('btn-add-link-trigger').style.display = 'flex';
    }

    function updateBtnPreview() {
        const label = document.getElementById('edit-btn-label').value || 'Your button';
        const emoji = document.getElementById('edit-btn-emoji').value;
        const btn = document.getElementById('btn-preview');

        btn.innerHTML = (emoji ? emoji + ' ' : '') + label;
    }

    function saveButton() {
        const url = document.getElementById('edit-btn-url').value;
        const label = document.getElementById('edit-btn-label').value;
        const emoji = document.getElementById('edit-btn-emoji').value;

        if (!url || !label) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Info',
                text: 'Please provide both a URL and a Label.',
                customClass: { popup: 'modern-alert' }
            });
            return;
        }

        // Create Active Button Element
        const div = document.createElement('div');
        div.className = 'active-button-item';
        div.innerHTML = `
            <div class="active-button-visual">
                ${emoji ? '<span class="btn-emoji">' + emoji + '</span>' : ''}
                <span class="btn-label">${label}</span>
                <a href="${url}" target="_blank" class="btn-open-link"><i class="fas fa-external-link-alt"></i></a>
            </div>
            <div class="active-button-actions">
                 <button type="button" onclick="this.closest('.active-button-item').remove()" class="btn-delete-item"><i class="fas fa-trash"></i></button>
            </div>
            
            <!-- Hidden Inputs for Form Submission -->
            <input type="hidden" name="btn_label[]" value="${label}">
            <input type="hidden" name="btn_url[]" value="${url}">
            <input type="hidden" name="btn_emoji[]" value="${emoji}">
        `;

        document.getElementById('active-buttons-list').appendChild(div);
        closeButtonEditor();
    }

    /* IMAGE UPLOAD LOGIC */
    function triggerUpload(fieldName) {
        document.getElementById('file_' + fieldName).click();
    }

    function uploadImage(fileInput, fieldName) {
        if (fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];
            const formData = new FormData();
            formData.append('file', file);

            // Get elements with null check
            const previewEl = document.getElementById('preview_' + fieldName);
            const inputEl = document.getElementById('input_' + fieldName);

            // Special handling for external_image_url (uses img tag instead of background)
            const isExternalImage = fieldName === 'external_image_url';
            const wrapperEl = isExternalImage ? document.getElementById('external_image_preview_wrapper') : null;
            const addBtnEl = isExternalImage ? document.getElementById('btnAddExternalImage') : null;

            if (!isExternalImage && !previewEl) {
                console.error('Preview element not found: preview_' + fieldName);
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Error',
                    text: 'Preview element not found',
                    customClass: { popup: 'modern-alert' }
                });
                return;
            }

            // Show loading state
            if (isExternalImage && addBtnEl) {
                addBtnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            } else if (previewEl) {
                var originalContent = previewEl.innerHTML;
                previewEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.url) {
                        // Update Hidden Input (with null check)
                        if (inputEl) {
                            inputEl.value = data.url;
                        } else {
                            console.warn('Input element not found: input_' + fieldName);
                        }

                        // Handle external image differently
                        if (isExternalImage) {
                            if (wrapperEl) {
                                wrapperEl.style.display = 'block';
                                document.getElementById('preview_external_image').src = data.url;
                            }
                            if (addBtnEl) {
                                addBtnEl.style.display = 'none';
                            }
                        } else {
                            // Update Preview (standard embed images)
                            previewEl.classList.add('has-image');
                            previewEl.style.backgroundImage = `url('${data.url}')`;
                            previewEl.innerHTML = '';
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: data.error || 'Unknown error',
                            customClass: { popup: 'modern-alert' }
                        });
                        if (isExternalImage && addBtnEl) {
                            addBtnEl.innerHTML = '<i class="fas fa-image"></i> Add image (outside embed)';
                        } else if (previewEl) {
                            previewEl.innerHTML = originalContent; // Revert
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Error',
                        text: 'An unexpected error occurred.',
                        customClass: { popup: 'modern-alert' }
                    });
                    if (isExternalImage && addBtnEl) {
                        addBtnEl.innerHTML = '<i class="fas fa-image"></i> Add image (outside embed)';
                    } else if (previewEl) {
                        previewEl.innerHTML = originalContent; // Revert
                    }
                });
        }
    }

    // Remove external image function
    function removeExternalImage() {
        document.getElementById('input_external_image_url').value = '';
        document.getElementById('external_image_preview_wrapper').style.display = 'none';
        document.getElementById('preview_external_image').src = '';
        document.getElementById('btnAddExternalImage').style.display = 'block';
        document.getElementById('btnAddExternalImage').innerHTML = '<i class="fas fa-image"></i> Add image (outside embed)';
    }

    /* SIDE TOOLS LOGIC */
    function updateEmbedColor(color) {
        document.getElementById('embedStripe').style.backgroundColor = color;
    }

    function showEmbed() {
        document.getElementById('embedWrapper').style.display = 'flex';
        document.getElementById('btnAddEmbed').style.display = 'none';
    }

    function deleteEmbed() {
        showConfirm('Delete Embed', 'Are you sure you want to delete this embed? This action cannot be undone.', () => {
            // Confirmed action
            // Clear inputs
            document.querySelector('.embed-title').value = '';
            document.querySelector('.embed-desc').value = '';
            document.querySelector('.author-name').value = '';
            document.querySelector('.footer-text').value = '';
            document.querySelectorAll('.media-link').forEach(el => el.value = '');
            document.getElementById('fields-area').innerHTML = '';
            // Reset Placeholders
            document.querySelectorAll('.visual-placeholder').forEach(el => {
                el.classList.remove('has-image');
                el.style.backgroundImage = 'none';
                if (el.classList.contains('thumbnail-box') || el.classList.contains('image-box')) {
                    el.innerHTML = '<i class="fas fa-image"></i>';
                    // Find hidden input sibling and clear it
                    // Logic based on DOM PromptImage
                    let wrapperP = el.closest('div');
                }
            });

            // Clear hidden inputs for images
            ['input_author_icon', 'input_thumbnail_url', 'input_image_url', 'input_footer_icon'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });

            // Hide Wrapper, Show Button
            document.getElementById('embedWrapper').style.display = 'none';
            document.getElementById('btnAddEmbed').style.display = 'block';

            showAlert('Deleted!', 'Your embed has been cleared.');
        }, 'Delete', 'danger');
    }


    /* GENERIC CHANNEL SELECTOR */
    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.custom-select-container')) {
            document.querySelectorAll('.custom-select-container').forEach(el => el.classList.remove('open'));
        }
    });

    function toggleChannelList(containerId) {
        const container = document.getElementById(containerId);
        const isOpen = container.classList.contains('open');
        // Close others
        document.querySelectorAll('.custom-select-container').forEach(el => el.classList.remove('open'));

        if (!isOpen) {
            container.classList.add('open');
        }
    }

    function selectChannel(item, id, name) {
        const container = item.closest('.custom-select-container');
        container.querySelector('.real-channel-input').value = id;
        const triggerText = container.querySelector('.selected-name');
        triggerText.innerText = name;
        triggerText.style.color = '#fff';
        container.classList.remove('open');
    }

    function filterChannels(searchInput) {
        const term = searchInput.value.toLowerCase();
        const container = searchInput.closest('.custom-select-dropdown');
        const listWrapper = container.querySelector('.channel-list-items');
        if (!listWrapper) return;

        const elements = listWrapper.children;
        let currentHeader = null;
        let currentHeaderMatches = false;

        for (let el of elements) {
            if (el.classList.contains('channel-category-header')) {
                currentHeader = el;
                // Check if category name matches
                currentHeaderMatches = el.innerText.toLowerCase().includes(term);
                // Hide initially, show if needed later
                el.style.display = 'none';
            } else if (el.classList.contains('channel-item')) {
                if (el.classList.contains('disabled')) continue; // Skip 'No channels found'

                const text = el.innerText.toLowerCase();
                const matches = text.includes(term) || currentHeaderMatches;

                el.style.display = matches ? 'flex' : 'none';

                if (matches && currentHeader) {
                    currentHeader.style.display = 'block';
                }
            }
        }
    }

    /* ====== FEED DROPDOWN FUNCTIONS (Discord Feed Settings Style) ====== */
    // Toggle feed dropdown open/close
    function toggleFeedDropdown(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const isOpen = container.classList.contains('open');

        // Close all feed dropdowns
        document.querySelectorAll('.feed-dropdown-container').forEach(el => el.classList.remove('open'));

        if (!isOpen) {
            container.classList.add('open');

            // Attach click listeners to items
            attachFeedDropdownListeners(containerId);

            // Focus search input
            const searchInput = container.querySelector('.feed-dropdown-search input');
            if (searchInput) {
                setTimeout(() => searchInput.focus(), 50);
            }
        }
    }

    // Attach click listeners to feed dropdown items
    function attachFeedDropdownListeners(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.querySelectorAll('.feed-dropdown-item').forEach(item => {
            // Remove existing listeners to prevent duplicates
            item.removeEventListener('click', handleFeedItemClick);
            item.addEventListener('click', handleFeedItemClick);
        });
    }

    // Handle feed dropdown item click
    function handleFeedItemClick(e) {
        const item = e.currentTarget;
        const container = item.closest('.feed-dropdown-container');
        if (!container) return;

        const id = item.dataset.id;
        const name = item.dataset.name;
        const type = item.dataset.type || 'text';

        selectFeedChannel(container.id, id, name, type);
    }

    // Select a channel in feed dropdown
    function selectFeedChannel(containerId, id, name, type) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Update hidden input based on container ID
        let hiddenInput = null;
        let selectedText = null;

        if (containerId === 'embedChannelDropdown') {
            hiddenInput = document.getElementById('embedChannelInput');
            selectedText = document.getElementById('embedChannelText');
        } else if (containerId === 'serverWelcomeChannelDropdown') {
            hiddenInput = document.getElementById('serverWelcomeChannelId');
            selectedText = document.getElementById('serverWelcomeChannelText');
        }

        else if (containerId === 'eventChannelDropdown') {
            hiddenInput = document.getElementById('eventChannelInput');
            selectedText = document.getElementById('eventChannelText');
        }

        else if (containerId === 'voiceControlDropdown') {
            hiddenInput = document.getElementById('voiceChannelSelect');
            selectedText = document.getElementById('voiceControlText');
        } else if (containerId === 'welcomeSoundChannelDropdown') {
            hiddenInput = document.getElementById('welcomeSoundChannel');
            selectedText = document.getElementById('welcomeSoundChannelText');
            // Also sync the DM section dropdown text
            var dmText = document.getElementById('voiceWelcomeDmChannelText');
            if (dmText) {
                let syncIcon = type === 'voice' ? '<i class="fas fa-volume-up" style="color: #43b581;"></i>' : '<i class="fas fa-volume-up" style="color: #43b581;"></i>';
                dmText.innerHTML = id ? `${syncIcon} ${name}` : `${syncIcon} All Voice Channels`;
            }
        } else if (containerId === 'voiceWelcomeDmChannelDropdown') {
            hiddenInput = document.getElementById('welcomeSoundChannel');
            selectedText = document.getElementById('voiceWelcomeDmChannelText');
            // Also sync the Sound section dropdown text
            var soundText = document.getElementById('welcomeSoundChannelText');
            if (soundText) {
                let syncIcon = type === 'voice' ? '<i class="fas fa-volume-up" style="color: #43b581;"></i>' : '<i class="fas fa-volume-up" style="color: #43b581;"></i>';
                soundText.innerHTML = id ? `${syncIcon} ${name}` : `${syncIcon} All Voice Channels`;
            }
        } else if (containerId === 'serverLeaveChannelDropdown') {
            hiddenInput = document.getElementById('serverLeaveChannelId');
            selectedText = document.getElementById('serverLeaveChannelText');
        } else if (containerId === 'voiceLogsChannelDropdown') {
            hiddenInput = document.getElementById('voiceLogsChannelId');
            selectedText = document.getElementById('voiceLogsChannelText');
        }

        else if (containerId === 'eventPingRoleDropdown') {
            hiddenInput = document.getElementById('eventPingRoleInput');
            selectedText = document.getElementById('eventPingRoleText');
        }

        if (hiddenInput) hiddenInput.value = id;
        if (selectedText) {
            let icon;
            const item = container.querySelector(`.feed-dropdown-item[data-id="${id}"]`);
            const roleColor = item ? item.dataset.color : '#99aab5';

            if (type === 'role') {
                if (id === '') {
                    selectedText.innerHTML = '-- No role ping --';
                } else {
                    icon = `<i class="fas fa-at" style="color: ${roleColor};"></i>`;
                    selectedText.innerHTML = `${icon} <span style="color: ${roleColor};">@${name}</span>`;
                }
            } else if (type === 'voice') {
                icon = '<i class="fas fa-volume-up" style="color: #43b581;"></i>';
                selectedText.innerHTML = `${icon} ${name}`;
            } else if (type === 'forum') {
                icon = '<i class="fas fa-hashtag" style="color: #57F287;"></i>';
                selectedText.innerHTML = `${icon} ${name}`;
            } else {
                icon = '<i class="fas fa-hashtag" style="color: #5865f2;"></i>';
                selectedText.innerHTML = `${icon} ${name}`;
            }
        }

        // Mark selected item
        container.querySelectorAll('.feed-dropdown-item').forEach(item => {
            item.classList.toggle('selected', item.dataset.id === id);
        });

        // Close dropdown
        container.classList.remove('open');
    }

    // Filter feed dropdown channels
    function filterFeedChannels(containerId, query) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const listContainer = container.querySelector('.feed-dropdown-list');
        if (!listContainer) return;

        const filterLower = query.toLowerCase();
        let currentCategory = null;
        let currentCategoryVisible = false;

        // Handle categories and items
        Array.from(listContainer.children).forEach(el => {
            if (el.classList.contains('feed-dropdown-category')) {
                // Hide category initially
                el.style.display = 'none';
                currentCategory = el;
                currentCategoryVisible = false;
            } else if (el.classList.contains('feed-dropdown-item')) {
                const name = (el.dataset.name || '').toLowerCase();
                const category = (el.dataset.category || '').toLowerCase();
                const matches = !query || name.includes(filterLower) || category.includes(filterLower);

                el.style.display = matches ? 'flex' : 'none';

                if (matches && currentCategory) {
                    currentCategory.style.display = 'block';
                    currentCategoryVisible = true;
                }
            }
        });
    }

    // Close feed dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.feed-dropdown-container')) {
            document.querySelectorAll('.feed-dropdown-container').forEach(el => el.classList.remove('open'));
        }
    });

    /* TEXT FORMATTING LOGIC - Works with contenteditable and textareas */
    let activeInputForFormat = null;
    let lastSelection = null;

    // Track last active input (textarea, input, or contenteditable)
    document.addEventListener('focusin', (e) => {
        const target = e.target;
        if (target && (
            target.tagName === 'TEXTAREA' ||
            (target.tagName === 'INPUT' && target.type === 'text') ||
            target.isContentEditable
        )) {
            activeInputForFormat = target;
        }
    });

    // Save selection for contenteditable before losing focus
    document.addEventListener('selectionchange', () => {
        const sel = window.getSelection();
        if (sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            const container = range.commonAncestorContainer;
            const editor = container.nodeType === 3 ? container.parentElement : container;
            if (editor && (editor.isContentEditable || editor.closest('[contenteditable]'))) {
                lastSelection = range.cloneRange();
            }
        }
    });

    function applyFormat(type) {
        const editor = document.getElementById('messageContentEditor');
        if (!editor) return;

        // Focus the editor first
        editor.focus();

        const sel = window.getSelection();
        let selectedText = '';

        // Try to restore last selection if current is outside editor
        if (lastSelection && (!sel.rangeCount || !editor.contains(sel.anchorNode))) {
            sel.removeAllRanges();
            sel.addRange(lastSelection);
        }

        if (sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            selectedText = range.toString();
        }

        let prefix = '', suffix = '';
        switch (type) {
            case 'h1': prefix = '# '; suffix = ''; break;
            case 'bold': prefix = '**'; suffix = '**'; break;
            case 'italic': prefix = '*'; suffix = '*'; break;
            case 'underline': prefix = '__'; suffix = '__'; break;
        }

        // Get the raw content, apply markdown, and re-render
        syncMessageContent(editor);
        const hiddenInput = document.querySelector('.message-content-hidden');
        let rawText = hiddenInput ? hiddenInput.value : '';

        // If there's selected text, we need to find it in raw text and wrap it
        if (selectedText.trim()) {
            // Simple approach: append formatted text if not found in raw
            // For a more sophisticated approach, we'd need to track cursor position in raw text
            const formattedText = prefix + selectedText + suffix;

            // Insert at cursor position by using execCommand
            document.execCommand('insertText', false, formattedText);

            // Remove the plain selected text that was replaced
            // (execCommand already does this)
        } else {
            // No selection - insert placeholder
            const placeholder = prefix + 'text' + suffix;
            document.execCommand('insertText', false, placeholder);
        }

        // Sync the content after formatting
        syncMessageContent(editor);

        // Show a Discord-style preview toast
        Toast.fire({
            icon: 'info',
            title: `${type.charAt(0).toUpperCase() + type.slice(1)} applied`,
            timer: 1000
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('embedForm');
        if (form) {
            form.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    // Allow Textarea newlines
                    if (e.target.tagName === 'TEXTAREA') return;

                    // Prevent Default Form Submit
                    e.preventDefault();

                    // Button Editor Specifics
                    if (e.target.id === 'edit-btn-label' || e.target.id === 'edit-btn-url') {
                        saveButton();
                    }

                    // Emoji Search
                    if (e.target.classList.contains('emoji-search-input')) {
                        // Optional: select first emoji
                    }
                }
            });
        }
    });

    function toggleEventForm() {
        const formContainer = document.getElementById('event-form-container');
        const listContainer = document.getElementById('active-events-list');
        const btnText = document.querySelector('.event-manager-header button');

        if (formContainer.style.display === 'none' || !formContainer.style.display) {
            formContainer.style.display = 'block';
            listContainer.style.display = 'none';
            btnText.innerHTML = '<i class="fas fa-times"></i> CANCEL';
            btnText.classList.replace('primary', 'secondary');
        } else {
            formContainer.style.display = 'none';
            listContainer.style.display = 'block';
            btnText.innerHTML = '<i class="fas fa-plus"></i> CREATE EVENT';
            btnText.classList.replace('secondary', 'primary');
        }
    }

    /* ROLE SELECTOR LOGIC */
    let selectedRoles = ['all'];

    function toggleRoleSelection(element, roleId) {
        if (roleId === 'all') {
            selectedRoles = ['all'];
            // Visual Reset
            document.querySelectorAll('.role-item').forEach(el => {
                if (el.dataset.id === 'all') {
                    el.querySelector('.role-checkbox').classList.add('checked');
                } else {
                    el.querySelector('.role-checkbox').classList.remove('checked');
                }
            });
        } else {
            // Remove        'all' if present
            if (selectedRoles.includes('all')) {
                selectedRoles = [];
                document.querySelector('.role-item[data-id="all"] .role-checkbox').classList.remove('checked');
            } const checkbox = element.querySelector('.role-checkbox');
            if (selectedRoles.includes(roleId)) {
                selectedRoles = selectedRoles.filter(id => id !== roleId);
                checkbox.classList.remove('checked');
            } else {
                selectedRoles.push(roleId);
                checkbox.classList.add('checked');
            }

            // If empty, revert to all? No, let user decide. Or maybe show placeholder.
            if (selectedRoles.length === 0) {
                // optional: auto-select all?
                // selectedRoles = ['all'];
                // document.querySelector('.role-item[data-id="all"] .role-checkbox').classList.add('checked');
            }
        }

        // Update Hidden Input & Label
        document.getElementById('input_role_ids').value = selectedRoles.join(',');
        const label = document.getElementById('roleSelectLabel');
        if (selectedRoles.includes('all')) {
            label.innerText = 'Everyone (No restrictions)';
        } else {
            label.innerText = `${selectedRoles.length} Roles Selected`;
        }
    }

    /* MOCK EVENT LOADING */
    document.addEventListener('DOMContentLoaded', () => {
        loadActiveEvents();
    });

    function loadActiveEvents() {
        // In real apps, fetch via AJAX
        const container = document.getElementById('active-events-list');
        // Currently empty logic is handled by PHP if e                   mpty
        // Adding Dummy Data for Visualization
        /*
        con        st dummyHTML = `
                   <div class="config-card" style="display: flex; gap: 15px; align-items: center; border-left: 4px solid #5865F2;">
                <div style="width: 80px; height: 80px; background: #40444b; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-calendar-day" style="font-size: 2rem; color: #fff;"></i>
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0; color: #fff;">Board Game Night</h4>
                    <p style="margin: 5px 0; color: #b9bbbe; font-size: 0.9rem;">
                        <i class="fas fa-clock"></i> Friday, 8:00 PM • <i class="fas fa-hourglass-half"></i> 2h
                        <br>
                        <i class="fas fa-map-marker-alt"></i> Voice Chat
                    </p>
                    <div style="display: flex; gap: 10px; font-size: 0.8rem; margin-top: 5px;">
                        <span style="background: #2f3136; padding: 2px 8px; border-radius: 4px; color: #43b581;"><i class="fas fa-check-circle"></i> 12 Going</span>
                        <span style="background: #2f3136; padding: 2px 8px; border-radius: 4px; color: #faa61a;"><i class="fas fa-question-circle"></i> 5 Maybe</span>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 5px;">
                    <button class="btn primary sm"><i class="fas fa-pen"></i> Edit</button>
                    <button class="btn danger sm"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </div>
        `;
        if(container.innerHTML.includes('No active events')) {
           // Uncomment in prod or when testing: container.innerHTML = dummyHTML;
        }
        */
    }
    // ====== MODERN TOAST NOTIFICATIONS - DEBUG V1 ======
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: { popup: 'modern-toast' },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // ====== VOICE CONTROL AJAX FUNCTIONS ======
    async function joinVoiceChannel() {
        const channelId = document.getElementById('voiceChannelSelect').value;

        if (!channelId) {
            Toast.fire({
                icon: 'warning',
                title: 'กรุณาเลือก Voice Channel ก่อน'
            });
            return;
        }

        try {
            const response = await fetch('../includes/bot_api_proxy.php?action=join_voice', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ channel_id: channelId })
            });
            const data = await response.json();

            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: '🔊 ' + (data.data?.message || 'เข้าห้องเสียงสำเร็จ!')
                });
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.error || 'เข้าห้องเสียงไม่สำเร็จ'
                });
            }
        } catch (error) {
            console.error('Join voice error:', error);
            Toast.fire({
                icon: 'error',
                title: 'Network Error'
            });
        }
    }

    async function leaveVoiceChannel() {
        try {
            const response = await fetch('../includes/bot_api_proxy.php?action=leave_voice', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });
            const data = await response.json();

            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: '📴 ' + (data.data?.message || 'ออกจากห้องเสียงแล้ว')
                });
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.error || 'ออกจากห้องเสียงไม่สำเร็จ'
                });
            }
        } catch (error) {
            console.error('Leave voice error:', error);
            Toast.fire({
                icon: 'error',
                title: 'Network Error'
            });
        }
    }

    // ====== BOT SETTINGS AJAX FUNCTION ======
    
    // Activity Texts Management
    let botActivityTexts = <?php echo json_encode(isset($botStatus['activity_texts']) && is_array($botStatus['activity_texts']) ? $botStatus['activity_texts'] : [isset($botStatus['activity_text']) ? $botStatus['activity_text'] : '']); ?>;
    
    // Default config: limit to 2 options. If none exist, prepopulate with default
    if (!botActivityTexts || botActivityTexts.length === 0 || (botActivityTexts.length === 1 && botActivityTexts[0].trim() === '')) {
        botActivityTexts = ['ARAM | S.T.O.R.M.']; 
    }
    
    function renderBotActivityTexts() {
        const container = document.getElementById('botActivityTextsList');
        if (!container) return;
        container.innerHTML = '';
        
        botActivityTexts.forEach((text, index) => {
            const row = document.createElement('div');
            row.style.display = 'flex';
            row.style.gap = '10px';
            row.style.marginBottom = '10px';
            row.innerHTML = `
                <input type="text" class="modern-input activity-text-input" style="flex: 1;" placeholder="e.g., ARAM | S.T.O.R.M." value="${escapeHtml(text)}" oninput="updateBotActivityText(${index}, this.value)">
                <button type="button" class="btn danger sm" style="padding: 0 12px;" onclick="removeBotActivityText(${index})" ${botActivityTexts.length <= 1 ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''}>
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(row);
        });
        
        // Hide add button if reaching limit
        const addBtn = document.getElementById('addActivityTextBtn');
        if (addBtn) {
            addBtn.style.display = botActivityTexts.length >= 2 ? 'none' : 'inline-block';
        }
    }

    function addBotActivityText() {
        if (botActivityTexts.length < 2) {
            botActivityTexts.push('');
            renderBotActivityTexts();
        }
    }

    function removeBotActivityText(index) {
        if (botActivityTexts.length > 1) {
            botActivityTexts.splice(index, 1);
            renderBotActivityTexts();
        }
    }

    function updateBotActivityText(index, value) {
        botActivityTexts[index] = value;
    }

    function getBotActivityTexts() {
        // Filter out completely empty rows before saving
        return botActivityTexts.filter(t => t.trim() !== '');
    }

    // Call render once on load
    document.addEventListener('DOMContentLoaded', () => {
        renderBotActivityTexts();
    });

    async function saveBotSettings() {
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        try {
            const settings = {
                name: document.getElementById('botNameInput').value,
                status: document.getElementById('botStatusSelect').value,
                activity_type: document.getElementById('activityTypeSelect').value,
                activity_texts: getBotActivityTexts(),
                activity_interval: parseInt(document.getElementById('activityIntervalInput').value) || 15
            };

            // Include avatar if uploaded
            const avatarBase64 = document.getElementById('input_bot_avatar').value;
            if (avatarBase64) {
                settings.avatar_base64 = avatarBase64;
            }

            const response = await fetch('../includes/bot_api_proxy.php?action=update_bot_settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings)
            });
            const data = await response.json();

            if (data.success) {
                Toast.fire({
                    icon: 'success',
                    title: '✅ บันทึกการตั้งค่าสำเร็จ!'
                });
            } else {
                Toast.fire({
                    icon: 'error',
                    title: data.error || 'บันทึกไม่สำเร็จ'
                });
            }
        } catch (error) {
            console.error('Save settings error:', error);
            Toast.fire({
                icon: 'error',
                title: 'Network Error'
            });
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
</script>

<script>
    // --- PERMISSIONS SYSTEM ---
    const commandsList = ['embed', 'send', 'event_create', 'join', 'leave'];

    async function loadPermissions() {
        const container = document.getElementById('permissions-container');
        if (!container) return; // Not on permissions tab or page

        try {
            // We use the proxy which will forward to bot API
            const response = await fetch('../includes/bot_api_proxy.php?action=get_permissions');
            const result = await response.json();

            let perms = {};
            if (result.success) {
                perms = result.data.permissions || {};
            }

            renderPermissionsUI(perms);

        } catch (error) {
            console.error('Error loading permissions:', error);
            container.innerHTML = '<div style="text-align:center; color:#f04747;">Failed to load permissions. Check bot connection.</div>';
        }
    }

    function renderPermissionsUI(existingPerms) {
        const container = document.getElementById('permissions-container');
        container.innerHTML = '';

        commandsList.forEach(cmd => {
            const cmdPerms = existingPerms[cmd] || { roles: [], allow_all: false };
            const selectedRoles = new Set(cmdPerms.roles || []);

            let roleOptions = '';
            // Use autocompleteRoles from parent script block if available
            const roles = (typeof autocompleteRoles !== 'undefined') ? autocompleteRoles : [];
            roles.forEach(role => {
                const isSelected = selectedRoles.has(role.id);
                roleOptions += `
                <div class="role-option ${isSelected ? 'selected' : ''}" 
                     data-role-id="${role.id}"
                     onclick="toggleRoleSelection(this)"
                     >
                    <div class="checkmark">
                        ${isSelected ? '<i class="fas fa-check" style="font-size: 11px; color: white;"></i>' : ''}
                    </div>
                    <span style="color: ${role.color && role.color !== '#000000' ? role.color : '#fff'}; font-weight: 500;">
                        ${role.name}
                    </span>
                </div>
            `;
            });

            const selectedCount = (cmdPerms.roles || []).length;
            const triggerText = selectedCount > 0
                ? `Selected Roles (${selectedCount} Granted)`
                : 'Select Allowed Roles (None Selected)';

            const html = `
            <div class="permission-card" data-command="${cmd}" style="background: rgba(30, 33, 41, 0.5); padding: 20px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: #5865f2; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-size: 0.9em;">/${cmd.replace('_', ' ')}</span>
                    </div>
                     <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9em; user-select: none;">
                        <span style="color: #faa61a;">⚠️ Allow Everyone</span>
                        <input type="checkbox" class="allow-all-check" ${cmdPerms.allow_all ? 'checked' : ''} style="accent-color: #faa61a;">
                    </label>
                </div>
                
                <h5 style="margin: 0 0 10px 0; color: #b5bac1; font-size: 0.85em;">ALLOWED ROLES (Select to Grant Access)</h5>
                
                <!-- Collapsible Role Selector -->
                <div class="role-selector-wrapper">
                    <div class="role-selector-trigger" onclick="toggleRoleDropdown(this)">
                        <span class="trigger-text" style="font-weight: 500; color: #ddd;">${triggerText}</span>
                        <i class="fas fa-chevron-down arrow-icon"></i>
                    </div>
                    
                    <div class="role-selector-content" style="display: none;">
                         <div class="role-selector-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
                             ${roleOptions}
                         </div>
                    </div>
                </div>
            </div>
        `;
            container.innerHTML += html;
        });
    }

    function toggleRoleDropdown(triggerEl) {
        const wrapper = triggerEl.closest('.role-selector-wrapper');
        const content = wrapper.querySelector('.role-selector-content');

        // Check current display state
        const isClosed = content.style.display === 'none';

        if (isClosed) {
            content.style.display = 'block';
            wrapper.classList.add('open');
        } else {
            content.style.display = 'none';
            wrapper.classList.remove('open');
        }
    }

    function toggleRoleSelection(el) {
        el.classList.toggle('selected');
        const checkmark = el.querySelector('.checkmark');
        const isSelected = el.classList.contains('selected');

        // Style handling moved to CSS classes (.role-option and .role-option.selected)
        checkmark.innerHTML = isSelected ? '<i class="fas fa-check" style="font-size: 11px; color: white;"></i>' : '';

        // Update Summary Text
        updateRoleSummary(el);
    }

    function updateRoleSummary(el) {
        const wrapper = el.closest('.role-selector-wrapper');
        const triggerText = wrapper.querySelector('.trigger-text');
        const selectedCount = wrapper.querySelectorAll('.role-option.selected').length;

        if (selectedCount > 0) {
            triggerText.innerText = `Selected Roles (${selectedCount} Granted)`;
            triggerText.style.color = '#fff';
        } else {
            triggerText.innerText = 'Select Allowed Roles (None Selected)';
            triggerText.style.color = '#ddd';
        }
    }

    async function savePermissions() {
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        const statusEl = document.getElementById('permissionsStatus');

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;
        statusEl.textContent = '';

        const perms = {};
        document.querySelectorAll('.permission-card').forEach(card => {
            const cmd = card.dataset.command;
            const allowAll = card.querySelector('.allow-all-check').checked;
            const selectedRoleEls = card.querySelectorAll('.role-option.selected');
            const roles = Array.from(selectedRoleEls).map(el => el.dataset.roleId);

            perms[cmd] = {
                allow_all: allowAll,
                roles: roles
            };
        });

        try {
            const response = await fetch('../includes/bot_api_proxy.php?action=save_permissions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(perms)
            });
            const result = await response.json();

            if (result.success) {
                statusEl.textContent = '✓ Permissions Saved!';
                statusEl.style.color = '#43b581';
            } else {
                statusEl.textContent = '✗ Error: ' + (result.error || 'Unknown');
                statusEl.style.color = '#f04747';
            }
        } catch (e) {
            console.error(e);
            statusEl.textContent = '✗ Network Error';
            statusEl.style.color = '#f04747';
        }

        btn.disabled = false;
        btn.innerHTML = originalText;

        setTimeout(() => { if (statusEl.textContent.includes('Saved')) statusEl.textContent = ''; }, 3000);
    }

    // Load permissions when on the tab or on page load (simple approach)
    document.addEventListener('DOMContentLoaded', loadPermissions);

    // ================== SAVED MESSAGES (MESSAGE BUILDER) ==================

    let currentEditingMessageId = null;
    let allSavedMessages = [];
    let savedMessagesPage = 1;
    const MESSAGES_PER_PAGE = 5;

    async function loadSavedMessages() {
        const container = document.getElementById('savedMessagesList');
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: #72767d;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

        try {
            const response = await fetch('../includes/bot_api_proxy.php?action=list_messages&t=' + new Date().getTime());
            const result = await response.json();

            if (!result.success || !result.data || result.data.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 30px; color: #72767d;">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        No saved messages yet. Send an embed to save it here.
                    </div>`;
                return;
            }

            allSavedMessages = result.data;
            savedMessagesPage = 1;
            renderSavedMessagesPage();
        } catch (e) {
            console.error('Error loading saved messages:', e);
            container.innerHTML = '<div style="color: #f04747; text-align: center; padding: 20px;">Error loading messages</div>';
        }
    }

    function renderSavedMessagesPage() {
        const container = document.getElementById('savedMessagesList');
        const totalPages = Math.ceil(allSavedMessages.length / MESSAGES_PER_PAGE);
        const startIdx = (savedMessagesPage - 1) * MESSAGES_PER_PAGE;
        const endIdx = startIdx + MESSAGES_PER_PAGE;
        const pageMessages = allSavedMessages.slice(startIdx, endIdx);

        let html = pageMessages.map(msg => `
            <div class="saved-message-item" style="
                background: rgba(0,0,0,0.2);
                border-radius: 8px;
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-left: 3px solid ${msg.status === 'published' ? '#43b581' : '#faa61a'};
            ">
                <div>
                    <div style="font-weight: 600; color: #fff; display: flex; align-items: center; gap: 8px;">
                        ${msg.is_pinned == 1 ? '<i class="fas fa-thumbtack" style="color: #faa61a; font-size: 0.8em;" title="Pinned"></i>' : ''}
                        ${escapeHtml(msg.name)}
                    </div>
                    <div style="font-size: 0.85em; color: #72767d;">
                        in <span style="color: #5865f2;">#${escapeHtml(msg.channel_name || 'unknown')}</span>
                        <span style="
                            background: ${msg.status === 'published' ? 'rgba(67,181,129,0.2)' : 'rgba(250,166,26,0.2)'};
                            color: ${msg.status === 'published' ? '#43b581' : '#faa61a'};
                            padding: 2px 8px;
                            border-radius: 4px;
                            font-size: 0.75em;
                            margin-left: 8px;
                            text-transform: uppercase;
                        ">${msg.status}</span>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn secondary sm" onclick="togglePin(${msg.id})" title="${msg.is_pinned == 1 ? 'Unpin' : 'Pin'}">
                        <i class="fas fa-thumbtack" style="${msg.is_pinned == 1 ? 'color: #faa61a;' : 'opacity: 0.5;'}"></i>
                    </button>
                    <button class="btn secondary sm" onclick="editSavedMessage(${msg.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn secondary sm" onclick="duplicateSavedMessage(${msg.id})" title="Duplicate">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button class="btn danger sm" onclick="deleteSavedMessage(${msg.id}, '${msg.message_id || ''}', '${msg.channel_id}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

        // Pagination controls
        if (totalPages > 1) {
            html += `
                <div style="
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 8px;
                    margin-top: 20px;
                    padding: 15px;
                    background: rgba(0,0,0,0.15);
                    border-radius: 10px;
                ">
                    <button onclick="goToSavedMessagesPage(1)" 
                        ${savedMessagesPage === 1 ? 'disabled' : ''} 
                        style="
                            background: ${savedMessagesPage === 1 ? 'rgba(255,255,255,0.05)' : 'rgba(88, 101, 242, 0.2)'};
                            border: 1px solid ${savedMessagesPage === 1 ? 'rgba(255,255,255,0.1)' : 'rgba(88, 101, 242, 0.4)'};
                            color: ${savedMessagesPage === 1 ? '#72767d' : '#5865f2'};
                            padding: 8px 12px;
                            border-radius: 6px;
                            cursor: ${savedMessagesPage === 1 ? 'not-allowed' : 'pointer'};
                            font-size: 0.85rem;
                            transition: all 0.2s ease;
                        ">
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    <button onclick="goToSavedMessagesPage(${savedMessagesPage - 1})" 
                        ${savedMessagesPage === 1 ? 'disabled' : ''} 
                        style="
                            background: ${savedMessagesPage === 1 ? 'rgba(255,255,255,0.05)' : 'rgba(88, 101, 242, 0.2)'};
                            border: 1px solid ${savedMessagesPage === 1 ? 'rgba(255,255,255,0.1)' : 'rgba(88, 101, 242, 0.4)'};
                            color: ${savedMessagesPage === 1 ? '#72767d' : '#5865f2'};
                            padding: 8px 12px;
                            border-radius: 6px;
                            cursor: ${savedMessagesPage === 1 ? 'not-allowed' : 'pointer'};
                            font-size: 0.85rem;
                            transition: all 0.2s ease;
                        ">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <div style="display: flex; gap: 4px;">
                        ${generatePageButtons(savedMessagesPage, totalPages)}
                    </div>
                    
                    <button onclick="goToSavedMessagesPage(${savedMessagesPage + 1})" 
                        ${savedMessagesPage === totalPages ? 'disabled' : ''} 
                        style="
                            background: ${savedMessagesPage === totalPages ? 'rgba(255,255,255,0.05)' : 'rgba(88, 101, 242, 0.2)'};
                            border: 1px solid ${savedMessagesPage === totalPages ? 'rgba(255,255,255,0.1)' : 'rgba(88, 101, 242, 0.4)'};
                            color: ${savedMessagesPage === totalPages ? '#72767d' : '#5865f2'};
                            padding: 8px 12px;
                            border-radius: 6px;
                            cursor: ${savedMessagesPage === totalPages ? 'not-allowed' : 'pointer'};
                            font-size: 0.85rem;
                            transition: all 0.2s ease;
                        ">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button onclick="goToSavedMessagesPage(${totalPages})" 
                        ${savedMessagesPage === totalPages ? 'disabled' : ''} 
                        style="
                            background: ${savedMessagesPage === totalPages ? 'rgba(255,255,255,0.05)' : 'rgba(88, 101, 242, 0.2)'};
                            border: 1px solid ${savedMessagesPage === totalPages ? 'rgba(255,255,255,0.1)' : 'rgba(88, 101, 242, 0.4)'};
                            color: ${savedMessagesPage === totalPages ? '#72767d' : '#5865f2'};
                            padding: 8px 12px;
                            border-radius: 6px;
                            cursor: ${savedMessagesPage === totalPages ? 'not-allowed' : 'pointer'};
                            font-size: 0.85rem;
                            transition: all 0.2s ease;
                        ">
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </div>
                <div style="text-align: center; color: #72767d; font-size: 0.8rem; margin-top: 8px;">
                    Showing ${startIdx + 1}-${Math.min(endIdx, allSavedMessages.length)} of ${allSavedMessages.length} messages
                </div>
            `;
        }

        container.innerHTML = html;
    }

    function generatePageButtons(currentPage, totalPages) {
        let buttons = '';
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === currentPage;
            buttons += `
                <button onclick="goToSavedMessagesPage(${i})" style="
                    background: ${isActive ? 'linear-gradient(135deg, #5865f2, #7289da)' : 'rgba(255,255,255,0.05)'};
                    border: 1px solid ${isActive ? '#5865f2' : 'rgba(255,255,255,0.1)'};
                    color: ${isActive ? '#fff' : '#b5bac1'};
                    padding: 8px 14px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 0.85rem;
                    font-weight: ${isActive ? '600' : '400'};
                    transition: all 0.2s ease;
                    min-width: 36px;
                    ${isActive ? 'box-shadow: 0 4px 12px rgba(88, 101, 242, 0.3);' : ''}
                ">${i}</button>
            `;
        }
        return buttons;
    }

    function goToSavedMessagesPage(page) {
        const totalPages = Math.ceil(allSavedMessages.length / MESSAGES_PER_PAGE);
        if (page < 1 || page > totalPages) return;
        savedMessagesPage = page;
        renderSavedMessagesPage();
    }

    async function editSavedMessage(id) {
        try {
            const response = await fetch(`../includes/bot_api_proxy.php?action=get_message&id=${id}`);
            const result = await response.json();

            if (!result.success || !result.data) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load message data.', background: '#36393f', color: '#fff' });
                return;
            }

            const msg = result.data;
            currentEditingMessageId = id;

            // Set hidden inputs for Edit Mode
            const discordIdInput = document.getElementById('input_discord_message_id');
            const dbIdInput = document.getElementById('input_saved_db_id');
            if (discordIdInput) discordIdInput.value = msg.message_id || '';
            if (dbIdInput) dbIdInput.value = id;

            // Change Submit Button to "Update Message"
            const submitBtn = document.getElementById('btn-submit-embed');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-sync-alt" style="margin-right:8px;"></i> Update Message';
                submitBtn.style.background = '#faa61a'; // Visual cue
            }

            // Populate the embed builder form with this message data
            // Use class selectors that match the actual form fields
            // Message content goes into the contenteditable div
            const msgContentEditor = document.getElementById('messageContentEditor');
            if (msgContentEditor) {
                // Use renderMentionsInEditor to properly render Discord mentions
                renderMentionsInEditor(msgContentEditor, msg.content || '');
            }

            if (msg.embed_data) {
                const embed = msg.embed_data;

                // Show the embed wrapper if hidden
                const embedWrapper = document.getElementById('embedWrapper');
                const btnAddEmbed = document.getElementById('btnAddEmbed');
                if (embedWrapper) embedWrapper.style.display = 'flex';
                if (btnAddEmbed) btnAddEmbed.style.display = 'none';

                // Author
                const authorField = document.querySelector('.author-name');
                if (authorField) authorField.value = embed.author?.name || '';

                // Title
                const titleField = document.querySelector('.embed-title');
                if (titleField) titleField.value = embed.title || '';

                // Description
                const descField = document.querySelector('.embed-desc');
                if (descField) descField.value = embed.description || '';

                // Color
                const colorField = document.querySelector('[name="embed_color"]');
                if (colorField) {
                    colorField.value = embed.color || '#5865f2';
                    updateEmbedColor(embed.color || '#5865f2');
                }

                // Thumbnail URL (hidden input)
                const thumbnailInput = document.getElementById('input_thumbnail_url');
                if (thumbnailInput && embed.thumbnail) {
                    thumbnailInput.value = embed.thumbnail;
                    const preview = document.getElementById('preview_thumbnail_url');
                    if (preview) {
                        preview.style.backgroundImage = `url(${embed.thumbnail})`;
                        preview.classList.add('has-image');
                    }
                }

                // Image URL (hidden input)
                const imageInput = document.getElementById('input_image_url');
                if (imageInput && embed.image) {
                    imageInput.value = embed.image;
                    const preview = document.getElementById('preview_image_url');
                    if (preview) {
                        preview.style.backgroundImage = `url(${embed.image})`;
                        preview.classList.add('has-image');
                    }
                }

                // Footer
                const footerField = document.querySelector('.footer-text');
                if (footerField) footerField.value = embed.footer || '';
            }

            // Set channel (hidden input for form submission)
            const channelInput = document.getElementById('embedChannelInput');
            if (channelInput) {
                channelInput.value = msg.channel_id;
            }

            // Hide channel selector and delivery method when editing
            // The channel is already set and shouldn't be changed when updating
            const channelSelectorWrapper = document.getElementById('channelSelectorWrapper');
            const deliveryMethodSection = document.querySelector('.delivery-method-grid')?.parentElement;
            if (channelSelectorWrapper) {
                channelSelectorWrapper.style.display = 'none';
            }
            if (deliveryMethodSection) {
                deliveryMethodSection.style.display = 'none';
            }

            // Add an info message showing which channel is being edited
            let editInfoBanner = document.getElementById('edit-info-banner');
            if (!editInfoBanner) {
                editInfoBanner = document.createElement('div');
                editInfoBanner.id = 'edit-info-banner';
                const submitBtn = document.getElementById('btn-submit-embed');
                if (submitBtn) {
                    submitBtn.parentElement.insertBefore(editInfoBanner, submitBtn);
                }
            }
            editInfoBanner.style.cssText = `
                background: linear-gradient(90deg, rgba(88, 101, 242, 0.2), rgba(88, 101, 242, 0.05));
                border: 1px solid rgba(88, 101, 242, 0.4);
                border-radius: 8px;
                padding: 12px 16px;
                margin-bottom: 12px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            `;
            editInfoBanner.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-edit" style="color: #5865f2; font-size: 1rem;"></i>
                    <span style="color: #fff; font-weight: 500;">
                        ${escapeHtml(msg.name)} 
                        <span style="color: #72767d; font-weight: 400;">in</span> 
                        <span style="color: #5865f2;">#${escapeHtml(msg.channel_name || msg.channel_id)}</span>
                    </span>
                </div>
                <button type="button" onclick="cancelEditMode()" style="
                    background: transparent;
                    border: 1px solid rgba(240, 71, 71, 0.4);
                    color: #f04747;
                    padding: 6px 12px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 0.8rem;
                    font-weight: 500;
                    transition: all 0.2s ease;
                " onmouseover="this.style.background='rgba(240, 71, 71, 0.15)';"
                   onmouseout="this.style.background='transparent';">
                    <i class="fas fa-times" style="margin-right: 4px;"></i>Cancel
                </button>
            `;
            editInfoBanner.style.display = 'flex';

            // Scroll to embed builder
            document.querySelector('.editor-area').scrollIntoView({ behavior: 'smooth' });

            Toast.fire({
                icon: 'info',
                title: `Editing: ${msg.name}`,
                timer: 2000
            });
        } catch (e) {
            console.error('Error editing message:', e);
        }
    }

    function cancelEditMode() {
        // Reset editing state
        currentEditingMessageId = null;

        // Clear hidden edit inputs
        const discordIdInput = document.getElementById('input_discord_message_id');
        const dbIdInput = document.getElementById('input_saved_db_id');
        if (discordIdInput) discordIdInput.value = '';
        if (dbIdInput) dbIdInput.value = '';

        // Reset submit button
        const submitBtn = document.getElementById('btn-submit-embed');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right:8px;"></i> Send';
            submitBtn.style.background = '';
        }

        // Show channel selector and delivery method again
        const channelSelectorWrapper = document.getElementById('channelSelectorWrapper');
        const deliveryMethodSection = document.querySelector('.delivery-method-grid')?.parentElement;
        if (channelSelectorWrapper) {
            channelSelectorWrapper.style.display = 'block';
        }
        if (deliveryMethodSection) {
            deliveryMethodSection.style.display = 'block';
        }

        // Hide edit info banner
        const editInfoBanner = document.getElementById('edit-info-banner');
        if (editInfoBanner) {
            editInfoBanner.style.display = 'none';
        }

        // Clear the form
        const msgContentEditor = document.getElementById('messageContentEditor');
        if (msgContentEditor) {
            msgContentEditor.innerHTML = '';
            syncMessageContent(msgContentEditor);
        }

        // Hide embed wrapper
        const embedWrapper = document.getElementById('embedWrapper');
        const btnAddEmbed = document.getElementById('btnAddEmbed');
        if (embedWrapper) embedWrapper.style.display = 'none';
        if (btnAddEmbed) btnAddEmbed.style.display = 'flex';

        // Clear all form fields
        document.querySelectorAll('.transparent-input').forEach(input => {
            if (input.tagName === 'TEXTAREA' || input.tagName === 'INPUT') {
                input.value = '';
            }
        });

        Toast.fire({
            icon: 'success',
            title: 'Edit mode cancelled',
            timer: 1500
        });
    }

    async function duplicateSavedMessage(id) {
        try {
            const response = await fetch(`../includes/bot_api_proxy.php?action=get_message&id=${id}`);
            const result = await response.json();

            if (!result.success || !result.data) return;

            const msg = result.data;
            msg.name = msg.name + ' (Copy)';
            msg.message_id = null;
            msg.status = 'draft';

            const saveResponse = await fetch('../includes/bot_api_proxy.php?action=save_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(msg)
            });

            const saveResult = await saveResponse.json();
            if (saveResult.success) {
                loadSavedMessages();
                Toast.fire({ icon: 'success', title: 'Duplicated!', timer: 1500 });
            }
        } catch (e) {
            console.error('Error duplicating message:', e);
        }
    }

    async function deleteSavedMessage(id, messageId, channelId) {
        let confirmMsg = 'Are you sure you want to delete this message?\n\nThis action cannot be undone.';
        if (messageId) {
            confirmMsg += '\n\n(Note: This only deletes from the database. The message on Discord will remain unless manually deleted.)';
        }

        showConfirm('Delete Message', confirmMsg, async () => {
            try {
                // Defaulting delete_discord to false for now as UI checkbox is gone
                // OR we can make a 3rd button? No, keep it simple.
                const deleteFromDiscord = false;

                const response = await fetch(`../includes/bot_api_proxy.php?action=delete_message&id=${id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ delete_from_discord: deleteFromDiscord, message_id: messageId, channel_id: channelId })
                });

                const result = await response.json();
                if (result.success) {
                    loadSavedMessages();
                    Toast.fire({ icon: 'success', title: 'Deleted!', timer: 1500 });
                } else {
                    showAlert('Error', 'Failed to delete: ' + (result.error || 'Unknown error'));
                }
            } catch (e) {
                console.error(e);
                showAlert('Error', 'Network error');
            }
        }, 'Delete', 'danger');
    }



    async function togglePin(id) {
        try {
            const response = await fetch(`../includes/bot_api_proxy.php?action=toggle_pin&id=${id}`);
            const result = await response.json();

            if (result.success) {
                loadSavedMessages();
                Toast.fire({ icon: 'success', title: 'Pin updated!', timer: 1500 });
            } else {
                Toast.fire({ icon: 'error', title: 'Failed to update pin' });
            }
        } catch (e) {
            console.error('Error toggling pin:', e);
            Toast.fire({ icon: 'error', title: 'Network error' });
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Load saved messages on page load (if on embed tab)
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(loadSavedMessages, 500);
    });

</script>



<!-- CUSTOM ALERT/CONFIRM MODAL -->
<div id="customModalOverlay" class="custom-modal-overlay">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h3 id="customModalTitle" class="custom-modal-title">Title</h3>
        </div>
        <div id="customModalBody" class="custom-modal-body">
            Message
        </div>
        <div id="customModalFooter" class="custom-modal-footer">
            <button id="customModalCancel" class="c-btn c-btn-cancel">Cancel</button>
            <button id="customModalConfirm" class="c-btn c-btn-confirm">OK</button>
        </div>
    </div>
</div>

<script>
    // === CUSTOM MODAL LOGIC ===
    const customModalOverlay = document.getElementById('customModalOverlay');
    const customModalTitle = document.getElementById('customModalTitle');
    const customModalBody = document.getElementById('customModalBody');
    const customModalCancel = document.getElementById('customModalCancel');
    const customModalConfirm = document.getElementById('customModalConfirm');

    let currentConfirmCallback = null;

    function showModal(title, message, isConfirm = false, onConfirm = null, confirmText = 'OK', type = 'normal') {
        customModalTitle.textContent = title;
        customModalBody.innerHTML = message;

        if (customModalCancel) customModalCancel.style.display = isConfirm ? 'block' : 'none';

        if (customModalConfirm) {
            customModalConfirm.textContent = confirmText;
            if (type === 'danger') {
                customModalConfirm.className = 'c-btn c-btn-danger';
            } else {
                customModalConfirm.className = 'c-btn c-btn-confirm';
            }
        }

        currentConfirmCallback = onConfirm;

        if (customModalOverlay) {
            customModalOverlay.style.display = 'flex';
            setTimeout(() => customModalOverlay.classList.add('active'), 10);
        }
    }

    function closeModal() {
        if (customModalOverlay) {
            customModalOverlay.classList.remove('active');
            setTimeout(() => {
                customModalOverlay.style.display = 'none';
                currentConfirmCallback = null;
            }, 300);
        }
    }

    if (customModalCancel) customModalCancel.onclick = closeModal;
    if (customModalConfirm) customModalConfirm.onclick = () => {
        if (currentConfirmCallback) currentConfirmCallback();
        closeModal();
    };

    function showAlert(title, message, callback) {
        showModal(title, message, false, callback, 'OK', 'normal');
    }

    function showConfirm(title, message, callback, confirmText = 'Confirm', type = 'normal') {
        showModal(title, message, true, callback, confirmText, type);
    }

    /**
     * Convert HTML from contenteditable (with mention badges) to Discord format
     * HTML badges: <span class="discord-mention" data-mention-id="123" data-mention-type="user|role|channel">@Name</span>
     * Discord format: <@123> for users, <@&123> for roles, <#123> for channels
     */
    function convertHtmlToDiscordFormat(html) {
        if (!html) return '';

        // Create a temporary container to parse HTML
        const temp = document.createElement('div');
        temp.innerHTML = html;

        // Find all mention spans with data-mention-id (this is how mentions are actually stored)
        const mentions = temp.querySelectorAll('.discord-mention, [data-mention-id]');
        mentions.forEach(span => {
            const id = span.dataset.mentionId;
            const type = span.dataset.mentionType || 'user';
            let discordFormat = '';

            if (!id) {
                // No ID found, just use text content
                span.replaceWith(span.textContent);
                return;
            }

            if (type === 'role') {
                discordFormat = `<@&${id}>`;
            } else if (type === 'channel') {
                discordFormat = `<#${id}>`;
            } else {
                // Default to user mention
                discordFormat = `<@${id}>`;
            }

            // Replace the span with Discord format text
            span.replaceWith(discordFormat);
        });

        // Get text content (removes all other HTML formatting)
        // But preserve line breaks
        let result = temp.innerHTML;
        result = result.replace(/<br\s*\/?>/gi, '\n');
        result = result.replace(/<\/div>/gi, '\n');
        result = result.replace(/<\/p>/gi, '\n');
        result = result.replace(/<[^>]+>/g, ''); // Remove remaining HTML tags
        result = result.replace(/&nbsp;/g, ' ');
        result = result.replace(/&lt;/g, '<');
        result = result.replace(/&gt;/g, '>');
        result = result.replace(/&amp;/g, '&');
        result = result.trim();

        return result;
    }

    /* SERVER WELCOME SETTINGS LOGIC */

    // Toggle Embed Options
    function toggleServerWelcomeEmbedOptions() {
        const checkbox = document.getElementById('serverWelcomeUseEmbed');
        const container = document.getElementById('serverWelcomeEmbedOptions');
        const slider = checkbox.nextElementSibling;

        if (checkbox.checked) {
            container.style.display = 'block';
            slider.style.background = '#43b581';
        } else {
            container.style.display = 'none';
            slider.style.background = '#72767d';
        }
    }

    // Toggle Enable Switch Visuals
    function updateServerWelcomeToggleStyle() {
        const checkbox = document.getElementById('serverWelcomeEnabled');
        const slider = document.getElementById('serverWelcomeSlider');
        slider.style.background = checkbox.checked ? '#43b581' : '#72767d';
    }

    // Sync Contenteditable Message
    function syncServerWelcomeMessage(editor) {
        document.getElementById('serverWelcomeMessageHidden').value = editor.innerHTML;
    }

    // Banner Image Logic
    function removeServerWelcomeBanner() {
        document.getElementById('input_server_welcome_banner').value = '';
        document.getElementById('preview_server_welcome_banner').innerHTML = '<i class="fas fa-image" style="font-size: 2rem; color: #72767d;"></i><span style="display: block; margin-top: 10px; color: #72767d;">Click to Upload Image/GIF</span>';
        document.getElementById('preview_server_welcome_banner').style.backgroundImage = 'none';
        document.getElementById('serverWelcomeBannerControls').style.display = 'none';
    }

    // LOAD SETTINGS
    async function loadServerWelcomeSettings() {
        console.log('Currently executing: loadServerWelcomeSettings');
        try {
            const url = '../includes/bot_api_proxy.php?action=get_server_welcome&t=' + new Date().getTime();
            console.log('Fetching API:', url);

            const response = await fetch(url);
            console.log('API Response Status:', response.status);

            const result = await response.json();
            console.log('API Result Data:', result);

            if (!result || !result.data) {
                console.warn('API returned no data or success false');
                return;
            }

            // Handle double-wrapped response: {success, data: {success, data: {...}}}
            let data = result.data;
            if (data && data.data) {
                data = data.data; // Unwrap second layer
                console.log('Unwrapped data:', data);
            }

            // 1. Enable Toggle
            const enabledCb = document.getElementById('serverWelcomeEnabled');
            enabledCb.checked = data.enabled == 1 || data.enabled == 'true' || data.enabled === true;
            updateServerWelcomeToggleStyle();

            // 2. Channel
            if (data.channel_id) {
                document.getElementById('serverWelcomeChannelId').value = data.channel_id;
                // Try to find name in dropdown list
                const item = document.querySelector(`#serverWelcomeChannelList .feed-dropdown-item[data-id="${data.channel_id}"]`);
                if (item) {
                    const name = item.dataset.name;
                    document.getElementById('serverWelcomeChannelText').innerHTML = `<i class="fas fa-hashtag" style="color: #5865f2;"></i> ${name}`;
                } else {
                    document.getElementById('serverWelcomeChannelText').innerText = 'Unknown Channel';
                }
            }

            // 3. Message Content (Styled)
            if (data.message_content) {
                const editor = document.getElementById('serverWelcomeMessageEditor');
                // Use existing render function if available, else raw HTML
                if (typeof renderMentionsInEditor === 'function') {
                    renderMentionsInEditor(editor, data.message_content);
                } else {
                    editor.innerHTML = data.message_content;
                }
                document.getElementById('serverWelcomeMessageHidden').value = data.message_content;
            }

            // 4. Banner Image
            if (data.banner_image) {
                document.getElementById('input_server_welcome_banner').value = data.banner_image;
                const preview = document.getElementById('preview_server_welcome_banner');
                preview.innerHTML = '';
                preview.style.backgroundImage = `url('${data.banner_image}')`;
                preview.style.backgroundSize = 'cover';
                preview.style.backgroundPosition = 'center';
                document.getElementById('serverWelcomeBannerControls').style.display = 'block';
            }

            // 5. Embed Options
            const useEmbedCb = document.getElementById('serverWelcomeUseEmbed');
            useEmbedCb.checked = data.use_embed == 1 || data.use_embed == 'true';
            toggleServerWelcomeEmbedOptions();

            if (data.embed_data) {
                const embed = typeof data.embed_data === 'string' ? JSON.parse(data.embed_data) : data.embed_data;
                document.getElementById('serverWelcomeEmbedTitle').value = embed.title || '';
                document.getElementById('serverWelcomeEmbedDescription').value = embed.description || '';
                document.getElementById('serverWelcomeImageUrl').value = embed.image || '';
                document.getElementById('serverWelcomeEmbedColor').value = embed.color || '#5865f2';
            }

        } catch (e) {
            console.error('Error loading server welcome settings:', e);
        }
    }

    // SAVE SETTINGS
    async function saveServerWelcomeSettings() {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        const statusEl = document.getElementById('serverWelcomeStatus');
        statusEl.textContent = '';

        try {
            // Collect Data
            const payload = {
                enabled: document.getElementById('serverWelcomeEnabled').checked,
                channel_id: document.getElementById('serverWelcomeChannelId').value,
                message_content: convertHtmlToDiscordFormat(document.getElementById('serverWelcomeMessageHidden').value || document.getElementById('serverWelcomeMessageEditor').innerHTML),
                banner_image: document.getElementById('input_server_welcome_banner').value,
                use_embed: document.getElementById('serverWelcomeUseEmbed').checked,
                embed_data: {
                    title: document.getElementById('serverWelcomeEmbedTitle').value,
                    description: document.getElementById('serverWelcomeEmbedDescription').value,
                    image: document.getElementById('serverWelcomeImageUrl').value,
                    color: document.getElementById('serverWelcomeEmbedColor').value
                }
            };

            const response = await fetch('../includes/bot_api_proxy.php?action=set_server_welcome', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.success) {
                statusEl.textContent = '✓ Saved!';
                statusEl.style.color = '#43b581';
                Swal.fire({
                    icon: 'success',
                    title: 'Saved',
                    text: 'Server welcome settings updated successfully.',
                    timer: 1500,
                    showConfirmButton: false,
                    customClass: { popup: 'modern-alert' }
                });
            } else {
                throw new Error(result.error || 'Unknown error');
            }

        } catch (e) {
            console.error(e);
            statusEl.textContent = '✗ Error';
            statusEl.style.color = '#f04747';
            Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: e.message || 'Could not save settings.',
                customClass: { popup: 'modern-alert' }
            });
        }

        btn.disabled = false;
        btn.innerHTML = originalText;
    }

    // TEST WELCOME
    async function testServerWelcome() {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
        btn.disabled = true;

        try {
            // Send current form data as test payload
            const payload = {
                channel_id: document.getElementById('serverWelcomeChannelId').value,
                message_content: convertHtmlToDiscordFormat(document.getElementById('serverWelcomeMessageHidden').value || document.getElementById('serverWelcomeMessageEditor').innerHTML),
                banner_image: document.getElementById('input_server_welcome_banner').value,
                use_embed: document.getElementById('serverWelcomeUseEmbed').checked,
                embed_data: {
                    title: document.getElementById('serverWelcomeEmbedTitle').value,
                    description: document.getElementById('serverWelcomeEmbedDescription').value,
                    image: document.getElementById('serverWelcomeImageUrl').value,
                    color: document.getElementById('serverWelcomeEmbedColor').value
                }
            };

            const response = await fetch('../includes/bot_api_proxy.php?action=test_server_welcome', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Test Sent',
                    text: 'Check your discord channel!',
                    timer: 2000,
                    showConfirmButton: false,
                    customClass: { popup: 'modern-alert' }
                });
            } else {
                throw new Error(result.error);
            }

        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Test Failed',
                text: e.message || 'Could not send test message.',
                customClass: { popup: 'modern-alert' }
            });
        }

        btn.disabled = false;
        btn.innerHTML = originalText;
    }

    // LOAD ON START
    document.addEventListener('DOMContentLoaded', () => {
        loadServerWelcomeSettings();

        // Attach mention handlers to the new editor container
        const welcomeContainer = document.getElementById('serverWelcomeSettings');
        if (welcomeContainer) {
            const selector = '.msg-content'; // Match the new editor

            welcomeContainer.addEventListener('input', (e) => {
                if (e.target.matches(selector)) {
                    if (typeof handleMentionInput === 'function') handleMentionInput(e);
                }
            });

            welcomeContainer.addEventListener('keydown', (e) => {
                if (e.target.matches(selector)) {
                    if (typeof handleAutocompleteNavigation === 'function') handleAutocompleteNavigation(e);
                }
            });

            welcomeContainer.addEventListener('focusout', (e) => {
                if (e.target.matches(selector)) {
                    if (typeof handleAutocompleteBlur === 'function') handleAutocompleteBlur(e);
                }
            });
        }
        // Initial Load
        loadServerWelcomeSettings();
        if(typeof loadServerLeaveSettings === 'function') loadServerLeaveSettings();
        if(typeof loadVoiceLogsSettings === 'function') loadVoiceLogsSettings();
    });

</script>

<!-- INLINE CSS TO FORCE OVERRIDE (Bypassing Cache) -->
<style>
    .custom-trigger-premium {
        /* Slights lighter gradient for visibility */
        background: linear-gradient(135deg, rgba(40, 40, 45, 1), rgba(30, 30, 35, 1)) !important;
        /* High Opacity Border */
        border: 1px solid rgba(114, 137, 218, 0.8) !important;
        border-radius: 10px !important;
        padding: 12px 16px !important;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #fff !important;
        /* Brighter text */
        transition: all 0.3s ease;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5) !important;
    }

    .custom-trigger-premium:hover {
        border-color: #7289da !important;
        /* Solid Blurple */
        box-shadow: 0 4px 20px rgba(114, 137, 218, 0.25) !important;
        transform: translateY(-1px);
    }

    .custom-trigger-premium i {
        color: #7289da;
        font-size: 0.9rem;
        /* Slightly larger icon */
        transition: transform 0.3s ease;
    }

    .custom-select-container.open .custom-trigger-premium {
        border-color: #7289da !important;
        border-bottom-left-radius: 10px !important;
        border-bottom-right-radius: 10px !important;
        border-top-left-radius: 0 !important;
        border-top-right-radius: 0 !important;
        background: rgba(30, 30, 35, 1) !important;
    }

    .custom-select-container.open .custom-trigger-premium i {
        transform: rotate(180deg);
    }
</style>

<script>
    // ====== TICKET SYSTEM ======
    let ticketCategories = [];

    async function loadTicketPanels() {
        const container = document.getElementById('ticketPanelsList');
        if (!container) return;
        try {
            const resp = await fetch('ticket_actions.php?action=list_panels');
            const data = await resp.json();
            if (!data.success || !data.panels.length) {
                container.innerHTML = '<div style="text-align:center; color:#72767d; padding:40px;"><i class="fas fa-ticket-alt" style="font-size:2rem; margin-bottom:10px; display:block; opacity:0.3;"></i>No ticket panels yet. Create one to get started.</div>';
                return;
            }
            container.innerHTML = data.panels.map(p => `
            <div style="background:rgba(0,0,0,0.2); border-radius:12px; padding:16px; margin-bottom:12px; border:1px solid rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:space-between;">
                <div style="flex:1;">
                    <div style="font-weight:600; color:#fff; font-size:1.05rem; margin-bottom:4px;">
                        <i class="fas fa-ticket-alt" style="color:#5865F2; margin-right:6px;"></i>${escHtml(p.title)}
                    </div>
                    <div style="color:#b5bac1; font-size:0.85rem;">
                        ${p.message_id ? '<span style="color:#43b581;"><i class="fas fa-check-circle"></i> Deployed</span>' : '<span style="color:#faa61a;"><i class="fas fa-clock"></i> Not deployed</span>'}
                        &nbsp;·&nbsp; <i class="fas fa-envelope-open"></i> ${p.open_tickets || 0} open
                        &nbsp;·&nbsp; <i class="fas fa-archive"></i> ${p.total_tickets || 0} total
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn primary sm" onclick="deployPanel(${p.id})" title="Deploy to Discord">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    <button class="btn secondary sm" onclick="editPanel(${p.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn danger sm" onclick="deletePanel(${p.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
        } catch (e) {
            container.innerHTML = '<div style="color:#f04747; text-align:center; padding:20px;">Failed to load panels</div>';
        }
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    async function loadCategories() {
        try {
            const resp = await fetch('ticket_actions.php?action=get_categories');
            const data = await resp.json();
            if (data.success) {
                ticketCategories = data.categories || [];
                const sel = document.getElementById('panelCategoryId');
                if (sel) {
                    sel.innerHTML = '<option value="">-- No Category --</option>' +
                        ticketCategories.map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('');
                }
            }
        } catch (e) { console.error('Failed to load categories:', e); }
    }

    function showPanelEditor(panel) {
        document.getElementById('panelEditId').value = panel ? panel.id : '';
        document.getElementById('panelTitle').value = panel ? panel.title : '';
        document.getElementById('panelDescription').value = panel ? panel.description : '';
        document.getElementById('panelChannelId').value = panel ? panel.channel_id : '';
        document.getElementById('panelCategoryId').value = panel ? (panel.category_id || '') : '';
        document.getElementById('panelSupportRoleId').value = panel ? (panel.support_role_id || '') : '';
        document.getElementById('panelButtonText').value = panel ? panel.button_text : 'Open Ticket';
        document.getElementById('panelButtonColor').value = panel ? panel.button_color : 'green';
        document.getElementById('panelEmbedColor').value = panel ? panel.embed_color : '#5865F2';
        document.getElementById('panelMaxTickets').value = panel ? panel.max_tickets : 1;
        document.getElementById('panelWelcomeMessage').value = panel ? (panel.welcome_message || '') : '';
        document.getElementById('panelEditorTitle').innerHTML = panel
            ? '<i class="fas fa-edit"></i> Edit Panel: ' + escHtml(panel.title)
            : '<i class="fas fa-ticket-alt"></i> New Ticket Panel';

        const overlay = document.getElementById('panelEditorOverlay');
        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('active'), 10);
    }

    function closePanelEditor() {
        const overlay = document.getElementById('panelEditorOverlay');
        overlay.classList.remove('active');
        setTimeout(() => overlay.style.display = 'none', 300);
    }

    async function savePanel() {
        const id = document.getElementById('panelEditId').value;
        const fd = new FormData();
        fd.append('action', 'save_panel');
        if (id) fd.append('id', id);
        fd.append('title', document.getElementById('panelTitle').value);
        fd.append('description', document.getElementById('panelDescription').value);
        fd.append('channel_id', document.getElementById('panelChannelId').value);
        fd.append('category_id', document.getElementById('panelCategoryId').value);
        fd.append('support_role_id', document.getElementById('panelSupportRoleId').value);
        fd.append('button_text', document.getElementById('panelButtonText').value);
        fd.append('button_color', document.getElementById('panelButtonColor').value);
        fd.append('embed_color', document.getElementById('panelEmbedColor').value);
        fd.append('max_tickets', document.getElementById('panelMaxTickets').value);
        fd.append('welcome_message', document.getElementById('panelWelcomeMessage').value);

        try {
            const resp = await fetch('ticket_actions.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                closePanelEditor();
                Toast.fire({ icon: 'success', title: id ? 'Panel updated!' : 'Panel created!' });
                loadTicketPanels();
            } else {
                Toast.fire({ icon: 'error', title: data.error || 'Failed to save' });
            }
        } catch (e) {
            Toast.fire({ icon: 'error', title: 'Network error' });
        }
    }

    async function editPanel(id) {
        try {
            const resp = await fetch(`ticket_actions.php?action=get_panel&id=${id}`);
            const data = await resp.json();
            if (data.success) {
                showPanelEditor(data.panel);
            }
        } catch (e) { Toast.fire({ icon: 'error', title: 'Failed to load panel' }); }
    }

    async function deletePanel(id) {
        const result = await Swal.fire({
            title: 'Delete Panel?',
            text: 'This will remove the panel configuration. Existing tickets will not be affected.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            customClass: { popup: 'modern-alert' }
        });
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('action', 'delete_panel');
        fd.append('id', id);
        try {
            const resp = await fetch('ticket_actions.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Panel deleted' });
                loadTicketPanels();
            }
        } catch (e) { Toast.fire({ icon: 'error', title: 'Failed to delete' }); }
    }

    async function deployPanel(id) {
        const result = await Swal.fire({
            title: 'Deploy Panel?',
            text: 'This will send the ticket panel embed to the configured Discord channel.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-paper-plane"></i> Deploy',
            customClass: { popup: 'modern-alert' }
        });
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('action', 'deploy_panel');
        fd.append('id', id);
        try {
            const resp = await fetch('ticket_actions.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Panel deployed to Discord!' });
                loadTicketPanels();
            } else {
                Toast.fire({ icon: 'error', title: data.error || 'Deploy failed' });
            }
        } catch (e) { Toast.fire({ icon: 'error', title: 'Network error' }); }
    }

    async function loadTickets(status) {
        const container = document.getElementById('ticketsList');
        if (!container) return;

        // Update filter button styles
        ['btnFilterOpen', 'btnFilterClosed', 'btnFilterAll'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) { btn.style.background = ''; btn.style.borderColor = ''; btn.style.color = ''; }
        });
        const activeBtn = status === 'open' ? 'btnFilterOpen' : status === 'closed' ? 'btnFilterClosed' : 'btnFilterAll';
        const btn = document.getElementById(activeBtn);
        if (btn) {
            const clr = status === 'open' ? '#43b581' : status === 'closed' ? '#f04747' : '#5865F2';
            btn.style.background = clr + '33';
            btn.style.borderColor = clr;
            btn.style.color = clr;
        }

        container.innerHTML = '<div style="text-align:center; color:#b5bac1; padding:30px;"><i class="fas fa-circle-notch fa-spin"></i> Loading...</div>';

        try {
            let url = 'ticket_actions.php?action=list_tickets';
            if (status) url += '&status=' + status;
            const resp = await fetch(url);
            const data = await resp.json();

            if (!data.success || !data.tickets.length) {
                container.innerHTML = '<div style="text-align:center; color:#72767d; padding:30px;"><i class="fas fa-inbox" style="font-size:1.5rem; margin-bottom:8px; display:block; opacity:0.3;"></i>No tickets found</div>';
                return;
            }

            container.innerHTML = `
            <div style="display:grid; gap:8px;">
                ${data.tickets.map(t => `
                    <div style="background:rgba(0,0,0,0.15); border-radius:8px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; border-left:3px solid ${t.status === 'open' ? '#43b581' : '#72767d'};">
                        <div>
                            <span style="font-weight:600; color:#fff;">${escHtml(t.user_name || 'Unknown')}</span>
                            <span style="color:#72767d; font-size:0.85rem; margin-left:8px;">#${t.id}</span>
                            <div style="font-size:0.8rem; color:#b5bac1; margin-top:2px;">
                                ${escHtml(t.panel_title || '')} · ${t.created_at || ''}
                                ${t.status === 'closed' ? ' · Closed by ' + escHtml(t.closed_by || '') : ''}
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="padding:3px 10px; border-radius:12px; font-size:0.75rem; font-weight:600; background:${t.status === 'open' ? 'rgba(67,181,129,0.15); color:#43b581;' : 'rgba(114,118,125,0.15); color:#72767d;'}">${t.status.toUpperCase()}</span>
                            ${t.status === 'open' ? `<button class="btn danger sm" onclick="closeTicketAdmin(${t.id})" title="Close"><i class="fas fa-lock"></i></button>` : ''}
                            ${t.status === 'closed' ? `<button class="btn secondary sm" onclick="viewTranscript(${t.id})" title="View Transcript" style="background:rgba(88,101,242,0.15); border-color:#5865F2; color:#5865F2;"><i class="fas fa-scroll"></i></button>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        } catch (e) {
            container.innerHTML = '<div style="color:#f04747; text-align:center; padding:20px;">Failed to load tickets</div>';
        }
    }

    async function closeTicketAdmin(ticketId) {
        const result = await Swal.fire({
            title: 'Close Ticket?',
            text: 'This will close the ticket and delete the Discord channel.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Close Ticket',
            customClass: { popup: 'modern-alert' }
        });
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('action', 'close_ticket');
        fd.append('ticket_id', ticketId);
        try {
            const resp = await fetch('ticket_actions.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Ticket closed' });
                loadTickets('open');
            } else {
                Toast.fire({ icon: 'error', title: data.error || 'Failed' });
            }
        } catch (e) { Toast.fire({ icon: 'error', title: 'Network error' }); }
    }

    async function viewTranscript(ticketId) {
        const overlay = document.getElementById('transcriptViewerOverlay');
        const msgContainer = document.getElementById('transcriptMessages');
        const metaContainer = document.getElementById('transcriptMeta');
        const titleEl = document.getElementById('transcriptTitle');

        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('active'), 10);
        msgContainer.innerHTML = '<div style="text-align:center; color:#b5bac1; padding:60px;"><i class="fas fa-circle-notch fa-spin"></i> Loading transcript...</div>';
        metaContainer.innerHTML = '';

        try {
            const resp = await fetch(`ticket_actions.php?action=get_transcript&ticket_id=${ticketId}`);
            const data = await resp.json();

            if (!data.success || !data.transcript) {
                msgContainer.innerHTML = '<div style="text-align:center; color:#72767d; padding:60px;"><i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:10px; opacity:0.3;"></i>No transcript available for this ticket.<br><span style="font-size:0.8rem;">Transcripts are saved when tickets are closed after this feature was enabled.</span></div>';
                return;
            }

            const t = data.transcript;
            titleEl.innerHTML = `<i class="fas fa-scroll" style="color:#5865F2;"></i> Transcript: ${escHtml(t.channel_name || 'ticket-' + ticketId)}`;
            metaContainer.innerHTML = `
                <span><i class="fas fa-user" style="color:#43b581;"></i> ${escHtml(t.user_name || 'Unknown')}</span>
                <span><i class="fas fa-ticket-alt" style="color:#5865F2;"></i> ${escHtml(t.panel_title || 'Support')}</span>
                <span><i class="fas fa-calendar"></i> Opened: ${t.ticket_created || '-'}</span>
                <span><i class="fas fa-lock" style="color:#f04747;"></i> Closed: ${t.ticket_closed || '-'} by ${escHtml(t.closed_by || '-')}</span>
                <span><i class="fas fa-comment"></i> ${t.message_count || 0} messages</span>
            `;

            let messages = [];
            try { messages = JSON.parse(t.messages); } catch(e) { messages = []; }

            if (!messages.length) {
                msgContainer.innerHTML = '<div style="text-align:center; color:#72767d; padding:40px;">No messages in transcript</div>';
                return;
            }

            let html = '';
            let lastAuthor = '';
            let lastTime = '';

            messages.forEach(msg => {
                const msgTime = new Date(msg.timestamp);
                const timeStr = msgTime.toLocaleString('th-TH', { dateStyle: 'short', timeStyle: 'short' });
                const isNewGroup = msg.author !== lastAuthor || (msgTime - new Date(lastTime)) > 300000;

                if (isNewGroup) {
                    const avatarUrl = msg.author_avatar || 'https://cdn.discordapp.com/embed/avatars/0.png';
                    const nameColor = msg.is_bot ? '#5865F2' : '#fff';
                    html += `
                    <div style="display:flex; gap:12px; margin-top:${lastAuthor ? '14px' : '0'}; padding:4px 0;">
                        <img src="${avatarUrl}" style="width:36px; height:36px; border-radius:50%; flex-shrink:0; margin-top:2px;" onerror="this.src='https://cdn.discordapp.com/embed/avatars/0.png'">
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:baseline; gap:8px; margin-bottom:2px;">
                                <span style="font-weight:600; color:${nameColor}; font-size:0.95rem;">${escHtml(msg.author)}${msg.is_bot ? ' <span style="background:#5865F2; color:#fff; font-size:0.6rem; padding:1px 5px; border-radius:3px; vertical-align:middle;">BOT</span>' : ''}</span>
                                <span style="color:#72767d; font-size:0.72rem;">${timeStr}</span>
                            </div>`;
                }

                // Message content
                if (msg.content) {
                    html += `<div style="color:#dcddde; font-size:0.9rem; line-height:1.4; word-break:break-word; ${!isNewGroup ? 'padding-left:48px;' : ''}">${escHtml(msg.content).replace(/\n/g, '<br>')}</div>`;
                }

                // Embeds
                if (msg.embeds && msg.embeds.length) {
                    msg.embeds.forEach(emb => {
                        const borderColor = emb.color || '#5865F2';
                        html += `<div style="border-left:3px solid ${borderColor}; background:rgba(0,0,0,0.15); border-radius:4px; padding:10px 14px; margin:4px 0; max-width:450px; ${!isNewGroup ? 'margin-left:48px;' : ''}">`;
                        if (emb.title) html += `<div style="font-weight:600; color:#fff; font-size:0.85rem;">${escHtml(emb.title)}</div>`;
                        if (emb.description) html += `<div style="color:#b5bac1; font-size:0.82rem; margin-top:4px;">${escHtml(emb.description).replace(/\n/g, '<br>')}</div>`;
                        html += '</div>';
                    });
                }

                // Attachments
                if (msg.attachments && msg.attachments.length) {
                    msg.attachments.forEach(att => {
                        const isImage = (att.content_type || '').startsWith('image/');
                        if (isImage) {
                            html += `<div style="margin:4px 0; ${!isNewGroup ? 'padding-left:48px;' : ''}"><img src="${att.url}" style="max-width:350px; max-height:250px; border-radius:8px; cursor:pointer;" onclick="window.open('${att.url}','_blank')" onerror="this.style.display='none'"></div>`;
                        } else {
                            html += `<div style="margin:4px 0; ${!isNewGroup ? 'padding-left:48px;' : ''}"><a href="${att.url}" target="_blank" style="color:#00b0f4; font-size:0.85rem;"><i class="fas fa-file"></i> ${escHtml(att.filename)}</a></div>`;
                        }
                    });
                }

                if (isNewGroup) {
                    html += '</div></div>';
                }

                lastAuthor = msg.author;
                lastTime = msg.timestamp;
            });

            msgContainer.innerHTML = html;
            msgContainer.scrollTop = msgContainer.scrollHeight;

        } catch (e) {
            console.error('Transcript load error:', e);
            msgContainer.innerHTML = '<div style="color:#f04747; text-align:center; padding:40px;">Failed to load transcript</div>';
        }
    }

    function closeTranscriptViewer() {
        const overlay = document.getElementById('transcriptViewerOverlay');
        overlay.classList.remove('active');
        setTimeout(() => overlay.style.display = 'none', 300);
    }

    // Auto-load ticket data when tab is visible
    document.addEventListener('DOMContentLoaded', () => {
        const ticketTab = document.getElementById('tab-tickets');
        if (ticketTab && ticketTab.classList.contains('active')) {
            loadTicketPanels();
            loadTickets('open');
            loadCategories();
        }
    });

    // Hook into tab switching to load data
    const origOpenTab = window.openTab;
    if (typeof origOpenTab === 'function') {
        window.openTab = function (evt, tabName) {
            origOpenTab(evt, tabName);
            if (tabName === 'tab-tickets') {
                loadTicketPanels();
                loadTickets('open');
                loadCategories();
            } else if (tabName === 'tab-welcome') {
                // Ensure the welcome sound UI settings load
                if(typeof loadWelcomeSoundSettings === 'function') loadWelcomeSoundSettings();
                if(typeof loadServerLeaveSettings === 'function') loadServerLeaveSettings();
                if(typeof loadVoiceLogsSettings === 'function') loadVoiceLogsSettings();
            }
        };
    }
    
    // Auto-load on initial page load if active
    document.addEventListener('DOMContentLoaded', () => {
        const welcomeTab = document.getElementById('tab-welcome');
        if (welcomeTab && welcomeTab.classList.contains('active')) {
            if(typeof loadWelcomeSoundSettings === 'function') loadWelcomeSoundSettings();
            if(typeof loadServerLeaveSettings === 'function') loadServerLeaveSettings();
            if(typeof loadVoiceLogsSettings === 'function') loadVoiceLogsSettings();
        }
        const rolesTab = document.getElementById('tab-roles');
        if (rolesTab && rolesTab.classList.contains('active')) {
            loadRolePanels();
        }
    });

    // ====== ROLE PANELS TAB ======

    const rpAvailableRoles = <?php echo json_encode($roles); ?>;

    async function loadRolePanels() {
        const container = document.getElementById('rolePanelsList');
        if (!container) return;
        container.innerHTML = '<div style="text-align:center; color:#b5bac1; padding:30px;"><i class="fas fa-circle-notch fa-spin"></i> Loading...</div>';

        try {
            const resp = await fetch('../includes/bot_api_proxy.php?endpoint=/role-panels&method=GET');
            const data = await resp.json();

            if (!data.success || !data.panels || !data.panels.length) {
                container.innerHTML = '<div style="text-align:center; color:#72767d; padding:40px;"><i class="fas fa-id-badge" style="font-size:1.5rem; margin-bottom:10px; display:block; opacity:0.3;"></i>No role panels yet. Click <b>New Panel</b> to create one.</div>';
                return;
            }

            let html = '<div style="display:grid; gap:12px;">';
            data.panels.forEach(p => {
                const buttons = (typeof p.buttons === 'string' ? JSON.parse(p.buttons) : p.buttons) || [];
                const btnPreview = buttons.map(b => {
                    const colors = { blue: '#5865F2', blurple: '#5865F2', green: '#43b581', red: '#ed4245', grey: '#4f545c', gray: '#4f545c' };
                    const bg = colors[b.color] || '#5865F2';
                    return `<span style="display:inline-block; background:${bg}; color:#fff; padding:4px 12px; border-radius:3px; font-size:0.8rem; font-weight:500;">${escHtml(b.emoji || '')} ${escHtml(b.label || 'Button')}</span>`;
                }).join(' ');
                const modeLabel = p.mode === 'give' ? 'Give Only' : 'Toggle';
                const sentBadge = p.message_id ? `<span style="padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:600; background:rgba(67,181,129,0.15); color:#43b581;">LIVE</span>` : `<span style="padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:600; background:rgba(255,255,255,0.08); color:#72767d;">DRAFT</span>`;

                html += `
                <div style="background:rgba(0,0,0,0.15); border-radius:8px; padding:16px 20px; border-left:3px solid #5865F2;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                        <div>
                            <span style="font-weight:600; color:#fff; font-size:1.05rem;">${escHtml(p.title || 'Untitled')}</span>
                            ${sentBadge}
                            <span style="padding:2px 8px; border-radius:10px; font-size:0.7rem; font-weight:600; background:rgba(88,101,242,0.15); color:#5865F2;">${modeLabel}</span>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button class="btn secondary sm" onclick="editRolePanel('${p.panel_id}')" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn primary sm" onclick="sendRolePanelToDiscord('${p.panel_id}')" title="Send to Discord"><i class="fas fa-paper-plane"></i></button>
                            <button class="btn danger sm" onclick="deleteRolePanel('${p.panel_id}')" title="Delete" style="background:rgba(237,66,69,0.15); border-color:#ed4245; color:#ed4245;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div style="color:#b5bac1; font-size:0.85rem; margin-bottom:10px;">${escHtml(p.description || '')}</div>
                    <div style="display:flex; flex-wrap:wrap; gap:6px;">${btnPreview || '<span style="color:#72767d; font-size:0.8rem;">No buttons configured</span>'}</div>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        } catch (e) {
            console.error('Load role panels error:', e);
            container.innerHTML = '<div style="color:#f04747; text-align:center; padding:20px;">Failed to load role panels</div>';
        }
    }

    function showRolePanelEditor(panelData = null) {
        document.getElementById('rpEditPanelId').value = panelData ? panelData.panel_id : '';
        document.getElementById('rpTitle').value = panelData ? panelData.title : '';
        document.getElementById('rpDescription').value = panelData ? panelData.description : '';
        document.getElementById('rpChannelId').value = panelData ? (panelData.channel_id || '') : '';
        document.getElementById('rpEmbedColor').value = panelData ? (panelData.embed_color || '#5865F2') : '#5865F2';
        document.getElementById('rpMode').value = panelData ? (panelData.mode || 'toggle') : 'toggle';
        document.getElementById('rolePanelEditorTitle').innerHTML = panelData ? '<i class="fas fa-edit"></i> Edit Role Panel' : '<i class="fas fa-id-badge"></i> New Role Panel';

        const btnList = document.getElementById('rpButtonsList');
        btnList.innerHTML = '';

        if (panelData && panelData.buttons) {
            const buttons = typeof panelData.buttons === 'string' ? JSON.parse(panelData.buttons) : panelData.buttons;
            buttons.forEach(b => addRolePanelButton(b));
        }

        const overlay = document.getElementById('rolePanelEditorOverlay');
        overlay.style.display = 'flex';
        setTimeout(() => overlay.classList.add('active'), 10);
    }

    function closeRolePanelEditor() {
        const overlay = document.getElementById('rolePanelEditorOverlay');
        overlay.classList.remove('active');
        setTimeout(() => overlay.style.display = 'none', 300);
    }

    function addRolePanelButton(data = null) {
        const list = document.getElementById('rpButtonsList');
        const idx = list.children.length;

        const roleOptions = rpAvailableRoles.map(r => {
            const selected = data && data.role_id === r.id ? 'selected' : '';
            return `<option value="${r.id}" ${selected} style="color:${r.color || '#99aab5'}">@${escHtml(r.name)}</option>`;
        }).join('');

        const div = document.createElement('div');
        div.style.cssText = 'background:rgba(0,0,0,0.2); border-radius:6px; padding:12px; display:grid; grid-template-columns:1fr 1fr 80px 80px auto; gap:8px; align-items:center;';
        div.innerHTML = `
            <select class="modern-input rp-btn-role" style="font-size:0.85rem;">
                <option value="">-- Select Role --</option>
                ${roleOptions}
            </select>
            <input type="text" class="modern-input rp-btn-label" placeholder="Button Label" value="${escHtml(data ? data.label || '' : '')}" style="font-size:0.85rem;">
            <input type="text" class="modern-input rp-btn-emoji" placeholder="Emoji" value="${escHtml(data ? data.emoji || '' : '')}" style="font-size:0.85rem; text-align:center;" maxlength="5">
            <select class="modern-input rp-btn-color" style="font-size:0.85rem;">
                <option value="blurple" ${data && data.color === 'blurple' ? 'selected' : ''}>Blue</option>
                <option value="green" ${data && data.color === 'green' ? 'selected' : ''}>Green</option>
                <option value="red" ${data && data.color === 'red' ? 'selected' : ''}>Red</option>
                <option value="grey" ${data && data.color === 'grey' || data && data.color === 'gray' ? 'selected' : ''}>Grey</option>
            </select>
            <button type="button" onclick="this.parentElement.remove()" style="background:rgba(237,66,69,0.2); border:none; color:#ed4245; width:32px; height:32px; border-radius:6px; cursor:pointer; font-size:0.85rem;"><i class="fas fa-times"></i></button>
        `;
        list.appendChild(div);
    }

    function collectRolePanelButtons() {
        const items = document.querySelectorAll('#rpButtonsList > div');
        const buttons = [];
        items.forEach(item => {
            const role_id = item.querySelector('.rp-btn-role').value;
            const label = item.querySelector('.rp-btn-label').value;
            const emoji = item.querySelector('.rp-btn-emoji').value;
            const color = item.querySelector('.rp-btn-color').value;
            if (role_id) {
                buttons.push({ role_id, label: label || getRoleNameById(role_id), emoji, color });
            }
        });
        return buttons;
    }

    function getRoleNameById(id) {
        const r = rpAvailableRoles.find(r => r.id === id);
        return r ? r.name : 'Role';
    }

    async function saveRolePanel() {
        const panelId = document.getElementById('rpEditPanelId').value;
        const title = document.getElementById('rpTitle').value.trim();
        const description = document.getElementById('rpDescription').value.trim();
        const channelId = document.getElementById('rpChannelId').value;
        const embedColor = document.getElementById('rpEmbedColor').value;
        const mode = document.getElementById('rpMode').value;
        const buttons = collectRolePanelButtons();

        console.log('[RolePanel] Save clicked', { panelId, title, channelId, mode, buttons });

        if (!title) { Toast.fire({ icon: 'warning', title: 'Please enter a title' }); return; }
        if (buttons.length === 0) { Toast.fire({ icon: 'warning', title: 'Add at least one role button' }); return; }

        const payload = {
            panel_id: panelId || undefined,
            title, description, embed_color: embedColor, mode,
            channel_id: channelId || null,
            buttons
        };

        console.log('[RolePanel] Sending payload:', JSON.stringify(payload));

        try {
            const resp = await fetch('../includes/bot_api_proxy.php?endpoint=/role-panels&method=POST', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const text = await resp.text();
            console.log('[RolePanel] Status:', resp.status, 'Body:', text);
            let data;
            try { data = JSON.parse(text); } catch(pe) {
                alert('[RolePanel ERROR] Server returned invalid JSON: ' + text.substring(0, 200));
                return;
            }
            if (data.success) {
                closeRolePanelEditor();
                Toast.fire({ icon: 'success', title: 'Role panel saved!' });
                loadRolePanels();
            } else {
                alert('[RolePanel ERROR] ' + (data.error || 'Save failed'));
            }
        } catch (e) {
            alert('[RolePanel EXCEPTION] ' + e.message);
        }
    }

    async function editRolePanel(panelId) {
        try {
            const resp = await fetch('../includes/bot_api_proxy.php?endpoint=/role-panels&method=GET');
            const data = await resp.json();
            if (data.success && data.panels) {
                const panel = data.panels.find(p => p.panel_id === panelId);
                if (panel) {
                    showRolePanelEditor(panel);
                }
            }
        } catch (e) {
            Toast.fire({ icon: 'error', title: 'Failed to load panel' });
        }
    }

    async function deleteRolePanel(panelId) {
        const result = await Swal.fire({
            title: 'Delete Role Panel?',
            text: 'This will also delete the Discord message if it was sent.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            customClass: { popup: 'modern-alert' }
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch('../includes/bot_api_proxy.php?endpoint=/role-panels&method=DELETE', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ panel_id: panelId })
            });
            const data = await resp.json();
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Panel deleted' });
                loadRolePanels();
            } else {
                Toast.fire({ icon: 'error', title: data.error || 'Delete failed' });
            }
        } catch (e) {
            Toast.fire({ icon: 'error', title: 'Network error' });
        }
    }

    async function sendRolePanelToDiscord(panelId) {
        // Get panel to check channel
        let channelId = '';
        try {
            const resp = await fetch('../includes/bot_api_proxy.php?endpoint=/role-panels&method=GET');
            const data = await resp.json();
            if (data.success && data.panels) {
                const panel = data.panels.find(p => p.panel_id === panelId);
                if (panel) channelId = panel.channel_id || '';
            }
        } catch(e) {}

        if (!channelId) {
            Toast.fire({ icon: 'warning', title: 'Please edit the panel and select a target channel first.' });
            return;
        }

        const result = await Swal.fire({
            title: 'Send Role Panel?',
            text: 'This will send (or re-send) the role panel embed with buttons to the selected channel.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Send',
            customClass: { popup: 'modern-alert' }
        });
        if (!result.isConfirmed) return;

        try {
            const resp = await fetch('../includes/bot_api_proxy.php?endpoint=/role-panels/send&method=POST', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ panel_id: panelId, channel_id: channelId })
            });
            const data = await resp.json();
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'Role panel sent to Discord!' });
                loadRolePanels();
            } else {
                Toast.fire({ icon: 'error', title: data.error || 'Send failed' });
            }
        } catch (e) {
            Toast.fire({ icon: 'error', title: 'Network error' });
        }
    }

    // Hook role panels into tab switching
    const _origOpenTabForRoles = window.openTab;
    if (typeof _origOpenTabForRoles === 'function') {
        window.openTab = function(evt, tabName) {
            _origOpenTabForRoles(evt, tabName);
            if (tabName === 'tab-roles') {
                loadRolePanels();
            }
        };
    }
</script>
</body>

</html>