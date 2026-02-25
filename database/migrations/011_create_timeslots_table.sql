-- Create timeslots table for named variants of workshop time combinations
-- Examples: A => "Pátek dopoledne", AB => "Pátek celý den", ABCD => "Dvoudenní workshop pátek sobota"

CREATE TABLE timeslots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Letter combination matching workshops.timeslot (e.g. A, AB, ABCD)',
    name VARCHAR(255) NOT NULL COMMENT 'Human-readable label shown to users',
    `order` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Display order',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_timeslots_order ON timeslots(`order`);
