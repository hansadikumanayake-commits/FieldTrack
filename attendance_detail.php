<?php

session_start();
include "db.php";

if(
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
){
    header("Location:login.php");
    exit();
}
$record_id=isset($_GET['id'])
? trim($_GET['id'])
: '';

if($record_id === '' || !ctype_digit($record_id)){
    header("Location:admin_panel.php");
    exit();
}

$record_id=(int)$record_id;

$sql="
    SELECT
        attendance_events.id,
        attendance_events.action_type,
        attendance_events.latitude,
        attendance_events.longitude,
        attendance_events.photo_path,
        attendance_events.created_at,
        users.name,
        users.username

    FROM attendance_events
    JOIN users 
        ON attendance_events.user_id=users.id
    WHERE attendance_events.id=?
    LIMIT 1
";

$stmt=mysqli_prepare($conn,$sql);
if(!$stmt){
    die("Query preparation failed.");
}

mysqli_stmt_bind_param($stmt,"i",$record_id);
mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);
$record=mysqli_fetch_assoc($result);

if(!$record){
    http_response_code(404);
    die("Attendance record not found");
}

$latitude=(float)$record['latitude'];
$longitude=(float)$record['longitude'];

$formatted_date = date(
    "d/m/Y h:i A",
    strtotime($record['created_at'])
);


?>