ALTER TABLE user_questions
    ADD COLUMN in_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER ticket_id;
