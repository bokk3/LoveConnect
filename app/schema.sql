-- Database schema for login system
-- MariaDB compatible SQL

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS login_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE login_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Sessions table
CREATE TABLE IF NOT EXISTS sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    INDEX idx_user_session (user_id, session_id)
) ENGINE=InnoDB;

-- Seed data: Create multiple users with different roles
-- Passwords: admin123, editor123, user123
INSERT INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$VUIucTRLcjBxb1VnREhZeg$bcEhxvnLj/xtpF/GBzqVq1YihU0isVa52WrKzbzxK9Y', 'admin'),
('editor', 'editor@example.com', '$argon2id$v=19$m=65536,t=4,p=1$Nlkwd2RCTzVyVFk3aWE3aw$1WfT61QJ3Ql4Zxr1azS7u7E3eoCE3KXttSBLElqlnz4', 'editor'),
('user', 'user@example.com', '$argon2id$v=19$m=65536,t=4,p=1$UjlERS5sb04vS05zWGRQdw$pUa+tjJChnoUaXrmZ5qbHonitxhUMx0rLvEUSNjOsZk', 'user')
ON DUPLICATE KEY UPDATE username=username;

-- Clean up expired sessions (older than 30 minutes)
-- This should be run periodically by a cron job
-- DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE);