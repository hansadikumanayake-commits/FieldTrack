<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/weekly_helpers.php';

requireAdministrativeUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToDashboard();
}

$submissionId = filter_input(
    INPUT_POST,
    'submission_id',
    FILTER_VALIDATE_INT
);

$reviewAction = trim((string) ($_POST['review_action'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));
$comment = trim((string) ($_POST['comment'] ?? ''));

if ($submissionId === false || $submissionId === null || $submissionId < 1) {
    redirectToDashboard();
}

if (strlen($reason) > 2000 || strlen($comment) > 1000) {
    redirectToDashboard();
}

$role = currentRole();
$reviewerId = currentUserId();

$allowedActions = [
    'approve_level1',
    'reject_level1',
    'approve_final',
    'reject_final',
];

if (!in_array($reviewAction, $allowedActions, true)) {
    redirectToDashboard();
}

if (
    in_array($reviewAction, ['reject_level1', 'reject_final'], true) &&
    $reason === ''
) {
    $detailsPath = 'weekly_submission_details.php?id=' .
        (int) $submissionId .
        '&msg=' .
        rawurlencode('A rejection reason is required.');

    redirectTo($detailsPath);
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        "SELECT *
         FROM weekly_submissions
         WHERE id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param('i', $submissionId);
    $stmt->execute();

    $submission = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$submission) {
        $conn->rollback();
        redirectToDashboard();
    }

    preventSelfApproval((int) $submission['field_officer_id']);

    $previousStatus = (string) $submission['status'];
    $newStatus = '';
    $decision = '';
    $historyReason = null;
    $auditAction = '';
    $lockValue = 1;

    if ($role === 'admin_officer') {
        requirePermission('weekly.review_assigned');

        if ((int) $submission['admin_officer_id'] !== $reviewerId) {
            $conn->rollback();
            http_response_code(403);
            exit('This submission is not assigned to you.');
        }

        if (!in_array($previousStatus, ['submitted', 'resubmitted'], true)) {
            $conn->rollback();
            redirectTo(
                'admin_officer_panel.php?msg=' .
                rawurlencode('That submission is no longer waiting for Admin Officer review.')
            );
        }

        if ($reviewAction === 'approve_level1') {
            requirePermission('weekly.approve_level1');

            $newStatus = 'pending_manager_review';
            $decision = 'approved';
            $auditAction = 'ADMIN_OFFICER_APPROVED';
            $lockValue = 1;
        } elseif ($reviewAction === 'reject_level1') {
            requirePermission('weekly.reject_level1');

            $newStatus = 'admin_officer_rejected';
            $decision = 'rejected';
            $historyReason = $reason;
            $auditAction = 'ADMIN_OFFICER_REJECTED';
            $lockValue = 0;
        } else {
            $conn->rollback();
            http_response_code(403);
            exit('This action is not valid for an Admin Officer.');
        }

        $update = $conn->prepare(
            "UPDATE weekly_submissions
             SET
                status = ?,
                latest_rejection_reason = ?,
                admin_reviewed_at = NOW(),
                manager_reviewed_at = NULL
             WHERE id = ?"
        );

        $update->bind_param(
            'ssi',
            $newStatus,
            $historyReason,
            $submissionId
        );
        $update->execute();
        $update->close();

        $dashboard = 'admin_officer_panel.php';
    } elseif ($role === 'admin_manager') {
        requirePermission('weekly.review_assigned');

        if ((int) $submission['admin_manager_id'] !== $reviewerId) {
            $conn->rollback();
            http_response_code(403);
            exit('This submission is not assigned to you.');
        }

        if (!in_array(
            $previousStatus,
            ['pending_manager_review', 'admin_officer_approved'],
            true
        )) {
            $conn->rollback();
            redirectTo(
                'admin_manager_panel.php?msg=' .
                rawurlencode('That submission is no longer waiting for Manager review.')
            );
        }

        if ($reviewAction === 'approve_final') {
            requirePermission('weekly.approve_final');

            $newStatus = 'final_approved';
            $decision = 'approved';
            $auditAction = 'MANAGER_FINAL_APPROVED';
            $lockValue = 1;
        } elseif ($reviewAction === 'reject_final') {
            requirePermission('weekly.reject_final');

            $newStatus = 'manager_rejected';
            $decision = 'rejected';
            $historyReason = $reason;
            $auditAction = 'MANAGER_REJECTED';
            $lockValue = 0;
        } else {
            $conn->rollback();
            http_response_code(403);
            exit('This action is not valid for an Admin Manager.');
        }

        $update = $conn->prepare(
            "UPDATE weekly_submissions
             SET
                status = ?,
                latest_rejection_reason = ?,
                manager_reviewed_at = NOW()
             WHERE id = ?"
        );

        $update->bind_param(
            'ssi',
            $newStatus,
            $historyReason,
            $submissionId
        );
        $update->execute();
        $update->close();

        $dashboard = 'admin_manager_panel.php';
    } else {
        $conn->rollback();
        http_response_code(403);
        exit('System Administrators can view submissions but do not approve or reject them.');
    }

    $lock = $conn->prepare(
        "UPDATE attendance_events ae
         INNER JOIN weekly_submission_records wsr
            ON wsr.attendance_event_id = ae.id
         SET ae.is_locked = ?
         WHERE wsr.submission_id = ?"
    );

    $lock->bind_param('ii', $lockValue, $submissionId);
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
                reason,
                comment,
                ip_address
            )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $historyComment = $comment !== '' ? $comment : null;

    $history->bind_param(
        'iisssssss',
        $submissionId,
        $reviewerId,
        $role,
        $decision,
        $previousStatus,
        $newStatus,
        $historyReason,
        $historyComment,
        $ip
    );

    $history->execute();
    $history->close();

    $details =
        'Status changed from ' .
        $previousStatus .
        ' to ' .
        $newStatus;

    if ($reason !== '') {
        $details .= '. Reason: ' . $reason;
    }

    $audit = $conn->prepare(
        "INSERT INTO audit_logs
            (user_id, action, target_type, target_id, details, ip_address)
         VALUES (?, ?, 'weekly_submission', ?, ?, ?)"
    );

    $audit->bind_param(
        'isiss',
        $reviewerId,
        $auditAction,
        $submissionId,
        $details,
        $ip
    );
    $audit->execute();
    $audit->close();

    $conn->commit();

    $message = $decision === 'approved'
        ? 'Weekly submission approved successfully.'
        : 'Weekly submission rejected successfully. The Field Officer can now correct and resubmit it.';

    /*
     * IMPORTANT:
     * Redirect directly to the known dashboard using an absolute app path.
     * This avoids the 404 problem caused by mixed old/new filenames.
     */
    redirectTo($dashboard . '?msg=' . rawurlencode($message));
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
        // Ignore rollback failure.
    }

    error_log('FieldTrack weekly review error: ' . $error->getMessage());

    $dashboard = currentRole() === 'admin_manager'
        ? 'admin_manager_panel.php'
        : 'admin_officer_panel.php';

    redirectTo(
        $dashboard .
        '?msg=' .
        rawurlencode('Review failed: ' . $error->getMessage())
    );
}
