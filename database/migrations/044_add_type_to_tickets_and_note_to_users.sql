-- Migration: Add type + children_item_id to tickets, add note to users
-- Created: 2026-04-30

ALTER TABLE `tickets`
    ADD COLUMN `type` ENUM('standard', 'child', 'adult', 'family') NOT NULL DEFAULT 'standard' AFTER `is_active`,
    ADD COLUMN `children_item_id` INT UNSIGNED NULL DEFAULT NULL AFTER `type`;

ALTER TABLE `users`
    ADD COLUMN `note` TEXT NULL DEFAULT NULL AFTER `awaiting_payment`;

    
INSERT INTO `tickets` (`id`, `name`, `description`, `mailnote`, `date`, `time`, `price`, `capacity`, `is_active`, `type`, `children_item_id`, `created_at`) VALUES
(4,	'Vstup 1. představení DětskéhoTřesku -dítě',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'child',	1,	'2026-05-01 02:05:27'),
(7,	'Vstup 1. představení DětskéhoTřesku - dospělý',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'adult',	1,	'2026-05-01 02:05:27'),
(8,	'Vstup 1. představení DětskéhoTřesku -rodina',	NULL,	NULL,	NULL,	NULL,	150.00,	NULL,	1,	'family',	1,	'2026-05-01 02:05:27'),
(9,	'Vstup 2. představení DětskéhoTřesku - dítě',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'child',	2,	'2026-05-01 02:05:27'),
(10, 'Vstup 2. představení DětskéhoTřesku - dospělý',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'adult',	2,	'2026-05-01 02:05:27'),
(11, 'Vstup 2. představení DětskéhoTřesku - rodina',	NULL,	NULL,	NULL,	NULL,	150.00,	NULL,	1,	'family',	2,	'2026-05-01 02:05:27'),
(12,	'Vstup 3. představení DětskéhoTřesku - dítě',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	0,	'child',	3,	'2026-05-01 02:05:27'),
(13,	'Vstup 3. představení DětskéhoTřesku - dospělý',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'adult',	3,	'2026-05-01 02:05:27'),
(14,	'Vstup 3. představení DětskéhoTřesku - rodina',	NULL,	NULL,	NULL,	NULL,	150.00,	NULL,	1,	'family',	3,	'2026-05-01 02:05:27'),
(15,	'Vstup 4. představení DětskéhoTřesku - dítě',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'child',	4,	'2026-05-01 02:05:27'),
(16,	'Vstup 4. představení DětskéhoTřesku - dospělý',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'adult',	4,	'2026-05-01 02:05:27'),
(17,	'Vstup 4. představení DětskéhoTřesku - rodina',	NULL,	NULL,	NULL,	NULL,	150.00,	NULL,	1,	'family',	4,	'2026-05-01 02:05:27'),
(18,	'Vstup 5. představení DětskéhoTřesku - dítě',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'child',	5,	'2026-05-01 02:05:27'),
(19,	'Vstup 5. představení DětskéhoTřesku - dospělý',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'adult',	5,	'2026-05-01 02:05:27'),
(20,	'Vstup 5. představení DětskéhoTřesku - rodina',	NULL,	NULL,	NULL,	NULL,	150.00,	NULL,	1,	'family',	5,	'2026-05-01 02:05:27'),
(21,	'Vstup 6. představení DětskéhoTřesku - dítě',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'child',	6,	'2026-05-01 02:05:27'),
(22,	'Vstup 6. představení DětskéhoTřesku - dospělý',	NULL,	NULL,	NULL,	NULL,	50.00,	NULL,	1,	'adult',	6,	'2026-05-01 02:05:27'),
(23,	'Vstup 6. představení DětskéhoTřesku - rodina',	NULL,	NULL,	NULL,	NULL,	150.00,	NULL,	1,	'family',	6,	'2026-05-01 02:05:27');
