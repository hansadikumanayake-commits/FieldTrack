<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';
require_once 'review_helpers.php';

requireRole(['admin_manager']);

$userId = currentUserId();
$name = (string) ($_SESSION['name'] ?? 'Admin Manager');

$stmt = $conn->prepare(
    "SELECT
        ws.id,
        ws.week_start,
        ws.week_end,
        ws.status,
        ws.submitted_at,
        ws.updated_at,
        fo.name AS field_officer_name,
        fo.username AS field_officer_username,
        ao.name AS admin_officer_name,
        COUNT(wsr.id) AS record_count
     FROM weekly_submissions ws
     INNER JOIN users fo
        ON fo.id = ws.field_officer_id
     INNER JOIN users ao
        ON ao.id = ws.admin_officer_id
     LEFT JOIN weekly_submission_records wsr
        ON wsr.submission_id = ws.id
     WHERE ws.admin_manager_id = ?
     GROUP BY
        ws.id,
        ws.week_start,
        ws.week_end,
        ws.status,
        ws.submitted_at,
        ws.updated_at,
        fo.name,
        fo.username,
        ao.name
     ORDER BY
        FIELD(
            ws.status,
            'pending_manager_review',
            'admin_officer_approved',
            'final_approved',
            'returned_for_correction',
            'manager_rejected',
            'submitted',
            'resubmitted',
            'admin_officer_rejected',
            'draft'
        ),
        ws.updated_at DESC"
);

$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$pendingCount = 0;
$rows = [];

while ($row = $result->fetch_assoc()) {
    if (in_array(
        $row['status'],
        ['pending_manager_review', 'admin_officer_approved'],
        true
    )) {
        $pendingCount++;
    }

    $rows[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Manager Panel</title>
    <link rel="stylesheet" href="review_panel.css">
</head>
<body>
<header class="topbar">
    <div>
        <h1>FieldTrack</h1>
        <p>Admin Manager Dashboard — <?= h($name) ?></p>
    </div>
    <div class="topbar-links">
        <a href="admin_manager_panel.php">Dashboard</a>
        <a href="audit_logs.php">Audit Logs</a>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<main class="container">
    <?php if (isset($_GET['msg'])): ?>
        <div class="notice">
            <?= h((string) $_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="summary-grid">
        <div class="summary-card">
            Pending final review
            <strong><?= $pendingCount ?></strong>
        </div>
        <div class="summary-card">
            All assigned submissions
            <strong><?= count($rows) ?></strong>
        </div>
    </div>

    <section class="panel">
        <h2>Manager Review Queue</h2>

        <div class="submission-list">
            <?php if ($rows === []): ?>
                <div class="empty">No weekly submissions are assigned to you.</div>
            <?php endif; ?>

            <?php foreach ($rows as $row): ?>
                <article class="submission-card">
                    <div>
                        <h3>
                            <?= h((string) $row['field_officer_name']) ?>
                            <small>
                                (@<?= h((string) $row['field_officer_username']) ?>)
                            </small>
                        </h3>
                        <div class="submission-meta">
                            Admin Officer:
                            <?= h((string) $row['admin_officer_name']) ?><br>
                            Week:
                            <?= date('d M Y', strtotime($row['week_start'])) ?>
                            –
                            <?= date('d M Y', strtotime($row['week_end'])) ?><br>
                            Records: <?= (int) $row['record_count'] ?><br>
                            <span class="status">
                                <?= h(getWeekStatusLabel((string) $row['status'])) ?>
                            </span>
                        </div>
                    </div>

                    <a class="btn btn-primary"
                       href="submission_details.php?id=<?= (int) $row['id'] ?>">
                        Open Submission
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
