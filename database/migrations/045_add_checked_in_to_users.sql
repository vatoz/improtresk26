-- Migration: Add checked_in datetime to users table
-- Created: 2026-05-05

ALTER TABLE `users`
    ADD COLUMN `checked_in` DATETIME NULL DEFAULT NULL AFTER `note`;
