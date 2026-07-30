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

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {
    exit('Database connection is unavailable.');
}

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function adminEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function adminFormatDateTime(?string $dateTime): string
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

function adminFormatRole(string $role): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $role
        )
    );
}

function adminSubmissionStatus(string $status): string
{
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
            str_replace('_', ' ', $status)
        );
}

function adminSubmissionClass(string $status): string
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

/*
|--------------------------------------------------------------------------
| Execute a COUNT query
|--------------------------------------------------------------------------
*/

function adminFetchCount(
    mysqli $conn,
    string $sql,
    ?int $userId = null
): int {
    $statement = $conn->prepare($sql);

    if ($userId !== null) {
        $statement->bind_param(
            'i',
            $userId
        );
    }

    $statement->execute();

    $row = $statement
        ->get_result()
        ->fetch_assoc();

    $statement->close();

    return (int) ($row['total'] ?? 0);
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
| Determine dashboard scope
|--------------------------------------------------------------------------
*/

if (hasRole('system_admin')) {
    $dashboardRole = 'system_admin';
} elseif (hasRole('admin_manager')) {
    $dashboardRole = 'admin_manager';
} elseif (hasRole('admin_officer')) {
    $dashboardRole = 'admin_officer';
} else {
    http_response_code(403);

    exit('Access denied.');
}

$dashboardRoleLabel =
    adminFormatRole($dashboardRole);

/*
|--------------------------------------------------------------------------
| Dashboard variables
|--------------------------------------------------------------------------
*/

$totalFieldOfficers = 0;
$todayInCount = 0;
$todayOutCount = 0;
$pendingReviewCount = 0;

$recentAttendance = [];
$recentSubmissions = [];

$dataError = '';

try {
    /*
    |--------------------------------------------------------------------------
    | System Administrator statistics
    |--------------------------------------------------------------------------
    */

    if ($dashboardRole === 'system_admin') {
        $totalFieldOfficers = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT users.id) AS total

             FROM users

             INNER JOIN user_roles
                ON user_roles.user_id =
                   users.id

             INNER JOIN roles
                ON roles.id =
                   user_roles.role_id

             WHERE roles.role_name =
                   'field_officer'

             AND users.is_active = 1"
        );

        $todayInCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT attendance_events.id)
                    AS total

             FROM attendance_events

             INNER JOIN users
                ON users.id =
                   attendance_events.user_id

             INNER JOIN user_roles
                ON user_roles.user_id =
                   users.id

             INNER JOIN roles
                ON roles.id =
                   user_roles.role_id

             WHERE roles.role_name =
                   'field_officer'

             AND attendance_events.action_type =
                 'IN'

             AND DATE(
                    attendance_events.created_at
                 ) = CURDATE()"
        );

        $todayOutCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT attendance_events.id)
                    AS total

             FROM attendance_events

             INNER JOIN users
                ON users.id =
                   attendance_events.user_id

             INNER JOIN user_roles
                ON user_roles.user_id =
                   users.id

             INNER JOIN roles
                ON roles.id =
                   user_roles.role_id

             WHERE roles.role_name =
                   'field_officer'

             AND attendance_events.action_type =
                 'OUT'

             AND DATE(
                    attendance_events.created_at
                 ) = CURDATE()"
        );

        $pendingReviewCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(*) AS total

             FROM weekly_submissions

             WHERE status IN (
                'submitted',
                'resubmitted',
                'pending_manager_review',
                'admin_officer_approved'
             )"
        );

        /*
        |--------------------------------------------------------------------------
        | Recent attendance for System Administrator
        |--------------------------------------------------------------------------
        */

        $attendanceStatement = $conn->prepare(
            "SELECT DISTINCT
                attendance_events.id,
                attendance_events.action_type,
                attendance_events.latitude,
                attendance_events.longitude,
                attendance_events.photo_path,
                attendance_events.is_locked,
                attendance_events.created_at,

                users.name AS officer_name,
                users.username AS officer_username

             FROM attendance_events

             INNER JOIN users
                ON users.id =
                   attendance_events.user_id

             INNER JOIN user_roles
                ON user_roles.user_id =
                   users.id

             INNER JOIN roles
                ON roles.id =
                   user_roles.role_id

             WHERE roles.role_name =
                   'field_officer'

             ORDER BY
                attendance_events.created_at DESC,
                attendance_events.id DESC

             LIMIT 15"
        );

        /*
        |--------------------------------------------------------------------------
        | Recent submissions for System Administrator
        |--------------------------------------------------------------------------
        */

        $submissionStatement = $conn->prepare(
            "SELECT
                weekly_submissions.id,
                weekly_submissions.week_start,
                weekly_submissions.week_end,
                weekly_submissions.status,
                weekly_submissions.submitted_at,

                field_officer.name
                    AS field_officer_name,

                field_officer.username
                    AS field_officer_username

             FROM weekly_submissions

             INNER JOIN users AS field_officer
                ON field_officer.id =
                   weekly_submissions.field_officer_id

             ORDER BY
                weekly_submissions.submitted_at DESC,
                weekly_submissions.id DESC

             LIMIT 10"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Officer statistics
    |--------------------------------------------------------------------------
    */

    if ($dashboardRole === 'admin_officer') {
        $totalFieldOfficers = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT
                    officer_assignments.field_officer_id
                ) AS total

             FROM officer_assignments

             INNER JOIN users AS field_officer
                ON field_officer.id =
                   officer_assignments.field_officer_id

             WHERE officer_assignments.admin_officer_id = ?

             AND field_officer.is_active = 1",
            $currentAdminId
        );

        $todayInCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT attendance_events.id)
                    AS total

             FROM attendance_events

             INNER JOIN officer_assignments
                ON officer_assignments.field_officer_id =
                   attendance_events.user_id

             WHERE officer_assignments.admin_officer_id = ?

             AND attendance_events.action_type =
                 'IN'

             AND DATE(
                    attendance_events.created_at
                 ) = CURDATE()",
            $currentAdminId
        );

        $todayOutCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT attendance_events.id)
                    AS total

             FROM attendance_events

             INNER JOIN officer_assignments
                ON officer_assignments.field_officer_id =
                   attendance_events.user_id

             WHERE officer_assignments.admin_officer_id = ?

             AND attendance_events.action_type =
                 'OUT'

             AND DATE(
                    attendance_events.created_at
                 ) = CURDATE()",
            $currentAdminId
        );

        $pendingReviewCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(*) AS total

             FROM weekly_submissions

             WHERE admin_officer_id = ?

             AND status IN (
                'submitted',
                'resubmitted'
             )",
            $currentAdminId
        );

        /*
        |--------------------------------------------------------------------------
        | Recent attendance assigned to Admin Officer
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

                field_officer.name AS officer_name,
                field_officer.username
                    AS officer_username

             FROM attendance_events

             INNER JOIN users AS field_officer
                ON field_officer.id =
                   attendance_events.user_id

             INNER JOIN officer_assignments
                ON officer_assignments.field_officer_id =
                   field_officer.id

             WHERE officer_assignments.admin_officer_id = ?

             ORDER BY
                attendance_events.created_at DESC,
                attendance_events.id DESC

             LIMIT 15"
        );

        $attendanceStatement->bind_param(
            'i',
            $currentAdminId
        );

        /*
        |--------------------------------------------------------------------------
        | Recent submissions assigned to Admin Officer
        |--------------------------------------------------------------------------
        */

        $submissionStatement = $conn->prepare(
            "SELECT
                weekly_submissions.id,
                weekly_submissions.week_start,
                weekly_submissions.week_end,
                weekly_submissions.status,
                weekly_submissions.submitted_at,

                field_officer.name
                    AS field_officer_name,

                field_officer.username
                    AS field_officer_username

             FROM weekly_submissions

             INNER JOIN users AS field_officer
                ON field_officer.id =
                   weekly_submissions.field_officer_id

             WHERE weekly_submissions.admin_officer_id = ?

             ORDER BY
                weekly_submissions.submitted_at DESC,
                weekly_submissions.id DESC

             LIMIT 10"
        );

        $submissionStatement->bind_param(
            'i',
            $currentAdminId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Manager statistics
    |--------------------------------------------------------------------------
    */

    if ($dashboardRole === 'admin_manager') {
        $totalFieldOfficers = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT
                    officer_assignments.field_officer_id
                ) AS total

             FROM officer_assignments

             INNER JOIN users AS field_officer
                ON field_officer.id =
                   officer_assignments.field_officer_id

             WHERE officer_assignments.admin_manager_id = ?

             AND field_officer.is_active = 1",
            $currentAdminId
        );

        $todayInCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT attendance_events.id)
                    AS total

             FROM attendance_events

             INNER JOIN officer_assignments
                ON officer_assignments.field_officer_id =
                   attendance_events.user_id

             WHERE officer_assignments.admin_manager_id = ?

             AND attendance_events.action_type =
                 'IN'

             AND DATE(
                    attendance_events.created_at
                 ) = CURDATE()",
            $currentAdminId
        );

        $todayOutCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(DISTINCT attendance_events.id)
                    AS total

             FROM attendance_events

             INNER JOIN officer_assignments
                ON officer_assignments.field_officer_id =
                   attendance_events.user_id

             WHERE officer_assignments.admin_manager_id = ?

             AND attendance_events.action_type =
                 'OUT'

             AND DATE(
                    attendance_events.created_at
                 ) = CURDATE()",
            $currentAdminId
        );

        $pendingReviewCount = adminFetchCount(
            $conn,
            "SELECT
                COUNT(*) AS total

             FROM weekly_submissions

             WHERE admin_manager_id = ?

             AND status IN (
                'pending_manager_review',
                'admin_officer_approved'
             )",
            $currentAdminId
        );

        /*
        |--------------------------------------------------------------------------
        | Recent attendance assigned to Admin Manager
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

                field_officer.name AS officer_name,
                field_officer.username
                    AS officer_username

             FROM attendance_events

             INNER JOIN users AS field_officer
                ON field_officer.id =
                   attendance_events.user_id

             INNER JOIN officer_assignments
                ON officer_assignments.field_officer_id =
                   field_officer.id

             WHERE officer_assignments.admin_manager_id = ?

             ORDER BY
                attendance_events.created_at DESC,
                attendance_events.id DESC

             LIMIT 15"
        );

        $attendanceStatement->bind_param(
            'i',
            $currentAdminId
        );

        /*
        |--------------------------------------------------------------------------
        | Recent submissions assigned to Admin Manager
        |--------------------------------------------------------------------------
        */

        $submissionStatement = $conn->prepare(
            "SELECT
                weekly_submissions.id,
                weekly_submissions.week_start,
                weekly_submissions.week_end,
                weekly_submissions.status,
                weekly_submissions.submitted_at,

                field_officer.name
                    AS field_officer_name,

                field_officer.username
                    AS field_officer_username

             FROM weekly_submissions

             INNER JOIN users AS field_officer
                ON field_officer.id =
                   weekly_submissions.field_officer_id

             WHERE weekly_submissions.admin_manager_id = ?

             ORDER BY
                weekly_submissions.submitted_at DESC,
                weekly_submissions.id DESC

             LIMIT 10"
        );

        $submissionStatement->bind_param(
            'i',
            $currentAdminId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Execute attendance query
    |--------------------------------------------------------------------------
    */

    $attendanceStatement->execute();

    $attendanceResult =
        $attendanceStatement->get_result();

    while (
        $attendanceRow =
        $attendanceResult->fetch_assoc()
    ) {
        $recentAttendance[] =
            $attendanceRow;
    }

    $attendanceStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Execute submission query
    |--------------------------------------------------------------------------
    */

    $submissionStatement->execute();

    $submissionResult =
        $submissionStatement->get_result();

    while (
        $submissionRow =
        $submissionResult->fetch_assoc()
    ) {
        $recentSubmissions[] =
            $submissionRow;
    }

    $submissionStatement->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack admin dashboard error: ' .
        $error->getMessage()
    );

    $dataError =
        'The admin data could not be loaded. ' .
        $error->getMessage();
}

