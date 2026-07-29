CREATE DATABASE IF NOT EXISTS fieldtrack_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE fieldtrack_db;

/* =========================================================
   USERS
   ========================================================= */

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
MODIFY password VARCHAR(255) NOT NULL;

/* =========================================================
   ATTENDANCE EVENTS
   ========================================================= */

CREATE TABLE IF NOT EXISTS attendance_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('IN', 'OUT') NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_attendance_user (user_id),
    INDEX idx_attendance_created (created_at),
    INDEX idx_attendance_action (action_type),

    CONSTRAINT fk_attendance_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/* =========================================================
   AUDIT LOGS
   ========================================================= */

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(100) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at),

    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/* =========================================================
   ROLES
   ========================================================= */

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/* =========================================================
   PERMISSIONS
   ========================================================= */

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

/* =========================================================
   USER ROLES
   ========================================================= */

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

/* =========================================================
   ROLE PERMISSIONS
   ========================================================= */

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

/* =========================================================
   OFFICER ASSIGNMENTS
   ========================================================= */

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

/* =========================================================
   INSERT ROLES
   ========================================================= */

INSERT INTO roles (
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
)
ON DUPLICATE KEY UPDATE
description = VALUES(description);

/* =========================================================
   INSERT PERMISSIONS
   ========================================================= */

INSERT INTO permissions (
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
    'Assign Field Officers to reviewers'
),
(
    'audit.view',
    'View system audit logs'
)
ON DUPLICATE KEY UPDATE
description = VALUES(description);

/* =========================================================
   FIELD OFFICER PERMISSIONS
   ========================================================= */

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

/* =========================================================
   ADMIN OFFICER PERMISSIONS
   ========================================================= */

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

/* =========================================================
   ADMIN MANAGER PERMISSIONS
   ========================================================= */

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

/* =========================================================
   SYSTEM ADMINISTRATOR PERMISSIONS
   ========================================================= */

INSERT IGNORE INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    roles.id,
    permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.role_name = 'system_admin'
AND permissions.permission_name IN (
    'users.manage',
    'roles.manage',
    'assignments.manage',
    'audit.view'
);

/* =========================================================
   CREATE OR UPDATE TEST ACCOUNTS
   Plain-text passwords
   ========================================================= */

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
    'admin123',
    'admin'
),
(
    'Field Officer',
    'officer',
    'officer123',
    'user'
),
(
    'Kamal Perera',
    'kamal',
    '123',
    'admin'
),
(
    'Admin Manager',
    'test',
    'test123',
    'admin'
)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
password = VALUES(password),
role = VALUES(role);

/* =========================================================
   REMOVE OLD TEST-ACCOUNT ROLE ASSIGNMENTS
   ========================================================= */

DELETE user_roles
FROM user_roles
INNER JOIN users
    ON users.id = user_roles.user_id
WHERE users.username IN (
    'admin',
    'officer',
    'kamal',
    'test'
);

/* =========================================================
   ASSIGN SYSTEM ADMINISTRATOR ROLE
   ========================================================= */

INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    users.id,
    roles.id
FROM users
CROSS JOIN roles
WHERE users.username = 'admin'
AND roles.role_name = 'system_admin';

/* =========================================================
   ASSIGN FIELD OFFICER ROLE
   ========================================================= */

INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    users.id,
    roles.id
FROM users
CROSS JOIN roles
WHERE users.username = 'officer'
AND roles.role_name = 'field_officer';

/* =========================================================
   ASSIGN ADMIN OFFICER ROLE
   ========================================================= */

INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    users.id,
    roles.id
FROM users
CROSS JOIN roles
WHERE users.username = 'kamal'
AND roles.role_name = 'admin_officer';

/* =========================================================
   ASSIGN ADMIN MANAGER ROLE
   ========================================================= */

INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    users.id,
    roles.id
FROM users
CROSS JOIN roles
WHERE users.username = 'test'
AND roles.role_name = 'admin_manager';

/* =========================================================
   ASSIGN FIELD OFFICER TO REVIEWERS
   ========================================================= */

INSERT INTO officer_assignments (
    field_officer_id,
    admin_officer_id,
    admin_manager_id
)
SELECT
    fieldOfficer.id,
    adminOfficer.id,
    adminManager.id
FROM users AS fieldOfficer
CROSS JOIN users AS adminOfficer
CROSS JOIN users AS adminManager
WHERE fieldOfficer.username = 'officer'
AND adminOfficer.username = 'kamal'
AND adminManager.username = 'test'
ON DUPLICATE KEY UPDATE
admin_officer_id = VALUES(admin_officer_id),
admin_manager_id = VALUES(admin_manager_id),
updated_at = CURRENT_TIMESTAMP;