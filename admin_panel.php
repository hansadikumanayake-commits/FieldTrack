<?php

require_once 'auth.php';
require_once 'db.php';

requireRole(['admin']);

session_start();
include "db.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}

function formatDateTime($dateTime)
{
    if (empty($dateTime)) {
        return "-";
    }

    return date("d/m/Y h:i A", strtotime($dateTime));
}

function isValidDateValue($date)
{
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    return (
        $dateObject !== false &&
        $dateObject->format('Y-m-d') === $date
    );
}

function isValidTimeValue($time)
{
    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $time
    ) === 1;
}

$officers = [];

$officer_sql = "
    SELECT id, name, username
    FROM users
    WHERE role = 'user'
    ORDER BY name ASC
";

$officers_result = mysqli_query($conn, $officer_sql);

if (!$officers_result) {
    die("Officer query failed: " . mysqli_error($conn));
}

while ($officer = mysqli_fetch_assoc($officers_result)) {
    $officers[] = $officer;
}

$selected_user = isset($_GET['user_id'])
    ? trim($_GET['user_id'])
    : '';

$date_range = isset($_GET['date_range'])
    ? trim($_GET['date_range'])
    : 'all';

$action_type = isset($_GET['action_type'])
    ? trim($_GET['action_type'])
    : '';

$photo_filter = isset($_GET['photo_filter'])
    ? trim($_GET['photo_filter'])
    : '';

$from_date = isset($_GET['from_date'])
    ? trim($_GET['from_date'])
    : '';

$to_date = isset($_GET['to_date'])
    ? trim($_GET['to_date'])
    : '';

$from_time = isset($_GET['from_time'])
    ? trim($_GET['from_time'])
    : '';

$to_time = isset($_GET['to_time'])
    ? trim($_GET['to_time'])
    : '';

if (
    $selected_user !== '' &&
    !ctype_digit($selected_user)
) {
    $selected_user = '';
}

$allowed_date_ranges = [
    'all',
    'today',
    'yesterday',
    'last_7_days',
    'last_30_days',
    'this_month',
    'custom'
];

if (!in_array($date_range, $allowed_date_ranges, true)) {
    $date_range = 'all';
}

if (!in_array($action_type, ['', 'IN', 'OUT'], true)) {
    $action_type = '';
}

if (
    !in_array(
        $photo_filter,
        ['', 'with_photo', 'without_photo'],
        true
    )
) {
    $photo_filter = '';
}

$filter_error = '';

if ($date_range === 'custom') {
    if ($from_date === '' || $to_date === '') {
        $filter_error =
            'Please select both From Date and To Date.';
    } elseif (
        !isValidDateValue($from_date) ||
        !isValidDateValue($to_date)
    ) {
        $filter_error =
            'Please select valid From Date and To Date values.';
    } elseif ($from_date > $to_date) {
        $filter_error =
            'From Date cannot be later than To Date.';
    }
}

if ($filter_error === '') {
    if (
        ($from_time !== '' && $to_time === '') ||
        ($from_time === '' && $to_time !== '')
    ) {
        $filter_error =
            'Please select both From Time and To Time.';
    } elseif (
        $from_time !== '' &&
        $to_time !== '' &&
        (
            !isValidTimeValue($from_time) ||
            !isValidTimeValue($to_time)
        )
    ) {
        $filter_error =
            'Please select valid From Time and To Time values.';
    } elseif (
        $from_time !== '' &&
        $to_time !== '' &&
        $from_time > $to_time
    ) {
        $filter_error =
            'From Time cannot be later than To Time.';
    }
}

$effective_date_range = $date_range;
$effective_from_date = $from_date;
$effective_to_date = $to_date;
$effective_from_time = $from_time;
$effective_to_time = $to_time;

if ($filter_error !== '') {
    $effective_date_range = 'all';
    $effective_from_date = '';
    $effective_to_date = '';
    $effective_from_time = '';
    $effective_to_time = '';
}

