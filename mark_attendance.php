<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['field_officer']);

function attendanceBack(string $message): never
{
    redirectTo('user_panel.php?msg=' . rawurlencode($message));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('user_panel.php');
}

requireValidCsrf();

$userId = currentUserId();
$actionType = strtoupper(trim((string) ($_POST['action_type'] ?? '')));
$latitudeValue = trim((string) ($_POST['latitude'] ?? ''));
$longitudeValue = trim((string) ($_POST['longitude'] ?? ''));

if (!in_array($actionType, ['IN', 'OUT'], true)) {
    attendanceBack('Invalid attendance action.');
}

if (
    $latitudeValue === '' ||
    $longitudeValue === '' ||
    !is_numeric($latitudeValue) ||
    !is_numeric($longitudeValue)
) {
    attendanceBack('Current location could not be captured. Please allow location access and try again.');
}

$latitude = (float) $latitudeValue;
$longitude = (float) $longitudeValue;

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    attendanceBack('The captured GPS location is invalid.');
}

[$currentWeekStart] = getWeekBounds();
$submission = getWeeklySubmission($conn, $userId, $currentWeekStart);

if (!isWeekEditable($submission)) {
    attendanceBack('This week is locked because it has already been submitted.');
}

try {
    $conn->begin_transaction();

    $lastStmt = $conn->prepare(
        "SELECT action_type, created_at
         FROM attendance_events
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1
         FOR UPDATE"
    );
    $lastStmt->bind_param('i', $userId);
    $lastStmt->execute();
    $last = $lastStmt->get_result()->fetch_assoc();
    $lastStmt->close();

    $lastAction = $last['action_type'] ?? null;

    if ($lastAction === null && $actionType === 'OUT') {
        $conn->rollback();
        attendanceBack('Your first attendance action must be IN.');
    }

    if ($lastAction === $actionType) {
        $conn->rollback();
        attendanceBack(
            $actionType === 'IN'
                ? 'You are already IN. Mark OUT first.'
                : 'You are already OUT. Mark IN first.'
        );
    }

    if (!empty($last['created_at'])) {
        $secondsSinceLast = time() - strtotime((string) $last['created_at']);

        if ($secondsSinceLast >= 0 && $secondsSinceLast < 10) {
            $conn->rollback();
            attendanceBack('Please wait a few seconds before marking another attendance action.');
        }
    }

    $insert = $conn->prepare(
        "INSERT INTO attendance_events
            (user_id, action_type, latitude, longitude, is_locked)
         VALUES (?, ?, ?, ?, 0)"
    );
    $insert->bind_param('isdd', $userId, $actionType, $latitude, $longitude);
    $insert->execute();
    $attendanceId = (int) $conn->insert_id;
    $insert->close();

    $ip = getClientIpAddress();
    $details = sprintf('%s at %.6f, %.6f', $actionType, $latitude, $longitude);
    $auditAction = $actionType === 'IN' ? 'ATTENDANCE_MARKED_IN' : 'ATTENDANCE_MARKED_OUT';

    $audit = $conn->prepare(
        "INSERT INTO audit_logs
            (user_id, action, target_type, target_id, details, ip_address)
         VALUES (?, ?, 'attendance_event', ?, ?, ?)"
    );
    $audit->bind_param('isiss', $userId, $auditAction, $attendanceId, $details, $ip);
    $audit->execute();
    $audit->close();

    $conn->commit();
    attendanceBack($actionType . ' attendance saved successfully with your current GPS location.');
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }

    error_log('FieldTrack attendance error: ' . $error->getMessage());
    attendanceBack('Attendance could not be saved. Please try again.');
}