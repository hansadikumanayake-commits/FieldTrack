<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['field_officer']);

function submitBack(string $message): never
{
    redirectTo('user_panel.php?msg=' . rawurlencode($message));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('user_panel.php');
}

requireValidCsrf();

$fieldOfficerId = currentUserId();
$weekStart = trim((string) ($_POST['week_start'] ?? ''));

if (!isValidWeekStart($weekStart)) {
    submitBack('Invalid week selected.');
}

[, $weekEnd] = getWeekBounds($weekStart);

if ($weekEnd >= date('Y-m-d')) {
    submitBack('You can submit a week only after that week has finished.');
}

try {
    $existing = getWeeklySubmission($conn, $fieldOfficerId, $weekStart);

    if ($existing !== null) {
        submitBack(
            'That week already has a submission with status: ' .
            getWeekStatusLabel((string) $existing['status']) . '.'
        );
    }

    $assignment = getOfficerAssignment($conn, $fieldOfficerId);

    if ($assignment === null) {
        submitBack('No Admin Officer / Manager assignment exists for your account.');
    }

    $recordCount = countWeekRecords($conn, $fieldOfficerId, $weekStart, $weekEnd);

    if ($recordCount === 0) {
        submitBack('There are no attendance records in that week.');
    }

    $completeness = getWeekCompleteness($conn, $fieldOfficerId, $weekStart, $weekEnd);

    if (!$completeness['is_complete']) {
        submitBack(
            'Week cannot be submitted. ' . implode(' | ', $completeness['missing'])
        );
    }

    $adminOfficerId = (int) $assignment['admin_officer_id'];
    $adminManagerId = (int) $assignment['admin_manager_id'];

    $conn->begin_transaction();

    $insert = $conn->prepare(
        "INSERT INTO weekly_submissions
            (
                field_officer_id,
                admin_officer_id,
                admin_manager_id,
                week_start,
                week_end,
                status,
                latest_rejection_reason,
                submitted_at
            )
         VALUES (?, ?, ?, ?, ?, 'submitted', NULL, NOW())"
    );

    $insert->bind_param(
        'iiiss',
        $fieldOfficerId,
        $adminOfficerId,
        $adminManagerId,
        $weekStart,
        $weekEnd
    );
    $insert->execute();
    $submissionId = (int) $conn->insert_id;
    $insert->close();

    $link = $conn->prepare(
        "INSERT INTO weekly_submission_records (submission_id, attendance_event_id)
         SELECT ?, id
         FROM attendance_events
         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?"
    );
    $link->bind_param('iiss', $submissionId, $fieldOfficerId, $weekStart, $weekEnd);
    $link->execute();
    $link->close();

    $lock = $conn->prepare(
        "UPDATE attendance_events
         SET is_locked = 1
         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?"
    );
    $lock->bind_param('iss', $fieldOfficerId, $weekStart, $weekEnd);
    $lock->execute();
    $lock->close();

    $ip = getClientIpAddress();

    $history = $conn->prepare(
        "INSERT INTO approval_history
            (submission_id, reviewer_id, reviewer_role, decision, previous_status, new_status, comment, ip_address)
         VALUES (?, ?, 'field_officer', 'submitted', NULL, 'submitted', 'Weekly attendance submitted', ?)"
    );
    $history->bind_param('iis', $submissionId, $fieldOfficerId, $ip);
    $history->execute();
    $history->close();

    $details = 'Week: ' . $weekStart . ' to ' . $weekEnd;
    $audit = $conn->prepare(
        "INSERT INTO audit_logs
            (user_id, action, target_type, target_id, details, ip_address)
         VALUES (?, 'WEEKLY_ATTENDANCE_SUBMITTED', 'weekly_submission', ?, ?, ?)"
    );
    $audit->bind_param('iiss', $fieldOfficerId, $submissionId, $details, $ip);
    $audit->execute();
    $audit->close();

    $conn->commit();
    submitBack('Weekly attendance submitted successfully.');
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }

    error_log('FieldTrack submit week error: ' . $error->getMessage());
    submitBack('Weekly attendance could not be submitted.');
}