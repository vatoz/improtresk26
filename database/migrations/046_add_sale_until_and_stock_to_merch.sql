-- Migration: Merch – datum konce prodeje a omezený počet kusů
-- Created: 2026-11-01
--
-- sale_until      – poslední den, kdy lze merch objednat (včetně)
-- stock_total     – celkový počet kusů; NULL = neomezeno
-- stock_available – počet volných kusů = stock_total − objednané kusy (pending + paid);
--                   přepočítává se v App\Models\Merch::recountStock(), NULL = neomezeno

ALTER TABLE `merch`
    ADD COLUMN `sale_until`      DATE         NOT NULL DEFAULT '2027-04-25' AFTER `is_active`,
    ADD COLUMN `stock_total`     INT UNSIGNED NULL     DEFAULT NULL         AFTER `sale_until`,
    ADD COLUMN `stock_available` INT          NULL     DEFAULT NULL         AFTER `stock_total`;
