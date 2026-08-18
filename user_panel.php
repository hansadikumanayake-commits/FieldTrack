<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['field_officer']);

$userId = currentUserId();
$name = currentDisplayName();
$username = currentUsername();

$lastStmt = $conn->prepare(
    "SELECT action_type, created_at
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);
$lastStmt->bind_param('i', $userId);
$lastStmt->execute();
$last = $lastStmt->get_result()->fetch_assoc();
$lastStmt->close();

$lastAction = $last['action_type'] ?? null;
$nextAction = $lastAction === 'IN' ? 'OUT' : 'IN';
$lastActionTime = $last['created_at'] ?? null;

$todayStmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, is_locked, created_at
     FROM attendance_events
     WHERE user_id = ?
     AND DATE(created_at) = CURDATE()
     ORDER BY created_at ASC, id ASC"
);
$todayStmt->bind_param('i', $userId);
$todayStmt->execute();
$todayResult = $todayStmt->get_result();
$todayRecords = [];
while ($row = $todayResult->fetch_assoc()) {
    $todayRecords[] = $row;
}
$todayStmt->close();

$todayIn = null;
$todayOut = null;
foreach ($todayRecords as $row) {
    if ($row['action_type'] === 'IN' && $todayIn === null) {
        $todayIn = $row['created_at'];
    }
    if ($row['action_type'] === 'OUT') {
        $todayOut = $row['created_at'];
    }
}

$recentStmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, is_locked, created_at
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 40"
);
$recentStmt->bind_param('i', $userId);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
$recentRecords = [];
while ($row = $recentResult->fetch_assoc()) {
    $recentRecords[] = $row;
}
$recentStmt->close();

$weeks = [];
$currentMonday = new DateTimeImmutable('monday this week');

for ($offset = 0; $offset < 6; $offset++) {
    $start = $currentMonday->modify('-' . $offset . ' week');
    $weekStart = $start->format('Y-m-d');
    $weekEnd = $start->modify('+6 days')->format('Y-m-d');
    $submission = getWeeklySubmission($conn, $userId, $weekStart);
    $completeness = getWeekCompleteness($conn, $userId, $weekStart, $weekEnd);

    $weeks[] = [
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'submission' => $submission,
        'record_count' => countWeekRecords($conn, $userId, $weekStart, $weekEnd),
        'week_finished' => $weekEnd < date('Y-m-d'),
        'completeness' => $completeness,
    ];
}

$notificationStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM weekly_submissions
     WHERE field_officer_id = ?
     AND status IN ('admin_officer_rejected','manager_rejected','returned_for_correction')"
);
$notificationStmt->bind_param('i', $userId);
$notificationStmt->execute();
$notificationCount = (int) ($notificationStmt->get_result()->fetch_assoc()['total'] ?? 0);
$notificationStmt->close();

