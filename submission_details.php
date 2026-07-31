<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';
require_once 'review_helpers.php';

requireRole([
    'admin_officer',
    'admin_manager',
    'system_admin',
]);

$submissionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$submissionId || $submissionId <= 0) {
    http_response_code(400);
    exit('Invalid submission ID.');
}

$submission = loadSubmission(
    $conn,
    (int) $submissionId
);

if ($submission === null) {
    http_response_code(404);
    exit('Submission not found.');
}

$userId = currentUserId();
$role = currentRole();

if (!reviewerCanAccessSubmission(
    $submission,
    $userId,
    $role
)) {
    http_response_code(403);
    exit('You are not assigned to this submission.');
}

$recordsStmt = $conn->prepare(
    "SELECT
        ae.id,
        ae.action_type,
        ae.latitude,
        ae.longitude,
        ae.created_at,
        ae.is_locked
     FROM attendance_events ae
     WHERE ae.user_id = ?
     AND DATE(ae.created_at) BETWEEN ? AND ?
     ORDER BY ae.created_at ASC, ae.id ASC"
);

$recordsStmt->bind_param(
    'iss',
    $submission['field_officer_id'],
    $submission['week_start'],
    $submission['week_end']
);

$recordsStmt->execute();
$recordsResult = $recordsStmt->get_result();

$historyStmt = $conn->prepare(
    "SELECT
        ah.*,
        u.name AS reviewer_name
     FROM approval_history ah
     LEFT JOIN users u
        ON u.id = ah.reviewer_id
     WHERE ah.submission_id = ?
     ORDER BY ah.created_at ASC, ah.id ASC"
);

