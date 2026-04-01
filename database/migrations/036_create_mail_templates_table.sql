-- Mail templates for the admin send-mail page
CREATE TABLE IF NOT EXISTS `mail_templates` (
    `id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`    VARCHAR(255) NOT NULL COMMENT 'Short name shown as button label',
    `subject`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Pre-filled e-mail subject',
    `text`     TEXT         NOT NULL COMMENT 'HTML body of the e-mail',
    `is_valid` TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = hidden/disabled',
    `created_at` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
