-- Migration: Create workshops table
-- Created: 2026-01-31

CREATE TABLE IF NOT EXISTS `workshops` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `instructor` VARCHAR(255) NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `duration_minutes` INT UNSIGNED DEFAULT 120,
    `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `capacity` INT UNSIGNED DEFAULT 20,
    `location` VARCHAR(255) NULL,
    `level` ENUM('beginner', 'intermediate', 'advanced', 'all') DEFAULT 'all',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date` (`date`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
