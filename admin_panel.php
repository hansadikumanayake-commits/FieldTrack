
Friday 10:37 AM
Friday 3:48 PM
422f1c8a-44b3-47f3-be3a-906fb6601f03.png
where should i paste thid 

Today 8:02 PM

Pasted code(4).php
File

I checked your uploaded admin_panel.php. The main problem is that the officer filter form is incomplete and contains invalid PHP/HTML. Your table and map queries also do not yet use $where_sql. 


Make these exact changes.

1. Correct the officers array
Near the top, find:

$officer=[];
Replace it with:

$officers = [];
2. Move the admin login check to the top
Immediately after:

session_start();
include "db.php";
add:

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}
Then delete the same login-check code that currently appears farther down after:

$where_sql = implode(' AND ', $conditions);
3. Add the missing time filters
Find:

$where_sql = implode(' AND ', $conditions);
Paste this immediately above it:

// Time range filter
if (
    $from_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $from_time)
) {
    $safe_from_time = mysqli_real_escape_string(
        $conn,
        $from_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        >= '$safe_from_time'
    ";
}

if (
    $to_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $to_time)
) {
    $safe_to_time = mysqli_real_escape_string(
        $conn,
        $to_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        <= '$safe_to_time'
    ";
}
The final part should look like:

if ($to_time !== '') {
    // Time-filter code
}

$where_sql = implode(' AND ', $conditions);
4. Replace your complete filter section
Find this section:

<section class="admin-filter-section">
Delete everything from that opening line until its matching:

</section>
Then paste this complete corrected section:

<section class="admin-filter-section">

    <div class="filter-heading">
        <div>
            <p class="filter-label">SEARCH AND FILTER</p>
            <h2>Filter Attendance Records</h2>
        </div>

        <p class="filter-description">
            Filter attendance by officer, date, time,
            action type and uploaded photo.
        </p>
    </div>

    <form
        action="admin_panel.php"
        method="GET"
        class="admin-filter-form"
    >

        <!-- Officer -->
        <div class="filter-group">
            <label for="user_id">Officer</label>

            <select name="user_id" id="user_id">
                <option value="">All Officers</option>

                <?php foreach ($officers as $officer): ?>

                    <option
                        value="<?= (int) $officer['id'] ?>"
                        <?= (string) $selected_user ===
                            (string) $officer['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars($officer['name']) ?>
                    </option>

                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date range -->
        <div class="filter-group">
            <label for="date_range">Date Range</label>

            <select name="date_range" id="date_range">

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

        <!-- Action type -->
        <div class="filter-group">
            <label for="action_type">Attendance Type</label>

            <select name="action_type" id="action_type">

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

        <!-- Photo -->
        <div class="filter-group">
            <label for="photo_filter">Photo</label>

            <select name="photo_filter" id="photo_filter">

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

        <!-- From date -->
        <div class="filter-group">
            <label for="from_date">From Date</label>

            <input
                type="date"
                name="from_date"
                id="from_date"
                value="<?= htmlspecialchars($from_date) ?>"
            >
        </div>

        <!-- To date -->
        <div class="filter-group">
            <label for="to_date">To Date</label>

            <input
                type="date"
                name="to_date"
                id="to_date"
                value="<?= htmlspecialchars($to_date) ?>"
            >
        </div>

        <!-- From time -->
        <div class="filter-group">
            <label for="from_time">From Time</label>

            <input
                type="time"
                name="from_time"
                id="from_time"
                value="<?= htmlspecialchars($from_time) ?>"
            >
        </div>

        <!-- To time -->
        <div class="filter-group">
            <label for="to_time">To Time</label>

            <input
                type="time"
                name="to_time"
                id="to_time"
                value="<?= htmlspecialchars($to_time) ?>"
            >
        </div>

        <!-- Buttons -->
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
Your existing code was wrong here:

<?php foreach($officers as $officer) ; ?>
The semicolon ended the loop immediately. It must use:

<?php foreach ($officers as $officer): ?>
and finish with:

<?php endforeach; ?>
5. Make the recent-records table use the filters
Find and delete your current complete $recent_records_sql query:

$recent_records_sql = "
    SELECT ...
";
Replace it with:

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
Now the table will respond to:

Officer

Date range

IN or OUT

Photo

From time

To time

6. Make the officer records use the same filters
In $records_sql, find:

WHERE users.role = 'user'
Replace only that line with:

WHERE $where_sql
The section should become:

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
    LEFT JOIN attendance_events 
        ON users.id = attendance_events.user_id
    WHERE $where_sql
    ORDER BY
        users.name ASC,
        users.id ASC,
        attendance_events.created_at ASC,
        attendance_events.id ASC
";
Save the file and open:

http://localhost/FieldTrack/admin_panel.php
Test the Officer and IN/OUT filters first. Do not change the map layout yet.


give the correct code

Worked for 49s
Replace the entire contents of your current admin_panel.php with this corrected version:

admin_panel_corrected.php

Your uploaded file had problems with the $officers array, broken foreach syntax, missing filter options, missing time filtering, and queries that did not use the selected filters. 


Do this:

Open:

C:\xampp\htdocs\FieldTrack\admin_panel.php
Press:

Ctrl + A
Delete everything.

Open the downloaded corrected file, copy all its code, and paste it into admin_panel.php.

Save using:

Ctrl + S
Test:

http://localhost/FieldTrack/admin_panel.php
This version includes:

Officer filter

Date-range filter

From and to date

From and to time

IN and OUT filter

Photo filter

Filtered summary cards

Filtered records table

Filtered officer maps

Photo links

Marker hover information

Marker click popup with photo preview

The next required change is adding the filter styling to admin_style.css. 


give the code

Worked for 2m 16s
Replace the entire content of:

C:\xampp\htdocs\FieldTrack\admin_panel.php
Do not paste this below your old code. Press Ctrl + A, delete everything, then paste this:

<?php
session_start();
include "db.php";

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}

function h($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function formatDateTime($dateTime): string
{
    if (empty($dateTime)) {
        return '-';
    }

    return date(
        'd/m/Y h:i A',
        strtotime($dateTime)
    );
}

/* ========================================
   Load officers for the filter dropdown
======================================== */

$officers = [];

$officers_sql = "
    SELECT id, name, username
    FROM users
    WHERE role = 'user'
    ORDER BY name ASC
";

$officers_result = mysqli_query(
    $conn,
    $officers_sql
);

if (!$officers_result) {
    die(
        'Officer query failed: ' .
        mysqli_error($conn)
    );
}

while (
    $officer = mysqli_fetch_assoc(
        $officers_result
    )
) {
    $officers[] = $officer;
}

/* ========================================
   Read filter values
======================================== */

$selected_user = trim(
    $_GET['user_id'] ?? ''
);

$date_range = trim(
    $_GET['date_range'] ?? 'all'
);

$action_type = trim(
    $_GET['action_type'] ?? ''
);

$photo_filter = trim(
    $_GET['photo_filter'] ?? ''
);

$from_date = trim(
    $_GET['from_date'] ?? ''
);

$to_date = trim(
    $_GET['to_date'] ?? ''
);

$from_time = trim(
    $_GET['from_time'] ?? ''
);

$to_time = trim(
    $_GET['to_time'] ?? ''
);

/* ========================================
   Validate filter values
======================================== */

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
        [
            '',
            'with_photo',
            'without_photo'
        ],
        true
    )
) {
    $photo_filter = '';
}

$is_valid_from_date =
    $from_date !== '' &&
    preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $from_date
    );

$is_valid_to_date =
    $to_date !== '' &&
    preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $to_date
    );

$is_valid_from_time =
    $from_time !== '' &&
    preg_match(
        '/^\d{2}:\d{2}$/',
        $from_time
    );

$is_valid_to_time =
    $to_time !== '' &&
    preg_match(
        '/^\d{2}:\d{2}$/',
        $to_time
    );

/* ========================================
   Build filter conditions
======================================== */

$conditions = [
    "users.role = 'user'"
];

if ($selected_user !== '') {
    $selected_user_id =
        (int) $selected_user;

    $conditions[] =
        "attendance_events.user_id =
        $selected_user_id";
}

