-- Migration: Create program_info table for detailed program entries
-- Created: 2026-04-11

CREATE TABLE IF NOT EXISTS `program_info` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `program_item_id` INT UNSIGNED NULL,
    `web_link` VARCHAR(500) NULL,
    `instagram_link` VARCHAR(500) NULL,
    `facebook_link` VARCHAR(500) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `order` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`program_item_id`) REFERENCES `program_items`(`id`) ON DELETE SET NULL,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