/*
|--------------------------------------------------------------------------
| Prepare map marker data
|--------------------------------------------------------------------------
*/

$mapMarkers = [];

foreach ($recentAttendance as $attendance) {
    $latitude =
        (float) $attendance['latitude'];

    $longitude =
        (float) $attendance['longitude'];

    if (
        $latitude < -90 ||
        $latitude > 90 ||
        $longitude < -180 ||
        $longitude > 180
    ) {
        continue;
    }

    $mapMarkers[] = [
        'id' =>
            (int) $attendance['id'],

        'officer' =>
            (string) $attendance['officer_name'],

        'username' =>
            (string) $attendance['officer_username'],

        'action' =>
            (string) $attendance['action_type'],

        'latitude' =>
            $latitude,

        'longitude' =>
            $longitude,

        'created_at' =>
            adminFormatDateTime(
                (string) $attendance['created_at']
            ),

        'details_url' =>
            'attendance_details.php?id=' .
            (int) $attendance['id']
    ];
}

$mapMarkersJson = json_encode(
    $mapMarkers,
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
);

if ($mapMarkersJson === false) {
    $mapMarkersJson = '[]';
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
        Admin Dashboard | FieldTrack
    </title>

    <link
        rel="stylesheet"
        href="admin_style.css"
    >

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>
        #admin-map {
            width: 100%;
            height: 480px;
            border-radius: 12px;
        }
    </style>
