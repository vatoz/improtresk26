-- Migration: Create registrations table
-- Created: 2026-01-31

CREATE TABLE IF NOT EXISTS `registrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `workshop_id` INT UNSIGNED NOT NULL,
    `payment_status` ENUM('pending', 'paid', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    `companion_program` BOOLEAN DEFAULT FALSE,
    `variable_symbol` VARCHAR(20) NULL UNIQUE,
    `paid_at` TIMESTAMP NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`workshop_id`) REFERENCES `workshops`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_workshop` (`user_id`, `workshop_id`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_variable_symbol` (`variable_symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
