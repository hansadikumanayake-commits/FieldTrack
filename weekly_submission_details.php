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
| Helper functions
|--------------------------------------------------------------------------
*/

function escapeSubmissionValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function submissionStatusLabel(string $status): string
{
    $labels = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'admin_officer_approved' => 'Admin Officer Approved',
        'admin_officer_rejected' => 'Admin Officer Rejected',
        'pending_manager_review' => 'Pending Manager Review',
        'manager_rejected' => 'Manager Rejected',
        'returned_for_correction' => 'Returned for Correction',
        'resubmitted' => 'Resubmitted',
        'final_approved' => 'Final Approved'
    ];

    return $labels[$status] ??
        ucwords(
            str_replace('_', ' ', $status)
        );
}

function submissionStatusClass(string $status): string
{
    if (
        in_array(
            $status,
            [
                'admin_officer_approved',
                'final_approved'
            ],
            true
        )
    ) {
        return 'status-approved';
    }

    if (
        in_array(
            $status,
            [
                'admin_officer_rejected',
                'manager_rejected',
                'returned_for_correction'
            ],
            true
        )
    ) {
        return 'status-rejected';
    }

    if (
        in_array(
            $status,
            [
                'submitted',
                'resubmitted',
                'pending_manager_review'
            ],
            true
        )
    ) {
        return 'status-pending';
    }

    return 'status-default';
}

function formatSubmissionDate(?string $date): string
{
    if (
        $date === null ||
        $date === ''
    ) {
        return '—';
    }

    try {
        return (new DateTime($date))
            ->format('d M Y');
    } catch (Throwable) {
        return $date;
    }
}

function formatSubmissionDateTime(?string $dateTime): string
{
    if (
        $dateTime === null ||
        $dateTime === ''
    ) {
        return '—';
    }

    try {
        return (new DateTime($dateTime))
            ->format('d M Y, h:i A');
    } catch (Throwable) {
        return $dateTime;
    }
}

function approvalDecisionLabel(string $decision): string
{
    $labels = [
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'returned' => 'Returned',
        'resubmitted' => 'Resubmitted'
    ];

    return $labels[$decision] ??
        ucwords(
            str_replace('_', ' ', $decision)
        );
}

/*
|--------------------------------------------------------------------------
| Current administrative user
|--------------------------------------------------------------------------
*/

$currentAdminId = currentUserId();
$currentAdminName = currentUserName();

/*
|--------------------------------------------------------------------------
| Determine current administrative role
|--------------------------------------------------------------------------
*/

