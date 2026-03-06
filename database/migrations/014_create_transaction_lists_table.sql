-- Migration: Create transaction_lists table
-- Created: 2026-02-27

CREATE TABLE IF NOT EXISTS `transaction_lists` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `fio_id` VARCHAR(255) NOT NULL COMMENT 'ID pohybu (column_22)',
    `date` VARCHAR(50) NULL COMMENT 'Datum (column_0)',
    `amount` VARCHAR(50) NULL COMMENT 'Objem (column_1)',
    `currency` VARCHAR(10) NULL COMMENT 'Měna (column_14)',
    `counter_account` VARCHAR(255) NULL COMMENT 'Protiúčet (column_2)',
    `counter_account_name` VARCHAR(255) NULL COMMENT 'Název protiúčtu (column_10)',
    `bank_code` VARCHAR(50) NULL COMMENT 'Kód banky (column_3)',
    `bank_name` VARCHAR(255) NULL COMMENT 'Název banky (column_12)',
    `constant_symbol` VARCHAR(50) NULL COMMENT 'KS (column_4)',
    `variable_symbol` VARCHAR(50) NULL COMMENT 'VS (column_5)',
    `specific_symbol` VARCHAR(50) NULL COMMENT 'SS (column_6)',
    `user_identification` TEXT NULL COMMENT 'Uživatelská identifikace (column_7)',
    `message` TEXT NULL COMMENT 'Zpráva pro příjemce (column_16)',
    `type` VARCHAR(255) NULL COMMENT 'Typ (column_8)',
    `executor` VARCHAR(255) NULL COMMENT 'Provedl (column_9)',
    `account_name` VARCHAR(255) NULL COMMENT 'Název účtu (column_18)',
    `comment` TEXT NULL COMMENT 'Komentář (column_25)',
    `bic` VARCHAR(50) NULL COMMENT 'BIC (column_26)',
    `instruction_id` VARCHAR(255) NULL COMMENT 'ID pokynu (column_17)',
    `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_fio_id` (`fio_id`),
    INDEX `idx_variable_symbol` (`variable_symbol`),
    INDEX `idx_date` (`date`),
    INDEX `idx_imported_at` (`imported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