if ($action_type !== '') {
    $safe_action_type =
        mysqli_real_escape_string(
            $conn,
            $action_type
        );

    $conditions[] =
        "attendance_events.action_type =
        '$safe_action_type'";
}

if ($photo_filter === 'with_photo') {
    $conditions[] = "
        attendance_events.photo_path
        IS NOT NULL
        AND
        attendance_events.photo_path != ''
    ";
}

if (
    $photo_filter ===
    'without_photo'
) {
    $conditions[] = "
        (
            attendance_events.photo_path
            IS NULL
            OR
            attendance_events.photo_path = ''
        )
    ";
}

/* ========================================
   Date range filter
======================================== */

switch ($date_range) {
    case 'today':
        $conditions[] = "
            DATE(
                attendance_events.created_at
            ) = CURDATE()
        ";
        break;

    case 'yesterday':
        $conditions[] = "
            DATE(
                attendance_events.created_at
            ) =
            DATE_SUB(
                CURDATE(),
                INTERVAL 1 DAY
            )
        ";
        break;

    case 'last_7_days':
        $conditions[] = "
            attendance_events.created_at
            >= DATE_SUB(
                NOW(),
                INTERVAL 7 DAY
            )
        ";
        break;

    case 'last_30_days':
        $conditions[] = "
            attendance_events.created_at
            >= DATE_SUB(
                NOW(),
                INTERVAL 30 DAY
            )
        ";
        break;

    case 'this_month':
        $conditions[] = "
            YEAR(
                attendance_events.created_at
            ) = YEAR(CURDATE())
        ";

        $conditions[] = "
            MONTH(
                attendance_events.created_at
            ) = MONTH(CURDATE())
        ";
        break;

    case 'custom':
        if ($is_valid_from_date) {
            $safe_from_date =
                mysqli_real_escape_string(
                    $conn,
                    $from_date
                );

            $conditions[] = "
                DATE(
                    attendance_events.created_at
                ) >= '$safe_from_date'
            ";
        }

        if ($is_valid_to_date) {
            $safe_to_date =
                mysqli_real_escape_string(
                    $conn,
                    $to_date
                );

            $conditions[] = "
                DATE(
                    attendance_events.created_at
                ) <= '$safe_to_date'
            ";
        }
        break;
}

/* ========================================
   Time range filter
======================================== */

if ($is_valid_from_time) {
    $safe_from_time =
        mysqli_real_escape_string(
            $conn,
            $from_time
        );

    $conditions[] = "
        TIME(
            attendance_events.created_at
        ) >= '$safe_from_time'
    ";
}

if ($is_valid_to_time) {
    $safe_to_time =
        mysqli_real_escape_string(
            $conn,
            $to_time
        );

    $conditions[] = "
        TIME(
            attendance_events.created_at
        ) <= '$safe_to_time'
    ";
}

$where_sql = implode(
    ' AND ',
    $conditions
);

/* ========================================
   Filtered summary cards
======================================== */

$summary_sql = "
    SELECT
        COUNT(
            DISTINCT
            attendance_events.user_id
        ) AS matching_officers,

        COALESCE(
            SUM(
                attendance_events.action_type
                = 'IN'
            ),
            0
        ) AS filtered_in,

        COALESCE(
            SUM(
                attendance_events.action_type
                = 'OUT'
            ),
            0
        ) AS filtered_out,

        COUNT(
            attendance_events.id
        ) AS filtered_records

    FROM attendance_events

    JOIN users
        ON attendance_events.user_id
        = users.id

    WHERE $where_sql
";

$summary_result = mysqli_query(
    $conn,
    $summary_sql
);

if (!$summary_result) {
    die(
        'Summary query failed: ' .
        mysqli_error($conn)
    );
}

$summary = mysqli_fetch_assoc(
    $summary_result
);

$matching_officers =
    (int) $summary['matching_officers'];

$filtered_in =
    (int) $summary['filtered_in'];

$filtered_out =
    (int) $summary['filtered_out'];

$filtered_records =
    (int) $summary['filtered_records'];

/* ========================================
   Filtered recent records table
======================================== */

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
        ON attendance_events.user_id
        = users.id

    WHERE $where_sql

    ORDER BY
        attendance_events.created_at DESC,
        attendance_events.id DESC

    LIMIT 20
";

$recent_records_result =
    mysqli_query(
        $conn,
        $recent_records_sql
    );

if (!$recent_records_result) {
    die(
        'Recent records query failed: ' .
        mysqli_error($conn)
    );
}

/* ========================================
   Filtered records for maps
======================================== */

$records_sql = "
    SELECT
        users.id AS user_id,
        users.name,
        users.username,

        attendance_events.id
            AS event_id,

        attendance_events.action_type,
        attendance_events.latitude,
        attendance_events.longitude,
        attendance_events.photo_path,
        attendance_events.created_at

    FROM users

    JOIN attendance_events
        ON users.id
        = attendance_events.user_id

    WHERE $where_sql

    ORDER BY
        users.name ASC,
        attendance_events.created_at ASC,
        attendance_events.id ASC
";

$records_result = mysqli_query(
    $conn,
    $records_sql
);

if (!$records_result) {
    die(
        'Map records query failed: ' .
        mysqli_error($conn)
    );
}

$users = [];

while (
    $row = mysqli_fetch_assoc(
        $records_result
    )
) {
    $user_id =
        (int) $row['user_id'];

    if (!isset($users[$user_id])) {
        $users[$user_id] = [
            'id' => $user_id,
            'name' => $row['name'],
            'username' =>
                $row['username'],
            'records' => [],
            'visits' => []
        ];
    }

    $users[$user_id]['records'][] = [
        'id' =>
            (int) $row['event_id'],

        'action_type' =>
            $row['action_type'],

        'latitude' =>
            $row['latitude'],

        'longitude' =>
            $row['longitude'],

        'photo_path' =>
            $row['photo_path'],

        'created_at' =>
            $row['created_at']
    ];
}

/* ========================================
   Pair IN and OUT records
======================================== */

