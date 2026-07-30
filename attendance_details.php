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

function attendanceDetailsEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function attendanceDetailsDateTime(?string $dateTime): string
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

function attendanceDetailsRoleLabel(string $role): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $role
        )
    );
}

function attendanceDetailsStatusLabel(string $status): string
{
    $labels = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
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

function attendanceDetailsStatusClass(
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

/*
|--------------------------------------------------------------------------
| Current administrative user
|--------------------------------------------------------------------------
*/

$currentAdminId = currentUserId();
$currentAdminName = currentUserName();

/*
|--------------------------------------------------------------------------
| Determine access scope
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
| Validate attendance ID
|--------------------------------------------------------------------------
*/

$attendanceIdValue = trim(
    (string) ($_GET['id'] ?? '')
);

if (
    $attendanceIdValue === '' ||
    !ctype_digit($attendanceIdValue)
) {
    header('Location: admin_panel.php');
    exit();
}

$attendanceId = (int) $attendanceIdValue;

if ($attendanceId < 1) {
    header('Location: admin_panel.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Load attendance record
|--------------------------------------------------------------------------
*/

$attendance = null;
$weeklySubmissions = [];
$dataError = '';

try {
    $attendanceSql = "
        SELECT
            attendance_events.id,
            attendance_events.user_id,
            attendance_events.action_type,
            attendance_events.latitude,
            attendance_events.longitude,
            attendance_events.photo_path,
            attendance_events.is_locked,
            attendance_events.created_at,
            attendance_events.updated_at,

            field_officer.name
                AS field_officer_name,

            field_officer.username
                AS field_officer_username,

            field_officer.is_active
                AS field_officer_active,

            officer_assignments.id
                AS assignment_id,

            assigned_admin_officer.name
                AS assigned_admin_officer_name,

            assigned_admin_officer.username
                AS assigned_admin_officer_username,

            assigned_admin_manager.name
                AS assigned_admin_manager_name,

            assigned_admin_manager.username
                AS assigned_admin_manager_username

        FROM attendance_events

        INNER JOIN users AS field_officer
            ON field_officer.id =
               attendance_events.user_id

        LEFT JOIN officer_assignments
            ON officer_assignments.field_officer_id =
               field_officer.id

        LEFT JOIN users AS assigned_admin_officer
            ON assigned_admin_officer.id =
               officer_assignments.admin_officer_id

        LEFT JOIN users AS assigned_admin_manager
            ON assigned_admin_manager.id =
               officer_assignments.admin_manager_id

        WHERE attendance_events.id = ?

        AND
        (
            ? = 'system_admin'

            OR
            (
                ? = 'admin_officer'

                AND EXISTS
                (
                    SELECT 1

                    FROM officer_assignments
                        AS officer_scope

                    WHERE officer_scope.field_officer_id =
                          attendance_events.user_id

                    AND officer_scope.admin_officer_id = ?
                )
            )

            OR
            (
                ? = 'admin_manager'

                AND EXISTS
                (
                    SELECT 1

                    FROM officer_assignments
                        AS manager_scope

                    WHERE manager_scope.field_officer_id =
                          attendance_events.user_id

                    AND manager_scope.admin_manager_id = ?
                )
            )
        )

        LIMIT 1
    ";

    $attendanceStatement = $conn->prepare(
        $attendanceSql
    );

    $attendanceStatement->bind_param(
        'issisi',
        $attendanceId,
        $scopeRole,
        $scopeRole,
        $currentAdminId,
        $scopeRole,
        $currentAdminId
    );

    $attendanceStatement->execute();

    $attendance = $attendanceStatement
        ->get_result()
        ->fetch_assoc();

    $attendanceStatement->close();

    if (!$attendance) {
        http_response_code(404);

        exit(
            'The attendance record was not found or you are not allowed to view it.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load weekly submissions containing this attendance record
    |--------------------------------------------------------------------------
    */

    $submissionStatement = $conn->prepare(
        "SELECT
            weekly_submissions.id,
            weekly_submissions.week_start,
            weekly_submissions.week_end,
            weekly_submissions.status,
            weekly_submissions.submitted_at,
            weekly_submissions.admin_reviewed_at,
            weekly_submissions.manager_reviewed_at,
            weekly_submissions.latest_rejection_reason

         FROM weekly_submission_records

         INNER JOIN weekly_submissions
            ON weekly_submissions.id =
               weekly_submission_records.submission_id

         WHERE weekly_submission_records.attendance_event_id = ?

         ORDER BY
            weekly_submissions.created_at DESC,
            weekly_submissions.id DESC"
    );

    $submissionStatement->bind_param(
        'i',
        $attendanceId
    );

    $submissionStatement->execute();

    $submissionResult =
        $submissionStatement->get_result();

    while (
        $submission =
        $submissionResult->fetch_assoc()
    ) {
        $weeklySubmissions[] = $submission;
    }

    $submissionStatement->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack attendance details error: ' .
        $error->getMessage()
    );

    $dataError =
        'The attendance details could not be loaded.';
}

/*
|--------------------------------------------------------------------------
| Prepare displayed values
|--------------------------------------------------------------------------
*/

$latitude = (float) (
    $attendance['latitude'] ?? 0
);

$longitude = (float) (
    $attendance['longitude'] ?? 0
);

$actionType = (string) (
    $attendance['action_type'] ?? ''
);

$scopeLabel = attendanceDetailsRoleLabel(
    $scopeRole
);

$photoPath = trim(
    (string) (
        $attendance['photo_path'] ?? ''
    )
);

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
        Attendance Details | FieldTrack
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
            width: min(1350px, calc(100% - 32px));
            margin: 30px auto 50px;
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
        }

        .page-button.secondary {
            background: #475569;
        }

        .details-card {
            margin-bottom: 24px;
            padding: 22px;
            border: 1px solid #e2e8f0;
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
            overflow-wrap: anywhere;
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
        }

        .action-badge {
            display: inline-block;
            min-width: 60px;
            padding: 7px 11px;
            border-radius: 999px;
            text-align: center;
            font-size: 13px;
            font-weight: 800;
        }

        .action-in {
            background: #dcfce7;
            color: #166534;
        }

        .action-out {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .lock-badge {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .locked {
            background: #fee2e2;
            color: #991b1b;
        }

        .unlocked {
            background: #dcfce7;
            color: #166534;
        }

        .photo-layout {
            display: grid;
            grid-template-columns:
                minmax(280px, 500px)
                minmax(280px, 1fr);
            gap: 24px;
            align-items: start;
        }

        .attendance-photo {
            display: block;
            width: 100%;
            max-height: 500px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            object-fit: contain;
            background: #f8fafc;
        }

        .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 260px;
            border: 1px dashed #94a3b8;
            border-radius: 12px;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
        }

        #attendance-map {
            width: 100%;
            height: 420px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
        }

        .coordinate-box {
            margin-top: 14px;
            padding: 14px;
            border-radius: 9px;
            background: #f8fafc;
            line-height: 1.7;
        }

        .map-link {
            display: inline-block;
            margin-top: 10px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 700;
        }

        .map-link:hover {
            text-decoration: underline;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .submission-table {
            width: 100%;
            min-width: 900px;
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
            font-size: 13px;
            text-transform: uppercase;
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

        .small-button {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 7px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
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
            padding: 30px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 1000px) {
            .information-grid {
                grid-template-columns:
                    repeat(2, minmax(180px, 1fr));
            }

            .photo-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .details-page {
                width: calc(100% - 20px);
                margin-top: 18px;
            }

            .details-header {
                flex-direction: column;
            }

            .information-grid {
                grid-template-columns: 1fr;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .page-button {
                width: 100%;
                text-align: center;
            }

            #attendance-map {
                height: 340px;
            }
        }
    </style>
</head>

<body>

<main class="details-page">

    <header class="details-header">

        <div>
            <h1>
                Attendance Record
                #<?= $attendanceId ?>
            </h1>

            <p>
                Logged in as
                <?= attendanceDetailsEscape(
                    $currentAdminName
                ) ?>

                —
                <?= attendanceDetailsEscape(
                    $scopeLabel
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

    <?php if ($dataError !== ''): ?>

        <div class="error-message">
            <?= attendanceDetailsEscape(
                $dataError
            ) ?>
        </div>

    <?php endif; ?>

    <section class="details-card">

        <h2>Attendance Information</h2>

        <div class="information-grid">

            <div class="information-item">
                <span>Attendance ID</span>

                <strong>
                    #<?= (int) $attendance['id'] ?>
                </strong>
            </div>

            <div class="information-item">
                <span>Action</span>

                <strong>
                    <span
                        class="action-badge
                        <?= $actionType === 'IN'
                            ? 'action-in'
                            : 'action-out' ?>"
                    >
                        <?= attendanceDetailsEscape(
                            $actionType
                        ) ?>
                    </span>
                </strong>
            </div>

            <div class="information-item">
                <span>Date and Time</span>

                <strong>
                    <?= attendanceDetailsEscape(
                        attendanceDetailsDateTime(
                            $attendance['created_at']
                        )
                    ) ?>
                </strong>
            </div>

            <div class="information-item">
                <span>Record Lock Status</span>

                <strong>
                    <span
                        class="lock-badge
                        <?= (int) $attendance[
                            'is_locked'
                        ] === 1
                            ? 'locked'
                            : 'unlocked' ?>"
                    >
                        <?= (int) $attendance[
                            'is_locked'
                        ] === 1
                            ? 'Locked'
                            : 'Unlocked' ?>
                    </span>
                </strong>
            </div>

            <div class="information-item">
                <span>Field Officer</span>

                <strong>
                    <?= attendanceDetailsEscape(
                        $attendance[
                            'field_officer_name'
                        ]
                    ) ?>
                </strong>

                @<?= attendanceDetailsEscape(
                    $attendance[
                        'field_officer_username'
                    ]
                ) ?>
            </div>

            <div class="information-item">
                <span>Field Officer Status</span>

                <strong>
                    <?= (int) $attendance[
                        'field_officer_active'
                    ] === 1
                        ? 'Active'
                        : 'Inactive' ?>
                </strong>
            </div>

            <div class="information-item">
                <span>Assigned Admin Officer</span>

                <strong>
                    <?= attendanceDetailsEscape(
                        $attendance[
                            'assigned_admin_officer_name'
                        ] ?? 'Not assigned'
                    ) ?>
                </strong>

                <?php if (
                    !empty(
                        $attendance[
                            'assigned_admin_officer_username'
                        ]
                    )
                ): ?>

                    @<?= attendanceDetailsEscape(
                        $attendance[
                            'assigned_admin_officer_username'
                        ]
                    ) ?>

                <?php endif; ?>
            </div>

            <div class="information-item">
                <span>Assigned Admin Manager</span>

                <strong>
                    <?= attendanceDetailsEscape(
                        $attendance[
                            'assigned_admin_manager_name'
                        ] ?? 'Not assigned'
                    ) ?>
                </strong>

                <?php if (
                    !empty(
                        $attendance[
                            'assigned_admin_manager_username'
                        ]
                    )
                ): ?>

                    @<?= attendanceDetailsEscape(
                        $attendance[
                            'assigned_admin_manager_username'
                        ]
                    ) ?>

                <?php endif; ?>
            </div>

            <div class="information-item">
                <span>Created At</span>

                <strong>
                    <?= attendanceDetailsEscape(
                        attendanceDetailsDateTime(
                            $attendance['created_at']
                        )
                    ) ?>
                </strong>
            </div>

            <div class="information-item">
                <span>Last Updated</span>

                <strong>
                    <?= attendanceDetailsEscape(
                        attendanceDetailsDateTime(
                            $attendance['updated_at']
                        )
                    ) ?>
                </strong>
            </div>

        </div>

    </section>

    <section class="details-card">

        <h2>Photo and Location</h2>

        <div class="photo-layout">

            <div>

                <?php if ($photoPath !== ''): ?>

                    <a
                        href="<?= attendanceDetailsEscape(
                            $photoPath
                        ) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <img
                            class="attendance-photo"
                            src="<?= attendanceDetailsEscape(
                                $photoPath
                            ) ?>"
                            alt="Attendance photo"
                        >
                    </a>

                <?php else: ?>

                    <div class="no-photo">
                        No attendance photo was uploaded.
                    </div>

                <?php endif; ?>

            </div>

            <div>

                <div id="attendance-map"></div>

                <div class="coordinate-box">

                    <strong>Latitude:</strong>

                    <?= attendanceDetailsEscape(
                        $attendance['latitude']
                    ) ?>

                    <br>

                    <strong>Longitude:</strong>

                    <?= attendanceDetailsEscape(
                        $attendance['longitude']
                    ) ?>

                    <br>

                    <a
                        class="map-link"
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
                        Open location in Google Maps
                    </a>

                </div>

            </div>

        </div>

    </section>

    <section class="details-card">

        <h2>Weekly Submission Information</h2>

        <div class="table-wrapper">

            <table class="submission-table">

                <thead>
                    <tr>
                        <th>Submission ID</th>
                        <th>Week</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th>Admin Review</th>
                        <th>Manager Review</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    empty($weeklySubmissions)
                ): ?>

                    <tr>
                        <td
                            colspan="7"
                            class="empty-message"
                        >
                            This attendance record has not been
                            included in a weekly submission.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $weeklySubmissions
                        as $submission
                    ): ?>

                        <tr>

                            <td>
                                #<?= (int) $submission['id'] ?>
                            </td>

                            <td>
                                <?= attendanceDetailsEscape(
                                    $submission[
                                        'week_start'
                                    ]
                                ) ?>

                                to

                                <?= attendanceDetailsEscape(
                                    $submission[
                                        'week_end'
                                    ]
                                ) ?>
                            </td>

                            <td>
                                <span
                                    class="status-badge
                                    <?= attendanceDetailsEscape(
                                        attendanceDetailsStatusClass(
                                            $submission[
                                                'status'
                                            ]
                                        )
                                    ) ?>"
                                >
                                    <?= attendanceDetailsEscape(
                                        attendanceDetailsStatusLabel(
                                            $submission[
                                                'status'
                                            ]
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= attendanceDetailsEscape(
                                    attendanceDetailsDateTime(
                                        $submission[
                                            'submitted_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= attendanceDetailsEscape(
                                    attendanceDetailsDateTime(
                                        $submission[
                                            'admin_reviewed_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= attendanceDetailsEscape(
                                    attendanceDetailsDateTime(
                                        $submission[
                                            'manager_reviewed_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <a
                                    class="small-button"
                                    href="weekly_submission_details.php?id=<?= (int) $submission['id'] ?>"
                                >
                                    View Submission
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
    const attendanceLatitude =
        <?= json_encode($latitude) ?>;

    const attendanceLongitude =
        <?= json_encode($longitude) ?>;

    const attendanceAction =
        <?= json_encode($actionType) ?>;

    const officerName =
        <?= json_encode(
            (string) $attendance[
                'field_officer_name'
            ]
        ) ?>;

    const attendanceMap = L.map(
        'attendance-map'
    ).setView(
        [
            attendanceLatitude,
            attendanceLongitude
        ],
        16
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution:
                '&copy; OpenStreetMap contributors'
        }
    ).addTo(attendanceMap);

    const attendanceMarker = L.marker(
        [
            attendanceLatitude,
            attendanceLongitude
        ]
    ).addTo(attendanceMap);

    attendanceMarker.bindPopup(
        '<strong>' +
        escapeAttendanceMapText(officerName) +
        '</strong><br>' +
        'Action: ' +
        escapeAttendanceMapText(attendanceAction)
    ).openPopup();

    function escapeAttendanceMapText(value) {
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