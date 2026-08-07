<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/weekly_helpers.php';

requireRole(['admin_manager']);

$userId = currentUserId();

$countStmt = $conn->prepare(
    "SELECT
        SUM(CASE WHEN status IN ('pending_manager_review','admin_officer_approved') THEN 1 ELSE 0 END) AS pending_count,
        COUNT(*) AS total_count
     FROM weekly_submissions
     WHERE admin_manager_id = ?"
);
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$counts = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

$stmt = $conn->prepare(
    "SELECT
        ws.id,
        ws.week_start,
        ws.week_end,
        ws.status,
        ws.submitted_at,
        fo.name AS field_officer_name,
        fo.username AS field_officer_username,
        COUNT(wsr.id) AS record_count
     FROM weekly_submissions ws
     INNER JOIN users fo
        ON fo.id = ws.field_officer_id
     LEFT JOIN weekly_submission_records wsr
        ON wsr.submission_id = ws.id
     WHERE ws.admin_manager_id = ?
     GROUP BY
        ws.id,
        ws.week_start,
        ws.week_end,
        ws.status,
        ws.submitted_at,
        fo.name,
        fo.username
     ORDER BY
        CASE WHEN ws.status IN ('pending_manager_review','admin_officer_approved') THEN 0 ELSE 1 END,
        ws.week_start DESC,
        ws.id DESC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

$message = trim((string) ($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Manager Panel</title>
    <link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
    <div>
        <h1>FieldTrack</h1>
        <p>Admin Manager Dashboard — <?= h(currentDisplayName()) ?></p>
    </div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('admin_manager_panel.php')) ?>">Dashboard</a>
        <a href="<?= h(appUrl('audit_logs.php')) ?>">Audit Logs</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <?php if ($message !== ''): ?>
        <div class="message success-message"><?= h($message) ?></div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            Pending final review
            <strong><?= (int) ($counts['pending_count'] ?? 0) ?></strong>
        </div>
        <div class="summary-card">
            All assigned submissions
            <strong><?= (int) ($counts['total_count'] ?? 0) ?></strong>
        </div>
    </div>

    <section class="panel">
        <h2>Weekly Submissions</h2>

        <?php if (count($rows) === 0): ?>
            <p>No weekly submissions are assigned to you.</p>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $status = (string) $row['status'];
            $statusClass = in_array($status, ['pending_manager_review','admin_officer_approved'], true)
                ? 'status-pending'
                : (str_contains($status, 'rejected') || $status === 'returned_for_correction'
                    ? 'status-rejected'
                    : ($status === 'final_approved' ? 'status-approved' : ''));
            ?>
            <div class="submission-card">
                <div>
                    <h3><?= h($row['field_officer_name']) ?> (@<?= h($row['field_officer_username']) ?>)</h3>
                    <p>
                        Week:
                        <?= h(date('d M Y', strtotime((string) $row['week_start']))) ?>
                        —
                        <?= h(date('d M Y', strtotime((string) $row['week_end']))) ?>
                    </p>
                    <p>Records: <?= (int) $row['record_count'] ?></p>
                    <span class="status-badge <?= h($statusClass) ?>">
                        <?= h(getWeekStatusLabel($status)) ?>
                    </span>
                </div>
                <a
                    class="open-button"
                    href="<?= h(appUrl('weekly_submission_details.php?id=' . (int) $row['id'])) ?>"
                >Open Submission</a>
            </div>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
