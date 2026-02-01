-- Migration: Create program_items table for festival schedule
-- Created: 2026-01-31

CREATE TABLE IF NOT EXISTS `program_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `performer` VARCHAR(255) NULL,
    `type` ENUM('performance', 'workshop', 'discussion', 'party', 'other') NOT NULL DEFAULT 'performance',
    `date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `location` VARCHAR(255) NULL,
    `is_free` BOOLEAN DEFAULT FALSE,
    `max_capacity` INT UNSIGNED NULL,
    `image_url` VARCHAR(512) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date_time` (`date`, `start_time`),
    INDEX `idx_type` (`type`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
