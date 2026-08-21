/* =========================================================
   FIELDTRACK DATABASE
   Complete clean database with RBAC and approval workflow

   Test accounts:
   System Administrator: admin / admin123
   Field Officer:        officer / officer123
   Admin Officer:        kamal / 123
   Admin Manager:        test / test123

   NOTE:
   Demo passwords are stored as PHP-compatible bcrypt hashes.
   ========================================================= */


/* =========================================================
   1. CREATE DATABASE
   ========================================================= */

DROP DATABASE IF EXISTS `fieldtrack_db`;

CREATE DATABASE `fieldtrack_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `fieldtrack_db`;


/* =========================================================
   2. USERS TABLE
   Stores user account information
   ========================================================= */

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


/* =========================================================
   3. ROLES TABLE
   Stores FieldTrack RBAC roles
   ========================================================= */

CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `role_name` VARCHAR(50) NOT NULL UNIQUE,

    `description` VARCHAR(255) DEFAULT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   4. USER ROLES TABLE
   Connects users to roles
   ========================================================= */

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


/* =========================================================
   5. PERMISSIONS TABLE
   Stores actions allowed in FieldTrack
   ========================================================= */

CREATE TABLE `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `permission_name` VARCHAR(100) NOT NULL UNIQUE,

    `description` VARCHAR(255) DEFAULT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   6. ROLE PERMISSIONS TABLE
   Connects roles to permissions
   ========================================================= */

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


/* =========================================================
   7. ATTENDANCE EVENTS TABLE
   Stores Field Officer IN and OUT records
   ========================================================= */

