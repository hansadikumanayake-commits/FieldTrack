<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';

requireRole(['field_officer']);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'Field Officer';

function getInitials($fullName) {
    $words = preg_split('/\s+/', trim($fullName));
    $initials = '';

    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'FO';
}

/* Get last action */
$last_action = null;

$last_stmt = $conn->prepare(
    "SELECT action_type
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1"
);

$last_stmt->bind_param("i", $user_id);
$last_stmt->execute();
$last_result = $last_stmt->get_result();
$last_row = $last_result->fetch_assoc();

if ($last_row) {
    $last_action = $last_row['action_type'];
}

$last_stmt->close();

$next_action = ($last_action === 'IN') ? 'OUT' : 'IN';

/* ===============================
   WEEKLY SUBMISSION / APPROVAL WORKFLOW
================================ */

[$week_start, $week_end] = getWeekBounds();

$weekly_submission = getWeeklySubmission($conn, $user_id, $week_start);
$week_status = $weekly_submission['status'] ?? 'draft';
$week_editable = isWeekEditable($weekly_submission);
$week_day_summary = getWeekDaySummary($conn, $user_id, $week_start, $week_end);

/* Approved weekly history */
$approved_history_stmt = $conn->prepare(
    "SELECT
        week_start,
        week_end,
        manager_reviewed_at AS reviewed_at
     FROM weekly_submissions
     WHERE field_officer_id = ?
     AND status = 'final_approved'
     ORDER BY week_start DESC"
);
$approved_history_stmt->bind_param("i", $user_id);
$approved_history_stmt->execute();
$approved_history_result = $approved_history_stmt->get_result();

/* Previous records for logged-in user only */
$records_stmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, created_at
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC"
);

$records_stmt->bind_param("i", $user_id);
$records_stmt->execute();
$records_result = $records_stmt->get_result();

/* Today's records for route map */
$today_stmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, created_at
     FROM attendance_events
     WHERE user_id = ?
     AND DATE(created_at) = CURDATE()
     ORDER BY created_at ASC, id ASC"
);

$today_stmt->bind_param("i", $user_id);
$today_stmt->execute();
$today_result = $today_stmt->get_result();

$today_locations = [];

while ($today_row = $today_result->fetch_assoc()) {
    $today_locations[] = [
        'id' => (int) $today_row['id'],
        'action_type' => $today_row['action_type'],
        'latitude' => (float) $today_row['latitude'],
        'longitude' => (float) $today_row['longitude'],
        'created_at' => date("h:i A", strtotime($today_row['created_at']))
    ];
}

$today_stmt->close();

$message = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'success') {
        $message = "Attendance saved successfully.";
    } elseif ($_GET['msg'] === 'location_required') {
        $message = "Could not get your location. Please allow location access and try again.";
    } elseif ($_GET['msg'] === 'invalid_location') {
        $message = "Invalid location details. Please try again.";
    } elseif ($_GET['msg'] === 'already_in') {
        $message = "You are already IN. Please mark OUT first.";
    } elseif ($_GET['msg'] === 'already_out') {
        $message = "You are already OUT. Please mark IN first.";
    } elseif ($_GET['msg'] === 'must_start_in') {
        $message = "Your first attendance action must be IN.";
    } elseif ($_GET['msg'] === 'save_failed') {
        $message = "Attendance could not be saved. Please try again.";
    } elseif ($_GET['msg'] === 'week_locked') {
        $message = "This week is locked because your attendance has already been submitted for approval.";
    } elseif ($_GET['msg'] === 'week_submitted') {
        $message = "Your weekly attendance has been submitted successfully.";
    } elseif ($_GET['msg'] === 'week_resubmitted') {
        $message = "Your corrected weekly attendance has been resubmitted for approval.";
    } elseif ($_GET['msg'] === 'week_already_submitted') {
        $message = "This week has already been submitted and can no longer be resubmitted here.";
    } elseif ($_GET['msg'] === 'week_empty') {
        $message = "You have no attendance records for this week yet, so there is nothing to submit.";
    } elseif ($_GET['msg'] === 'week_not_finished') {
        $message = "You can submit weekly attendance only after the week is completed.";
    } elseif ($_GET['msg'] === 'no_assignment') {
        $message = "Your Admin Officer and Admin Manager assignment has not been configured yet.";
    } elseif ($_GET['msg'] === 'nothing_to_resubmit') {
        $message = "There is no correction pending for this week.";
    } elseif ($_GET['msg'] === 'week_submit_failed' || $_GET['msg'] === 'week_resubmit_failed') {
        $message = "Your weekly attendance could not be submitted. Please try again.";
    } else {
        $message = "Something went wrong. Please try again.";
    }
}

