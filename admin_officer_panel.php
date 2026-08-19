<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/weekly_helpers.php';

requireRole(['admin_officer']);

$userId = currentUserId();
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$allowed = ['all', 'pending', 'approved', 'rejected', 'resubmitted'];
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'all';

$countStmt = $conn->prepare(
    "SELECT
        SUM(status IN ('submitted','resubmitted')) AS pending_count,
        SUM(status = 'resubmitted') AS resubmitted_count,
        SUM(status IN ('pending_manager_review','final_approved')) AS approved_count,
        SUM(status IN ('admin_officer_rejected','manager_rejected','returned_for_correction')) AS rejected_count,
        COUNT(*) AS total_count
     FROM weekly_submissions
     WHERE admin_officer_id = ?"
);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$counts = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

$where = "ws.admin_officer_id = ?";
if ($statusFilter === 'pending') $where .= " AND ws.status IN ('submitted','resubmitted')";
elseif ($statusFilter === 'approved') $where .= " AND ws.status IN ('pending_manager_review','final_approved')";
elseif ($statusFilter === 'rejected') $where .= " AND ws.status IN ('admin_officer_rejected','manager_rejected','returned_for_correction')";
elseif ($statusFilter === 'resubmitted') $where .= " AND ws.status = 'resubmitted'";

$stmt = $conn->prepare(
    "SELECT
        ws.id, ws.week_start, ws.week_end, ws.status, ws.submitted_at,
        ws.latest_rejection_reason,
        fo.name AS field_officer_name,
        fo.username AS field_officer_username,
        COUNT(wsr.id) AS record_count
     FROM weekly_submissions ws
     INNER JOIN users fo ON fo.id = ws.field_officer_id
     LEFT JOIN weekly_submission_records wsr ON wsr.submission_id = ws.id
     WHERE $where
     GROUP BY ws.id, ws.week_start, ws.week_end, ws.status, ws.submitted_at,
              ws.latest_rejection_reason, fo.name, fo.username
     ORDER BY
        CASE WHEN ws.status = 'resubmitted' THEN 0 WHEN ws.status = 'submitted' THEN 1 ELSE 2 END,
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