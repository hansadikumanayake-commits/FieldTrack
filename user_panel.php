<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['field_officer']);

$userId = currentUserId();
$name = currentDisplayName();
$username = currentUsername();

/* --------------------------------
   Get latest attendance action
-------------------------------- */

$lastStmt = $conn->prepare(
    "SELECT action_type
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);

$lastStmt->bind_param('i', $userId);
$lastStmt->execute();

$lastRow = $lastStmt
    ->get_result()
    ->fetch_assoc();

$lastStmt->close();

$lastAction = $lastRow['action_type'] ?? null;

$nextAction = $lastAction === 'IN'
    ? 'OUT'
    : 'IN';


/* --------------------------------
   Previous attendance records
-------------------------------- */

$recordsStmt = $conn->prepare(
    "SELECT
        id,
        action_type,
        latitude,
        longitude,
        is_locked,
        created_at
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 60"
);

$recordsStmt->bind_param(
    'i',
    $userId
);

$recordsStmt->execute();

$recordsResult =
    $recordsStmt->get_result();

$records = [];

while (
    $row =
    $recordsResult->fetch_assoc()
) {
    $records[] = $row;
}

$recordsStmt->close();


/* --------------------------------
   Today's locations
-------------------------------- */

$todayStmt = $conn->prepare(
    "SELECT
        id,
        action_type,
        latitude,
        longitude,
        created_at
     FROM attendance_events
     WHERE user_id = ?
     AND DATE(created_at) = CURDATE()
     ORDER BY created_at ASC, id ASC"
);

$todayStmt->bind_param(
    'i',
    $userId
);

$todayStmt->execute();

$todayResult =
    $todayStmt->get_result();

$todayLocations = [];

while (
    $row =
    $todayResult->fetch_assoc()
) {

    $todayLocations[] = [
        'id' =>
            (int) $row['id'],

        'action_type' =>
            (string) $row['action_type'],

        'latitude' =>
            (float) $row['latitude'],

        'longitude' =>
            (float) $row['longitude'],

        'created_at' =>
            date(
                'h:i A',
                strtotime(
                    (string) $row['created_at']
                )
            )
    ];
}

$todayStmt->close();


/* --------------------------------
   Weekly submissions
-------------------------------- */

$weeks = [];

$currentMonday =
    new DateTimeImmutable(
        'monday this week'
    );

for (
    $offset = 0;
    $offset < 6;
    $offset++
) {

    $startObject =
        $currentMonday
            ->modify(
                '-' .
                $offset .
                ' week'
            );

    $weekStart =
        $startObject
            ->format('Y-m-d');

    $weekEnd =
        $startObject
            ->modify('+6 days')
            ->format('Y-m-d');

    $submission =
        getWeeklySubmission(
            $conn,
            $userId,
            $weekStart
        );

    $recordCount =
        countWeekRecords(
            $conn,
            $userId,
            $weekStart,
            $weekEnd
        );

    $weeks[] = [
        'week_start' =>
            $weekStart,

        'week_end' =>
            $weekEnd,

        'submission' =>
            $submission,

        'record_count' =>
            $recordCount,

        'is_complete' =>
            $weekEnd < date('Y-m-d')
    ];
}


$message =
    trim(
        (string)
        ($_GET['msg'] ?? '')
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
        FieldTrack - Field Officer
    </title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="<?= h(
            appUrl(
                'user_panel.css'
            )
        ) ?>"
    >

</head>

<body>

<div class="page">

<header class="user-header">

    <div>

        <h1>
            FieldTrack
        </h1>

        <p>
            Field Officer Dashboard —
            <?= h($name) ?>
            (@<?= h($username) ?>)
        </p>

    </div>

    <a
        class="logout-btn"
        href="<?= h(
            appUrl(
                'logout.php'
            )
        ) ?>"
    >
        Logout
    </a>

</header>


<main class="user-container">

<?php if ($message !== ''): ?>

    <div class="message-box">

        <?= h($message) ?>

    </div>

<?php endif; ?>


<!-- ===============================
     SUMMARY
================================ -->

<section class="summary-grid">

    <div class="summary-card">

        <span>
            Current Status
        </span>

        <strong>

            <?= $lastAction === 'IN'
                ? 'IN'
                : 'OUT' ?>

        </strong>

    </div>


    <div class="summary-card">

        <span>
            Next Allowed Action
        </span>

        <strong>

            <?= h($nextAction) ?>

        </strong>

    </div>


    <div class="summary-card">

        <span>
            Today's Records
        </span>

        <strong>

            <?= count(
                $todayLocations
            ) ?>

        </strong>

    </div>

</section>


<!-- ===============================
     ATTENDANCE
================================ -->

<form
    id="attendanceForm"
    action="<?= h(
        appUrl(
            'mark_attendance.php'
        )
    ) ?>"
    method="POST"
