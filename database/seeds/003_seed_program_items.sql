-- Seed: Festival program items

INSERT INTO `program_items` (`title`, `description`, `performer`, `type`, `date`, `start_time`, `end_time`, `location`, `track`, `is_free`, `is_active`)
VALUES
    -- Day 1: Opening (full-width) and concurrent evening shows
    (
        'Slavnostní zahájení Improtřesku 2026',
        'Úvodní show s představením všech účinkujících a improvizované číslo na přivítanou.',
        'Organizátoři festivalu',
        'performance',
        '2026-05-07',
        '19:00:00',
        '20:00:00',
        'Hlavní sál',
        NULL, -- Full-width item
        TRUE,
        TRUE
    ),
    (
        'Show - Improvizační liga',
        'Zápas dvou týmů v klasickém formátu improvizační ligy s rozhodčím a publikem jako porotou.',
        'Týmy Východ vs. Západ',
        'performance',
        '2026-05-07',
        '20:30:00',
        '22:00:00',
        'Hlavní sál',
        'A', -- Track A
        FALSE,
        TRUE
    ),
    (
        'Workshop - Postavy a improvizace',
        'Naučte se vytvářet nezapomenutelné postavy v improvizaci.',
        'Lektor Martin Novák',
        'workshop',
        '2026-05-07',
        '20:30:00',
        '22:00:00',
        'Malý sál',
        'B', -- Track B
        FALSE,
        TRUE
    ),
    -- Day 2: Concurrent workshops and evening program
    (
        'Workshop - Hra s objekty',
        'Jak efektivně pracovat s rekvizitami a imaginárními předměty.',
        'Lektorka Jana Svobodová',
        'workshop',
        '2026-05-08',
        '14:00:00',
        '16:00:00',
        'Hlavní sál',
        'A', -- Track A
        FALSE,
        TRUE
    ),
    (
        'Workshop - Hudba v improvizaci',
        'Jak využít hudbu a zpěv ve svých improvizacích.',
        'Lektor Petr Dvořák',
        'workshop',
        '2026-05-08',
        '14:00:00',
        '16:00:00',
        'Malý sál',
        'B', -- Track B
        FALSE,
        TRUE
    ),
    (
        'Večerní diskuse s lektory',
        'Neformální setkání s lektory workshopů. Možnost klást otázky a diskutovat o improvizaci.',
        'Všichni lektoři',
        'discussion',
        '2026-05-08',
        '20:00:00',
        '21:30:00',
        'Foyer',
        NULL, -- Full-width item
        TRUE,
        TRUE
    ),
    -- Day 3: Concurrent shows
    (
        'Show - Long-form: Příběhy z ulice',
        'Dlouhý improvizovaný formát vycházející z příběhů diváků.',
        'Skupina Impro Praha',
        'performance',
        '2026-05-09',
        '20:00:00',
        '21:30:00',
        'Hlavní sál',
        'A', -- Track A
        FALSE,
        TRUE
    ),
    (
        'Show - Short-form šílenství',
        'Rychlá série krátkých improvizačních her a scének.',
        'Skupina Impro Brno',
        'performance',
        '2026-05-09',
        '20:00:00',
        '21:30:00',
        'Malý sál',
        'B', -- Track B
        FALSE,
        TRUE
    ),
    (
        'Afterparty s živou hudbou',
        'Večírková improvizace s živou kapelou a otevřenou scénou pro všechny účastníky.',
        'DJ + improvizátoři',
        'party',
        '2026-05-09',
        '22:00:00',
        '02:00:00',
        'Klub',
        NULL, -- Full-width item
        FALSE,
        TRUE
    ),
    -- Day 4: Closing show (full-width)
    (
        'Závěrečná show - Best of Festival',
        'Výběr nejlepších scének a improvizací z celého festivalu.',
        'Všichni účinkující',
        'performance',
        '2026-05-10',
        '19:00:00',
        '21:00:00',
        'Hlavní sál',
        NULL, -- Full-width item
        FALSE,
        TRUE
    )
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
