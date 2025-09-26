-- PostgreSQL Schema for LoveConnect Dating App
-- Adapted from MySQL schema

-- Drop existing tables if they exist
DROP TABLE IF EXISTS matches CASCADE;
DROP TABLE IF EXISTS sessions CASCADE; 
DROP TABLE IF EXISTS users CASCADE;

-- Create ENUM types for PostgreSQL
CREATE TYPE gender_type AS ENUM ('male', 'female', 'non-binary', 'prefer-not-to-say');

-- Enhanced users table for dating app
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Dating app specific fields
    gender gender_type NOT NULL DEFAULT 'prefer-not-to-say',
    bio TEXT DEFAULT NULL,
    interests JSONB DEFAULT NULL,
    age INTEGER DEFAULT NULL CHECK (age >= 18 AND age <= 120),
    location VARCHAR(100) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    
    -- User preferences for matching
    looking_for JSONB DEFAULT NULL, -- ['male', 'female', 'non-binary']
    age_min INTEGER DEFAULT 18 CHECK (age_min >= 18),
    age_max INTEGER DEFAULT 100 CHECK (age_max <= 120),
    max_distance INTEGER DEFAULT 50, -- km
    
    -- Account status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX idx_users_gender ON users(gender);
CREATE INDEX idx_users_age ON users(age);
CREATE INDEX idx_users_location ON users(location);
CREATE INDEX idx_users_active ON users(is_active, last_active);

-- Sessions table for secure authentication
CREATE TABLE sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address INET, -- PostgreSQL native IP type
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL '30 days'),
    is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX idx_sessions_session_id ON sessions(session_id);
CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);

-- Matches/Relationships table
CREATE TABLE matches (
    id SERIAL PRIMARY KEY,
    user1_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    user2_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected', 'blocked')),
    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Ensure no duplicate matches and no self-matches
    UNIQUE(user1_id, user2_id),
    CHECK (user1_id != user2_id)
);

CREATE INDEX idx_matches_user1 ON matches(user1_id);
CREATE INDEX idx_matches_user2 ON matches(user2_id);
CREATE INDEX idx_matches_status ON matches(status);

-- Insert demo users with PostgreSQL-compatible data
INSERT INTO users (username, email, password_hash, gender, bio, interests, age, location, looking_for, is_active, is_verified) VALUES 
('admin', 'admin@datingapp.com', '$argon2id$v=19$m=65536,t=4,p=1$YWRtaW5fc2FsdA$X8Z6QkJ6k5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5', 'prefer-not-to-say', 'Administrator account for managing the dating platform.', '["admin", "management"]'::jsonb, 30, 'San Francisco, CA', '[]'::jsonb, TRUE, TRUE),

('alex_tech', 'alex@example.com', '$argon2id$v=19$m=65536,t=4,p=1$Nlkwd2RCTzVyVFk3aWE3aw$1WfT61QJ3Ql4Zxr1azS7u7E3eoCE3KXttSBLElqlnz4', 'male', 'Software engineer who loves hiking, craft beer, and weekend adventures! Looking for someone who enjoys both Netflix nights and outdoor activities.', '["technology", "hiking", "beer", "travel", "movies", "fitness"]'::jsonb, 28, 'San Francisco, CA', '["female", "non-binary"]'::jsonb, TRUE, TRUE),

('sarah_artist', 'sarah@example.com', '$argon2id$v=19$m=65536,t=4,p=1$c2FyYWhfc2FsdA$Y8Z6QkJ6k5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5', 'female', 'Creative soul and digital artist. I paint emotions and code dreams. Looking for someone who appreciates art, deep conversations, and spontaneous museum visits.', '["art", "painting", "photography", "museums", "coffee", "indie music"]'::jsonb, 26, 'Brooklyn, NY', '["male", "female", "non-binary"]'::jsonb, TRUE, TRUE),

('mike_chef', 'mike@example.com', '$argon2id$v=19$m=65536,t=4,p=1$bWlrZV9zYWx0$Z8Z6QkJ6k5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5', 'male', 'Professional chef who believes food is love made visible. When I''m not in the kitchen, you''ll find me at farmers markets or trying new restaurants.', '["cooking", "food", "wine", "travel", "farmers markets", "restaurants"]'::jsonb, 32, 'Austin, TX', '["female"]'::jsonb, TRUE, TRUE),

('emma_doctor', 'emma@example.com', '$argon2id$v=19$m=65536,t=4,p=1$ZW1tYV9zYWx0$A8Z6QkJ6k5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5', 'female', 'ER doctor with a passion for saving lives and exploring life. Love rock climbing, reading medical journals, and binge-watching documentaries.', '["medicine", "rock climbing", "documentaries", "reading", "hiking", "science"]'::jsonb, 29, 'Denver, CO', '["male", "non-binary"]'::jsonb, TRUE, TRUE),

('jordan_nb', 'jordan@example.com', '$argon2id$v=19$m=65536,t=4,p=1$am9yZGFuX3NhbHQ$B8Z6QkJ6k5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5Z5', 'non-binary', 'UX designer and part-time philosopher. I design experiences that matter and ask questions that make you think. Let''s explore the city and discuss the meaning of life.', '["design", "philosophy", "coffee", "cycling", "books", "sustainability"]'::jsonb, 27, 'Portland, OR', '["female", "non-binary", "male"]'::jsonb, TRUE, TRUE);

-- Demo passwords (for development only):
-- admin: admin123
-- alex_tech: editor123  
-- sarah_artist: user123
-- mike_chef: chef123
-- emma_doctor: doctor123  
-- jordan_nb: designer123

-- Update trigger for updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Clean up expired sessions periodically (you can run this via cron)
-- DELETE FROM sessions WHERE expires_at < NOW();