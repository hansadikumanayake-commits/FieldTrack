<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

requireAdministrativeUser();

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Redirect to weekly submission list
|--------------------------------------------------------------------------
*/

function redirectReview(string $message): never
{
    header(
        'Location: admin_weekly_submissions.php?msg=' .
        rawurlencode($message)
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| Only accept POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_weekly_submissions.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Read submitted values
|--------------------------------------------------------------------------
*/

$submissionIdValue = trim(
    (string) ($_POST['submission_id'] ?? '')
);

$reviewAction = trim(
    (string) ($_POST['review_action'] ?? '')
);

$reason = trim(
    (string) ($_POST['reason'] ?? '')
);

$comment = trim(
    (string) ($_POST['comment'] ?? '')
);

/*
|--------------------------------------------------------------------------
| Validate submission ID
|--------------------------------------------------------------------------
*/

if (
    $submissionIdValue === '' ||
    !ctype_digit($submissionIdValue)
) {
    redirectReview('invalid_submission');
}

$submissionId = (int) $submissionIdValue;

if ($submissionId < 1) {
    redirectReview('invalid_submission');
}

/*
|--------------------------------------------------------------------------
| Validate review action
|--------------------------------------------------------------------------
*/

$allowedActions = [
    'approve_level1',
    'reject_level1',
    'approve_final',
    'reject_final'
];

if (
    !in_array(
        $reviewAction,
        $allowedActions,
        true
    )
) {
    redirectReview('review_failed');
}

/*
|--------------------------------------------------------------------------
| Limit input lengths
|--------------------------------------------------------------------------
*/

if (mb_strlen($reason) > 2000) {
    $reason = mb_substr(
        $reason,
        0,
        2000
    );
}

if (mb_strlen($comment) > 1000) {
    $comment = mb_substr(
        $comment,
        0,
        1000
    );
}

/*
|--------------------------------------------------------------------------
| Rejection reason is mandatory
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $reviewAction,
        [
            'reject_level1',
            'reject_final'
        ],
        true
    ) &&
    $reason === ''
) {
    redirectReview('review_failed');
}

/*
|--------------------------------------------------------------------------
| Determine reviewer role and required permission
|--------------------------------------------------------------------------
*/

$currentReviewerId = currentUserId();
$currentReviewerRole = '';

if (hasRole('admin_manager')) {
    $currentReviewerRole = 'admin_manager';
} elseif (hasRole('admin_officer')) {
    $currentReviewerRole = 'admin_officer';
} else {
    /*
     * System Administrators may view submissions,
     * but they do not perform attendance approval.
     */

    http_response_code(403);

    exit(
        'Access denied. Your role cannot approve or reject weekly submissions.'
    );
}

/*
|--------------------------------------------------------------------------
| Verify action belongs to the correct role
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $reviewAction,
        [
            'approve_level1',
            'reject_level1'
        ],
        true
    ) &&
    $currentReviewerRole !== 'admin_officer'
) {
    http_response_code(403);

    exit(
        'Only an Admin Officer can perform the first review.'
    );
}

if (
    in_array(
        $reviewAction,
        [
            'approve_final',
            'reject_final'
        ],
        true
    ) &&
    $currentReviewerRole !== 'admin_manager'
) {
    http_response_code(403);

    exit(
        'Only an Admin Manager can perform the final review.'
    );
}

/*
|--------------------------------------------------------------------------
| Check RBAC permission
|--------------------------------------------------------------------------
*/

switch ($reviewAction) {
    case 'approve_level1':
        requirePermission(
            'weekly.approve_level1'
        );
        break;

    case 'reject_level1':
        requirePermission(
            'weekly.reject_level1'
        );
        break;

    case 'approve_final':
        requirePermission(
            'weekly.approve_final'
        );
        break;

    case 'reject_final':
        requirePermission(
            'weekly.reject_final'
        );
        break;
}

/*
|--------------------------------------------------------------------------
| Transaction variables
|--------------------------------------------------------------------------
*/

$transactionStarted = false;

try {
    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    $transactionStarted = true;

    /*
    |--------------------------------------------------------------------------
    | Load and lock weekly submission
    |--------------------------------------------------------------------------
    */

    $submissionStatement = $conn->prepare(
        "SELECT
            id,
            field_officer_id,
            admin_officer_id,
            admin_manager_id,
            week_start,
            week_end,
            status
         FROM weekly_submissions
         WHERE id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $submissionStatement->bind_param(
        'i',
        $submissionId
    );

    $submissionStatement->execute();

    $submission = $submissionStatement
        ->get_result()
        ->fetch_assoc();

    $submissionStatement->close();

    if (!$submission) {
        $conn->rollback();

        $transactionStarted = false;

        redirectReview('invalid_submission');
    }

    $fieldOfficerId = (int) (
        $submission['field_officer_id']
    );

    $assignedAdminOfficerId = (int) (
        $submission['admin_officer_id']
    );

    $assignedAdminManagerId = (int) (
        $submission['admin_manager_id']
    );

    $previousStatus = (string) (
        $submission['status']
    );

    /*
    |--------------------------------------------------------------------------
    | Prevent self-approval
    |--------------------------------------------------------------------------
    */

    preventSelfApproval(
        $fieldOfficerId
    );

    /*
    |--------------------------------------------------------------------------
    | Confirm submission is assigned to current reviewer
    |--------------------------------------------------------------------------
    */

    if (
        $currentReviewerRole === 'admin_officer' &&
        $assignedAdminOfficerId !== $currentReviewerId
    ) {
        throw new RuntimeException(
            'This submission is not assigned to the current Admin Officer.'
        );
    }

    if (
        $currentReviewerRole === 'admin_manager' &&
        $assignedAdminManagerId !== $currentReviewerId
    ) {
        throw new RuntimeException(
            'This submission is not assigned to the current Admin Manager.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Determine new status
    |--------------------------------------------------------------------------
    */

    $newStatus = '';
    $decision = '';
    $successMessage = '';
    $auditAction = '';
    $historyComment = $comment;

    switch ($reviewAction) {
        /*
        |--------------------------------------------------------------------------
        | Admin Officer approval
        |--------------------------------------------------------------------------
        */

        case 'approve_level1':

            if (
                !in_array(
                    $previousStatus,
                    [
                        'submitted',
                        'resubmitted'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'This submission is not awaiting Admin Officer approval.'
                );
            }

            $newStatus =
                'pending_manager_review';

            $decision =
                'approved';

            $successMessage =
                'level1_approved';

            $auditAction =
                'ADMIN_OFFICER_APPROVED';

            if ($historyComment === '') {
                $historyComment =
                    'Weekly attendance approved and forwarded to the Admin Manager.';
            }

            break;

        /*
        |--------------------------------------------------------------------------
        | Admin Officer rejection
        |--------------------------------------------------------------------------
        */

        case 'reject_level1':

            if (
                !in_array(
                    $previousStatus,
                    [
                        'submitted',
                        'resubmitted'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'This submission is not awaiting Admin Officer review.'
                );
            }

            $newStatus =
                'admin_officer_rejected';

            $decision =
                'rejected';

            $successMessage =
                'level1_rejected';

            $auditAction =
                'ADMIN_OFFICER_REJECTED';

            if ($historyComment === '') {
                $historyComment =
                    'Weekly attendance rejected and returned to the Field Officer.';
            }

            break;

        /*
        |--------------------------------------------------------------------------
        | Admin Manager final approval
        |--------------------------------------------------------------------------
        */

        case 'approve_final':

            if (
                !in_array(
                    $previousStatus,
                    [
                        'pending_manager_review',
                        'admin_officer_approved'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'This submission is not awaiting final approval.'
                );
            }

            $newStatus =
                'final_approved';

            $decision =
                'approved';

            $successMessage =
                'final_approved';

            $auditAction =
                'ADMIN_MANAGER_APPROVED';

            if ($historyComment === '') {
                $historyComment =
                    'Weekly attendance received final approval.';
            }

            break;

        /*
        |--------------------------------------------------------------------------
        | Admin Manager final rejection
        |--------------------------------------------------------------------------
        */

        case 'reject_final':

            if (
                !in_array(
                    $previousStatus,
                    [
                        'pending_manager_review',
                        'admin_officer_approved'
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'This submission is not awaiting final review.'
                );
            }

            $newStatus =
                'manager_rejected';

            $decision =
                'rejected';

            $successMessage =
                'final_rejected';

            $auditAction =
                'ADMIN_MANAGER_REJECTED';

            if ($historyComment === '') {
                $historyComment =
                    'Weekly attendance rejected by the Admin Manager and returned for correction.';
            }

            break;
    }

    /*
    |--------------------------------------------------------------------------
    | Update weekly submission
    |--------------------------------------------------------------------------
    */

    if ($currentReviewerRole === 'admin_officer') {
        $latestReason =
            $decision === 'rejected'
                ? $reason
                : null;

        $updateStatement = $conn->prepare(
            "UPDATE weekly_submissions
             SET
                status = ?,
                latest_rejection_reason = ?,
                admin_reviewed_at = NOW()
             WHERE id = ?"
        );

        $updateStatement->bind_param(
            'ssi',
            $newStatus,
            $latestReason,
            $submissionId
        );
    } else {
        $latestReason =
            $decision === 'rejected'
                ? $reason
                : null;

        $updateStatement = $conn->prepare(
            "UPDATE weekly_submissions
             SET
                status = ?,
                latest_rejection_reason = ?,
                manager_reviewed_at = NOW()
             WHERE id = ?"
        );

        $updateStatement->bind_param(
            'ssi',
            $newStatus,
            $latestReason,
            $submissionId
        );
    }

    $updateStatement->execute();
    $updateStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Unlock attendance after rejection
    |--------------------------------------------------------------------------
    |
    | This allows the Field Officer's correction process
    | to work before resubmission.
    |
    */

    if ($decision === 'rejected') {
        $unlockStatement = $conn->prepare(
            "UPDATE attendance_events

             INNER JOIN weekly_submission_records
                ON weekly_submission_records.attendance_event_id =
                   attendance_events.id

             SET attendance_events.is_locked = 0

             WHERE weekly_submission_records.submission_id = ?"
        );

        $unlockStatement->bind_param(
            'i',
            $submissionId
        );

        $unlockStatement->execute();
        $unlockStatement->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Keep records locked after approval
    |--------------------------------------------------------------------------
    */

    if ($decision === 'approved') {
        $lockStatement = $conn->prepare(
            "UPDATE attendance_events

             INNER JOIN weekly_submission_records
                ON weekly_submission_records.attendance_event_id =
                   attendance_events.id

             SET attendance_events.is_locked = 1

             WHERE weekly_submission_records.submission_id = ?"
        );

        $lockStatement->bind_param(
            'i',
            $submissionId
        );

        $lockStatement->execute();
        $lockStatement->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Store approval history
    |--------------------------------------------------------------------------
    */

    $historyReason =
        $decision === 'rejected'
            ? $reason
            : null;

    $ipAddress =
        $_SERVER['REMOTE_ADDR'] ?? null;

    $historyStatement = $conn->prepare(
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
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )"
    );

    $historyStatement->bind_param(
        'iisssssss',
        $submissionId,
        $currentReviewerId,
        $currentReviewerRole,
        $decision,
        $previousStatus,
        $newStatus,
        $historyReason,
        $historyComment,
        $ipAddress
    );

    $historyStatement->execute();
    $historyStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Store audit log
    |--------------------------------------------------------------------------
    */

    $targetType =
        'weekly_submission';

    $auditDetails =
        $currentReviewerRole .
        ' changed weekly submission #' .
        $submissionId .
        ' from ' .
        $previousStatus .
        ' to ' .
        $newStatus .
        '.';

    if ($decision === 'rejected') {
        $auditDetails .=
            ' Rejection reason: ' .
            $reason;
    }

    $auditStatement = $conn->prepare(
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
            ?,
            ?,
            ?,
            ?,
            ?
        )"
    );

    $auditStatement->bind_param(
        'ississ',
        $currentReviewerId,
        $auditAction,
        $targetType,
        $submissionId,
        $auditDetails,
        $ipAddress
    );

    $auditStatement->execute();
    $auditStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Commit transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    $transactionStarted = false;

    redirectReview(
        $successMessage
    );
} catch (Throwable $error) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable) {
            // Preserve the original exception.
        }
    }

    error_log(
        'FieldTrack weekly review error: ' .
        $error->getMessage()
    );

    redirectReview('review_failed');
}