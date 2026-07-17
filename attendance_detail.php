<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'audit_log.php';

requireRole(['admin']);

function escapeDetailValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
 * Validate attendance record ID.
 */
$recordId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    $recordId === false ||
    $recordId === null ||
    $recordId <= 0
) {
    header('Location: admin_panel.php');
    exit();
}

try {
    $stmt = $conn->prepare(
        "SELECT
            attendance_events.id,
            attendance_events.user_id,
            attendance_events.action_type,
            attendance_events.latitude,
            attendance_events.longitude,
            attendance_events.photo_path,
            attendance_events.created_at,
            users.name,
            users.username

         FROM attendance_events

         INNER JOIN users
            ON users.id =
               attendance_events.user_id

         WHERE attendance_events.id = ?

         LIMIT 1"
    );

    $stmt->bind_param(
        'i',
        $recordId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $record = $result->fetch_assoc();

    $stmt->close();
} catch (Throwable $error) {
    error_log(
        'Attendance detail error: ' .
        $error->getMessage()
    );

    http_response_code(500);

    exit(
        'The attendance record could not be loaded.'
    );
}

if (!$record) {
    http_response_code(404);

    exit('Attendance record not found.');
}

/*
 * Record that the administrator viewed
 * this attendance record.
 */
writeAuditLog(
    $conn,
    (int) $_SESSION['user_id'],
    'ATTENDANCE_RECORD_VIEWED',
    'attendance_event',
    $recordId
);

$timestamp = strtotime(
    (string) $record['created_at']
);

$formattedDate = $timestamp !== false
    ? date('d/m/Y h:i A', $timestamp)
    : 'Unknown';

$latitude = filter_var(
    $record['latitude'],
    FILTER_VALIDATE_FLOAT
);

$longitude = filter_var(
    $record['longitude'],
    FILTER_VALIDATE_FLOAT
);

$hasValidLocation =
    $latitude !== false &&
    $longitude !== false &&
    $latitude >= -90 &&
    $latitude <= 90 &&
    $longitude >= -180 &&
    $longitude <= 180;

$attendanceType = in_array(
    $record['action_type'],
    ['IN', 'OUT'],
    true
)
    ? $record['action_type']
    : 'Unknown';

$attendanceClass = strtolower(
    $attendanceType
);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Attendance Details</title>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="admin_style.css"
    >

</head>

<body>

<header class="admin-header">

    <div>

        <h1>Attendance Details</h1>

        <p>
            View the selected officer attendance record.
        </p>

    </div>

    <a
        href="admin_panel.php"
        class="logout-btn"
    >
        Back to Dashboard
    </a>

</header>

<main class="admin-container">

    <section class="admin-section">

        <div class="section-title">

            <div>

                <h2>
                    <?= escapeDetailValue(
                        $record['name']
                    ) ?>
                </h2>

                <p>
                    @<?= escapeDetailValue(
                        $record['username']
                    ) ?>
                </p>

            </div>

            <span
                class="status-badge <?= escapeDetailValue(
                    $attendanceClass
                ) ?>"
            >
                <?= escapeDetailValue(
                    $attendanceType
                ) ?>
            </span>

        </div>

        <div class="attendance-details-grid">

            <div class="attendance-information">

                <div class="detail-row">

                    <span>Record ID</span>

                    <strong>
                        <?= (int) $record['id'] ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Officer</span>

                    <strong>
                        <?= escapeDetailValue(
                            $record['name']
                        ) ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Username</span>

                    <strong>
                        @<?= escapeDetailValue(
                            $record['username']
                        ) ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Attendance Type</span>

                    <strong>
                        <?= escapeDetailValue(
                            $attendanceType
                        ) ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Date and Time</span>

                    <strong>
                        <?= escapeDetailValue(
                            $formattedDate
                        ) ?>
                    </strong>

                </div>

                <div class="detail-row">

                    <span>Latitude</span>

                    <strong>

                        <?php if (
                            $hasValidLocation
                        ): ?>

                            <?= escapeDetailValue(
                                number_format(
                                    (float) $latitude,
                                    8,
                                    '.',
                                    ''
                                )
                            ) ?>

                        <?php else: ?>

                            Not available

                        <?php endif; ?>

                    </strong>

                </div>

                <div class="detail-row">

                    <span>Longitude</span>

                    <strong>

                        <?php if (
                            $hasValidLocation
                        ): ?>

                            <?= escapeDetailValue(
                                number_format(
                                    (float) $longitude,
                                    8,
                                    '.',
                                    ''
                                )
                            ) ?>

                        <?php else: ?>

                            Not available

                        <?php endif; ?>

                    </strong>

                </div>

            </div>

            <div class="attendance-photo">

                <h3>Attendance Photo</h3>

                <?php if (
                    !empty($record['photo_path'])
                ): ?>

                    <a
                        href="<?= escapeDetailValue(
                            $record['photo_path']
                        ) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >

                        <img
                            src="<?= escapeDetailValue(
                                $record['photo_path']
                            ) ?>"
                            alt="Attendance Photo"
                        >

                    </a>

                <?php else: ?>

                    <div class="empty-map-box">
                        No photo was uploaded for this record.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <section class="admin-section">

        <div class="section-title">

            <div>

                <h2>Attendance Location</h2>

                <p>
                    Exact location recorded for this
                    attendance event.
                </p>

            </div>

        </div>

        <?php if ($hasValidLocation): ?>

            <div id="attendance-detail-map"></div>

        <?php else: ?>

            <div class="empty-map-box">
                A valid location was not recorded.
            </div>

        <?php endif; ?>

    </section>

</main>

<?php if ($hasValidLocation): ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
const latitude =
    <?= json_encode((float) $latitude) ?>;

const longitude =
    <?= json_encode((float) $longitude) ?>;

const attendanceType =
    <?= json_encode(
        $attendanceType,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;

const officerName =
    <?= json_encode(
        $record['name'],
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;

const attendanceDate =
    <?= json_encode(
        $formattedDate,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;

function escapeMapHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

const map = L.map(
    "attendance-detail-map"
).setView(
    [latitude, longitude],
    16
);

L.tileLayer(
    "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
    {
        maxZoom: 19,
        attribution:
            "&copy; OpenStreetMap contributors"
    }
).addTo(map);

const popupContent = `
    <div class="map-popup">

        <strong>
            ${escapeMapHtml(officerName)}
        </strong>

        <br>

        ${escapeMapHtml(attendanceType)}

        <br>

        ${escapeMapHtml(attendanceDate)}

        <br>

        ${latitude.toFixed(8)},
        ${longitude.toFixed(8)}

    </div>
`;

L.marker(
    [latitude, longitude]
)
.addTo(map)
.bindPopup(popupContent)
.openPopup();

setTimeout(function () {
    map.invalidateSize();
}, 300);
</script>

<?php endif; ?>

</body>

</html>