/* Does this week have any day missing an IN or OUT (up to today only)? */
$today_date = date('Y-m-d');
$has_missing_day = false;
$first_missing_label = null;

foreach ($week_day_summary as $day => $flags) {
    if ($day > $today_date) {
        continue; // future days aren't missing yet
    }

    $isComplete = $flags['in'] && $flags['out'];
    $isEmpty = !$flags['in'] && !$flags['out'];
    $isToday = ($day === $today_date);

    if (!$isComplete && !($isEmpty && $isToday)) {
        $has_missing_day = true;

        if ($first_missing_label === null) {
            $dayName = date('l', strtotime($day));

            if ($flags['in'] && !$flags['out']) {
                $first_missing_label = "$dayName's OUT attendance is missing";
            } elseif (!$flags['in'] && $flags['out']) {
                $first_missing_label = "$dayName's IN attendance is missing";
            } else {
                $first_missing_label = "$dayName has no attendance recorded";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FieldTrack User Panel</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="user_panel.css">
</head>
<body>

<div class="page active">
    <div class="dash-container">

        <header>
            <div class="header-left">
                <h1>FieldTrack</h1>
                <p>Field Officer Dashboard</p>
            </div>

            <div class="header-right">
                <span class="date-pill"><?= date("d/m/Y") ?></span>
                <div class="avatar"><?= htmlspecialchars(getInitials($name)) ?></div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>

        <section class="welcome">
            <div>
                <h2>Welcome, <?= htmlspecialchars($name) ?></h2>
                <p>Tap IN or OUT — your current location is captured automatically.</p>
            </div>

            <div class="welcome-emoji">📍</div>
        </section>

        <?php if ($message !== ""): ?>
            <div class="message-box">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">

            <!-- ===============================
                 WEEKLY ATTENDANCE STATUS CARD
            ================================ -->
            <section class="card weekly-card">
                <div class="weekly-header">
                    <h3>Current Weekly Attendance</h3>
                    <span class="status-badge <?= htmlspecialchars(getWeekStatusClass($week_status)) ?>">
                        <?= htmlspecialchars(getWeekStatusLabel($week_status)) ?>
                    </span>
                </div>

                <p class="weekly-range">
                    Week: <?= date('d M', strtotime($week_start)) ?> – <?= date('d M Y', strtotime($week_end)) ?>
                </p>

                <div class="weekly-day-list">
                    <?php foreach ($week_day_summary as $day => $flags): ?>
                        <?php
                            $isComplete = $flags['in'] && $flags['out'];
                            $isEmpty = !$flags['in'] && !$flags['out'];
                            $isFuture = $day > $today_date;
                            $isToday = $day === $today_date;

                            if ($isFuture) {
                                $dayClass = 'day-future';
                                $dayText = 'Upcoming';
                            } elseif ($isComplete) {
                                $dayClass = 'day-complete';
                                $dayText = 'Complete';
                            } elseif ($flags['in'] && !$flags['out']) {
                                $dayClass = $isToday ? 'day-pending' : 'day-warning';
                                $dayText = $isToday ? 'OUT pending' : 'Missing OUT';
                            } elseif (!$flags['in'] && $flags['out']) {
                                $dayClass = 'day-warning';
                                $dayText = 'Missing IN';
                            } elseif ($isEmpty && $isToday) {
                                $dayClass = 'day-pending';
                                $dayText = 'Not marked yet';
                            } else {
                                $dayClass = 'day-warning';
                                $dayText = 'No record';
                            }
                        ?>
                        <div class="weekly-day-item <?= $dayClass ?>">
                            <span class="weekly-day-name"><?= date('D d M', strtotime($day)) ?></span>
                            <span class="weekly-day-status"><?= htmlspecialchars($dayText) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($has_missing_day && in_array($week_status, ['draft', 'returned_for_correction', 'admin_officer_rejected', 'manager_rejected'], true)): ?>
                    <p class="weekly-warning-note">
                        ⚠️ <?= htmlspecialchars(ucfirst($first_missing_label)) ?>.
                    </p>
                <?php endif; ?>

                <?php if (in_array($week_status, ['admin_officer_rejected', 'manager_rejected', 'returned_for_correction'], true) && !empty($weekly_submission['rejection_reason'])): ?>
                    <div class="rejection-box">
                        <strong>Reason:</strong>
                        <?= htmlspecialchars($weekly_submission['rejection_reason']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($week_status === 'draft'): ?>
                    <form action="submit_week.php" method="POST" onsubmit="return confirm('Submit this week\'s attendance for approval? You will not be able to add or change attendance for this week afterwards.');">
                        <button type="submit" class="weekly-action-btn submit-week-btn">
                            📤 Submit Weekly Attendance
                        </button>
                    </form>
                <?php elseif (in_array($week_status, ['returned_for_correction', 'admin_officer_rejected', 'manager_rejected'], true)): ?>
                    <p class="weekly-help-note">
                        Correct the flagged day(s) above using the Mark Attendance section, then resubmit.
                    </p>
                    <form action="resubmit_week.php" method="POST" onsubmit="return confirm('Resubmit this week\'s corrected attendance for approval?');">
                        <button type="submit" class="weekly-action-btn resubmit-week-btn">
                            🔁 Resubmit Week
                        </button>
                    </form>
                <?php elseif ($week_status === 'submitted'): ?>
                    <p class="weekly-help-note">Waiting for the Admin Officer to review. You'll see updates here as it progresses.</p>
                <?php elseif ($week_status === 'resubmitted'): ?>
                    <p class="weekly-help-note">Your corrected attendance has been resubmitted and is waiting for review.</p>
                <?php elseif ($week_status === 'admin_officer_approved'): ?>
                    <p class="weekly-help-note">Approved by the Admin Officer — moving to manager review.</p>
                <?php elseif ($week_status === 'pending_manager_review'): ?>
                    <p class="weekly-help-note">Pending final approval from the Manager.</p>
                <?php elseif ($week_status === 'final_approved'): ?>
                    <p class="weekly-help-note">✅ This week has been fully approved and is now locked.</p>
                <?php endif; ?>
            </section>

            <form id="attendanceForm" action="mark_attendance.php" method="POST">

                <input type="hidden" name="latitude" id="latInput">
                <input type="hidden" name="longitude" id="lonInput">

                <section class="card attendance-card">
                    <h3>Mark Attendance</h3>

                    <p class="current-status">
                        Current Status:
                        <?php if ($last_action === 'IN'): ?>
                            <strong class="status-in">IN</strong>
                        <?php elseif ($last_action === 'OUT'): ?>
                            <strong class="status-out">OUT</strong>
                        <?php else: ?>
                            <strong class="status-none">Not marked yet</strong>
                        <?php endif; ?>
                    </p>

                    <p class="next-action">
                        Next allowed action:
                        <strong><?= htmlspecialchars($next_action) ?></strong>
                    </p>

                    <?php if (!$week_editable): ?>
                        <p class="week-locked-note">
                            🔒 This week is locked (status: <?= htmlspecialchars(getWeekStatusLabel($week_status)) ?>) and cannot be edited here.
                        </p>
                    <?php endif; ?>

                    <div class="action-buttons">

                        <button
                            type="button"
                            id="inBtn"
                            class="action-submit-btn in-submit-btn"
                            onclick="submitAttendance('IN', this)"
                            data-disabled-by-status="<?= ($next_action !== 'IN' || !$week_editable) ? 'true' : 'false' ?>"
                            <?= ($next_action !== 'IN' || !$week_editable) ? 'disabled' : '' ?>
                        >
                            ✅ Mark IN
                            <span>Start field visit</span>
                        </button>

                        <button
                            type="button"
                            id="outBtn"
                            class="action-submit-btn out-submit-btn"
                            onclick="submitAttendance('OUT', this)"
                            data-disabled-by-status="<?= ($next_action !== 'OUT' || !$week_editable) ? 'true' : 'false' ?>"
                            <?= ($next_action !== 'OUT' || !$week_editable) ? 'disabled' : '' ?>
                        >
                            🚪 Mark OUT
                            <span>End field visit</span>
                        </button>

                    </div>

                    <p class="sequence-note">
                        The system allows only this sequence: IN → OUT → IN → OUT.
                    </p>

                    <input type="hidden" name="action_type" id="actionTypeInput">
                </section>

            </form>

        </div>

        <section class="records">

            <div class="today-map-card">
                <div class="today-map-header">
                    <div>
                        <h3>Your Today’s Visit Route</h3>
                        <p>All your IN / OUT locations for today are shown together.</p>
                    </div>

                    <span class="location-count"><?= count($today_locations) ?> location(s) today</span>
                </div>

                <?php if (count($today_locations) === 0): ?>

                    <p class="empty-records">No locations marked today yet.</p>

                <?php else: ?>

                    <div class="today-map-layout">

                        <div class="today-map-box">
                            <div id="todayRecordsMap"></div>
                        </div>

                        <div class="today-location-list">
                            <?php foreach ($today_locations as $index => $location): ?>
                                <?php $actionClass = strtolower($location['action_type']); ?>

                                <div class="today-location-item">
                                    <div class="today-number <?= htmlspecialchars($actionClass) ?>">
                                        <?= $index + 1 ?>
                                    </div>

                                    <div class="today-location-details">
                                        <span class="today-badge <?= htmlspecialchars($actionClass) ?>">
                                            <?= htmlspecialchars($location['action_type']) ?>
                                        </span>

                                        <p class="today-time">
                                            <?= htmlspecialchars($location['created_at']) ?>
                                        </p>

                                        <p>
                                            Lat: <?= number_format((float) $location['latitude'], 6) ?>
                                        </p>

                                        <p>
                                            Lng: <?= number_format((float) $location['longitude'], 6) ?>
                                        </p>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        </div>

                    </div>

                <?php endif; ?>
            </div>

            <h3 class="previous-heading">Your Previous IN / OUT Records</h3>

            <div class="records-grid">

                <?php if ($records_result->num_rows === 0): ?>
                    <p class="empty-records">No attendance records yet.</p>
                <?php endif; ?>

                <?php while ($row = $records_result->fetch_assoc()): ?>
                    <?php $actionClass = strtolower($row['action_type']); ?>

                    <div class="record-card record-<?= htmlspecialchars($actionClass) ?>">

                        <div class="record-top">
                            <span class="badge badge-<?= htmlspecialchars($actionClass) ?>">
                                <?= htmlspecialchars($row['action_type']) ?>
                            </span>

                            <span class="record-time">
                                <?= date("h:i A", strtotime($row['created_at'])) ?>
                            </span>
                        </div>

                        <div class="record-info">
                            <p>📅 <?= date("d/m/Y", strtotime($row['created_at'])) ?></p>
                            <p>📍 Latitude: <?= number_format((float) $row['latitude'], 6) ?></p>
                            <p>📍 Longitude: <?= number_format((float) $row['longitude'], 6) ?></p>
                        </div>

                    </div>

                <?php endwhile; ?>

            </div>

            <h3 class="previous-heading">Approved Weekly History</h3>

            <div class="approved-history-list">
                <?php if ($approved_history_result->num_rows === 0): ?>
                    <p class="empty-records">No approved weeks yet.</p>
                <?php endif; ?>

                <?php while ($history_row = $approved_history_result->fetch_assoc()): ?>
                    <div class="approved-history-item">
                        <span class="approved-history-range">
                            <?= date('d M', strtotime($history_row['week_start'])) ?> – <?= date('d M Y', strtotime($history_row['week_end'])) ?>
                        </span>
                        <span class="approved-history-badge">Final Approved</span>
                        <?php if (!empty($history_row['reviewed_at'])): ?>
                            <span class="approved-history-date">
                                Approved on <?= date('d/m/Y', strtotime($history_row['reviewed_at'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>

        </section>

    </div>
</div>

<button id="goTopBtn" title="Go to top">↑</button>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const todayLocations = <?= json_encode($today_locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

const goTopBtn = document.getElementById("goTopBtn");

window.onscroll = function () {
    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
        goTopBtn.classList.add("show");
    } else {
        goTopBtn.classList.remove("show");
    }
};

goTopBtn.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: "smooth" });
});

/* Submit IN or OUT: current GPS location is captured automatically */

const inBtn = document.getElementById("inBtn");
const outBtn = document.getElementById("outBtn");

function setButtonsBusy(isBusy) {
    if (inBtn) inBtn.disabled = isBusy || inBtn.dataset.disabledByStatus === "true";
    if (outBtn) outBtn.disabled = isBusy || outBtn.dataset.disabledByStatus === "true";
}

function submitAttendance(actionType, clickedBtn) {
    if (!navigator.geolocation) {
        alert("Your browser does not support location access, so attendance cannot be marked.");
        return;
    }

    const originalLabel = clickedBtn ? clickedBtn.innerHTML : null;

    setButtonsBusy(true);

    if (clickedBtn) {
        clickedBtn.innerHTML = "📍 Getting location...";
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {
            document.getElementById("latInput").value = position.coords.latitude;
            document.getElementById("lonInput").value = position.coords.longitude;
            document.getElementById("actionTypeInput").value = actionType;
            document.getElementById("attendanceForm").submit();
        },
        function () {
            setButtonsBusy(false);

            if (clickedBtn && originalLabel !== null) {
                clickedBtn.innerHTML = originalLabel;
            }

            alert("Location permission is required to mark attendance. Please allow location access and try again.");
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}

/* Today's route map */

if (todayLocations.length > 0) {
    const todayMap = L.map("todayRecordsMap", {
        scrollWheelZoom: true
    });

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(todayMap);

    const bounds = [];
    const routePoints = [];

    function createNumberIcon(number, actionType) {
        const markerClass = actionType === "IN" ? "custom-marker-in" : "custom-marker-out";

        return L.divIcon({
            className: "custom-number-marker",
            html: '<div class="' + markerClass + '"><span>' + number + '</span></div>',
            iconSize: [34, 34],
            iconAnchor: [17, 34],
            popupAnchor: [0, -34]
        });
    }

    todayLocations.forEach(function (location, index) {
        const lat = Number(location.latitude);
        const lng = Number(location.longitude);

        if (isNaN(lat) || isNaN(lng)) {
            return;
        }

        const number = index + 1;
        const actionType = location.action_type;

        let popupContent =
            '<div class="custom-popup">' +
            '<strong>' + number + '. ' + actionType + '</strong>' +
            '<p>Time: ' + location.created_at + '</p>' +
            '<p>Lat: ' + lat.toFixed(6) + '</p>' +
            '<p>Lng: ' + lng.toFixed(6) + '</p>' +
            '</div>';

        L.marker([lat, lng], {
            icon: createNumberIcon(number, actionType)
        }).addTo(todayMap).bindPopup(popupContent);

        bounds.push([lat, lng]);
        routePoints.push([lat, lng]);
    });

    if (routePoints.length > 1) {
        L.polyline(routePoints, {
            weight: 4,
            opacity: 0.8,
            dashArray: "8, 8"
        }).addTo(todayMap);
    }

    if (bounds.length === 1) {
        todayMap.setView(bounds[0], 16);
    } else if (bounds.length > 1) {
        todayMap.fitBounds(bounds, {
            padding: [60, 60],
            maxZoom: 15
        });
    }

    setTimeout(function () {
        todayMap.invalidateSize();
    }, 300);
}
</script>

<?php
$records_stmt->close();
$approved_history_stmt->close();
$conn->close();
?>

</body>
</html>