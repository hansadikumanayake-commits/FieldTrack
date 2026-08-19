<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/weekly_helpers.php';

requireRole(['admin_manager']);

$userId = currentUserId();
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$allowed = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'all';

$countStmt = $conn->prepare(
    "SELECT
        SUM(status IN ('pending_manager_review','admin_officer_approved')) AS pending_count,
        SUM(status = 'final_approved') AS approved_count,
        SUM(status IN ('manager_rejected','returned_for_correction')) AS rejected_count,
        COUNT(*) AS total_count
     FROM weekly_submissions
     WHERE admin_manager_id = ?"
);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$counts = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

$where = "ws.admin_manager_id = ?";
if ($statusFilter === 'pending') $where .= " AND ws.status IN ('pending_manager_review','admin_officer_approved')";
elseif ($statusFilter === 'approved') $where .= " AND ws.status = 'final_approved'";
elseif ($statusFilter === 'rejected') $where .= " AND ws.status IN ('manager_rejected','returned_for_correction')";

$stmt = $conn->prepare(
    "SELECT
        ws.id, ws.week_start, ws.week_end, ws.status, ws.submitted_at, ws.admin_reviewed_at,
        fo.name AS field_officer_name, fo.username AS field_officer_username,
        ao.name AS admin_officer_name,
        COUNT(wsr.id) AS record_count
     FROM weekly_submissions ws
     INNER JOIN users fo ON fo.id = ws.field_officer_id
     INNER JOIN users ao ON ao.id = ws.admin_officer_id
     LEFT JOIN weekly_submission_records wsr ON wsr.submission_id = ws.id
     WHERE $where
     GROUP BY ws.id, ws.week_start, ws.week_end, ws.status, ws.submitted_at,
              ws.admin_reviewed_at, fo.name, fo.username, ao.name
     ORDER BY
        CASE WHEN ws.status IN ('pending_manager_review','admin_officer_approved') THEN 0 ELSE 1 END,
        ws.week_start DESC, ws.id DESC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
$stmt->close();
$message = trim((string) ($_GET['msg'] ?? ''));
?>