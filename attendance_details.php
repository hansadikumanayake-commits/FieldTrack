<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin']);

$recordId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($recordId === false || $recordId === null || $recordId < 1) {
    redirectTo('admin_panel.php');
}

$stmt = $conn->prepare(
    "SELECT
        ae.*,
        u.name,
        u.username
     FROM attendance_events ae
     INNER JOIN users u ON u.id = ae.user_id
     WHERE ae.id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $recordId);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    http_response_code(404);
    exit('Attendance record not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Details</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
    <style>#detailMap{height:420px;border-radius:12px;background:#e2e8f0}</style>
</head>
<body>
<header class="topbar">
    <div>
        <h1>FieldTrack</h1>
        <p>Attendance Record #<?= (int) $recordId ?></p>
    </div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('admin_panel.php')) ?>">Back</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <section class="details-card">
        <h2>Attendance Information</h2>

        <div class="detail-grid">
            <div class="detail-item"><span>Officer</span><strong><?= h($record['name']) ?> (@<?= h($record['username']) ?>)</strong></div>
            <div class="detail-item"><span>Action</span><strong><?= h($record['action_type']) ?></strong></div>
            <div class="detail-item"><span>Date / Time</span><strong><?= h(formatDateTimeValue($record['created_at'])) ?></strong></div>
            <div class="detail-item"><span>Latitude</span><strong><?= h($record['latitude']) ?></strong></div>
            <div class="detail-item"><span>Longitude</span><strong><?= h($record['longitude']) ?></strong></div>
            <div class="detail-item"><span>Locked</span><strong><?= (int) $record['is_locked'] === 1 ? 'Yes' : 'No' ?></strong></div>
        </div>

        <?php if (!empty($record['photo_path'])): ?>
            <div class="actions">
                <a class="open-button" target="_blank" href="<?= h(appUrl((string) $record['photo_path'])) ?>">View Photo</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="details-card">
        <h2>Recorded Location</h2>
        <div id="detailMap"></div>
    </section>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const lat = <?= json_encode((float) $record['latitude']) ?>;
const lng = <?= json_encode((float) $record['longitude']) ?>;

const map = L.map('detailMap').setView([lat, lng], 16);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

L.marker([lat, lng]).addTo(map).bindPopup('<?= h($record['action_type']) ?>').openPopup();
</script>
</body>
</html>
