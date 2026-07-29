<?php

require_once 'session_config.php';

/*
|--------------------------------------------------------------------------
| Redirect users who are already logged in
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['user_id']) &&
    isset($_SESSION['role'])
) {
    if ($_SESSION['role'] === 'field_officer') {
        header('Location: user_panel.php');
        exit();
    }

    header('Location: admin_panel.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve and remove login error
|--------------------------------------------------------------------------
*/

$loginError = $_SESSION['login_error'] ?? '';

unset($_SESSION['login_error']);

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
        href="login_style.css"
    >
</head>

<body>

    <main class="login-page">

        <section class="login-container">

            <div class="login-header">

                <h1>FieldTrack</h1>

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
                action="login_process.php"
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

        </section>

    </main>

</body>

</html>