foreach (
    $users as
    $userId => $userData
) {
    $visits = [];
    $currentVisit = null;
    $pairNo = 1;

    foreach (
        $userData['records']
        as $record
    ) {
        if (
            $record['action_type']
            === 'IN'
        ) {
            if (
                $currentVisit !== null
            ) {
                $currentVisit['pair_no']
                    = $pairNo;

                $visits[] =
                    $currentVisit;

                $pairNo++;
            }

            $currentVisit = [
                'pair_no' => $pairNo,
                'in' => $record,
                'out' => null
            ];
        }

        if (
            $record['action_type']
            === 'OUT'
        ) {
            if (
                $currentVisit !== null &&
                $currentVisit['out']
                === null
            ) {
                $currentVisit['out']
                    = $record;

                $currentVisit['pair_no']
                    = $pairNo;

                $visits[] =
                    $currentVisit;

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
        $currentVisit['pair_no']
            = $pairNo;

        $visits[] =
            $currentVisit;
    }

    $users[$userId]['visits']
        = $visits;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width,
        initial-scale=1.0"
    >

    <title>
        FieldTrack Admin Panel
    </title>

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
        <h1>
            FieldTrack Admin Panel
        </h1>

        <p>
            Monitor field officers,
            IN / OUT records,
            photos and locations.
        </p>
    </div>

    <a
        href="logout.php"
        class="logout-btn"
    >
        Logout
    </a>

</header>

<main class="admin-container">

    <section class="summary-grid">

        <div class="summary-card">
            <h3>
                Matching Officers
            </h3>

            <p>
                <?= $matching_officers ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>
                Filtered IN
            </h3>

            <p>
                <?= $filtered_in ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>
                Filtered OUT
            </h3>

            <p>
                <?= $filtered_out ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>
                Filtered Records
            </h3>

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
                Filter attendance by officer,
                date, time, attendance type
                and uploaded photo.
            </p>

        </div>

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
                                (string) $selected_user
                                ===
                                (string) $officer['id']
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h(
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
                            : ''
                        ?>
                    >
                        All Dates
                    </option>

                    <option
                        value="today"
                        <?= $date_range === 'today'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Today
                    </option>

                    <option
                        value="yesterday"
                        <?= $date_range === 'yesterday'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Yesterday
                    </option>

                    <option
                        value="last_7_days"
                        <?= $date_range === 'last_7_days'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Last 7 Days
                    </option>

                    <option
                        value="last_30_days"
                        <?= $date_range === 'last_30_days'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Last 30 Days
                    </option>

                    <option
                        value="this_month"
                        <?= $date_range === 'this_month'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        This Month
                    </option>

                    <option
                        value="custom"
                        <?= $date_range === 'custom'
                            ? 'selected'
                            : ''
                        ?>
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
                            : ''
                        ?>
                    >
                        All Records
                    </option>

                    <option
                        value="IN"
                        <?= $action_type === 'IN'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        IN Only
                    </option>

                    <option
                        value="OUT"
                        <?= $action_type === 'OUT'
                            ? 'selected'
                            : ''
                        ?>
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
                            : ''
                        ?>
                    >
                        All Records
                    </option>

                    <option
                        value="with_photo"
                        <?= $photo_filter
                            === 'with_photo'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        With Photos
                    </option>

                    <option
                        value="without_photo"
                        <?= $photo_filter
                            === 'without_photo'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Without Photos
                    </option>

                </select>

            </div>

            <div class="filter-group">

                <label for="from_date">
                    From Date
                </label>

                <input
                    type="date"
                    name="from_date"
                    id="from_date"
                    value="<?= h($from_date) ?>"
                >

            </div>

            <div class="filter-group">

                <label for="to_date">
                    To Date
                </label>

                <input
                    type="date"
                    name="to_date"
                    id="to_date"
                    value="<?= h($to_date) ?>"
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
                    value="<?= h($from_time) ?>"
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
                    value="<?= h($to_time) ?>"
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

            <h2>
                Recent Attendance Records
            </h2>

            <p>
                Showing up to 20 records
                matching the selected filters.
            </p>

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
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    mysqli_num_rows(
                        $recent_records_result
                    ) > 0
                ): ?>

                    <?php while (
                        $record =
                            mysqli_fetch_assoc(
                                $recent_records_result
                            )
                    ): ?>

                        <tr>

                            <td>
                                <?= h(
                                    $record['name']
                                ) ?>
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
                                    !empty(
                                        $record['photo_path']
                                    )
                                ): ?>

                                    <a
                                        href="<?= h(
                                            $record['photo_path']
                                        ) ?>"
                                        target="_blank"
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

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">
                            No attendance records
                            matched the selected filters.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <section class="admin-section">

        <div class="section-title">

            <h2>
                Officer Location Maps
            </h2>

            <p>
                Only records matching the
                selected filters are shown below.
            </p>

        </div>

        <?php if (
            count($users) > 0
        ): ?>

            <div class="officer-map-grid">

                <?php foreach (
                    $users as $user
                ): ?>

                    <div class="officer-card">

                        <div class="officer-card-header">

                            <div>

                                <h3>
                                    <?= h(
                                        $user['name']
                                    ) ?>
                                </h3>

                                <p>
                                    @<?= h(
                                        $user['username']
                                    ) ?>
                                </p>

                            </div>

                            <span class="visit-count">

                                <?= count(
                                    $user['visits']
                                ) ?>

                                Visit<?= count(
                                    $user['visits']
                                ) === 1
                                    ? ''
                                    : 's'
                                ?>

                            </span>

                        </div>

                        <div
                            id="user-map-<?= (int) $user['id'] ?>"
                            class="user-map"
                        ></div>

                        <div class="visit-list">

                            <?php foreach (
                                $user['visits']
                                as $visit
                            ): ?>

                                <div class="visit-pair-card">

                                    <h4>
                                        Visit
                                        <?= (int) $visit['pair_no'] ?>
                                    </h4>

                                    <div class="visit-details-grid">

                                        <div class="visit-detail-box in-box">

                                            <h5>IN</h5>

                                            <?php if (
                                                !empty(
                                                    $visit['in']
                                                )
                                            ): ?>

                                                <p>
                                                    <strong>
                                                        Time:
                                                    </strong>

                                                    <?= h(
                                                        formatDateTime(
                                                            $visit['in']['created_at']
                                                        )
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>
                                                        Latitude:
                                                    </strong>

                                                    <?= h(
                                                        $visit['in']['latitude']
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>
                                                        Longitude:
                                                    </strong>

                                                    <?= h(
                                                        $visit['in']['longitude']
                                                    ) ?>
                                                </p>

                                                <?php if (
                                                    !empty(
                                                        $visit['in']['photo_path']
                                                    )
                                                ): ?>

                                                    <div class="visit-photo-box">

                                                        <img
                                                            src="<?= h(
                                                                $visit['in']['photo_path']
                                                            ) ?>"
                                                            alt="IN Photo"
                                                        >

                                                        <a
                                                            href="<?= h(
                                                                $visit['in']['photo_path']
                                                            ) ?>"
                                                            target="_blank"
                                                        >
                                                            View Full IN Photo
                                                        </a>

                                                    </div>

                                                <?php else: ?>

                                                    <span>
                                                        No IN photo
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p>
                                                    No IN record
                                                </p>

                                            <?php endif; ?>

                                        </div>

                                        <div class="visit-detail-box out-box">

                                            <h5>OUT</h5>

                                            <?php if (
                                                !empty(
                                                    $visit['out']
                                                )
                                            ): ?>

                                                <p>
                                                    <strong>
                                                        Time:
                                                    </strong>

                                                    <?= h(
                                                        formatDateTime(
                                                            $visit['out']['created_at']
                                                        )
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>
                                                        Latitude:
                                                    </strong>

                                                    <?= h(
                                                        $visit['out']['latitude']
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>
                                                        Longitude:
                                                    </strong>

                                                    <?= h(
                                                        $visit['out']['longitude']
                                                    ) ?>
                                                </p>

                                                <?php if (
                                                    !empty(
                                                        $visit['out']['photo_path']
                                                    )
                                                ): ?>

                                                    <div class="visit-photo-box">

                                                        <img
                                                            src="<?= h(
                                                                $visit['out']['photo_path']
                                                            ) ?>"
                                                            alt="OUT Photo"
                                                        >

                                                        <a
                                                            href="<?= h(
                                                                $visit['out']['photo_path']
                                                            ) ?>"
                                                            target="_blank"
                                                        >
                                                            View Full OUT Photo
                                                        </a>

                                                    </div>

                                                <?php else: ?>

                                                    <span>
                                                        No OUT photo
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p>
                                                    No OUT record yet
                                                </p>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-map-box">
                No map records matched
                the selected filters.
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
        .replaceAll(
            "&",
            "&amp;"
        )
        .replaceAll(
            "<",
            "&lt;"
        )
        .replaceAll(
            ">",
            "&gt;"
        )
        .replaceAll(
            '"',
            "&quot;"
        )
        .replaceAll(
            "'",
            "&#039;"
        );
}

function createPairIcon(
    type,
    color
) {
    return L.divIcon({
        className:
            "admin-custom-marker",

        html: `
            <div
                class="admin-marker-pin"
                style="background:${color}"
            >
                <span>${type}</span>
            </div>
        `,

        iconSize: [46, 46],
        iconAnchor: [23, 46],
        popupAnchor: [0, -42]
    });
}

function buildTooltip(
    userName,
    pairNo,
    type,
    record
) {
    return `
        <strong>
            ${escapeHtml(userName)}
        </strong>
        <br>

        ${escapeHtml(type)}
        - Visit
        ${escapeHtml(pairNo)}
        <br>

        ${escapeHtml(
            record.created_at
        )}
        <br>

        ${escapeHtml(
            record.latitude
        )},
        ${escapeHtml(
            record.longitude
        )}
    `;
}

