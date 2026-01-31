-- Seed: Festival program items

INSERT INTO `program_items` (`title`, `description`, `performer`, `type`, `date`, `start_time`, `end_time`, `location`, `is_free`, `is_active`)
VALUES
    (
        'Slavnostní zahájení Improtřesku 2026',
        'Úvodní show s představením všech účinkujících a improvizované číslo na přivítanou.',
        'Organizátoři festivalu',
        'performance',
        '2026-05-07',
        '19:00:00',
        '20:30:00',
        'Hlavní sál',
        TRUE,
        TRUE
    ),
    (
        'Show - Improvizační liga',
        'Zápas dvou týmů v klasickém formátu improvizační ligy s rozhodčím a publikem jako porotou.',
        'Týmy Východ vs. Západ',
        'performance',
        '2026-05-07',
        '21:00:00',
        '22:30:00',
        'Hlavní sál',
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
        TRUE,
        TRUE
    ),
    (
        'Show - Long-form: Příběhy z ulice',
        'Dlouhý improvizovaný formát vycházející z příběhů diváků.',
        'Skupina Impro Praha',
        'performance',
        '2026-05-09',
        '20:00:00',
        '21:30:00',
        'Hlavní sál',
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
        FALSE,
        TRUE
    ),
    (
        'Závěrečná show - Best of Festival',
        'Výběr nejlepších scének a improvizací z celého festivalu.',
        'Všichni účinkující',
        'performance',
        '2026-05-10',
        '19:00:00',
        '21:00:00',
        'Hlavní sál',
        FALSE,
        TRUE
    )
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
