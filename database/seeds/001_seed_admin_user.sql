-- Seed: Create admin user
-- Password: admin123

INSERT INTO `users` (`name`, `email`, `password`, `role`, `created_at`)
VALUES
    ('Admin', 'admin@improtresk.cz', '$2y$10$nebGBXP2RQFBIv8rjJYKPODla0SdnDDOd.gchiXzWMTvGlEBWIpgC', 'admin', NOW()),
    ('Test User', 'user@improtresk.cz', '$2y$10$nebGBXP2RQFBIv8rjJYKPODla0SdnDDOd.gchiXzWMTvGlEBWIpgC', 'user', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Note: Default password for both users is: admin123
-- Generated with: password_hash('admin123', PASSWORD_DEFAULT)
