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

// Get all attendance records grouped by each officer
$records_sql = "SELECT 
                    users.id AS user_id,
                    users.name,
                    attendance_events.id AS event_id,
                    attendance_events.action_type,
                    attendance_events.latitude,
                    attendance_events.longitude,
                    attendance_events.photo_path,
                    attendance_events.created_at
                FROM users
                LEFT JOIN attendance_events 
                ON users.id = attendance_events.user_id
                WHERE users.role = 'user'
                ORDER BY users.name ASC, attendance_events.created_at DESC";

$records_result = mysqli_query($conn, $records_sql);

if (!$records_result) {
    die("Records query failed: " . mysqli_error($conn));
}

// Store records user by user
$users = [];

while ($row = mysqli_fetch_assoc($records_result)) {
    $user_id = $row['user_id'];

    if (!isset($users[$user_id])) {
        $users[$user_id] = [
            'name' => $row['name'],
            'records' => [],
            'points' => []
        ];
    }

    if (!empty($row['event_id'])) {
        $users[$user_id]['records'][] = $row;

        if (!empty($row['latitude']) && !empty($row['longitude'])) {
            $users[$user_id]['points'][] = [
                'action_type' => $row['action_type'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'created_at' => $row['created_at']
            ];
        }
    }
}


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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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

        <section class="records-section">
    <div class="section-title">
        <h2>Officer Location Records</h2>
        <p>Each officer's IN and OUT details are shown with their own map.</p>
    </div>

    <?php foreach ($users as $user_id => $user) { ?>

        <div class="user-location-card">

            <h3 class="user-title"><?php echo $user['name']; ?></h3>

            <div class="in-out-wrapper">

                <div class="in-details-box">
                    <h4>IN Details</h4>

                    <?php
                    $has_in = false;

                    foreach ($user['records'] as $record) {
                        if ($record['action_type'] == 'IN') {
                            $has_in = true;
                    ?>

                            <div class="visit-detail">
                                <span class="status in-status">IN</span>

                                <p><strong>Date:</strong> <?php echo date("Y-m-d", strtotime($record['created_at'])); ?></p>
                                <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($record['created_at'])); ?></p>
                                <p><strong>Location:</strong> <?php echo $record['latitude'] . ", " . $record['longitude']; ?></p>

                                <?php if (!empty($record['photo_path'])) { ?>
                                    <img class="record-photo" src="<?php echo $record['photo_path']; ?>" alt="IN Photo">
                                <?php } else { ?>
                                    <p>No Photo Uploaded</p>
                                <?php } ?>
                            </div>

                    <?php
                        }
                    }

                    if (!$has_in) {
                        echo "<p>No IN records yet.</p>";
                    }
                    ?>
                </div>

                <div class="out-details-box">
                    <h4>OUT Details</h4>

                    <?php
                    $has_out = false;

                    foreach ($user['records'] as $record) {
                        if ($record['action_type'] == 'OUT') {
                            $has_out = true;
                    ?>

                            <div class="visit-detail">
                                <span class="status out-status">OUT</span>

                                <p><strong>Date:</strong> <?php echo date("Y-m-d", strtotime($record['created_at'])); ?></p>
                                <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($record['created_at'])); ?></p>
                                <p><strong>Location:</strong> <?php echo $record['latitude'] . ", " . $record['longitude']; ?></p>

                                <?php if (!empty($record['photo_path'])) { ?>
                                    <img class="record-photo" src="<?php echo $record['photo_path']; ?>" alt="OUT Photo">
                                <?php } else { ?>
                                    <p>No Photo Uploaded</p>
                                <?php } ?>
                            </div>

                    <?php
                        }
                    }

                    if (!$has_out) {
                        echo "<p>No OUT records yet.</p>";
                    }
                    ?>
                </div>

            </div>

            <?php if (!empty($user['points'])) { ?>
                <div id="user-map-<?php echo $user_id; ?>" class="user-map"></div>
            <?php } else { ?>
                <p class="no-location">No location available for this officer.</p>
            <?php } ?>

        </div>

    <?php } ?>

</section>

    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

</body>
</html>