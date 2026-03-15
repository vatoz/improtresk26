CREATE TABLE IF NOT EXISTS people (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    weblink VARCHAR(255) NULL,
    fblink VARCHAR(255) NULL,
    instagramlink VARCHAR(255) NULL,
    workshop_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    `order` INT NOT NULL DEFAULT 0
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
