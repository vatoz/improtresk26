-- Add timeslot field to workshops table
-- This field holds letters representing time blocks (e.g., "abc", "bc", "d")
-- to handle overlapping workshops and filtering

ALTER TABLE workshops
ADD COLUMN timeslot VARCHAR(10) DEFAULT NULL COMMENT 'Letters representing time blocks (e.g., abc, bc, d)';

-- Add index for faster filtering by timeslot
CREATE INDEX idx_workshops_timeslot ON workshops(timeslot);
