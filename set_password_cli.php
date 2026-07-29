<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(
        'This file can only be run from the command line.'
    );
}

require_once 'db.php';

echo 'Username: ';

$username = trim(
    fgets(STDIN)
);

echo 'New password: ';

$password = rtrim(
    fgets(STDIN),
    "\r\n"
);

echo 'Confirm password: ';

$confirmPassword = rtrim(
    fgets(STDIN),
    "\r\n"
);

if ($username === '') {
    exit("Username is required.\n");
}

if (strlen($password) < 8) {
    exit(
        "Password must contain at least 8 characters.\n"
    );
}

if ($password !== $confirmPassword) {
    exit("Passwords do not match.\n");
}

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$sql = "
    UPDATE users
    SET password = ?
    WHERE username = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    exit("Unable to prepare password update.\n");
}

mysqli_stmt_bind_param(
    $stmt,
    'ss',
    $passwordHash,
    $username
);

mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) === 1) {
    echo "Password updated successfully.\n";
} else {
    echo "User was not found or the password was unchanged.\n";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);