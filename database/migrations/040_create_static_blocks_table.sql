-- Migration: Create static_blocks table for named content blocks
-- Created: 2026-04-12

CREATE TABLE IF NOT EXISTS `static_blocks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `block_name` VARCHAR(100) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `block_description` TEXT NULL,
    `content` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    INDEX `idx_block_name` (`block_name`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
