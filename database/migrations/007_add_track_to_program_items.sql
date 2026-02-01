-- Add track column to program_items table for concurrent programming blocks

ALTER TABLE program_items
ADD COLUMN track VARCHAR(10) NULL COMMENT 'Track identifier: NULL for full-width items, A/B for concurrent tracks'
AFTER location;

-- Add index for efficient track-based queries
CREATE INDEX idx_program_items_track ON program_items(track);
