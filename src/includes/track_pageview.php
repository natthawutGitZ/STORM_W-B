<?php
/**
 * Track page views for analytics
 * Include this file at the top of pages you want to track
 */

function trackPageView($pdo, $pageTitle = null)
{
    // Don't track admin pages or API calls
    $currentPage = $_SERVER['REQUEST_URI'] ?? '';

    // Skip tracking for AJAX requests
    if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
    ) {
        return;
    }

    // Skip tracking for asset files
    if (preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf)$/i', $currentPage)) {
        return;
    }

    try {
        $userId = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ipAddress = trim($_SERVER['HTTP_X_REAL_IP']);
        }
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
        $referrer = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 512);
        $pageUrl = substr($currentPage, 0, 255);

        $stmt = $pdo->prepare("
            INSERT INTO page_views (page_url, page_title, user_id, ip_address, user_agent, referrer)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$pageUrl, $pageTitle, $userId, $ipAddress, $userAgent, $referrer]);
    } catch (PDOException $e) {
        // Silently fail - don't break the page if tracking fails
        error_log("Page view tracking error: " . $e->getMessage());
    }
}
