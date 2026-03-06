-- Merch: physical items available for sale (t-shirts, stickers, etc.)

CREATE TABLE merch (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255)   NOT NULL                    COMMENT 'Item name',
    description TEXT           NULL                        COMMENT 'Detailed description / variants',
    price       DECIMAL(10,2)  NOT NULL DEFAULT 0          COMMENT 'Price in CZK',
    is_active   TINYINT(1)     NOT NULL DEFAULT 1,
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
