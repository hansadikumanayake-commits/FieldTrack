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

/*
 * Required working period is Monday-Friday.
 * Therefore the officer does not need to wait until Sunday.
 */
$requiredPeriodEnd = date(
    'Y-m-d',
    strtotime($weekStart . ' +4 days')
);

if ($requiredPeriodEnd > date('Y-m-d')) {
    submitBack(
        'You can submit the week after the required Monday-Friday period is completed.'
    );
}

try {

    /*
     * Check whether this week was already submitted.
     */
    $existing = getWeeklySubmission(
        $conn,
        $fieldOfficerId,
        $weekStart
    );

    if ($existing !== null) {
        submitBack(
            'That week already has a submission with status: ' .
            getWeekStatusLabel(
                (string) $existing['status']
            ) .
            '.'
        );
    }

    /*
     * Find assigned Admin Officer and Admin Manager.
     */
    $assignment = getOfficerAssignment(
        $conn,
        $fieldOfficerId
    );

    if ($assignment === null) {
        submitBack(
            'No Admin Officer / Manager assignment exists for your account.'
        );
    }

    /*
     * Check attendance exists.
     */
    $recordCount = countWeekRecords(
        $conn,
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    if ($recordCount === 0) {
        submitBack(
            'There are no attendance records in that week.'
        );
    }

    /*
     * Check Monday-Friday completeness.
     */
    $completeness = getWeekCompleteness(
        $conn,
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    if (!$completeness['is_complete']) {

        $problems = array_merge(
            $completeness['missing'],
            $completeness['sequence_issues'] ?? []
        );

        submitBack(
            'Week cannot be submitted. ' .
            implode(' | ', $problems)
        );
    }

    $adminOfficerId =
        (int) $assignment['admin_officer_id'];

    $adminManagerId =
        (int) $assignment['admin_manager_id'];

    /*
     * Start transaction.
     */
    $conn->begin_transaction();

    /*
     * Create weekly submission.
     */
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
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            'submitted',
            NULL,
            NOW()
        )"
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

    $submissionId =
        (int) $conn->insert_id;

    $insert->close();

    /*
     * Link attendance records to submission.
     */
    $link = $conn->prepare(
        "INSERT INTO weekly_submission_records
        (
            submission_id,
            attendance_event_id
        )

        SELECT
            ?,
            id

        FROM attendance_events

        WHERE user_id = ?

        AND DATE(created_at)
        BETWEEN ? AND ?"
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

    /*
     * Lock attendance records.
     */
    $lock = $conn->prepare(
        "UPDATE attendance_events

        SET is_locked = 1

        WHERE user_id = ?

        AND DATE(created_at)
        BETWEEN ? AND ?"
    );

    $lock->bind_param(
        'iss',
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    $lock->execute();
    $lock->close();

    /*
     * IP address for audit trail.
     */
    $ip = getClientIpAddress();

    /*
     * Approval history.
     */
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
        (
            ?,
            ?,
            'field_officer',
            'submitted',
            NULL,
            'submitted',
            'Weekly attendance submitted',
            ?
        )"
    );

    $history->bind_param(
        'iis',
        $submissionId,
        $fieldOfficerId,
        $ip
    );

    $history->execute();
    $history->close();

    /*
     * Audit log.
     */
    $details =
        'Week: ' .
        $weekStart .
        ' to ' .
        $weekEnd;

    $audit = $conn->prepare(
        "INSERT INTO audit_logs
        (
            user_id,
            action,
            target_type,
            target_id,
            details,
            ip_address
        )

        VALUES
        (
            ?,
            'WEEKLY_ATTENDANCE_SUBMITTED',
            'weekly_submission',
            ?,
            ?,
            ?
        )"
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

    /*
     * Finish transaction.
     */
    $conn->commit();

    submitBack(
        'Weekly attendance submitted successfully.'
    );

} catch (Throwable $error) {

    try {
        $conn->rollback();
    } catch (Throwable) {
    }

    error_log(
        'FieldTrack submit week error: ' .
        $error->getMessage()
    );

    submitBack(
        'Weekly attendance could not be submitted.'
    );
}