function buildPopup(
    userName,
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
                href="${escapeHtml(
                    record.photo_path
                )}"
                target="_blank"
            >
                <img
                    src="${escapeHtml(
                        record.photo_path
                    )}"
                    class="map-popup-photo"
                    alt="${escapeHtml(
                        type
                    )} Photo"
                >
            </a>
        `;
    }

    return `
        <div class="map-popup">

            <div
                class="popup-title"
                style="
                    border-left-color:
                    ${color}
                "
            >
                <strong>
                    ${escapeHtml(type)}
                    - Visit
                    ${escapeHtml(pairNo)}
                </strong>

                <span>
                    ${escapeHtml(userName)}
                </span>
            </div>

            <p>
                <b>Date and time:</b>
                ${escapeHtml(
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

        </div>
    `;
}

Object.keys(
    usersMapData
).forEach(userId => {

    const user =
        usersMapData[userId];

    const visits =
        user.visits;

    if (
        !visits ||
        visits.length === 0
    ) {
        return;
    }

    const mapId =
        "user-map-" + userId;

    const mapElement =
        document.getElementById(
            mapId
        );

    if (!mapElement) {
        return;
    }

    const map = L.map(
        mapId,
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

    visits.forEach(
        (visit, index) => {

            const pairColor =
                pairColors[
                    index %
                    pairColors.length
                ];

            const pairPoints = [];

            if (
                visit.in &&
                visit.in.latitude &&
                visit.in.longitude
            ) {
                const inLat =
                    parseFloat(
                        visit.in.latitude
                    );

                const inLng =
                    parseFloat(
                        visit.in.longitude
                    );

                if (
                    !Number.isNaN(inLat) &&
                    !Number.isNaN(inLng)
                ) {
                    const inMarker =
                        L.marker(
                            [inLat, inLng],
                            {
                                icon:
                                    createPairIcon(
                                        "IN",
                                        pairColor
                                    )
                            }
                        ).addTo(map);

                    inMarker.bindTooltip(
                        buildTooltip(
                            user.name,
                            visit.pair_no,
                            "IN",
                            visit.in
                        )
                    );

                    inMarker.bindPopup(
                        buildPopup(
                            user.name,
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
                    parseFloat(
                        visit.out.latitude
                    );

                const outLng =
                    parseFloat(
                        visit.out.longitude
                    );

                if (
                    !Number.isNaN(outLat) &&
                    !Number.isNaN(outLng)
                ) {
                    const outMarker =
                        L.marker(
                            [outLat, outLng],
                            {
                                icon:
                                    createPairIcon(
                                        "OUT",
                                        pairColor
                                    )
                            }
                        ).addTo(map);

                    outMarker.bindTooltip(
                        buildTooltip(
                            user.name,
                            visit.pair_no,
                            "OUT",
                            visit.out
                        )
                    );

                    outMarker.bindPopup(
                        buildPopup(
                            user.name,
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

            if (
                pairPoints.length === 2
            ) {
                L.polyline(
                    pairPoints,
                    {
                        color: pairColor,
                        weight: 5,
                        opacity: 0.9,
                        lineCap: "round"
                    }
                ).addTo(map);
            }
        }
    );

    if (bounds.length === 1) {
        map.setView(
            bounds[0],
            15
        );
    } else if (
        bounds.length > 1
    ) {
        map.fitBounds(
            bounds,
            {
                padding: [50, 50],
                maxZoom: 15
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
});
</script>

</body>
</html>
Save it and open:

http://localhost/FieldTrack/admin_panel.php
This file has been checked for PHP syntax errors. The filters will work, but the filter section will need CSS added to admin_style.css next.


no give me the thigs i should add not thecdoe entire section by section place by place giveme what shoud add

Do these edits inside your current admin_panel.php, one place at a time. Do not replace the whole file. Your uploaded code has a few small errors in the officer array, filter form, missing time conditions, and SQL queries. 


1. Fix the officer array
Near the top, find:

$officer=[];
Replace it with:

$officers = [];
2. Move the admin-login protection
At the top you have:

<?php
session_start();
include "db.php";
Immediately underneath include "db.php";, add:

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}
Farther down your file, find the same admin-login check:

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
Delete that later copy. Keep only the one at the top.

3. Add the missing time filter
Find:

$where_sql = implode(' AND ', $conditions);
Immediately above it, paste:

if (
    $from_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $from_time)
) {
    $safe_from_time = mysqli_real_escape_string(
        $conn,
        $from_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        >= '$safe_from_time'
    ";
}

if (
    $to_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $to_time)
) {
    $safe_to_time = mysqli_real_escape_string(
        $conn,
        $to_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        <= '$safe_to_time'
    ";
}
Then keep:

$where_sql = implode(' AND ', $conditions);
underneath it.

4. Fix only the officer dropdown
Inside your filter form, find this broken part:

<div class="filter-group">
    <label for="user_id">Officer</label>
    
    <select name="user_id" id="user_id">
        <option value="">All Officer</option>

        <?php foreach($officers as $officer) ; ?>

        <option value="<?php (int)$officer['id']?>"
        <?=  (string) $selected_user ===
        (string) $officer['id'] 
        ? 'selected'
        : '' ?>>
        
        <option>
    </select>
</div>
Delete only that block and replace it with:

<div class="filter-group">
    <label for="user_id">Officer</label>

    <select name="user_id" id="user_id">
        <option value="">All Officers</option>

        <?php foreach ($officers as $officer): ?>

            <option
                value="<?= (int) $officer['id'] ?>"
                <?= (
                    (string) $selected_user ===
                    (string) $officer['id']
                ) ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($officer['name']) ?>
            </option>

        <?php endforeach; ?>
    </select>
</div>
5. Add the date-range filter
Still inside:

<form action="admin_panel.php" method="GET" class="admin-filter-form">
Paste this after the officer dropdown block:

<div class="filter-group">
    <label for="date_range">Date Range</label>

    <select name="date_range" id="date_range">
        <option
            value="all"
            <?= $date_range === 'all' ? 'selected' : '' ?>
        >
            All Dates
        </option>

        <option
            value="today"
            <?= $date_range === 'today' ? 'selected' : '' ?>
        >
            Today
        </option>

        <option
            value="yesterday"
            <?= $date_range === 'yesterday' ? 'selected' : '' ?>
        >
            Yesterday
        </option>

        <option
            value="last_7_days"
            <?= $date_range === 'last_7_days' ? 'selected' : '' ?>
        >
            Last 7 Days
        </option>

        <option
            value="last_30_days"
            <?= $date_range === 'last_30_days' ? 'selected' : '' ?>
        >
            Last 30 Days
        </option>

        <option
            value="this_month"
            <?= $date_range === 'this_month' ? 'selected' : '' ?>
        >
            This Month
        </option>

        <option
            value="custom"
            <?= $date_range === 'custom' ? 'selected' : '' ?>
        >
            Custom Date Range
        </option>
    </select>
</div>
6. Add the IN/OUT filter
Paste this directly under the date-range filter:

<div class="filter-group">
    <label for="action_type">Attendance Type</label>

    <select name="action_type" id="action_type">
        <option
            value=""
            <?= $action_type === '' ? 'selected' : '' ?>
        >
            All Records
        </option>

        <option
            value="IN"
            <?= $action_type === 'IN' ? 'selected' : '' ?>
        >
            IN Only
        </option>

        <option
            value="OUT"
            <?= $action_type === 'OUT' ? 'selected' : '' ?>
        >
            OUT Only
        </option>
    </select>
</div>
7. Add the photo filter
Paste this directly under the IN/OUT filter:

<div class="filter-group">
    <label for="photo_filter">Photo</label>

    <select name="photo_filter" id="photo_filter">
        <option
            value=""
            <?= $photo_filter === '' ? 'selected' : '' ?>
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
8. Add the date and time inputs
Paste these directly under the photo filter:

<div class="filter-group">
    <label for="from_date">From Date</label>

    <input
        type="date"
        name="from_date"
        id="from_date"
        value="<?= htmlspecialchars($from_date) ?>"
    >
</div>

<div class="filter-group">
    <label for="to_date">To Date</label>

    <input
        type="date"
        name="to_date"
        id="to_date"
        value="<?= htmlspecialchars($to_date) ?>"
    >
</div>

<div class="filter-group">
    <label for="from_time">From Time</label>

    <input
        type="time"
        name="from_time"
        id="from_time"
        value="<?= htmlspecialchars($from_time) ?>"
    >
</div>

<div class="filter-group">
    <label for="to_time">To Time</label>

    <input
        type="time"
        name="to_time"
        id="to_time"
        value="<?= htmlspecialchars($to_time) ?>"
    >
</div>
9. Add the Apply and Reset buttons
Paste this at the bottom of the form, before </form>:

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
Your filter form should now end like this:

<div class="filter-actions">
    ...
</div>

</form>
10. Make the recent table use the filters
Find this part inside $recent_records_sql:

FROM attendance_events
JOIN users ON attendance_events.user_id = users.id
ORDER BY attendance_events.created_at DESC, attendance_events.id DESC
LIMIT 10
Change it to:

FROM attendance_events
JOIN users ON attendance_events.user_id = users.id
WHERE $where_sql
ORDER BY attendance_events.created_at DESC, attendance_events.id DESC
LIMIT 20
Do not change the selected columns.

11. Make the map records use the filters
Inside $records_sql, find:

WHERE users.role = 'user'
Replace it with:

WHERE $where_sql
So that part becomes:

FROM users
LEFT JOIN attendance_events 
    ON users.id = attendance_events.user_id
WHERE $where_sql
ORDER BY users.name ASC,
         users.id ASC,
         attendance_events.created_at ASC,
         attendance_events.id ASC
After making these edits, save admin_panel.php and test:

http://localhost/FieldTrack/admin_panel.php
Test Officer, IN Only, OUT Only, and With Photos first.



Delete that later copy. Keep only the one at the top.

wht t do i didnt understnd

You only need to remove the duplicate admin-login check.

In your admin_panel.php, you already have this block farther down, immediately after:

$where_sql = implode(' AND ', $conditions);
It currently looks like this:

$where_sql = implode(' AND ', $conditions);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$total_officers = 0;
Delete only this part:

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
After deleting it, that area should look like:

$where_sql = implode(' AND ', $conditions);

$total_officers = 0;
Keep the login check only once, at the very top after:

session_start();
include "db.php";
Like this:

<?php
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
Do not delete $where_sql or $total_officers = 0;. 



Today 10:10 PM
wht should i do next whts the next thing i should do

Next, fix the Officer filter dropdown only. Do not change anything else yet.

Open:

C:\xampp\htdocs\FieldTrack\admin_panel.php
Search for this broken code:

<?php foreach($officers as $officer) ; ?>

<option value="<?php (int)$officer['id']?>"
<?=  (string) $selected_user ===
(string) $officer['id'] 
? 'selected'
: '' ?>>

<option>
Delete that whole part.

Replace it with:

<?php foreach ($officers as $officer): ?>

    <option
        value="<?= (int) $officer['id'] ?>"
        <?= (
            (string) $selected_user ===
            (string) $officer['id']
        ) ? 'selected' : '' ?>
    >
        <?= htmlspecialchars($officer['name']) ?>
    </option>

<?php endforeach; ?>
The complete Officer section should look like this:

<div class="filter-group">
    <label for="user_id">Officer</label>

    <select name="user_id" id="user_id">
        <option value="">All Officers</option>

        <?php foreach ($officers as $officer): ?>

            <option
                value="<?= (int) $officer['id'] ?>"
                <?= (
                    (string) $selected_user ===
                    (string) $officer['id']
                ) ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($officer['name']) ?>
            </option>

        <?php endforeach; ?>
    </select>
</div>
Save the file, refresh the admin page, and check whether the officer names appear in the dropdown. Do not add the other filters until this works.



Pasted code(5).php
File
any errors

Worked for 26s
Yes. There are four important errors. PHP syntax itself is valid, but these mistakes will stop parts of the filters from working.

1. Fix attendance_event table name
Find:

$conditions[]="
TIME(attendance_event.created_at)
>= '$safe_from_time'";
Replace it with:

$conditions[] = "
    TIME(attendance_events.created_at)
    >= '$safe_from_time'
";
You wrote attendance_event, but your table name is attendance_events.

2. Fix the To Time filter
Find this entire block:

if(
    $to_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $to_time)
){
    $safe_to_time = mysqli_real_escape_string(
        $conn,
        $to_time
    );

    $condtions[]="
    TIME(attendance_events.created_at)
    <= '$safe_to_time";

}
Replace it with:

if (
    $to_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $to_time)
) {
    $safe_to_time = mysqli_real_escape_string(
        $conn,
        $to_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        <= '$safe_to_time'
    ";
}
You had two errors:

$condtions
must be:

$conditions
And the SQL value was missing its closing single quote.

3. Fix the officer dropdown closing tag
Find this part:

<?= htmlspecialchars($officer['name']) ?>

<option>
<?php endforeach; ?>
Replace it with:

<?= htmlspecialchars($officer['name']) ?>

</option>
<?php endforeach; ?>
The complete officer dropdown should be:

<div class="filter-group">
    <label for="user_id">Officer</label>

    <select name="user_id" id="user_id">
        <option value="">All Officers</option>

        <?php foreach ($officers as $officer): ?>

            <option
                value="<?= (int) $officer['id'] ?>"
                <?= (
                    (string) $selected_user ===
                    (string) $officer['id']
                ) ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($officer['name']) ?>
            </option>

        <?php endforeach; ?>
    </select>
</div>
4. Move the buttons inside the form
Your code currently closes the form too early:

</div>
</form>

<div class="filter-actions">
This means the Apply Filters button is outside the form, so it may not submit anything.

Find this section:

        </form>

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
Replace it with:

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
The buttons must appear before </form>.

After fixing
Save the file and test in this order:

Officer filter

IN Only

OUT Only

With Photos

From Time and To Time

Reset Filters

Your file has no PHP syntax error, but these four corrections are necessary for the filters to work properly.


give the corrected code

Replace these three parts only in your current admin_panel.php. 


1. Replace both time-filter blocks
Find the code starting with:

if(
    $from_time !== ''
and ending just before:

$where_sql = implode(' AND ', $conditions);
Delete that section and paste:

if (
    $from_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $from_time)
) {
    $safe_from_time = mysqli_real_escape_string(
        $conn,
        $from_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        >= '$safe_from_time'
    ";
}

if (
    $to_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $to_time)
) {
    $safe_to_time = mysqli_real_escape_string(
        $conn,
        $to_time
    );

    $conditions[] = "
        TIME(attendance_events.created_at)
        <= '$safe_to_time'
    ";
}

$where_sql = implode(' AND ', $conditions);
2. Replace the complete Officer filter block
Find:

<div class="filter-group">
    <label for="user_id">Officer</label>
Delete that complete officer <div> and paste:

<div class="filter-group">
    <label for="user_id">Officer</label>

    <select name="user_id" id="user_id">
        <option value="">All Officers</option>

        <?php foreach ($officers as $officer): ?>

            <option
                value="<?= (int) $officer['id'] ?>"
                <?= (
                    (string) $selected_user ===
                    (string) $officer['id']
                ) ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($officer['name']) ?>
            </option>

        <?php endforeach; ?>
    </select>
</div>
3. Correct the form buttons section
At the bottom of your filter form, you currently have:

</form>

<div class="filter-actions">
Delete the current </form> and complete button section.

Replace it with:

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
The final filter section must end like this:

<div class="filter-group">
    <label for="user_id">Officer</label>

    <select name="user_id" id="user_id">
        <option value="">All Officers</option>

        <?php foreach ($officers as $officer): ?>
            <option
                value="<?= (int) $officer['id'] ?>"
                <?= (
                    (string) $selected_user ===
                    (string) $officer['id']
                ) ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($officer['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
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
Save the file and refresh:

http://localhost/FieldTrack/admin_panel.php
The filter button should now submit properly, and the officer and time filters should work.


complete code

Use this complete corrected admin_panel.php:

admin_panel_corrected.php

Do this:

Open:

C:\xampp\htdocs\FieldTrack\admin_panel.php
Press:

Ctrl + A
Delete the old code.

Open the downloaded file and copy all the code into admin_panel.php.

Save and test:

http://localhost/FieldTrack/admin_panel.php
I also checked the file with PHP syntax validation, and it has no syntax errors. 


Library
/
admin_panel_corrected.php


<?php
session_start();
include "db.php";

/* =========================
   Admin Access Protection
========================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}

/* =========================
   Helper Function
========================= */

function formatDateTime($dateTime) {
    if (empty($dateTime)) {
        return "-";
    }

    return date("d/m/Y h:i A", strtotime($dateTime));
}

/* =========================
   Load Officers
========================= */

$officers = [];

$officers_sql = "
    SELECT id, name, username
    FROM users
    WHERE role = 'user'
    ORDER BY name ASC
";

$officers_result = mysqli_query($conn, $officers_sql);

if (!$officers_result) {
    die("Officer query failed: " . mysqli_error($conn));
}

while ($officer = mysqli_fetch_assoc($officers_result)) {
    $officers[] = $officer;
}

/* =========================
   Read Filter Values
========================= */

$selected_user = trim($_GET['user_id'] ?? '');
$date_range = trim($_GET['date_range'] ?? 'all');
$action_type = trim($_GET['action_type'] ?? '');
$photo_filter = trim($_GET['photo_filter'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');
$from_time = trim($_GET['from_time'] ?? '');
$to_time = trim($_GET['to_time'] ?? '');

/* =========================
   Validate Filter Values
========================= */

if ($selected_user !== '' && !ctype_digit($selected_user)) {
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

if (!in_array(
    $photo_filter,
    ['', 'with_photo', 'without_photo'],
    true
)) {
    $photo_filter = '';
}

$is_valid_from_date = (
    $from_date !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)
);

$is_valid_to_date = (
    $to_date !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)
);

$is_valid_from_time = (
    $from_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $from_time)
);

$is_valid_to_time = (
    $to_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $to_time)
);

/* =========================
   Build Filter Conditions
========================= */

$conditions = [
    "users.role = 'user'"
];

if ($selected_user !== '') {
    $selected_user_id = (int) $selected_user;
    $conditions[] = "attendance_events.user_id = $selected_user_id";
}

if ($action_type === 'IN') {
    $conditions[] = "attendance_events.action_type = 'IN'";
}

if ($action_type === 'OUT') {
    $conditions[] = "attendance_events.action_type = 'OUT'";
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

switch ($date_range) {
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
            AND MONTH(attendance_events.created_at) = MONTH(CURDATE())
        ";
        break;

    case 'custom':
        if ($is_valid_from_date) {
            $safe_from_date = mysqli_real_escape_string($conn, $from_date);

            $conditions[] = "
                DATE(attendance_events.created_at)
                >= '$safe_from_date'
            ";
        }

        if ($is_valid_to_date) {
            $safe_to_date = mysqli_real_escape_string($conn, $to_date);

            $conditions[] = "
                DATE(attendance_events.created_at)
                <= '$safe_to_date'
            ";
        }
        break;
}

if ($is_valid_from_time) {
    $safe_from_time = mysqli_real_escape_string($conn, $from_time);

    $conditions[] = "
        TIME(attendance_events.created_at)
        >= '$safe_from_time'
    ";
}

if ($is_valid_to_time) {
    $safe_to_time = mysqli_real_escape_string($conn, $to_time);

    $conditions[] = "
        TIME(attendance_events.created_at)
        <= '$safe_to_time'
    ";
}

$where_sql = implode(' AND ', $conditions);

/* =========================
   Filtered Dashboard Summary
========================= */

$matching_officers = 0;
$filtered_in = 0;
$filtered_out = 0;
$filtered_records = 0;

$summary_sql = "
    SELECT
        COUNT(DISTINCT attendance_events.user_id) AS matching_officers,
        COALESCE(
            SUM(attendance_events.action_type = 'IN'),
            0
        ) AS filtered_in,
        COALESCE(
            SUM(attendance_events.action_type = 'OUT'),
            0
        ) AS filtered_out,
        COUNT(attendance_events.id) AS filtered_records
    FROM attendance_events
    JOIN users
        ON attendance_events.user_id = users.id
    WHERE $where_sql
";

$summary_result = mysqli_query($conn, $summary_sql);

if (!$summary_result) {
    die("Summary query failed: " . mysqli_error($conn));
}

$summary_row = mysqli_fetch_assoc($summary_result);

$matching_officers = (int) $summary_row['matching_officers'];
$filtered_in = (int) $summary_row['filtered_in'];
$filtered_out = (int) $summary_row['filtered_out'];
$filtered_records = (int) $summary_row['filtered_records'];

/* =========================
   Filtered Recent Records
========================= */

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

/* =========================
   Filtered Map Records
========================= */

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
    die("Map records query failed: " . mysqli_error($conn));
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
        'created_at' => $row['created_at']
    ];
}

/* =========================
   Pair IN and OUT Records
========================= */

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

    <link rel="stylesheet" href="admin_style.css">
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
                Filter attendance by officer, date, time,
                attendance type and uploaded photo.
            </p>
        </div>

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

            <div class="filter-group">
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

            <div class="filter-group">
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
            <h2>Recent Attendance Records</h2>

            <p>
                Showing up to 20 records matching the
                selected filters.
            </p>
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
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    mysqli_num_rows($recent_records_result) > 0
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
                                        htmlspecialchars(
                                            $record['action_type']
                                        )
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
                                    !empty($record['photo_path'])
                                ): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $record['photo_path']
                                        ) ?>"
                                        target="_blank"
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
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">
                            No attendance records matched
                            the selected filters.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <section class="admin-section">

        <div class="section-title">
            <h2>Officer Location Maps</h2>

            <p>
                Only records matching the selected filters
                are shown below.
            </p>
        </div>

        <?php if (count($users) > 0): ?>

            <div class="officer-map-grid">

                <?php foreach ($users as $user): ?>

                    <div class="officer-card">

                        <div class="officer-card-header">

                            <div>
                                <h3>
                                    <?= htmlspecialchars(
                                        $user['name']
                                    ) ?>
                                </h3>

                                <p>
                                    @<?= htmlspecialchars(
                                        $user['username']
                                    ) ?>
                                </p>
                            </div>

                            <span class="visit-count">
                                <?= count($user['visits']) ?>
                                Visit<?= count(
                                    $user['visits']
                                ) === 1 ? '' : 's' ?>
                            </span>

                        </div>

                        <div
                            id="user-map-<?= (int) $user['id'] ?>"
                            class="user-map"
                        ></div>

                        <div class="visit-list">

                            <?php foreach (
                                $user['visits'] as $visit
                            ): ?>

                                <div class="visit-pair-card">

                                    <h4>
                                        Visit
                                        <?= (int) $visit['pair_no'] ?>
                                    </h4>

                                    <div class="visit-details-grid">

                                        <div
                                            class="visit-detail-box in-box"
                                        >
                                            <h5>IN</h5>

                                            <?php if (
                                                !empty($visit['in'])
                                            ): ?>

                                                <p>
                                                    <strong>Time:</strong>
                                                    <?= htmlspecialchars(
                                                        formatDateTime(
                                                            $visit['in']['created_at']
                                                        )
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Latitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['in']['latitude']
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Longitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['in']['longitude']
                                                    ) ?>
                                                </p>

                                                <?php if (
                                                    !empty(
                                                        $visit['in']['photo_path']
                                                    )
                                                ): ?>

                                                    <div
                                                        class="visit-photo-box"
                                                    >
                                                        <img
                                                            src="<?= htmlspecialchars(
                                                                $visit['in']['photo_path']
                                                            ) ?>"
                                                            alt="IN Photo"
                                                        >

                                                        <a
                                                            href="<?= htmlspecialchars(
                                                                $visit['in']['photo_path']
                                                            ) ?>"
                                                            target="_blank"
                                                        >
                                                            View Full IN Photo
                                                        </a>
                                                    </div>

                                                <?php else: ?>

                                                    <span>
                                                        No IN photo
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p>No IN record</p>

                                            <?php endif; ?>
                                        </div>

                                        <div
                                            class="visit-detail-box out-box"
                                        >
                                            <h5>OUT</h5>

                                            <?php if (
                                                !empty($visit['out'])
                                            ): ?>

                                                <p>
                                                    <strong>Time:</strong>
                                                    <?= htmlspecialchars(
                                                        formatDateTime(
                                                            $visit['out']['created_at']
                                                        )
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Latitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['out']['latitude']
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Longitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['out']['longitude']
                                                    ) ?>
                                                </p>

                                                <?php if (
                                                    !empty(
                                                        $visit['out']['photo_path']
                                                    )
                                                ): ?>

                                                    <div
                                                        class="visit-photo-box"
                                                    >
                                                        <img
                                                            src="<?= htmlspecialchars(
                                                                $visit['out']['photo_path']
                                                            ) ?>"
                                                            alt="OUT Photo"
                                                        >

                                                        <a
                                                            href="<?= htmlspecialchars(
                                                                $visit['out']['photo_path']
                                                            ) ?>"
                                                            target="_blank"
                                                        >
                                                            View Full OUT Photo
                                                        </a>
                                                    </div>

                                                <?php else: ?>

                                                    <span>
                                                        No OUT photo
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p>No OUT record yet</p>

                                            <?php endif; ?>
                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

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
    if (value === null || value === undefined) {
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
                <span>${type}</span>
            </div>
        `,

        iconSize: [46, 46],
        iconAnchor: [23, 46],
        popupAnchor: [0, -42]
    });
}

