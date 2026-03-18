<?php
// Script to set up the Campaigns Database Tables
// Run this file once by visiting it in your browser: http://yourdomain/admin/tools/setup_campaigns_db.php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdmin()) {
    die("Unauthorized access. Only administrators can run this script.");
}

try {
    // 1. Create `campaigns` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `campaigns` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `image_path` VARCHAR(500) DEFAULT NULL,
            `description` TEXT,
            `status` ENUM('Active', 'Completed') DEFAULT 'Active',
            `created_by` INT,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Table 'campaigns' created successfully.<br>";

    // 2. Create `campaign_chapters` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `campaign_chapters` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `event_date` DATETIME,
            `sort_order` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Table 'campaign_chapters' created successfully.<br>";

    // 3. Create `campaign_docs` table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `campaign_docs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `content` TEXT,
            `doc_type` VARCHAR(50) DEFAULT 'Intel',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✅ Table 'campaign_docs' created successfully.<br>";

    echo "<br><strong>🎉 All Campaigns tables have been set up successfully!</strong>";
    echo "<br><a href='../dashboard.php'>Return to Dashboard</a>";

} catch (PDOException $e) {
    die("❌ Error creating tables: " . $e->getMessage());
}
?>