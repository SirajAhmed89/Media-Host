-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS mediahost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mediahost;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    storage_used BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB;

-- Media files table
CREATE TABLE media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('image', 'video') NOT NULL,
    file_size BIGINT NOT NULL,
    description TEXT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_upload (user_id, upload_date),
    INDEX idx_filename (file_name)
) ENGINE=InnoDB;

-- Authentication tokens table (for "Remember Me" functionality)
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expiry (expires_at)
) ENGINE=InnoDB;

-- Create cleanup event for expired tokens (runs daily)
DELIMITER //
CREATE EVENT cleanup_expired_tokens
ON SCHEDULE EVERY 1 DAY
DO
BEGIN
    DELETE FROM auth_tokens WHERE expires_at < NOW();
END//
DELIMITER ;

-- Create trigger to update users.updated_at
DELIMITER //
CREATE TRIGGER update_user_timestamp
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//
DELIMITER ;

-- Insert default indexes
ALTER TABLE users ADD FULLTEXT INDEX ft_username_email (username, email);
ALTER TABLE media ADD FULLTEXT INDEX ft_filename_description (file_name, description);

-- Create stored procedure for user storage calculation
DELIMITER //
CREATE PROCEDURE calculate_user_storage(IN user_id_param INT)
BEGIN
    UPDATE users u
    SET storage_used = (
        SELECT COALESCE(SUM(file_size), 0)
        FROM media
        WHERE user_id = user_id_param
    )
    WHERE id = user_id_param;
END//
DELIMITER ;

-- Grant necessary permissions (adjust according to your hosting environment)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON mediahost.* TO 'hostinger_db_user'@'localhost';
-- FLUSH PRIVILEGES; 