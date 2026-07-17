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

SHOW INDEX FROM attendance_events;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id BIGINT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user_created (
        user_id,
        created_at
    ),

    INDEX idx_audit_created (
        created_at
    ),

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
);

INSERT INTO audit_logs
(
    user_id,
    action,
    target_type,
    target_id,
    ip_address
)
VALUES
(
    NULL,
    'TEST_AUDIT_LOG',
    'system',
    NULL,
    '127.0.0.1'
);