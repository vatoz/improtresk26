-- Tickets: evening / program events with a price
-- Each row represents one purchasable evening slot (e.g. "Páteční večer", "Sobotní gala")

CREATE TABLE tickets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255)   NOT NULL                    COMMENT 'Event name shown to users',
    description TEXT           NULL                        COMMENT 'Short programme description',
    date        DATE           NULL                        COMMENT 'Date of the evening event',
    time        TIME           NULL                        COMMENT 'Start time',
    price       DECIMAL(10,2)  NOT NULL DEFAULT 0          COMMENT 'Price in CZK',
    capacity    SMALLINT UNSIGNED NULL                     COMMENT 'Max tickets; NULL = unlimited',
    is_active   TINYINT(1)     NOT NULL DEFAULT 1,
    created_at  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
