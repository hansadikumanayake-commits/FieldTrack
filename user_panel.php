<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['field_officer']);

$userId = currentUserId();
$name = currentDisplayName();
$username = currentUsername();

$lastStmt = $conn->prepare(
    "SELECT action_type
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);
$lastStmt->bind_param('i', $userId);
$lastStmt->execute();
$lastRow = $lastStmt->get_result()->fetch_assoc();
$lastStmt->close();

$lastAction = $lastRow['action_type'] ?? null;
$nextAction = $lastAction === 'IN' ? 'OUT' : 'IN';

$recordsStmt = $conn->prepare(
    "SELECT
        id,
        action_type,
        latitude,
        longitude,
        photo_path,
        is_locked,
        created_at
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 60"
);
$recordsStmt->bind_param('i', $userId);
$recordsStmt->execute();
$recordsResult = $recordsStmt->get_result();

$records = [];
while ($row = $recordsResult->fetch_assoc()) {
    $records[] = $row;
}
$recordsStmt->close();

$todayStmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, created_at
     FROM attendance_events
     WHERE user_id = ?
     AND DATE(created_at) = CURDATE()
     ORDER BY created_at ASC, id ASC"
);
$todayStmt->bind_param('i', $userId);
$todayStmt->execute();
$todayResult = $todayStmt->get_result();

$todayLocations = [];
while ($row = $todayResult->fetch_assoc()) {
    $todayLocations[] = [
        'id' => (int) $row['id'],
        'action_type' => (string) $row['action_type'],
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'created_at' => date('h:i A', strtotime((string) $row['created_at'])),
    ];
}
$todayStmt->close();

/*
 * Show the current week plus the previous five weeks.
 * Completed weeks can be submitted. Rejected weeks can be resubmitted.
 */
$weeks = [];
$currentMonday = new DateTimeImmutable('monday this week');

for ($offset = 0; $offset < 6; $offset++) {
    $startObject = $currentMonday->modify('-' . $offset . ' week');
    $weekStart = $startObject->format('Y-m-d');
    $weekEnd = $startObject->modify('+6 days')->format('Y-m-d');

    $submission = getWeeklySubmission($conn, $userId, $weekStart);
    $recordCount = countWeekRecords($conn, $userId, $weekStart, $weekEnd);

    $weeks[] = [
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'submission' => $submission,
        'record_count' => $recordCount,
        'is_complete' => $weekEnd < date('Y-m-d'),
    ];
}

