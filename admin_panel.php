<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin']);

/* =========================================================
   FILTERS
   ========================================================= */

$userFilter = filter_input(
    INPUT_GET,
    'user_id',
    FILTER_VALIDATE_INT
);

$userFilter =
    ($userFilter === false || $userFilter === null)
        ? 0
        : (int) $userFilter;


$actionFilter =
    strtoupper(
        trim(
            (string) ($_GET['action_type'] ?? '')
        )
    );

if (
    !in_array(
        $actionFilter,
        ['', 'IN', 'OUT'],
        true
    )
) {
    $actionFilter = '';
}


$dateRange =
    trim(
        (string) ($_GET['date_range'] ?? 'all')
    );

$allowedRanges = [
    'all',
    'today',
    'yesterday',
    'last_7_days',
    'this_month',
    'custom'
];

if (
    !in_array(
        $dateRange,
        $allowedRanges,
        true
    )
) {
    $dateRange = 'all';
}


$fromDate = '';
$toDate = '';


if ($dateRange === 'today') {

    $fromDate =
        $toDate =
        date('Y-m-d');

} elseif ($dateRange === 'yesterday') {

    $fromDate =
        $toDate =
        date(
            'Y-m-d',
            strtotime('-1 day')
        );

} elseif ($dateRange === 'last_7_days') {

    $fromDate =
        date(
            'Y-m-d',
            strtotime('-6 days')
        );

    $toDate =
        date('Y-m-d');

} elseif ($dateRange === 'this_month') {

    $fromDate =
        date('Y-m-01');

    $toDate =
        date('Y-m-d');

} elseif ($dateRange === 'custom') {

    $fromDate =
        trim(
            (string) ($_GET['from_date'] ?? '')
        );

    $toDate =
        trim(
            (string) ($_GET['to_date'] ?? '')
        );


    $validDate =
        static function (
            string $value
        ): bool {

            $date =
                DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $value
                );

            return (
                $date !== false &&
                $date->format('Y-m-d') === $value
            );
        };


    if (
        !$validDate($fromDate) ||
        !$validDate($toDate) ||
        $fromDate > $toDate
    ) {

        $fromDate = '';
        $toDate = '';
    }
}


/* =========================================================
   DASHBOARD COUNTS
   ========================================================= */

$totalUsers =
    (int) $conn
        ->query(
            "SELECT COUNT(*) AS total
             FROM users"
        )
        ->fetch_assoc()['total'];


$totalOfficers =
    (int) $conn
        ->query(
            "SELECT
                COUNT(DISTINCT ur.user_id) AS total
             FROM user_roles ur
             INNER JOIN roles r
                ON r.id = ur.role_id
             WHERE r.role_name = 'field_officer'"
        )
        ->fetch_assoc()['total'];


$todayIn =
    (int) $conn
        ->query(
            "SELECT COUNT(*) AS total
             FROM attendance_events
             WHERE action_type = 'IN'
             AND DATE(created_at) = CURDATE()"
        )
        ->fetch_assoc()['total'];


$todayOut =
    (int) $conn
        ->query(
            "SELECT COUNT(*) AS total
             FROM attendance_events
             WHERE action_type = 'OUT'
             AND DATE(created_at) = CURDATE()"
        )
        ->fetch_assoc()['total'];


/* =========================================================
   FIELD OFFICERS
   ========================================================= */

$officers = [];

$officerResult =
    $conn->query(
        "SELECT
            u.id,
            u.name,
            u.username
         FROM users u
         INNER JOIN user_roles ur
            ON ur.user_id = u.id
         INNER JOIN roles r
            ON r.id = ur.role_id
         WHERE r.role_name = 'field_officer'
         ORDER BY u.name"
    );


while (
    $row =
    $officerResult->fetch_assoc()
) {
    $officers[] = $row;
}


/* =========================================================
   ATTENDANCE RECORDS
   NO PHOTO FIELD
   ========================================================= */

