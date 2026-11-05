-- Migration: Merch – ruční pořadí ve výpisu
-- Created: 2026-11-04
--
-- order – primární klíč řazení na stránce /merch (vzestupně), sekundárně název.
--         U kapitoly se bere `order` rodiče, varianty uvnitř se řadí podle vlastního `order`.
--         Pozor: ORDER je rezervované slovo – v SQL vždy psát v backtickách.

ALTER TABLE `merch`
    ADD COLUMN `order` INT NOT NULL DEFAULT 0 AFTER `name`;
