<?php

declare(strict_types=1);

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

/*
 * Check whether a camera photo or gallery photo
 * has been submitted.
 */
$uploadedFile = null;

if (
    isset($_FILES['camera_photo']) &&
    !empty($_FILES['camera_photo']['name'])
) {
    $uploadedFile = $_FILES['camera_photo'];
} elseif (
    isset($_FILES['gallery_photo']) &&
    !empty($_FILES['gallery_photo']['name'])
) {
    $uploadedFile = $_FILES['gallery_photo'];
}

$temporaryPhotoPath = null;
$photoExtension = null;

/*
 * Validate the uploaded photo.
 */
if ($uploadedFile !== null) {
    if (
        !isset(
            $uploadedFile['error'],
            $uploadedFile['size'],
            $uploadedFile['tmp_name'],
            $uploadedFile['name']
        ) ||
        $uploadedFile['error'] !== UPLOAD_ERR_OK
    ) {
        redirectToUserPanel('photo_error');
    }

    /*
     * Maximum allowed photo size: 5 MB.
     */
    $maximumPhotoSize = 5 * 1024 * 1024;

    if (
        (int) $uploadedFile['size'] <= 0 ||
        (int) $uploadedFile['size'] > $maximumPhotoSize
    ) {
        redirectToUserPanel('invalid_photo');
    }

    $temporaryPhotoPath =
        (string) $uploadedFile['tmp_name'];

    if (!is_uploaded_file($temporaryPhotoPath)) {
        redirectToUserPanel('invalid_photo');
    }

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
        redirectToUserPanel('invalid_photo');
    }

    /*
     * Confirm that the uploaded file is actually an image.
     */
    $imageInformation = @getimagesize(
        $temporaryPhotoPath
    );

    if ($imageInformation === false) {
        redirectToUserPanel('invalid_photo');
    }

    /*
     * Check the real MIME type instead of trusting
     * only the filename extension.
     */
    $fileInformation = new finfo(
        FILEINFO_MIME_TYPE
    );

    $mimeType = $fileInformation->file(
        $temporaryPhotoPath
    );

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (
        $mimeType === false ||
        !isset($allowedMimeTypes[$mimeType])
    ) {
        redirectToUserPanel('invalid_photo');
    }

    /*
     * JPG, JPEG and JFIF files are saved using .jpg.
     */
    $photoExtension =
        $allowedMimeTypes[$mimeType];
}

$photoPath = null;
$absolutePhotoPath = null;
$photoWasMoved = false;
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
     * Move the validated photo to the uploads folder.
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
                'The upload directory could not be created.'
            );
        }

        /*
         * Generate a random filename.
         * The original filename is not used.
         */
        $newFilename =
            'fieldtrack_' .
            bin2hex(random_bytes(16)) .
            '.' .
            $photoExtension;

        $absolutePhotoPath =
            $uploadDirectory . $newFilename;

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
            'uploads/' . $newFilename;
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
                longitude,
                photo_path
            )
         VALUES (?, ?, ?, ?, ?)"
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
     * Remove the uploaded photo if the database
     * record could not be saved.
     */
    if (
        $photoWasMoved &&
        $absolutePhotoPath !== null &&
        is_file($absolutePhotoPath)
    ) {
        @unlink($absolutePhotoPath);
    }

    /*
     * Store the real error in the server log
     * instead of showing database details to users.
     */
    error_log(
        'FieldTrack attendance save error: ' .
        $error->getMessage()
    );

    redirectToUserPanel('save_failed');
}