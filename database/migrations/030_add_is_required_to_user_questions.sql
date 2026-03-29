ALTER TABLE user_questions
    ADD COLUMN is_required TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
