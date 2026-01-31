-- Seed: Create admin user
-- Password: admin123

INSERT INTO `users` (`name`, `email`, `password`, `role`, `created_at`)
VALUES
    ('Admin', 'admin@improtresk.cz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()),
    ('Test User', 'user@improtresk.cz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Note: Default password for both users is: password
-- Generated with: password_hash('password', PASSWORD_DEFAULT)
