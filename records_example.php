<?php

declare(strict_types=1);

/*
 * Optional reusable record list.
 * The photo_path column has been removed, so this file displays
 * only action, date/time and coordinates.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once 'db.php';
}

if (!isset($user_id)) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
}

$stmt = $conn->prepare(
    "SELECT
        action_type,
        latitude,
        longitude,
        created_at

     FROM attendance_events

     WHERE user_id = ?

     ORDER BY created_at DESC, id DESC

     LIMIT 10"
);

if ($stmt === false) {
    throw new RuntimeException(
        'Prepare failed (record list): ' .
        $conn->error
    );
}

$stmt->bind_param('i', $user_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<section class="records">
    <h3>Previous IN / OUT Records</h3>

    <div class="records-grid">
        <?php if ($result->num_rows === 0): ?>
            <p class="empty-records">
                No attendance records yet.
            </p>
        <?php endif; ?>

        <?php while ($row = $result->fetch_assoc()): ?>
            <?php
                $actionClass = strtolower(
                    (string) $row['action_type']
                );
            ?>

            <div class="record-card record-<?= htmlspecialchars($actionClass) ?>">
                <div class="record-top">
                    <span class="badge badge-<?= htmlspecialchars($actionClass) ?>">
                        <?= htmlspecialchars((string) $row['action_type']) ?>
                    </span>

                    <span class="record-time">
                        <?= date(
                            'h:i A',
                            strtotime((string) $row['created_at'])
                        ) ?>
                    </span>
                </div>

                <div class="record-info">
                    <p>
                        📅
                        <?= date(
                            'd/m/Y',
                            strtotime((string) $row['created_at'])
                        ) ?>
                    </p>

                    <p>
                        📍
                        <?= number_format(
                            (float) $row['latitude'],
                            6
                        ) ?>,
                        <?= number_format(
                            (float) $row['longitude'],
                            6
                        ) ?>
                    </p>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</section>

<?php $stmt->close(); ?>
