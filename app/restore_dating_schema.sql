-- Restore Dating App Database Schema
USE login_system;

-- Add dating app specific columns to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) AFTER password_hash,
ADD COLUMN IF NOT EXISTS age INT AFTER full_name,
ADD COLUMN IF NOT EXISTS gender ENUM('male', 'female', 'non-binary', 'other') AFTER age,
ADD COLUMN IF NOT EXISTS interests JSON AFTER gender,
ADD COLUMN IF NOT EXISTS bio TEXT AFTER interests,
ADD COLUMN IF NOT EXISTS location VARCHAR(100) AFTER bio,
ADD COLUMN IF NOT EXISTS dating_preferences JSON AFTER location,
ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(255) AFTER dating_preferences;

-- Update existing users with dating app data
UPDATE users SET 
    full_name = CASE 
        WHEN username = 'admin' THEN 'Admin User'
        WHEN username = 'alex_tech' THEN 'Alex Thompson'
        WHEN username = 'sarah_artist' THEN 'Sarah Martinez'
        WHEN username = 'mike_chef' THEN 'Mike Johnson'
        WHEN username = 'emma_doctor' THEN 'Emma Chen'
        WHEN username = 'jordan_nb' THEN 'Jordan Rivera'
        WHEN username = 'rolex' THEN 'Rolex User'
        ELSE CONCAT(UPPER(LEFT(username, 1)), SUBSTRING(username, 2))
    END,
    age = CASE 
        WHEN username = 'admin' THEN 30
        WHEN username = 'alex_tech' THEN 28
        WHEN username = 'sarah_artist' THEN 26
        WHEN username = 'mike_chef' THEN 32
        WHEN username = 'emma_doctor' THEN 29
        WHEN username = 'jordan_nb' THEN 27
        WHEN username = 'rolex' THEN 25
        ELSE 25 + (id % 15)
    END,
    gender = CASE 
        WHEN username = 'admin' THEN 'other'
        WHEN username = 'alex_tech' THEN 'male'
        WHEN username = 'sarah_artist' THEN 'female'
        WHEN username = 'mike_chef' THEN 'male'
        WHEN username = 'emma_doctor' THEN 'female'
        WHEN username = 'jordan_nb' THEN 'non-binary'
        WHEN username = 'rolex' THEN 'male'
        ELSE CASE (id % 3) WHEN 0 THEN 'male' WHEN 1 THEN 'female' ELSE 'non-binary' END
    END,
    interests = CASE 
        WHEN username = 'alex_tech' THEN '["Technology", "Gaming", "Coffee", "Hiking", "Photography"]'
        WHEN username = 'sarah_artist' THEN '["Art", "Music", "Travel", "Yoga", "Wine Tasting"]'
        WHEN username = 'mike_chef' THEN '["Cooking", "Food", "Travel", "Music", "Sports"]'
        WHEN username = 'emma_doctor' THEN '["Medicine", "Reading", "Running", "Movies", "Volunteering"]'
        WHEN username = 'jordan_nb' THEN '["Writing", "Books", "Coffee", "Nature", "Movies"]'
        WHEN username = 'rolex' THEN '["Luxury", "Travel", "Fashion", "Cars", "Business"]'
        ELSE '["Music", "Movies", "Travel", "Food"]'
    END,
    bio = CASE 
        WHEN username = 'alex_tech' THEN 'Software developer who loves building cool apps and exploring the outdoors. Looking for someone to share adventures with! 🚀'
        WHEN username = 'sarah_artist' THEN 'Creative soul who finds beauty everywhere. Love painting, traveling, and meaningful conversations over wine. 🎨'
        WHEN username = 'mike_chef' THEN 'Professional chef with a passion for creating amazing dishes. Let me cook for you! 👨‍🍳'
        WHEN username = 'emma_doctor' THEN 'Doctor by day, bookworm by night. Love helping people and staying active. 💊📚'
        WHEN username = 'jordan_nb' THEN 'Writer and dreamer. Love deep conversations about life, books, and everything in between. ✍️'
        WHEN username = 'rolex' THEN 'Entrepreneur with a taste for the finer things. Love luxury travel and making memories. ⌚'
        ELSE 'Looking for meaningful connections and great conversations!'
    END,
    location = CASE 
        WHEN username = 'alex_tech' THEN 'San Francisco, CA'
        WHEN username = 'sarah_artist' THEN 'New York, NY'
        WHEN username = 'mike_chef' THEN 'Chicago, IL'
        WHEN username = 'emma_doctor' THEN 'Boston, MA'
        WHEN username = 'jordan_nb' THEN 'Portland, OR'
        WHEN username = 'rolex' THEN 'Miami, FL'
        ELSE 'Los Angeles, CA'
    END
WHERE full_name IS NULL OR full_name = '';

