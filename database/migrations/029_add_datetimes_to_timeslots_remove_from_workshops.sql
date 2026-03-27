-- Add start/end datetime and duration to timeslots
-- Remove date, time, duration_minutes from workshops (now owned by timeslots)

ALTER TABLE timeslots
    ADD COLUMN start_datetime DATETIME NULL COMMENT 'Workshop block start date and time' AFTER `order`,
    ADD COLUMN end_datetime   DATETIME NULL COMMENT 'Workshop block end date and time'   AFTER start_datetime,
    ADD COLUMN duration_minutes INT UNSIGNED NULL COMMENT 'Duration of workshops in this block in minutes' AFTER end_datetime;

ALTER TABLE workshops
    DROP COLUMN date,
    DROP COLUMN time,
    DROP COLUMN duration_minutes;