function buildTooltip(
    userName,
    pairNo,
    type,
    record
) {
    return `
        <strong>${escapeHtml(userName)}</strong><br>
        ${escapeHtml(type)} - Visit
        ${escapeHtml(pairNo)}<br>
        ${escapeHtml(record.created_at)}<br>
        ${escapeHtml(record.latitude)},
        ${escapeHtml(record.longitude)}
    `;
}

function buildPopup(
    userName,
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
                    ${escapeHtml(type)} -
                    Visit ${escapeHtml(pairNo)}
                </strong>

                <span>${escapeHtml(userName)}</span>
            </div>

            <p>
                <b>Date and time:</b>
                ${escapeHtml(record.created_at)}
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

        </div>
    `;
}

Object.keys(usersMapData).forEach(userId => {
    const user = usersMapData[userId];
    const visits = user.visits;

    if (!visits || visits.length === 0) {
        return;
    }

    const mapId = "user-map-" + userId;
    const mapElement = document.getElementById(mapId);

    if (!mapElement) {
        return;
    }

    const map = L.map(mapId, {
        scrollWheelZoom: true
    });

    L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }
    ).addTo(map);

    const bounds = [];

    visits.forEach((visit, index) => {
        const pairColor =
            pairColors[index % pairColors.length];

        const pairPoints = [];

        if (
            visit.in &&
            visit.in.latitude &&
            visit.in.longitude
        ) {
            const inLat = parseFloat(visit.in.latitude);
            const inLng = parseFloat(visit.in.longitude);

            if (!isNaN(inLat) && !isNaN(inLng)) {
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
                        visit.pair_no,
                        "IN",
                        visit.in
                    )
                );

                inMarker.bindPopup(
                    buildPopup(
                        user.name,
                        visit.pair_no,
                        "IN",
                        visit.in,
                        pairColor
                    )
                );

                pairPoints.push([inLat, inLng]);
                bounds.push([inLat, inLng]);
            }
        }

        if (
            visit.out &&
            visit.out.latitude &&
            visit.out.longitude
        ) {
            const outLat = parseFloat(
                visit.out.latitude
            );

            const outLng = parseFloat(
                visit.out.longitude
            );

            if (!isNaN(outLat) && !isNaN(outLng)) {
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
                        visit.pair_no,
                        "OUT",
                        visit.out
                    )
                );

                outMarker.bindPopup(
                    buildPopup(
                        user.name,
                        visit.pair_no,
                        "OUT",
                        visit.out,
                        pairColor
                    )
                );

                pairPoints.push([outLat, outLng]);
                bounds.push([outLat, outLng]);
            }
        }

        if (pairPoints.length === 2) {
            L.polyline(pairPoints, {
                color: pairColor,
                weight: 5,
                opacity: 0.9,
                lineCap: "round"
            }).addTo(map);
        }
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 15);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, {
            padding: [50, 50],
            maxZoom: 15
        });
    } else {
        map.setView([7.8731, 80.7718], 7);
    }

    setTimeout(() => {
        map.invalidateSize();
    }, 300);
});
</script>

</body>
</html>
Library
/
admin_panel_corrected.php


<?php
session_start();
include "db.php";

/* =========================
   Admin Access Protection
========================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}

/* =========================
   Helper Function
========================= */

function formatDateTime($dateTime) {
    if (empty($dateTime)) {
        return "-";
    }

    return date("d/m/Y h:i A", strtotime($dateTime));
}

/* =========================
   Load Officers
========================= */

$officers = [];

$officers_sql = "
    SELECT id, name, username
    FROM users
    WHERE role = 'user'
    ORDER BY name ASC
";

$officers_result = mysqli_query($conn, $officers_sql);

if (!$officers_result) {
    die("Officer query failed: " . mysqli_error($conn));
}

while ($officer = mysqli_fetch_assoc($officers_result)) {
    $officers[] = $officer;
}

/* =========================
   Read Filter Values
========================= */

$selected_user = trim($_GET['user_id'] ?? '');
$date_range = trim($_GET['date_range'] ?? 'all');
$action_type = trim($_GET['action_type'] ?? '');
$photo_filter = trim($_GET['photo_filter'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');
$from_time = trim($_GET['from_time'] ?? '');
$to_time = trim($_GET['to_time'] ?? '');

/* =========================
   Validate Filter Values
========================= */

if ($selected_user !== '' && !ctype_digit($selected_user)) {
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

if (!in_array(
    $photo_filter,
    ['', 'with_photo', 'without_photo'],
    true
)) {
    $photo_filter = '';
}

$is_valid_from_date = (
    $from_date !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)
);

$is_valid_to_date = (
    $to_date !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)
);

$is_valid_from_time = (
    $from_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $from_time)
);

$is_valid_to_time = (
    $to_time !== '' &&
    preg_match('/^\d{2}:\d{2}$/', $to_time)
);

/* =========================
   Build Filter Conditions
========================= */

$conditions = [
    "users.role = 'user'"
];

if ($selected_user !== '') {
    $selected_user_id = (int) $selected_user;
    $conditions[] = "attendance_events.user_id = $selected_user_id";
}

if ($action_type === 'IN') {
    $conditions[] = "attendance_events.action_type = 'IN'";
}

if ($action_type === 'OUT') {
    $conditions[] = "attendance_events.action_type = 'OUT'";
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

switch ($date_range) {
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
            AND MONTH(attendance_events.created_at) = MONTH(CURDATE())
        ";
        break;

    case 'custom':
        if ($is_valid_from_date) {
            $safe_from_date = mysqli_real_escape_string($conn, $from_date);

            $conditions[] = "
                DATE(attendance_events.created_at)
                >= '$safe_from_date'
            ";
        }

        if ($is_valid_to_date) {
            $safe_to_date = mysqli_real_escape_string($conn, $to_date);

            $conditions[] = "
                DATE(attendance_events.created_at)
                <= '$safe_to_date'
            ";
        }
        break;
}

if ($is_valid_from_time) {
    $safe_from_time = mysqli_real_escape_string($conn, $from_time);

    $conditions[] = "
        TIME(attendance_events.created_at)
        >= '$safe_from_time'
    ";
}

if ($is_valid_to_time) {
    $safe_to_time = mysqli_real_escape_string($conn, $to_time);

    $conditions[] = "
        TIME(attendance_events.created_at)
        <= '$safe_to_time'
    ";
}

$where_sql = implode(' AND ', $conditions);

/* =========================
   Filtered Dashboard Summary
========================= */

$matching_officers = 0;
$filtered_in = 0;
$filtered_out = 0;
$filtered_records = 0;

$summary_sql = "
    SELECT
        COUNT(DISTINCT attendance_events.user_id) AS matching_officers,
        COALESCE(
            SUM(attendance_events.action_type = 'IN'),
            0
        ) AS filtered_in,
        COALESCE(
            SUM(attendance_events.action_type = 'OUT'),
            0
        ) AS filtered_out,
        COUNT(attendance_events.id) AS filtered_records
    FROM attendance_events
    JOIN users
        ON attendance_events.user_id = users.id
    WHERE $where_sql
";

$summary_result = mysqli_query($conn, $summary_sql);

if (!$summary_result) {
    die("Summary query failed: " . mysqli_error($conn));
}

$summary_row = mysqli_fetch_assoc($summary_result);

$matching_officers = (int) $summary_row['matching_officers'];
$filtered_in = (int) $summary_row['filtered_in'];
$filtered_out = (int) $summary_row['filtered_out'];
$filtered_records = (int) $summary_row['filtered_records'];

/* =========================
   Filtered Recent Records
========================= */

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

/* =========================
   Filtered Map Records
========================= */

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
    die("Map records query failed: " . mysqli_error($conn));
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
        'created_at' => $row['created_at']
    ];
}

/* =========================
   Pair IN and OUT Records
========================= */

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

    <link rel="stylesheet" href="admin_style.css">
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
                Filter attendance by officer, date, time,
                attendance type and uploaded photo.
            </p>
        </div>

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

            <div class="filter-group">
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

            <div class="filter-group">
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
            <h2>Recent Attendance Records</h2>

            <p>
                Showing up to 20 records matching the
                selected filters.
            </p>
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
                    </tr>
                </thead>

                <tbody>

                <?php if (
                    mysqli_num_rows($recent_records_result) > 0
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
                                        htmlspecialchars(
                                            $record['action_type']
                                        )
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
                                    !empty($record['photo_path'])
                                ): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $record['photo_path']
                                        ) ?>"
                                        target="_blank"
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
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6">
                            No attendance records matched
                            the selected filters.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <section class="admin-section">

        <div class="section-title">
            <h2>Officer Location Maps</h2>

            <p>
                Only records matching the selected filters
                are shown below.
            </p>
        </div>

        <?php if (count($users) > 0): ?>

            <div class="officer-map-grid">

                <?php foreach ($users as $user): ?>

                    <div class="officer-card">

                        <div class="officer-card-header">

                            <div>
                                <h3>
                                    <?= htmlspecialchars(
                                        $user['name']
                                    ) ?>
                                </h3>

                                <p>
                                    @<?= htmlspecialchars(
                                        $user['username']
                                    ) ?>
                                </p>
                            </div>

                            <span class="visit-count">
                                <?= count($user['visits']) ?>
                                Visit<?= count(
                                    $user['visits']
                                ) === 1 ? '' : 's' ?>
                            </span>

                        </div>

                        <div
                            id="user-map-<?= (int) $user['id'] ?>"
                            class="user-map"
                        ></div>

                        <div class="visit-list">

                            <?php foreach (
                                $user['visits'] as $visit
                            ): ?>

                                <div class="visit-pair-card">

                                    <h4>
                                        Visit
                                        <?= (int) $visit['pair_no'] ?>
                                    </h4>

                                    <div class="visit-details-grid">

                                        <div
                                            class="visit-detail-box in-box"
                                        >
                                            <h5>IN</h5>

                                            <?php if (
                                                !empty($visit['in'])
                                            ): ?>

                                                <p>
                                                    <strong>Time:</strong>
                                                    <?= htmlspecialchars(
                                                        formatDateTime(
                                                            $visit['in']['created_at']
                                                        )
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Latitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['in']['latitude']
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Longitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['in']['longitude']
                                                    ) ?>
                                                </p>

                                                <?php if (
                                                    !empty(
                                                        $visit['in']['photo_path']
                                                    )
                                                ): ?>

                                                    <div
                                                        class="visit-photo-box"
                                                    >
                                                        <img
                                                            src="<?= htmlspecialchars(
                                                                $visit['in']['photo_path']
                                                            ) ?>"
                                                            alt="IN Photo"
                                                        >

                                                        <a
                                                            href="<?= htmlspecialchars(
                                                                $visit['in']['photo_path']
                                                            ) ?>"
                                                            target="_blank"
                                                        >
                                                            View Full IN Photo
                                                        </a>
                                                    </div>

                                                <?php else: ?>

                                                    <span>
                                                        No IN photo
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p>No IN record</p>

                                            <?php endif; ?>
                                        </div>

                                        <div
                                            class="visit-detail-box out-box"
                                        >
                                            <h5>OUT</h5>

                                            <?php if (
                                                !empty($visit['out'])
                                            ): ?>

                                                <p>
                                                    <strong>Time:</strong>
                                                    <?= htmlspecialchars(
                                                        formatDateTime(
                                                            $visit['out']['created_at']
                                                        )
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Latitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['out']['latitude']
                                                    ) ?>
                                                </p>

                                                <p>
                                                    <strong>Longitude:</strong>
                                                    <?= htmlspecialchars(
                                                        $visit['out']['longitude']
                                                    ) ?>
                                                </p>

                                                <?php if (
                                                    !empty(
                                                        $visit['out']['photo_path']
                                                    )
                                                ): ?>

                                                    <div
                                                        class="visit-photo-box"
                                                    >
                                                        <img
                                                            src="<?= htmlspecialchars(
                                                                $visit['out']['photo_path']
                                                            ) ?>"
                                                            alt="OUT Photo"
                                                        >

                                                        <a
                                                            href="<?= htmlspecialchars(
                                                                $visit['out']['photo_path']
                                                            ) ?>"
                                                            target="_blank"
                                                        >
                                                            View Full OUT Photo
                                                        </a>
                                                    </div>

                                                <?php else: ?>

                                                    <span>
                                                        No OUT photo
                                                    </span>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <p>No OUT record yet</p>

                                            <?php endif; ?>
                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

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
    if (value === null || value === undefined) {
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
                <span>${type}</span>
            </div>
        `,

        iconSize: [46, 46],
        iconAnchor: [23, 46],
        popupAnchor: [0, -42]
    });
}