$stmt =
    $conn->prepare(
        "SELECT
            ae.id,
            ae.user_id,
            ae.action_type,
            ae.latitude,
            ae.longitude,
            ae.is_locked,
            ae.created_at,
            u.name,
            u.username

         FROM attendance_events ae

         INNER JOIN users u
            ON u.id = ae.user_id

         WHERE
            (? = 0 OR ae.user_id = ?)

         AND
            (? = '' OR ae.action_type = ?)

         AND
            (? = '' OR DATE(ae.created_at) >= ?)

         AND
            (? = '' OR DATE(ae.created_at) <= ?)

         ORDER BY
            ae.created_at DESC,
            ae.id DESC

         LIMIT 300"
    );


$stmt->bind_param(
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


$stmt->execute();

$result =
    $stmt->get_result();


$records = [];

$mapRecords = [];


while (
    $row =
    $result->fetch_assoc()
) {

    $records[] = $row;


    $mapRecords[] = [

        'id' =>
            (int) $row['id'],

        'name' =>
            (string) $row['name'],

        'username' =>
            (string) $row['username'],

        'action_type' =>
            (string) $row['action_type'],

        'latitude' =>
            (float) $row['latitude'],

        'longitude' =>
            (float) $row['longitude'],

        'created_at' =>
            date(
                'd/m/Y h:i A',
                strtotime(
                    (string) $row['created_at']
                )
            )

    ];
}


$stmt->close();

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
        FieldTrack System Administrator
    </title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="<?= h(
            appUrl('admin_style.css')
        ) ?>"
    >

</head>

<body>


<header class="admin-header">

    <div>

        <h1>
            FieldTrack
        </h1>

        <p>
            System Administrator Dashboard
        </p>

    </div>


    <nav class="admin-nav">

        <a href="<?= h(
            appUrl('manage_users.php')
        ) ?>">
            Users
        </a>

        <a href="<?= h(
            appUrl('manage_roles.php')
        ) ?>">
            Roles
        </a>

        <a href="<?= h(
            appUrl('manage_assignments.php')
        ) ?>">
            Assignments
        </a>

        <a href="<?= h(
            appUrl('audit_logs.php')
        ) ?>">
            Audit Logs
        </a>

        <a
            class="logout"
            href="<?= h(
                appUrl('logout.php')
            ) ?>"
        >
            Logout
        </a>

    </nav>

</header>


<main class="admin-container">


<section class="summary-grid">

    <div class="summary-card">

        <h3>
            Total Users
        </h3>

        <p>
            <?= $totalUsers ?>
        </p>

    </div>


    <div class="summary-card">

        <h3>
            Field Officers
        </h3>

        <p>
            <?= $totalOfficers ?>
        </p>

    </div>


    <div class="summary-card">

        <h3>
            Today IN
        </h3>

        <p>
            <?= $todayIn ?>
        </p>

    </div>


    <div class="summary-card">

        <h3>
            Today OUT
        </h3>

        <p>
            <?= $todayOut ?>
        </p>

    </div>

</section>


<!-- =====================================================
     FILTERS
     ===================================================== -->

<section class="admin-section">

<h2>
    Filter Attendance
</h2>


<form
    class="filter-form"
    method="GET"
    action="<?= h(
        appUrl('admin_panel.php')
    ) ?>"
>


<div>

<label for="user_id">
    Officer
</label>


<select
    id="user_id"
    name="user_id"
>


<option value="0">

All Officers

</option>


<?php foreach (
    $officers as $officer
): ?>


<option

    value="<?= (int)
        $officer['id'] ?>"

    <?= $userFilter ===
        (int) $officer['id']
        ? 'selected'
        : '' ?>

>

<?= h(
    $officer['name']
) ?>

(@<?= h(
    $officer['username']
) ?>)

</option>


<?php endforeach; ?>


</select>

</div>


<div>

<label for="date_range">

Date Range

</label>


<select
    id="date_range"
    name="date_range"
>


<?php foreach (
    [

        'all' =>
            'All',

        'today' =>
            'Today',

        'yesterday' =>
            'Yesterday',

        'last_7_days' =>
            'Last 7 Days',

        'this_month' =>
            'This Month',

        'custom' =>
            'Custom'

    ]
    as
    $value =>
    $label
): ?>


<option

value="<?= h(
    $value
) ?>"

