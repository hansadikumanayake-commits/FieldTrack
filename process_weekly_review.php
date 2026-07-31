<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';
require_once 'review_helpers.php';

requireRole([
    'admin_officer',
    'admin_manager',
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToDashboard();
}

$submissionId = filter_input(
    INPUT_POST,
    'submission_id',
    FILTER_VALIDATE_INT
);

$decision = trim(
    (string) ($_POST['decision'] ?? '')
);

$reason = trim(
    (string) ($_POST['reason'] ?? '')
);

if (!$submissionId || $submissionId <= 0) {
    http_response_code(400);
    exit('Invalid submission.');
}

$role = currentRole();
$reviewerId = currentUserId();
$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare(
        "SELECT *
         FROM weekly_submissions
         WHERE id = ?
         LIMIT 1
         FOR UPDATE"
    );

    if ($stmt === false) {
        throw new RuntimeException(
            'Prepare failed: ' . $conn->error
        );
    }

    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($submission === null) {
        throw new RuntimeException(
            'Submission not found.'
        );
    }

    if (!reviewerCanAccessSubmission(
        $submission,
        $reviewerId,
        $role
    )) {
        http_response_code(403);
        throw new RuntimeException(
            'Reviewer is not assigned to this submission.'
        );
    }

    $previousStatus = (string) $submission['status'];
    $newStatus = '';
    $historyDecision = '';
    $historyReason = null;
    $auditAction = '';
    $lockValue = 1;

    if ($role === 'admin_officer') {
        if (!in_array(
            $previousStatus,
            ['submitted', 'resubmitted'],
            true
        )) {
            throw new RuntimeException(
                'This submission is not waiting for Admin Officer review.'
            );
        }

        if ($decision === 'admin_approve') {
            $newStatus = 'pending_manager_review';
            $historyDecision = 'approved';
            $auditAction = 'ADMIN_OFFICER_APPROVED';
            $lockValue = 1;
        } elseif ($decision === 'admin_reject') {
            if ($reason === '') {
                throw new RuntimeException(
                    'A rejection reason is required.'
                );
            }

            $newStatus = 'admin_officer_rejected';
            $historyDecision = 'rejected';
            $historyReason = $reason;
            $auditAction = 'ADMIN_OFFICER_REJECTED';
            $lockValue = 0;
        } else {
            throw new RuntimeException(
                'Invalid Admin Officer decision.'
            );
        }

        $updateStmt = $conn->prepare(
            "UPDATE weekly_submissions
             SET
                status = ?,
                latest_rejection_reason = ?,
                admin_reviewed_at = NOW(),
                manager_reviewed_at = NULL
             WHERE id = ?"
        );
    } else {
        if (!in_array(
            $previousStatus,
            [
                'pending_manager_review',
                'admin_officer_approved',
            ],
            true
        )) {
            throw new RuntimeException(
                'This submission is not waiting for Manager review.'
            );
        }

        if ($decision === 'manager_approve') {
            $newStatus = 'final_approved';
            $historyDecision = 'approved';
            $auditAction = 'MANAGER_FINAL_APPROVED';
            $lockValue = 1;
        } elseif ($decision === 'manager_return') {
            if ($reason === '') {
                throw new RuntimeException(
                    'A correction reason is required.'
                );
            }

            $newStatus = 'returned_for_correction';
            $historyDecision = 'returned';
            $historyReason = $reason;
            $auditAction = 'MANAGER_RETURNED_FOR_CORRECTION';
            $lockValue = 0;
        } elseif ($decision === 'manager_reject') {
            if ($reason === '') {
                throw new RuntimeException(
                    'A rejection reason is required.'
                );
            }

            $newStatus = 'manager_rejected';
            $historyDecision = 'rejected';
            $historyReason = $reason;
            $auditAction = 'MANAGER_REJECTED';
            $lockValue = 0;
        } else {
            throw new RuntimeException(
                'Invalid Manager decision.'
            );
        }

        $updateStmt = $conn->prepare(
            "UPDATE weekly_submissions
             SET
                status = ?,
                latest_rejection_reason = ?,
                manager_reviewed_at = NOW()
             WHERE id = ?"
        );
    }

    if ($updateStmt === false) {
        throw new RuntimeException(
            'Prepare failed: ' . $conn->error
        );
    }

    $updateStmt->bind_param(
        'ssi',
        $newStatus,
        $historyReason,
        $submissionId
    );

    $updateStmt->execute();
    $updateStmt->close();

    $lockStmt = $conn->prepare(
        "UPDATE attendance_events ae
         INNER JOIN weekly_submission_records wsr
            ON wsr.attendance_event_id = ae.id
         SET ae.is_locked = ?
         WHERE wsr.submission_id = ?"
    );

    if ($lockStmt === false) {
        throw new RuntimeException(
            'Prepare failed: ' . $conn->error
        );
    }

    $lockStmt->bind_param(
        'ii',
        $lockValue,
        $submissionId
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
                reason,
                comment,
                ip_address
            )
         VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?
            )"
    );

    if ($historyStmt === false) {
        throw new RuntimeException(
            'Prepare failed: ' . $conn->error
        );
    }

    $comment = getWeekStatusLabel($newStatus);

    $historyStmt->bind_param(
        'iisssssss',
        $submissionId,
        $reviewerId,
        $role,
        $historyDecision,
        $previousStatus,
        $newStatus,
        $historyReason,
        $comment,
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
         VALUES (?, ?, 'weekly_submission', ?, ?, ?)"
    );

    if ($auditStmt !== false) {
        $details =
            'Status changed from ' .
            $previousStatus .
            ' to ' .
            $newStatus .
            ($reason !== '' ? '. Reason: ' . $reason : '');

        $auditStmt->bind_param(
            'isiss',
            $reviewerId,
            $auditAction,
            $submissionId,
            $details,
            $ipAddress
        );

        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    $transactionStarted = false;

    header(
        'Location: submission_details.php?id=' .
        $submissionId .
        '&msg=' .
        rawurlencode(
            'Submission updated to ' .
            getWeekStatusLabel($newStatus) .
            '.'
        )
    );
    exit;
} catch (Throwable $error) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable) {
            // Keep the original error.
        }
    }

    error_log(
        'FieldTrack review error: ' .
        $error->getMessage()
    );

    header(
        'Location: submission_details.php?id=' .
        (int) $submissionId .
        '&msg=' .
        rawurlencode(
            'The review action failed: ' .
            $error->getMessage()
        )
    );
    exit;
}
