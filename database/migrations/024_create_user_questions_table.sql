CREATE TABLE IF NOT EXISTS user_questions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question    TEXT NOT NULL,
    question_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type        ENUM('free_text','phone','yes_no') NOT NULL DEFAULT 'free_text',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    `order`     INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
