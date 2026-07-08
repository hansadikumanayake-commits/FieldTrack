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

                <form action="login_process.php" method="POST">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>

                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>


                </form>
</div>
</div>
    </body>

</html>