>

<section class="card attendance-card">

    <h2>
        Mark Attendance
    </h2>

    <p class="muted">

        Click Mark IN or Mark OUT.
        Your current GPS location will be captured automatically.

    </p>


    <!-- Hidden location fields -->

    <input
        type="hidden"
        name="latitude"
        id="latInput"
    >

    <input
        type="hidden"
        name="longitude"
        id="lonInput"
    >

    <input
        type="hidden"
        name="action_type"
        id="actionInput"
    >


    <div class="attendance-actions">


        <button
            type="button"
            class="in-btn"
            id="inBtn"
            <?= $nextAction !== 'IN'
                ? 'disabled'
                : '' ?>
        >

            Mark IN

        </button>


        <button
            type="button"
            class="out-btn"
            id="outBtn"
            <?= $nextAction !== 'OUT'
                ? 'disabled'
                : '' ?>
        >

            Mark OUT

        </button>


    </div>


    <p
        id="locationStatus"
        class="location-status"
    >

        Current location will be captured automatically when attendance is marked.

    </p>


    <p class="muted">

        Attendance sequence:

        <strong>
            IN → OUT → IN → OUT
        </strong>

    </p>

</section>

</form>


<!-- ===============================
     WEEKLY SUBMISSION
================================ -->

<section class="card">

<h2>
    Weekly Attendance Submission
</h2>

<p class="muted">

    Submit completed weekly attendance
    to your Admin Officer.

</p>


<div class="week-list">


<?php foreach ($weeks as $week): ?>


<?php

$submission =
    $week['submission'];

$status =
    $submission['status']
    ?? 'draft';

?>


<div class="week-card">


<div>


<h3>

<?= h(
    date(
        'd M Y',
        strtotime(
            $week['week_start']
        )
    )
) ?>

—

<?= h(
    date(
        'd M Y',
        strtotime(
            $week['week_end']
        )
    )
) ?>

</h3>


<p>

<?= (int)
    $week['record_count'] ?>

attendance records

</p>


<?php if (
    $submission !== null
): ?>


<span class="week-status">

<?= h(
    getWeekStatusLabel(
        (string) $status
    )
) ?>

</span>


<?php else: ?>


<span class="week-status">

Not Submitted

</span>


<?php endif; ?>



<?php if (
    $submission !== null &&
    !empty(
        $submission[
            'latest_rejection_reason'
        ]
    )
): ?>


<div class="rejection-reason">

<strong>
    Rejection Reason:
</strong>

<?= h(
    (string)
    $submission[
        'latest_rejection_reason'
    ]
) ?>

</div>


<?php endif; ?>


</div>



<div class="week-actions">


<?php if (

    $submission === null &&

    $week['is_complete'] &&

    (int)
    $week['record_count'] > 0

): ?>


<form

    action="<?= h(
        appUrl(
            'submit_week.php'
        )
    ) ?>"

    method="POST"

    onsubmit="
        return confirm(
            'Submit this completed week for approval?'
        );
    "

>


<input
    type="hidden"
    name="week_start"
    value="<?= h(
        $week[
            'week_start'
        ]
    ) ?>"
>


<button type="submit">

Submit Week

</button>


</form>



<?php elseif (

    $submission !== null &&

    isResubmittable(
        $submission
    )

): ?>


<form

    action="<?= h(
        appUrl(
            'resubmit_week.php'
        )
    ) ?>"

    method="POST"

    onsubmit="
        return confirm(
            'Resubmit this rejected week?'
        );
    "

>


<input
    type="hidden"
    name="submission_id"
    value="<?= (int)
        $submission['id'] ?>"
>


<button type="submit">

Resubmit Week

</button>


</form>


<?php elseif (
    !$week['is_complete']
): ?>


<span class="muted">

Current week —
submit after Sunday

</span>


<?php endif; ?>


</div>


</div>


<?php endforeach; ?>


</div>

</section>



<!-- ===============================
     TODAY ROUTE MAP
================================ -->

<section class="card">

<h2>
    Today's Route
</h2>


<?php if (
    count(
        $todayLocations
    ) === 0
): ?>


<p class="muted">

No attendance locations
recorded today.

</p>


<?php else: ?>


<div id="todayRecordsMap"></div>


<?php endif; ?>


</section>



<!-- ===============================
     ATTENDANCE HISTORY
================================ -->

<section class="card">

<h2>
    Recent Attendance Records
</h2>


<div class="table-wrap">

<table>


<thead>

<tr>

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

</tr>

</thead>


<tbody>


<?php if (
    count($records) === 0
): ?>


<tr>

<td colspan="5">

No attendance records yet.

</td>

</tr>


<?php endif; ?>



<?php foreach (
    $records as $record
): ?>


<tr>


<td>

<strong>

