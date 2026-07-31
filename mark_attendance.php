<?php

declare(strict_types=1);

const DEBUG_MODE = false;

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';

requireRole(['field_officer']);

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

[$currentWeekStart] = getWeekBounds();

try {
    $weeklySubmission = getWeeklySubmission(
        $conn,
        $userId,
        $currentWeekStart
    );
} catch (Throwable $error) {
    error_log(
        'FieldTrack weekly lock check error: ' .
        $error->getMessage()
    );

    redirectToUserPanel('save_failed');
}

if (!isWeekEditable($weeklySubmission)) {
    redirectToUserPanel('week_locked');
}

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    /*
     * Lock the latest event while checking the required
     * IN -> OUT -> IN -> OUT sequence.
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
            'Prepare failed (last attendance): ' .
            $conn->error
        );
    }

    $lastStatement->bind_param('i', $userId);
    $lastStatement->execute();

    $lastRow = $lastStatement
        ->get_result()
        ->fetch_assoc();

    $lastStatement->close();

    if ($lastRow === null && $actionType === 'OUT') {
        $conn->rollback();
        $transactionStarted = false;

        redirectToUserPanel('must_start_in');
    }

    if ($lastRow !== null) {
        $lastAction = (string) $lastRow['action_type'];

        if (
            $lastAction === 'IN' &&
            $actionType === 'IN'
        ) {
            $conn->rollback();
            $transactionStarted = false;

            redirectToUserPanel('already_in');
        }

        if (
            $lastAction === 'OUT' &&
            $actionType === 'OUT'
        ) {
            $conn->rollback();
            $transactionStarted = false;

            redirectToUserPanel('already_out');
        }
    }

    $insertStatement = $conn->prepare(
        "INSERT INTO attendance_events
            (
                user_id,
                action_type,
                latitude,
                longitude,
                is_locked
            )
         VALUES (?, ?, ?, ?, 0)"
    );

    if ($insertStatement === false) {
        throw new RuntimeException(
            'Prepare failed (attendance insert): ' .
            $conn->error
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

    $attendanceId = (int) $conn->insert_id;

    $insertStatement->close();

    $auditStatement = $conn->prepare(
        "INSERT INTO audit_logs
            (
                user_id,
                action,
                target_type,
                target_id,
                details,
                ip_address
            )
         VALUES (?, ?, 'attendance_event', ?, ?, ?)"
    );

    if ($auditStatement !== false) {
        $auditAction =
            $actionType === 'IN'
                ? 'ATTENDANCE_MARKED_IN'
                : 'ATTENDANCE_MARKED_OUT';

        $details =
            'Latitude: ' . $latitude .
            ', Longitude: ' . $longitude;

        $ipAddress = getClientIpAddress();

        $auditStatement->bind_param(
            'isiss',
            $userId,
            $auditAction,
            $attendanceId,
            $details,
            $ipAddress
        );

        $auditStatement->execute();
        $auditStatement->close();
    }

    $conn->commit();
    $transactionStarted = false;

    redirectToUserPanel('success');
} catch (Throwable $error) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable) {
            // Keep the original error.
        }
    }

    error_log(
        'FieldTrack attendance save error: ' .
        $error->getMessage()
    );

    if (DEBUG_MODE) {
        exit($error->getMessage());
    }

    redirectToUserPanel('save_failed');
}
