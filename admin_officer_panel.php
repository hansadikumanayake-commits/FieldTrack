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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Officer Panel</title>
<link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
    <div><h1>FieldTrack</h1><p>Admin Officer Dashboard — <?= h(currentDisplayName()) ?></p></div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('admin_officer_panel.php')) ?>">Dashboard</a>
        <a href="<?= h(appUrl('reports.php')) ?>">Reports</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>
<main class="container">
<?php if ($message !== ''): ?><div class="message success-message"><?= h($message) ?></div><?php endif; ?>

<div class="notification-strip">🔔 <?= (int) ($counts['pending_count'] ?? 0) ?> submission(s) currently need your review.</div>

<div class="summary-grid">
    <div class="summary-card">Pending Review<strong><?= (int) ($counts['pending_count'] ?? 0) ?></strong></div>
    <div class="summary-card">Resubmitted<strong><?= (int) ($counts['resubmitted_count'] ?? 0) ?></strong></div>
    <div class="summary-card">Approved / Forwarded<strong><?= (int) ($counts['approved_count'] ?? 0) ?></strong></div>
    <div class="summary-card">Rejected / Returned<strong><?= (int) ($counts['rejected_count'] ?? 0) ?></strong></div>
</div>

<section class="panel">
    <div class="panel-heading-row">
        <div><h2>Assigned Weekly Submissions</h2><p>Use the filter to focus on pending, rejected, or resubmitted weeks.</p></div>
        <form method="GET" class="inline-filter">
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                <option value="resubmitted" <?= $statusFilter === 'resubmitted' ? 'selected' : '' ?>>Resubmitted</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </form>
    </div>

    <?php if (!$rows): ?><p>No submissions match this filter.</p><?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <?php
        $status = (string) $row['status'];
        $statusClass = in_array($status, ['submitted','resubmitted'], true)
            ? 'status-pending'
            : (str_contains($status, 'rejected') || $status === 'returned_for_correction'
                ? 'status-rejected'
                : 'status-approved');
        ?>
        <div class="submission-card <?= $status === 'resubmitted' ? 'resubmitted-card' : '' ?>">
            <div>
                <h3><?= h($row['field_officer_name']) ?> (@<?= h($row['field_officer_username']) ?>)</h3>
                <p>Week: <?= h(date('d M Y', strtotime((string) $row['week_start']))) ?> — <?= h(date('d M Y', strtotime((string) $row['week_end']))) ?></p>
                <p>Records: <?= (int) $row['record_count'] ?> • Submitted: <?= h(formatDateTimeValue($row['submitted_at'])) ?></p>
                <span class="status-badge <?= h($statusClass) ?>"><?= h(getWeekStatusLabel($status)) ?></span>
                <?php if ($status === 'resubmitted'): ?><span class="new-badge">RESUBMITTED</span><?php endif; ?>
            </div>
            <a class="open-button" href="<?= h(appUrl('weekly_submission_details.php?id=' . (int) $row['id'])) ?>">Open Submission</a>
        </div>
    <?php endforeach; ?>
</section>
</main>
</body>
</html>