$historyStmt->bind_param('i', $submissionId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();

$adminOfficerAction =
    $role === 'admin_officer' &&
    in_array(
        $submission['status'],
        ['submitted', 'resubmitted'],
        true
    );

$managerAction =
    $role === 'admin_manager' &&
    in_array(
        $submission['status'],
        [
            'pending_manager_review',
            'admin_officer_approved',
        ],
        true
    );

$backPage = match ($role) {
    'admin_officer' => 'admin_officer_panel.php',
    'admin_manager' => 'admin_manager_panel.php',
    default => 'admin_panel.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Details</title>
    <link rel="stylesheet" href="review_panel.css">
</head>
<body>
<header class="topbar">
    <div>
        <h1>Weekly Submission #<?= (int) $submission['id'] ?></h1>
        <p><?= h(getWeekStatusLabel((string) $submission['status'])) ?></p>
    </div>
    <div class="topbar-links">
        <a href="<?= h($backPage) ?>">Back to Dashboard</a>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<main class="container">
    <?php if (isset($_GET['msg'])): ?>
        <div class="notice">
            <?= h((string) $_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>Submission Information</h2>

        <div class="detail-grid">
            <div class="detail-box">
                <span>Field Officer</span>
                <strong>
                    <?= h((string) $submission['field_officer_name']) ?>
                    (@<?= h((string) $submission['field_officer_username']) ?>)
                </strong>
            </div>
            <div class="detail-box">
                <span>Admin Officer</span>
                <strong><?= h((string) $submission['admin_officer_name']) ?></strong>
            </div>
            <div class="detail-box">
                <span>Admin Manager</span>
                <strong><?= h((string) $submission['admin_manager_name']) ?></strong>
            </div>
            <div class="detail-box">
                <span>Week</span>
                <strong>
                    <?= date('d M Y', strtotime($submission['week_start'])) ?>
                    –
                    <?= date('d M Y', strtotime($submission['week_end'])) ?>
                </strong>
            </div>
            <div class="detail-box">
                <span>Status</span>
                <strong><?= h(getWeekStatusLabel((string) $submission['status'])) ?></strong>
            </div>
            <div class="detail-box">
                <span>Latest reason</span>
                <strong>
                    <?= h((string) ($submission['latest_rejection_reason'] ?? 'None')) ?>
                </strong>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Attendance Records</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Action</th>
                    <th>Date and Time</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Locked</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($recordsResult->num_rows === 0): ?>
                    <tr>
                        <td colspan="6">No records found for this week.</td>
                    </tr>
                <?php endif; ?>

                <?php while ($record = $recordsResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int) $record['id'] ?></td>
                        <td><?= h((string) $record['action_type']) ?></td>
                        <td><?= h((string) $record['created_at']) ?></td>
                        <td><?= h((string) $record['latitude']) ?></td>
                        <td><?= h((string) $record['longitude']) ?></td>
                        <td><?= (int) $record['is_locked'] === 1 ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($adminOfficerAction): ?>
        <section class="panel">
            <h2>Admin Officer Decision</h2>
            <div class="action-layout">
                <form class="action-form"
                      action="process_review.php"
                      method="POST">
                    <input type="hidden" name="submission_id"
                           value="<?= (int) $submission['id'] ?>">
                    <input type="hidden" name="decision"
                           value="admin_approve">
                    <p>Approve and send this submission to the Admin Manager.</p>
                    <button class="btn btn-success" type="submit">
                        Approve and Send to Manager
                    </button>
                </form>

                <form class="action-form"
                      action="process_review.php"
                      method="POST">
                    <input type="hidden" name="submission_id"
                           value="<?= (int) $submission['id'] ?>">
                    <input type="hidden" name="decision"
                           value="admin_reject">
                    <label for="admin_reason"><strong>Rejection reason</strong></label>
                    <textarea id="admin_reason"
                              name="reason"
                              required></textarea>
                    <button class="btn btn-danger" type="submit">
                        Reject and Unlock for Correction
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($managerAction): ?>
        <section class="panel">
            <h2>Admin Manager Decision</h2>
            <div class="action-layout">
                <form class="action-form"
                      action="process_review.php"
                      method="POST">
                    <input type="hidden" name="submission_id"
                           value="<?= (int) $submission['id'] ?>">
                    <input type="hidden" name="decision"
                           value="manager_approve">
                    <p>Give final approval and permanently lock the submitted records.</p>
                    <button class="btn btn-success" type="submit">
                        Final Approve
                    </button>
                </form>

                <form class="action-form"
                      action="process_review.php"
                      method="POST">
                    <input type="hidden" name="submission_id"
                           value="<?= (int) $submission['id'] ?>">
                    <input type="hidden" name="decision"
                           value="manager_return">
                    <label for="return_reason"><strong>Correction reason</strong></label>
                    <textarea id="return_reason"
                              name="reason"
                              required></textarea>
                    <button class="btn btn-warning" type="submit">
                        Return for Correction and Unlock
                    </button>
                </form>

                <form class="action-form"
                      action="process_review.php"
                      method="POST">
                    <input type="hidden" name="submission_id"
                           value="<?= (int) $submission['id'] ?>">
                    <input type="hidden" name="decision"
                           value="manager_reject">
                    <label for="manager_reason"><strong>Rejection reason</strong></label>
                    <textarea id="manager_reason"
                              name="reason"
                              required></textarea>
                    <button class="btn btn-danger" type="submit">
                        Reject and Unlock
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>Approval History</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Reviewer</th>
                    <th>Role</th>
                    <th>Decision</th>
                    <th>Previous</th>
                    <th>New</th>
                    <th>Reason</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($historyResult->num_rows === 0): ?>
                    <tr>
                        <td colspan="7">No approval history recorded.</td>
                    </tr>
                <?php endif; ?>

                <?php while ($history = $historyResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= h((string) $history['created_at']) ?></td>
                        <td><?= h((string) ($history['reviewer_name'] ?? 'System')) ?></td>
                        <td><?= h((string) $history['reviewer_role']) ?></td>
                        <td><?= h((string) $history['decision']) ?></td>
                        <td><?= h((string) ($history['previous_status'] ?? 'None')) ?></td>
                        <td><?= h((string) $history['new_status']) ?></td>
                        <td><?= h((string) ($history['reason'] ?? '')) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
<?php
$recordsStmt->close();
$historyStmt->close();
$conn->close();
?>