$conditions = [
    "users.role = 'user'"
];

if ($selected_user !== '') {
    $selected_user_id = (int) $selected_user;

    $conditions[] = "
        attendance_events.user_id = $selected_user_id
    ";
}

if ($action_type === 'IN') {
    $conditions[] = "
        attendance_events.action_type = 'IN'
    ";
}

if ($action_type === 'OUT') {
    $conditions[] = "
        attendance_events.action_type = 'OUT'
    ";
}

if ($photo_filter === 'with_photo') {
    $conditions[] = "
        attendance_events.photo_path IS NOT NULL
        AND attendance_events.photo_path != ''
    ";
}

if ($photo_filter === 'without_photo') {
    $conditions[] = "
        (
            attendance_events.photo_path IS NULL
            OR attendance_events.photo_path = ''
        )
    ";
}

switch ($effective_date_range) {
    case 'today':
        $conditions[] = "
            DATE(attendance_events.created_at) = CURDATE()
        ";
        break;

    case 'yesterday':
        $conditions[] = "
            DATE(attendance_events.created_at)
            = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ";
        break;

    case 'last_7_days':
        $conditions[] = "
            attendance_events.created_at
            >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        break;

    case 'last_30_days':
        $conditions[] = "
            attendance_events.created_at
            >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        break;

    case 'this_month':
        $conditions[] = "
            YEAR(attendance_events.created_at) = YEAR(CURDATE())
            AND
            MONTH(attendance_events.created_at) = MONTH(CURDATE())
        ";
        break;

    case 'custom':
        $safe_from_date = mysqli_real_escape_string(
            $conn,
            $effective_from_date
        );

        $safe_to_date = mysqli_real_escape_string(
            $conn,
            $effective_to_date
        );

        $conditions[] = "
            DATE(attendance_events.created_at)
            BETWEEN '$safe_from_date' AND '$safe_to_date'
        ";
        break;
}

