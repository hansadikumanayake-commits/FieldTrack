<?php
include 'db.php';

//total no of officers
$total_officers_sql="SELECT COUNT(*) AS total_officers FROM users WHERE role='user'";
$total_officers_result=mysqli_query($conn,$total_officers_sql);


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
                    <p>12</p>
                </div>

                <div class="summary-card">
                    <h3>Today IN</h3>
                    <p>8</p>
                </div>

                <div class="summary-card">
                    <h3>Today OUT</h3>
                    <p>4</p>
                </div>

                <div class="summary-card">
                    <h3>Total Visits</h3>
                    <p>24</p>
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