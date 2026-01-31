-- Seed: Sample workshops

INSERT INTO `workshops` (`name`, `description`, `instructor`, `date`, `time`, `duration_minutes`, `price`, `capacity`, `location`, `level`, `is_active`)
VALUES
    (
        'Improvizace pro začátečníky',
        'Workshop pro ty, kteří s improvizací začínají. Naučíte se základní techniky, práci s partnerem a budování scény.',
        'Martin Dočkal',
        '2026-05-07',
        '10:00:00',
        180,
        1500.00,
        20,
        'Studio A',
        'beginner',
        TRUE
    ),
    (
        'Long-form improvizace',
        'Pokročilý workshop zaměřený na dlouhé improvizované formáty. Práce s příběhem, postavami a dramaturgií.',
        'Jana Nováková',
        '2026-05-08',
        '14:00:00',
        240,
        2000.00,
        15,
        'Studio B',
        'advanced',
        TRUE
    ),
    (
        'Improvizace a hudba',
        'Spojení improvizace s hudbou. Improvizované muzikály a hudební čísla.',
        'David Svoboda',
        '2026-05-09',
        '10:00:00',
        180,
        1800.00,
        18,
        'Hlavní sál',
        'intermediate',
        TRUE
    ),
    (
        'Status a hra',
        'Workshop zaměřený na práci se statusem postav a jeho využití v improvizaci.',
        'Dana Kardová',
        '2026-05-09',
        '15:00:00',
        120,
        1200.00,
        25,
        'Studio A',
        'all',
        TRUE
    ),
    (
        'Improvizační stand-up',
        'Kombinace improvizace a stand-up comedy. Práce s publikem a spontánní humor.',
        'Václav Černý',
        '2026-05-10',
        '11:00:00',
        150,
        1600.00,
        20,
        'Hlavní sál',
        'intermediate',
        TRUE
    )
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
