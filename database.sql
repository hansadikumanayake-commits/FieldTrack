CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL
);

CREATE TABLE attendance_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('IN', 'OUT') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    photo_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id)
);
INSERT INTO users (name, username, password, role) VALUES
('Admin User', 'admin', 'admin123', 'admin'),
('Field Officer', 'officer', 'officer123', 'user');

ALTER TABLE attendance_events
ADD INDEX idx_user_created (user_id, created_at),
ADD INDEX idx_created_at (created_at),
ADD INDEX idx_action_created (action_type, created_at);