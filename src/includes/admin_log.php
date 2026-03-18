<?php
/**
 * Admin Activity Logger
 * Include this file and call logAdminAction() to record any admin action.
 */

function logAdminAction($pdo, $action, $targetType = null, $targetId = null, $details = null)
{
    try {
        // Ensure table exists
        static $tableChecked = false;
        if (!$tableChecked) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_log (
id INT(11) NOT NULL AUTO_INCREMENT,
admin_id INT(11) NOT NULL,
action VARCHAR(100) NOT NULL,
target_type VARCHAR(50) DEFAULT NULL,
target_id INT(11) DEFAULT NULL,
details TEXT DEFAULT NULL,
ip_address VARCHAR(45) DEFAULT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
KEY idx_admin_id (admin_id),
KEY idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $tableChecked = true;
        }

        // Get current admin
        if (function_exists('getUser')) {
            $currentAdmin = getUser();
        } else {
            $currentAdmin = $_SESSION['user'] ?? null;
        }
        $adminId = $currentAdmin ? ($currentAdmin['id'] ?? 0) : 0;

        if (!$adminId) {
            return false;
        }

        // Convert details to JSON if array
        if (is_array($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE);
        }

        // Get real client IP (behind Docker/reverse proxy)
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $clientIp = trim($_SERVER['HTTP_X_REAL_IP']);
        }

        $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, target_type, target_id, details, ip_address)
VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $adminId,
            $action,
            $targetType,
            $targetId,
            $details,
            $clientIp
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Admin activity log failed: " . $e->getMessage());
        return false;
    }
}