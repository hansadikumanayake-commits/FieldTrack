<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';

/*
 * Only logged-in administrators can
 * access the audit logs page.
 */
requireRole(['admin']);

const AUDIT_RECORDS_PER_PAGE = 25;

/*
 * Escape database values before displaying
 * them inside HTML.
 */
function escapeAuditValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
 * Convert stored actions such as:
 * ADMIN_LOGIN_SUCCESS
 *
 * Into:
 * Admin Login Success
 */
function formatAuditAction(string $action): string
{
    $action = trim($action);

    if ($action === '') {
        return 'Unknown Action';
    }

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
 * Format the audit date and time.
 */
function formatAuditDate(
    ?string $dateTime
): string {
    if (empty($dateTime)) {
        return 'Unknown';
    }

    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return 'Unknown';
    }

    return date(
        'd/m/Y h:i A',
        $timestamp
    );
}

/*
 * Validate the requested page number.
 */
$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

if (
    $page === false ||
    $page === null ||
    $page < 1
) {
    $page = 1;
}

$auditLogs = [];

try {
    /*
     * Count all audit records.
     */
    $countResult = $conn->query(
        "SELECT
            COUNT(*) AS total_records

         FROM audit_logs"
    );

    if (!$countResult instanceof mysqli_result) {
        throw new RuntimeException(
            'Unable to count audit records.'
        );
    }

    $countRow = $countResult->fetch_assoc();

    $countResult->free();

    $totalRecords = (int) (
        $countRow['total_records'] ?? 0
    );

    /*
     * Calculate the total number of pages.
     * Keep at least one page, even if there
     * are no audit records.
     */
    $totalPages = max(
        1,
        (int) ceil(
            $totalRecords /
            AUDIT_RECORDS_PER_PAGE
        )
    );

    /*
     * Prevent requesting a page higher
     * than the final page.
     */
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $recordsPerPage =
        AUDIT_RECORDS_PER_PAGE;

    $offset =
        ($page - 1) *
        AUDIT_RECORDS_PER_PAGE;

    /*
     * Retrieve one page of audit records.
     *
     * LEFT JOIN is used because a user may
     * have been deleted while their audit
     * history remains in the system.
     */
    $stmt = $conn->prepare(
        "SELECT
            audit_logs.id,
            audit_logs.user_id,
            audit_logs.action,
            audit_logs.target_type,
            audit_logs.target_id,
            audit_logs.ip_address,
            audit_logs.created_at,
            users.name,
            users.username

         FROM audit_logs

         LEFT JOIN users
            ON users.id =
               audit_logs.user_id

         ORDER BY
            audit_logs.created_at DESC,
            audit_logs.id DESC

         LIMIT ?
         OFFSET ?"
    );

    $stmt->bind_param(
        'ii',
        $recordsPerPage,
        $offset
    );

    $stmt->execute();

    $auditResult = $stmt->get_result();

    if (!$auditResult instanceof mysqli_result) {
        throw new RuntimeException(
            'Unable to retrieve audit records.'
        );
    }

    while (
        $auditLog =
            $auditResult->fetch_assoc()
    ) {
        $auditLogs[] = $auditLog;
    }

    $stmt->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack audit page error: ' .
        $error->getMessage()
    );

    http_response_code(500);

    exit(
        'Audit records could not be loaded. ' .
        'Please check that the audit_logs table exists.'
    );
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

    <title>FieldTrack Audit Logs</title>

    <link
        rel="stylesheet"
        href="admin_style.css"
    >

</head>

<body>

<header class="admin-header">

    <div>

        <h1>Audit Logs</h1>

        <p>
            View administrator activity recorded
            by FieldTrack.
        </p>

    </div>

    <div class="header-actions">

        <a
            href="admin_panel.php"
            class="logout-btn audit-btn"
        >
            Back to Dashboard
        </a>

        <a
            href="logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</header>

<main class="admin-container">

    <!-- Audit summary cards -->
    <section class="summary-grid">

        <div class="summary-card">

            <h3>Total Records</h3>

            <p>
                <?= $totalRecords ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Current Page</h3>

            <p>
                <?= $page ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Total Pages</h3>

            <p>
                <?= $totalPages ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Records Per Page</h3>

            <p>
                <?= AUDIT_RECORDS_PER_PAGE ?>
            </p>

        </div>

    </section>

    <!-- Audit records table -->
    <section class="admin-section">

        <div class="section-title">

            <div>

                <h2>Administrator Activity</h2>

                <p>
                    The newest administrator activity
                    appears first.
                </p>

            </div>

        </div>

        <div class="table-wrapper">

            <table class="records-table">

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Administrator</th>
                        <th>Action</th>
                        <th>Target Type</th>
                        <th>Target ID</th>
                        <th>IP Address</th>
                        <th>Date and Time</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (
                    count($auditLogs) > 0
                ): ?>

                    <?php foreach (
                        $auditLogs as $log
                    ): ?>

                        <tr>

                            <td>
                                <?= (int) $log['id'] ?>
                            </td>

                            <td>

                                <?php if (
                                    !empty($log['name'])
                                ): ?>

                                    <strong>
                                        <?= escapeAuditValue(
                                            $log['name']
                                        ) ?>
                                    </strong>

                                    <br>

                                    <span>
                                        @<?= escapeAuditValue(
                                            $log['username'] ??
                                            ''
                                        ) ?>
                                    </span>

                                <?php else: ?>

                                    <span>
                                        Unknown user
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <strong>
                                    <?= escapeAuditValue(
                                        formatAuditAction(
                                            (string) (
                                                $log['action'] ??
                                                ''
                                            )
                                        )
                                    ) ?>
                                </strong>

                            </td>

                            <td>

                                <?php if (
                                    !empty(
                                        $log['target_type']
                                    )
                                ): ?>

                                    <?= escapeAuditValue(
                                        $log['target_type']
                                    ) ?>

                                <?php else: ?>

                                    &mdash;

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (
                                    $log['target_id'] !==
                                    null
                                ): ?>

                                    <?= (int) $log['target_id'] ?>

                                <?php else: ?>

                                    &mdash;

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= escapeAuditValue(
                                    !empty(
                                        $log['ip_address']
                                    )
                                        ? $log['ip_address']
                                        : 'Not available'
                                ) ?>

                            </td>

                            <td>

                                <?= escapeAuditValue(
                                    formatAuditDate(
                                        $log['created_at'] ??
                                        null
                                    )
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="7">

                            No audit records exist yet.
                            Log out and log in again as
                            an administrator to create
                            audit records.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- Pagination -->
        <?php if (
            $totalPages > 1
        ): ?>

            <div class="filter-actions">

                <?php if (
                    $page > 1
                ): ?>

                    <a
                        href="audit_logs.php?page=<?= $page - 1 ?>"
                        class="reset-filter-btn"
                    >
                        Previous
                    </a>

                <?php endif; ?>

                <span>
                    Page
                    <?= $page ?>
                    of
                    <?= $totalPages ?>
                </span>

                <?php if (
                    $page < $totalPages
                ): ?>

                    <a
                        href="audit_logs.php?page=<?= $page + 1 ?>"
                        class="apply-filter-btn"
                    >
                        Next
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>

</html>