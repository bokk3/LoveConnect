-- Mobile-First Dating App Database Schema
-- PHP 8.2 + MariaDB with complete dating functionality

-- Drop existing tables if they exist (for clean rebuild)
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;

-- Enhanced users table for dating app
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Dating app specific fields
    gender ENUM('male', 'female', 'non-binary', 'prefer-not-to-say') NOT NULL DEFAULT 'prefer-not-to-say',
    bio TEXT DEFAULT NULL,
    interests JSON DEFAULT NULL,
    age INT UNSIGNED DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    
    -- User preferences for matching
    looking_for JSON DEFAULT NULL, -- ['male', 'female', 'non-binary']
    age_min INT UNSIGNED DEFAULT 18,
    age_max INT UNSIGNED DEFAULT 100,
    max_distance INT UNSIGNED DEFAULT 50, -- km
    
    -- Account status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_gender (gender),
    INDEX idx_age (age),
    INDEX idx_location (location),
    INDEX idx_active (is_active, last_active)
);

-- Sessions table for secure authentication
CREATE TABLE sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45), -- Support IPv6
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_user_sessions (user_id, last_activity),
    INDEX idx_cleanup (expires_at)
);

-- Matches table for dating functionality
CREATE TABLE matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    matched_user_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'liked', 'passed', 'matched', 'unmatched') NOT NULL DEFAULT 'pending',
    
    -- Match details
    match_score DECIMAL(3,2) DEFAULT NULL, -- 0.00 to 1.00
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Prevent duplicate matches and self-matching
    UNIQUE KEY unique_match (user_id, matched_user_id),
    CHECK (user_id != matched_user_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_matches (user_id, status),
    INDEX idx_matched_user (matched_user_id, status),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- Messages table for matched users (future enhancement)
CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_match_messages (match_id, created_at),
    INDEX idx_recipient_unread (recipient_id, is_read, created_at)
);

-- User reports table for safety (future enhancement)
CREATE TABLE user_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT UNSIGNED NOT NULL,
    reported_id INT UNSIGNED NOT NULL,
    reason ENUM('inappropriate', 'fake', 'harassment', 'spam', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_reported_user (reported_id, status),
    INDEX idx_status (status, created_at)
);

-- Seed data: Create diverse dating profiles
INSERT INTO users (username, email, password_hash, gender, bio, interests, age, location, looking_for, is_active, is_verified) VALUES 

-- Admin user
('admin', 'admin@datingapp.com', '$argon2id$v=19$m=65536,t=4,p=1$VUIucTRLcjBxb1VnREhZeg$bcEhxvnLj/xtpF/GBzqVq1YihU0isVa52WrKzbzxK9Y', 'prefer-not-to-say', 'System administrator account', '["technology", "management"]', 30, 'System', '[]', TRUE, TRUE),

-- Demo dating profiles
('alex_tech', 'alex@example.com', '$argon2id$v=19$m=65536,t=4,p=1$Nlkwd2RCTzVyVFk3aWE3aw$1WfT61QJ3Ql4Zxr1azS7u7E3eoCE3KXttSBLElqlnz4', 'male', 'Software engineer who loves hiking, craft beer, and weekend adventures! Looking for someone who enjoys both Netflix nights and outdoor activities.', '["technology", "hiking", "beer", "travel", "movies", "fitness"]', 28, 'San Francisco, CA', '["female", "non-binary"]', TRUE, TRUE),

('sarah_artist', 'sarah@example.com', '$argon2id$v=19$m=65536,t=4,p=1$UjlERS5sb04vS05zWGRQdw$pUa+tjJChnoUaXrmZ5qbHonitxhUMx0rLvEUSNjOsZk', 'female', 'Artist and yoga instructor seeking genuine connections. Love painting, meditation, farmers markets, and deep conversations over coffee.', '["art", "yoga", "meditation", "coffee", "nature", "photography"]', 26, 'Portland, OR', '["male", "female"]', TRUE, TRUE),

