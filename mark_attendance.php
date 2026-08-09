<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';

requireRole(['field_officer']);


/* --------------------------------
   Redirect helper
-------------------------------- */

function backWithMessage(
    string $message
): never {

    redirectTo(
        'user_panel.php?msg=' .
        rawurlencode(
            $message
        )
    );

}


/* --------------------------------
   POST requests only
-------------------------------- */

if (
    $_SERVER[
        'REQUEST_METHOD'
    ] !== 'POST'
) {

    redirectTo(
        'user_panel.php'
    );

}


/* --------------------------------
   Get submitted values
-------------------------------- */

$userId =
    currentUserId();


$actionType =
    strtoupper(
        trim(
            (string)
            (
                $_POST[
                    'action_type'
                ]
                ?? ''
            )
        )
    );


$latitudeValue =
    trim(
        (string)
        (
            $_POST[
                'latitude'
            ]
            ?? ''
        )
    );


$longitudeValue =
    trim(
        (string)
        (
            $_POST[
                'longitude'
            ]
            ?? ''
        )
    );


/* --------------------------------
   Validate action
-------------------------------- */

if (
    !in_array(
        $actionType,
        [
            'IN',
            'OUT'
        ],
        true
    )
) {

    backWithMessage(
        'Invalid attendance action.'
    );

}


/* --------------------------------
   Validate GPS location
-------------------------------- */

if (

    $latitudeValue === '' ||

    $longitudeValue === '' ||

    !is_numeric(
        $latitudeValue
    ) ||

    !is_numeric(
        $longitudeValue
    )

) {

    backWithMessage(
        'Current location could not be captured. Please allow location access and try again.'
    );

}


$latitude =
    (float)
    $latitudeValue;


$longitude =
    (float)
    $longitudeValue;


if (

    !is_finite(
        $latitude
    ) ||

    !is_finite(
        $longitude
    ) ||

    $latitude < -90 ||

    $latitude > 90 ||

    $longitude < -180 ||

    $longitude > 180

) {

    backWithMessage(
        'The captured location is invalid. Please try again.'
    );

}


/* --------------------------------
   Current week
-------------------------------- */

[
    $currentWeekStart
] =
    getWeekBounds();


try {


    $weeklySubmission =
        getWeeklySubmission(

            $conn,

            $userId,

            $currentWeekStart

        );


    if (
        !isWeekEditable(
            $weeklySubmission
        )
    ) {

        backWithMessage(
            'This week is locked because it has already been submitted for approval.'
        );

    }


    $conn->begin_transaction();


    /* --------------------------------
       Get last attendance action
    -------------------------------- */

    $lastStmt =
        $conn->prepare(

            "SELECT action_type
             FROM attendance_events
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1
             FOR UPDATE"

        );


    $lastStmt->bind_param(
        'i',
        $userId
    );


    $lastStmt->execute();


    $lastRow =
        $lastStmt
            ->get_result()
            ->fetch_assoc();


    $lastStmt->close();


    $lastAction =
        $lastRow[
            'action_type'
        ]
        ?? null;



    /* --------------------------------
       IN / OUT sequence validation
    -------------------------------- */


    if (

        $lastAction === null &&

        $actionType === 'OUT'

    ) {


        $conn->rollback();


        backWithMessage(
            'Your first attendance action must be IN.'
        );


    }



    if (

        $lastAction === 'IN' &&

        $actionType === 'IN'

    ) {


        $conn->rollback();


        backWithMessage(
            'You are already IN. Mark OUT first.'
        );


    }



    if (

        $lastAction === 'OUT' &&

        $actionType === 'OUT'

    ) {


        $conn->rollback();


        backWithMessage(
            'You are already OUT. Mark IN first.'
        );


    }



    /* --------------------------------
       Save attendance
    -------------------------------- */


    $insert =
        $conn->prepare(

            "INSERT INTO attendance_events
            (
                user_id,
                action_type,
                latitude,
                longitude,
                is_locked
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                0
            )"

        );


    $insert->bind_param(

        'isdd',

        $userId,

        $actionType,

        $latitude,

        $longitude

    );


    $insert->execute();


    $attendanceId =
        (int)
        $conn->insert_id;


    $insert->close();



    /* --------------------------------
       Audit log
    -------------------------------- */


    $details =
        sprintf(

            '%s at %.6f, %.6f',

            $actionType,

            $latitude,

            $longitude

        );


    $ip =
        getClientIpAddress();


    $auditAction =

        $actionType === 'IN'

        ? 'ATTENDANCE_MARKED_IN'

        : 'ATTENDANCE_MARKED_OUT';



    $audit =
        $conn->prepare(

            "INSERT INTO audit_logs
            (
                user_id,
                action,
                target_type,
                target_id,
                details,
                ip_address
            )

            VALUES
            (
                ?,
                ?,
                'attendance_event',
                ?,
                ?,
                ?
            )"

        );


    $audit->bind_param(

        'isiss',

        $userId,

        $auditAction,

        $attendanceId,

        $details,

        $ip

    );


    $audit->execute();

    $audit->close();


    $conn->commit();


    backWithMessage(
        'Attendance saved successfully with your current location.'
    );


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


    error_log(

        'FieldTrack attendance error: ' .
        $error->getMessage()

    );


    backWithMessage(
        'Attendance could not be saved. Please try again.'
    );

}