-- Clear and recreate matches table
DROP TABLE IF EXISTS matches;
CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    matched_user_id INT NOT NULL,
    status ENUM('liked', 'passed', 'blocked', 'matched') DEFAULT 'matched',
    match_score DECIMAL(3,2) DEFAULT 0.50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_match (user_id, status),
    INDEX idx_matched_user (matched_user_id, status)
);

-- Clear and recreate messages table
DROP TABLE IF EXISTS messages;
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_match_id (match_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_created_at (created_at)
);

-- Create sample mutual matches
INSERT INTO matches (user_id, matched_user_id, status, match_score, created_at) VALUES
-- Alex (2) and Sarah (3) - Match ID 1
(2, 3, 'matched', 0.87, NOW() - INTERVAL 3 DAY),
(3, 2, 'matched', 0.87, NOW() - INTERVAL 3 DAY),

-- Alex (2) and Emma (5) - Match ID 2
(2, 5, 'matched', 0.72, NOW() - INTERVAL 2 DAY),
(5, 2, 'matched', 0.72, NOW() - INTERVAL 2 DAY),

-- Sarah (3) and Mike (4) - Match ID 3
(3, 4, 'matched', 0.81, NOW() - INTERVAL 1 DAY),
(4, 3, 'matched', 0.81, NOW() - INTERVAL 1 DAY),

-- Mike (4) and Emma (5) - Match ID 4
(4, 5, 'matched', 0.69, NOW() - INTERVAL 4 HOUR),
(5, 4, 'matched', 0.69, NOW() - INTERVAL 4 HOUR),

-- Jordan (6) and Alex (2) - Match ID 5
(6, 2, 'matched', 0.78, NOW() - INTERVAL 6 HOUR),
(2, 6, 'matched', 0.78, NOW() - INTERVAL 6 HOUR),

-- Admin (1) and Rolex (7) - Match ID 6
(1, 7, 'matched', 0.65, NOW() - INTERVAL 5 DAY),
(7, 1, 'matched', 0.65, NOW() - INTERVAL 5 DAY);

-- Add sample messages with proper match_id assignments
INSERT INTO messages (match_id, sender_id, recipient_id, message, is_read, created_at) VALUES
-- Match 1: Alex (2) and Sarah (3)
(1, 2, 3, 'Hey Sarah! I love your art profile, very creative! 🎨', TRUE, NOW() - INTERVAL 2 HOUR),
(1, 3, 2, 'Thanks Alex! Your tech projects look fascinating. What are you working on?', TRUE, NOW() - INTERVAL 90 MINUTE),
(1, 2, 3, 'Building a cool dating app actually! Want to grab coffee and I can show you?', TRUE, NOW() - INTERVAL 1 HOUR),
(1, 3, 2, 'That sounds amazing! I would love to see it ☕', FALSE, NOW() - INTERVAL 30 MINUTE),

-- Match 2: Alex (2) and Emma (5)
(2, 2, 5, 'Hi Emma! Fellow coffee lover here ☕', TRUE, NOW() - INTERVAL 3 HOUR),
(2, 5, 2, 'Hey Alex! Yes, coffee is life! What is your favorite spot?', TRUE, NOW() - INTERVAL 2 HOUR),
(2, 2, 5, 'There is this great little cafe downtown, Blue Bottle. You?', FALSE, NOW() - INTERVAL 1 HOUR),

-- Match 3: Sarah (3) and Mike (4)
(3, 3, 4, 'Hey Mike! Your cooking photos made me hungry 😋', TRUE, NOW() - INTERVAL 4 HOUR),
(3, 4, 3, 'Haha thanks Sarah! I would love to cook for you sometime', TRUE, NOW() - INTERVAL 3 HOUR),
(3, 3, 4, 'That would be amazing! What is your signature dish?', FALSE, NOW() - INTERVAL 2 HOUR),

-- Match 4: Mike (4) and Emma (5)
(4, 4, 5, 'Hey Emma! Love that you are a doctor - very admirable!', TRUE, NOW() - INTERVAL 2 HOUR),
(4, 5, 4, 'Thank you Mike! Your culinary skills are impressive too', FALSE, NOW() - INTERVAL 1 HOUR),

-- Match 5: Jordan (6) and Alex (2)
(5, 6, 2, 'Hi Alex! Your profile caught my eye - love the tech passion', TRUE, NOW() - INTERVAL 3 HOUR),
(5, 2, 6, 'Hey Jordan! I read some of your writing, really thoughtful stuff', FALSE, NOW() - INTERVAL 2 HOUR),

-- Match 6: Admin (1) and Rolex (7)
(6, 1, 7, 'Welcome to LoveConnect!', TRUE, NOW() - INTERVAL 1 DAY),
(6, 7, 1, 'Thanks! Great platform you have built here', FALSE, NOW() - INTERVAL 20 HOUR);

-- Show completion message
SELECT 'Dating app database restored successfully!' as status;
