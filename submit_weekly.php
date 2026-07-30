<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Redirect back to the Field Officer panel
|--------------------------------------------------------------------------
*/

function redirectWeeklySubmission(string $message): never
{
    header(
        'Location: user_panel.php?msg=' .
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
    header('Location: user_panel.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Check login role and permission
|--------------------------------------------------------------------------
*/

requireFieldOfficer();
requirePermission('weekly.submit');

$fieldOfficerId = currentUserId();

/*
|--------------------------------------------------------------------------
| Calculate current week
|--------------------------------------------------------------------------
|
| Week starts on Monday and ends on Sunday.
|
*/

$today = new DateTimeImmutable('today');

$weekStartObject = $today->modify(
    'monday this week'
);

$weekEndObject = $weekStartObject->modify(
    '+6 days'
);

$nextWeekStartObject = $weekStartObject->modify(
    '+7 days'
);

$weekStart = $weekStartObject->format(
    'Y-m-d'
);

$weekEnd = $weekEndObject->format(
    'Y-m-d'
);

$weekStartDateTime = $weekStartObject->format(
    'Y-m-d 00:00:00'
);

$nextWeekStartDateTime =
    $nextWeekStartObject->format(
        'Y-m-d 00:00:00'
    );

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
    | Confirm Field Officer account
    |--------------------------------------------------------------------------
    */

    $userStatement = $conn->prepare(
        "SELECT id
         FROM users
         WHERE id = ?
         AND is_active = 1
         LIMIT 1
         FOR UPDATE"
    );

    $userStatement->bind_param(
        'i',
        $fieldOfficerId
    );

    $userStatement->execute();

    $activeUser = $userStatement
        ->get_result()
        ->fetch_assoc();

    $userStatement->close();

    if (!$activeUser) {
        throw new RuntimeException(
            'The Field Officer account is inactive.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load Officer assignment
    |--------------------------------------------------------------------------
    */

    $assignmentStatement = $conn->prepare(
        "SELECT
            admin_officer_id,
            admin_manager_id
         FROM officer_assignments
         WHERE field_officer_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $assignmentStatement->bind_param(
        'i',
        $fieldOfficerId
    );

    $assignmentStatement->execute();

    $assignment = $assignmentStatement
        ->get_result()
        ->fetch_assoc();

    $assignmentStatement->close();

    if (!$assignment) {
        $conn->rollback();

        $transactionStarted = false;

        redirectWeeklySubmission(
            'assignment_missing'
        );
    }

    $adminOfficerId = (int) (
        $assignment['admin_officer_id']
    );

    $adminManagerId = (int) (
        $assignment['admin_manager_id']
    );

    /*
    |--------------------------------------------------------------------------
    | Load attendance records for the current week
    |--------------------------------------------------------------------------
    */

    $attendanceStatement = $conn->prepare(
        "SELECT
            id,
            action_type,
            is_locked
         FROM attendance_events
         WHERE user_id = ?
         AND created_at >= ?
         AND created_at < ?
         ORDER BY created_at ASC, id ASC
         FOR UPDATE"
    );

    $attendanceStatement->bind_param(
        'iss',
        $fieldOfficerId,
        $weekStartDateTime,
        $nextWeekStartDateTime
    );

    $attendanceStatement->execute();

    $attendanceResult =
        $attendanceStatement->get_result();

    $attendanceIds = [];
    $inCount = 0;
    $outCount = 0;

    while (
        $attendanceRow =
        $attendanceResult->fetch_assoc()
    ) {
        $attendanceIds[] =
            (int) $attendanceRow['id'];

        if (
            $attendanceRow['action_type'] === 'IN'
        ) {
            $inCount++;
        }

        if (
            $attendanceRow['action_type'] === 'OUT'
        ) {
            $outCount++;
        }
    }

    $attendanceStatement->close();

    if (empty($attendanceIds)) {
        $conn->rollback();

        $transactionStarted = false;

        redirectWeeklySubmission(
            'no_weekly_attendance'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ensure the latest IN has a matching OUT
    |--------------------------------------------------------------------------
    */

    if ($inCount !== $outCount) {
        $conn->rollback();

        $transactionStarted = false;

        redirectWeeklySubmission(
            'incomplete_attendance'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Find an existing submission for this week
    |--------------------------------------------------------------------------
    */

    $existingStatement = $conn->prepare(
        "SELECT
            id,
            status
         FROM weekly_submissions
         WHERE field_officer_id = ?
         AND week_start = ?
         AND week_end = ?
         LIMIT 1
         FOR UPDATE"
    );

    $existingStatement->bind_param(
        'iss',
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    $existingStatement->execute();

    $existingSubmission =
        $existingStatement
            ->get_result()
            ->fetch_assoc();

    $existingStatement->close();

    $submissionId = 0;
    $previousStatus = null;
    $newStatus = 'submitted';
    $historyDecision = 'submitted';

    /*
    |--------------------------------------------------------------------------
    | Create a new weekly submission
    |--------------------------------------------------------------------------
    */

    if (!$existingSubmission) {
        $insertSubmissionStatement =
            $conn->prepare(
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

        $insertSubmissionStatement->bind_param(
            'iiiss',
            $fieldOfficerId,
            $adminOfficerId,
            $adminManagerId,
            $weekStart,
            $weekEnd
        );

        $insertSubmissionStatement->execute();

        $submissionId = (int) $conn->insert_id;

        $insertSubmissionStatement->close();
    } else {
        /*
        |--------------------------------------------------------------------------
        | Existing submission
        |--------------------------------------------------------------------------
        */

        $submissionId = (int) (
            $existingSubmission['id']
        );

        $previousStatus = (string) (
            $existingSubmission['status']
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate submission
        |--------------------------------------------------------------------------
        */

        $blockedStatuses = [
            'submitted',
            'resubmitted',
            'admin_officer_approved',
            'pending_manager_review',
            'final_approved'
        ];

        if (
            in_array(
                $previousStatus,
                $blockedStatuses,
                true
            )
        ) {
            $conn->rollback();

            $transactionStarted = false;

            redirectWeeklySubmission(
                'already_submitted'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Detect resubmission
        |--------------------------------------------------------------------------
        */

        $rejectedStatuses = [
            'admin_officer_rejected',
            'manager_rejected',
            'returned_for_correction'
        ];

        if (
            in_array(
                $previousStatus,
                $rejectedStatuses,
                true
            )
        ) {
            $newStatus = 'resubmitted';
            $historyDecision = 'resubmitted';
        }

        /*
        |--------------------------------------------------------------------------
        | Update existing submission
        |--------------------------------------------------------------------------
        */

        $updateSubmissionStatement =
            $conn->prepare(
                "UPDATE weekly_submissions
                 SET
                    admin_officer_id = ?,
                    admin_manager_id = ?,
                    status = ?,
                    latest_rejection_reason = NULL,
                    submitted_at = NOW(),
                    admin_reviewed_at = NULL,
                    manager_reviewed_at = NULL
                 WHERE id = ?"
            );

        $updateSubmissionStatement->bind_param(
            'iisi',
            $adminOfficerId,
            $adminManagerId,
            $newStatus,
            $submissionId
        );

        $updateSubmissionStatement->execute();
        $updateSubmissionStatement->close();

        /*
        |--------------------------------------------------------------------------
        | Remove old attendance links
        |--------------------------------------------------------------------------
        */

        $deleteRecordsStatement =
            $conn->prepare(
                "DELETE FROM weekly_submission_records
                 WHERE submission_id = ?"
            );

        $deleteRecordsStatement->bind_param(
            'i',
            $submissionId
        );

        $deleteRecordsStatement->execute();
        $deleteRecordsStatement->close();
    }

    /*
    |--------------------------------------------------------------------------
    | Link attendance records to weekly submission
    |--------------------------------------------------------------------------
    */

    $recordStatement = $conn->prepare(
        "INSERT INTO weekly_submission_records
        (
            submission_id,
            attendance_event_id
        )
        VALUES (?, ?)"
    );

    foreach ($attendanceIds as $attendanceId) {
        $recordStatement->bind_param(
            'ii',
            $submissionId,
            $attendanceId
        );

        $recordStatement->execute();
    }

    $recordStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Lock the submitted attendance records
    |--------------------------------------------------------------------------
    */

    $lockStatement = $conn->prepare(
        "UPDATE attendance_events
         SET is_locked = 1
         WHERE user_id = ?
         AND created_at >= ?
         AND created_at < ?"
    );

    $lockStatement->bind_param(
        'iss',
        $fieldOfficerId,
        $weekStartDateTime,
        $nextWeekStartDateTime
    );

    $lockStatement->execute();
    $lockStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Save approval history
    |--------------------------------------------------------------------------
    */

    $reviewerRole = 'field_officer';

    $historyComment =
        $historyDecision === 'resubmitted'
            ? 'Weekly attendance corrected and resubmitted.'
            : 'Weekly attendance submitted for Admin Officer review.';

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
            NULL,
            ?,
            ?
        )"
    );

    $historyStatement->bind_param(
        'iissssss',
        $submissionId,
        $fieldOfficerId,
        $reviewerRole,
        $historyDecision,
        $previousStatus,
        $newStatus,
        $historyComment,
        $ipAddress
    );

    $historyStatement->execute();
    $historyStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Save audit log
    |--------------------------------------------------------------------------
    */

    $auditAction =
        $historyDecision === 'resubmitted'
            ? 'WEEKLY_RESUBMITTED'
            : 'WEEKLY_SUBMITTED';

    $targetType = 'weekly_submission';

    $auditDetails =
        'Weekly attendance submitted from ' .
        $weekStart .
        ' to ' .
        $weekEnd .
        '. IN records: ' .
        $inCount .
        ', OUT records: ' .
        $outCount .
        '.';

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
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $auditStatement->bind_param(
        'ississ',
        $fieldOfficerId,
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

    redirectWeeklySubmission(
        $historyDecision === 'resubmitted'
            ? 'weekly_resubmitted'
            : 'weekly_submitted'
    );
} catch (Throwable $error) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable) {
            // Keep the original error.
        }
    }

    error_log(
        'FieldTrack weekly submission error: ' .
        $error->getMessage()
    );

    redirectWeeklySubmission(
        'weekly_submit_failed'
    );
}