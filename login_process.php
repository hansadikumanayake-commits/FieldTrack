<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'audit_log.php';

/*
 * Do not use requireRole() here.
 * The user has not logged in yet.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

/*
 * Basic input validation.
 */
if ($username === '' || $password === '') {
    header('Location: login_failed.php');
    exit();
}

/*
 * Get the user using a prepared statement.
 * The password is not included in the SQL query.
 */
$stmt = $conn->prepare(
    "SELECT
        id,
        name,
        username,
        password,
        role
     FROM users
     WHERE username = ?
     LIMIT 1"
);

$stmt->bind_param('s', $username);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

/*
 * Stop when the username does not exist.
 */
if (!$user) {
    header('Location: login_failed.php');
    exit();
}

$storedPassword = (string) $user['password'];
$passwordIsCorrect = false;

/*
 * Check whether the database password is already hashed.
 */
$passwordInformation = password_get_info($storedPassword);

if ($passwordInformation['algo'] !== null) {
    /*
     * Secure hashed-password verification.
     */
    $passwordIsCorrect = password_verify(
        $password,
        $storedPassword
    );
} else {
    /*
     * Temporary support for your existing
     * plain-text passwords.
     */
    $passwordIsCorrect = hash_equals(
        $storedPassword,
        $password
    );

    /*
     * Convert the plain-text password into a secure hash
     * after the user successfully logs in.
     */
    if ($passwordIsCorrect) {
        $newPasswordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $updateStmt = $conn->prepare(
            "UPDATE users
             SET password = ?
             WHERE id = ?"
        );

        $userId = (int) $user['id'];

        $updateStmt->bind_param(
            'si',
            $newPasswordHash,
            $userId
        );

        $updateStmt->execute();
        $updateStmt->close();
    }
}

/*
 * Stop when the password is incorrect.
 */
if (!$passwordIsCorrect) {
    header('Location: login_failed.php');
    exit();
}

/*
 * Prevent session fixation attacks.
 */
session_regenerate_id(true);

/*
 * Save the logged-in user information.
 */
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['name'] = $user['name'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['logged_in'] = true;
$_SESSION['last_activity'] = time();

/*
 * Redirect according to role.
 */
if ($user['role'] === 'admin') {
    writeAuditLog(
        $conn,
        (int) $user['id'],
        'ADMIN_LOGIN_SUCCESS'
    );

    header('Location: admin_panel.php');
    exit();
}

if ($user['role'] === 'user') {
    header('Location: user_panel.php');
    exit();
}

/*
 * Block unknown roles.
 */
$_SESSION = [];
session_destroy();

header('Location: login_failed.php');
exit();