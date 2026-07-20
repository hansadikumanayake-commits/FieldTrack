<?php
// records_example.php
// This is a reusable example for showing only the logged-in user's previous records.
// You can include this file inside user_panel.php after $user_id is available.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    include "db.php";
}

if (!isset($user_id)) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    $user_id = (int) $_SESSION['user_id'];
}

$stmt = $conn->prepare(
    "SELECT action_type, latitude, longitude, photo_path, created_at
     FROM attendance_events
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 10"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<section class="records">
    <h3>Previous IN / OUT Records</h3>

    <div class="records-grid">
        <?php if ($result->num_rows === 0): ?>
            <p class="empty-records">No attendance records yet.</p>
        <?php endif; ?>

        <?php while ($row = $result->fetch_assoc()): ?>
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
                    <img src="<?= htmlspecialchars($row['photo_path']) ?>" alt="Location Photo">
                <?php else: ?>
                    <div class="no-photo">No photo uploaded</div>
                <?php endif; ?>

                <div class="record-info">
                    <p>📅 <?= date("d/m/Y", strtotime($row['created_at'])) ?></p>
                    <p>📍 <?= number_format((float) $row['latitude'], 6) ?>, <?= number_format((float) $row['longitude'], 6) ?></p>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</section>

<?php $stmt->close(); ?>