if (
    $effective_from_time !== '' &&
    $effective_to_time !== ''
) {
    $safe_from_time = mysqli_real_escape_string(
        $conn,
        $effective_from_time
    );

    $safe_to_time = mysqli_real_escape_string(
        $conn,
        $effective_to_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        BETWEEN '$safe_from_time' AND '$safe_to_time'
    ";
}

$where_sql = implode(' AND ', $conditions);

$summary_sql = "
    SELECT
        COUNT(
            DISTINCT attendance_events.user_id
        ) AS matching_officers,

        COALESCE(
            SUM(attendance_events.action_type = 'IN'),
            0
        ) AS filtered_in,

        COALESCE(
            SUM(attendance_events.action_type = 'OUT'),
            0
        ) AS filtered_out,

        COUNT(
            attendance_events.id
        ) AS filtered_records

    FROM attendance_events

    JOIN users
        ON attendance_events.user_id = users.id

    WHERE $where_sql
";

$summary_result = mysqli_query($conn, $summary_sql);

if (!$summary_result) {
    die("Summary query failed: " . mysqli_error($conn));
}

$summary = mysqli_fetch_assoc($summary_result);

$matching_officers =
    (int) $summary['matching_officers'];

$filtered_in =
    (int) $summary['filtered_in'];

$filtered_out =
    (int) $summary['filtered_out'];

$filtered_records =
    (int) $summary['filtered_records'];

$recent_records_sql = "
    SELECT
        attendance_events.id,
        attendance_events.user_id,
        attendance_events.action_type,
        attendance_events.latitude,
        attendance_events.longitude,
        attendance_events.photo_path,
        attendance_events.created_at,
        users.name,
        users.username

    FROM attendance_events

    JOIN users
        ON attendance_events.user_id = users.id

    WHERE $where_sql

    ORDER BY
        attendance_events.created_at DESC,
        attendance_events.id DESC

    LIMIT 20
";

$recent_records_result = mysqli_query(
    $conn,
    $recent_records_sql
);

if (!$recent_records_result) {
    die(
        "Recent records query failed: " .
        mysqli_error($conn)
    );
}

$records_sql = "
    SELECT
        users.id AS user_id,
        users.name,
        users.username,
        attendance_events.id AS event_id,
        attendance_events.action_type,
        attendance_events.latitude,
        attendance_events.longitude,
        attendance_events.photo_path,
        attendance_events.created_at

    FROM users

    JOIN attendance_events
        ON users.id = attendance_events.user_id

    WHERE $where_sql

    ORDER BY
        users.name ASC,
        users.id ASC,
        attendance_events.created_at ASC,
        attendance_events.id ASC
";

$records_result = mysqli_query($conn, $records_sql);

if (!$records_result) {
    die("Map query failed: " . mysqli_error($conn));
}

$users = [];

while ($row = mysqli_fetch_assoc($records_result)) {
    $user_id = (int) $row['user_id'];

    if (!isset($users[$user_id])) {
        $users[$user_id] = [
            'id' => $user_id,
            'name' => $row['name'],
            'username' => $row['username'],
            'records' => [],
            'visits' => []
        ];
    }

    $users[$user_id]['records'][] = [
        'id' => (int) $row['event_id'],
        'action_type' => $row['action_type'],
        'latitude' => $row['latitude'],
        'longitude' => $row['longitude'],
        'photo_path' => $row['photo_path'],
        'created_at' => $row['created_at'],
        'formatted_datetime' =>
            formatDateTime($row['created_at'])
    ];
}

foreach ($users as $userId => $userData) {
    $visits = [];
    $currentVisit = null;
    $pairNo = 1;

    foreach ($userData['records'] as $record) {
        if ($record['action_type'] === 'IN') {
            if ($currentVisit !== null) {
                $currentVisit['pair_no'] = $pairNo;
                $visits[] = $currentVisit;
                $pairNo++;
            }

            $currentVisit = [
                'pair_no' => $pairNo,
                'in' => $record,
                'out' => null
            ];
        }

        if ($record['action_type'] === 'OUT') {
            if (
                $currentVisit !== null &&
                $currentVisit['out'] === null
            ) {
                $currentVisit['out'] = $record;
                $currentVisit['pair_no'] = $pairNo;
                $visits[] = $currentVisit;

                $currentVisit = null;
                $pairNo++;
            } else {
                $visits[] = [
                    'pair_no' => $pairNo,
                    'in' => null,
                    'out' => $record
                ];

                $pairNo++;
            }
        }
    }

    if ($currentVisit !== null) {
        $currentVisit['pair_no'] = $pairNo;
        $visits[] = $currentVisit;
    }

    $users[$userId]['visits'] = $visits;
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

    <title>FieldTrack Admin Panel</title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="admin_style.css"
    >
</head>

<body>

<header class="admin-header">

    <div>
        <h1>FieldTrack Admin Panel</h1>

        <p>
            Monitor field officers, IN / OUT records,
            photos and locations.
        </p>
    </div>

    <a href="logout.php" class="logout-btn">
        Logout
    </a>

</header>

<main class="admin-container">

    <section class="summary-grid">

        <div class="summary-card">
            <h3>Matching Officers</h3>
            <p><?= $matching_officers ?></p>
        </div>

        <div class="summary-card">
            <h3>Filtered IN</h3>
            <p><?= $filtered_in ?></p>
        </div>

        <div class="summary-card">
            <h3>Filtered OUT</h3>
            <p><?= $filtered_out ?></p>
        </div>

        <div class="summary-card">
            <h3>Filtered Records</h3>
            <p><?= $filtered_records ?></p>
        </div>

    </section>

    <section class="admin-filter-section">

        <div class="filter-heading">

            <div>
                <p class="filter-label">
                    SEARCH AND FILTER
                </p>

                <h2>Filter Attendance Records</h2>
            </div>

            <p class="filter-description">
                Filter attendance by officer, date,
                time, action type and photo.
            </p>

        </div>

        <?php if ($filter_error !== ''): ?>

            <div class="filter-error-message">
                <?= htmlspecialchars($filter_error) ?>
            </div>

        <?php endif; ?>

        <form
            action="admin_panel.php"
            method="GET"
            class="admin-filter-form"
        >

            <div class="filter-group">

                <label for="user_id">
                    Officer
                </label>

                <select name="user_id" id="user_id">

                    <option value="">
                        All Officers
                    </option>

                    <?php foreach ($officers as $officer): ?>

                        <option
                            value="<?= (int) $officer['id'] ?>"
                            <?= (
                                (string) $selected_user ===
                                (string) $officer['id']
                            ) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $officer['name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="filter-group">

                <label for="date_range">
                    Date Range
                </label>

                <select
                    name="date_range"
                    id="date_range"
                >

                    <option
                        value="all"
                        <?= $date_range === 'all'
                            ? 'selected'
                            : '' ?>
                    >
                        All Dates
                    </option>

                    <option
                        value="today"
                        <?= $date_range === 'today'
                            ? 'selected'
                            : '' ?>
                    >
                        Today
                    </option>

                    <option
                        value="yesterday"
                        <?= $date_range === 'yesterday'
                            ? 'selected'
                            : '' ?>
                    >
                        Yesterday
                    </option>

                    <option
                        value="last_7_days"
                        <?= $date_range === 'last_7_days'
                            ? 'selected'
                            : '' ?>
                    >
                        Last 7 Days
                    </option>

                    <option
                        value="last_30_days"
                        <?= $date_range === 'last_30_days'
                            ? 'selected'
                            : '' ?>
                    >
                        Last 30 Days
                    </option>

                    <option
                        value="this_month"
                        <?= $date_range === 'this_month'
                            ? 'selected'
                            : '' ?>
                    >
                        This Month
                    </option>

                    <option
                        value="custom"
                        <?= $date_range === 'custom'
                            ? 'selected'
                            : '' ?>
                    >
                        Custom Date Range
                    </option>

                </select>

            </div>

            <div class="filter-group">

                <label for="action_type">
                    Attendance Type
                </label>

                <select
                    name="action_type"
                    id="action_type"
                >

                    <option
                        value=""
                        <?= $action_type === ''
                            ? 'selected'
                            : '' ?>
                    >
                        All Records
                    </option>

                    <option
                        value="IN"
                        <?= $action_type === 'IN'
                            ? 'selected'
                            : '' ?>
                    >
                        IN Only
                    </option>

                    <option
                        value="OUT"
                        <?= $action_type === 'OUT'
                            ? 'selected'
                            : '' ?>
                    >
                        OUT Only
                    </option>

                </select>

            </div>

            <div class="filter-group">

                <label for="photo_filter">
                    Photo
                </label>

                <select
                    name="photo_filter"
                    id="photo_filter"
                >

                    <option
                        value=""
                        <?= $photo_filter === ''
                            ? 'selected'
                            : '' ?>
                    >
                        All Records
                    </option>

                    <option
                        value="with_photo"
                        <?= $photo_filter === 'with_photo'
                            ? 'selected'
                            : '' ?>
                    >
                        With Photos
                    </option>

                    <option
                        value="without_photo"
                        <?= $photo_filter === 'without_photo'
                            ? 'selected'
                            : '' ?>
                    >
                        Without Photos
                    </option>

                </select>

            </div>

            <div
                class="filter-group"
                id="from-date-group"
            >

                <label for="from_date">
                    From Date
                </label>

                <input
                    type="date"
                    name="from_date"
                    id="from_date"
                    value="<?= htmlspecialchars($from_date) ?>"
                >

            </div>

            <div
                class="filter-group"
                id="to-date-group"
            >

                <label for="to_date">
                    To Date
                </label>

                <input
                    type="date"
                    name="to_date"
                    id="to_date"
                    value="<?= htmlspecialchars($to_date) ?>"
                >

            </div>

            <div class="filter-group">

                <label for="from_time">
                    From Time
                </label>

                <input
                    type="time"
                    name="from_time"
                    id="from_time"
                    value="<?= htmlspecialchars($from_time) ?>"
                >

            </div>

            <div class="filter-group">

                <label for="to_time">
                    To Time
                </label>

                <input
                    type="time"
                    name="to_time"
                    id="to_time"
                    value="<?= htmlspecialchars($to_time) ?>"
                >

            </div>

            <div class="filter-actions">

                <button
                    type="submit"
                    class="apply-filter-btn"
                >
                    Apply Filters
                </button>

                <a
                    href="admin_panel.php"
                    class="reset-filter-btn"
                >
                    Reset Filters
                </a>

            </div>

        </form>

    </section>

    <section class="admin-section">

        <div class="section-title">

            <div>
                <h2>Recent Attendance Records</h2>

                <p>
                    Showing up to 20 records matching
                    the selected filters.
                </p>
            </div>

        </div>

        <div class="table-wrapper">

            <table class="records-table">

                <thead>
                    <tr>
                        <th>Officer</th>
                        <th>Action</th>
                        <th>Date &amp; Time</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Photo</th>
                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    mysqli_num_rows(
                        $recent_records_result
                    ) > 0
                ): ?>

                    <?php while (
                        $record = mysqli_fetch_assoc(
                            $recent_records_result
                        )
                    ): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $record['name']
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="status-badge <?= strtolower(
                                        $record['action_type']
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        $record['action_type']
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    formatDateTime(
                                        $record['created_at']
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $record['latitude']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $record['longitude']
                                ) ?>
                            </td>

                            <td>

                                <?php if (
                                    !empty(
                                        $record['photo_path']
                                    )
                                ): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $record['photo_path']
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="photo-link"
                                    >
                                        View Photo
                                    </a>

                                <?php else: ?>

                                    <span class="no-photo-text">
                                        No photo
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <a
                                    href="attendance_detail.php?id=<?= (int) $record['id'] ?>"
                                    class="photo-link"
                                >
                                    View Details
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7">
                            No attendance records matched
                            the selected filters.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <section class="admin-section shared-map-section">

        <div class="section-title">

            <div>
                <h2>All Officer Locations</h2>

                <p>
                    All filtered IN and OUT attendance
                    locations are displayed on map.
                </p>
            </div>

            <span class="map-record-count">
                <?= $filtered_records ?>
                Record<?= $filtered_records === 1
                    ? ''
                    : 's' ?>
            </span>

        </div>

        <?php if (count($users) > 0): ?>

            <div class="shared-map-wrapper">

                <div id="admin-map"></div>

                <div class="map-legend">

                    <div class="legend-item">
                        <span
                            class="legend-label in-label"
                        >
                            IN
                        </span>

                        <span>
                            Officer entered the location
                        </span>
                    </div>

                    <div class="legend-item">
                        <span
                            class="legend-label out-label"
                        >
                            OUT
                        </span>

                        <span>
                            Officer left the location
                        </span>
                    </div>

                    <p class="legend-note">
                        Markers with the same colour belong
                        to the same IN and OUT visit pair.
                    </p>

                </div>

            </div>

        <?php else: ?>

            <div class="empty-map-box">
                No map records matched the selected filters.
            </div>

        <?php endif; ?>

    </section>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const usersMapData = <?= json_encode(
    $users,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;

const pairColors = [
    "#2563eb",
    "#16a34a",
    "#e11d48",
    "#7c3aed",
    "#f59e0b",
    "#06b6d4",
    "#db2777",
    "#0f766e"
];

function escapeHtml(value) {
    if (
        value === null ||
        value === undefined
    ) {
        return "";
    }

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function createPairIcon(type, color) {
    return L.divIcon({
        className: "admin-custom-marker",

        html: `
            <div
                class="admin-marker-pin"
                style="background:${color}"
            >
                <span>${escapeHtml(type)}</span>
            </div>
        `,

        iconSize: [46, 46],
        iconAnchor: [23, 46],
        popupAnchor: [0, -42]
    });
}

function buildTooltip(
    userName,
    username,
    pairNo,
    type,
    record
) {
    return `
        <strong>
            ${escapeHtml(userName)}
        </strong>

        <br>

        @${escapeHtml(username)}

        <br>

        ${escapeHtml(type)}
        - Visit ${escapeHtml(pairNo)}

        <br>

        ${escapeHtml(
            record.formatted_datetime ||
            record.created_at
        )}

        <br>

        ${escapeHtml(record.latitude)},
        ${escapeHtml(record.longitude)}
    `;
}

function buildPopup(
    userName,
    username,
    pairNo,
    type,
    record,
    color
) {
    let photoHtml = `
        <p class="no-photo-text">
            No photo uploaded
        </p>
    `;

    if (record.photo_path) {
        photoHtml = `
            <a
                href="${escapeHtml(record.photo_path)}"
                target="_blank"
                rel="noopener noreferrer"
            >
                <img
                    src="${escapeHtml(record.photo_path)}"
                    class="map-popup-photo"
                    alt="${escapeHtml(type)} Photo"
                >
            </a>
        `;
    }

    return `
        <div class="map-popup">

            <div
                class="popup-title"
                style="border-left-color:${color}"
            >
                <strong>
                    ${escapeHtml(type)}
                    - Visit ${escapeHtml(pairNo)}
                </strong>

                <span>
                    ${escapeHtml(userName)}
                    (@${escapeHtml(username)})
                </span>
            </div>

            <p>
                <b>Date and time:</b>

                ${escapeHtml(
                    record.formatted_datetime ||
                    record.created_at
                )}
            </p>

            <p>
                <b>Latitude:</b>
                ${escapeHtml(record.latitude)}
            </p>

            <p>
                <b>Longitude:</b>
                ${escapeHtml(record.longitude)}
            </p>

            ${photoHtml}

            <a
                href="attendance_detail.php?id=${encodeURIComponent(
                    record.id
                )}"
                class="popup-details-link"
            >
                View Full Details
            </a>

        </div>
    `;
}

const sharedMapElement =
    document.getElementById("admin-map");

if (sharedMapElement) {
    const map = L.map("admin-map", {
        scrollWheelZoom: true
    });

    L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            maxZoom: 19,
            attribution:
                "&copy; OpenStreetMap contributors"
        }
    ).addTo(map);

    const bounds = [];
    let pairColorIndex = 0;

    Object.keys(usersMapData).forEach(userId => {
        const user = usersMapData[userId];
        const visits = user.visits || [];

        visits.forEach(visit => {
            const pairColor =
                pairColors[
                    pairColorIndex %
                    pairColors.length
                ];

            pairColorIndex++;

            const pairPoints = [];

            if (
                visit.in &&
                visit.in.latitude &&
                visit.in.longitude
            ) {
                const inLat =
                    parseFloat(visit.in.latitude);

                const inLng =
                    parseFloat(visit.in.longitude);

                if (
                    !Number.isNaN(inLat) &&
                    !Number.isNaN(inLng)
                ) {
                    const inMarker = L.marker(
                        [inLat, inLng],
                        {
                            icon: createPairIcon(
                                "IN",
                                pairColor
                            )
                        }
                    ).addTo(map);

                    inMarker.bindTooltip(
                        buildTooltip(
                            user.name,
                            user.username,
                            visit.pair_no,
                            "IN",
                            visit.in
                        ),
                        {
                            direction: "top",
                            sticky: true,
                            opacity: 0.95
                        }
                    );

                    inMarker.bindPopup(
                        buildPopup(
                            user.name,
                            user.username,
                            visit.pair_no,
                            "IN",
                            visit.in,
                            pairColor
                        )
                    );

                    pairPoints.push(
                        [inLat, inLng]
                    );

                    bounds.push(
                        [inLat, inLng]
                    );
                }
            }

            if (
                visit.out &&
                visit.out.latitude &&
                visit.out.longitude
            ) {
                const outLat =
                    parseFloat(visit.out.latitude);

                const outLng =
                    parseFloat(visit.out.longitude);

                if (
                    !Number.isNaN(outLat) &&
                    !Number.isNaN(outLng)
                ) {
                    const outMarker = L.marker(
                        [outLat, outLng],
                        {
                            icon: createPairIcon(
                                "OUT",
                                pairColor
                            )
                        }
                    ).addTo(map);

                    outMarker.bindTooltip(
                        buildTooltip(
                            user.name,
                            user.username,
                            visit.pair_no,
                            "OUT",
                            visit.out
                        ),
                        {
                            direction: "top",
                            sticky: true,
                            opacity: 0.95
                        }
                    );

                    outMarker.bindPopup(
                        buildPopup(
                            user.name,
                            user.username,
                            visit.pair_no,
                            "OUT",
                            visit.out,
                            pairColor
                        )
                    );

                    pairPoints.push(
                        [outLat, outLng]
                    );

                    bounds.push(
                        [outLat, outLng]
                    );
                }
            }

            if (pairPoints.length === 2) {
                L.polyline(
                    pairPoints,
                    {
                        color: pairColor,
                        weight: 5,
                        opacity: 0.85,
                        lineCap: "round",
                        dashArray: "8, 8"
                    }
                ).addTo(map);
            }
        });
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 16);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, {
            padding: [50, 50],
            maxZoom: 16
        });
    } else {
        map.setView(
            [7.8731, 80.7718],
            7
        );
    }

    setTimeout(() => {
        map.invalidateSize();
    }, 300);
}

const dateRangeSelect =
    document.getElementById("date_range");

const fromDateGroup =
    document.getElementById("from-date-group");

const toDateGroup =
    document.getElementById("to-date-group");

const fromDateInput =
    document.getElementById("from_date");

const toDateInput =
    document.getElementById("to_date");

const fromTimeInput =
    document.getElementById("from_time");

const toTimeInput =
    document.getElementById("to_time");

const filterForm =
    document.querySelector(".admin-filter-form");

function updateCustomDateFields() {
    const isCustom =
        dateRangeSelect.value === "custom";

    fromDateGroup.style.display =
        isCustom ? "flex" : "none";

    toDateGroup.style.display =
        isCustom ? "flex" : "none";

    fromDateInput.disabled = !isCustom;
    toDateInput.disabled = !isCustom;
}

dateRangeSelect.addEventListener(
    "change",
    updateCustomDateFields
);

updateCustomDateFields();

filterForm.addEventListener("submit", function (event) {
    if (dateRangeSelect.value === "custom") {
        if (!fromDateInput.value || !toDateInput.value) {
            event.preventDefault();

            alert(
                "Please select both From Date and To Date."
            );

            return;
        }

        if (fromDateInput.value > toDateInput.value) {
            event.preventDefault();

            alert(
                "From Date cannot be later than To Date."
            );

            return;
        }
    }

    if (
        (fromTimeInput.value && !toTimeInput.value) ||
        (!fromTimeInput.value && toTimeInput.value)
    ) {
        event.preventDefault();

        alert(
            "Please select both From Time and To Time."
        );

        return;
    }

    if (
        fromTimeInput.value &&
        toTimeInput.value &&
        fromTimeInput.value > toTimeInput.value
    ) {
        event.preventDefault();

        alert(
            "From Time cannot be later than To Time."
        );
    }
});
</script>

</body>
</html>