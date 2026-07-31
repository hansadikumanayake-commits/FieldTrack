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

    if (!isResubmittable($existing)) {
        header(
            'Location: user_panel.php?' .
            'msg=nothing_to_resubmit'
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

    $submissionId = (int) $existing['id'];
    $previousStatus = (string) $existing['status'];
    $adminOfficerId =
        (int) $assignment['admin_officer_id'];
    $adminManagerId =
        (int) $assignment['admin_manager_id'];

    $conn->begin_transaction();

    $updateStmt = $conn->prepare(
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

    if ($updateStmt === false) {
        throw new RuntimeException(
            'Prepare failed (resubmit week): ' .
            $conn->error
        );
    }

    $updateStmt->bind_param(
        'iiii',
        $adminOfficerId,
        $adminManagerId,
        $submissionId,
        $fieldOfficerId
    );

    $updateStmt->execute();
    $updateStmt->close();

    $deleteLinks = $conn->prepare(
        "DELETE FROM weekly_submission_records
         WHERE submission_id = ?"
    );

    if ($deleteLinks === false) {
        throw new RuntimeException(
            'Prepare failed (delete old links): ' .
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
            'Prepare failed (relink attendance): ' .
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

    $ipAddress = getClientIpAddress();

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
                'resubmitted',
                ?,
                'resubmitted',
                'Corrected weekly attendance resubmitted',
                ?
            )"
    );

    if ($historyStmt === false) {
        throw new RuntimeException(
            'Prepare failed (resubmit history): ' .
            $conn->error
        );
    }

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
                'WEEKLY_ATTENDANCE_RESUBMITTED',
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
        'Location: user_panel.php?' .
        'msg=week_resubmitted'
    );

    exit;
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
        // No active transaction or rollback failed.
    }

    error_log(
        'FieldTrack week resubmit error: ' .
        $error->getMessage()
    );

    header(
        'Location: user_panel.php?' .
        'msg=week_resubmit_failed'
    );

    exit;
}
