<?php

require_once 'auth.php';
require_once 'db.php';

requireRole(['user','admin']);

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

/* Previous records for logged-in user only */
$records_stmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, photo_path, created_at
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC"
);

$records_stmt->bind_param("i", $user_id);
$records_stmt->execute();
$records_result = $records_stmt->get_result();

/* Today's records for route map */
$today_stmt = $conn->prepare(
    "SELECT id, action_type, latitude, longitude, photo_path, created_at
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
        'photo_path' => $today_row['photo_path'],
        'created_at' => date("h:i A", strtotime($today_row['created_at']))
    ];
}

$today_stmt->close();

$message = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'success') {
        $message = "Attendance saved successfully.";
    } elseif ($_GET['msg'] === 'location_required') {
        $message = "Please select your location using current location or by clicking the map.";
    } elseif ($_GET['msg'] === 'invalid_location') {
        $message = "Invalid location details. Please select the location again.";
    } elseif ($_GET['msg'] === 'already_in') {
        $message = "You are already IN. Please mark OUT first.";
    } elseif ($_GET['msg'] === 'already_out') {
        $message = "You are already OUT. Please mark IN first.";
    } elseif ($_GET['msg'] === 'must_start_in') {
        $message = "Your first attendance action must be IN.";
    } elseif ($_GET['msg'] === 'invalid_photo') {
        $message = "Invalid photo type. Please upload JPG, JPEG, PNG, WEBP, or JFIF.";
    } elseif ($_GET['msg'] === 'photo_error') {
        $message = "Photo upload failed. Please try again.";
    } elseif ($_GET['msg'] === 'photo_move_failed') {
        $message = "Photo could not be saved. Check the uploads folder.";
    } elseif ($_GET['msg'] === 'save_failed') {
        $message = "Attendance could not be saved. Please try again.";
    } else {
        $message = "Something went wrong. Please try again.";
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
                <p>Step 1: Select your location. Step 2: Upload a photo of the place. Step 3: Mark your attendance (IN or OUT).</p>
            </div>

            <div class="welcome-emoji">📍</div>
        </section>

        <?php if ($message !== ""): ?>
            <div class="message-box">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form id="attendanceForm" action="mark_attendance.php" method="POST" enctype="multipart/form-data">

            <div class="dashboard-grid">

<section class="card location-card">
                    <h3>Select Location</h3>

                    <p class="location-help">
                        Use GPS, search a place, or click directly on the map to select your location.
                    </p>

                    <button type="button" id="currentLocationBtn" class="current-location-btn">
                        📌 Use Current Location
                    </button>

                    <div class="search-box">
                        <input type="text" id="locationSearch" placeholder="Search a place">
                        <button type="button" id="searchBtn">Search</button>
                    </div>

                    <div id="map"></div>

                    <p>
                        <span class="tag-label">Latitude</span>
                        <span id="latitude">Not selected</span>
                    </p>

                    <p>
                        <span class="tag-label">Longitude</span>
                        <span id="longitude">Not selected</span>
                    </p>

                    <p id="locationStatus" class="status-waiting">
                        Choose location before marking IN or OUT.
                    </p>

                    <input type="hidden" name="latitude" id="latInput">
                    <input type="hidden" name="longitude" id="lonInput">
                </section>

<section class="card photo-section">
                    <h3>Upload Location Photo</h3>

                    <label class="upload-btn">
                        📷 Take Photo
                        <input
                            type="file"
                            name="camera_photo"
                            id="cameraPhotoInput"
                            accept="image/*,.jpg,.jpeg,.png,.webp,.jfif,.JPG,.JPEG,.PNG,.WEBP,.JFIF"
                            capture="environment"
                            onchange="showPhotoPreview(this, 'galleryPhotoInput')"
                            hidden>
                    </label>

                    <label class="upload-btn gallery">
                        🖼 Choose From Gallery
                        <input
                            type="file"
                            name="gallery_photo"
                            id="galleryPhotoInput"
                            accept="image/*,.jpg,.jpeg,.png,.webp,.jfif,.JPG,.JPEG,.PNG,.WEBP,.JFIF"
                            onchange="showPhotoPreview(this, 'cameraPhotoInput')"
                            hidden>
                    </label>

                    <p id="photoFeedback" class="photo-feedback">
                        Photo is optional, but uploaded photos will appear in your records.
                    </p>

                    <div id="photoPreviewBox" class="photo-preview-box">
                        <p>Selected Photo Preview</p>
                        <img id="selectedPhotoPreview" src="" alt="Selected Photo Preview">
                    </div>
                </section>

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

                    <div class="action-buttons">

                        <button
                            type="button"
                            class="action-submit-btn in-submit-btn"
                            onclick="submitAttendance('IN')"
                            <?= $next_action !== 'IN' ? 'disabled' : '' ?>
                        >
                            ✅ Mark IN
                            <span>Start field visit</span>
                        </button>

                        <button
                            type="button"
                            class="action-submit-btn out-submit-btn"
                            onclick="submitAttendance('OUT')"
                            <?= $next_action !== 'OUT' ? 'disabled' : '' ?>
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

                

                

                

            </div>

        </form>

        <section class="records">

            <div class="today-map-card">
                <div class="today-map-header">
                    <div>
                        <h3>Your Today’s Visit Route</h3>
                        <p>All your IN / OUT locations for today are shown together.</p>
                    </div>

                    <span class="location-count">
                        <?= count($today_locations) ?> Location<?= count($today_locations) === 1 ? '' : 's' ?>
                    </span>
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

                        <?php if (!empty($row['photo_path'])): ?>
                            <img src="<?= htmlspecialchars($row['photo_path']) ?>" alt="Uploaded Location Photo">
                        <?php else: ?>
                            <div class="no-photo">No photo uploaded</div>
                        <?php endif; ?>

                        <div class="record-info">
                            <p>📅 <?= date("d/m/Y", strtotime($row['created_at'])) ?></p>
                            <p>📍 Latitude: <?= number_format((float) $row['latitude'], 6) ?></p>
                            <p>📍 Longitude: <?= number_format((float) $row['longitude'], 6) ?></p>
                        </div>

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

/* Main map for selecting current attendance location */

const map = L.map("map").setView([7.8731, 80.7718], 7);

L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: "&copy; OpenStreetMap contributors"
}).addTo(map);