<?= $dateRange ===
    $value
    ? 'selected'
    : '' ?>

>

<?= h(
    $label
) ?>

</option>


<?php endforeach; ?>


</select>

</div>


<div>

<label for="from_date">

From Date

</label>

<input

    id="from_date"

    name="from_date"

    type="date"

    value="<?= h(
        $fromDate
    ) ?>"

>

</div>


<div>

<label for="to_date">

To Date

</label>

<input

    id="to_date"

    name="to_date"

    type="date"

    value="<?= h(
        $toDate
    ) ?>"

>

</div>


<div>

<label for="action_type">

Action

</label>

<select

    id="action_type"

    name="action_type"

>

<option value="">

IN + OUT

</option>

<option
    value="IN"
    <?= $actionFilter === 'IN'
        ? 'selected'
        : '' ?>
>
    IN
</option>

<option
    value="OUT"
    <?= $actionFilter === 'OUT'
        ? 'selected'
        : '' ?>
>
    OUT
</option>

</select>

</div>


<div>

<button type="submit">

Apply Filter

</button>

</div>


</form>

</section>


<!-- =====================================================
     MAP
     ===================================================== -->

<section class="admin-section">

<h2>
    Attendance Map
</h2>


<?php if (
    count(
        $mapRecords
    ) === 0
): ?>


<p>

No attendance records match the current filters.

</p>


<?php else: ?>


<div id="attendanceMap"></div>


<?php endif; ?>


</section>


<!-- =====================================================
     ATTENDANCE TABLE
     ===================================================== -->

<section class="admin-section">

<h2>
    Attendance Records
</h2>


<div class="table-wrap">

<table>


<thead>

<tr>

<th>
Officer
</th>

<th>
Action
</th>

<th>
Date / Time
</th>

<th>
Latitude
</th>

<th>
Longitude
</th>

<th>
Locked
</th>

<th>
Details
</th>

</tr>

</thead>


<tbody>


<?php if (
    count($records) === 0
): ?>


<tr>

<td colspan="7">

No matching records.

</td>

</tr>


<?php endif; ?>



<?php foreach (
    $records as $record
): ?>


<tr>


<td>

<?= h(
    $record['name']
) ?>

(@<?= h(
    $record['username']
) ?>)

</td>


<td
class="<?=

    $record['action_type'] === 'IN'
        ? 'status-in'
        : 'status-out'

?>"
>

<?= h(
    $record['action_type']
) ?>

</td>


<td>

<?= h(
    formatDateTimeValue(
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

<?= (int)
    $record['is_locked']
    === 1
        ? 'Yes'
        : 'No'
?>

</td>


<td>

<a
href="<?= h(
    appUrl(
        'attendance_details.php?id=' .
        (int) $record['id']
    )
) ?>"
>

View Details

</a>

</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>

</div>

</section>


</main>


<script
src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
</script>


<script>

const records =

<?= json_encode(

    $mapRecords,

    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT

) ?>;


if (
    records.length > 0
) {


    const map =
        L.map(
            'attendanceMap'
        );


    L.tileLayer(

        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

        {

            maxZoom: 19,

            attribution:
                '&copy; OpenStreetMap contributors'

        }

    ).addTo(
        map
    );


    const bounds = [];


    records.forEach(

        function (
            record
        ) {


            const point = [

                Number(
                    record.latitude
                ),

                Number(
                    record.longitude
                )

            ];


            bounds.push(
                point
            );


            L.marker(
                point
            )
            .addTo(
                map
            )
            .bindPopup(

                '<strong>' +

                record.name +

                '</strong><br>' +

                '@' +

                record.username +

                '<br>' +

                record.action_type +

                '<br>' +

                record.created_at +

                '<br>' +

                '<a href="<?= h(
                    appUrl(
                        'attendance_details.php?id='
                    )
                ) ?>' +

                record.id +

                '">View Details</a>'

            );


        }

    );


    if (
        bounds.length === 1
    ) {


        map.setView(

            bounds[0],

            16

        );


    } else {


        map.fitBounds(

            bounds,

            {

                padding:
                    [35, 35]

            }

        );


    }


}

</script>


</body>

</html>