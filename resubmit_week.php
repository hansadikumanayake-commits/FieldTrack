<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';

requireRole(['field_officer']);

function resubmitBack(string $message): never
{
    redirectTo('user_panel.php?msg=' . rawurlencode($message));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('user_panel.php');
}

$fieldOfficerId = currentUserId();

$submissionId = filter_input(
    INPUT_POST,
    'submission_id',
    FILTER_VALIDATE_INT
);

try {
    if ($submissionId !== false && $submissionId !== null && $submissionId > 0) {
        $stmt = $conn->prepare(
            "SELECT *
             FROM weekly_submissions
             WHERE id = ?
             AND field_officer_id = ?
             LIMIT 1"
        );

        $stmt->bind_param('ii', $submissionId, $fieldOfficerId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        [$currentWeekStart] = getWeekBounds();
        $existing = getWeeklySubmission($conn, $fieldOfficerId, $currentWeekStart);
        $submissionId = (int) ($existing['id'] ?? 0);
    }

    if (!$existing || !isResubmittable($existing)) {
        resubmitBack('There is no rejected week available to resubmit.');
    }

    $weekStart = (string) $existing['week_start'];
    $weekEnd = (string) $existing['week_end'];
    $previousStatus = (string) $existing['status'];

    $assignment = getOfficerAssignment($conn, $fieldOfficerId);

    if ($assignment === null) {
        resubmitBack('No Admin Officer / Manager assignment exists for your account.');
    }

    $recordCount = countWeekRecords(
        $conn,
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    if ($recordCount === 0) {
        resubmitBack('There are no attendance records in this week to resubmit.');
    }

    $adminOfficerId = (int) $assignment['admin_officer_id'];
    $adminManagerId = (int) $assignment['admin_manager_id'];

    $conn->begin_transaction();

    $update = $conn->prepare(
        "UPDATE weekly_submissions
         SET
            admin_officer_id = ?,
            admin_manager_id = ?,
            status = 'resubmitted',
            latest_rejection_reason = NULL,
            submitted_at = NOW(),
            admin_reviewed_at = NULL,
            manager_reviewed_at = NULL
         WHERE id = ?
         AND field_officer_id = ?"
    );

    $update->bind_param(
        'iiii',
        $adminOfficerId,
        $adminManagerId,
        $submissionId,
        $fieldOfficerId
    );
    $update->execute();
    $update->close();

    $deleteLinks = $conn->prepare(
        "DELETE FROM weekly_submission_records
         WHERE submission_id = ?"
    );

    $deleteLinks->bind_param('i', $submissionId);
    $deleteLinks->execute();
    $deleteLinks->close();

    $link = $conn->prepare(
        "INSERT INTO weekly_submission_records
            (submission_id, attendance_event_id)
         SELECT ?, id
         FROM attendance_events
         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?"
    );

    $link->bind_param(
        'iiss',
        $submissionId,
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );
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
            (
                submission_id,
                reviewer_id,
                reviewer_role,
                decision,
                previous_status,
                new_status,
                comment,
                ip_address
            )
         VALUES
            (?, ?, 'field_officer', 'resubmitted', ?, 'resubmitted',
             'Corrected weekly attendance resubmitted', ?)"
    );

    $history->bind_param(
        'iiss',
        $submissionId,
        $fieldOfficerId,
        $previousStatus,
        $ip
    );
    $history->execute();
    $history->close();

    $details = 'Week: ' . $weekStart . ' to ' . $weekEnd;

    $audit = $conn->prepare(
        "INSERT INTO audit_logs
            (user_id, action, target_type, target_id, details, ip_address)
         VALUES
            (?, 'WEEKLY_ATTENDANCE_RESUBMITTED', 'weekly_submission', ?, ?, ?)"
    );

    $audit->bind_param(
        'iiss',
        $fieldOfficerId,
        $submissionId,
        $details,
        $ip
    );
    $audit->execute();
    $audit->close();

    $conn->commit();

    resubmitBack('The rejected week was resubmitted successfully.');
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
        // Ignore rollback errors.
    }

    error_log('FieldTrack resubmit error: ' . $error->getMessage());
    resubmitBack('The rejected week could not be resubmitted.');
}
