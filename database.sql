CREATE DATABASE IF NOT EXISTS fieldtrack_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE fieldtrack_db;


SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS attendance_events;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    username VARCHAR(100) NOT NULL,

    password VARCHAR(255) NOT NULL,

    role ENUM('admin', 'user') NOT NULL
        DEFAULT 'user',

    UNIQUE INDEX idx_users_username (
        username
    ),

    INDEX idx_users_role_name (
        role,
        name
    )
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


CREATE TABLE attendance_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    action_type ENUM('IN', 'OUT') NOT NULL,

    latitude DECIMAL(10, 8) NOT NULL,

    longitude DECIMAL(11, 8) NOT NULL,

    photo_path VARCHAR(255) DEFAULT NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

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
        ON UPDATE CASCADE
        ON DELETE RESTRICT
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED DEFAULT NULL,

    action VARCHAR(100) NOT NULL,

    target_type VARCHAR(50) DEFAULT NULL,

    target_id BIGINT UNSIGNED DEFAULT NULL,

    ip_address VARCHAR(45) DEFAULT NULL,

    created_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user_created (
        user_id,
        created_at
    ),

    INDEX idx_audit_created_id (
        created_at,
        id
    ),

    INDEX idx_audit_action_created (
        action,
        created_at
    ),

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

INSERT INTO users (
    name,
    username,
    password,
    role
)
VALUES
(
    'Admin User',
    'admin',
    '$2y$12$pyJyk9OmEwv/FiNaRttmheIAMSiMwoFhnKx6zxIi5Lbaa3.blDVJe',
    'admin'
),
(
    'Field Officer',
    'officer',
    '$2y$12$vbaZOABUi7OIZMPisIpWYOyCSgegvkSAxLljNx35bEerBLdcCrZue',
    'user'
);


SHOW TABLES;

-- Run this once against your EXISTING database to remove the
-- photo_path column (skip this if setting up a brand new database,
-- since the CREATE TABLE below no longer includes it at all).
ALTER TABLE attendance_events
DROP COLUMN photo_path;

CREATE TABLE attendance_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('IN', 'OUT') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
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
-- Run this once against your EXISTING database to remove the
-- photo_path column (skip this if setting up a brand new database,
-- since the CREATE TABLE below no longer includes it at all).
ALTER TABLE attendance_events
DROP COLUMN photo_path;

CREATE TABLE attendance_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('IN', 'OUT') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
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

-- Make sure the password column can store secure password hashes
ALTER TABLE users
MODIFY password VARCHAR(255) NOT NULL;

-- Store the available system roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Store individual permissions
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Connect users to roles
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, role_id),

    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id)
        REFERENCES roles(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Connect roles to permissions
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (role_id, permission_id),

    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id)
        REFERENCES roles(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id)
        REFERENCES permissions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

-- Assign each Field Officer to an Admin Officer and Admin Manager
CREATE TABLE IF NOT EXISTS officer_assignments (
    field_officer_id INT PRIMARY KEY,
    admin_officer_id INT NOT NULL,
    admin_manager_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_assignment_field_officer
        FOREIGN KEY (field_officer_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assignment_admin_officer
        FOREIGN KEY (admin_officer_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assignment_admin_manager
        FOREIGN KEY (admin_manager_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (
    role_name,
    description
)
VALUES
(
    'field_officer',
    'Marks IN and OUT and submits weekly attendance'
),
(
    'admin_officer',
    'Reviews assigned Field Officer attendance'
),
(
    'admin_manager',
    'Provides final attendance approval or rejection'
),
(
    'system_admin',
    'Manages users, roles, permissions and assignments'
);

INSERT IGNORE INTO permissions (
    permission_name,
    description
)
VALUES
(
    'attendance.mark_in',
    'Mark IN attendance'
),
(
    'attendance.mark_out',
    'Mark OUT attendance'
),
(
    'attendance.view_own',
    'View personal attendance records'
),
(
    'weekly.submit',
    'Submit weekly attendance'
),
(
    'weekly.view_own',
    'View personal weekly submissions'
),
(
    'weekly.review_assigned',
    'Review assigned Field Officer submissions'
),
(
    'weekly.approve_level1',
    'Approve attendance at Admin Officer level'
),
(
    'weekly.reject_level1',
    'Reject attendance at Admin Officer level'
),
(
    'weekly.approve_final',
    'Provide final attendance approval'
),
(
    'weekly.reject_final',
    'Provide final attendance rejection'
),
(
    'users.manage',
    'Create and manage user accounts'
),
(
    'roles.manage',
    'Manage roles and permissions'
),
(
    'assignments.manage',
    'Assign officers to reviewers'
),
(
    'audit.view',
    'View system audit logs'
);
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    roles.id,
    permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.role_name = 'field_officer'
AND permissions.permission_name IN (
    'attendance.mark_in',
    'attendance.mark_out',
    'attendance.view_own',
    'weekly.submit',
    'weekly.view_own'
);
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    roles.id,
    permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.role_name = 'admin_officer'
AND permissions.permission_name IN (
    'weekly.review_assigned',
    'weekly.approve_level1',
    'weekly.reject_level1'
);

INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    roles.id,
    permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.role_name = 'admin_manager'
AND permissions.permission_name IN (
    'weekly.review_assigned',
    'weekly.approve_final',
    'weekly.reject_final',
    'audit.view'
);
INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    roles.id,
    permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.role_name = 'admin_manager'
AND permissions.permission_name IN (
    'weekly.review_assigned',
    'weekly.approve_final',
    'weekly.reject_final',
    'audit.view'
);