</head>

<body>

<header class="admin-header">

    <div class="admin-header-content">

        <div>
            <h1>FieldTrack Admin Dashboard</h1>

            <p>
                Welcome,
                <?= adminEscape($currentAdminName) ?>

                —
                <?= adminEscape($dashboardRoleLabel) ?>
            </p>
        </div>

        <nav class="admin-header-actions">

            <a
                class="admin-nav-button"
                href="admin_weekly_submissions.php"
            >
                Weekly Submissions
            </a>

            <?php if (
                currentUserHasPermission('audit.view')
            ): ?>

                <a
                    class="admin-nav-button"
                    href="audit_logs.php"
                >
                    Audit Logs
                </a>

            <?php endif; ?>

            <?php if (
                $dashboardRole === 'system_admin'
            ): ?>

                <a
                    class="admin-nav-button"
                    href="manage_users.php"
                >
                    Manage Users
                </a>

                <a
                    class="admin-nav-button"
                    href="manage_roles.php"
                >
                    Manage Roles
                </a>

                <a
                    class="admin-nav-button"
                    href="manage_assignments.php"
                >
                    Assignments
                </a>

            <?php endif; ?>

            <a
                class="admin-nav-button logout-button"
                href="logout.php"
            >
                Logout
            </a>

        </nav>

    </div>

