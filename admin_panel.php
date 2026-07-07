<?php
include 'db.php';

//total no of officers
$total_officers_sql="SELECT COUNT(*) AS total_officers FROM users WHERE role='user'";

//use the database connection and execute the query to get the total number of officers
$total_officers_result=mysqli_query($conn,$total_officers_sql);

//Take the result from MySQL and convert it into an associative array to access the values using column names
$total_officers_row=mysqli_fetch_assoc($total_officers_result);

$total_officers=$total_officers_row['total_officers'];


//today IN 
$today_in_sql="SELECT COUNT(*) AS today_in FROM attendance_events WHERE action_type='IN' AND DATE(created_at)=CURDATE()";
$today_in_result=mysqli_query($conn,$today_in_sql);
$today_in_row=mysqli_fetch_assoc($today_in_result);
$today_in=$today_in_row['today_in'];

//today OUT
$today_out_sql="SELECT COUNT(*) AS today_out FROM attendance_events WHERE action_type='OUT' AND DATE(created_at)=CURDATE()";
$today_out_result=mysqli_query($conn,$today_out_sql);
$today_out_row=mysqli_fetch_assoc($today_out_result);
$today_out=$today_out_row['today_out'];

//total visits
$total_visits_sql="SELECT COUNT(*) AS total_visits FROM attendance_events";
$total_visits_result=mysqli_query($conn,$total_visits_sql);
$total_visits_row=mysqli_fetch_assoc($total_visits_result);
$total_visits=$total_visits_row['total_visits'];


?>


<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Field Track Admin Dashboard</title>
        <link rel="stylesheet" href="admin_style.css">
    </head>
    <body>
        <header class="admin-header">
            <div>
                <h1>FieldTrack</h1>
                <p>Admin Dashboard</p>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>

        </header>

        <main class="admin-container">
            <section class="summary-section">

                <div class="summary-card">
                    <h3>Total Officers</h3>
                    <p><?php echo $total_officers;?></p>
                </div>

                <div class="summary-card">
                    <h3>Today IN</h3>
                    <p><?php echo $today_in; ?></p>
                </div>

                <div class="summary-card">
                    <h3>Today OUT</h3>
                    <p><?php echo $today_out; ?></p>
                </div>

                <div class="summary-card">
                    <h3>Total Visits</h3>
                    <p><?php echo $total_visits;?></p>
                </div>

            </section>

            <section class="map-section">
                <div class="section-title">
                    <h2>Location Map</h2>
                    <p>IN and OUT locations of the user will be shown in here</p>

                </div>
                <div class="map-box">
                    <p>Map View Placeholder</p>
                    <span>OpenStreetMap will be added here</span>
                </div>

            </section>

            <section class="records-section">
                <div class="section-title">
                    <h2>Field Visit Records</h2>
                    <p>Admin can view all officers' IN and OUT records here</p>
                </div>

                <div class="record-card">
                    <div class="record-info">
                        <h3>Field Officer</h3>
                        <span class="status in-status">IN </span>

                        <p><strong>Date:</strong>2026-07-07</p>
                        <p><strong>Time:</strong> 10:00 AM</p>
                        <p><strong>Location:</strong> 6.9271, 79.8621</p>
                    </div>

                    <div class= "photo-box">
                        <p>Photo Uploaded</p>
                    </div>

                </div>

                <div class="record-card">
                    <div class="record-info">
                        <h3>Field Officer</h3>
                        <span class="status out-status">OUT</span>

                        <p><strong>Date:</strong> 2026-07-07</p>
                        <p><strong>Time:</strong> 04:00 PM</p>
                        <p><strong>Location:</strong> 6.9291, 79.8621</p>
                    </div>

                    <div class="photo-box">
                        <p>Photo Uploaded</p>
                </div>
            </section>

        </main>

    </body>
</html>