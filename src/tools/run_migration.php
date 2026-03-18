<?php
/**
 * Run page_views migration
 * Access this file once to create the table, then delete it
 */

require_once ROOT_PATH . '/includes/db.php';

// Check if admin
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die('Unauthorized');
}

try {
    $sql = "CREATE TABLE IF NOT EXISTS `page_views` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `page_url` VARCHAR(255) NOT NULL,
        `page_title` VARCHAR(100) DEFAULT NULL,
        `user_id` INT(11) DEFAULT NULL COMMENT 'NULL for guests',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(512) DEFAULT NULL,
        `referrer` VARCHAR(512) DEFAULT NULL,
        `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_viewed_at` (`viewed_at`),
        KEY `idx_page_url` (`page_url`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);

    echo "<h1 style='color: green;'>✅ Migration completed successfully!</h1>";
    echo "<p>Table 'page_views' has been created.</p>";
    echo "<p><strong>IMPORTANT:</strong> Delete this file after running!</p>";
    echo "<a href='admin/dashboard.php'>Go to Dashboard</a>";

} catch (PDOException $e) {
    echo "<h1 style='color: red;'>❌ Migration failed!</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
