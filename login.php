<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1.0">
        <title>FieldTrack Login</title>
        <link rel="stylesheet" href="login_style.css">
    </head>
    <body>
        <div class="login-container">
            <div class="login-box">
                <h1>FieldTrack</h1>
                <p>Login to continue</p>

                <?php if (
                     ($_GET['session'] ?? '') === 'expired'): ?>

                <div class="login-error">
                    Your session expired because of inactivity.
                    Please log in again.
                </div>

                    <?php endif; ?>

                <form action="login_process.php" method="POST">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>

                    <button type="submit">Login</button>

                </form>
</div>
</div>
    </body>

</html>