CREATE TABLE `attendance_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `user_id` INT UNSIGNED NOT NULL,

    `action_type` ENUM(
        'IN',
        'OUT'
    ) NOT NULL,

    `latitude` DECIMAL(10,8) NOT NULL,

    `longitude` DECIMAL(11,8) NOT NULL,


    `is_locked` TINYINT(1) NOT NULL DEFAULT 0,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_attendance_user` (`user_id`),

    INDEX `idx_attendance_action` (`action_type`),

    INDEX `idx_attendance_created` (`created_at`),

    INDEX `idx_attendance_locked` (`is_locked`),

    CONSTRAINT `fk_attendance_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   8. OFFICER ASSIGNMENTS TABLE

   Field Officer
        ↓
   Admin Officer
        ↓
   Admin Manager
   ========================================================= */

CREATE TABLE `officer_assignments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `field_officer_id` INT UNSIGNED NOT NULL,

    `admin_officer_id` INT UNSIGNED NOT NULL,

    `admin_manager_id` INT UNSIGNED NOT NULL,

    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `unique_field_officer_assignment`
        (`field_officer_id`),

    INDEX `idx_assignment_admin_officer`
        (`admin_officer_id`),

    INDEX `idx_assignment_admin_manager`
        (`admin_manager_id`),

    CONSTRAINT `fk_assignment_field_officer`
        FOREIGN KEY (`field_officer_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_assignment_admin_officer`
        FOREIGN KEY (`admin_officer_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_assignment_admin_manager`
        FOREIGN KEY (`admin_manager_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   9. WEEKLY SUBMISSIONS TABLE
   Stores weekly attendance submissions and statuses
   ========================================================= */

CREATE TABLE `weekly_submissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `field_officer_id` INT UNSIGNED NOT NULL,

    `admin_officer_id` INT UNSIGNED NOT NULL,

    `admin_manager_id` INT UNSIGNED NOT NULL,

    `week_start` DATE NOT NULL,

    `week_end` DATE NOT NULL,

    `status` ENUM(
        'draft',
        'submitted',
        'admin_officer_approved',
        'admin_officer_rejected',
        'pending_manager_review',
        'manager_rejected',
        'returned_for_correction',
        'resubmitted',
        'final_approved'
    ) NOT NULL DEFAULT 'draft',

    `latest_rejection_reason` TEXT DEFAULT NULL,

    `submitted_at` DATETIME DEFAULT NULL,

    `admin_reviewed_at` DATETIME DEFAULT NULL,

    `manager_reviewed_at` DATETIME DEFAULT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `unique_officer_week` (
        `field_officer_id`,
        `week_start`,
        `week_end`
    ),

    INDEX `idx_weekly_field_officer`
        (`field_officer_id`),

    INDEX `idx_weekly_admin_officer`
        (`admin_officer_id`),

    INDEX `idx_weekly_admin_manager`
        (`admin_manager_id`),

    INDEX `idx_weekly_status`
        (`status`),

    INDEX `idx_weekly_dates`
        (`week_start`, `week_end`),

    CONSTRAINT `fk_weekly_field_officer`
        FOREIGN KEY (`field_officer_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_weekly_admin_officer`
        FOREIGN KEY (`admin_officer_id`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_weekly_admin_manager`
        FOREIGN KEY (`admin_manager_id`)
        REFERENCES `users` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   10. WEEKLY SUBMISSION RECORDS TABLE
   Connects weekly submissions to IN and OUT records
   ========================================================= */

CREATE TABLE `weekly_submission_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `submission_id` INT UNSIGNED NOT NULL,

    `attendance_event_id` INT UNSIGNED NOT NULL,

    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `unique_submission_event` (
        `submission_id`,
        `attendance_event_id`
    ),

    INDEX `idx_submission_record_submission`
        (`submission_id`),

    INDEX `idx_submission_record_event`
        (`attendance_event_id`),

    CONSTRAINT `fk_submission_record_submission`
        FOREIGN KEY (`submission_id`)
        REFERENCES `weekly_submissions` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_submission_record_event`
        FOREIGN KEY (`attendance_event_id`)
        REFERENCES `attendance_events` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   11. APPROVAL HISTORY TABLE
   Keeps every approval, rejection and resubmission
   ========================================================= */

CREATE TABLE `approval_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `submission_id` INT UNSIGNED NOT NULL,

    `reviewer_id` INT UNSIGNED DEFAULT NULL,

    `reviewer_role` VARCHAR(50) NOT NULL,

    `decision` ENUM(
        'submitted',
        'approved',
        'rejected',
        'returned',
        'resubmitted'
    ) NOT NULL,

    `previous_status` ENUM(
        'draft',
        'submitted',
        'admin_officer_approved',
        'admin_officer_rejected',
        'pending_manager_review',
        'manager_rejected',
        'returned_for_correction',
        'resubmitted',
        'final_approved'
    ) DEFAULT NULL,

    `new_status` ENUM(
        'draft',
        'submitted',
        'admin_officer_approved',
        'admin_officer_rejected',
        'pending_manager_review',
        'manager_rejected',
        'returned_for_correction',
        'resubmitted',
        'final_approved'
    ) NOT NULL,

    `reason` TEXT DEFAULT NULL,

    `comment` TEXT DEFAULT NULL,

    `ip_address` VARCHAR(45) DEFAULT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_approval_submission`
        (`submission_id`),

    INDEX `idx_approval_reviewer`
        (`reviewer_id`),

    INDEX `idx_approval_decision`
        (`decision`),

    INDEX `idx_approval_created`
        (`created_at`),

    CONSTRAINT `fk_approval_submission`
        FOREIGN KEY (`submission_id`)
        REFERENCES `weekly_submissions` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_approval_reviewer`
        FOREIGN KEY (`reviewer_id`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   12. AUDIT LOGS TABLE
   Stores important system activities
   ========================================================= */

CREATE TABLE `audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    `user_id` INT UNSIGNED DEFAULT NULL,

    `action` VARCHAR(100) NOT NULL,

    `target_type` VARCHAR(100) DEFAULT NULL,

    `target_id` INT UNSIGNED DEFAULT NULL,

    `details` TEXT DEFAULT NULL,

    `ip_address` VARCHAR(45) DEFAULT NULL,

    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_audit_user`
        (`user_id`),

    INDEX `idx_audit_action`
        (`action`),

    INDEX `idx_audit_target`
        (`target_type`, `target_id`),

    INDEX `idx_audit_created`
        (`created_at`),

    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


/* =========================================================
   13. INSERT FIELDTRACK ROLES
   ========================================================= */

INSERT INTO `roles` (
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
    'Provides final approval or rejection for weekly submissions'
),
(
    'system_admin',
    'Manages users, roles, permissions and officer assignments'
);


/* =========================================================
   14. INSERT FIELDTRACK PERMISSIONS
   ========================================================= */

INSERT INTO `permissions` (
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


/* =========================================================
   15. ASSIGN FIELD OFFICER PERMISSIONS
   ========================================================= */

INSERT INTO `role_permissions` (
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


/* =========================================================
   16. ASSIGN ADMIN OFFICER PERMISSIONS
   ========================================================= */

INSERT INTO `role_permissions` (
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


/* =========================================================
   17. ASSIGN ADMIN MANAGER PERMISSIONS
   ========================================================= */

INSERT INTO `role_permissions` (
    `role_id`,
    `permission_id`
)
SELECT
    `roles`.`id`,
    `permissions`.`id`
FROM `roles`
CROSS JOIN `permissions`
WHERE `roles`.`role_name` = 'admin_manager'
AND `permissions`.`permission_name` IN (
    'weekly.review_assigned',
    'weekly.approve_final',
    'weekly.reject_final',
    'audit.view'
);


/* =========================================================
   18. ASSIGN SYSTEM ADMINISTRATOR PERMISSIONS
   ========================================================= */

INSERT INTO `role_permissions` (
    `role_id`,
    `permission_id`
)
SELECT
    `roles`.`id`,
    `permissions`.`id`
FROM `roles`
CROSS JOIN `permissions`
WHERE `roles`.`role_name` = 'system_admin'
AND `permissions`.`permission_name` IN (
    'users.manage',
    'roles.manage',
    'assignments.manage',
    'audit.view'
);


/* =========================================================
   19. INSERT TEST USERS
   Demo users with password hashes
   ========================================================= */

INSERT INTO `users` (
    `name`,
    `username`,
    `password`,
    `is_active`
)
VALUES
(
    'System Administrator',
    'admin',
    '$2y$12$9jW0NiYJyhlnr5Uer/sPSOCuEQPqEqMV7cF4akknCHSwQUNBH01Ny',
    1
),
(
    'Field Officer',
    'officer',
    '$2y$12$CkkAUgWUpoZ58PnJxacxZO.WqJx5lD3QKS0oQLpXk9.5TkcuplUuO',
    1
),
(
    'Kamal Perera',
    'kamal',
    '$2y$12$11uemfFtp8UmSv202TSx2uvgWw5mOmVsABXsX7RAJH9lRE3aulK02',
    1
),
(
    'Admin Manager',
    'test',
    '$2y$12$NIZi4o.Ct9BRcD0uwuoStuAFJ6XTteTBLr3QIDwxi6la9fAwQhUp2',
    1
);


/* =========================================================
   20. ASSIGN SYSTEM ADMINISTRATOR ROLE
   ========================================================= */

INSERT INTO `user_roles` (
    `user_id`,
    `role_id`
)
SELECT
    `users`.`id`,
    `roles`.`id`
FROM `users`
CROSS JOIN `roles`
WHERE `users`.`username` = 'admin'
AND `roles`.`role_name` = 'system_admin';


/* =========================================================
   21. ASSIGN FIELD OFFICER ROLE
   ========================================================= */

INSERT INTO `user_roles` (
    `user_id`,
    `role_id`
)
SELECT
    `users`.`id`,
    `roles`.`id`
FROM `users`
CROSS JOIN `roles`
WHERE `users`.`username` = 'officer'
AND `roles`.`role_name` = 'field_officer';


/* =========================================================
   22. ASSIGN ADMIN OFFICER ROLE
   ========================================================= */

INSERT INTO `user_roles` (
    `user_id`,
    `role_id`
)
SELECT
    `users`.`id`,
    `roles`.`id`
FROM `users`
CROSS JOIN `roles`
WHERE `users`.`username` = 'kamal'
AND `roles`.`role_name` = 'admin_officer';


/* =========================================================
   23. ASSIGN ADMIN MANAGER ROLE
   ========================================================= */

INSERT INTO `user_roles` (
    `user_id`,
    `role_id`
)
SELECT
    `users`.`id`,
    `roles`.`id`
FROM `users`
CROSS JOIN `roles`
WHERE `users`.`username` = 'test'
AND `roles`.`role_name` = 'admin_manager';


/* =========================================================
   24. CREATE OFFICER REPORTING ASSIGNMENT

   officer → kamal → test
   ========================================================= */

INSERT INTO `officer_assignments` (
    `field_officer_id`,
    `admin_officer_id`,
    `admin_manager_id`
)
SELECT
    fieldOfficer.`id`,
    adminOfficer.`id`,
    adminManager.`id`
FROM `users` AS fieldOfficer
CROSS JOIN `users` AS adminOfficer
CROSS JOIN `users` AS adminManager
WHERE fieldOfficer.`username` = 'officer'
AND adminOfficer.`username` = 'kamal'
AND adminManager.`username` = 'test';


/* =========================================================
   25. FINAL VERIFICATION QUERIES
   ========================================================= */

SELECT
    `users`.`id`,
    `users`.`name`,
    `users`.`username`,
    `roles`.`role_name`
FROM `users`
INNER JOIN `user_roles`
    ON `user_roles`.`user_id` = `users`.`id`
INNER JOIN `roles`
    ON `roles`.`id` = `user_roles`.`role_id`
ORDER BY `users`.`id`;


SELECT
    fieldOfficer.`name` AS `field_officer`,
    adminOfficer.`name` AS `admin_officer`,
    adminManager.`name` AS `admin_manager`
FROM `officer_assignments`
INNER JOIN `users` AS fieldOfficer
    ON fieldOfficer.`id` =
       `officer_assignments`.`field_officer_id`
INNER JOIN `users` AS adminOfficer
    ON adminOfficer.`id` =
       `officer_assignments`.`admin_officer_id`
INNER JOIN `users` AS adminManager
    ON adminManager.`id` =
       `officer_assignments`.`admin_manager_id`;
       