$message = trim((string) ($_GET['msg'] ?? ''));
$mapRecords = array_map(
    static fn(array $row): array => [
        'action_type' => (string) $row['action_type'],
        'latitude' => (float) $row['latitude'],
        'longitude' => (float) $row['longitude'],
        'time' => date('h:i A', strtotime((string) $row['created_at'])),
    ],
    $todayRecords
);
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

    <?php if ($notificationCount > 0): ?>
        <div class="notification-box">🔔 <?= $notificationCount ?> rejected/returned weekly submission(s) need your attention.</div>
    <?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card"><span>Current Status</span><strong><?= $lastAction === 'IN' ? 'IN' : 'OUT' ?></strong></div>
        <div class="summary-card"><span>Next Action</span><strong><?= h($nextAction) ?></strong></div>
        <div class="summary-card"><span>Today's IN</span><strong><?= $todayIn ? h(date('h:i A', strtotime((string) $todayIn))) : '—' ?></strong></div>
        <div class="summary-card"><span>Today's OUT</span><strong><?= $todayOut ? h(date('h:i A', strtotime((string) $todayOut))) : '—' ?></strong></div>
    </section>

    <form id="attendanceForm" action="<?= h(appUrl('mark_attendance.php')) ?>" method="POST">
        <?= csrfInput() ?>
        <section class="card attendance-card">
            <h2>Mark Attendance</h2>
            <p class="muted">Click the valid action. FieldTrack captures your current GPS location automatically.</p>
            <input type="hidden" name="latitude" id="latInput">
            <input type="hidden" name="longitude" id="lonInput">
            <input type="hidden" name="action_type" id="actionInput">

            <div class="attendance-actions">
                <button type="button" class="in-btn" id="inBtn" <?= $nextAction !== 'IN' ? 'disabled' : '' ?>>Mark IN</button>
                <button type="button" class="out-btn" id="outBtn" <?= $nextAction !== 'OUT' ? 'disabled' : '' ?>>Mark OUT</button>
            </div>
            <p id="locationStatus" class="location-status">Location will be captured automatically.</p>
            <?php if ($lastActionTime): ?>
                <p class="muted">Last action: <?= h((string) $lastAction) ?> at <?= h(date('d M Y h:i A', strtotime((string) $lastActionTime))) ?></p>
            <?php endif; ?>
        </section>
    </form>

    <section class="card">
        <h2>Weekly Attendance</h2>
        <p class="muted">Monday-Friday must each contain at least one IN and one OUT before a completed week can be submitted.</p>

        <div class="week-list">
        <?php foreach ($weeks as $week): ?>
            <?php
            $submission = $week['submission'];
            $status = $submission['status'] ?? 'draft';
            $complete = (bool) $week['completeness']['is_complete'];
            ?>
            <div class="week-card">
                <div class="week-main">
                    <h3><?= h(date('d M Y', strtotime($week['week_start']))) ?> — <?= h(date('d M Y', strtotime($week['week_end']))) ?></h3>
                    <p><?= (int) $week['record_count'] ?> records • <?= (int) $week['completeness']['complete_days'] ?>/<?= (int) $week['completeness']['required_days'] ?> required days complete</p>
                    <span class="week-status"><?= $submission ? h(getWeekStatusLabel((string) $status)) : 'Not Submitted' ?></span>
                    <span class="completeness-badge <?= $complete ? 'complete' : 'incomplete' ?>"><?= $complete ? '✓ Complete' : '⚠ Incomplete' ?></span>

                    <div class="day-status-grid">
                    <?php foreach ($week['completeness']['days'] as $date => $day): ?>
                        <?php
                        $required = in_array((int) $day['weekday'], FIELDTRACK_REQUIRED_WEEKDAYS, true);
                        $dayComplete = $day['in'] && $day['out'];
                        ?>
                        <div class="day-status <?= $dayComplete ? 'day-ok' : ($required ? 'day-missing' : 'day-optional') ?>">
                            <strong><?= h(substr((string) $day['label'], 0, 3)) ?></strong>
                            <span><?= $dayComplete ? '✓ Complete' : ($required ? '⚠ Missing' : 'Optional') ?></span>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <?php if (!$complete && $week['week_finished']): ?>
                        <div class="missing-box">
                            <strong>Missing attendance:</strong>
                            <ul>
                            <?php foreach ($week['completeness']['missing'] as $missing): ?>
                                <li><?= h($missing) ?></li>
                            <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($submission && !empty($submission['latest_rejection_reason'])): ?>
                        <div class="rejection-reason"><strong>Rejection reason:</strong> <?= h((string) $submission['latest_rejection_reason']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="week-actions">
                    <?php if ($submission === null && $week['week_finished'] && $complete): ?>
                        <form action="<?= h(appUrl('submit_week.php')) ?>" method="POST" onsubmit="return confirm('Submit this completed week? The attendance records will be locked.');">
                            <?= csrfInput() ?>
                            <input type="hidden" name="week_start" value="<?= h($week['week_start']) ?>">
                            <button type="submit">Submit Week</button>
                        </form>
                    <?php elseif ($submission !== null && isResubmittable($submission)): ?>
                        <form action="<?= h(appUrl('resubmit_week.php')) ?>" method="POST" onsubmit="return confirm('Resubmit this corrected week?');">
                            <?= csrfInput() ?>
                            <input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
                            <button type="submit">Resubmit Week</button>
                        </form>
                    <?php elseif (!$week['week_finished']): ?>
                        <span class="muted">Current week — submit after Sunday</span>
                    <?php elseif (!$complete): ?>
                        <span class="muted">Complete missing IN/OUT records before submission.</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>Today's Route</h2>
        <?php if (!$todayRecords): ?>
            <p class="muted">No attendance locations recorded today.</p>
        <?php else: ?>
            <div id="todayRecordsMap"></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Recent Attendance Records</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Action</th><th>Date / Time</th><th>Latitude</th><th>Longitude</th><th>Locked</th></tr></thead>
                <tbody>
                <?php foreach ($recentRecords as $record): ?>
                    <tr>
                        <td><strong><?= h($record['action_type']) ?></strong></td>
                        <td><?= h(date('d/m/Y h:i A', strtotime((string) $record['created_at']))) ?></td>
                        <td><?= h($record['latitude']) ?></td>
                        <td><?= h($record['longitude']) ?></td>
                        <td><?= (int) $record['is_locked'] === 1 ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentRecords): ?><tr><td colspan="5">No attendance records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const form = document.getElementById('attendanceForm');
