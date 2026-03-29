ALTER TABLE user_questions
    ADD COLUMN ticket_id INT UNSIGNED NULL DEFAULT NULL AFTER is_required,
    ADD CONSTRAINT fk_uq_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL;
