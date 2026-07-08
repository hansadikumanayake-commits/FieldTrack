<?php
include 'db.php';

// Total number of officers
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

// Get attendance records grouped by each officer
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
                ORDER BY users.name ASC, users.id ASC, attendance_events.created_at ASC";

$records_result = mysqli_query($conn, $records_sql);

if (!$records_result) {
    die("Records query failed: " . mysqli_error($conn));
}

// Store records officer by officer
$users = [];

while ($row = mysqli_fetch_assoc($records_result)) {
    $user_id = $row['user_id'];

    if (!isset($users[$user_id])) {
        $users[$user_id] = [
            'name' => $row['name'],
            'records' => [],
            'visits' => []
        ];
    }

    if (!empty($row['event_id'])) {
        $users[$user_id]['records'][] = $row;
    }
}

// Create IN and OUT pairs for each officer
foreach ($users as $user_id => &$user) {
    $current_visit = null;
    $pair_no = 1;

    foreach ($user['records'] as $record) {

        if ($record['action_type'] == 'IN') {

            if ($current_visit !== null) {
                $current_visit['pair_no'] = $pair_no;
                $user['visits'][] = $current_visit;
                $pair_no++;
            }

            $current_visit = [
                'pair_no' => null,
                'in' => $record,
                'out' => null
            ];
        }

        if ($record['action_type'] == 'OUT') {

            if ($current_visit !== null) {
                $current_visit['out'] = $record;
                $current_visit['pair_no'] = $pair_no;
                $user['visits'][] = $current_visit;

                $current_visit = null;
                $pair_no++;
            } else {
                $user['visits'][] = [
                    'pair_no' => $pair_no,
                    'in' => null,
                    'out' => $record
                ];

                $pair_no++;
            }
        }
    }

    if ($current_visit !== null) {
        $current_visit['pair_no'] = $pair_no;
        $user['visits'][] = $current_visit;
    }
}

unset($user);
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
        <p>Each officer's IN and OUT visits are paired and shown on their own map.</p>
    </div>

    <?php foreach ($users as $user_id => $user) { ?>

        <div class="user-location-card">

            <h3 class="user-title"><?php echo $user['name']; ?></h3>

            <?php if (empty($user['visits'])) { ?>

                <p>No IN or OUT records yet.</p>

            <?php } else { ?>

                <?php foreach ($user['visits'] as $visit) { ?>

                    <div class="visit-pair-card">

                        <h4>Visit Pair <?php echo $visit['pair_no']; ?></h4>

                        <div class="in-out-wrapper">

                            <div class="in-details-box">
                                <h4>IN Details</h4>

                                <?php if (!empty($visit['in'])) { ?>
                                    <span class="status in-status">IN</span>

                                    <p><strong>Date:</strong> <?php echo date("Y-m-d", strtotime($visit['in']['created_at'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($visit['in']['created_at'])); ?></p>
                                    <p><strong>Location:</strong> <?php echo $visit['in']['latitude'] . ", " . $visit['in']['longitude']; ?></p>

                                    <?php if (!empty($visit['in']['photo_path'])) { ?>
                                        <img class="record-photo" src="<?php echo $visit['in']['photo_path']; ?>" alt="IN Photo">
                                    <?php } else { ?>
                                        <p>No Photo Uploaded</p>
                                    <?php } ?>

                                <?php } else { ?>
                                    <p>No IN record for this pair.</p>
                                <?php } ?>
                            </div>

                            <div class="out-details-box">
                                <h4>OUT Details</h4>

                                <?php if (!empty($visit['out'])) { ?>
                                    <span class="status out-status">OUT</span>

                                    <p><strong>Date:</strong> <?php echo date("Y-m-d", strtotime($visit['out']['created_at'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo date("h:i A", strtotime($visit['out']['created_at'])); ?></p>
                                    <p><strong>Location:</strong> <?php echo $visit['out']['latitude'] . ", " . $visit['out']['longitude']; ?></p>

                                    <?php if (!empty($visit['out']['photo_path'])) { ?>
                                        <img class="record-photo" src="<?php echo $visit['out']['photo_path']; ?>" alt="OUT Photo">
                                    <?php } else { ?>
                                        <p>No Photo Uploaded</p>
                                    <?php } ?>

                                <?php } else { ?>
                                    <p>No OUT record yet for this pair.</p>
                                <?php } ?>
                            </div>

                        </div>

                    </div>

                <?php } ?>

                <div id="user-map-<?php echo $user_id; ?>" class="user-map"></div>

            <?php } ?>

        </div>

    <?php } ?>

</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const usersMapData = <?php echo json_encode($users); ?>;

Object.keys(usersMapData).forEach(userId => {
    const user = usersMapData[userId];
    const points = user.points;

    if (!points || points.length === 0) {
        return;
    }

    const mapId = "user-map-" + userId;

    const map = L.map(mapId);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let bounds = [];

    points.forEach(point => {
        const lat = parseFloat(point.latitude);
        const lng = parseFloat(point.longitude);

        if (!isNaN(lat) && !isNaN(lng)) {
            bounds.push([lat, lng]);

            L.marker([lat, lng])
                .addTo(map)
                .bindPopup(
                    `<strong>${user.name}</strong><br>
                     <strong>${point.action_type}</strong><br>
                     ${point.created_at}<br>
                     Lat: ${point.latitude}<br>
                     Lng: ${point.longitude}`
                );
        }
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 17);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, {
            padding: [30, 30]
        });
    }
});
</script>

</body>
</html>