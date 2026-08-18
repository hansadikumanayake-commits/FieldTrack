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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Manager Panel</title>
<link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
<div><h1>FieldTrack</h1><p>Admin Manager Dashboard — <?= h(currentDisplayName()) ?></p></div>
<div class="topbar-links">
<a href="<?= h(appUrl('admin_manager_panel.php')) ?>">Dashboard</a>
<a href="<?= h(appUrl('reports.php')) ?>">Reports</a>
<a href="<?= h(appUrl('audit_logs.php')) ?>">Audit Logs</a>
<a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
</div>
</header>
<main class="container">
<?php if ($message !== ''): ?><div class="message success-message"><?= h($message) ?></div><?php endif; ?>
<div class="notification-strip">🔔 <?= (int) ($counts['pending_count'] ?? 0) ?> submission(s) are waiting for final review.</div>
<div class="summary-grid">
<div class="summary-card">Pending Final Review<strong><?= (int) ($counts['pending_count'] ?? 0) ?></strong></div>
<div class="summary-card">Final Approved<strong><?= (int) ($counts['approved_count'] ?? 0) ?></strong></div>
<div class="summary-card">Rejected / Returned<strong><?= (int) ($counts['rejected_count'] ?? 0) ?></strong></div>
<div class="summary-card">All Assigned<strong><?= (int) ($counts['total_count'] ?? 0) ?></strong></div>
</div>
<section class="panel">
<div class="panel-heading-row">
<div><h2>Weekly Submissions</h2><p>Final review queue and previously reviewed submissions.</p></div>
<form method="GET" class="inline-filter">
<select name="status" onchange="this.form.submit()">
<option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
<option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Final Review</option>
<option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Final Approved</option>
<option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
</select>
</form>
</div>
<?php if (!$rows): ?><p>No submissions match this filter.</p><?php endif; ?>
<?php foreach ($rows as $row): ?>
<?php
$status = (string) $row['status'];
$statusClass = in_array($status, ['pending_manager_review','admin_officer_approved'], true)
    ? 'status-pending'
    : ($status === 'final_approved' ? 'status-approved' : 'status-rejected');
?>
<div class="submission-card">
<div>
<h3><?= h($row['field_officer_name']) ?> (@<?= h($row['field_officer_username']) ?>)</h3>
<p>Week: <?= h(date('d M Y', strtotime((string) $row['week_start']))) ?> — <?= h(date('d M Y', strtotime((string) $row['week_end']))) ?></p>
<p>Admin Officer: <?= h($row['admin_officer_name']) ?> • Records: <?= (int) $row['record_count'] ?></p>
<p>Admin review: <?= h(formatDateTimeValue($row['admin_reviewed_at'])) ?></p>
<span class="status-badge <?= h($statusClass) ?>"><?= h(getWeekStatusLabel($status)) ?></span>
</div>
<a class="open-button" href="<?= h(appUrl('weekly_submission_details.php?id=' . (int) $row['id'])) ?>">Open Submission</a>
</div>
<?php endforeach; ?>
</section>
</main>
</body>
</html>