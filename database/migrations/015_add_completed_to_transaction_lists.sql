-- Migration: Add completed timestamp to transaction_lists
-- Created: 2026-02-27

ALTER TABLE `transaction_lists`
    ADD COLUMN `completed` TIMESTAMP NULL DEFAULT NULL AFTER `imported_at`,
    ADD INDEX `idx_completed` (`completed`);
