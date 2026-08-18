<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['field_officer']);

function correctionBack(int $submissionId, string $message): never
{
    redirectTo(
        'edit_attendance.php?submission_id=' . $submissionId .
        '&msg=' . rawurlencode($message)
    );
}

$fieldOfficerId = currentUserId();
$submissionId = filter_input(
    $_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET,
    'submission_id',
    FILTER_VALIDATE_INT
);

if (!$submissionId || $submissionId < 1) {
    redirectTo('user_panel.php?msg=' . rawurlencode('Invalid weekly submission.'));
}

$stmt = $conn->prepare(
    "SELECT *
     FROM weekly_submissions
     WHERE id = ? AND field_officer_id = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $submissionId, $fieldOfficerId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$submission || !isResubmittable($submission)) {
    redirectTo('user_panel.php?msg=' . rawurlencode('This week is not open for correction.'));
}

$weekStart = (string)$submission['week_start'];
$weekEnd = (string)$submission['week_end'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
    $actionType = strtoupper(trim((string)($_POST['action_type'] ?? '')));
    $occurredAtRaw = trim((string)($_POST['occurred_at'] ?? ''));

    if (!$recordId || !in_array($actionType, ['IN', 'OUT'], true) || $occurredAtRaw === '') {
        correctionBack($submissionId, 'Invalid attendance correction.');
    }

    $occurredAtObj = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $occurredAtRaw);
    if ($occurredAtObj === false) {
        correctionBack($submissionId, 'Invalid date or time.');
    }

    $occurredAt = $occurredAtObj->format('Y-m-d H:i:s');
    $occurredDate = $occurredAtObj->format('Y-m-d');

    if ($occurredDate < $weekStart || $occurredDate > $weekEnd) {
        correctionBack($submissionId, 'The corrected date must remain inside the rejected week.');
    }

    if ($occurredAtObj > new DateTimeImmutable('now')) {
        correctionBack($submissionId, 'Attendance cannot be moved into the future.');
    }

    try {
        $conn->begin_transaction();

        $lockStmt = $conn->prepare(
            "SELECT id, user_id, action_type, latitude, longitude, is_locked, created_at
             FROM attendance_events
             WHERE id = ? AND user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $lockStmt->bind_param('ii', $recordId, $fieldOfficerId);
        $lockStmt->execute();
        $record = $lockStmt->get_result()->fetch_assoc();
        $lockStmt->close();

        if (!$record) {
            $conn->rollback();
            correctionBack($submissionId, 'Attendance record not found.');
        }

        $originalDate = date('Y-m-d', strtotime((string)$record['created_at']));
        if (
            $originalDate < $weekStart ||
            $originalDate > $weekEnd ||
            (int)$record['is_locked'] !== 0
        ) {
            $conn->rollback();
            correctionBack($submissionId, 'This attendance record is not editable.');
        }

        $oldAction = (string)$record['action_type'];
        $oldTime = (string)$record['created_at'];

        $update = $conn->prepare(
            "UPDATE attendance_events
             SET action_type = ?, created_at = ?
             WHERE id = ? AND user_id = ? AND is_locked = 0"
        );
        $update->bind_param('ssii', $actionType, $occurredAt, $recordId, $fieldOfficerId);
        $update->execute();
        $update->close();

        $issues = getWeekSequenceIssues($conn, $fieldOfficerId, $weekStart, $weekEnd);

        if ($issues) {
            $conn->rollback();
            correctionBack(
                $submissionId,
                'Correction would create an invalid IN/OUT sequence: ' . implode(' | ', $issues)
            );
        }

        $details =
            'Record #' . $recordId .
            ' corrected from ' . $oldAction . ' at ' . $oldTime .
            ' to ' . $actionType . ' at ' . $occurredAt .
            '. GPS coordinates were preserved.';

        $ip = getClientIpAddress();
        $audit = $conn->prepare(
            "INSERT INTO audit_logs
                (user_id, action, target_type, target_id, details, ip_address)
             VALUES (?, 'ATTENDANCE_CORRECTED', 'attendance_event', ?, ?, ?)"
        );
        $audit->bind_param('iiss', $fieldOfficerId, $recordId, $details, $ip);
        $audit->execute();
        $audit->close();

        $conn->commit();
        correctionBack($submissionId, 'Attendance record corrected successfully.');
    } catch (Throwable $error) {
        try { $conn->rollback(); } catch (Throwable) {}
        error_log('Attendance correction error: ' . $error->getMessage());
        correctionBack($submissionId, 'Attendance correction failed.');
    }
}

$recordsStmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, is_locked, created_at
     FROM attendance_events
     WHERE user_id = ?
     AND DATE(created_at) BETWEEN ? AND ?
     ORDER BY created_at ASC, id ASC"
);
$recordsStmt->bind_param('iss', $fieldOfficerId, $weekStart, $weekEnd);
$recordsStmt->execute();
$result = $recordsStmt->get_result();
$records = [];
while ($row = $result->fetch_assoc()) $records[] = $row;
$recordsStmt->close();

$completeness = getWeekCompleteness($conn, $fieldOfficerId, $weekStart, $weekEnd);
$message = trim((string)($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Correct Attendance</title>
<link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
    <div>
        <h1>FieldTrack</h1>
        <p>Correct Rejected Attendance</p>
    </div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('user_panel.php')) ?>">Dashboard</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <?php if ($message !== ''): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2><?= h(formatDateValue($weekStart)) ?> — <?= h(formatDateValue($weekEnd)) ?></h2>
        <p><strong>Rejection reason:</strong>
            <?= h((string)($submission['latest_rejection_reason'] ?? 'No reason recorded.')) ?>
        </p>
        <p class="small">
            Only the action type and recorded date/time can be corrected.
            The GPS coordinates remain unchanged because they were captured at the time of attendance.
        </p>
    </section>

    <?php if (!$completeness['is_complete']): ?>
        <section class="panel">
            <h2>Week Validation</h2>
            <?php if ($completeness['missing']): ?>
                <p><strong>Missing attendance:</strong></p>
                <ul>
                    <?php foreach ($completeness['missing'] as $item): ?>
                        <li><?= h($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($completeness['sequence_issues']): ?>
                <p><strong>Sequence issues:</strong></p>
                <ul>
                    <?php foreach ($completeness['sequence_issues'] as $item): ?>
                        <li><?= h($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>Editable Attendance Records</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Date / Time</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Save</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$records): ?>
                    <tr><td colspan="6">No attendance records found for this week.</td></tr>
                <?php endif; ?>

                <?php foreach ($records as $record): ?>
                    <?php $formId = 'edit-record-' . (int)$record['id']; ?>
                    <tr>
                        <td>#<?= (int)$record['id'] ?></td>
                        <td>
                            <select
                                name="action_type"
                                form="<?= h($formId) ?>"
                                <?= (int)$record['is_locked'] === 1 ? 'disabled' : '' ?>
                            >
                                <option value="IN" <?= $record['action_type'] === 'IN' ? 'selected' : '' ?>>IN</option>
                                <option value="OUT" <?= $record['action_type'] === 'OUT' ? 'selected' : '' ?>>OUT</option>
                            </select>
                        </td>
                        <td>
                            <input
                                type="datetime-local"
                                name="occurred_at"
                                form="<?= h($formId) ?>"
                                value="<?= h(date('Y-m-d\TH:i', strtotime((string)$record['created_at']))) ?>"
                                <?= (int)$record['is_locked'] === 1 ? 'disabled' : '' ?>
                                required
                            >
                        </td>
                        <td><?= h($record['latitude']) ?></td>
                        <td><?= h($record['longitude']) ?></td>
                        <td>
                            <?php if ((int)$record['is_locked'] === 0): ?>
                                <form
                                    id="<?= h($formId) ?>"
                                    method="POST"
                                    action="<?= h(appUrl('edit_attendance.php')) ?>"
                                >
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="submission_id" value="<?= (int)$submissionId ?>">
                                    <input type="hidden" name="record_id" value="<?= (int)$record['id'] ?>">
                                    <button class="approve-button" type="submit">Save Correction</button>
                                </form>
                            <?php else: ?>
                                Locked
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Resubmit</h2>
        <?php if ($completeness['is_complete']): ?>
            <p>The week passes the completeness and IN/OUT sequence checks.</p>
            <form method="POST" action="<?= h(appUrl('resubmit_week.php')) ?>"
                  onsubmit="return confirm('Resubmit this corrected week?');">
                <?= csrfInput() ?>
                <input type="hidden" name="submission_id" value="<?= (int)$submissionId ?>">
                <button class="approve-button" type="submit">Resubmit Corrected Week</button>
            </form>
        <?php else: ?>
            <p>Resolve the validation issues above before resubmitting.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>