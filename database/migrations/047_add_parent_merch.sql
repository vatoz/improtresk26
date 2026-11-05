-- Migration: Merch – varianty téhož zboží (např. tričko v různých velikostech)
-- Created: 2026-11-04
--
-- parent_merch – id záznamu, pod který varianta patří (ten dává skupině název,
--                popis a fotku). NULL = samostatná položka nebo sám rodič.
--                Rodič může mít parent_merch = NULL i vlastní id – obojí funguje.
--                Každá varianta má vlastní cenu, sale_until, sklad i is_active.
--                Neaktivní rodič se neprodává, ale dál slouží jako hlavička skupiny.

ALTER TABLE `merch`
    ADD COLUMN `parent_merch` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_merch_parent` (`parent_merch`);
