<?php

declare(strict_types=1);

/*
 * Set to true temporarily while debugging to see the exact
 * database error in the browser instead of a generic message.
 * Always set back to false before going live.
 */
const DEBUG_MODE = false;

require_once 'auth.php';
require_once 'db.php';

requireRole(['user', 'admin']);

function redirectToUserPanel(string $message): never
{
    header(
        'Location: user_panel.php?msg=' .
        rawurlencode($message)
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user_panel.php');
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$actionType = strtoupper(
    trim((string) ($_POST['action_type'] ?? ''))
);

$latitudeValue = trim(
    (string) ($_POST['latitude'] ?? '')
);

$longitudeValue = trim(
    (string) ($_POST['longitude'] ?? '')
);

if (!in_array($actionType, ['IN', 'OUT'], true)) {
    redirectToUserPanel('invalid_action');
}

if ($latitudeValue === '' || $longitudeValue === '') {
    redirectToUserPanel('location_required');
}

if (
    !is_numeric($latitudeValue) ||
    !is_numeric($longitudeValue)
) {
    redirectToUserPanel('invalid_location');
}

$latitude = (float) $latitudeValue;
$longitude = (float) $longitudeValue;

if (
    !is_finite($latitude) ||
    !is_finite($longitude) ||
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {
    redirectToUserPanel('invalid_location');
}

$transactionStarted = false;

try {
    /*
     * Start a transaction so the IN/OUT validation
     * and attendance insert happen together.
     */
    $conn->begin_transaction();

    $transactionStarted = true;

    /*
     * Get and lock the user's latest attendance event.
     *
     * Correct order:
     * IN → OUT → IN → OUT
     */
    $lastStatement = $conn->prepare(
        "SELECT action_type
         FROM attendance_events
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1
         FOR UPDATE"
    );

    if ($lastStatement === false) {
        throw new RuntimeException(
            'Prepare failed (last action select): ' . $conn->error
        );
    }

    $lastStatement->bind_param(
        'i',
        $userId
    );

    $lastStatement->execute();

    $lastRow = $lastStatement
        ->get_result()
        ->fetch_assoc();

    $lastStatement->close();

    /*
     * The first attendance action must be IN.
     */
    if (
        $lastRow === null &&
        $actionType === 'OUT'
    ) {
        $conn->rollback();

        $transactionStarted = false;

        redirectToUserPanel('must_start_in');
    }

    if ($lastRow !== null) {
        $lastAction =
            (string) $lastRow['action_type'];

        /*
         * Prevent IN followed by another IN.
         */
        if (
            $lastAction === 'IN' &&
            $actionType === 'IN'
        ) {
            $conn->rollback();

            $transactionStarted = false;

            redirectToUserPanel('already_in');
        }

        /*
         * Prevent OUT followed by another OUT.
         */
        if (
            $lastAction === 'OUT' &&
            $actionType === 'OUT'
        ) {
            $conn->rollback();

            $transactionStarted = false;

            redirectToUserPanel('already_out');
        }
    }

    /*
     * Save the attendance record.
     */
    $insertStatement = $conn->prepare(
        "INSERT INTO attendance_events
            (
                user_id,
                action_type,
                latitude,
                longitude
            )
         VALUES (?, ?, ?, ?)"
    );

    if ($insertStatement === false) {
        throw new RuntimeException(
            'Prepare failed (insert attendance): ' . $conn->error
        );
    }

    $insertStatement->bind_param(
        'isdd',
        $userId,
        $actionType,
        $latitude,
        $longitude
    );

    $insertStatement->execute();

    $insertStatement->close();

    $conn->commit();

    $transactionStarted = false;

    redirectToUserPanel('success');
} catch (Throwable $error) {
    /*
     * Undo database changes when an error occurs.
     */
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable) {
            // Keep the original error.
        }
    }

    /*
     * Store the real error in the server log
     * instead of showing database details to users.
     */
    error_log(
        'FieldTrack attendance save error: ' .
        $error->getMessage()
    );

    if (DEBUG_MODE) {
        die($error->getMessage());
    }

    redirectToUserPanel('save_failed');
}