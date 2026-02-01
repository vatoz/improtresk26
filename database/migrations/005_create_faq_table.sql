-- Migration: Create faq table for frequently asked questions
-- Created: 2026-01-31

CREATE TABLE IF NOT EXISTS `faq` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question` TEXT NOT NULL,
    `answer` TEXT NOT NULL,
    `category` VARCHAR(100) NULL,
    `order` INT UNSIGNED DEFAULT 0,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_order` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
