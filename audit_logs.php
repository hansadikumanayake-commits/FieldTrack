<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
|
| Only users with the audit.view permission can access this page.
|
*/

requireAdministrativeUser();
requirePermission('audit.view');

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function escapeAuditValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function isValidAuditDate(string $date): bool
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

function formatAuditDateTime(?string $dateTime): string
{
    if (
        $dateTime === null ||
        $dateTime === ''
    ) {
        return '—';
    }

    try {
        return (new DateTime($dateTime))
            ->format('d M Y, h:i:s A');
    } catch (Throwable) {
        return $dateTime;
    }
}

function formatAuditAction(string $action): string
{
    return ucwords(
        strtolower(
            str_replace(
                '_',
                ' ',
                $action
            )
        )
    );
}

/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$currentAdminName = currentUserName();
$currentAdminRole = currentRole();

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$recordsPerPage = 25;

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
| Read filters
|--------------------------------------------------------------------------
*/

$userFilterValue = trim(
    (string) ($_GET['user_id'] ?? '')
);

$userFilter = 0;

if (
    $userFilterValue !== '' &&
    ctype_digit($userFilterValue)
) {
    $userFilter = (int) $userFilterValue;
}

$actionFilter = trim(
    (string) ($_GET['action'] ?? '')
);

if (mb_strlen($actionFilter) > 100) {
    $actionFilter = mb_substr(
        $actionFilter,
        0,
        100
    );
}

$fromDate = trim(
    (string) ($_GET['from_date'] ?? '')
);

$toDate = trim(
    (string) ($_GET['to_date'] ?? '')
);

if (!isValidAuditDate($fromDate)) {
    $fromDate = '';
}

