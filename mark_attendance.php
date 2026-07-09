<?php
session_start();
include 'db.php';

// Only logged-in normal users can mark attendance
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $action_type = $_POST['action_type'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];

    // Check action type
    if ($action_type != "IN" && $action_type != "OUT") {
        die("Invalid action type.");
    }

    // Check location
    if (empty($latitude) || empty($longitude)) {
        die("Location is missing. Please allow location access.");
    }

    // Check latest record to prevent wrong IN/OUT order
    $latest_sql = "SELECT action_type 
                   FROM attendance_events 
                   WHERE user_id = '$user_id' 
                   ORDER BY created_at DESC 
                   LIMIT 1";

    $latest_result = mysqli_query($conn, $latest_sql);
    $latest_action = null;

    if (mysqli_num_rows($latest_result) > 0) {
        $latest_row = mysqli_fetch_assoc($latest_result);
        $latest_action = $latest_row['action_type'];
    }

    if ($latest_action == null && $action_type != "IN") {
        die("You must click IN first.");
    }

    if ($latest_action == "IN" && $action_type == "IN") {
        die("You have already clicked IN. Please click OUT next.");
    }

    if ($latest_action == "OUT" && $action_type == "OUT") {
        die("You have already clicked OUT. Please click IN next.");
    }

    // Photo upload
    $photo_path = "";

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {

        $upload_folder = "uploads/";

        if (!is_dir($upload_folder)) {
            mkdir($upload_folder, 0777, true);
        }

        $photo_name = $_FILES['photo']['name'];
        $photo_tmp = $_FILES['photo']['tmp_name'];

        $file_extension = pathinfo($photo_name, PATHINFO_EXTENSION);

        $new_photo_name = "attendance_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;

        $photo_path = $upload_folder . $new_photo_name;

        if (!move_uploaded_file($photo_tmp, $photo_path)) {
            die("Photo upload failed.");
        }
    }

    // Save attendance record
    $insert_sql = "INSERT INTO attendance_events 
                   (user_id, action_type, latitude, longitude, photo_path)
                   VALUES 
                   ('$user_id', '$action_type', '$latitude', '$longitude', '$photo_path')";

    if (mysqli_query($conn, $insert_sql)) {
        header("Location: user_panel.php");
        exit();
    } else {
        die("Error saving attendance: " . mysqli_error($conn));
    }

} else {
    header("Location: user_panel.php");
    exit();
}
?>