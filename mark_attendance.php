<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';

requireRole(['field_officer']);

function backWithMessage(string $message): never
{
    redirectTo('user_panel.php?msg=' . rawurlencode($message));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('user_panel.php');
}

$userId = currentUserId();

$actionType = strtoupper(trim((string) ($_POST['action_type'] ?? '')));
$latitudeValue = trim((string) ($_POST['latitude'] ?? ''));
$longitudeValue = trim((string) ($_POST['longitude'] ?? ''));

if (!in_array($actionType, ['IN', 'OUT'], true)) {
    backWithMessage('Invalid attendance action.');
}

if (
    $latitudeValue === '' ||
    $longitudeValue === '' ||
    !is_numeric($latitudeValue) ||
    !is_numeric($longitudeValue)
) {
    backWithMessage('Select a valid location before marking attendance.');
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
    backWithMessage('The selected latitude or longitude is invalid.');
}

[$currentWeekStart] = getWeekBounds();

try {
    $weeklySubmission = getWeeklySubmission(
        $conn,
        $userId,
        $currentWeekStart
    );

    if (!isWeekEditable($weeklySubmission)) {
        backWithMessage(
            'This week is locked because it has already been submitted for approval.'
        );
    }

    $conn->begin_transaction();

    $lastStmt = $conn->prepare(
        "SELECT action_type
         FROM attendance_events
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1
         FOR UPDATE"
    );

    $lastStmt->bind_param('i', $userId);
    $lastStmt->execute();
    $lastRow = $lastStmt->get_result()->fetch_assoc();
    $lastStmt->close();

    $lastAction = $lastRow['action_type'] ?? null;

    if ($lastAction === null && $actionType === 'OUT') {
        $conn->rollback();
        backWithMessage('Your first attendance action must be IN.');
    }

    if ($lastAction === 'IN' && $actionType === 'IN') {
        $conn->rollback();
        backWithMessage('You are already IN. Mark OUT first.');
    }

    if ($lastAction === 'OUT' && $actionType === 'OUT') {
        $conn->rollback();
        backWithMessage('You are already OUT. Mark IN first.');
    }

    $photoPath = null;

    $upload = null;

    foreach (['attendance_photo', 'camera_photo', 'gallery_photo'] as $fieldName) {
        if (
            isset($_FILES[$fieldName]) &&
            is_array($_FILES[$fieldName]) &&
            (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ) {
            $upload = $_FILES[$fieldName];
            break;
        }
    }

    if ($upload !== null) {
        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorCode !== UPLOAD_ERR_OK) {
            $conn->rollback();
            backWithMessage('The photo could not be uploaded.');
        }

        $size = (int) ($upload['size'] ?? 0);

        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            $conn->rollback();
            backWithMessage('Photo must be smaller than 5 MB.');
        }

        $originalName = (string) ($upload['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];

        if (!in_array($extension, $allowedExtensions, true)) {
            $conn->rollback();
            backWithMessage('Use a JPG, JPEG, PNG, WEBP, or JFIF photo.');
        }

        $uploadDirectory = __DIR__ . '/uploads';

        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
            $conn->rollback();
            backWithMessage('The uploads folder could not be created.');
        }

        $safeFilename = sprintf(
            'attendance_%d_%s_%s.%s',
            $userId,
            date('Ymd_His'),
            bin2hex(random_bytes(4)),
            $extension
        );

        $destination = $uploadDirectory . '/' . $safeFilename;

        if (!move_uploaded_file((string) $upload['tmp_name'], $destination)) {
            $conn->rollback();
            backWithMessage('The photo could not be saved.');
        }

        $photoPath = 'uploads/' . $safeFilename;
    }

    $insert = $conn->prepare(
        "INSERT INTO attendance_events
            (user_id, action_type, latitude, longitude, photo_path, is_locked)
         VALUES (?, ?, ?, ?, ?, 0)"
    );

    $insert->bind_param(
        'isdds',
        $userId,
        $actionType,
        $latitude,
        $longitude,
        $photoPath
    );

    $insert->execute();
    $attendanceId = (int) $conn->insert_id;
    $insert->close();

    $details = sprintf(
        '%s at %.6f, %.6f',
        $actionType,
        $latitude,
        $longitude
    );
    $ip = getClientIpAddress();

    $audit = $conn->prepare(
        "INSERT INTO audit_logs
            (user_id, action, target_type, target_id, details, ip_address)
         VALUES (?, ?, 'attendance_event', ?, ?, ?)"
    );

    $auditAction = $actionType === 'IN'
        ? 'ATTENDANCE_MARKED_IN'
        : 'ATTENDANCE_MARKED_OUT';

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

    backWithMessage('Attendance saved successfully.');
} catch (Throwable $error) {
    try {
        $conn->rollback();
    } catch (Throwable) {
        // Ignore rollback errors.
    }

    error_log('FieldTrack attendance error: ' . $error->getMessage());
    backWithMessage('Attendance could not be saved. Please try again.');
}