$message = trim((string) ($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FieldTrack - Field Officer</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?= h(appUrl('user_panel.css')) ?>">
</head>
<body>
<div class="page">
    <header class="user-header">
        <div>
            <h1>FieldTrack</h1>
            <p>Field Officer Dashboard — <?= h($name) ?> (@<?= h($username) ?>)</p>
        </div>
        <a class="logout-btn" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </header>

    <main class="user-container">
        <?php if ($message !== ''): ?>
            <div class="message-box"><?= h($message) ?></div>
        <?php endif; ?>

        <section class="summary-grid">
            <div class="summary-card">
                <span>Current status</span>
                <strong><?= $lastAction === 'IN' ? 'IN' : 'OUT' ?></strong>
            </div>
            <div class="summary-card">
                <span>Next allowed action</span>
                <strong><?= h($nextAction) ?></strong>
            </div>
            <div class="summary-card">
                <span>Today's records</span>
                <strong><?= count($todayLocations) ?></strong>
            </div>
        </section>

        <form
            id="attendanceForm"
            action="<?= h(appUrl('mark_attendance.php')) ?>"
            method="POST"
            enctype="multipart/form-data"
        >
            <div class="dashboard-grid">
                <section class="card">
                    <h2>Select Visit Location</h2>
                    <p class="muted">Use GPS, search, or click the map.</p>

                    <div class="location-actions">
                        <button type="button" id="currentLocationBtn">Use Current Location</button>
                        <div class="search-row">
                            <input id="locationSearch" type="text" placeholder="Search a place">
                            <button type="button" id="searchBtn">Search</button>
                        </div>
                    </div>

                    <div id="map"></div>

                    <div class="coordinates">
                        <div><span>Latitude</span><strong id="latitudeText">Not selected</strong></div>
                        <div><span>Longitude</span><strong id="longitudeText">Not selected</strong></div>
                    </div>

                    <input type="hidden" name="latitude" id="latInput">
                    <input type="hidden" name="longitude" id="lonInput">
                    <input type="hidden" name="action_type" id="actionInput">
                </section>

                <section class="card">
                    <h2>Photo Evidence</h2>
                    <p class="muted">Optional. Maximum 5 MB.</p>

                    <input
                        type="file"
                        name="attendance_photo"
                        id="photoInput"
                        accept=".jpg,.jpeg,.png,.webp,.jfif,image/*"
                    >

                    <img id="photoPreview" class="photo-preview" alt="Selected photo preview">

                    <h2 class="attendance-heading">Mark Attendance</h2>

                    <div class="attendance-actions">
                        <button
                            type="button"
                            class="in-btn"
                            id="inBtn"
                            <?= $nextAction !== 'IN' ? 'disabled' : '' ?>
                        >Mark IN</button>

                        <button
                            type="button"
                            class="out-btn"
                            id="outBtn"
                            <?= $nextAction !== 'OUT' ? 'disabled' : '' ?>
                        >Mark OUT</button>
                    </div>

                    <p class="muted">
                        Sequence is always IN → OUT → IN → OUT.
                    </p>
                </section>
            </div>
        </form>

        <section class="card">
            <h2>Weekly Attendance Submission</h2>
            <p class="muted">
                Submit a completed week to your Admin Officer. If rejected, the reason is shown here and the same week can be resubmitted.
            </p>

            <div class="week-list">
                <?php foreach ($weeks as $week): ?>
                    <?php
                    $submission = $week['submission'];
                    $status = $submission['status'] ?? 'draft';
                    ?>
                    <div class="week-card">
                        <div>
                            <h3>
                                <?= h(date('d M Y', strtotime($week['week_start']))) ?>
                                —
                                <?= h(date('d M Y', strtotime($week['week_end']))) ?>
                            </h3>
                            <p><?= (int) $week['record_count'] ?> attendance records</p>

                            <?php if ($submission !== null): ?>
                                <span class="week-status"><?= h(getWeekStatusLabel((string) $status)) ?></span>
                            <?php else: ?>
                                <span class="week-status">Not submitted</span>
                            <?php endif; ?>

                            <?php if (
                                $submission !== null &&
                                !empty($submission['latest_rejection_reason'])
                            ): ?>
                                <div class="rejection-reason">
                                    <strong>Reason:</strong>
                                    <?= h((string) $submission['latest_rejection_reason']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="week-actions">
                            <?php if (
                                $submission === null &&
                                $week['is_complete'] &&
                                (int) $week['record_count'] > 0
                            ): ?>
                                <form
                                    action="<?= h(appUrl('submit_week.php')) ?>"
                                    method="POST"
                                    onsubmit="return confirm('Submit this completed week for approval?');"
                                >
                                    <input type="hidden" name="week_start" value="<?= h($week['week_start']) ?>">
                                    <button type="submit">Submit Week</button>
                                </form>
                            <?php elseif (
                                $submission !== null &&
                                isResubmittable($submission)
                            ): ?>
                                <form
                                    action="<?= h(appUrl('resubmit_week.php')) ?>"
                                    method="POST"
                                    onsubmit="return confirm('Resubmit this rejected week?');"
                                >
                                    <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
                                    <button type="submit">Resubmit Week</button>
                                </form>
                            <?php elseif (!$week['is_complete']): ?>
                                <span class="muted">Current week — submit after Sunday</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <h2>Today's Route</h2>
            <?php if (count($todayLocations) === 0): ?>
                <p class="muted">No attendance locations recorded today.</p>
            <?php else: ?>
                <div id="todayRecordsMap"></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Recent Attendance Records</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Date / Time</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Photo</th>
                        <th>Locked</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($records) === 0): ?>
                        <tr><td colspan="6">No attendance records yet.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><strong><?= h((string) $record['action_type']) ?></strong></td>
                            <td><?= h(date('d/m/Y h:i A', strtotime((string) $record['created_at']))) ?></td>
                            <td><?= h((string) $record['latitude']) ?></td>
                            <td><?= h((string) $record['longitude']) ?></td>
                            <td>
                                <?php if (!empty($record['photo_path'])): ?>
                                    <a target="_blank" href="<?= h(appUrl((string) $record['photo_path'])) ?>">View Photo</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $record['is_locked'] === 1 ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('map').setView([7.8731, 80.7718], 7);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let marker = null;

function setLocation(lat, lng) {
    document.getElementById('latInput').value = lat.toFixed(8);
    document.getElementById('lonInput').value = lng.toFixed(8);
    document.getElementById('latitudeText').textContent = lat.toFixed(6);
    document.getElementById('longitudeText').textContent = lng.toFixed(6);

    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng]).addTo(map);
    }

    map.setView([lat, lng], 16);
}

map.on('click', function (event) {
    setLocation(event.latlng.lat, event.latlng.lng);
});

document.getElementById('currentLocationBtn').addEventListener('click', function () {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by this browser.');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            setLocation(position.coords.latitude, position.coords.longitude);
        },
        function () {
            alert('Could not get your current location. Allow location access or click on the map.');
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
});

document.getElementById('searchBtn').addEventListener('click', function () {
    const query = document.getElementById('locationSearch').value.trim();

    if (!query) {
        return;
    }

    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(results => {
            if (!results.length) {
                alert('Location not found.');
                return;
            }

            setLocation(Number(results[0].lat), Number(results[0].lon));
        })
        .catch(() => alert('Location search failed.'));
});

function submitAttendance(actionType) {
    const lat = document.getElementById('latInput').value;
    const lon = document.getElementById('lonInput').value;

    if (!lat || !lon) {
        alert('Select a location first.');
        return;
    }

    document.getElementById('actionInput').value = actionType;
    document.getElementById('attendanceForm').submit();
}

document.getElementById('inBtn').addEventListener('click', function () {
    submitAttendance('IN');
});

document.getElementById('outBtn').addEventListener('click', function () {
    submitAttendance('OUT');
});

document.getElementById('photoInput').addEventListener('change', function () {
    const preview = document.getElementById('photoPreview');
    const file = this.files && this.files[0];

    if (!file) {
        preview.removeAttribute('src');
        preview.style.display = 'none';
        return;
    }

    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
});

const todayLocations = <?= json_encode(
    $todayLocations,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

if (todayLocations.length > 0) {
    const todayMap = L.map('todayRecordsMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(todayMap);

    const bounds = [];
    const route = [];

    todayLocations.forEach(function (item, index) {
        const point = [Number(item.latitude), Number(item.longitude)];
        bounds.push(point);
        route.push(point);

        L.marker(point)
            .addTo(todayMap)
            .bindPopup(
                '<strong>' + (index + 1) + '. ' + item.action_type + '</strong><br>' +
                'Time: ' + item.created_at
            );
    });

    if (route.length > 1) {
        L.polyline(route, { weight: 4, opacity: 0.75 }).addTo(todayMap);
    }

    if (bounds.length === 1) {
        todayMap.setView(bounds[0], 16);
    } else {
        todayMap.fitBounds(bounds, { padding: [35, 35] });
    }
}
</script>
</body>
</html>