let marker = null;

function setSelectedLocation(lat, lng, message) {
    lat = Number(lat);
    lng = Number(lng);

    document.getElementById("latitude").textContent = lat.toFixed(6);
    document.getElementById("longitude").textContent = lng.toFixed(6);

    document.getElementById("latInput").value = lat;
    document.getElementById("lonInput").value = lng;

    document.getElementById("locationStatus").textContent = message;

    if (marker) {
        map.removeLayer(marker);
    }

    marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup("Selected Location").openPopup();

    map.setView([lat, lng], 16);
}

/* Manual map click */

map.on("click", function (e) {
    setSelectedLocation(
        e.latlng.lat,
        e.latlng.lng,
        "Location selected manually from map."
    );
});

/* Use current location */

document.getElementById("currentLocationBtn").addEventListener("click", function () {
    if (!navigator.geolocation) {
        document.getElementById("locationStatus").textContent =
            "Current location is not supported by this browser.";
        return;
    }

    document.getElementById("locationStatus").textContent = "Getting current location...";

    navigator.geolocation.getCurrentPosition(
        function (position) {
            setSelectedLocation(
                position.coords.latitude,
                position.coords.longitude,
                "Current location selected."
            );
        },
        function () {
            document.getElementById("locationStatus").textContent =
                "Location permission denied. Please click the map manually.";
        }
    );
});

/* Search location */

document.getElementById("searchBtn").addEventListener("click", function () {
    const searchValue = document.getElementById("locationSearch").value.trim();

    if (searchValue === "") {
        alert("Please enter a place name.");
        return;
    }

    fetch("https://nominatim.openstreetmap.org/search?format=json&q=" + encodeURIComponent(searchValue))
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (!data.length) {
                document.getElementById("locationStatus").textContent =
                    "Place not found. Try another name or click on the map.";
                return;
            }

            const lat = data[0].lat;
            const lon = data[0].lon;

            setSelectedLocation(lat, lon, "Location selected from search.");
        })
        .catch(function () {
            document.getElementById("locationStatus").textContent =
                "Search failed. Please click the map manually.";
        });
});

/* Submit IN or OUT */

function submitAttendance(actionType) {
    const lat = document.getElementById("latInput").value;
    const lon = document.getElementById("lonInput").value;

    if (lat === "" || lon === "") {
        alert("Please select your location first. You can click the map or use current location.");
        return;
    }

    document.getElementById("actionTypeInput").value = actionType;
    document.getElementById("attendanceForm").submit();
}

/* Show selected photo preview */

function showPhotoPreview(input, otherInputId) {
    const otherInput = document.getElementById(otherInputId);

    if (otherInput) {
        otherInput.value = "";
    }

    const previewBox = document.getElementById("photoPreviewBox");
    const previewImage = document.getElementById("selectedPhotoPreview");
    const photoFeedback = document.getElementById("photoFeedback");

    if (input.files && input.files[0]) {
        const file = input.files[0];

        photoFeedback.textContent = "Selected photo: " + file.name;

        const reader = new FileReader();

        reader.onload = function (e) {
            previewImage.src = e.target.result;
            previewBox.style.display = "block";
        };

        reader.readAsDataURL(file);
    }
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
            '<p>Lng: ' + lng.toFixed(6) + '</p>';

        if (location.photo_path) {
            popupContent +=
                '<img src="' + location.photo_path + '" class="map-popup-photo" alt="Record Photo">';
        }

        popupContent += '</div>';

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
$conn->close();
?>

</body>
</html>