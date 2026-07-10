<?php
session_start();
include "db.php";

$officer=[];

$officer_sql="
            SELECT id,name,username
            FROM users
            WHERE role='user'
            ORDER BY name ASC
            ";

$officers_result=mysqli_query($conn,$officers_sql);

if(! $officers_result){
    die("Officer query failed:".mysqli_error($conn));
}
while($officer=mysqli_fetch_assoc($officers_result)){
    $officers[]=$officer;
}

$selected_user=isset($_GET['user_id'])
? trim($_GET['user_id'])
: '';

$date_range = isset($_GET['date_range'])
    ? trim($_GET['date_range'])
    : 'all';


$action_type = isset($_GET['action_type'])
    ? trim($_GET['action_type'])
    : '';

$photo_filter = isset($_GET['photo_filter'])
    ? trim($_GET['photo_filter'])
    : '';

$from_date = isset($_GET['from_date'])
    ? trim($_GET['from_date'])
    : '';

$to_date = isset($_GET['to_date'])
    ? trim($_GET['to_date'])
    : '';

$from_time = isset($_GET['from_time'])
    ? trim($_GET['from_time'])
    : '';

$to_time = isset($_GET['to_time'])
    ? trim($_GET['to_time'])
    : '';


if ($selected_user !== '' && !ctype_digit($selected_user)) {
    $selected_user = '';
}

$allowed_date_ranges = [
    'all',
    'today',
    'yesterday',
    'last_7_days',
    'last_30_days',
    'this_month',
    'custom'
];

if (!in_array($date_range, $allowed_date_ranges, true)) {
    $date_range = 'all';
}

if (!in_array($action_type, ['', 'IN', 'OUT'], true)) {
    $action_type = '';
}

if (!in_array(
    $photo_filter,
    ['', 'with_photo', 'without_photo'],
    true
)) {
    $photo_filter = '';
}


$conditions = [
    "users.role = 'user'"
];

if ($selected_user !== '') {
    $selected_user_id = (int) $selected_user;

    $conditions[] =
        "attendance_events.user_id = $selected_user_id";
}

if ($action_type === 'IN') {
    $conditions[] =
        "attendance_events.action_type = 'IN'";
}

if ($action_type === 'OUT') {
    $conditions[] =
        "attendance_events.action_type = 'OUT'";
}

if ($photo_filter === 'with_photo') {
    $conditions[] = "
        attendance_events.photo_path IS NOT NULL
        AND attendance_events.photo_path != ''
    ";
}

if ($photo_filter === 'without_photo') {
    $conditions[] = "
        (
            attendance_events.photo_path IS NULL
            OR attendance_events.photo_path = ''
        )
    ";
}





if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$total_officers = 0;
$today_in = 0;
$today_out = 0;
$total_records = 0;

$total_officers_sql = "SELECT COUNT(*) AS total_officers FROM users WHERE role='user'";
$total_officers_result = mysqli_query($conn, $total_officers_sql);

if ($total_officers_result) {
    $row = mysqli_fetch_assoc($total_officers_result);
    $total_officers = $row['total_officers'];
}

$today_in_sql = "SELECT COUNT(*) AS today_in 
                 FROM attendance_events 
                 WHERE action_type='IN' AND DATE(created_at)=CURDATE()";
$today_in_result = mysqli_query($conn, $today_in_sql);

if ($today_in_result) {
    $row = mysqli_fetch_assoc($today_in_result);
    $today_in = $row['today_in'];
}

$today_out_sql = "SELECT COUNT(*) AS today_out 
                  FROM attendance_events 
                  WHERE action_type='OUT' AND DATE(created_at)=CURDATE()";
$today_out_result = mysqli_query($conn, $today_out_sql);

if ($today_out_result) {
    $row = mysqli_fetch_assoc($today_out_result);
    $today_out = $row['today_out'];
}

$total_records_sql = "SELECT COUNT(*) AS total_records FROM attendance_events";
$total_records_result = mysqli_query($conn, $total_records_sql);

