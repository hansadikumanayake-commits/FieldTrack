<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';

requireRole(['field_officer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user_panel.php');
    exit;
}

$fieldOfficerId = (int) (
    $_SESSION['user_id'] ?? 0
);

if ($fieldOfficerId <= 0) {
    header('Location: login.php');
    exit;
}

[$weekStart, $weekEnd] = getWeekBounds();

try {
    $existing = getWeeklySubmission(
        $conn,
        $fieldOfficerId,
        $weekStart
    );

    /*
     * A normal submission is allowed only when there is
     * no row yet or the row is still a draft.
     */
    if (
        $existing !== null &&
        (string) $existing['status'] !== 'draft'
    ) {
        header(
            'Location: user_panel.php?' .
            'msg=week_already_submitted'
        );

        exit;
    }

    $assignment = getOfficerAssignment(
        $conn,
        $fieldOfficerId
    );

    if ($assignment === null) {
        header(
            'Location: user_panel.php?' .
            'msg=no_assignment'
        );

        exit;
    }

    $adminOfficerId =
        (int) $assignment['admin_officer_id'];

    $adminManagerId =
        (int) $assignment['admin_manager_id'];

    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM attendance_events
         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?"
    );

    if ($countStmt === false) {
        throw new RuntimeException(
            'Prepare failed (attendance count): ' .
            $conn->error
        );
    }

    $countStmt->bind_param(
        'iss',
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    $countStmt->execute();

    $countRow = $countStmt
        ->get_result()
        ->fetch_assoc();

    $countStmt->close();

    if ((int) ($countRow['total'] ?? 0) === 0) {
        header(
            'Location: user_panel.php?msg=week_empty'
        );

        exit;
    }

    $conn->begin_transaction();

    $previousStatus =
        $existing['status'] ?? null;

    $submissionStmt = $conn->prepare(
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
                ?, ?, ?, ?, ?,
                'submitted',
                NULL,
                NOW(),
                NULL,
                NULL
            )

         ON DUPLICATE KEY UPDATE
            admin_officer_id =
                VALUES(admin_officer_id),
            admin_manager_id =
                VALUES(admin_manager_id),
            status = 'submitted',
            latest_rejection_reason = NULL,
            submitted_at = NOW(),
            admin_reviewed_at = NULL,
            manager_reviewed_at = NULL"
    );

    if ($submissionStmt === false) {
        throw new RuntimeException(
            'Prepare failed (weekly submission): ' .
            $conn->error
        );
    }

    $submissionStmt->bind_param(
        'iiiss',
        $fieldOfficerId,
        $adminOfficerId,
        $adminManagerId,
        $weekStart,
        $weekEnd
    );

    $submissionStmt->execute();
    $submissionStmt->close();

    $submissionLookup = $conn->prepare(
        "SELECT id
         FROM weekly_submissions
         WHERE field_officer_id = ?
         AND week_start = ?
         LIMIT 1
         FOR UPDATE"
    );

    if ($submissionLookup === false) {
        throw new RuntimeException(
            'Prepare failed (submission ID): ' .
            $conn->error
        );
    }

    $submissionLookup->bind_param(
        'is',
        $fieldOfficerId,
        $weekStart
    );

    $submissionLookup->execute();

    $submissionRow = $submissionLookup
        ->get_result()
        ->fetch_assoc();

    $submissionLookup->close();

    if ($submissionRow === null) {
        throw new RuntimeException(
            'The weekly submission was not created.'
        );
    }

    $submissionId = (int) $submissionRow['id'];

    $deleteLinks = $conn->prepare(
        "DELETE FROM weekly_submission_records
         WHERE submission_id = ?"
    );

    if ($deleteLinks === false) {
        throw new RuntimeException(
            'Prepare failed (delete submission links): ' .
            $conn->error
        );
    }

    $deleteLinks->bind_param(
        'i',
        $submissionId
    );

    $deleteLinks->execute();
    $deleteLinks->close();

    $linkStmt = $conn->prepare(
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
         AND DATE(created_at) BETWEEN ? AND ?"
    );

    if ($linkStmt === false) {
        throw new RuntimeException(
            'Prepare failed (link attendance): ' .
            $conn->error
        );
    }

    $linkStmt->bind_param(
        'iiss',
        $submissionId,
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    $linkStmt->execute();
    $linkStmt->close();

    $lockStmt = $conn->prepare(
        "UPDATE attendance_events
         SET is_locked = 1
         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?"
    );

    if ($lockStmt === false) {
        throw new RuntimeException(
            'Prepare failed (lock attendance): ' .
            $conn->error
        );
    }

    $lockStmt->bind_param(
        'iss',
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    $lockStmt->execute();
    $lockStmt->close();

    $historyStmt = $conn->prepare(
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
                ?,
                'submitted',
                'Weekly attendance submitted',
                ?
            )"
    );

    if ($historyStmt === false) {
        throw new RuntimeException(
            'Prepare failed (approval history): ' .
            $conn->error
        );
    }

    $ipAddress = getClientIpAddress();

    $historyStmt->bind_param(
        'iiss',
        $submissionId,
        $fieldOfficerId,
        $previousStatus,
        $ipAddress
    );

    $historyStmt->execute();
    $historyStmt->close();

    $auditStmt = $conn->prepare(
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

    if ($auditStmt !== false) {
        $details =
            'Week: ' . $weekStart .
            ' to ' . $weekEnd;

        $auditStmt->bind_param(
            'iiss',
            $fieldOfficerId,
            $submissionId,
            $details,
            $ipAddress
        );

        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();

    header(
        'Location: user_panel.php?msg=week_submitted'
    );

    exit;
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
        // No active transaction or rollback failed.
    }

    error_log(
        'FieldTrack week submit error: ' .
        $error->getMessage()
    );

    header(
        'Location: user_panel.php?' .
        'msg=week_submit_failed'
    );

    exit;
}