if (!isValidAuditDate($toDate)) {
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
| Load filter options and audit records
|--------------------------------------------------------------------------
*/

$users = [];
$availableActions = [];
$auditRecords = [];
$dataError = '';

$totalRecords = 0;
$totalPages = 1;

try {
    /*
    |--------------------------------------------------------------------------
    | Load users for filter
    |--------------------------------------------------------------------------
    */

    $userResult = $conn->query(
        "SELECT
            id,
            name,
            username
         FROM users
         ORDER BY name ASC"
    );

    while (
        $userRow =
        $userResult->fetch_assoc()
    ) {
        $users[] = $userRow;
    }

    /*
    |--------------------------------------------------------------------------
    | Load available audit actions
    |--------------------------------------------------------------------------
    */

    $actionResult = $conn->query(
        "SELECT DISTINCT action
         FROM audit_logs
         WHERE action IS NOT NULL
         AND action <> ''
         ORDER BY action ASC"
    );

    while (
        $actionRow =
        $actionResult->fetch_assoc()
    ) {
        $availableActions[] =
            (string) $actionRow['action'];
    }

    /*
    |--------------------------------------------------------------------------
    | Count filtered records
    |--------------------------------------------------------------------------
    */

    $countSql = "
        SELECT COUNT(*) AS total
        FROM audit_logs

        WHERE
        (
            ? = 0
            OR audit_logs.user_id = ?
        )

        AND
        (
            ? = ''
            OR audit_logs.action = ?
        )

        AND
        (
            ? = ''
            OR DATE(audit_logs.created_at) >= ?
        )

        AND
        (
            ? = ''
            OR DATE(audit_logs.created_at) <= ?
        )
    ";

    $countStatement = $conn->prepare(
        $countSql
    );

    $countStatement->bind_param(
        'iissssss',
        $userFilter,
        $userFilter,
        $actionFilter,
        $actionFilter,
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

    $totalRecords = (int) (
        $countRow['total'] ?? 0
    );

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
    | Load filtered audit records
    |--------------------------------------------------------------------------
    */

    $auditSql = "
        SELECT
            audit_logs.id,
            audit_logs.user_id,
            audit_logs.action,
            audit_logs.target_type,
            audit_logs.target_id,
            audit_logs.details,
            audit_logs.ip_address,
            audit_logs.created_at,

            users.name AS user_name,
            users.username

        FROM audit_logs

        LEFT JOIN users
            ON users.id =
               audit_logs.user_id

        WHERE
        (
            ? = 0
            OR audit_logs.user_id = ?
        )

        AND
        (
            ? = ''
            OR audit_logs.action = ?
        )

        AND
        (
            ? = ''
            OR DATE(audit_logs.created_at) >= ?
        )

        AND
        (
            ? = ''
            OR DATE(audit_logs.created_at) <= ?
        )

        ORDER BY
            audit_logs.created_at DESC,
            audit_logs.id DESC

        LIMIT ?
        OFFSET ?
    ";

    $auditStatement = $conn->prepare(
        $auditSql
    );

    $auditStatement->bind_param(
        'iissssssii',
        $userFilter,
        $userFilter,
        $actionFilter,
        $actionFilter,
        $fromDate,
        $fromDate,
        $toDate,
        $toDate,
        $recordsPerPage,
        $offset
    );

    $auditStatement->execute();

    $auditResult =
        $auditStatement->get_result();

    while (
        $auditRow =
        $auditResult->fetch_assoc()
    ) {
        $auditRecords[] = $auditRow;
    }

    $auditStatement->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack audit log error: ' .
        $error->getMessage()
    );

    $dataError =
        'The audit log data could not be loaded.';
}

/*
|--------------------------------------------------------------------------
| Build pagination URL
|--------------------------------------------------------------------------
*/

function auditPageUrl(
    int $page,
    int $userFilter,
    string $actionFilter,
    string $fromDate,
    string $toDate
): string {
    return 'audit_logs.php?' .
        http_build_query([
            'page' => $page,
            'user_id' =>
                $userFilter > 0
                    ? $userFilter
                    : '',
            'action' => $actionFilter,
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]);
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

    <title>Audit Logs | FieldTrack</title>

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

        .audit-page {
            width: min(1500px, calc(100% - 32px));
            margin: 30px auto;
        }

        .audit-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .audit-header h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .audit-header p {
            margin: 0;
            color: #64748b;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-button {
            display: inline-block;
            padding: 11px 17px;
            border: none;
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

        .filter-card,
        .audit-card {
            margin-bottom: 24px;
            padding: 22px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.08);
        }

        .filter-card h2,
        .audit-card h2 {
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
            font-size: 14px;
            font-weight: 700;
        }

        .filter-field select,
        .filter-field input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            font: inherit;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 17px;
            flex-wrap: wrap;
        }

        .summary-text {
            margin-bottom: 16px;
            color: #475569;
            font-weight: 700;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .audit-table {
            width: 100%;
            min-width: 1250px;
            border-collapse: collapse;
        }

        .audit-table th,
        .audit-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        .audit-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 13px;
            text-transform: uppercase;
        }

        .audit-table tbody tr:hover {
            background: #f8fafc;
        }

        .user-name {
            display: block;
            font-weight: 700;
        }

        .username {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .action-badge {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .details-cell {
            max-width: 380px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .target-cell,
        .ip-cell {
            white-space: nowrap;
        }

        .error-message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            background: #fee2e2;
            color: #991b1b;
            font-weight: 700;
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
            display: inline-block;
            min-width: 40px;
            padding: 9px 11px;
            border-radius: 7px;
            text-align: center;
            text-decoration: none;
            font-weight: 700;
        }

        .pagination a {
            background: #e2e8f0;
            color: #0f172a;
        }

        .pagination a:hover {
            background: #cbd5e1;
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
            .audit-page {
                width: calc(100% - 20px);
                margin-top: 18px;
            }

            .audit-header {
                flex-direction: column;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .page-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<main class="audit-page">

    <header class="audit-header">

        <div>
            <h1>Audit Logs</h1>

            <p>
                View important user and system activities.
            </p>

            <p>
                Logged in as
                <?= escapeAuditValue(
                    $currentAdminName
                ) ?>

                —
                <?= escapeAuditValue(
                    ucwords(
                        str_replace(
                            '_',
                            ' ',
                            $currentAdminRole
                        )
                    )
                ) ?>
            </p>
        </div>

        <div class="header-buttons">

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

    <?php if ($dataError !== ''): ?>

        <div class="error-message">
            <?= escapeAuditValue(
                $dataError
            ) ?>
        </div>

    <?php endif; ?>

    <section class="filter-card">

        <h2>Filter Audit Logs</h2>

        <form method="GET">

            <div class="filter-grid">

                <div class="filter-field">

                    <label for="user_id">
                        User
                    </label>

                    <select
                        id="user_id"
                        name="user_id"
                    >
                        <option value="">
                            All Users
                        </option>

                        <?php foreach ($users as $user): ?>

                            <option
                                value="<?= (int) $user['id'] ?>"
                                <?= $userFilter ===
                                    (int) $user['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapeAuditValue(
                                    $user['name']
                                ) ?>

                                (@<?= escapeAuditValue(
                                    $user['username']
                                ) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-field">

                    <label for="action">
                        Action
                    </label>

                    <select
                        id="action"
                        name="action"
                    >
                        <option value="">
                            All Actions
                        </option>

                        <?php foreach (
                            $availableActions
                            as $availableAction
                        ): ?>

                            <option
                                value="<?= escapeAuditValue(
                                    $availableAction
                                ) ?>"
                                <?= $actionFilter ===
                                    $availableAction
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapeAuditValue(
                                    formatAuditAction(
                                        $availableAction
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-field">

                    <label for="from_date">
                        From Date
                    </label>

                    <input
                        type="date"
                        id="from_date"
                        name="from_date"
                        value="<?= escapeAuditValue(
                            $fromDate
                        ) ?>"
                    >

                </div>

                <div class="filter-field">

                    <label for="to_date">
                        To Date
                    </label>

                    <input
                        type="date"
                        id="to_date"
                        name="to_date"
                        value="<?= escapeAuditValue(
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
                    href="audit_logs.php"
                >
                    Reset Filters
                </a>

            </div>

        </form>

    </section>

    <section class="audit-card">

        <h2>System Activity</h2>

        <div class="summary-text">
            Total records:
            <?= $totalRecords ?>

            | Page:
            <?= $currentPage ?>
            of
            <?= $totalPages ?>
        </div>

        <div class="table-wrapper">

            <table class="audit-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date and Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($auditRecords)): ?>

                    <tr>
                        <td
                            colspan="7"
                            class="empty-message"
                        >
                            No audit records were found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $auditRecords
                        as $auditRecord
                    ): ?>

                        <tr>

                            <td>
                                #<?= (int) $auditRecord['id'] ?>
                            </td>

                            <td>
                                <?= escapeAuditValue(
                                    formatAuditDateTime(
                                        $auditRecord[
                                            'created_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>

                                <span class="user-name">
                                    <?= escapeAuditValue(
                                        $auditRecord[
                                            'user_name'
                                        ] ??
                                        'Unknown / Deleted User'
                                    ) ?>
                                </span>

                                <?php if (
                                    !empty(
                                        $auditRecord[
                                            'username'
                                        ]
                                    )
                                ): ?>

                                    <span class="username">
                                        @<?= escapeAuditValue(
                                            $auditRecord[
                                                'username'
                                            ]
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <span class="action-badge">
                                    <?= escapeAuditValue(
                                        formatAuditAction(
                                            $auditRecord[
                                                'action'
                                            ]
                                        )
                                    ) ?>
                                </span>

                            </td>

                            <td class="target-cell">

                                <?= escapeAuditValue(
                                    $auditRecord[
                                        'target_type'
                                    ] ??
                                    '—'
                                ) ?>

                                <?php if (
                                    !empty(
                                        $auditRecord[
                                            'target_id'
                                        ]
                                    )
                                ): ?>

                                    #<?= (int) $auditRecord[
                                        'target_id'
                                    ] ?>

                                <?php endif; ?>

                            </td>

                            <td class="details-cell">
                                <?= nl2br(
                                    escapeAuditValue(
                                        $auditRecord[
                                            'details'
                                        ] ??
                                        '—'
                                    )
                                ) ?>
                            </td>

                            <td class="ip-cell">
                                <?= escapeAuditValue(
                                    $auditRecord[
                                        'ip_address'
                                    ] ??
                                    '—'
                                ) ?>
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
                        href="<?= escapeAuditValue(
                            auditPageUrl(
                                $currentPage - 1,
                                $userFilter,
                                $actionFilter,
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
                            href="<?= escapeAuditValue(
                                auditPageUrl(
                                    $pageNumber,
                                    $userFilter,
                                    $actionFilter,
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
                        href="<?= escapeAuditValue(
                            auditPageUrl(
                                $currentPage + 1,
                                $userFilter,
                                $actionFilter,
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