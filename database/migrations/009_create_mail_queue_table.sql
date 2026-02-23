-- Migration: Create mail_queue table
-- Created: 2026-02-23

CREATE TABLE IF NOT EXISTS `mail_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `to_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `template` VARCHAR(255) NULL COMMENT 'Twig template path relative to templates/emails/, e.g. password-reset.twig',
    `vars` JSON NULL COMMENT 'Template variables as JSON',
    `body` LONGTEXT NULL COMMENT 'Pre-rendered or plain HTML/text body (used when template is NULL)',
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `queued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_queued_at` (`queued_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
