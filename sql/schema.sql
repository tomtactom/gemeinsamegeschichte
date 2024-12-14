-- sql/schema.sql

CREATE TABLE IF NOT EXISTS stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    participation_password_hash VARCHAR(255) NOT NULL,
    admin_password_hash VARCHAR(255) NOT NULL,
    is_private BOOLEAN DEFAULT FALSE,
    status ENUM('ongoing', 'completed', 'locked') DEFAULT 'ongoing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sentences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    sentence TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
);
