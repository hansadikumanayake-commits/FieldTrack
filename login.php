<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Prevent browser caching
|--------------------------------------------------------------------------
*/

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header('Pragma: no-cache');
header('Expires: 0');

/*
|--------------------------------------------------------------------------
| Redirect users who are already logged in
|--------------------------------------------------------------------------
*/

if (isLoggedIn()) {
    redirectToDashboard();
}

/*
|--------------------------------------------------------------------------
| Safely display values
|--------------------------------------------------------------------------
*/

function loginEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Login messages
|--------------------------------------------------------------------------
*/

$errorMessage = '';
$successMessage = '';

if (
    isset($_SESSION['login_error']) &&
    is_string($_SESSION['login_error'])
) {
    $errorMessage = trim(
        $_SESSION['login_error']
    );

    unset($_SESSION['login_error']);
}

/*
|--------------------------------------------------------------------------
| Session-expired message
|--------------------------------------------------------------------------
*/

$sessionStatus = trim(
    (string) ($_GET['session'] ?? '')
);

if (
    $sessionStatus === 'expired' &&
    $errorMessage === ''
) {
    $errorMessage =
        'Your session has expired. Please log in again.';
}

/*
|--------------------------------------------------------------------------
| Logout-success message
|--------------------------------------------------------------------------
*/

$logoutStatus = trim(
    (string) ($_GET['logout'] ?? '')
);

if ($logoutStatus === 'success') {
    $successMessage =
        'You have logged out successfully.';
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

    <title>Login | FieldTrack</title>

    <link
        rel="stylesheet"
        href="login_style.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #0f172a,
                    #1e3a8a
                );

            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 430px;
        }

        .login-card {
            padding: 34px;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 20px 45px
                rgba(15, 23, 42, 0.3);
        }

        .login-heading {
            margin-bottom: 27px;
            text-align: center;
        }

        .login-heading h1 {
            margin: 0 0 8px;

            color: #0f172a;

            font-size: 32px;
        }

        .login-heading p {
            margin: 0;

            color: #64748b;

            line-height: 1.5;
        }

        .login-message {
            margin-bottom: 18px;
            padding: 13px 15px;

            border-radius: 8px;

            font-size: 14px;
            font-weight: 700;
            line-height: 1.5;
        }

        .login-error {
            border: 1px solid #fca5a5;

            background: #fee2e2;
            color: #991b1b;
        }

        .login-success {
            border: 1px solid #86efac;

            background: #dcfce7;
            color: #166534;
        }

        .login-form-group {
            margin-bottom: 18px;
        }

        .login-form-group label {
            display: block;

            margin-bottom: 7px;

            color: #334155;

            font-size: 14px;
            font-weight: 700;
        }

        .login-form-group input {
            width: 100%;
            padding: 12px 13px;

            border: 1px solid #cbd5e1;
            border-radius: 8px;

            outline: none;

            background: #ffffff;
            color: #0f172a;

            font: inherit;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .login-form-group input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.14);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 78px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 10px;

            transform: translateY(-50%);

            padding: 6px 8px;

            border: 0;
            border-radius: 6px;

            background: transparent;
            color: #2563eb;

            font-size: 13px;
            font-weight: 700;

            cursor: pointer;
        }

        .password-toggle:hover {
            background: #eff6ff;
        }

        .login-button {
            width: 100%;
            min-height: 46px;
            padding: 12px 18px;

            border: 0;
            border-radius: 8px;

            background: #2563eb;
            color: #ffffff;

            font-size: 15px;
            font-weight: 800;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }

        .login-button:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .login-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
        }

        .login-footer {
            margin-top: 22px;

            color: #64748b;

            text-align: center;
            font-size: 13px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 25px 20px;
            }

            .login-heading h1 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>

<main class="login-container">

    <section class="login-card">

        <header class="login-heading">

            <h1>FieldTrack</h1>

            <p>
                Sign in to access your attendance
                and administration dashboard.
            </p>

        </header>

        <?php if ($errorMessage !== ''): ?>

            <div
                class="login-message login-error"
                role="alert"
            >
                <?= loginEscape($errorMessage) ?>
            </div>

        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>

            <div
                class="login-message login-success"
                role="status"
            >
                <?= loginEscape($successMessage) ?>
            </div>

        <?php endif; ?>

        <form
            id="login-form"
            action="login_process.php"
            method="POST"
            autocomplete="on"
        >

            <div class="login-form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    maxlength="100"
                    autocomplete="username"
                    required
                    autofocus
                >

            </div>

            <div class="login-form-group">

                <label for="password">
                    Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        maxlength="255"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        class="password-toggle"
                        id="password-toggle"
                        type="button"
                        aria-controls="password"
                        aria-label="Show password"
                    >
                        Show
                    </button>

                </div>

            </div>

            <button
                class="login-button"
                id="login-button"
                type="submit"
            >
                Login
            </button>

        </form>

        <footer class="login-footer">
            FieldTrack Attendance and Visit Tracking System
        </footer>

    </section>

</main>

<script>
    const passwordInput =
        document.getElementById('password');

    const passwordToggle =
        document.getElementById('password-toggle');

    const loginForm =
        document.getElementById('login-form');

    const loginButton =
        document.getElementById('login-button');

    passwordToggle.addEventListener(
        'click',
        function () {
            const passwordIsHidden =
                passwordInput.type === 'password';

            passwordInput.type =
                passwordIsHidden
                    ? 'text'
                    : 'password';

            passwordToggle.textContent =
                passwordIsHidden
                    ? 'Hide'
                    : 'Show';

            passwordToggle.setAttribute(
                'aria-label',
                passwordIsHidden
                    ? 'Hide password'
                    : 'Show password'
            );
        }
    );

    loginForm.addEventListener(
        'submit',
        function () {
            loginButton.disabled = true;
            loginButton.textContent = 'Signing in...';
        }
    );
</script>

</body>

</html>