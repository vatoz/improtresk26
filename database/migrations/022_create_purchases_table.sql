-- Purchases: links users to tickets or merch items they wish to buy / have bought

CREATE TABLE purchases (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED   NOT NULL,
    item_type      ENUM('ticket','merch') NOT NULL           COMMENT 'Which catalogue table the item belongs to',
    item_id        INT UNSIGNED   NOT NULL                   COMMENT 'FK into tickets.id or merch.id',
    quantity       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    payment_status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    note           TEXT           NULL                       COMMENT 'Optional user note (size, colour, etc.)',
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_purchases_user   (user_id),
    INDEX idx_purchases_item   (item_type, item_id),
    INDEX idx_purchases_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
