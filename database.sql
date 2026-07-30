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