<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Load RBAC permissions, authentication and database connection
|--------------------------------------------------------------------------
|
| permissions.php already loads:
| - auth.php
| - db.php
|
*/

require_once 'permissions.php';

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Redirect back to the Field Officer dashboard
|--------------------------------------------------------------------------
*/

function redirectToUserPanel(string $messageCode): never
{
    header(
        'Location: user_panel.php?msg=' .
        rawurlencode($messageCode)
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user_panel.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Detect uploads larger than the PHP server limit
|--------------------------------------------------------------------------
|
| When the uploaded file exceeds post_max_size, PHP may empty both
| $_POST and $_FILES.
|
*/

$contentLength = (int) (
    $_SERVER['CONTENT_LENGTH'] ?? 0
);

if (
    $contentLength > 0 &&
    empty($_POST) &&
    empty($_FILES)
) {
    redirectToUserPanel('photo_error');
}

/*
|--------------------------------------------------------------------------
| Read the attendance action
|--------------------------------------------------------------------------
*/

$actionType = strtoupper(
    trim(
        (string) (
            $_POST['action_type'] ?? ''
        )
    )
);

if (
    !in_array(
        $actionType,
        ['IN', 'OUT'],
        true
    )
) {
    redirectToUserPanel('invalid_action');
}

/*
|--------------------------------------------------------------------------
| Check RBAC permission
|--------------------------------------------------------------------------
*/

if ($actionType === 'IN') {
    requirePermission(
        'attendance.mark_in'
    );
}

if ($actionType === 'OUT') {
    requirePermission(
        'attendance.mark_out'
    );
}

/*
|--------------------------------------------------------------------------
| Get logged-in Field Officer ID
|--------------------------------------------------------------------------
|
| The user ID must come from the session.
| Do not accept a user ID from the form.
|
*/

$userId = currentUserId();

/*
|--------------------------------------------------------------------------
| Read and validate location
|--------------------------------------------------------------------------
*/

$latitudeValue = trim(
    (string) (
        $_POST['latitude'] ?? ''
    )
);

$longitudeValue = trim(
    (string) (
        $_POST['longitude'] ?? ''
    )
);

if (
    $latitudeValue === '' ||
    $longitudeValue === ''
) {
    redirectToUserPanel(
        'location_required'
    );
}

if (
    !is_numeric($latitudeValue) ||
    !is_numeric($longitudeValue)
) {
    redirectToUserPanel(
        'invalid_location'
    );
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
    redirectToUserPanel(
        'invalid_location'
    );
}

/*
|--------------------------------------------------------------------------
| Detect camera or gallery photo
|--------------------------------------------------------------------------
*/

$uploadedFile = null;

if (
    isset($_FILES['camera_photo']) &&
    !empty($_FILES['camera_photo']['name'])
) {
    $uploadedFile =
        $_FILES['camera_photo'];
} elseif (
    isset($_FILES['gallery_photo']) &&
    !empty($_FILES['gallery_photo']['name'])
) {
    $uploadedFile =
        $_FILES['gallery_photo'];
}

/*
|--------------------------------------------------------------------------
| Photo validation variables
|--------------------------------------------------------------------------
*/

$temporaryPhotoPath = null;
$photoExtension = null;

/*
|--------------------------------------------------------------------------
| Validate uploaded photo
|--------------------------------------------------------------------------
*/

if ($uploadedFile !== null) {
    if (
        !isset(
            $uploadedFile['error'],
            $uploadedFile['size'],
            $uploadedFile['tmp_name'],
            $uploadedFile['name']
        )
    ) {
        redirectToUserPanel(
            'photo_error'
        );
    }

    if (
        (int) $uploadedFile['error'] !==
        UPLOAD_ERR_OK
    ) {
        redirectToUserPanel(
            'photo_error'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Maximum photo size: 5 MB
    |--------------------------------------------------------------------------
    */

    $maximumPhotoSize =
        5 * 1024 * 1024;

    $uploadedFileSize =
        (int) $uploadedFile['size'];

    if (
        $uploadedFileSize <= 0 ||
        $uploadedFileSize >
        $maximumPhotoSize
    ) {
        redirectToUserPanel(
            'invalid_photo'
        );
    }

    $temporaryPhotoPath =
        (string) $uploadedFile['tmp_name'];

    if (
        !is_uploaded_file(
            $temporaryPhotoPath
        )
    ) {
        redirectToUserPanel(
            'invalid_photo'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Check filename extension
    |--------------------------------------------------------------------------
    */

    $originalExtension = strtolower(
        pathinfo(
            (string) $uploadedFile['name'],
            PATHINFO_EXTENSION
        )
    );

    $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'jfif'
    ];

    if (
        !in_array(
            $originalExtension,
            $allowedExtensions,
            true
        )
    ) {
        redirectToUserPanel(
            'invalid_photo'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm that the uploaded file is an image
    |--------------------------------------------------------------------------
    */

    $imageInformation = @getimagesize(
        $temporaryPhotoPath
    );

    if ($imageInformation === false) {
        redirectToUserPanel(
            'invalid_photo'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Check the actual MIME type
    |--------------------------------------------------------------------------
    */

    $fileInformation = new finfo(
        FILEINFO_MIME_TYPE
    );

    $mimeType = $fileInformation->file(
        $temporaryPhotoPath
    );

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (
        $mimeType === false ||
        !isset(
            $allowedMimeTypes[$mimeType]
        )
    ) {
        redirectToUserPanel(
            'invalid_photo'
        );
    }

    /*
     * JPG, JPEG and JFIF photos will be stored
     * using the .jpg extension.
     */

    $photoExtension =
        $allowedMimeTypes[$mimeType];
}

/*
|--------------------------------------------------------------------------
| Prepare transaction variables
|--------------------------------------------------------------------------
*/

$photoPath = null;
$absolutePhotoPath = null;
$photoWasMoved = false;
$transactionStarted = false;

try {
    /*
    |--------------------------------------------------------------------------
    | Begin database transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    $transactionStarted = true;

    /*
    |--------------------------------------------------------------------------
    | Lock the current user row
    |--------------------------------------------------------------------------
    |
    | This helps prevent two attendance submissions for the same officer
    | from being processed at exactly the same time.
    |
    */

    $userLockStatement = $conn->prepare(
        "SELECT id
         FROM users
         WHERE id = ?
         AND is_active = 1
         LIMIT 1
         FOR UPDATE"
    );

    $userLockStatement->bind_param(
        'i',
        $userId
    );

    $userLockStatement->execute();

    $activeUser = $userLockStatement
        ->get_result()
        ->fetch_assoc();

    $userLockStatement->close();

    if ($activeUser === null) {
        throw new RuntimeException(
            'The user account is inactive or unavailable.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get the latest attendance record
    |--------------------------------------------------------------------------
    |
    | Correct sequence:
    |
    | IN → OUT → IN → OUT
    |
    */

    $lastStatement = $conn->prepare(
        "SELECT
            id,
            action_type
         FROM attendance_events
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1
         FOR UPDATE"
    );

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
    |--------------------------------------------------------------------------
    | First attendance action must be IN
    |--------------------------------------------------------------------------
    */

    if (
        $lastRow === null &&
        $actionType === 'OUT'
    ) {
        $conn->rollback();

        $transactionStarted = false;

        redirectToUserPanel(
            'must_start_in'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent repeated IN or repeated OUT
    |--------------------------------------------------------------------------
    */

    if ($lastRow !== null) {
        $lastAction = strtoupper(
            (string) $lastRow['action_type']
        );

        if (
            $lastAction === 'IN' &&
            $actionType === 'IN'
        ) {
            $conn->rollback();

            $transactionStarted = false;

            redirectToUserPanel(
                'already_in'
            );
        }

        if (
            $lastAction === 'OUT' &&
            $actionType === 'OUT'
        ) {
            $conn->rollback();

            $transactionStarted = false;

            redirectToUserPanel(
                'already_out'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Move photo into the uploads folder
    |--------------------------------------------------------------------------
    */

    if (
        $temporaryPhotoPath !== null &&
        $photoExtension !== null
    ) {
        $uploadDirectory =
            __DIR__ . '/uploads/';

        if (
            !is_dir($uploadDirectory) &&
            !mkdir(
                $uploadDirectory,
                0755,
                true
            ) &&
            !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                'The uploads folder could not be created.'
            );
        }

        /*
         * Generate a random secure filename.
         */

        $newFilename =
            'fieldtrack_' .
            bin2hex(
                random_bytes(16)
            ) .
            '.' .
            $photoExtension;

        $absolutePhotoPath =
            $uploadDirectory .
            $newFilename;

        if (
            !move_uploaded_file(
                $temporaryPhotoPath,
                $absolutePhotoPath
            )
        ) {
            $conn->rollback();

            $transactionStarted = false;

            redirectToUserPanel(
                'photo_move_failed'
            );
        }

        $photoWasMoved = true;

        $photoPath =
            'uploads/' .
            $newFilename;
    }

    /*
    |--------------------------------------------------------------------------
    | Insert attendance event
    |--------------------------------------------------------------------------
    */

    $insertStatement = $conn->prepare(
        "INSERT INTO attendance_events
        (
            user_id,
            action_type,
            latitude,
            longitude,
            photo_path,
            is_locked
        )
        VALUES (?, ?, ?, ?, ?, 0)"
    );

    $insertStatement->bind_param(
        'isdds',
        $userId,
        $actionType,
        $latitude,
        $longitude,
        $photoPath
    );

    $insertStatement->execute();

    $attendanceEventId =
        (int) $conn->insert_id;

    $insertStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Add audit log
    |--------------------------------------------------------------------------
    */

    $auditAction =
        $actionType === 'IN'
            ? 'ATTENDANCE_IN'
            : 'ATTENDANCE_OUT';

    $targetType =
        'attendance_event';

    $auditDetails =
        $actionType .
        ' attendance marked at latitude ' .
        $latitude .
        ' and longitude ' .
        $longitude;

    $ipAddress =
        $_SERVER['REMOTE_ADDR'] ??
        null;

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
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $auditStatement->bind_param(
        'ississ',
        $userId,
        $auditAction,
        $targetType,
        $attendanceEventId,
        $auditDetails,
        $ipAddress
    );

    $auditStatement->execute();

    $auditStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Complete transaction
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    $transactionStarted = false;

    redirectToUserPanel(
        'success'
    );
} catch (Throwable $error) {
    /*
    |--------------------------------------------------------------------------
    | Roll back database changes
    |--------------------------------------------------------------------------
    */

    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable) {
            // Keep the original error.
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete the uploaded photo if saving failed
    |--------------------------------------------------------------------------
    */

    if (
        $photoWasMoved &&
        $absolutePhotoPath !== null &&
        is_file($absolutePhotoPath)
    ) {
        @unlink(
            $absolutePhotoPath
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store technical error in the PHP/Apache error log
    |--------------------------------------------------------------------------
    */

    error_log(
        'FieldTrack attendance error: ' .
        $error->getMessage()
    );

    redirectToUserPanel(
        'save_failed'
    );
}