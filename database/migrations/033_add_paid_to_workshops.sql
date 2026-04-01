-- Add paid field to workshops table
-- Stores the cached count of paid registrations for each workshop

ALTER TABLE workshops
ADD COLUMN `paid` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cached count of paid registrations';
