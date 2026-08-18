USE `fieldtrack_db`;

SELECT 'EXPECTED TABLES' AS section;
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'fieldtrack_db'
AND TABLE_NAME IN (
    'users','roles','user_roles','permissions','role_permissions',
    'attendance_events','officer_assignments','weekly_submissions',
    'weekly_submission_records','approval_history','audit_logs'
)
ORDER BY TABLE_NAME;

SELECT 'ATTENDANCE COLUMNS' AS section;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'fieldtrack_db'
AND TABLE_NAME = 'attendance_events'
ORDER BY ORDINAL_POSITION;

SELECT 'USERS + ROLES' AS section;
SELECT u.id, u.name, u.username, u.is_active, r.role_name
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON r.id = ur.role_id
ORDER BY u.id;

SELECT 'OFFICER ASSIGNMENTS' AS section;
SELECT
    fo.name AS field_officer,
    ao.name AS admin_officer,
    am.name AS admin_manager
FROM officer_assignments oa
INNER JOIN users fo ON fo.id = oa.field_officer_id
INNER JOIN users ao ON ao.id = oa.admin_officer_id
INNER JOIN users am ON am.id = oa.admin_manager_id;

SELECT 'WEEKLY SUBMISSIONS' AS section;
SELECT
    ws.id,
    fo.username AS field_officer,
    ao.username AS admin_officer,
    am.username AS admin_manager,
    ws.week_start,
    ws.week_end,
    ws.status,
    ws.latest_rejection_reason
FROM weekly_submissions ws
INNER JOIN users fo ON fo.id = ws.field_officer_id
INNER JOIN users ao ON ao.id = ws.admin_officer_id
INNER JOIN users am ON am.id = ws.admin_manager_id
ORDER BY ws.week_start DESC, ws.id DESC;

SELECT 'RECENT APPROVAL HISTORY' AS section;
SELECT id, submission_id, reviewer_id, reviewer_role, decision,
       previous_status, new_status, reason, created_at
FROM approval_history
ORDER BY id DESC
LIMIT 50;

SELECT 'RECENT AUDIT LOGS' AS section;
SELECT id, user_id, action, target_type, target_id, details, ip_address, created_at
FROM audit_logs
ORDER BY id DESC
LIMIT 50;