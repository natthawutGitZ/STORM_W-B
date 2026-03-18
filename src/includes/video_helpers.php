<?php
/**
 * Video Helper Functions
 * Handles video platform detection, ID extraction, thumbnails, and embed URLs
 */

/**
 * Detect video platform from URL
 */
function detectVideoPlatform($url)
{
    $url = strtolower(trim($url));

    if (preg_match('/(?:youtube\.com|youtu\.be)/i', $url)) {
        return 'youtube';
    } elseif (preg_match('/vimeo\.com/i', $url)) {
        return 'vimeo';
    } elseif (preg_match('/dailymotion\.com|dai\.ly/i', $url)) {
        return 'dailymotion';
    } elseif (preg_match('/facebook\.com.*\/videos/i', $url)) {
        return 'facebook';
    } elseif (preg_match('/tiktok\.com/i', $url)) {
        return 'tiktok';
    } elseif (preg_match('/\.(mp4|webm|ogg)$/i', $url)) {
        return 'direct';
    }

    return 'unknown';
}

/**
 * Extract video ID from URL based on platform
 */
function extractVideoId($url, $platform)
{
    switch ($platform) {
        case 'youtube':
            // Handle various YouTube URL formats
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
                return $matches[1];
            }
            break;

        case 'vimeo':
            // Handle Vimeo URLs
            if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
                return $matches[1];
            }
            break;

        case 'dailymotion':
            // Handle Dailymotion URLs
            if (preg_match('/(?:dailymotion\.com\/video\/|dai\.ly\/)([a-zA-Z0-9]+)/', $url, $matches)) {
                return $matches[1];
            }
            break;

        case 'facebook':
            // For Facebook, return the full URL as ID
            return $url;

        case 'tiktok':
            // Handle TikTok URLs - extract video ID
            if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $matches)) {
                return $matches[1];
            } elseif (preg_match('/tiktok\.com\/v\/(\d+)/', $url, $matches)) {
                return $matches[1];
            }
            // If can't extract ID, return full URL
            return $url;

        case 'direct':
            // For direct links, return the URL
            return $url;
    }

    return null;
}

/**
 * Get video thumbnail URL
 */
function getVideoThumbnail($videoId, $platform)
{
    switch ($platform) {
        case 'youtube':
            return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";

        case 'vimeo':
            // Vimeo requires API call, return placeholder or use oEmbed
            try {
                $data = @file_get_contents("https://vimeo.com/api/v2/video/{$videoId}.json");
                if ($data) {
                    $json = json_decode($data, true);
                    return $json[0]['thumbnail_large'] ?? null;
                }
            } catch (Exception $e) {
                return null;
            }
            return null;

        case 'dailymotion':
            return "https://www.dailymotion.com/thumbnail/video/{$videoId}";

        case 'tiktok':
            // TikTok doesn't provide direct thumbnail API
            // Return null, will use default or try to fetch from oEmbed
            return null;

        case 'facebook':
        case 'direct':
        default:
            return null;
    }
}

/**
 * Get video embed URL
 */
function getVideoEmbedUrl($videoId, $platform)
{
    switch ($platform) {
        case 'youtube':
            return "https://www.youtube.com/embed/{$videoId}";

        case 'vimeo':
            return "https://player.vimeo.com/video/{$videoId}";

        case 'dailymotion':
            return "https://www.dailymotion.com/embed/video/{$videoId}";

        case 'facebook':
            return "https://www.facebook.com/plugins/video.php?href=" . urlencode($videoId);

        case 'tiktok':
            // TikTok embed - use the full URL
            return "https://www.tiktok.com/embed/v2/" . $videoId;

        case 'direct':
            return $videoId; // Return the direct URL

        default:
            return null;
    }
}

/**
 * Get video metadata using oEmbed
 */
function getVideoMetadata($url)
{
    $platform = detectVideoPlatform($url);
    $metadata = [
        'title' => '',
        'description' => '',
        'thumbnail' => '',
        'duration' => 0
    ];

    try {
        $oembedUrl = null;

        switch ($platform) {
            case 'youtube':
                $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
                break;

            case 'vimeo':
                $oembedUrl = "https://vimeo.com/api/oembed.json?url=" . urlencode($url);
                break;

            case 'dailymotion':
                $oembedUrl = "https://www.dailymotion.com/services/oembed?url=" . urlencode($url);
                break;

            case 'tiktok':
                $oembedUrl = "https://www.tiktok.com/oembed?url=" . urlencode($url);
                break;
        }

        if ($oembedUrl) {
            $data = @file_get_contents($oembedUrl);
            if ($data) {
                $json = json_decode($data, true);
                $metadata['title'] = $json['title'] ?? '';
                $metadata['description'] = $json['description'] ?? '';
                $metadata['thumbnail'] = $json['thumbnail_url'] ?? '';
            }
        }
    } catch (Exception $e) {
        // Silently fail and return empty metadata
    }

    return $metadata;
}

/**
 * Validate video URL
 */
function validateVideoUrl($url)
{
    if (empty($url)) {
        return ['valid' => false, 'error' => 'URL is required'];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['valid' => false, 'error' => 'Invalid URL format'];
    }

    $platform = detectVideoPlatform($url);
    if ($platform === 'unknown') {
        return ['valid' => false, 'error' => 'Unsupported video platform'];
    }

    $videoId = extractVideoId($url, $platform);
    if (!$videoId) {
        return ['valid' => false, 'error' => 'Could not extract video ID'];
    }

    return [
        'valid' => true,
        'platform' => $platform,
        'video_id' => $videoId
    ];
}
