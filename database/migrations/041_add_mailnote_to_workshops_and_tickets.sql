ALTER TABLE workshops ADD COLUMN mailnote TEXT NULL AFTER description;
ALTER TABLE tickets ADD COLUMN mailnote TEXT NULL AFTER description;
