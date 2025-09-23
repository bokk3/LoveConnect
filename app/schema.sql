-- Database schema for login system
-- MariaDB compatible SQL

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS login_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE login_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
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
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;

-- Seed data: Create admin user with password 'admin123'
-- Password hash generated with PHP: password_hash('admin123', PASSWORD_ARGON2ID)
INSERT INTO users (username, password_hash) VALUES 
('admin', '$argon2id$v=19$m=65536,t=4,p=1$Y3JTMmF3d1Ayc2UxTEoyWA$ikRK1u5836OdGPEKiVw5GrynmIKDJ8qLhaf8082457I')
ON DUPLICATE KEY UPDATE username=username;

-- Clean up expired sessions (older than 30 minutes)
-- This should be run periodically by a cron job
-- DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE);