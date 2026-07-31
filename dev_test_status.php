<?php

declare(strict_types=1);

/*
 * DEVELOPMENT-ONLY FILE.
 *
 * This page lets a Field Officer preview different weekly
 * statuses. Remove it and remove any link to it before final
 * submission or deployment.
 */

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';

requireRole(['field_officer']);

$fieldOfficerId = (int) (
    $_SESSION['user_id'] ?? 0
);

if ($fieldOfficerId <= 0) {
    header('Location: login.php');
    exit;
}

[$weekStart, $weekEnd] = getWeekBounds();

$allStatuses = getAllWeekStatuses();
$feedback = '';

try {
    $assignment = getOfficerAssignment(
        $conn,
        $fieldOfficerId
    );

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        $assignment === null
    ) {
        throw new RuntimeException(
            'No Admin Officer and Admin Manager assignment exists.'
        );
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $conn->begin_transaction();

        if (isset($_POST['reset'])) {
            $unlockStmt = $conn->prepare(
                "UPDATE attendance_events
                 SET is_locked = 0
                 WHERE user_id = ?
                 AND DATE(created_at) BETWEEN ? AND ?"
            );

            $unlockStmt->bind_param(
                'iss',
                $fieldOfficerId,
                $weekStart,
                $weekEnd
            );

            $unlockStmt->execute();
            $unlockStmt->close();

            $deleteStmt = $conn->prepare(
                "DELETE FROM weekly_submissions
                 WHERE field_officer_id = ?
                 AND week_start = ?"
            );

            $deleteStmt->bind_param(
                'is',
                $fieldOfficerId,
                $weekStart
            );

            $deleteStmt->execute();
            $deleteStmt->close();

            $feedback =
                'The week was reset to Draft.';
        } else {
            $newStatus = trim(
                (string) ($_POST['status'] ?? '')
            );

            $reason = trim(
                (string) (
                    $_POST['rejection_reason'] ?? ''
                )
            );

            if (
                !in_array(
                    $newStatus,
                    $allStatuses,
                    true
                )
            ) {
                throw new RuntimeException(
                    'Invalid weekly status.'
                );
            }

            $reasonValue =
                $reason !== ''
                    ? $reason
                    : null;

            $adminOfficerId =
                (int) $assignment['admin_officer_id'];

            $adminManagerId =
                (int) $assignment['admin_manager_id'];

            $adminReviewedAt = in_array(
                $newStatus,
                [
                    'admin_officer_approved',
                    'admin_officer_rejected',
                    'pending_manager_review',
                    'returned_for_correction',
                ],
                true
            ) ? date('Y-m-d H:i:s') : null;

            $managerReviewedAt = in_array(
                $newStatus,
                [
                    'manager_rejected',
                    'final_approved',
                ],
                true
            ) ? date('Y-m-d H:i:s') : null;

            $stmt = $conn->prepare(
                "INSERT INTO weekly_submissions
                    (
                        field_officer_id,
                        admin_officer_id,
                        admin_manager_id,
                        week_start,
                        week_end,
                        status,
                        latest_rejection_reason,
                        submitted_at,
                        admin_reviewed_at,
                        manager_reviewed_at
                    )
                 VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?,
                        NOW(), ?, ?
                    )

                 ON DUPLICATE KEY UPDATE
                    admin_officer_id =
                        VALUES(admin_officer_id),
                    admin_manager_id =
                        VALUES(admin_manager_id),
                    status = VALUES(status),
                    latest_rejection_reason =
                        VALUES(latest_rejection_reason),
                    admin_reviewed_at =
                        VALUES(admin_reviewed_at),
                    manager_reviewed_at =
                        VALUES(manager_reviewed_at)"
            );

            if ($stmt === false) {
                throw new RuntimeException(
                    'Prepare failed: ' .
                    $conn->error
                );
            }

            $stmt->bind_param(
                'iiissssss',
                $fieldOfficerId,
                $adminOfficerId,
                $adminManagerId,
                $weekStart,
                $weekEnd,
                $newStatus,
                $reasonValue,
                $adminReviewedAt,
                $managerReviewedAt
            );

            $stmt->execute();
            $stmt->close();

            $locked =
                in_array(
                    $newStatus,
                    [
                        'draft',
                        'returned_for_correction',
                        'admin_officer_rejected',
                        'manager_rejected',
                    ],
                    true
                )
                    ? 0
                    : 1;

            $lockStmt = $conn->prepare(
                "UPDATE attendance_events
                 SET is_locked = ?
                 WHERE user_id = ?
                 AND DATE(created_at) BETWEEN ? AND ?"
            );

            $lockStmt->bind_param(
                'iiss',
                $locked,
                $fieldOfficerId,
                $weekStart,
                $weekEnd
            );

            $lockStmt->execute();
            $lockStmt->close();

            $feedback =
                'Status set to "' .
                getWeekStatusLabel($newStatus) .
                '".';
        }

        $conn->commit();
    }
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
        // Ignore rollback error.
    }

    $feedback = $error->getMessage();
}

$current = getWeeklySubmission(
    $conn,
    $fieldOfficerId,
    $weekStart
);

$currentStatus =
    $current['status'] ?? 'draft';

$currentReason =
    $current['rejection_reason'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Dev: Week Status Switcher</title>

    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f6f7fb;
            padding: 30px;
            color: #1f2937;
        }

        .box {
            max-width: 520px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(31, 20, 90, 0.08);
        }

        h1 {
            font-size: 20px;
            margin: 0 0 8px;
        }

        .warn {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 10px 12px;
            border-radius: 8px;
            color: #92400e;
            margin-bottom: 16px;
        }

        .feedback {
            background: #eef2ff;
            color: #3730a3;
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        select,
        textarea,
        button {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            margin-bottom: 12px;
            font-size: 14px;
        }

        button {
            background: #5b3df0;
            color: #ffffff;
            border: none;
            font-weight: 700;
            cursor: pointer;
        }

        .reset-btn {
            background: #6b7280;
        }

        a {
            color: #5b3df0;
        }
    </style>
</head>

<body>
<div class="box">
    <h1>Dev: Week Status Switcher</h1>

    <div class="warn">
        Testing only. Delete this file before submitting
        or deploying FieldTrack.
    </div>

    <p>
        Week:
        <?= date('d M', strtotime($weekStart)) ?>
        –
        <?= date('d M Y', strtotime($weekEnd)) ?>
        <br>

        Current status:
        <strong>
            <?= htmlspecialchars(
                getWeekStatusLabel(
                    (string) $currentStatus
                )
            ) ?>
        </strong>
    </p>

    <?php if ($feedback !== ''): ?>
        <div class="feedback">
            <?= htmlspecialchars($feedback) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <select name="status" required>
            <?php foreach ($allStatuses as $status): ?>
                <option
                    value="<?= htmlspecialchars($status) ?>"
                    <?= $status === $currentStatus
                        ? 'selected'
                        : '' ?>
                >
                    <?= htmlspecialchars(
                        getWeekStatusLabel($status)
                    ) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <textarea
            name="rejection_reason"
            rows="3"
            placeholder="Optional rejection/correction reason"
        ><?= htmlspecialchars((string) $currentReason) ?></textarea>

        <button type="submit">
            Set Status
        </button>
    </form>

    <form method="POST">
        <input type="hidden" name="reset" value="1">

        <button type="submit" class="reset-btn">
            Reset Week to Draft
        </button>
    </form>

    <p>
        <a href="user_panel.php">
            &larr; Back to dashboard
        </a>
    </p>
</div>
</body>
</html>
