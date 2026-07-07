<?php
include 'db.php';

// Total number of officers
// This counts how many field officers are in the users table.
$total_officers_sql = "SELECT COUNT(*) AS total_officers FROM users WHERE role='user'";
$total_officers_result = mysqli_query($conn, $total_officers_sql);
$total_officers_row = mysqli_fetch_assoc($total_officers_result);
$total_officers = $total_officers_row['total_officers'];

// Today IN
$today_in_sql = "SELECT COUNT(*) AS today_in 
                 FROM attendance_events 
                 WHERE action_type='IN' AND DATE(created_at)=CURDATE()";
$today_in_result = mysqli_query($conn, $today_in_sql);
$today_in_row = mysqli_fetch_assoc($today_in_result);
$today_in = $today_in_row['today_in'];

// Today OUT
$today_out_sql = "SELECT COUNT(*) AS today_out 
                  FROM attendance_events 
                  WHERE action_type='OUT' AND DATE(created_at)=CURDATE()";
$today_out_result = mysqli_query($conn, $today_out_sql);
$today_out_row = mysqli_fetch_assoc($today_out_result);
$today_out = $today_out_row['today_out'];

// Total visits
$total_visits_sql = "SELECT COUNT(*) AS total_visits FROM attendance_events";
$total_visits_result = mysqli_query($conn, $total_visits_sql);
$total_visits_row = mysqli_fetch_assoc($total_visits_result);
$total_visits = $total_visits_row['total_visits'];

// Get all attendance records with officer names
$records_sql = "SELECT attendance_events.*, users.name 
                FROM attendance_events 
                JOIN users ON attendance_events.user_id = users.id 
                ORDER BY attendance_events.created_at DESC";

$records_result = mysqli_query($conn, $records_sql);

if (!$records_result) {
    die("Records query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FieldTrack Admin Dashboard</title>
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
                <p><?php echo $total_officers; ?></p>
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
                <p><?php echo $total_visits; ?></p>
            </div>

        </section>

        <section class="map-section">
            <div class="section-title">
                <h2>Location Map</h2>
                <p>IN and OUT locations of the user will be shown here.</p>
            </div>

            <div class="map-box">
                <p>Map View Placeholder</p>
                <span>OpenStreetMap will be added here</span>
            </div>
        </section>

        <section class="records-section">
            <div class="section-title">
                <h2>Field Visit Records</h2>
                <p>Admin can view all officers' IN and OUT records here.</p>
            </div>

            <?php
            // Loop through all records from the database one by one
            while ($row = mysqli_fetch_assoc($records_result)) {
            ?>

                <div class="record-card">
                    <div class="record-info">

                        <h3><?php echo $row['name']; ?></h3>

                        <?php if ($row['action_type'] == 'IN') { ?>
                            <span class="status in-status">IN</span>
                        <?php } else { ?>
                            <span class="status out-status">OUT</span>
                        <?php } ?>

                        <p><strong>Date:</strong> <?php echo date("Y-m-d", strtotime($row['created_at'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($row['created_at'])); ?></p>
                        <p><strong>Location:</strong> <?php echo $row['latitude'] . ", " . $row['longitude']; ?></p>

                    </div>

                    <div class="photo-box">
                        <?php if (!empty($row['photo_path'])) { ?>
                            <img src="<?php echo $row['photo_path']; ?>" alt="Photo Evidence">
                        <?php } else { ?>
                            <p>No Photo Uploaded</p>
                        <?php } ?>
                    </div>
                </div>

            <?php } ?>

        </section>

    </main>

</body>
</html>