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
| Redirect helper
|--------------------------------------------------------------------------
*/

function redirectReview(
    int $submissionId,
    string $message
): never {
    header(
        'Location: weekly_submission_details.php?id=' .
        $submissionId .
        '&msg=' .
        rawurlencode($message)
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| POST only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_weekly_submissions.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$currentAdminId = currentUserId();

/*
|--------------------------------------------------------------------------
| Get submitted values
|--------------------------------------------------------------------------
*/

$submissionId = filter_input(
    INPUT_POST,
    'submission_id',
    FILTER_VALIDATE_INT
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
    $submissionId === false ||
    $submissionId === null ||
    $submissionId < 1
) {
    header(
        'Location: admin_weekly_submissions.php?msg=invalid_submission'
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| Validate action
|--------------------------------------------------------------------------
*/

$allowedActions = [
    'approve_level1',
    'reject_level1',
    'approve_final',
    'reject_final'
];

if (!in_array(
    $reviewAction,
    $allowedActions,
    true
)) {
    redirectReview(
        $submissionId,
        'invalid_action'
    );
}

/*
|--------------------------------------------------------------------------
| Validate text lengths
|--------------------------------------------------------------------------
*/

if (
    mb_strlen($comment) > 1000 ||
    mb_strlen($reason) > 2000
) {
    redirectReview(
        $submissionId,
        'invalid_input'
    );
}

/*
|--------------------------------------------------------------------------
| Rejection reason is compulsory
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
    redirectReview(
        $submissionId,
        'reason_required'
    );
}

/*
|--------------------------------------------------------------------------
| Determine reviewer role and permission
|--------------------------------------------------------------------------
*/

$reviewerRole = '';

if (
    $reviewAction === 'approve_level1' ||
    $reviewAction === 'reject_level1'
) {
    if (!hasRole('admin_officer')) {
        http_response_code(403);

        exit(
            'Only the Admin Officer can perform this review.'
        );
    }

    if ($reviewAction === 'approve_level1') {
        requirePermission(
            'weekly.approve_level1'
        );
    } else {
        requirePermission(
            'weekly.reject_level1'
        );
    }

    $reviewerRole = 'admin_officer';
} else {
    if (!hasRole('admin_manager')) {
        http_response_code(403);

        exit(
            'Only the Admin Manager can perform this review.'
        );
    }

    if ($reviewAction === 'approve_final') {
        requirePermission(
            'weekly.approve_final'
        );
    } else {
        requirePermission(
            'weekly.reject_final'
        );
    }

    $reviewerRole = 'admin_manager';
}

/*
|--------------------------------------------------------------------------
| Process review
|--------------------------------------------------------------------------
*/

try {

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Lock and retrieve submission
    |--------------------------------------------------------------------------
    */

    $submissionStatement = $conn->prepare(
        "SELECT
            id,
            field_officer_id,
            admin_officer_id,
            admin_manager_id,
            status

         FROM weekly_submissions

         WHERE id = ?

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

        redirectReview(
            $submissionId,
            'not_found'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent officer from reviewing own submission
    |--------------------------------------------------------------------------
    */

    preventSelfApproval(
        (int) $submission['field_officer_id']
    );

    $oldStatus =
        (string) $submission['status'];

    $newStatus = '';
    $decision = '';

    /*
    |--------------------------------------------------------------------------
    | ADMIN OFFICER REVIEW
    |--------------------------------------------------------------------------
    */

    if ($reviewerRole === 'admin_officer') {

        /*
         * Must be the officer assigned to this submission.
         */
        if (
            (int) $submission['admin_officer_id']
            !== $currentAdminId
        ) {
            $conn->rollback();

            http_response_code(403);

            exit(
                'This submission is not assigned to you.'
            );
        }

        /*
         * Admin Officer can review only submitted/resubmitted weeks.
         */
        if (!in_array(
            $oldStatus,
            [
                'submitted',
                'resubmitted'
            ],
            true
        )) {
            $conn->rollback();

            redirectReview(
                $submissionId,
                'not_reviewable'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Admin Officer Approve
        |--------------------------------------------------------------------------
        */

        if ($reviewAction === 'approve_level1') {

            /*
             * After Admin Officer approval it waits
             * for Admin Manager.
             */
            $newStatus =
                'pending_manager_review';

            $decision = 'approved';

            $updateStatement = $conn->prepare(
                "UPDATE weekly_submissions

                 SET
                    status = ?,
                    latest_rejection_reason = NULL,
                    admin_reviewed_at = NOW()

                 WHERE id = ?"
            );

            $updateStatement->bind_param(
                'si',
                $newStatus,
                $submissionId
            );

        /*
        |--------------------------------------------------------------------------
        | Admin Officer Reject
        |--------------------------------------------------------------------------
        */

        } else {

            $newStatus =
                'admin_officer_rejected';

            $decision = 'rejected';

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
                $reason,
                $submissionId
            );
        }

    /*
    |--------------------------------------------------------------------------
    | ADMIN MANAGER REVIEW
    |--------------------------------------------------------------------------
    */

    } else {

        /*
         * Must be assigned Admin Manager.
         */
        if (
            (int) $submission['admin_manager_id']
            !== $currentAdminId
        ) {
            $conn->rollback();

            http_response_code(403);

            exit(
                'This submission is not assigned to you.'
            );
        }

        /*
         * Manager receives submissions after
         * Admin Officer approval.
         */
        if (!in_array(
            $oldStatus,
            [
                'admin_officer_approved',
                'pending_manager_review'
            ],
            true
        )) {
            $conn->rollback();

            redirectReview(
                $submissionId,
                'not_reviewable'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Final Approve
        |--------------------------------------------------------------------------
        */

        if ($reviewAction === 'approve_final') {

            $newStatus =
                'final_approved';

            $decision = 'approved';

            $updateStatement = $conn->prepare(
                "UPDATE weekly_submissions

                 SET
                    status = ?,
                    latest_rejection_reason = NULL,
                    manager_reviewed_at = NOW()

                 WHERE id = ?"
            );

            $updateStatement->bind_param(
                'si',
                $newStatus,
                $submissionId
            );

        /*
        |--------------------------------------------------------------------------
        | Final Reject
        |--------------------------------------------------------------------------
        */

        } else {

            $newStatus =
                'manager_rejected';

            $decision = 'rejected';

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
                $reason,
                $submissionId
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save status change
    |--------------------------------------------------------------------------
    */

    $updateStatement->execute();
    $updateStatement->close();

    /*
    |--------------------------------------------------------------------------
    | If rejected, unlock attendance for correction
    |--------------------------------------------------------------------------
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
    | Add approval / rejection history
    |--------------------------------------------------------------------------
    */

    $ipAddress = substr(
        (string) (
            $_SERVER['REMOTE_ADDR'] ?? ''
        ),
        0,
        45
    );

    $historyReason =
        $decision === 'rejected'
            ? $reason
            : null;

    $historyComment =
        $comment !== ''
            ? $comment
            : null;

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

         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $historyStatement->bind_param(
        'iisssssss',
        $submissionId,
        $currentAdminId,
        $reviewerRole,
        $decision,
        $oldStatus,
        $newStatus,
        $historyReason,
        $historyComment,
        $ipAddress
    );

    $historyStatement->execute();
    $historyStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Return to submission
    |--------------------------------------------------------------------------
    */

    if ($decision === 'approved') {
        redirectReview(
            $submissionId,
            'approved'
        );
    }

    redirectReview(
        $submissionId,
        'rejected'
    );

} catch (Throwable $error) {

    try {
        $conn->rollback();
    } catch (Throwable) {
        // Ignore rollback failure.
    }

    error_log(
        'FieldTrack weekly review error: ' .
        $error->getMessage()
    );

    redirectReview(
        $submissionId,
        'review_failed'
    );
}