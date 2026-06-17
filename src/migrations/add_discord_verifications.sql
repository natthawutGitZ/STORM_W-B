-- Migration: Add discord_verifications table for Form Verification System
-- This table stores temporary verification records for Discord DM button verification

CREATE TABLE IF NOT EXISTS `discord_verifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `discord_user_id` VARCHAR(30) NOT NULL COMMENT 'Discord user snowflake ID',
    `form_id` INT(11) NOT NULL COMMENT 'The form being submitted',
    `target_number` VARCHAR(10) NOT NULL COMMENT 'The correct number the user must press',
    `decoy_numbers` JSON NOT NULL COMMENT 'Array of all button numbers (shuffled)',
    `status` ENUM('pending', 'success', 'failed', 'expired') DEFAULT 'pending',
    `attempts` INT(11) DEFAULT 0 COMMENT 'Number of button presses attempted',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'Auto-expire after 5 minutes',
    PRIMARY KEY (`id`),
    KEY `idx_discord_user` (`discord_user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