const actionInput = document.getElementById('actionInput');
const latInput = document.getElementById('latInput');
const lonInput = document.getElementById('lonInput');
const statusText = document.getElementById('locationStatus');
const inBtn = document.getElementById('inBtn');
const outBtn = document.getElementById('outBtn');

function setBusy(busy) {
    if (busy) {
        inBtn.disabled = true;
        outBtn.disabled = true;
    } else {
        inBtn.disabled = <?= $nextAction !== 'IN' ? 'true' : 'false' ?>;
        outBtn.disabled = <?= $nextAction !== 'OUT' ? 'true' : 'false' ?>;
    }
}

function markAttendance(action) {
    if (!navigator.geolocation) {
        alert('This browser does not support location access.');
        return;
    }

    actionInput.value = action;
    setBusy(true);
    statusText.textContent = 'Getting your current GPS location...';

    navigator.geolocation.getCurrentPosition(
        function(position) {
            latInput.value = Number(position.coords.latitude).toFixed(8);
            lonInput.value = Number(position.coords.longitude).toFixed(8);
            statusText.textContent = 'Location found ✓ Saving attendance...';
            form.submit();
        },
        function(error) {
            setBusy(false);
            if (error.code === error.PERMISSION_DENIED) {
                statusText.textContent = 'Location permission denied.';
                alert('Please allow location access to mark attendance.');
            } else if (error.code === error.TIMEOUT) {
                statusText.textContent = 'Location request timed out.';
                alert('Location request timed out. Please try again.');
            } else {
                statusText.textContent = 'Current location is unavailable.';
                alert('Current location could not be captured. Please try again.');
            }
        },
        {enableHighAccuracy: true, timeout: 15000, maximumAge: 0}
    );
}

inBtn.addEventListener('click', () => markAttendance('IN'));
outBtn.addEventListener('click', () => markAttendance('OUT'));

const locations = <?= json_encode($mapRecords, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
if (locations.length > 0) {
    const map = L.map('todayRecordsMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const bounds = [];
    const route = [];
    locations.forEach((item, index) => {
        const point = [Number(item.latitude), Number(item.longitude)];
        bounds.push(point);
        route.push(point);
        L.marker(point).addTo(map).bindPopup('<strong>' + (index + 1) + '. ' + item.action_type + '</strong><br>' + item.time);
    });
    if (route.length > 1) L.polyline(route, {weight: 4, opacity: 0.75}).addTo(map);
    if (bounds.length === 1) map.setView(bounds[0], 16);
    else map.fitBounds(bounds, {padding: [35, 35]});
}
</script>
</body>
</html>