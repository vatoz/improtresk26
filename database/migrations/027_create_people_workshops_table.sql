-- Create people_workshops junction table
CREATE TABLE IF NOT EXISTS people_workshops (
    person_id INT NOT NULL,
    workshop_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (person_id, workshop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing data from people.workshop_id
INSERT IGNORE INTO people_workshops (person_id, workshop_id)
SELECT id, workshop_id FROM people WHERE workshop_id IS NOT NULL;

-- Remove workshop_id column from people
ALTER TABLE people DROP COLUMN workshop_id;
