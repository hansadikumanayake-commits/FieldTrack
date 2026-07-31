USE `fieldtrack_db`;

SHOW TABLES;

DESCRIBE `users`;
DESCRIBE `roles`;
DESCRIBE `user_roles`;
DESCRIBE `attendance_events`;
DESCRIBE `officer_assignments`;
DESCRIBE `weekly_submissions`;
DESCRIBE `weekly_submission_records`;
DESCRIBE `approval_history`;
DESCRIBE `audit_logs`;

SELECT
    u.id,
    u.name,
    u.username,
    r.role_name
FROM users u
INNER JOIN user_roles ur
    ON ur.user_id = u.id
INNER JOIN roles r
    ON r.id = ur.role_id
ORDER BY u.id;

SELECT
    fo.username AS field_officer,
    ao.username AS admin_officer,
    am.username AS admin_manager
FROM officer_assignments oa
INNER JOIN users fo
    ON fo.id = oa.field_officer_id
INNER JOIN users ao
    ON ao.id = oa.admin_officer_id
INNER JOIN users am
    ON am.id = oa.admin_manager_id;