('mike_chef', 'mike@example.com', '$argon2id$v=19$m=65536,t=4,p=1$QzVoR1FkNWpVcGRyVGE4bQ$sP9kVw2M8nQ4xL7jR5tY3mC8nF1oK6pW9qE4vZ2hB7A', 'male', 'Chef by day, guitarist by night! I can cook you the perfect pasta and serenade you afterward. Looking for a partner in culinary crime!', '["cooking", "music", "guitar", "food", "wine", "concerts"]', 32, 'Austin, TX', '["female"]', TRUE, TRUE),

('emma_doctor', 'emma@example.com', '$argon2id$v=19$m=65536,t=4,p=1$VGJmc3R5UWxOcDJyVWFudA$hN7wY8mP3qR9tK5xL2vF4oS1nE6jW9cM8bH4gV7aZ3D', 'female', 'Emergency room doctor who believes in work-life balance. Love rock climbing, reading sci-fi, and trying new restaurants. Seeking someone ambitious and kind!', '["medicine", "climbing", "books", "science", "travel", "food"]', 29, 'Seattle, WA', '["male", "non-binary"]', TRUE, TRUE),

('jordan_nb', 'jordan@example.com', '$argon2id$v=19$m=65536,t=4,p=1$T3B1RnJkWm1QeVRrVGRzcw$mK8qL4wN9eP2xR6yT5vC3hB7nM1oF8jS4gD6aZ9rH5E', 'non-binary', 'Non-binary graphic designer with a passion for sustainability and social justice. Board game enthusiast and amateur baker. Looking for thoughtful connections!', '["design", "sustainability", "gaming", "baking", "activism", "art"]', 27, 'Denver, CO', '["male", "female", "non-binary"]', TRUE, TRUE);

-- Create some sample matches to demonstrate functionality
INSERT INTO matches (user_id, matched_user_id, status, match_score) VALUES 
-- Alex liked Sarah
(2, 3, 'liked', 0.87),
-- Sarah liked Alex back (creates a match)
(3, 2, 'liked', 0.87),
-- Mike liked Emma
(4, 5, 'liked', 0.76),
-- Emma passed on Mike
(5, 4, 'passed', 0.76),
-- Jordan liked Alex
(6, 2, 'liked', 0.82),
-- Sarah liked Jordan
(3, 6, 'liked', 0.79),
-- Jordan liked Sarah back
(6, 3, 'liked', 0.79);

-- Create some sample sessions (these will be managed by the app)
-- Sessions are created dynamically during login

-- Sample messages for matched users
INSERT INTO messages (match_id, sender_id, recipient_id, message) VALUES 
(1, 2, 3, 'Hey Sarah! Love your art - that landscape painting is amazing!'),
(1, 3, 2, 'Thank you Alex! I saw you''re into hiking too. Have you been to the trails near Mount Tam?'),
(1, 2, 3, 'Yes! Actually planning to go this weekend. Would you like to join?');

-- Cleanup procedure for expired sessions (to be run periodically)
DELIMITER //
CREATE PROCEDURE CleanupExpiredSessions()
BEGIN
    DELETE FROM sessions WHERE expires_at < NOW();
END //
DELIMITER ;

-- Indexes for optimal matching queries
CREATE INDEX idx_user_matching ON users(gender, age, location, is_active);
CREATE INDEX idx_match_recommendations ON matches(user_id, status, updated_at);

-- Views for common queries
CREATE VIEW active_users AS 
SELECT id, username, gender, age, bio, interests, location, last_active
FROM users 
WHERE is_active = TRUE AND last_active > DATE_SUB(NOW(), INTERVAL 30 DAY);

CREATE VIEW mutual_matches AS
SELECT 
    m1.user_id as user1_id,
    m1.matched_user_id as user2_id,
    m1.created_at as match_date,
    u1.username as user1_name,
    u2.username as user2_name
FROM matches m1
JOIN matches m2 ON m1.user_id = m2.matched_user_id AND m1.matched_user_id = m2.user_id
JOIN users u1 ON m1.user_id = u1.id
JOIN users u2 ON m1.matched_user_id = u2.id
WHERE m1.status = 'liked' AND m2.status = 'liked';

-- Demo passwords (for development only):
-- admin: admin123
-- alex_tech: editor123  
-- sarah_artist: user123
-- mike_chef: chef123
-- emma_doctor: doctor123  
-- jordan_nb: designer123
-- DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE);