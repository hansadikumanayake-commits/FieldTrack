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

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL,

    UNIQUE INDEX idx_users_username (
        username
    ),

    INDEX idx_users_role_name (
        role,
        name
    )
);
ALTER TABLE attendance_events
ADD INDEX idx_attendance_user_created_id
(user_id, created_at, id);

ALTER TABLE attendance_events
ADD INDEX idx_attendance_created_id
(created_at, id);

ALTER TABLE attendance_events
ADD INDEX idx_attendance_action_created
(action_type, created_at);

ALTER TABLE users
ADD INDEX idx_users_role_name
(role, name);