if (hasRole('system_admin')) {
    $scopeRole = 'system_admin';
} elseif (hasRole('admin_manager')) {
    $scopeRole = 'admin_manager';

    requirePermission(
        'weekly.review_assigned'
    );
} elseif (hasRole('admin_officer')) {
    $scopeRole = 'admin_officer';

    requirePermission(
        'weekly.review_assigned'
    );
} else {
    http_response_code(403);

    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Validate submission ID
|--------------------------------------------------------------------------
*/

$submissionIdValue = trim(
    (string) ($_GET['id'] ?? '')
);

if (
    $submissionIdValue === '' ||
    !ctype_digit($submissionIdValue)
) {
    header(
        'Location: admin_weekly_submissions.php?msg=invalid_submission'
    );

    exit();
}

$submissionId = (int) $submissionIdValue;

if ($submissionId < 1) {
    header(
        'Location: admin_weekly_submissions.php?msg=invalid_submission'
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$submission = null;
$attendanceRecords = [];
$approvalHistory = [];
$dataError = '';

try {
    /*
    |--------------------------------------------------------------------------
    | Load submission according to administrative role
    |--------------------------------------------------------------------------
    */

    $submissionSql = "
        SELECT
            weekly_submissions.id,
            weekly_submissions.field_officer_id,
            weekly_submissions.admin_officer_id,
            weekly_submissions.admin_manager_id,
            weekly_submissions.week_start,
            weekly_submissions.week_end,
            weekly_submissions.status,
            weekly_submissions.latest_rejection_reason,
            weekly_submissions.submitted_at,
            weekly_submissions.admin_reviewed_at,
            weekly_submissions.manager_reviewed_at,
            weekly_submissions.created_at,
            weekly_submissions.updated_at,

            field_officer.name
                AS field_officer_name,

            field_officer.username
                AS field_officer_username,

            admin_officer.name
                AS admin_officer_name,

            admin_officer.username
                AS admin_officer_username,

            admin_manager.name
                AS admin_manager_name,

            admin_manager.username
                AS admin_manager_username

        FROM weekly_submissions

        INNER JOIN users AS field_officer
            ON field_officer.id =
               weekly_submissions.field_officer_id

        INNER JOIN users AS admin_officer
            ON admin_officer.id =
               weekly_submissions.admin_officer_id

        INNER JOIN users AS admin_manager
            ON admin_manager.id =
               weekly_submissions.admin_manager_id

        WHERE weekly_submissions.id = ?
    ";

    if ($scopeRole === 'admin_officer') {
        $submissionSql .= "
            AND weekly_submissions.admin_officer_id = ?
        ";
    }

    if ($scopeRole === 'admin_manager') {
        $submissionSql .= "
            AND weekly_submissions.admin_manager_id = ?
        ";
    }

    $submissionSql .= " LIMIT 1";

    $submissionStatement = $conn->prepare(
        $submissionSql
    );

    if ($scopeRole === 'system_admin') {
        $submissionStatement->bind_param(
            'i',
            $submissionId
        );
    } else {
        $submissionStatement->bind_param(
            'ii',
            $submissionId,
            $currentAdminId
        );
    }

    $submissionStatement->execute();

    $submission = $submissionStatement
        ->get_result()
        ->fetch_assoc();

    $submissionStatement->close();

    if (!$submission) {
        http_response_code(404);

        exit(
            'The weekly submission was not found or is not assigned to you.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent self-review
    |--------------------------------------------------------------------------
    */

    preventSelfApproval(
        (int) $submission['field_officer_id']
    );

    /*
    |--------------------------------------------------------------------------
    | Load attendance records connected to submission
    |--------------------------------------------------------------------------
    */

    $attendanceStatement = $conn->prepare(
        "SELECT
            attendance_events.id,
            attendance_events.action_type,
            attendance_events.latitude,
            attendance_events.longitude,
            attendance_events.photo_path,
            attendance_events.is_locked,
            attendance_events.created_at,
            attendance_events.updated_at

         FROM weekly_submission_records

         INNER JOIN attendance_events
            ON attendance_events.id =
               weekly_submission_records.attendance_event_id

         WHERE weekly_submission_records.submission_id = ?

         ORDER BY
            attendance_events.created_at ASC,
            attendance_events.id ASC"
    );

    $attendanceStatement->bind_param(
        'i',
        $submissionId
    );

    $attendanceStatement->execute();

    $attendanceResult =
        $attendanceStatement->get_result();

    while (
        $attendanceRecord =
        $attendanceResult->fetch_assoc()
    ) {
        $attendanceRecords[] =
            $attendanceRecord;
    }

    $attendanceStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Load approval history
    |--------------------------------------------------------------------------
    */

    $historyStatement = $conn->prepare(
        "SELECT
            approval_history.id,
            approval_history.reviewer_role,
            approval_history.decision,
            approval_history.previous_status,
            approval_history.new_status,
            approval_history.reason,
            approval_history.comment,
            approval_history.ip_address,
            approval_history.created_at,

            reviewer.name AS reviewer_name,
            reviewer.username AS reviewer_username

         FROM approval_history

         LEFT JOIN users AS reviewer
            ON reviewer.id =
               approval_history.reviewer_id

         WHERE approval_history.submission_id = ?

         ORDER BY
            approval_history.created_at ASC,
            approval_history.id ASC"
    );

    $historyStatement->bind_param(
        'i',
        $submissionId
    );

    $historyStatement->execute();

    $historyResult =
        $historyStatement->get_result();

    while (
        $historyRecord =
        $historyResult->fetch_assoc()
    ) {
        $approvalHistory[] =
            $historyRecord;
    }

    $historyStatement->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack submission details error: ' .
        $error->getMessage()
    );

    $dataError =
        'The submission details could not be loaded.';
}

/*
|--------------------------------------------------------------------------
| Count attendance records
|--------------------------------------------------------------------------
*/

$totalAttendance = count(
    $attendanceRecords
);

$inCount = 0;
$outCount = 0;

foreach ($attendanceRecords as $record) {
    if ($record['action_type'] === 'IN') {
        $inCount++;
    }

    if ($record['action_type'] === 'OUT') {
        $outCount++;
    }
}

/*
|--------------------------------------------------------------------------
| Determine whether current user can review
|--------------------------------------------------------------------------
*/

$currentStatus = (string) (
    $submission['status'] ?? ''
);

$canAdminOfficerReview = (
    $scopeRole === 'admin_officer' &&
    (int) $submission['admin_officer_id'] ===
        $currentAdminId &&
    in_array(
        $currentStatus,
        [
            'submitted',
            'resubmitted'
        ],
        true
    )
);

$canAdminManagerReview = (
    $scopeRole === 'admin_manager' &&
    (int) $submission['admin_manager_id'] ===
        $currentAdminId &&
    in_array(
        $currentStatus,
        [
            'admin_officer_approved',
            'pending_manager_review'
        ],
        true
    )
);

$canReview =
    $canAdminOfficerReview ||
    $canAdminManagerReview;

$scopeLabel = match ($scopeRole) {
    'system_admin' =>
        'System Administrator',

    'admin_manager' =>
        'Admin Manager',

    'admin_officer' =>
        'Admin Officer',

    default =>
        'Administrator'
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Weekly Submission Details | FieldTrack
    </title>

    <link
        rel="stylesheet"
        href="admin_style.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .details-page {
            width: min(1450px, calc(100% - 32px));
            margin: 30px auto;
        }

        .details-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .details-header h1 {
            margin: 0 0 8px;
        }

        .details-header p {
            margin: 0;
            color: #64748b;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-button {
            display: inline-block;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #0f172a;
            color: #ffffff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .page-button.secondary {
            background: #475569;
        }

        .details-card {
            margin-bottom: 24px;
            padding: 22px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.08);
        }

        .details-card h2 {
            margin: 0 0 18px;
        }

        .information-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(180px, 1fr));
            gap: 16px;
        }

        .information-item {
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #f8fafc;
        }

        .information-item span {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
        }

        .information-item strong {
            display: block;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-default {
            background: #e2e8f0;
            color: #334155;
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(160px, 1fr));
            gap: 16px;
        }

        .summary-box {
            padding: 18px;
            border-radius: 10px;
            background: #f8fafc;
            text-align: center;
        }

        .summary-box span {
            display: block;
            margin-bottom: 8px;
            color: #64748b;
            font-weight: 700;
        }

        .summary-box strong {
            font-size: 28px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            color: #334155;
            font-size: 13px;
            text-transform: uppercase;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .action-in {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: 800;
        }

        .action-out {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 800;
        }

        .photo-preview {
            width: 90px;
            height: 70px;
            border-radius: 7px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
        }

        .location-link {
            display: inline-block;
            margin-top: 6px;
            color: #2563eb;
            font-weight: 700;
            text-decoration: none;
        }

        .review-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(280px, 1fr));
            gap: 20px;
        }

        .review-form {
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .review-form h3 {
            margin-top: 0;
        }

        .review-form label {
            display: block;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .review-form textarea {
            width: 100%;
            min-height: 110px;
            margin-bottom: 14px;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            resize: vertical;
            font: inherit;
        }

        .approve-button,
        .reject-button {
            width: 100%;
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            color: #ffffff;
            font-weight: 800;
            cursor: pointer;
        }

        .approve-button {
            background: #16a34a;
        }

        .reject-button {
            background: #dc2626;
        }

        .message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
        }

        .error-message {
            border: 1px solid #fca5a5;
            background: #fee2e2;
            color: #991b1b;
        }

        .warning-message {
            border: 1px solid #fcd34d;
            background: #fef3c7;
            color: #92400e;
        }

        .rejection-box {
            margin-top: 18px;
            padding: 15px;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-message {
            padding: 30px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 1000px) {
            .information-grid {
                grid-template-columns:
                    repeat(2, minmax(180px, 1fr));
            }
        }

        @media (max-width: 700px) {
            .details-page {
                width: calc(100% - 20px);
                margin-top: 18px;
            }

            .details-header {
                flex-direction: column;
            }

            .information-grid,
            .summary-grid,
            .review-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<main class="details-page">

    <header class="details-header">
        <div>
            <h1>
                Weekly Submission
                #<?= (int) $submissionId ?>
            </h1>

            <p>
                Logged in as
                <?= escapeSubmissionValue(
                    $currentAdminName
                ) ?>

                — <?= escapeSubmissionValue(
                    $scopeLabel
                ) ?>
            </p>
        </div>

        <div class="header-actions">
            <a
                class="page-button secondary"
                href="admin_weekly_submissions.php"
            >
                Back to Submissions
            </a>

            <a
                class="page-button"
                href="admin_panel.php"
            >
                Dashboard
            </a>
        </div>
    </header>

    <?php if ($dataError !== ''): ?>
        <div class="message error-message">
            <?= escapeSubmissionValue(
                $dataError
            ) ?>
        </div>
    <?php endif; ?>

    <section class="details-card">

        <h2>Submission Information</h2>

        <div class="information-grid">

            <div class="information-item">
                <span>Field Officer</span>

                <strong>
                    <?= escapeSubmissionValue(
                        $submission[
                            'field_officer_name'
                        ]
                    ) ?>
                </strong>

                @<?= escapeSubmissionValue(
                    $submission[
                        'field_officer_username'
                    ]
                ) ?>
            </div>

            <div class="information-item">
                <span>Week</span>

                <strong>
                    <?= escapeSubmissionValue(
                        formatSubmissionDate(
                            $submission['week_start']
                        )
                    ) ?>

                    to

                    <?= escapeSubmissionValue(
                        formatSubmissionDate(
                            $submission['week_end']
                        )
                    ) ?>
                </strong>
            </div>

            <div class="information-item">
                <span>Status</span>

                <strong>
                    <span
                        class="status-badge
                        <?= escapeSubmissionValue(
                            submissionStatusClass(
                                $submission['status']
                            )
                        ) ?>"
                    >
                        <?= escapeSubmissionValue(
                            submissionStatusLabel(
                                $submission['status']
                            )
                        ) ?>
                    </span>
                </strong>
            </div>

            <div class="information-item">
                <span>Submitted At</span>

                <strong>
                    <?= escapeSubmissionValue(
                        formatSubmissionDateTime(
                            $submission['submitted_at']
                        )
                    ) ?>
                </strong>
            </div>

            <div class="information-item">
                <span>Admin Officer</span>

                <strong>
                    <?= escapeSubmissionValue(
                        $submission[
                            'admin_officer_name'
                        ]
                    ) ?>
                </strong>

                @<?= escapeSubmissionValue(
                    $submission[
                        'admin_officer_username'
                    ]
                ) ?>
            </div>

            <div class="information-item">
                <span>Admin Manager</span>

                <strong>
                    <?= escapeSubmissionValue(
                        $submission[
                            'admin_manager_name'
                        ]
                    ) ?>
                </strong>

                @<?= escapeSubmissionValue(
                    $submission[
                        'admin_manager_username'
                    ]
                ) ?>
            </div>

            <div class="information-item">
                <span>Admin Review Date</span>

                <strong>
                    <?= escapeSubmissionValue(
                        formatSubmissionDateTime(
                            $submission[
                                'admin_reviewed_at'
                            ]
                        )
                    ) ?>
                </strong>
            </div>

            <div class="information-item">
                <span>Manager Review Date</span>

                <strong>
                    <?= escapeSubmissionValue(
                        formatSubmissionDateTime(
                            $submission[
                                'manager_reviewed_at'
                            ]
                        )
                    ) ?>
                </strong>
            </div>

        </div>

        <?php
        if (
            !empty(
                $submission[
                    'latest_rejection_reason'
                ]
            )
        ):
        ?>

            <div class="rejection-box">
                <strong>
                    Latest rejection reason:
                </strong>

                <br><br>

                <?= nl2br(
                    escapeSubmissionValue(
                        $submission[
                            'latest_rejection_reason'
                        ]
                    )
                ) ?>
            </div>

        <?php endif; ?>

    </section>

    <section class="details-card">

        <h2>Attendance Summary</h2>

        <div class="summary-grid">

            <div class="summary-box">
                <span>Total Records</span>
                <strong><?= $totalAttendance ?></strong>
            </div>

            <div class="summary-box">
                <span>IN Records</span>
                <strong><?= $inCount ?></strong>
            </div>

            <div class="summary-box">
                <span>OUT Records</span>
                <strong><?= $outCount ?></strong>
            </div>

        </div>

    </section>

    <section class="details-card">

        <h2>Attendance Records</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Action</th>
                        <th>Date and Time</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Location</th>
                        <th>Photo</th>
                        <th>Locked</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($attendanceRecords)): ?>

                    <tr>
                        <td
                            colspan="8"
                            class="empty-message"
                        >
                            No attendance records are linked
                            to this submission.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $attendanceRecords
                        as $record
                    ): ?>

                        <tr>
                            <td>
                                #<?= (int) $record['id'] ?>
                            </td>

                            <td>
                                <span
                                    class="<?= $record[
                                        'action_type'
                                    ] === 'IN'
                                        ? 'action-in'
                                        : 'action-out' ?>"
                                >
                                    <?= escapeSubmissionValue(
                                        $record[
                                            'action_type'
                                        ]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeSubmissionValue(
                                    formatSubmissionDateTime(
                                        $record[
                                            'created_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= escapeSubmissionValue(
                                    $record['latitude']
                                ) ?>
                            </td>

                            <td>
                                <?= escapeSubmissionValue(
                                    $record['longitude']
                                ) ?>
                            </td>

                            <td>
                                <a
                                    class="location-link"
                                    href="https://www.google.com/maps?q=<?= rawurlencode(
                                        (string) $record[
                                            'latitude'
                                        ]
                                    ) ?>,<?= rawurlencode(
                                        (string) $record[
                                            'longitude'
                                        ]
                                    ) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Open Map
                                </a>
                            </td>

                            <td>
                                <?php
                                if (
                                    !empty(
                                        $record['photo_path']
                                    )
                                ):
                                ?>

                                    <a
                                        href="<?= escapeSubmissionValue(
                                            $record[
                                                'photo_path'
                                            ]
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <img
                                            class="photo-preview"
                                            src="<?= escapeSubmissionValue(
                                                $record[
                                                    'photo_path'
                                                ]
                                            ) ?>"
                                            alt="Attendance photo"
                                        >
                                    </a>

                                <?php else: ?>

                                    No photo

                                <?php endif; ?>
                            </td>

                            <td>
                                <?= (int) $record[
                                    'is_locked'
                                ] === 1
                                    ? 'Yes'
                                    : 'No' ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <section class="details-card">

        <h2>Approval History</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reviewer</th>
                        <th>Role</th>
                        <th>Decision</th>
                        <th>Status Change</th>
                        <th>Reason</th>
                        <th>Comment</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($approvalHistory)): ?>

                    <tr>
                        <td
                            colspan="7"
                            class="empty-message"
                        >
                            No approval history is available.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $approvalHistory
                        as $history
                    ): ?>

                        <tr>
                            <td>
                                <?= escapeSubmissionValue(
                                    formatSubmissionDateTime(
                                        $history[
                                            'created_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= escapeSubmissionValue(
                                    $history[
                                        'reviewer_name'
                                    ] ??
                                    'Unknown User'
                                ) ?>
                            </td>

                            <td>
                                <?= escapeSubmissionValue(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $history[
                                                'reviewer_role'
                                            ]
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= escapeSubmissionValue(
                                    approvalDecisionLabel(
                                        $history[
                                            'decision'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= escapeSubmissionValue(
                                    submissionStatusLabel(
                                        (string) (
                                            $history[
                                                'previous_status'
                                            ] ?? 'draft'
                                        )
                                    )
                                ) ?>

                                →

                                <?= escapeSubmissionValue(
                                    submissionStatusLabel(
                                        $history[
                                            'new_status'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    escapeSubmissionValue(
                                        $history['reason'] ??
                                        '—'
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= nl2br(
                                    escapeSubmissionValue(
                                        $history['comment'] ??
                                        '—'
                                    )
                                ) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <?php if ($canReview): ?>

        <section class="details-card">

            <h2>Review Submission</h2>

            <div class="review-grid">

                <form
                    class="review-form"
                    action="process_weekly_review.php"
                    method="POST"
                    onsubmit="return confirm('Approve this weekly submission?');"
                >

                    <h3>Approve Submission</h3>

                    <input
                        type="hidden"
                        name="submission_id"
                        value="<?= (int) $submissionId ?>"
                    >

                    <input
                        type="hidden"
                        name="review_action"
                        value="<?= $canAdminOfficerReview
                            ? 'approve_level1'
                            : 'approve_final' ?>"
                    >

                    <label for="approval_comment">
                        Comment
                    </label>

                    <textarea
                        id="approval_comment"
                        name="comment"
                        maxlength="1000"
                        placeholder="Optional approval comment"
                    ></textarea>

                    <button
                        class="approve-button"
                        type="submit"
                    >
                        <?= $canAdminOfficerReview
                            ? 'Admin Officer Approve'
                            : 'Final Approve' ?>
                    </button>

                </form>

                <form
                    class="review-form"
                    action="process_weekly_review.php"
                    method="POST"
                    onsubmit="return confirm('Reject and return this weekly submission?');"
                >

                    <h3>Reject Submission</h3>

                    <input
                        type="hidden"
                        name="submission_id"
                        value="<?= (int) $submissionId ?>"
                    >

                    <input
                        type="hidden"
                        name="review_action"
                        value="<?= $canAdminOfficerReview
                            ? 'reject_level1'
                            : 'reject_final' ?>"
                    >

                    <label for="rejection_reason">
                        Rejection Reason
                    </label>

                    <textarea
                        id="rejection_reason"
                        name="reason"
                        maxlength="2000"
                        required
                        placeholder="Explain why this submission is being rejected"
                    ></textarea>

                    <button
                        class="reject-button"
                        type="submit"
                    >
                        <?= $canAdminOfficerReview
                            ? 'Admin Officer Reject'
                            : 'Final Reject' ?>
                    </button>

                </form>

            </div>

        </section>

    <?php else: ?>

        <div class="message warning-message">
            This submission is not currently available
            for review by your role.
        </div>

    <?php endif; ?>

</main>

</body>
</html>