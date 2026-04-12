-- Migration: Create static_blocks table for named content blocks
-- Created: 2026-04-12

CREATE TABLE IF NOT EXISTS `static_blocks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `block_name` VARCHAR(100) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `block_description` TEXT NULL,
    `content` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    INDEX `idx_block_name` (`block_name`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET NAMES utf8mb4;

INSERT INTO `static_blocks` (`id`, `block_name`, `title`, `block_description`, `content`, `is_active`) VALUES
(1,	'contact_org',	'Organizátor',	'pod logem improligy',	'<p>Česká improvizační liga z.s.<br>\r\nWeb: <a href=\"https:\\\\improliga.cz\">improliga.cz</a><br>\r\nFB: <a href=\"https:\\\\improliga.cz\">improliga.cz</a><br>\r\nFestivalový email: <a href=\"mailto:improtresk@improlab.art\">improtresk@improlab.art</a><br>\r\nProgram: +420 723 538 317<br>\r\nWeb+registrace: +420 604 209 354\r\n</p>',	1),
(2,	'contact_zizkostel',	'Žižkostel',	NULL,	'Web: <a href=\"https://komunitnikostel.cz/\" target=\"_blank\">komunitnikostel.cz</a>',	1),
(3,	'contact_fostra',	'Fostra',	NULL,	'Soukromé gymnázium Fostra v Praze 3 Žižkov a Praze 5 Anděl<br>\r\n                        Web:<a href=\"https://www.fostra.cz\">www.fostra.cz</a>',	1),
(4,	'contact_sponsor1',	'Sponzor 1',	'Až tři bloky sponzorů',	'dfklsjflksdjflůksdkf',	0),
(5,	'contact_org3',	'Garantstvo',	'zobrazují se tři bloky po spoluorzích',	'<ul>\r\n<li>Veronika Maxová</li>\r\n<li>Veronika Raubicová</li>\r\n<li></li>\r\n<li></li>\r\n</ul>\r\n',	0),
(6,	'info_5',	'Další blok',	'bloky 4-7 před mapou',	'fdsjkhfkjdshfjkhsdkfj',	0),
(7,	'info_1',	'Místo konání večerního programu',	'nadpis se bere systémově',	'<p> Žižkostel<br>\r\n náměstí Barikád 1520/1<br>  Praha 3 - Žižkov, Česko</p>',	1),
(8,	'info_2',	'místa konání wsp',	'nadpis systémový',	'<p> Žižkostel<br>\r\nnáměstí Barikád 1520/1<br>  Praha 3 - Žižkov, Česko</p>\r\n\r\n<p> <a href=\"https:\\\\fostra.cz\">Gymnázium Fostra</a><br>\r\nRoháčova 1148/63<br>  Praha 3 - Žižkov, Česko</p>',	1),
(9,	'info_3',	'info 3 ubytko',	'nadpis systémový',	'<p>I v Praze bude možné využít ubytování ve společném prostoru nedaleko Žižkostela. A to\r\n                 v <a href=\"https://4pvs.nipax.cz/kontakt\">Skautské klubovně vodních skautů</a> na adrese Pitterova 2892/1, za budovou SUDOP, \r\n                 130 00 - Praha 3 (20 min pěšky přes park Parukářka nebo cca 10 min MHD ze zastavky Černínova do Olšanská) za 500 Kč na celou dobu festivalu (čtvrtek - neděle). Jedná se o možnost pro nenáročné účastníky. \r\n                 Počítejte prosím s tím, že bude potřeba vzít si s sebou vlastní karimatku nebo nafukovací matraci a taky spacák.\r\n                 <br>\r\n                 Kapacita je 30 lidí. \r\n</p><p>\r\n\r\nDruhou možností je sehnat si střechu nad hlavou na vlastní pěst buď u známého Pražáka či Pražačky, nebo v jednom z mnoha ubytovacích zařízení v Praze. \r\n\r\n </p>',	1),
(10,	'info_9',	'Historie',	'bloky 8,9,10,11,12 jsou po mapě',	'<p>Improtřesk odstartoval v roce 2011 jako oslava 10 let Pralin a Improligy  – a  to  v SVČ Lužánky v Brně.\r\nV roce 2012 si v Brně podržel domov, 2013 přišel s podtitulem Divadlo do improligy, improliga do divadla. Organizace se ujala skupina Bafni, která festival vedla i v roce 2014 s mottem Get inspired.\r\nOd roku 2015 se Improtřesk přestěhoval do Milevska a do rukou České improvizační ligy a přátel. Čtyři ročníky v Kulturním domě Milevsko přinesly postupně témata Sladíme se (2015), Na stejné vlnové délce (2016), Na vlastní kůži (2017) a Otevřeno nonstop (2018).\r\n</p><p>Rok 2019 znamenal další přesun – tentokrát do Ostravy, kde organizaci převzalo Divadlo Odvaz. V pandemickém roku 2020  se festival bohužel na poslední chvíli zrušil, ale Ostrava se vrátila se ctí v letech 2023, 2024 a 2025.\r\nA co nás čeká v roce 2026? Improtřesk se přesouvá do Prahy a organizace se opět ujímá České improvizační liga.</p>\r\n',	1),
(11,	'info_4',	'Doprava',	'4-7 před mapou',	'<p>\r\n                <img src=\"/sysimg/bus.png\" style=\"height:20px;\"/>  Autobus: Černínova - 136,133,207 <br>\r\n                <img src=\"/sysimg/tram.png\" style=\"height:20px;\"/>Tram: Ohrada - 1 , 2,5,7,9,11,15,25,36<br>\r\n                S parkováním je  v okolí komplikované. Vytipovali jsme pár míst, kde by to snad mohlo jít, najdeš je v <a href=\"/doc/parking.pdf\">malém návodu</a>.                                \r\n                </p>',	1);
-- 2026-04-12 14:59:43 UTC
