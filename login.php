<?php

declare(strict_types=1);

require_once __DIR__ . '/session_config.php';

/*
|--------------------------------------------------------------------------
| Redirect already logged-in users
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role'])
) {
    $role = (string) $_SESSION['role'];

    if ($role === 'field_officer') {
        header('Location: /FieldTrack/user_panel.php');
        exit();
    }

    if ($role === 'admin_officer') {
        header('Location: /FieldTrack/admin_officer_panel.php');
        exit();
    }

    if ($role === 'admin_manager') {
        header('Location: /FieldTrack/admin_manager_panel.php');
        exit();
    }

    if ($role === 'system_admin') {
        header('Location: /FieldTrack/admin_panel.php');
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Login error
|--------------------------------------------------------------------------
*/

$loginError = '';

if (
    isset($_SESSION['login_error']) &&
    $_SESSION['login_error'] !== ''
) {
    $loginError = (string) $_SESSION['login_error'];

    unset($_SESSION['login_error']);
}

/*
|--------------------------------------------------------------------------
| Session expired message
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['session']) &&
    $_GET['session'] === 'expired'
) {
    $loginError =
        'Your session expired because of inactivity. Please log in again.';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>FieldTrack Login</title>

    <link
        rel="stylesheet"
        href="/FieldTrack/login_style.css"
    >

</head>

<body>

<main class="login-page">

    <section class="login-container">

        <div class="login-header">

            <div class="logo-circle">
                FT
            </div>

            <h1>
                FieldTrack
            </h1>

            <p>
                Attendance and Field Visit Tracking System
            </p>

        </div>

        <?php if ($loginError !== ''): ?>

            <div
                class="error-message"
                role="alert"
            >
                <?= htmlspecialchars(
                    $loginError,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form
            action="/FieldTrack/login_process.php"
            method="POST"
            class="login-form"
            autocomplete="on"
        >

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    maxlength="100"
                    autocomplete="username"
                    required
                    autofocus
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    maxlength="255"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button
                type="submit"
                class="login-button"
            >
                Login
            </button>

        </form>

        <p class="login-footer">
            FieldTrack Attendance Management System
        </p>

    </section>

</main>

</body>

</html>