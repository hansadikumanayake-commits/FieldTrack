<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';

/*
 * Only logged-in administrators can open this page.
 * auth.php starts or resumes the session.
 */
requireRole(['admin']);

const RECENT_RECORD_LIMIT = 20;
const MAP_RECORD_LIMIT = 1000;

function formatDateTime(?string $dateTime): string
{
    if (empty($dateTime)) {
        return '-';
    }

    $timestamp = strtotime($dateTime);

    return $timestamp === false
        ? '-'
        : date('d/m/Y h:i A', $timestamp);
}

function isValidDateValue(string $date): bool
{
    $dateObject = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    return (
        $dateObject !== false &&
        $dateObject->format('Y-m-d') === $date
    );
}

function isValidTimeValue(string $time): bool
{
    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $time
    ) === 1;
}

/**
 * Runs a prepared SELECT query.
 *
 * @param array<int, int|float|string> $params
 */
function runPreparedSelect(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): mysqli_result {
    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $bindArguments = [$types];

        foreach ($params as $index => $value) {
            $params[$index] = $value;
            $bindArguments[] = &$params[$index];
        }

        call_user_func_array(
            [$stmt, 'bind_param'],
            $bindArguments
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    $stmt->close();

    if (!$result instanceof mysqli_result) {
        throw new RuntimeException(
            'The database did not return a result set.'
        );
    }

    return $result;
}

function stopForDatabaseError(Throwable $error): never
{
    error_log(
        'FieldTrack admin panel database error: ' .
        $error->getMessage()
    );

    http_response_code(500);

    exit(
        'The admin data could not be loaded. ' .
        'Please try again.'
    );
}

/*
 * Get all field officers for the filter dropdown.
 */
$officers = [];

try {
    $officers_result = runPreparedSelect(
        $conn,
        "
            SELECT id, name, username
            FROM users
            WHERE role = 'user'
            ORDER BY name ASC
        "
    );

    while ($officer = $officers_result->fetch_assoc()) {
        $officers[] = $officer;
    }
} catch (Throwable $error) {
    stopForDatabaseError($error);
}

/*
 * Read filter values.
 */
$selected_user = trim(
    (string) ($_GET['user_id'] ?? '')
);

$date_range = trim(
    (string) ($_GET['date_range'] ?? 'all')
);

$action_type = trim(
    (string) ($_GET['action_type'] ?? '')
);

$photo_filter = trim(
    (string) ($_GET['photo_filter'] ?? '')
);

$from_date = trim(
    (string) ($_GET['from_date'] ?? '')
);

$to_date = trim(
    (string) ($_GET['to_date'] ?? '')
);

$from_time = trim(
    (string) ($_GET['from_time'] ?? '')
);

$to_time = trim(
    (string) ($_GET['to_time'] ?? '')
);

/*
 * Validate officer ID.
 */
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

if (
    !in_array(
        $date_range,
        $allowed_date_ranges,
        true
    )
) {
    $date_range = 'all';
}

if (
    !in_array(
        $action_type,
        ['', 'IN', 'OUT'],
        true
    )
) {
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

/*
 * Validate date and time filters.
 */
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
    } elseif (
        $from_time !== '' &&
        $date_range === 'all'
    ) {
        $filter_error =
            'Please choose a date range when using time filters.';
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

/*
 * Build secure prepared-statement conditions.
 */
$conditions = [
    "u.role = 'user'"
];

$filter_types = '';
$filter_params = [];

if ($selected_user !== '') {
    $conditions[] = 'ae.user_id = ?';

    $filter_types .= 'i';
    $filter_params[] = (int) $selected_user;
}

if ($action_type !== '') {
    $conditions[] = 'ae.action_type = ?';

    $filter_types .= 's';
    $filter_params[] = $action_type;
}

if ($photo_filter === 'with_photo') {
    $conditions[] = "
        ae.photo_path IS NOT NULL
        AND ae.photo_path <> ''
    ";
}

if ($photo_filter === 'without_photo') {
    $conditions[] = "
        (
            ae.photo_path IS NULL
            OR ae.photo_path = ''
        )
    ";
}

/*
 * Date filters compare created_at directly.
 * This allows MySQL to use a created_at index.
 */
switch ($effective_date_range) {
    case 'today':
        $conditions[] = "
            ae.created_at >= CURDATE()
            AND ae.created_at < CURDATE() + INTERVAL 1 DAY
        ";
        break;

    case 'yesterday':
        $conditions[] = "
            ae.created_at >= CURDATE() - INTERVAL 1 DAY
            AND ae.created_at < CURDATE()
        ";
        break;

    case 'last_7_days':
        $conditions[] = "
            ae.created_at >= NOW() - INTERVAL 7 DAY
        ";
        break;

    case 'last_30_days':
        $conditions[] = "
            ae.created_at >= NOW() - INTERVAL 30 DAY
        ";
        break;

    case 'this_month':
        $conditions[] = "
            ae.created_at >=
                DATE_FORMAT(CURDATE(), '%Y-%m-01')

            AND ae.created_at <
                DATE_FORMAT(
                    CURDATE() + INTERVAL 1 MONTH,
                    '%Y-%m-01'
                )
        ";
        break;

    case 'custom':
        $start_time = $effective_from_time !== ''
            ? $effective_from_time . ':00'
            : '00:00:00';

        $start_datetime =
            $effective_from_date . ' ' . $start_time;

        if ($effective_to_time !== '') {
            $end_object =
                DateTimeImmutable::createFromFormat(
                    '!Y-m-d H:i:s',
                    $effective_to_date . ' ' .
                    $effective_to_time . ':00'
                );

            if ($end_object === false) {
                $filter_error =
                    'The selected custom date and time are invalid.';

                break;
            }

            /*
             * Add one minute so the selected ending
             * minute is included.
             */
            $end_datetime = $end_object
                ->modify('+1 minute')
                ->format('Y-m-d H:i:s');
        } else {
            $end_object =
                DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $effective_to_date
                );

            if ($end_object === false) {
                $filter_error =
                    'The selected custom date is invalid.';

                break;
            }

            $end_datetime = $end_object
                ->modify('+1 day')
                ->format('Y-m-d 00:00:00');
        }

        $conditions[] = "
            ae.created_at >= ?
            AND ae.created_at < ?
        ";

        $filter_types .= 'ss';

        $filter_params[] = $start_datetime;
        $filter_params[] = $end_datetime;

        break;
}

/*
 * Apply time filters to preset date ranges.
 */
if (
    $effective_date_range !== 'custom' &&
    $effective_from_time !== '' &&
    $effective_to_time !== ''
) {
    $conditions[] = "
        TIME(ae.created_at) >= ?
        AND TIME(ae.created_at) <= ?
    ";

    $filter_types .= 'ss';

    $filter_params[] =
        $effective_from_time . ':00';

    $filter_params[] =
        $effective_to_time . ':59';
}

/*
 * Fall back safely if DateTime creation failed.
 */
if ($filter_error !== '') {
    $conditions = [
        "u.role = 'user'"
    ];

    $filter_types = '';
    $filter_params = [];
}

$where_sql = implode(' AND ', $conditions);

/*
 * Summary query.
 */
$summary_sql = "
    SELECT
        COUNT(DISTINCT ae.user_id)
            AS matching_officers,

        COALESCE(
            SUM(ae.action_type = 'IN'),
            0
        ) AS filtered_in,

        COALESCE(
            SUM(ae.action_type = 'OUT'),
            0
        ) AS filtered_out,

        COUNT(ae.id)
            AS filtered_records

    FROM attendance_events AS ae

    INNER JOIN users AS u
        ON u.id = ae.user_id

    WHERE $where_sql
";

/*
 * Recent attendance table query.
 */
$recent_records_sql = "
    SELECT
        ae.id,
        ae.user_id,
        ae.action_type,
        ae.latitude,
        ae.longitude,
        ae.photo_path,
        ae.created_at,
        u.name,
        u.username

    FROM attendance_events AS ae

    INNER JOIN users AS u
        ON u.id = ae.user_id

    WHERE $where_sql

    ORDER BY
        ae.created_at DESC,
        ae.id DESC

    LIMIT ?
";

/*
 * Map records query.
 *
 * The map is limited so the browser does not try
 * to display unlimited markers.
 */
$map_records_sql = "
    SELECT
        u.id AS user_id,
        u.name,
        u.username,
        ae.id AS event_id,
        ae.action_type,
        ae.latitude,
        ae.longitude,
        ae.photo_path,
        ae.created_at

    FROM attendance_events AS ae

    INNER JOIN users AS u
        ON u.id = ae.user_id

    WHERE $where_sql

    ORDER BY
        ae.created_at DESC,
        ae.id DESC

    LIMIT ?
";

try {
    /*
     * Execute summary query.
     */
    $summary_result = runPreparedSelect(
        $conn,
        $summary_sql,
        $filter_types,
        $filter_params
    );

    $summary = $summary_result->fetch_assoc();

    $matching_officers =
        (int) ($summary['matching_officers'] ?? 0);

    $filtered_in =
        (int) ($summary['filtered_in'] ?? 0);

    $filtered_out =
        (int) ($summary['filtered_out'] ?? 0);

    $filtered_records =
        (int) ($summary['filtered_records'] ?? 0);

    /*
     * Execute recent-records query.
     */
    $recent_types = $filter_types . 'i';

    $recent_params = $filter_params;
    $recent_params[] = RECENT_RECORD_LIMIT;

    $recent_records_result = runPreparedSelect(
        $conn,
        $recent_records_sql,
        $recent_types,
        $recent_params
    );

    /*
     * Execute map query.
     */
    $map_types = $filter_types . 'i';

    $map_params = $filter_params;
    $map_params[] = MAP_RECORD_LIMIT;

    $records_result = runPreparedSelect(
        $conn,
        $map_records_sql,
        $map_types,
        $map_params
    );
} catch (Throwable $error) {
    stopForDatabaseError($error);
}

/*
 * Organize map records by officer.
 */
$users = [];
$map_record_count = 0;

while ($row = $records_result->fetch_assoc()) {
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

    $map_record_count++;
}

/*
 * Map records are returned newest first.
 * Reverse each officer's records before pairing.
 */
foreach ($users as $user_id => $user_data) {
    $users[$user_id]['records'] = array_reverse(
        $user_data['records']
    );
}

/*
 * Pair each IN record with the following OUT record.
 */
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

            <p>
                <?= $matching_officers ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Filtered IN</h3>

            <p>
                <?= $filtered_in ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Filtered OUT</h3>

            <p>
                <?= $filtered_out ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Filtered Records</h3>

            <p>
                <?= $filtered_records ?>
            </p>
        </div>

    </section>

    <section class="admin-filter-section">

        <div class="filter-heading">

            <div>
                <p class="filter-label">
                    SEARCH AND FILTER
                </p>

                <h2>
                    Filter Attendance Records
                </h2>
            </div>

            <p class="filter-description">
                Filter attendance by officer, date,
                time, action type and photo.
            </p>

        </div>

        <?php if ($filter_error !== ''): ?>

            <div class="filter-error-message">
                <?= htmlspecialchars(
                    $filter_error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
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

                <select
                    name="user_id"
                    id="user_id"
                >

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
                                $officer['name'],
                                ENT_QUOTES,
                                'UTF-8'
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
                    value="<?= htmlspecialchars(
                        $from_date,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $to_date,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $from_time,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $to_time,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                <h2>
                    Recent Attendance Records
                </h2>

                <p>
                    Showing up to
                    <?= RECENT_RECORD_LIMIT ?>
                    records matching the selected filters.
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
                    $recent_records_result->num_rows > 0
                ): ?>

                    <?php while (
                        $record =
                            $recent_records_result->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $record['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="status-badge <?= strtolower(
                                        htmlspecialchars(
                                            $record['action_type'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        $record['action_type'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    formatDateTime(
                                        $record['created_at']
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $record['latitude'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $record['longitude'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td>

                                <?php if (
                                    !empty($record['photo_path'])
                                ): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $record['photo_path'],
                                            ENT_QUOTES,
                                            'UTF-8'
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
                <h2>
                    All Officer Locations
                </h2>

                <p>
                    The newest filtered IN and OUT locations are
                    displayed on the map for consistent performance.
                </p>
            </div>

            <span class="map-record-count">
                <?= $map_record_count ?>

                Shown<?= $filtered_records > $map_record_count
                    ? ' of ' . $filtered_records
                    : '' ?>
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

                    pairPoints.push([
                        inLat,
                        inLng
                    ]);

                    bounds.push([
                        inLat,
                        inLng
                    ]);
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

                    pairPoints.push([
                        outLat,
                        outLng
                    ]);

                    bounds.push([
                        outLat,
                        outLng
                    ]);
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
        map.setView(
            bounds[0],
            16
        );
    } else if (bounds.length > 1) {
        map.fitBounds(
            bounds,
            {
                padding: [50, 50],
                maxZoom: 16
            }
        );
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

filterForm.addEventListener(
    "submit",
    function (event) {
        if (
            dateRangeSelect.value === "custom"
        ) {
            if (
                !fromDateInput.value ||
                !toDateInput.value
            ) {
                event.preventDefault();

                alert(
                    "Please select both From Date and To Date."
                );

                return;
            }

            if (
                fromDateInput.value >
                toDateInput.value
            ) {
                event.preventDefault();

                alert(
                    "From Date cannot be later than To Date."
                );

                return;
            }
        }

        if (
            (
                fromTimeInput.value &&
                !toTimeInput.value
            ) ||
            (
                !fromTimeInput.value &&
                toTimeInput.value
            )
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
            fromTimeInput.value >
            toTimeInput.value
        ) {
            event.preventDefault();

            alert(
                "From Time cannot be later than To Time."
            );
        }
    }
);
</script>

</body>
</html>