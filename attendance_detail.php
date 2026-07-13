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

<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width">
        <title>Attendance Details</title>
        <link rel="stylesheet" href="admin_style.css">
        <linl rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    </head>
    <body>
        <header class="admin-header">
            <div>
                <h1>Attendance Details</h1>
                <p>View the selected officer attendance record</p>
            </div>
            <a href="admin_panel.php" class="logout-btn">Back to Dashboard</a>
        </header>
        <main class="admin-container">
            <section class="admin-section">
                <div class="section-title">
                    <div>
                        <h2
                            <?= htmlspecialchars($record['name']) ?>
                    </h2>
                    <p>@<?=  htmlspecialchars($record['username']) ?>
                </p>
                    </div>
                    <span class="status-badge <?=  strtolower(
                        htmlspecialchars($record['action_type'])
                    ) ?>">
                    >
                <?=  htmlspecialchars($record['action_type']) ?>
            </span>
                </div>
            <div class="attendance-details=grid">
                <div class="attendance-information">
                    <div class="detail-row">
                        <span>Record ID</span>
                        <strong>
                            <?=  (int)$record['id'] ?>?>
                        </strong>

                    </div>
                    <div class="detail-row">
                        <span>Officer</span>
                        <strong>
                            <?=  htmlspecialchars($record['name']) ?>?>
                        </strong>
                    </div>
                    <div class="detail-row">
                        <span>Username</span>
                        <strong>
                            @<?=  htmlspecialchars($record['username']) ?>
                        </strong>
                    </div>
                    
                <div class="detail-row">
                    <span>Attendance Type</span>

                    <strong>
                        <?= htmlspecialchars($record['action_type']) ?>
                    </strong>
                </div>

                <div class="detail-row">
                    <span>Date and Time</span>

                    <strong>
                        <?= htmlspecialchars($formatted_date) ?>
                    </strong>
                </div>

                <div class="detail-row">
                    <span>Latitude</span>

                    <strong>
                        <?= htmlspecialchars($record['latitude']) ?>
                    </strong>
                </div>

                <div class="detail-row">
                     <span>Longitude</span>

                    <strong>
                        <?= htmlspecialchars($record['longitude']) ?>
                    </strong>
                </div>

            </div>

            <div class="attendance-photo">

                <h3>Attendance Photo</h3>

                <?php if (!empty($record['photo_path'])): ?>

                    <a
                        href="<?= htmlspecialchars(
                            $record['photo_path']
                        ) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <img
                            src="<?= htmlspecialchars(
                                $record['photo_path']
                            ) ?>"
                            alt="Attendance Photo"
                        >
                    </a>
                     <?php else: ?>

                    <div class="empty-map-box">
                        No photo was uploaded for this record.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>
   