if ($total_records_result) {
    $row = mysqli_fetch_assoc($total_records_result);
    $total_records = $row['total_records'];
}

/* =========================
   Recent Activity Table
========================= */

$recent_records_sql = "
    SELECT 
        attendance_events.id,
        attendance_events.action_type,
        attendance_events.latitude,
        attendance_events.longitude,
        attendance_events.photo_path,
        attendance_events.created_at,
        users.name
    FROM attendance_events
    JOIN users ON attendance_events.user_id = users.id
    ORDER BY attendance_events.created_at DESC, attendance_events.id DESC
    LIMIT 10
";

$recent_records_result = mysqli_query($conn, $recent_records_sql);

/* =========================
   Officer-wise Records
========================= */

$records_sql = "
    SELECT 
        users.id AS user_id,
        users.name,
        users.username,
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
    ORDER BY users.name ASC, users.id ASC, attendance_events.created_at ASC, attendance_events.id ASC
";

$records_result = mysqli_query($conn, $records_sql);

$users = [];

if ($records_result) {
    while ($row = mysqli_fetch_assoc($records_result)) {
        $user_id = $row['user_id'];

        if (!isset($users[$user_id])) {
            $users[$user_id] = [
                'id' => $user_id,
                'name' => $row['name'],
                'username' => $row['username'],
                'records' => [],
                'visits' => []
            ];
        }

        if (!empty($row['event_id'])) {
            $users[$user_id]['records'][] = [
                'id' => (int) $row['event_id'],
                'action_type' => $row['action_type'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'photo_path' => $row['photo_path'],
                'created_at' => $row['created_at']
            ];
        }
    }
}

/* =========================
   Pair IN and OUT Records
========================= */

foreach ($users as $userId => $userData) {
    $visits = [];
    $currentVisit = null;
    $pairNo = 1;

    foreach ($userData['records'] as $record) {
        if ($record['action_type'] === 'IN') {
            if ($currentVisit !== null) {
                $currentVisit['pair_no'] = $pairNo;
                $visits[] = $currentVisit;
                $pairNo++;
            }

            $currentVisit = [
                'pair_no' => $pairNo,
                'in' => $record,
                'out' => null
            ];
        }

        if ($record['action_type'] === 'OUT') {
            if ($currentVisit !== null && $currentVisit['out'] === null) {
                $currentVisit['out'] = $record;
                $currentVisit['pair_no'] = $pairNo;
                $visits[] = $currentVisit;
                $currentVisit = null;
                $pairNo++;
            } else {
                $visits[] = [
                    'pair_no' => $pairNo,
                    'in' => null,
                    'out' => $record
                ];
                $pairNo++;
            }
        }
    }

    if ($currentVisit !== null) {
        $currentVisit['pair_no'] = $pairNo;
        $visits[] = $currentVisit;
    }

    $users[$userId]['visits'] = $visits;
}

function formatDateTime($dateTime) {
    if (!$dateTime) {
        return "-";
    }

    return date("d/m/Y h:i A", strtotime($dateTime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FieldTrack Admin Panel</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>

<header class="admin-header">
    <div>
        <h1>FieldTrack Admin Panel</h1>
        <p>Monitor field officers, IN / OUT records, photos, and locations.</p>
    </div>

    <a href="logout.php" class="logout-btn">Logout</a>
</header>

<main class="admin-container">

    <section class="summary-grid">

        <div class="summary-card">
            <h3>Total Officers</h3>
            <p><?= htmlspecialchars($total_officers) ?></p>
        </div>

        <div class="summary-card">
            <h3>Today IN</h3>
            <p><?= htmlspecialchars($today_in) ?></p>
        </div>

        <div class="summary-card">
            <h3>Today OUT</h3>
            <p><?= htmlspecialchars($today_out) ?></p>
        </div>

        <div class="summary-card">
            <h3>Total Records</h3>
            <p><?= htmlspecialchars($total_records) ?></p>
        </div>

    </section>

    <section class="admin-section">
        <div class="section-title">
            <h2>Recent Attendance Records</h2>
            <p>Latest IN / OUT activities from all field officers.</p>
        </div>

        <div class="table-wrapper">
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Officer</th>
                        <th>Action</th>
                        <th>Date & Time</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Photo</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($recent_records_result && mysqli_num_rows($recent_records_result) > 0): ?>
                        <?php while ($record = mysqli_fetch_assoc($recent_records_result)): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['name']) ?></td>

                                <td>
                                    <span class="status-badge <?= strtolower(htmlspecialchars($record['action_type'])) ?>">
                                        <?= htmlspecialchars($record['action_type']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars(formatDateTime($record['created_at'])) ?></td>
                                <td><?= htmlspecialchars($record['latitude']) ?></td>
                                <td><?= htmlspecialchars($record['longitude']) ?></td>

                                <td>
                                    <?php if (!empty($record['photo_path'])): ?>
                                        <a href="<?= htmlspecialchars($record['photo_path']) ?>" target="_blank" class="photo-link">
                                            View Photo
                                        </a>
                                    <?php else: ?>
                                        <span class="no-photo-text">No photo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No attendance records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="section-title">
            <h2>Officer Location Maps</h2>
            <p>Each IN / OUT pair is grouped by one color on the map.</p>
        </div>

        <div class="officer-map-grid">

            <?php foreach ($users as $user): ?>

                <div class="officer-card">

                    <div class="officer-card-header">
                        <div>
                            <h3><?= htmlspecialchars($user['name']) ?></h3>
                            <p>@<?= htmlspecialchars($user['username']) ?></p>
                        </div>

                        <span class="visit-count">
                            <?= count($user['visits']) ?> Visit<?= count($user['visits']) === 1 ? '' : 's' ?>
                        </span>
                    </div>

                    <?php if (count($user['visits']) > 0): ?>

                        <div id="user-map-<?= htmlspecialchars($user['id']) ?>" class="user-map"></div>

                        <div class="visit-list">

                            <?php foreach ($user['visits'] as $visit): ?>

                                <div class="visit-pair-card">

                                    <h4>Visit <?= htmlspecialchars($visit['pair_no']) ?></h4>

                                    <div class="visit-details-grid">

                                        <div class="visit-detail-box in-box">
                                            <h5>IN</h5>

                                            <?php if (!empty($visit['in'])): ?>
                                                <p><strong>Time:</strong> <?= htmlspecialchars(formatDateTime($visit['in']['created_at'])) ?></p>
                                                <p><strong>Latitude:</strong> <?= htmlspecialchars($visit['in']['latitude']) ?></p>
                                                <p><strong>Longitude:</strong> <?= htmlspecialchars($visit['in']['longitude']) ?></p>

                                                <?php if (!empty($visit['in']['photo_path'])): ?>
                                                    <div class="visit-photo-box">
                                                        <img src="<?= htmlspecialchars($visit['in']['photo_path']) ?>" alt="IN Photo">
                                                        <a href="<?= htmlspecialchars($visit['in']['photo_path']) ?>" target="_blank">
                                                            View Full IN Photo
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span>No IN photo</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p>No IN record</p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="visit-detail-box out-box">
                                            <h5>OUT</h5>

                                            <?php if (!empty($visit['out'])): ?>
                                                <p><strong>Time:</strong> <?= htmlspecialchars(formatDateTime($visit['out']['created_at'])) ?></p>
                                                <p><strong>Latitude:</strong> <?= htmlspecialchars($visit['out']['latitude']) ?></p>
                                                <p><strong>Longitude:</strong> <?= htmlspecialchars($visit['out']['longitude']) ?></p>

                                                <?php if (!empty($visit['out']['photo_path'])): ?>
                                                    <div class="visit-photo-box">
                                                        <img src="<?= htmlspecialchars($visit['out']['photo_path']) ?>" alt="OUT Photo">
                                                        <a href="<?= htmlspecialchars($visit['out']['photo_path']) ?>" target="_blank">
                                                            View Full OUT Photo
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span>No OUT photo</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p>No OUT record yet</p>
                                            <?php endif; ?>
                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-map-box">
                            No IN or OUT records yet for this officer.
                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>
    </section>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const usersMapData = <?php echo json_encode($users, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

const pairColors = [
    "#2563eb",
    "#16a34a",
    "#e11d48",
    "#7c3aed",
    "#f59e0b",
    "#06b6d4",
    "#db2777",
    "#0f766e"
];

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return "";
    }

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function createPairIcon(type, color) {
    return L.divIcon({
        className: "admin-custom-marker",
        html: `
            <div class="admin-marker-pin" style="background:${color}">
                <span>${type}</span>
            </div>
        `,
        iconSize: [46, 46],
        iconAnchor: [23, 46],
        popupAnchor: [0, -42]
    });
}

function buildPopup(userName, pairNo, type, record, color) {
    let photoHtml = "";

    if (record.photo_path) {
        photoHtml = `
            <img src="${escapeHtml(record.photo_path)}" class="map-popup-photo" alt="${type} Photo">
        `;
    }

    return `
        <div class="map-popup">
            <div class="popup-title" style="border-left-color:${color}">
                <strong>${escapeHtml(type)} - Pair ${escapeHtml(pairNo)}</strong>
                <span>${escapeHtml(userName)}</span>
            </div>

            <p><b>Time:</b> ${escapeHtml(record.created_at)}</p>
            <p><b>Latitude:</b> ${escapeHtml(record.latitude)}</p>
            <p><b>Longitude:</b> ${escapeHtml(record.longitude)}</p>

            ${photoHtml}
        </div>
    `;
}

Object.keys(usersMapData).forEach(userId => {
    const user = usersMapData[userId];
    const visits = user.visits;

    if (!visits || visits.length === 0) {
        return;
    }

    const mapId = "user-map-" + userId;
    const mapElement = document.getElementById(mapId);

    if (!mapElement) {
        return;
    }

    const map = L.map(mapId, {
        scrollWheelZoom: true
    });

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map);

    const bounds = [];

    visits.forEach((visit, index) => {
        const pairColor = pairColors[index % pairColors.length];
        const pairPoints = [];

        if (visit.in && visit.in.latitude && visit.in.longitude) {
            const inLat = parseFloat(visit.in.latitude);
            const inLng = parseFloat(visit.in.longitude);

            if (!isNaN(inLat) && !isNaN(inLng)) {
                L.marker([inLat, inLng], {
                    icon: createPairIcon("IN", pairColor)
                })
                .addTo(map)
                .bindPopup(buildPopup(user.name, visit.pair_no, "IN", visit.in, pairColor));

                pairPoints.push([inLat, inLng]);
                bounds.push([inLat, inLng]);
            }
        }

        if (visit.out && visit.out.latitude && visit.out.longitude) {
            const outLat = parseFloat(visit.out.latitude);
            const outLng = parseFloat(visit.out.longitude);

            if (!isNaN(outLat) && !isNaN(outLng)) {
                L.marker([outLat, outLng], {
                    icon: createPairIcon("OUT", pairColor)
                })
                .addTo(map)
                .bindPopup(buildPopup(user.name, visit.pair_no, "OUT", visit.out, pairColor));

                pairPoints.push([outLat, outLng]);
                bounds.push([outLat, outLng]);
            }
        }

        if (pairPoints.length === 2) {
            L.polyline(pairPoints, {
                color: pairColor,
                weight: 5,
                opacity: 0.9,
                lineCap: "round"
            }).addTo(map);
        }
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 15);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, {
            padding: [50, 50],
            maxZoom: 15
        });
    } else {
        map.setView([7.8731, 80.7718], 7);
    }

    setTimeout(() => {
        map.invalidateSize();
    }, 300);
});
</script>

</body>
</html>