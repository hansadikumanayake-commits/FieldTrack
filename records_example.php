<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['system_admin']);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $fieldOfficerId = filter_input(INPUT_POST, 'field_officer_id', FILTER_VALIDATE_INT);
    $weekStart = trim((string)($_POST['week_start'] ?? ''));

    if (!$fieldOfficerId || !isValidWeekStart($weekStart)) {
        $message = 'Choose a valid Field Officer and a Monday date.';
    } else {
        [, $weekEnd] = getWeekBounds($weekStart);

        if ($weekEnd >= date('Y-m-d')) {
            $message = 'Choose a completed past week.';
        } else {
            try {
                $conn->begin_transaction();

                $delete = $conn->prepare(
                    "DELETE FROM attendance_events
                     WHERE user_id = ?
                     AND DATE(created_at) BETWEEN ? AND ?
                     AND is_locked = 0"
                );
                $delete->bind_param('iss', $fieldOfficerId, $weekStart, $weekEnd);
                $delete->execute();
                $delete->close();

                $insert = $conn->prepare(
                    "INSERT INTO attendance_events
                        (user_id, action_type, latitude, longitude, is_locked, created_at)
                     VALUES (?, ?, ?, ?, 0, ?)"
                );

                $base = new DateTimeImmutable($weekStart);

                for ($day = 0; $day < 5; $day++) {
                    $date = $base->modify('+' . $day . ' days')->format('Y-m-d');

                    $inAction = 'IN';
                    $inLat = 6.9271 + ($day * 0.002);
                    $inLon = 79.8612 + ($day * 0.002);
                    $inTime = $date . ' 08:30:00';
                    $insert->bind_param('isdds', $fieldOfficerId, $inAction, $inLat, $inLon, $inTime);
                    $insert->execute();

                    $outAction = 'OUT';
                    $outLat = $inLat + 0.001;
                    $outLon = $inLon + 0.001;
                    $outTime = $date . ' 16:30:00';
                    $insert->bind_param('isdds', $fieldOfficerId, $outAction, $outLat, $outLon, $outTime);
                    $insert->execute();
                }

                $insert->close();

                $actorId = currentUserId();
                $details = 'Created 10 demo attendance records for Field Officer #' .
                    $fieldOfficerId . ' for week ' . $weekStart . ' to ' . $weekEnd;
                $ip = getClientIpAddress();

                $audit = $conn->prepare(
                    "INSERT INTO audit_logs
                        (user_id, action, target_type, target_id, details, ip_address)
                     VALUES (?, 'DEMO_ATTENDANCE_CREATED', 'user', ?, ?, ?)"
                );
                $audit->bind_param('iiss', $actorId, $fieldOfficerId, $details, $ip);
                $audit->execute();
                $audit->close();

                $conn->commit();
                $message = '10 demo attendance records were created successfully.';
            } catch (Throwable $e) {
                try { $conn->rollback(); } catch (Throwable) {}
                error_log('Demo attendance error: ' . $e->getMessage());
                $message = 'Demo attendance records could not be created.';
            }
        }
    }
}

$officers = [];
$result = $conn->query(
    "SELECT u.id, u.name, u.username
     FROM users u
     INNER JOIN user_roles ur ON ur.user_id = u.id
     INNER JOIN roles r ON r.id = ur.role_id
     WHERE r.role_name = 'field_officer'
     AND u.is_active = 1
     ORDER BY u.name"
);
while ($row = $result->fetch_assoc()) $officers[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Demo Attendance</title>
<link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
<div><h1>FieldTrack</h1><p>Create Demo Attendance Records</p></div>
<div class="topbar-links">
<a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a>
<a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
</div>
</header>

<main class="container">
<?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>

<section class="panel">
<h2>Generate a Completed Demo Week</h2>
<p>Creates IN and OUT records for Monday to Friday. Use this only for local demonstrations.</p>

<form method="POST" class="form-grid">
<?= csrfInput() ?>

<div>
<label for="field_officer_id">Field Officer</label>
<select id="field_officer_id" name="field_officer_id" required>
<?php foreach ($officers as $officer): ?>
<option value="<?= (int)$officer['id'] ?>">
<?= h($officer['name']) ?> (@<?= h($officer['username']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>

<div>
<label for="week_start">Week Start (Monday)</label>
<input id="week_start" type="date" name="week_start" required>
</div>

<div class="form-actions">
<button class="approve-button" type="submit">Create Demo Week</button>
</div>
</form>
</section>
</main>
</body>
</html>