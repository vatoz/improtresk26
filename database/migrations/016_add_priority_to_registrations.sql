-- Add priority column to registrations for admin sorting
-- Lower value = higher priority; NULL means no priority set yet

ALTER TABLE registrations
    ADD COLUMN `priority` INT NULL DEFAULT NULL COMMENT 'Admin-assigned priority for sorting registrations' AFTER `notes`;

CREATE INDEX `idx_priority` ON registrations (`priority`);
