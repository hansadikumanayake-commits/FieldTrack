<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
*/

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

function weeklyListEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function weeklyListFormatRole(string $role): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $role
        )
    );
}

function weeklyListFormatDate(?string $date): string
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

function weeklyListFormatDateTime(
    ?string $dateTime
): string {
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

function weeklyListValidDate(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    return (
        $dateObject !== false &&
        $dateObject->format('Y-m-d') === $date
    );
}

function weeklyListStatusLabel(
    string $status
): string {
    $labels = [
        'draft' =>
            'Draft',

        'submitted' =>
            'Submitted',

        'admin_officer_approved' =>
            'Admin Officer Approved',

        'admin_officer_rejected' =>
            'Admin Officer Rejected',

        'pending_manager_review' =>
            'Pending Manager Review',

        'manager_rejected' =>
            'Manager Rejected',

        'returned_for_correction' =>
            'Returned for Correction',

        'resubmitted' =>
            'Resubmitted',

        'final_approved' =>
            'Final Approved'
    ];

    return $labels[$status] ??
        ucwords(
            str_replace(
                '_',
                ' ',
                $status
            )
        );
}

function weeklyListStatusClass(
    string $status
): string {
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

function weeklyListPageUrl(
    int $page,
    int $fieldOfficerId,
    string $status,
    string $fromDate,
    string $toDate
): string {
    return 'admin_weekly_submissions.php?' .
        http_build_query([
            'page' => $page,

            'field_officer_id' =>
                $fieldOfficerId > 0
                    ? $fieldOfficerId
                    : '',

            'status' => $status,
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]);
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
| Determine role and submission scope
|--------------------------------------------------------------------------
*/

if (hasRole('system_admin')) {
    $scopeRole = 'system_admin';
} elseif (hasRole('admin_manager')) {
    $scopeRole = 'admin_manager';
} elseif (hasRole('admin_officer')) {
    $scopeRole = 'admin_officer';
} else {
    http_response_code(403);

    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Admin Officer and Manager need review permission
|--------------------------------------------------------------------------
*/

if (
    $scopeRole !== 'system_admin' &&
    !currentUserHasPermission(
        'weekly.review_assigned'
    )
) {
    http_response_code(403);

    exit(
        'You do not have permission to review weekly submissions.'
    );
}

/*
|--------------------------------------------------------------------------
| Page messages
|--------------------------------------------------------------------------
*/

$messageCode = trim(
    (string) ($_GET['msg'] ?? '')
);

$messageText = '';
$messageClass = '';

$messageMap = [
    'level1_approved' => [
        'The weekly submission was approved and forwarded to the Admin Manager.',
        'success-message'
    ],

    'level1_rejected' => [
        'The weekly submission was rejected and returned to the Field Officer.',
        'success-message'
    ],

    'final_approved' => [
        'The weekly submission received final approval.',
        'success-message'
    ],

    'final_rejected' => [
        'The weekly submission was rejected by the Admin Manager.',
        'success-message'
    ],

    'invalid_submission' => [
        'The selected weekly submission is invalid.',
        'error-message'
    ],

    'review_failed' => [
        'The weekly submission review could not be completed.',
        'error-message'
    ]
];

if (isset($messageMap[$messageCode])) {
    $messageText =
        $messageMap[$messageCode][0];

    $messageClass =
        $messageMap[$messageCode][1];
}

/*
|--------------------------------------------------------------------------
| Read filters
|--------------------------------------------------------------------------
*/

$fieldOfficerIdValue = trim(
    (string) (
        $_GET['field_officer_id'] ?? ''
    )
);

$fieldOfficerFilter = 0;

if (
    $fieldOfficerIdValue !== '' &&
    ctype_digit($fieldOfficerIdValue)
) {
    $fieldOfficerFilter =
        (int) $fieldOfficerIdValue;
}

$statusFilter = trim(
    (string) ($_GET['status'] ?? '')
);

$allowedStatuses = [
    '',
    'draft',
    'submitted',
    'admin_officer_approved',
    'admin_officer_rejected',
    'pending_manager_review',
    'manager_rejected',
    'returned_for_correction',
    'resubmitted',
    'final_approved'
];

if (
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = '';
}

$fromDate = trim(
    (string) ($_GET['from_date'] ?? '')
);

$toDate = trim(
    (string) ($_GET['to_date'] ?? '')
);

if (!weeklyListValidDate($fromDate)) {
    $fromDate = '';
}

if (!weeklyListValidDate($toDate)) {
    $toDate = '';
}

if (
    $fromDate !== '' &&
    $toDate !== '' &&
    $fromDate > $toDate
) {
    $temporaryDate = $fromDate;
    $fromDate = $toDate;
    $toDate = $temporaryDate;
}

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$recordsPerPage = 20;

$pageValue = trim(
    (string) ($_GET['page'] ?? '1')
);

$currentPage = 1;

if (
    ctype_digit($pageValue) &&
    (int) $pageValue > 0
) {
    $currentPage = (int) $pageValue;
}

/*
|--------------------------------------------------------------------------
| Data containers
|--------------------------------------------------------------------------
*/

$fieldOfficers = [];
$submissions = [];

$totalRecords = 0;
$totalPages = 1;

$dataError = '';

try {
    /*
    |--------------------------------------------------------------------------
    | Load Field Officers for filter
    |--------------------------------------------------------------------------
    */

    if ($scopeRole === 'system_admin') {
        $fieldOfficerStatement =
            $conn->prepare(
                "SELECT DISTINCT
                    users.id,
                    users.name,
                    users.username

                 FROM users

                 INNER JOIN user_roles
                    ON user_roles.user_id =
                       users.id

                 INNER JOIN roles
                    ON roles.id =
                       user_roles.role_id

                 WHERE roles.role_name =
                       'field_officer'

                 ORDER BY
                    users.name ASC,
                    users.username ASC"
            );
    } elseif ($scopeRole === 'admin_officer') {
        $fieldOfficerStatement =
            $conn->prepare(
                "SELECT DISTINCT
                    field_officer.id,
                    field_officer.name,
                    field_officer.username

                 FROM officer_assignments

                 INNER JOIN users AS field_officer
                    ON field_officer.id =
                       officer_assignments.field_officer_id

                 WHERE officer_assignments.admin_officer_id = ?

                 ORDER BY
                    field_officer.name ASC,
                    field_officer.username ASC"
            );

        $fieldOfficerStatement->bind_param(
            'i',
            $currentAdminId
        );
    } else {
        $fieldOfficerStatement =
            $conn->prepare(
                "SELECT DISTINCT
                    field_officer.id,
                    field_officer.name,
                    field_officer.username

                 FROM officer_assignments

                 INNER JOIN users AS field_officer
                    ON field_officer.id =
                       officer_assignments.field_officer_id

                 WHERE officer_assignments.admin_manager_id = ?

                 ORDER BY
                    field_officer.name ASC,
                    field_officer.username ASC"
            );

        $fieldOfficerStatement->bind_param(
            'i',
            $currentAdminId
        );
    }

    $fieldOfficerStatement->execute();

    $fieldOfficerResult =
        $fieldOfficerStatement->get_result();

    while (
        $fieldOfficer =
        $fieldOfficerResult->fetch_assoc()
    ) {
        $fieldOfficers[] = $fieldOfficer;
    }

    $fieldOfficerStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Shared WHERE conditions
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT
            COUNT(*) AS total

        FROM weekly_submissions

        WHERE
        (
            ? = 'system_admin'

            OR
            (
                ? = 'admin_officer'
                AND weekly_submissions.admin_officer_id = ?
            )

            OR
            (
                ? = 'admin_manager'
                AND weekly_submissions.admin_manager_id = ?
            )
        )

        AND
        (
            ? = 0
            OR weekly_submissions.field_officer_id = ?
        )

        AND
        (
            ? = ''
            OR weekly_submissions.status = ?
        )

        AND
        (
            ? = ''
            OR weekly_submissions.week_start >= ?
        )

        AND
        (
            ? = ''
            OR weekly_submissions.week_end <= ?
        )
    ";

    /*
    |--------------------------------------------------------------------------
    | Count filtered submissions
    |--------------------------------------------------------------------------
    */

    $countStatement = $conn->prepare(
        $countSql
    );

    $countStatement->bind_param(
        'ssisiiissssss',
        $scopeRole,
        $scopeRole,
        $currentAdminId,
        $scopeRole,
        $currentAdminId,
        $fieldOfficerFilter,
        $fieldOfficerFilter,
        $statusFilter,
        $statusFilter,
        $fromDate,
        $fromDate,
        $toDate,
        $toDate
    );

    $countStatement->execute();

    $countRow = $countStatement
        ->get_result()
        ->fetch_assoc();

    $countStatement->close();

    $totalRecords =
        (int) ($countRow['total'] ?? 0);

    $totalPages = max(
        1,
        (int) ceil(
            $totalRecords /
            $recordsPerPage
        )
    );

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset =
        ($currentPage - 1) *
        $recordsPerPage;

    /*
    |--------------------------------------------------------------------------
    | Load filtered submissions
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
                AS admin_manager_username,

            (
                SELECT COUNT(*)

                FROM weekly_submission_records

                WHERE weekly_submission_records.submission_id =
                      weekly_submissions.id
            ) AS attendance_record_count

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

        WHERE
        (
            ? = 'system_admin'

            OR
            (
                ? = 'admin_officer'
                AND weekly_submissions.admin_officer_id = ?
            )

            OR
            (
                ? = 'admin_manager'
                AND weekly_submissions.admin_manager_id = ?
            )
        )

        AND
        (
            ? = 0
            OR weekly_submissions.field_officer_id = ?
        )

        AND
        (
            ? = ''
            OR weekly_submissions.status = ?
        )

        AND
        (
            ? = ''
            OR weekly_submissions.week_start >= ?
        )

        AND
        (
            ? = ''
            OR weekly_submissions.week_end <= ?
        )

        ORDER BY
            CASE
                WHEN weekly_submissions.status IN
                (
                    'submitted',
                    'resubmitted',
                    'pending_manager_review',
                    'admin_officer_approved'
                )
                THEN 0
                ELSE 1
            END ASC,

            weekly_submissions.submitted_at DESC,
            weekly_submissions.id DESC

        LIMIT ?
        OFFSET ?
    ";

    $submissionStatement = $conn->prepare(
        $submissionSql
    );

    $submissionStatement->bind_param(
        'ssisiiissssssii',
        $scopeRole,
        $scopeRole,
        $currentAdminId,
        $scopeRole,
        $currentAdminId,
        $fieldOfficerFilter,
        $fieldOfficerFilter,
        $statusFilter,
        $statusFilter,
        $fromDate,
        $fromDate,
        $toDate,
        $toDate,
        $recordsPerPage,
        $offset
    );

    $submissionStatement->execute();

    $submissionResult =
        $submissionStatement->get_result();

    while (
        $submission =
        $submissionResult->fetch_assoc()
    ) {
        $submissions[] = $submission;
    }

    $submissionStatement->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack weekly submissions list error: ' .
        $error->getMessage()
    );

    $dataError =
        'The weekly submissions could not be loaded.';
}

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
        Weekly Submissions | FieldTrack
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

        .weekly-page {
            width: min(1500px, calc(100% - 32px));
            margin: 30px auto 50px;
        }

        .weekly-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .weekly-header h1 {
            margin: 0 0 8px;
        }

        .weekly-header p {
            margin: 0;
            color: #64748b;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-weight: 700;
        }

        .success-message {
            border: 1px solid #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .error-message {
            border: 1px solid #fca5a5;
            background: #fee2e2;
            color: #991b1b;
        }

        .filter-card,
        .table-card {
            margin-bottom: 24px;
            padding: 22px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.08);
        }

        .filter-card h2,
        .table-card h2 {
            margin: 0 0 18px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(180px, 1fr));
            gap: 15px;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .filter-field label {
            color: #334155;
            font-size: 14px;
            font-weight: 700;
        }

        .filter-field input,
        .filter-field select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            color: #0f172a;
            font: inherit;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .summary-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 17px;
            color: #475569;
            font-weight: 700;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .submission-table {
            width: 100%;
            min-width: 1450px;
            border-collapse: collapse;
        }

        .submission-table th,
        .submission-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        .submission-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .submission-table tbody tr:hover {
            background: #f8fafc;
        }

        .submission-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .user-name {
            display: block;
            font-weight: 700;
        }

        .username {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-default {
            background: #e2e8f0;
            color: #334155;
        }

        .review-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 7px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .review-button:hover {
            background: #1d4ed8;
        }

        .rejection-reason {
            display: block;
            max-width: 270px;
            color: #991b1b;
            font-size: 12px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .empty-message {
            padding: 35px;
            text-align: center;
            color: #64748b;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 22px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            padding: 9px 11px;
            border-radius: 7px;
            text-decoration: none;
            font-weight: 700;
        }

        .pagination a {
            background: #e2e8f0;
            color: #0f172a;
        }

        .pagination .active-page {
            background: #2563eb;
            color: #ffffff;
        }

        .pagination .disabled {
            background: #f1f5f9;
            color: #94a3b8;
        }

        @media (max-width: 1000px) {
            .filter-grid {
                grid-template-columns:
                    repeat(2, minmax(180px, 1fr));
            }
        }

        @media (max-width: 650px) {
            .weekly-page {
                width: calc(100% - 20px);
                margin-top: 18px;
            }

            .weekly-header {
                flex-direction: column;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .header-actions .page-button {
                width: 100%;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .page-button {
                width: 100%;
            }

            .summary-line {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<main class="weekly-page">

    <header class="weekly-header">

        <div>
            <h1>Weekly Submissions</h1>

            <p>
                Review weekly attendance submissions available
                to your administrative role.
            </p>

            <p>
                Logged in as
                <?= weeklyListEscape(
                    $currentAdminName
                ) ?>

                —
                <?= weeklyListEscape(
                    weeklyListFormatRole(
                        $scopeRole
                    )
                ) ?>
            </p>
        </div>

        <div class="header-actions">

            <a
                class="page-button secondary"
                href="admin_panel.php"
            >
                Back to Dashboard
            </a>

            <a
                class="page-button"
                href="logout.php"
            >
                Logout
            </a>

        </div>

    </header>

    <?php if ($messageText !== ''): ?>

        <div
            class="message
            <?= weeklyListEscape(
                $messageClass
            ) ?>"
        >
            <?= weeklyListEscape(
                $messageText
            ) ?>
        </div>

    <?php endif; ?>

    <?php if ($dataError !== ''): ?>

        <div class="message error-message">
            <?= weeklyListEscape(
                $dataError
            ) ?>
        </div>

    <?php endif; ?>

    <section class="filter-card">

        <h2>Filter Submissions</h2>

        <form method="GET">

            <div class="filter-grid">

                <div class="filter-field">

                    <label for="field_officer_id">
                        Field Officer
                    </label>

                    <select
                        id="field_officer_id"
                        name="field_officer_id"
                    >

                        <option value="">
                            All Field Officers
                        </option>

                        <?php foreach (
                            $fieldOfficers
                            as $fieldOfficer
                        ): ?>

                            <option
                                value="<?= (int) $fieldOfficer['id'] ?>"
                                <?= $fieldOfficerFilter ===
                                    (int) $fieldOfficer['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= weeklyListEscape(
                                    $fieldOfficer['name']
                                ) ?>

                                (@<?= weeklyListEscape(
                                    $fieldOfficer['username']
                                ) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-field">

                    <label for="status">
                        Submission Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach (
                            array_slice(
                                $allowedStatuses,
                                1
                            )
                            as $allowedStatus
                        ): ?>

                            <option
                                value="<?= weeklyListEscape(
                                    $allowedStatus
                                ) ?>"
                                <?= $statusFilter ===
                                    $allowedStatus
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= weeklyListEscape(
                                    weeklyListStatusLabel(
                                        $allowedStatus
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-field">

                    <label for="from_date">
                        Week From
                    </label>

                    <input
                        type="date"
                        id="from_date"
                        name="from_date"
                        value="<?= weeklyListEscape(
                            $fromDate
                        ) ?>"
                    >

                </div>

                <div class="filter-field">

                    <label for="to_date">
                        Week To
                    </label>

                    <input
                        type="date"
                        id="to_date"
                        name="to_date"
                        value="<?= weeklyListEscape(
                            $toDate
                        ) ?>"
                    >

                </div>

            </div>

            <div class="filter-actions">

                <button
                    class="page-button"
                    type="submit"
                >
                    Apply Filters
                </button>

                <a
                    class="page-button secondary"
                    href="admin_weekly_submissions.php"
                >
                    Reset Filters
                </a>

            </div>

        </form>

    </section>

    <section class="table-card">

        <div class="summary-line">

            <h2>Submission Records</h2>

            <div>
                Total:
                <?= $totalRecords ?>

                &nbsp;|&nbsp;

                Page:
                <?= $currentPage ?>
                of
                <?= $totalPages ?>
            </div>

        </div>

        <div class="table-wrapper">

            <table class="submission-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Field Officer</th>
                        <th>Week</th>
                        <th>Records</th>
                        <th>Admin Officer</th>
                        <th>Admin Manager</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                        <th>Rejection Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($submissions)): ?>

                    <tr>
                        <td
                            colspan="10"
                            class="empty-message"
                        >
                            No weekly submissions were found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $submissions
                        as $submission
                    ): ?>

                        <tr>

                            <td>
                                #<?= (int) $submission['id'] ?>
                            </td>

                            <td>
                                <span class="user-name">
                                    <?= weeklyListEscape(
                                        $submission[
                                            'field_officer_name'
                                        ]
                                    ) ?>
                                </span>

                                <span class="username">
                                    @<?= weeklyListEscape(
                                        $submission[
                                            'field_officer_username'
                                        ]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= weeklyListEscape(
                                    weeklyListFormatDate(
                                        $submission[
                                            'week_start'
                                        ]
                                    )
                                ) ?>

                                <br>

                                to

                                <br>

                                <?= weeklyListEscape(
                                    weeklyListFormatDate(
                                        $submission[
                                            'week_end'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= (int) $submission[
                                    'attendance_record_count'
                                ] ?>
                            </td>

                            <td>
                                <span class="user-name">
                                    <?= weeklyListEscape(
                                        $submission[
                                            'admin_officer_name'
                                        ]
                                    ) ?>
                                </span>

                                <span class="username">
                                    @<?= weeklyListEscape(
                                        $submission[
                                            'admin_officer_username'
                                        ]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span class="user-name">
                                    <?= weeklyListEscape(
                                        $submission[
                                            'admin_manager_name'
                                        ]
                                    ) ?>
                                </span>

                                <span class="username">
                                    @<?= weeklyListEscape(
                                        $submission[
                                            'admin_manager_username'
                                        ]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= weeklyListEscape(
                                    weeklyListFormatDateTime(
                                        $submission[
                                            'submitted_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="status-badge
                                    <?= weeklyListEscape(
                                        weeklyListStatusClass(
                                            $submission[
                                                'status'
                                            ]
                                        )
                                    ) ?>"
                                >
                                    <?= weeklyListEscape(
                                        weeklyListStatusLabel(
                                            $submission[
                                                'status'
                                            ]
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>

                                <?php if (
                                    !empty(
                                        $submission[
                                            'latest_rejection_reason'
                                        ]
                                    )
                                ): ?>

                                    <span class="rejection-reason">
                                        <?= nl2br(
                                            weeklyListEscape(
                                                $submission[
                                                    'latest_rejection_reason'
                                                ]
                                            )
                                        ) ?>
                                    </span>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                            <td>
                                <a
                                    class="review-button"
                                    href="weekly_submission_details.php?id=<?= (int) $submission['id'] ?>"
                                >
                                    View / Review
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <?php if ($totalPages > 1): ?>

            <nav class="pagination">

                <?php if ($currentPage > 1): ?>

                    <a
                        href="<?= weeklyListEscape(
                            weeklyListPageUrl(
                                $currentPage - 1,
                                $fieldOfficerFilter,
                                $statusFilter,
                                $fromDate,
                                $toDate
                            )
                        ) ?>"
                    >
                        Previous
                    </a>

                <?php else: ?>

                    <span class="disabled">
                        Previous
                    </span>

                <?php endif; ?>

                <?php
                $startPage = max(
                    1,
                    $currentPage - 2
                );

                $endPage = min(
                    $totalPages,
                    $currentPage + 2
                );
                ?>

                <?php for (
                    $pageNumber = $startPage;
                    $pageNumber <= $endPage;
                    $pageNumber++
                ): ?>

                    <?php if (
                        $pageNumber === $currentPage
                    ): ?>

                        <span class="active-page">
                            <?= $pageNumber ?>
                        </span>

                    <?php else: ?>

                        <a
                            href="<?= weeklyListEscape(
                                weeklyListPageUrl(
                                    $pageNumber,
                                    $fieldOfficerFilter,
                                    $statusFilter,
                                    $fromDate,
                                    $toDate
                                )
                            ) ?>"
                        >
                            <?= $pageNumber ?>
                        </a>

                    <?php endif; ?>

                <?php endfor; ?>

                <?php if (
                    $currentPage < $totalPages
                ): ?>

                    <a
                        href="<?= weeklyListEscape(
                            weeklyListPageUrl(
                                $currentPage + 1,
                                $fieldOfficerFilter,
                                $statusFilter,
                                $fromDate,
                                $toDate
                            )
                        ) ?>"
                    >
                        Next
                    </a>

                <?php else: ?>

                    <span class="disabled">
                        Next
                    </span>

                <?php endif; ?>

            </nav>

        <?php endif; ?>

    </section>

</main>

</body>

</html>