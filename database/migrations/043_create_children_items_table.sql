-- Migration: Create children_items table for children's program (DětskýTřesk)
-- Created: 2026-04-29

CREATE TABLE IF NOT EXISTS `children_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `performer` VARCHAR(255) NULL,
    `date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `runtime` varchar(80) NULL,
    `location` VARCHAR(255) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `bio_name` VARCHAR(255) NULL,
    `bio_description` TEXT NULL,
    `bio_web_link` VARCHAR(512) NULL,
    `bio_facebook_link` VARCHAR(512) NULL,
    `bio_instagram_link` VARCHAR(512) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date_time` (`date`, `start_time`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



INSERT INTO `children_items` (`id`, `title`, `description`, `performer`, `date`, `start_time`, `runtime`, `location`, `is_active`, `bio_name`, `bio_description`, `bio_web_link`, `bio_facebook_link`, `bio_instagram_link`, `created_at`, `updated_at`) VALUES
(1,	'Představení Dopisy Smolíčkovi',	'Interaktivní představení vypráví o klukovi, který tráví prázdniny v lesní chaloupce u Jelena. Sám nikomu nesmí otevírat dveře, jen výjimečně pošťákovi, protože mu hodně zaměstnaní rodiče posílají dopisy ze svých pracovních cest. I přesto se k němu lstivé lesní víly Jeskyňky jednoho dne, kdy se Jelen vzdálí, vloudí a Smolíčka odloudí... <br>\r\n S sebou: <em>olšovou šišku nebo jinou malou šištičku/y</em>',	'Hanka Strejčková',	'2026-05-08',	'10:00:00',	'13:00:00',	NULL,	1,	NULL,	NULL,	NULL,	NULL,	NULL,	'2026-04-29 23:08:58',	'2026-04-29 23:47:09'),
(2,	'Pohádkový Jukebox a Škola žonglování',	'',	'',	'2026-05-08',	'14:30:00',	'17:30:00',	NULL,	1,	NULL,	'',	NULL,	NULL,	NULL,	'2026-04-29 23:08:58',	'2026-04-29 23:38:47'),
(3,	'Divadlo',	'',	'Divadlo Buřt',	'2026-05-09',	'14:30:00',	'17:30:00',	NULL,	1,	NULL,	'',	NULL,	NULL,	NULL,	'2026-04-29 23:08:58',	'2026-04-29 23:44:32'),
(4,	'Divadlo',	'',	'Divadlo Buřt',	'2026-05-09',	'10:00:00',	'13:00:00',	NULL,	1,	NULL,	'',	NULL,	NULL,	NULL,	'2026-04-29 23:08:58',	'2026-04-29 23:44:32'),
(5,	'Divadlo Bylo jedno rádio',	'Uvnitř jednoho starého rádia to žije!<br>\r\nKaždý den svobodně vysílá hudbu, zprávy i drobné příběhy pro celý svět.<br>\r\nStačí ale malá změna, známé hlasy začnou mizet a ve vysílání je slyšet jen nepříjemný šum.<br>\r\nAutorská loutková inscenace o odvaze se ozvat. A to hlavně ve chvílích, když kolem nás začíná být ticho.<br>\r\nPro děti od 5 let a jejich dospělé, kteří nezapomněli poslouchat a naslouchat. <br>',	'Divadlo Žumpa',	'2026-05-10',	'10:00:00',	'13:00:00',	NULL,	1,	NULL,	'',	'https://www.divadlozumpa.cz/hrali-jsme/bylo-jedno-radio/ ',	NULL,	NULL,	'2026-04-29 23:08:58',	'2026-04-29 23:44:24'),
(6,	'Divadlo',	'',	'Míša',	'2026-05-10',	'14:30:00',	'17:30:00',	NULL,	1,	NULL,	'',	NULL,	NULL,	NULL,	'2026-04-29 23:08:58',	'2026-04-29 23:41:23');
-- 2026-04-29 21:47:25 UTC
