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