function buildTooltip(
    userName,
    pairNo,
    type,
    record
) {
    return `
        <strong>${escapeHtml(userName)}</strong><br>
        ${escapeHtml(type)} - Visit
        ${escapeHtml(pairNo)}<br>
        ${escapeHtml(record.created_at)}<br>
        ${escapeHtml(record.latitude)},
        ${escapeHtml(record.longitude)}
    `;
}

function buildPopup(
    userName,
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
                    ${escapeHtml(type)} -
                    Visit ${escapeHtml(pairNo)}
                </strong>

                <span>${escapeHtml(userName)}</span>
            </div>

            <p>
                <b>Date and time:</b>
                ${escapeHtml(record.created_at)}
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

        </div>
    `;
}

Object.keys(usersMapData).forEach(userId => {
    const user = usersMapData[userId];
    const visits = user.visits;

    if (!visits || visits.length === 0) {
        return;
    }

    const mapId = "user-map-" + userId;
    const mapElement = document.getElementById(mapId);

    if (!mapElement) {
        return;
    }

    const map = L.map(mapId, {
        scrollWheelZoom: true
    });

    L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            maxZoom: 19,
            attribution: "&copy; OpenStreetMap contributors"
        }
    ).addTo(map);

    const bounds = [];

    visits.forEach((visit, index) => {
        const pairColor =
            pairColors[index % pairColors.length];

        const pairPoints = [];

        if (
            visit.in &&
            visit.in.latitude &&
            visit.in.longitude
        ) {
            const inLat = parseFloat(visit.in.latitude);
            const inLng = parseFloat(visit.in.longitude);

            if (!isNaN(inLat) && !isNaN(inLng)) {
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
                        visit.pair_no,
                        "IN",
                        visit.in
                    )
                );

                inMarker.bindPopup(
                    buildPopup(
                        user.name,
                        visit.pair_no,
                        "IN",
                        visit.in,
                        pairColor
                    )
                );

                pairPoints.push([inLat, inLng]);
                bounds.push([inLat, inLng]);
            }
        }

        if (
            visit.out &&
            visit.out.latitude &&
            visit.out.longitude
        ) {
            const outLat = parseFloat(
                visit.out.latitude
            );

            const outLng = parseFloat(
                visit.out.longitude
            );

            if (!isNaN(outLat) && !isNaN(outLng)) {
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
                        visit.pair_no,
                        "OUT",
                        visit.out
                    )
                );

                outMarker.bindPopup(
                    buildPopup(
                        user.name,
                        visit.pair_no,
                        "OUT",
                        visit.out,
                        pairColor
                    )
                );

                pairPoints.push([outLat, outLng]);
                bounds.push([outLat, outLng]);
            }
        }

        if (pairPoints.length === 2) {
            L.polyline(pairPoints, {
                color: pairColor,
                weight: 5,
                opacity: 0.9,
                lineCap: "round"
            }).addTo(map);
        }
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 15);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, {
            padding: [50, 50],
            maxZoom: 15
        });
    } else {
        map.setView([7.8731, 80.7718], 7);
    }

    setTimeout(() => {
        map.invalidateSize();
    }, 300);
});
</script>

</body>
</html>