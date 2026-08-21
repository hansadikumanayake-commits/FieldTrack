<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/review_helpers.php';

if (isLoggedIn()) redirectToDashboard();

$message = '';
$messageClass = 'info';
$error = trim((string)($_GET['error'] ?? ''));

if ($error === 'invalid') {
    $message = 'Incorrect username or password.';
    $messageClass = 'error';
} elseif ($error === 'missing') {
    $message = 'Enter both username and password.';
    $messageClass = 'error';
} elseif ($error === 'database') {
    $message = 'Login could not be completed. Check MySQL and the database.';
    $messageClass = 'error';
} elseif ($error === 'role_changed') {
    $message = 'Your role was changed. Please log in again.';
} elseif (isset($_GET['logout'])) {
    $message = 'You have been logged out.';
} elseif (isset($_GET['session'])) {
    $message = 'Your session expired. Please log in again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FieldTrack Login</title>
<link rel="stylesheet" href="<?= h(appUrl('login_style.css')) ?>">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="brand">FieldTrack</div>
        <p class="subtitle">Attendance and weekly approval system</p>

        <?php if ($message !== ''): ?>
            <div class="message <?= h($messageClass) ?>"><?= h($message) ?></div>
        <?php endif; ?>

        <form action="<?= h(appUrl('login_process.php')) ?>" method="POST" autocomplete="on">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" maxlength="100" required autocomplete="username">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            <button type="submit">Login</button>
        </form>
    </div>
</div>
</body>
</html>