<?= h(
    (string)
    $record[
        'action_type'
    ]
) ?>

</strong>

</td>


<td>

<?= h(
    date(
        'd/m/Y h:i A',
        strtotime(
            (string)
            $record[
                'created_at'
            ]
        )
    )
) ?>

</td>


<td>

<?= h(
    (string)
    $record[
        'latitude'
    ]
) ?>

</td>


<td>

<?= h(
    (string)
    $record[
        'longitude'
    ]
) ?>

</td>


<td>

<?= (int)
    $record[
        'is_locked'
    ] === 1

    ? 'Yes'

    : 'No'
?>

</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>

</div>

</section>


</main>

</div>


<script
src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
</script>


<script>


const attendanceForm =
    document.getElementById(
        'attendanceForm'
    );


const actionInput =
    document.getElementById(
        'actionInput'
    );


const latInput =
    document.getElementById(
        'latInput'
    );


const lonInput =
    document.getElementById(
        'lonInput'
    );


const locationStatus =
    document.getElementById(
        'locationStatus'
    );


const inBtn =
    document.getElementById(
        'inBtn'
    );


const outBtn =
    document.getElementById(
        'outBtn'
    );



function setButtonsBusy(
    isBusy
) {

    if (isBusy) {

        inBtn.disabled = true;

        outBtn.disabled = true;

        return;

    }


    inBtn.disabled =
        <?= $nextAction !== 'IN'
            ? 'true'
            : 'false' ?>;


    outBtn.disabled =
        <?= $nextAction !== 'OUT'
            ? 'true'
            : 'false' ?>;

}



function submitAttendance(
    actionType
) {


    if (
        !navigator.geolocation
    ) {

        locationStatus.textContent =
            'Your browser does not support location access.';


        alert(
            'Geolocation is not supported by this browser.'
        );


        return;

    }


    actionInput.value =
        actionType;


    locationStatus.textContent =
        'Getting your current location...';


    setButtonsBusy(
        true
    );



    navigator.geolocation
        .getCurrentPosition(


        function (
            position
        ) {


            latInput.value =
                Number(
                    position.coords.latitude
                ).toFixed(8);


            lonInput.value =
                Number(
                    position.coords.longitude
                ).toFixed(8);


            locationStatus.textContent =
                'Location captured. Saving attendance...';


            attendanceForm.submit();


        },


        function (
            error
        ) {


            setButtonsBusy(
                false
            );


            if (
                error.code ===
                error.PERMISSION_DENIED
            ) {


                locationStatus.textContent =
                    'Location permission was denied.';


                alert(
                    'Please allow location access to mark attendance.'
                );


            } else if (

                error.code ===
                error.POSITION_UNAVAILABLE

            ) {


                locationStatus.textContent =
                    'Current location is unavailable.';


                alert(
                    'Current location is unavailable. Please try again.'
                );


            } else if (

                error.code ===
                error.TIMEOUT

            ) {


                locationStatus.textContent =
                    'Location request timed out.';


                alert(
                    'Location request timed out. Please try again.'
                );


            } else {


                locationStatus.textContent =
                    'Could not capture your location.';


                alert(
                    'Could not capture your current location.'
                );


            }


        },


        {

            enableHighAccuracy:
                true,

            timeout:
                15000,

            maximumAge:
                0

        }


    );

}



inBtn.addEventListener(
    'click',
    function () {

        submitAttendance(
            'IN'
        );

    }
);



outBtn.addEventListener(
    'click',
    function () {

        submitAttendance(
            'OUT'
        );

    }
);



const todayLocations =

<?= json_encode(
    $todayLocations,
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;



if (
    todayLocations.length > 0
) {


    const todayMap =
        L.map(
            'todayRecordsMap'
        );


    L.tileLayer(

        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

        {

            maxZoom:
                19,

            attribution:
                '&copy; OpenStreetMap contributors'

        }

    ).addTo(
        todayMap
    );


    const bounds = [];

    const route = [];


    todayLocations.forEach(

        function (
            item,
            index
        ) {


            const point = [

                Number(
                    item.latitude
                ),

                Number(
                    item.longitude
                )

            ];


            bounds.push(
                point
            );


            route.push(
                point
            );


            L.marker(
                point
            )
            .addTo(
                todayMap
            )
            .bindPopup(

                '<strong>' +

                (index + 1) +

                '. ' +

                item.action_type +

                '</strong><br>' +

                'Time: ' +

                item.created_at

            );


        }

    );


    if (
        route.length > 1
    ) {


        L.polyline(

            route,

            {

                weight: 4,

                opacity: 0.75

            }

        ).addTo(
            todayMap
        );


    }


    if (
        bounds.length === 1
    ) {


        todayMap.setView(

            bounds[0],

            16

        );


    } else {


        todayMap.fitBounds(

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