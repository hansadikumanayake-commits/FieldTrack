USE `fieldtrack_db`;

SELECT 'USERS' AS section;
SELECT
    u.id,
    u.name,
    u.username,
    u.is_active,
    r.role_name
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON r.id = ur.role_id
ORDER BY u.id;

SELECT 'ASSIGNMENTS' AS section;
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

SELECT 'APPROVAL HISTORY' AS section;
SELECT
    id,
    submission_id,
    reviewer_id,
    reviewer_role,
    decision,
    previous_status,
    new_status,
    reason,
    created_at
FROM approval_history
ORDER BY id DESC
LIMIT 50;
