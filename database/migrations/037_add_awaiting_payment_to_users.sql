ALTER TABLE `users`
    ADD COLUMN `awaiting_payment` DECIMAL(10, 2) NOT NULL DEFAULT 0.00;
