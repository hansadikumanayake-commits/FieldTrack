CREATE TABLE attendance_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('IN', 'OUT') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_attendance_user_created_id (
        user_id,
        created_at,
        id
    ),

    INDEX idx_attendance_created_id (
        created_at,
        id
    ),

    INDEX idx_attendance_action_created (
        action_type,
        created_at
    ),

    CONSTRAINT fk_attendance_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);