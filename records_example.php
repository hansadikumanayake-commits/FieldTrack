<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/review_helpers.php';

requireRole([
    'system_admin'
]);


$message = '';


/* =========================================================
   CREATE DEMO RECORDS
   ========================================================= */

if (
    $_SERVER[
        'REQUEST_METHOD'
    ] === 'POST'
) {


    $fieldOfficerId =
        filter_input(
            INPUT_POST,
            'field_officer_id',
            FILTER_VALIDATE_INT
        );


    $weekStart =
        trim(
            (string)
            ($_POST[
                'week_start'
            ] ?? '')
        );


    if (

        $fieldOfficerId === false ||

        $fieldOfficerId === null ||

        $fieldOfficerId < 1 ||

        !isValidWeekStart(
            $weekStart
        )

    ) {


        $message =
            'Choose a valid Field Officer and a Monday date.';


    } else {


        try {


            $conn->begin_transaction();


            /*
             * Creates:
             *
             * Monday    IN + OUT
             * Tuesday   IN + OUT
             * Wednesday IN + OUT
             * Thursday  IN + OUT
             * Friday    IN + OUT
             *
             * Total = 10 records
             */


            $baseDate =
                new DateTimeImmutable(
                    $weekStart
                );


            $insert =
                $conn->prepare(
                    "INSERT INTO attendance_events
                    (
                        user_id,
                        action_type,
                        latitude,
                        longitude,
                        is_locked,
                        created_at
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        0,
                        ?
                    )"
                );


            for (
                $day = 0;
                $day < 5;
                $day++
            ) {


                $date =
                    $baseDate
                    ->modify(
                        '+' .
                        $day .
                        ' days'
                    )
                    ->format(
                        'Y-m-d'
                    );


                /* -------------------------
                   IN
                   ------------------------- */

                $inAction =
                    'IN';


                $inLat =
                    6.9271 +
                    (
                        $day *
                        0.002
                    );


                $inLon =
                    79.8612 +
                    (
                        $day *
                        0.002
                    );


                $inTime =
                    $date .
                    ' 08:30:00';


                $insert->bind_param(

                    'isdds',

                    $fieldOfficerId,

                    $inAction,

                    $inLat,

                    $inLon,

                    $inTime

                );


                $insert->execute();



                /* -------------------------
                   OUT
                   ------------------------- */

                $outAction =
                    'OUT';


                $outLat =
                    $inLat +
                    0.001;


                $outLon =
                    $inLon +
                    0.001;


                $outTime =
                    $date .
                    ' 16:30:00';


                $insert->bind_param(

                    'isdds',

                    $fieldOfficerId,

                    $outAction,

                    $outLat,

                    $outLon,

                    $outTime

                );


                $insert->execute();


            }


            $insert->close();


            $conn->commit();


            $message =
                '10 demo attendance records were created for the selected week.';


        } catch (
            Throwable $error
        ) {


            try {

                $conn->rollback();

            } catch (
                Throwable
            ) {

                // Ignore rollback error.

            }


            $message =
                'Demo records could not be created: ' .
                $error->getMessage();


        }


    }


}


/* =========================================================
   FIELD OFFICERS
   ========================================================= */

$officers = [];


$result =
    $conn->query(
        "SELECT
            u.id,
            u.name,
            u.username

         FROM users u

         INNER JOIN user_roles ur
            ON ur.user_id =
               u.id

         INNER JOIN roles r
            ON r.id =
               ur.role_id

         WHERE
            r.role_name =
            'field_officer'

         ORDER BY
            u.name"
    );


while (
    $row =
    $result->fetch_assoc()
) {

    $officers[] =
        $row;

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

Create Demo Attendance

</title>


<link
rel="stylesheet"
href="<?= h(
    appUrl(
        'review_panel.css'
    )
) ?>"
>


</head>


<body>


<header class="topbar">


<div>

<h1>
FieldTrack
</h1>

<p>
Create Demo Attendance Records
</p>

</div>


<div class="topbar-links">


<a href="<?= h(
    appUrl(
        'admin_panel.php'
    )
) ?>">

Dashboard

</a>


<a
class="logout"
href="<?= h(
    appUrl(
        'logout.php'
    )
) ?>">

Logout

</a>


</div>


</header>


<main class="container">


<?php if (
    $message !== ''
): ?>


<div class="message">

<?= h(
    $message
) ?>

</div>


<?php endif; ?>


<section class="panel">


<h2>

Generate One Completed Demo Week

</h2>


<p class="small">

Choose a Monday from a past week.

This creates 10 attendance records:

<strong>

IN and OUT for Monday to Friday.

</strong>

</p>


<form

method="POST"

class="form-grid"

action="<?= h(
    appUrl(
        'records_example.php'
    )
) ?>"

>


<div>


<label for="field_officer_id">

Field Officer

</label>


<select

id="field_officer_id"

name="field_officer_id"

required

>


<?php foreach (
    $officers
    as
    $officer
): ?>


<option

value="<?= (int)
    $officer[
        'id'
    ] ?>"

>


<?= h(
    $officer[
        'name'
    ]
) ?>

(@<?= h(
    $officer[
        'username'
    ]
) ?>)


</option>


<?php endforeach; ?>


</select>


</div>


<div>


<label for="week_start">

Week Start (Monday)

</label>


<input

id="week_start"

type="date"

name="week_start"

required

>


</div>


<div class="form-actions">


<button

class="approve-button"

type="submit"

>

Create Demo Week

</button>


</div>


</form>


</section>


</main>


</body>

</html>