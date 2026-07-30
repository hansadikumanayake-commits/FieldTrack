DROP DATABASE IF EXISTS `fieldtrack_db`;

CREATE DATABASE `fieldtrack_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `fieldtrack_db`;

USE `fieldtrack_db`;

CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles`
(
    `role_name`,
    `description`
)
VALUES
(
    'field_officer',
    'Marks IN and OUT attendance and submits weekly attendance'
),
(
    'admin_officer',
    'Reviews and approves or rejects assigned Field Officer attendance'
),
(
    'admin_manager',
    'Provides final approval or rejection for attendance submissions'
),
(
    'system_admin',
    'Manages users, roles, permissions and officer assignments'
);

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`user_id`, `role_id`),

    CONSTRAINT `fk_user_roles_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_user_roles_role`
        FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `permission_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
INSERT INTO `permissions`
(
    `permission_name`,
    `description`
)
VALUES
(
    'attendance.mark_in',
    'Allows a Field Officer to mark IN attendance'
),
(
    'attendance.mark_out',
    'Allows a Field Officer to mark OUT attendance'
),
(
    'attendance.view_own',
    'Allows a Field Officer to view personal attendance'
),
(
    'weekly.submit',
    'Allows a Field Officer to submit weekly attendance'
),
(
    'weekly.view_own',
    'Allows a Field Officer to view personal weekly submissions'
),
(
    'weekly.review_assigned',
    'Allows an Admin Officer to review assigned Field Officers'
),
(
    'weekly.approve_level1',
    'Allows an Admin Officer to approve a weekly submission'
),
(
    'weekly.reject_level1',
    'Allows an Admin Officer to reject a submission with a reason'
),
(
    'weekly.approve_final',
    'Allows an Admin Manager to provide final approval'
),
(
    'weekly.reject_final',
    'Allows an Admin Manager to provide final rejection'
),
(
    'users.manage',
    'Allows a System Administrator to manage users'
),
(
    'roles.manage',
    'Allows a System Administrator to manage roles and permissions'
),
(
    'assignments.manage',
    'Allows a System Administrator to assign officers'
),
(
    'audit.view',
    'Allows authorized users to view audit logs'
);
CREATE TABLE `role_permissions` (
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`role_id`, `permission_id`),

    CONSTRAINT `fk_role_permissions_role`
        FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_role_permissions_permission`
        FOREIGN KEY (`permission_id`)
        REFERENCES `permissions` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions`
(
    `role_id`,
    `permission_id`
)
SELECT
    `roles`.`id`,
    `permissions`.`id`
FROM `roles`
CROSS JOIN `permissions`
WHERE `roles`.`role_name` = 'field_officer'
AND `permissions`.`permission_name` IN (
    'attendance.mark_in',
    'attendance.mark_out',
    'attendance.view_own',
    'weekly.submit',
    'weekly.view_own'
);
INSERT INTO `role_permissions`
(
    `role_id`,
    `permission_id`
)
SELECT
    `roles`.`id`,
    `permissions`.`id`
FROM `roles`
CROSS JOIN `permissions`
WHERE `roles`.`role_name` = 'admin_officer'
AND `permissions`.`permission_name` IN (
    'weekly.review_assigned',
    'weekly.approve_level1',
    'weekly.reject_level1'
);