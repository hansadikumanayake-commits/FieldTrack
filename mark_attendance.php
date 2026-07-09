<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user_panel.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$action_type = $_POST['action_type'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';

if ($action_type !== 'IN' && $action_type !== 'OUT') {
    header("Location: user_panel.php?msg=invalid_action");
    exit();
}

if ($latitude === '' || $longitude === '') {
    header("Location: user_panel.php?msg=location_required");
    exit();
}

if (!is_numeric($latitude) || !is_numeric($longitude)) {
    header("Location: user_panel.php?msg=invalid_location");
    exit();
}

$latitude = (float) $latitude;
$longitude = (float) $longitude;

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    header("Location: user_panel.php?msg=invalid_location");
    exit();
}

/*
    Server-side IN / OUT protection.

    Correct order:
    IN -> OUT -> IN -> OUT

    This prevents:
    IN -> IN
    OUT -> OUT
    OUT as the first action
*/
$last_stmt = $conn->prepare(
    "SELECT action_type
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);

$last_stmt->bind_param("i", $user_id);
$last_stmt->execute();
$last_result = $last_stmt->get_result();
$last_row = $last_result->fetch_assoc();
$last_stmt->close();

if (!$last_row && $action_type === 'OUT') {
    header("Location: user_panel.php?msg=must_start_in");
    exit();
}

if ($last_row) {
    $last_action = $last_row['action_type'];

    if ($last_action === 'IN' && $action_type === 'IN') {
        header("Location: user_panel.php?msg=already_in");
        exit();
    }

    if ($last_action === 'OUT' && $action_type === 'OUT') {
        header("Location: user_panel.php?msg=already_out");
        exit();
    }
}

/*
    Photo upload.
    User can take photo from camera or choose from gallery.
*/
$photo_path = null;
$uploaded_file = null;

if (!empty($_FILES['camera_photo']['name'])) {
    $uploaded_file = $_FILES['camera_photo'];
} elseif (!empty($_FILES['gallery_photo']['name'])) {
    $uploaded_file = $_FILES['gallery_photo'];
}

if ($uploaded_file) {
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        header("Location: user_panel.php?msg=photo_error");
        exit();
    }

    $upload_dir = __DIR__ . "/uploads/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        header("Location: user_panel.php?msg=invalid_photo");
        exit();
    }

    $new_filename = uniqid("fieldtrack_", true) . "." . $file_ext;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($uploaded_file['tmp_name'], $target_path)) {
        $photo_path = "uploads/" . $new_filename;
    } else {
        header("Location: user_panel.php?msg=photo_move_failed");
        exit();
    }
}

$stmt = $conn->prepare(
    "INSERT INTO attendance_events
    (user_id, action_type, latitude, longitude, photo_path)
    VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "isdds",
    $user_id,
    $action_type,
    $latitude,
    $longitude,
    $photo_path
);

if ($stmt->execute()) {
    header("Location: user_panel.php?msg=success");
    exit();
} else {
    header("Location: user_panel.php?msg=save_failed");
    exit();
}

$stmt->close();
$conn->close();
?>