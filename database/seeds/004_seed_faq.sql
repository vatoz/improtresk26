-- Seed: FAQ items

INSERT INTO `faq` (`question`, `answer`, `category`, `order`, `is_active`)
VALUES
    (
        'Mohu čemukoli tady věřit?',
        'Ne, zatím je web ve vývoji.',
        'registrace',
        1,
        TRUE
    ),
    (
        'Jak se registrovat na festival?',
        'Registrace probíhá online přes náš web. Stačí si vytvořit účet, vybrat workshop a provést platbu.',
        'registrace',
        1,
        TRUE
    ),
    (
        'Mohu se zúčastnit bez registrace na workshop?',
        'Ano, vstup na večerní představení a doprovodný program je možný i bez registrace na workshop. Workshopy jsou placené, večerní program má sníženou cenu.',
        'registrace',
        2,
        TRUE
    ),
    (
        'Jaká je stornovací politika?',
        'Do 30 dnů před začátkem festivalu je možné stornovat s vratkou 80% ceny. Později již vratka není možná, můžete však přenechat místo někomu jinému.',
        'platba',
        3,
        TRUE
    ),
    (
        'Je nutná zkušenost s improvizací?',
        'Ne! Máme workshopy pro začátečníky i pokročilé. V popisu každého workshopu je uvedena náročnost.',
        'workshopy',
        4,
        TRUE
    ),
    (
        'Kde se festival koná?',
        'Festival se koná v Praze, v kulturním centru na Náměstí Pražanů. Přesná adresa a pokyny k dopravě najdete v sekci Informace.',
        'obecné',
        5,
        TRUE
    ),
    (
        'Mohu změnit vybraný workshop?',
        'Ano, změna je možná do 14 dnů před začátkem festivalu. Kontaktujte nás na info@improtresk.cz',
        'workshopy',
        6,
        TRUE
    ),
    (
        'Jsou workshopy v češtině?',
        'Většina workshopů je v češtině, některé mezinárodní workshopy probíhají v angličtině. Jazyk je vždy uveden v popisu workshopu.',
        'workshopy',
        7,
        TRUE
    ),
    (
        'Dostanu fakturu?',
        'Ano, po zaplacení vám automaticky zašleme daňový doklad na email.',
        'platba',
        8,
        TRUE
    )
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
