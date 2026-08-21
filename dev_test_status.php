<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin']);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submissionId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
    $status = trim((string) ($_POST['status'] ?? ''));

    if (
        $submissionId !== false &&
        $submissionId !== null &&
        $submissionId > 0 &&
        in_array($status, getAllWeekStatuses(), true)
    ) {
        $stmt = $conn->prepare(
            "UPDATE weekly_submissions
             SET status = ?
             WHERE id = ?"
        );
        $stmt->bind_param('si', $status, $submissionId);
        $stmt->execute();
        $stmt->close();

        $message = 'Demo status updated.';
    } else {
        $message = 'Invalid demo status request.';
    }
}

$result = $conn->query(
    "SELECT
        ws.id,
        ws.week_start,
        ws.week_end,
        ws.status,
        u.name,
        u.username
     FROM weekly_submissions ws
     INNER JOIN users u ON u.id = ws.field_officer_id
     ORDER BY ws.week_start DESC, ws.id DESC"
);

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $submissions[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FieldTrack Demo Status Tool</title>
    <link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
    <div><h1>FieldTrack</h1><p>Demo/Test Status Tool</p></div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <div class="message warning-message">
        Development/demo tool only. Normal demonstrations should use the real Approve and Reject buttons.
    </div>

    <?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>

    <section class="panel">
        <h2>Change Submission Status</h2>

        <form method="POST" class="form-grid" action="<?= h(appUrl('dev_test_status.php')) ?>">
            <div>
                <label for="submission_id">Submission</label>
                <select id="submission_id" name="submission_id" required>
                    <?php foreach ($submissions as $submission): ?>
                        <option value="<?= (int) $submission['id'] ?>">
                            #<?= (int) $submission['id'] ?>
                            — <?= h($submission['name']) ?>
                            — <?= h($submission['week_start']) ?>
                            — <?= h($submission['status']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <?php foreach (getAllWeekStatuses() as $status): ?>
                        <option value="<?= h($status) ?>"><?= h(getWeekStatusLabel($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button class="approve-button" type="submit">Update Demo Status</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>