</header>

<main class="admin-container">

    <?php if ($dataError !== ''): ?>

        <div class="admin-message error-message">
            <?= adminEscape($dataError) ?>
        </div>

    <?php endif; ?>

    <section class="admin-summary-grid">

        <article class="admin-summary-card">
            <span>Active Field Officers</span>

            <strong>
                <?= $totalFieldOfficers ?>
            </strong>
        </article>

        <article class="admin-summary-card">
            <span>Today IN</span>

            <strong>
                <?= $todayInCount ?>
            </strong>
        </article>

        <article class="admin-summary-card">
            <span>Today OUT</span>

            <strong>
                <?= $todayOutCount ?>
            </strong>
        </article>

        <article class="admin-summary-card">
            <span>Pending Reviews</span>

            <strong>
                <?= $pendingReviewCount ?>
            </strong>
        </article>

    </section>

    <section class="admin-card">

        <div class="admin-section-heading">
            <div>
                <h2>Attendance Location Map</h2>

                <p>
                    Displays the latest attendance locations
                    available to your role.
                </p>
            </div>
        </div>

        <?php if (empty($mapMarkers)): ?>

            <div class="admin-empty-message">
                No attendance locations are available.
            </div>

        <?php else: ?>

            <div id="admin-map"></div>

        <?php endif; ?>

    </section>

    <section class="admin-card">

        <div class="admin-section-heading">

            <div>
                <h2>Recent Attendance</h2>

                <p>
                    Latest Field Officer IN and OUT records.
                </p>
            </div>

        </div>

        <div class="admin-table-wrapper">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Field Officer</th>
                        <th>Action</th>
                        <th>Date and Time</th>
                        <th>Location</th>
                        <th>Photo</th>
                        <th>Locked</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    empty($recentAttendance)
                ): ?>

                    <tr>
                        <td
                            colspan="8"
                            class="admin-empty-message"
                        >
                            No attendance records were found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $recentAttendance
                        as $attendance
                    ): ?>

                        <tr>

                            <td>
                                #<?= (int) $attendance['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= adminEscape(
                                        $attendance[
                                            'officer_name'
                                        ]
                                    ) ?>
                                </strong>

                                <span class="admin-secondary-text">
                                    @<?= adminEscape(
                                        $attendance[
                                            'officer_username'
                                        ]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span
                                    class="attendance-badge
                                    <?= $attendance[
                                        'action_type'
                                    ] === 'IN'
                                        ? 'attendance-in'
                                        : 'attendance-out' ?>"
                                >
                                    <?= adminEscape(
                                        $attendance[
                                            'action_type'
                                        ]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= adminEscape(
                                    adminFormatDateTime(
                                        $attendance[
                                            'created_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= adminEscape(
                                    $attendance['latitude']
                                ) ?>

                                <br>

                                <?= adminEscape(
                                    $attendance['longitude']
                                ) ?>

                                <br>

                                <a
                                    class="admin-text-link"
                                    href="https://www.google.com/maps?q=<?= rawurlencode(
                                        (string) $attendance[
                                            'latitude'
                                        ]
                                    ) ?>,<?= rawurlencode(
                                        (string) $attendance[
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

                                <?php if (
                                    !empty(
                                        $attendance[
                                            'photo_path'
                                        ]
                                    )
                                ): ?>

                                    <a
                                        href="<?= adminEscape(
                                            $attendance[
                                                'photo_path'
                                            ]
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <img
                                            class="admin-photo"
                                            src="<?= adminEscape(
                                                $attendance[
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
                                <?= (int) $attendance[
                                    'is_locked'
                                ] === 1
                                    ? 'Yes'
                                    : 'No' ?>
                            </td>

                            <td>
                                <a
                                    class="admin-small-button"
                                    href="attendance_details.php?id=<?= (int) $attendance['id'] ?>"
                                >
                                    View
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <section class="admin-card">

        <div class="admin-section-heading">

            <div>
                <h2>Recent Weekly Submissions</h2>

                <p>
                    Latest weekly attendance approval records.
                </p>
            </div>

            <a
                class="admin-primary-button"
                href="admin_weekly_submissions.php"
            >
                View All Submissions
            </a>

        </div>

        <div class="admin-table-wrapper">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Field Officer</th>
                        <th>Week</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    empty($recentSubmissions)
                ): ?>

                    <tr>
                        <td
                            colspan="6"
                            class="admin-empty-message"
                        >
                            No weekly submissions were found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $recentSubmissions
                        as $submission
                    ): ?>

                        <tr>

                            <td>
                                #<?= (int) $submission['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= adminEscape(
                                        $submission[
                                            'field_officer_name'
                                        ]
                                    ) ?>
                                </strong>

                                <span class="admin-secondary-text">
                                    @<?= adminEscape(
                                        $submission[
                                            'field_officer_username'
                                        ]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= adminEscape(
                                    $submission[
                                        'week_start'
                                    ]
                                ) ?>

                                to

                                <?= adminEscape(
                                    $submission[
                                        'week_end'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <?= adminEscape(
                                    adminFormatDateTime(
                                        $submission[
                                            'submitted_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="submission-status
                                    <?= adminEscape(
                                        adminSubmissionClass(
                                            $submission[
                                                'status'
                                            ]
                                        )
                                    ) ?>"
                                >
                                    <?= adminEscape(
                                        adminSubmissionStatus(
                                            $submission[
                                                'status'
                                            ]
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="admin-small-button"
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

    </section>

</main>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>

<script>
    const attendanceMarkers =
        <?= $mapMarkersJson ?>;

    if (
        attendanceMarkers.length > 0 &&
        document.getElementById('admin-map')
    ) {
        const map = L.map('admin-map');

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution:
                    '&copy; OpenStreetMap contributors'
            }
        ).addTo(map);

        const markerGroup = [];

        attendanceMarkers.forEach(function (record) {
            const marker = L.marker([
                record.latitude,
                record.longitude
            ]).addTo(map);

            const popupContent =
                '<strong>' +
                escapeMapText(record.officer) +
                '</strong><br>' +

                '@' +
                escapeMapText(record.username) +
                '<br>' +

                'Action: ' +
                escapeMapText(record.action) +
                '<br>' +

                'Date: ' +
                escapeMapText(record.created_at) +
                '<br><br>' +

                '<a href="' +
                encodeURI(record.details_url) +
                '">View details</a>';

            marker.bindPopup(popupContent);

            markerGroup.push(marker);
        });

        const bounds = L.featureGroup(
            markerGroup
        ).getBounds();

        if (bounds.isValid()) {
            map.fitBounds(
                bounds,
                {
                    padding: [30, 30],
                    maxZoom: 16
                }
            );
        } else {
            map.setView(
                [7.8731, 80.7718],
                7
            );
        }
    }

    function escapeMapText(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
</script>

</body>

</html>