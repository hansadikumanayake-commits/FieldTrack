<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';

requireRole(['admin']);

const RECENT_RECORD_LIMIT = 20;
const MAP_RECORD_LIMIT = 1000;

function h(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatDateTime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? '-'
        : date('d/m/Y h:i A', $timestamp);
}

function validDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $value
    );

    return (
        $date !== false &&
        $date->format('Y-m-d') === $value
    );
}

function validTime(string $value): bool
{
    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $value
    ) === 1;
}

/*
 * Check whether an uploaded photo actually
 * exists inside the uploads folder.
 */
function existingPhotoPath(
    mixed $storedPath
): ?string {
    $storedPath = trim(
        str_replace(
            '\\',
            '/',
            (string) $storedPath
        )
    );

    if ($storedPath === '') {
        return null;
    }

    $fileName = basename($storedPath);

    if (
        $fileName === '' ||
        $fileName === '.' ||
        $fileName === '..'
    ) {
        return null;
    }

    $absolutePath =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR .
        $fileName;

    if (!is_file($absolutePath)) {
        return null;
    }

    return 'uploads/' . rawurlencode($fileName);
}

/**
 * Run a prepared SELECT query.
 *
 * @param array<int, int|float|string> $params
 */
function runSelect(
    mysqli $conn,
    string $sql,
    string $types = '',
    array $params = []
): mysqli_result {
    $stmt = $conn->prepare($sql);

    if ($types !== '') {
        $bindValues = [$types];

        foreach ($params as $index => $value) {
            $params[$index] = $value;
            $bindValues[] = &$params[$index];
        }

        call_user_func_array(
            [$stmt, 'bind_param'],
            $bindValues
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

function databaseFailure(
    Throwable $error
): never {
    error_log(
        'FieldTrack admin panel error: ' .
        $error->getMessage()
    );

    http_response_code(500);

    exit(
        'The admin data could not be loaded. ' .
        'Please try again.'
    );
}

/*
 * Get all field officers for the dropdown.
 */
$officers = [];

try {
    $officerResult = runSelect(
        $conn,
        "SELECT
            id,
            name,
            username

         FROM users

         WHERE role = 'user'

         ORDER BY name ASC"
    );

    while (
        $officer = $officerResult->fetch_assoc()
    ) {
        $officers[] = $officer;
    }
} catch (Throwable $error) {
    databaseFailure($error);
}

/*
 * Read filter values.
 */
$selectedUser = trim(
    (string) ($_GET['user_id'] ?? '')
);

$dateRange = trim(
    (string) ($_GET['date_range'] ?? 'all')
);

$actionType = trim(
    (string) ($_GET['action_type'] ?? '')
);

$photoFilter = trim(
    (string) ($_GET['photo_filter'] ?? '')
);

$fromDate = trim(
    (string) ($_GET['from_date'] ?? '')
);

$toDate = trim(
    (string) ($_GET['to_date'] ?? '')
);

$fromTime = trim(
    (string) ($_GET['from_time'] ?? '')
);

$toTime = trim(
    (string) ($_GET['to_time'] ?? '')
);

/*
 * Validate officer ID.
 */
if (
    $selectedUser !== '' &&
    !ctype_digit($selectedUser)
) {
    $selectedUser = '';
}

$allowedDateRanges = [
    'all',
    'today',
    'yesterday',
    'last_7_days',
    'last_30_days',
    'this_month',
    'custom'
];

if (!in_array(
    $dateRange,
    $allowedDateRanges,
    true
)) {
    $dateRange = 'all';
}

if (!in_array(
    $actionType,
    ['', 'IN', 'OUT'],
    true
)) {
    $actionType = '';
}

if (!in_array(
    $photoFilter,
    [
        '',
        'with_photo',
        'without_photo'
    ],
    true
)) {
    $photoFilter = '';
}

/*
 * Validate date and time filters.
 */
$filterError = '';

if ($dateRange === 'custom') {
    if (
        $fromDate === '' ||
        $toDate === ''
    ) {
        $filterError =
            'Please select both From Date and To Date.';
    } elseif (
        !validDate($fromDate) ||
        !validDate($toDate)
    ) {
        $filterError =
            'Please select valid From Date and To Date values.';
    } elseif ($fromDate > $toDate) {
        $filterError =
            'From Date cannot be later than To Date.';
    }
}

if ($filterError === '') {
    if (
        ($fromTime === '') !==
        ($toTime === '')
    ) {
        $filterError =
            'Please select both From Time and To Time.';
    } elseif (
        $fromTime !== '' &&
        (
            !validTime($fromTime) ||
            !validTime($toTime)
        )
    ) {
        $filterError =
            'Please select valid From Time and To Time values.';
    } elseif (
        $fromTime !== '' &&
        $fromTime > $toTime
    ) {
        $filterError =
            'From Time cannot be later than To Time.';
    } elseif (
        $fromTime !== '' &&
        $dateRange === 'all'
    ) {
        $filterError =
            'Please choose a date range when using time filters.';
    }
}

/*
 * Ignore invalid filters and show all records.
 */
$effectiveDateRange =
    $filterError === ''
        ? $dateRange
        : 'all';

$effectiveFromDate =
    $filterError === ''
        ? $fromDate
        : '';

$effectiveToDate =
    $filterError === ''
        ? $toDate
        : '';

$effectiveFromTime =
    $filterError === ''
        ? $fromTime
        : '';

$effectiveToTime =
    $filterError === ''
        ? $toTime
        : '';

/*
 * Build query conditions.
 */
$conditions = [
    "users.role = 'user'"
];

$filterTypes = '';
$filterParams = [];

if ($selectedUser !== '') {
    $conditions[] =
        'attendance_events.user_id = ?';

    $filterTypes .= 'i';

    $filterParams[] =
        (int) $selectedUser;
}

if ($actionType !== '') {
    $conditions[] =
        'attendance_events.action_type = ?';

    $filterTypes .= 's';

    $filterParams[] =
        $actionType;
}

if ($photoFilter === 'with_photo') {
    $conditions[] = "
        attendance_events.photo_path IS NOT NULL
        AND attendance_events.photo_path <> ''
    ";
} elseif (
    $photoFilter === 'without_photo'
) {
    $conditions[] = "
        (
            attendance_events.photo_path IS NULL
            OR attendance_events.photo_path = ''
        )
    ";
}

/*
 * Add date range conditions.
 */
switch ($effectiveDateRange) {
    case 'today':
        $conditions[] = "
            attendance_events.created_at >= CURDATE()
            AND attendance_events.created_at <
                CURDATE() + INTERVAL 1 DAY
        ";
        break;

    case 'yesterday':
        $conditions[] = "
            attendance_events.created_at >=
                CURDATE() - INTERVAL 1 DAY

            AND attendance_events.created_at <
                CURDATE()
        ";
        break;

    case 'last_7_days':
        $conditions[] = "
            attendance_events.created_at >=
                NOW() - INTERVAL 7 DAY
        ";
        break;

    case 'last_30_days':
        $conditions[] = "
            attendance_events.created_at >=
                NOW() - INTERVAL 30 DAY
        ";
        break;

    case 'this_month':
        $conditions[] = "
            attendance_events.created_at >=
                DATE_FORMAT(
                    CURDATE(),
                    '%Y-%m-01'
                )

            AND attendance_events.created_at <
                DATE_FORMAT(
                    CURDATE() + INTERVAL 1 MONTH,
                    '%Y-%m-01'
                )
        ";
        break;

    case 'custom':
        $startDateTime =
            $effectiveFromDate .
            ' ' .
            (
                $effectiveFromTime !== ''
                    ? $effectiveFromTime . ':00'
                    : '00:00:00'
            );

        if ($effectiveToTime !== '') {
            $endObject =
                new DateTimeImmutable(
                    $effectiveToDate .
                    ' ' .
                    $effectiveToTime .
                    ':00'
                );

            /*
             * Add one minute so the selected
             * ending minute is included.
             */
            $endDateTime =
                $endObject
                    ->modify('+1 minute')
                    ->format('Y-m-d H:i:s');
        } else {
            $endObject =
                new DateTimeImmutable(
                    $effectiveToDate .
                    ' 00:00:00'
                );

            /*
             * Include the complete To Date.
             */
            $endDateTime =
                $endObject
                    ->modify('+1 day')
                    ->format('Y-m-d H:i:s');
        }

        $conditions[] = "
            attendance_events.created_at >= ?
            AND attendance_events.created_at < ?
        ";

        $filterTypes .= 'ss';

        $filterParams[] =
            $startDateTime;

        $filterParams[] =
            $endDateTime;

        break;
}

/*
 * Add time conditions to preset ranges.
 */
if (
    $effectiveDateRange !== 'custom' &&
    $effectiveFromTime !== '' &&
    $effectiveToTime !== ''
) {
    $conditions[] = "
        TIME(attendance_events.created_at) >= ?
        AND TIME(attendance_events.created_at) <= ?
    ";

    $filterTypes .= 'ss';

    $filterParams[] =
        $effectiveFromTime . ':00';

    $filterParams[] =
        $effectiveToTime . ':59';
}

$whereSql = implode(
    ' AND ',
    $conditions
);

/*
 * Summary query.
 */
$summarySql = "
    SELECT
        COUNT(
            DISTINCT attendance_events.user_id
        ) AS matching_officers,

        COALESCE(
            SUM(
                attendance_events.action_type = 'IN'
            ),
            0
        ) AS filtered_in,

        COALESCE(
            SUM(
                attendance_events.action_type = 'OUT'
            ),
            0
        ) AS filtered_out,

        COUNT(
            attendance_events.id
        ) AS filtered_records

    FROM attendance_events

    INNER JOIN users
        ON users.id =
           attendance_events.user_id

    WHERE {$whereSql}
";

/*
 * Recent record query.
 */
$recentSql = "
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

    INNER JOIN users
        ON users.id =
           attendance_events.user_id

    WHERE {$whereSql}

    ORDER BY
        attendance_events.created_at DESC,
        attendance_events.id DESC

    LIMIT ?
";

/*
 * Map record query.
 */
$mapSql = "
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

    FROM attendance_events

    INNER JOIN users
        ON users.id =
           attendance_events.user_id

    WHERE {$whereSql}

    ORDER BY
        attendance_events.created_at DESC,
        attendance_events.id DESC

    LIMIT ?
";

try {
    /*
     * Run summary query.
     */
    $summaryResult = runSelect(
        $conn,
        $summarySql,
        $filterTypes,
        $filterParams
    );

    $summary =
        $summaryResult->fetch_assoc() ?: [];

    $matchingOfficers =
        (int) (
            $summary['matching_officers'] ?? 0
        );

    $filteredIn =
        (int) (
            $summary['filtered_in'] ?? 0
        );

    $filteredOut =
        (int) (
            $summary['filtered_out'] ?? 0
        );

    $filteredRecords =
        (int) (
            $summary['filtered_records'] ?? 0
        );

    /*
     * Run recent records query.
     */
    $recentParams =
        $filterParams;

    $recentParams[] =
        RECENT_RECORD_LIMIT;

    $recentResult = runSelect(
        $conn,
        $recentSql,
        $filterTypes . 'i',
        $recentParams
    );

    /*
     * Run map records query.
     */
    $mapParams =
        $filterParams;

    $mapParams[] =
        MAP_RECORD_LIMIT;

    $mapResult = runSelect(
        $conn,
        $mapSql,
        $filterTypes . 'i',
        $mapParams
    );
} catch (Throwable $error) {
    databaseFailure($error);
}

/*
 * Organize map records by officer.
 */
$usersMap = [];
$mapRecordCount = 0;

while (
    $row = $mapResult->fetch_assoc()
) {
    $userId =
        (int) $row['user_id'];

    if (!isset($usersMap[$userId])) {
        $usersMap[$userId] = [
            'id' => $userId,
            'name' => $row['name'],
            'username' => $row['username'],
            'records' => [],
            'visits' => []
        ];
    }

    $usersMap[$userId]['records'][] = [
        'id' =>
            (int) $row['event_id'],

        'action_type' =>
            $row['action_type'],

        'latitude' =>
            $row['latitude'],

        'longitude' =>
            $row['longitude'],

        'photo_path' =>
            existingPhotoPath(
                $row['photo_path'] ?? null
            ),

        'created_at' =>
            $row['created_at'],

        'formatted_datetime' =>
            formatDateTime(
                $row['created_at']
            )
    ];

    $mapRecordCount++;
}

/*
 * Pair IN and OUT records.
 */
foreach (
    $usersMap as $userId => $userData
) {
    /*
     * Query returned newest first.
     * Reverse for chronological pairing.
     */
    $records =
        array_reverse(
            $userData['records']
        );

    $visits = [];
    $currentVisit = null;
    $pairNumber = 1;

    foreach ($records as $record) {
        if (
            $record['action_type'] === 'IN'
        ) {
            /*
             * Save an incomplete previous IN.
             */
            if ($currentVisit !== null) {
                $currentVisit['pair_no'] =
                    $pairNumber;

                $visits[] =
                    $currentVisit;

                $pairNumber++;
            }

            $currentVisit = [
                'pair_no' =>
                    $pairNumber,

                'in' =>
                    $record,

                'out' =>
                    null
            ];
        } elseif (
            $record['action_type'] === 'OUT'
        ) {
            if (
                $currentVisit !== null &&
                $currentVisit['out'] === null
            ) {
                $currentVisit['out'] =
                    $record;

                $currentVisit['pair_no'] =
                    $pairNumber;

                $visits[] =
                    $currentVisit;

                $currentVisit = null;

                $pairNumber++;
            } else {
                /*
                 * Unmatched OUT record.
                 */
                $visits[] = [
                    'pair_no' =>
                        $pairNumber,

                    'in' =>
                        null,

                    'out' =>
                        $record
                ];

                $pairNumber++;
            }
        }
    }

    /*
     * Save an unfinished IN record.
     */
    if ($currentVisit !== null) {
        $currentVisit['pair_no'] =
            $pairNumber;

        $visits[] =
            $currentVisit;
    }

    $usersMap[$userId]['records'] =
        $records;

    $usersMap[$userId]['visits'] =
        $visits;
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

    <div class="header-actions">

        <a
            href="audit_logs.php"
            class="logout-btn audit-btn"
        >
            Audit Logs
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

    <section class="summary-grid">

        <div class="summary-card">

            <h3>Matching Officers</h3>

            <p>
                <?= $matchingOfficers ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Filtered IN</h3>

            <p>
                <?= $filteredIn ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Filtered OUT</h3>

            <p>
                <?= $filteredOut ?>
            </p>

        </div>

        <div class="summary-card">

            <h3>Filtered Records</h3>

            <p>
                <?= $filteredRecords ?>
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

        <?php if (
            $filterError !== ''
        ): ?>

            <div class="filter-error-message">
                <?= h($filterError) ?>
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

                    <?php foreach (
                        $officers as $officer
                    ): ?>

                        <option
                            value="<?= (int) $officer['id'] ?>"
                            <?= (
                                $selectedUser ===
                                (string) $officer['id']
                            )
                                ? 'selected'
                                : '' ?>
                        >
                            <?= h($officer['name']) ?>
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
                        <?= $dateRange === 'all'
                            ? 'selected'
                            : '' ?>
                    >
                        All Dates
                    </option>

                    <option
                        value="today"
                        <?= $dateRange === 'today'
                            ? 'selected'
                            : '' ?>
                    >
                        Today
                    </option>

                    <option
                        value="yesterday"
                        <?= $dateRange === 'yesterday'
                            ? 'selected'
                            : '' ?>
                    >
                        Yesterday
                    </option>

                    <option
                        value="last_7_days"
                        <?= $dateRange === 'last_7_days'
                            ? 'selected'
                            : '' ?>
                    >
                        Last 7 Days
                    </option>

                    <option
                        value="last_30_days"
                        <?= $dateRange === 'last_30_days'
                            ? 'selected'
                            : '' ?>
                    >
                        Last 30 Days
                    </option>

                    <option
                        value="this_month"
                        <?= $dateRange === 'this_month'
                            ? 'selected'
                            : '' ?>
                    >
                        This Month
                    </option>

                    <option
                        value="custom"
                        <?= $dateRange === 'custom'
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
                        <?= $actionType === ''
                            ? 'selected'
                            : '' ?>
                    >
                        All Records
                    </option>

                    <option
                        value="IN"
                        <?= $actionType === 'IN'
                            ? 'selected'
                            : '' ?>
                    >
                        IN Only
                    </option>

                    <option
                        value="OUT"
                        <?= $actionType === 'OUT'
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
                        <?= $photoFilter === ''
                            ? 'selected'
                            : '' ?>
                    >
                        All Records
                    </option>

                    <option
                        value="with_photo"
                        <?= $photoFilter === 'with_photo'
                            ? 'selected'
                            : '' ?>
                    >
                        With Photos
                    </option>

                    <option
                        value="without_photo"
                        <?= $photoFilter === 'without_photo'
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
                    value="<?= h($fromDate) ?>"
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
                    value="<?= h($toDate) ?>"
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
                    value="<?= h($fromTime) ?>"
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
                    value="<?= h($toTime) ?>"
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
                    $recentResult->num_rows > 0
                ): ?>

                    <?php while (
                        $record =
                            $recentResult->fetch_assoc()
                    ): ?>

                        <?php

                        $photoPath =
                            existingPhotoPath(
                                $record['photo_path'] ??
                                null
                            );

                        ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= h($record['name']) ?>
                                </strong>

                                <br>

                                <span>
                                    @<?= h(
                                        $record['username']
                                    ) ?>
                                </span>

                            </td>

                            <td>

                                <span
                                    class="status-badge <?= strtolower(
                                        h(
                                            $record['action_type']
                                        )
                                    ) ?>"
                                >
                                    <?= h(
                                        $record['action_type']
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= h(
                                    formatDateTime(
                                        $record['created_at']
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $record['latitude']
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    $record['longitude']
                                ) ?>
                            </td>

                            <td>

                                <?php if (
                                    $photoPath !== null
                                ): ?>

                                    <a
                                        href="<?= h(
                                            $photoPath
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="photo-link"
                                    >
                                        View Photo
                                    </a>

                                <?php elseif (
                                    !empty(
                                        $record['photo_path']
                                    )
                                ): ?>

                                    <span
                                        class="missing-photo-text"
                                    >
                                        File missing
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="no-photo-text"
                                    >
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

    <section
        class="admin-section shared-map-section"
    >

        <div class="section-title">

            <div>

                <h2>
                    All Officer Locations
                </h2>

                <p>
                    The newest filtered IN and OUT
                    locations are shown on the shared map.
                </p>

            </div>

            <span class="map-record-count">

                <?= $mapRecordCount ?>

                Shown<?= (
                    $filteredRecords >
                    $mapRecordCount
                )
                    ? ' of ' . $filteredRecords
                    : '' ?>

            </span>

        </div>

        <?php if (
            count($usersMap) > 0
        ): ?>

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
                        Markers with the same colour
                        belong to the same IN and OUT
                        visit pair.
                    </p>

                </div>

            </div>

        <?php else: ?>

            <div class="empty-map-box">
                No map records matched the
                selected filters.
            </div>

        <?php endif; ?>

    </section>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const usersMapData = <?= json_encode(
    $usersMap,
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
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function hasCoordinates(record) {
    return (
        record &&
        record.latitude !== null &&
        record.longitude !== null &&
        record.latitude !== "" &&
        record.longitude !== ""
    );
}

function createPairIcon(type, color) {
    return L.divIcon({
        className: "admin-custom-marker",

        html: `
            <div
                class="admin-marker-pin"
                style="background:${color}"
            >
                <span>
                    ${escapeHtml(type)}
                </span>
            </div>
        `,

        iconSize: [46, 46],
        iconAnchor: [23, 46],
        popupAnchor: [0, -42]
    });
}

function buildTooltip(
    user,
    visitNumber,
    type,
    record
) {
    return `
        <strong>
            ${escapeHtml(user.name)}
        </strong>

        <br>

        @${escapeHtml(user.username)}

        <br>

        ${escapeHtml(type)}
        -
        Visit ${escapeHtml(visitNumber)}

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
    user,
    visitNumber,
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
                href="${escapeHtml(
                    record.photo_path
                )}"
                target="_blank"
                rel="noopener noreferrer"
            >
                <img
                    src="${escapeHtml(
                        record.photo_path
                    )}"
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
                    -
                    Visit ${escapeHtml(
                        visitNumber
                    )}
                </strong>

                <span>
                    ${escapeHtml(user.name)}
                    (@${escapeHtml(
                        user.username
                    )})
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

                ${escapeHtml(
                    record.latitude
                )}
            </p>

            <p>
                <b>Longitude:</b>

                ${escapeHtml(
                    record.longitude
                )}
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

const mapElement =
    document.getElementById(
        "admin-map"
    );

if (mapElement) {
    const map = L.map(
        "admin-map",
        {
            scrollWheelZoom: true
        }
    );

    L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            maxZoom: 19,

            attribution:
                "&copy; OpenStreetMap contributors"
        }
    ).addTo(map);

    const bounds = [];

    let colorIndex = 0;

    Object.values(
        usersMapData
    ).forEach(user => {
        (
            user.visits || []
        ).forEach(visit => {
            const color =
                pairColors[
                    colorIndex %
                    pairColors.length
                ];

            colorIndex++;

            const pairPoints = [];

            [
                [
                    "IN",
                    visit.in
                ],
                [
                    "OUT",
                    visit.out
                ]
            ].forEach(
                ([type, record]) => {
                    if (
                        !hasCoordinates(
                            record
                        )
                    ) {
                        return;
                    }

                    const latitude =
                        Number.parseFloat(
                            record.latitude
                        );

                    const longitude =
                        Number.parseFloat(
                            record.longitude
                        );

                    if (
                        !Number.isFinite(
                            latitude
                        ) ||
                        !Number.isFinite(
                            longitude
                        )
                    ) {
                        return;
                    }

                    const marker =
                        L.marker(
                            [
                                latitude,
                                longitude
                            ],
                            {
                                icon:
                                    createPairIcon(
                                        type,
                                        color
                                    )
                            }
                        ).addTo(map);

                    marker.bindTooltip(
                        buildTooltip(
                            user,
                            visit.pair_no,
                            type,
                            record
                        ),
                        {
                            direction: "top",
                            sticky: true,
                            opacity: 0.95
                        }
                    );

                    marker.bindPopup(
                        buildPopup(
                            user,
                            visit.pair_no,
                            type,
                            record,
                            color
                        )
                    );

                    pairPoints.push([
                        latitude,
                        longitude
                    ]);

                    bounds.push([
                        latitude,
                        longitude
                    ]);
                }
            );

            if (
                pairPoints.length === 2
            ) {
                L.polyline(
                    pairPoints,
                    {
                        color: color,
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
    } else if (
        bounds.length > 1
    ) {
        map.fitBounds(
            bounds,
            {
                padding: [50, 50],
                maxZoom: 16
            }
        );
    } else {
        map.setView(
            [
                7.8731,
                80.7718
            ],
            7
        );
    }

    setTimeout(
        function () {
            map.invalidateSize();
        },
        300
    );
}

const dateRangeSelect =
    document.getElementById(
        "date_range"
    );

const fromDateGroup =
    document.getElementById(
        "from-date-group"
    );

const toDateGroup =
    document.getElementById(
        "to-date-group"
    );

const fromDateInput =
    document.getElementById(
        "from_date"
    );

const toDateInput =
    document.getElementById(
        "to_date"
    );

const fromTimeInput =
    document.getElementById(
        "from_time"
    );

const toTimeInput =
    document.getElementById(
        "to_time"
    );

const filterForm =
    document.querySelector(
        ".admin-filter-form"
    );

function updateCustomDateFields() {
    const customSelected =
        dateRangeSelect.value ===
        "custom";

    fromDateGroup.style.display =
        customSelected
            ? "flex"
            : "none";

    toDateGroup.style.display =
        customSelected
            ? "flex"
            : "none";

    fromDateInput.disabled =
        !customSelected;

    toDateInput.disabled =
        !customSelected;
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
            dateRangeSelect.value ===
            "custom"
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
                fromTimeInput.value ===
                ""
            ) !==
            (
                toTimeInput.value ===
                ""
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