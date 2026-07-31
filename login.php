<?php

declare(strict_types=1);

require_once 'auth.php';

if (isLoggedIn()) {
    redirectToDashboard();
}

$message = '';

if (isset($_GET['session']) && $_GET['session'] === 'expired') {
    $message = 'Your session expired. Please log in again.';
}

if (isset($_GET['logout'])) {
    $message = 'You have logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FieldTrack Login</title>
    <style>
        * { box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #4f46e5, #38bdf8);
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            padding: 32px;
            border-radius: 22px;
            box-shadow: 0 25px 60px rgba(15, 23, 42, .25);
        }
        h1 { margin: 0 0 6px; color: #111827; }
        .subtitle { margin: 0 0 24px; color: #6b7280; }
        label { display: block; margin: 14px 0 6px; font-weight: 700; color: #374151; }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            font-size: 15px;
        }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 13px;
            border: 0;
            border-radius: 12px;
            background: #4f46e5;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }
        .message {
            background: #eef2ff;
            color: #3730a3;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
    </style>
</head>
<body>
<div class="login-card">
    <h1>FieldTrack</h1>
    <p class="subtitle">Sign in to continue.</p>

    <?php if ($message !== ''): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form action="login_process.php" method="POST">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" maxlength="100" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <button type="submit">Login</button>
    </form>

    <div class="accounts">
        Local test accounts:<br>
        admin / admin123<br>
        officer / officer123<br>
        kamal / 123<br>
        test / test123
    </div>
</div>
</body>
</html>
