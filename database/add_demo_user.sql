-- Add demo user for login
-- Email: admin@laptop.com
-- Password: admin123

-- Option 1: Insert new user (if email doesn't exist)
INSERT INTO `users` (`name`, `email`, `password`, `role`) 
VALUES (
    'Admin Laptop', 
    'admin@laptop.com', 
    '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', -- password: admin123
    'admin'
);

-- Option 2: Update existing admin user to use demo credentials
-- UPDATE `users` SET 
--     `email` = 'admin@laptop.com',
--     `password` = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy'
-- WHERE `id` = 1;

