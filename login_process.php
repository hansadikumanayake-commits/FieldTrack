<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'audit_log.php';

/*
 * Do not use requireRole() here.
 * The person has not logged in yet.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$username = trim(
    (string) ($_POST['username'] ?? '')
);

$password = (string) (
    $_POST['password'] ?? ''
);

if ($username === '' || $password === '') {
    header('Location: login_failed.php');
    exit();
}

try {
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

    $stmt->bind_param(
        's',
        $username
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $user = $result->fetch_assoc();

    $stmt->close();
} catch (Throwable $error) {
    error_log(
        'Login database error: ' .
        $error->getMessage()
    );

    header('Location: login_failed.php');
    exit();
}

/*
 * Username does not exist.
 */
if (!$user) {
    header('Location: login_failed.php');
    exit();
}

$storedPassword =
    (string) $user['password'];

$passwordInformation =
    password_get_info($storedPassword);

$passwordIsHashed =
    ($passwordInformation['algoName'] ?? 'unknown')
    !== 'unknown';

$passwordIsCorrect = false;

/*
 * Check an already hashed password.
 */
if ($passwordIsHashed) {
    $passwordIsCorrect = password_verify(
        $password,
        $storedPassword
    );

    /*
     * Update the password hash when PHP
     * recommends a newer format.
     */
    if (
        $passwordIsCorrect &&
        password_needs_rehash(
            $storedPassword,
            PASSWORD_DEFAULT
        )
    ) {
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
} else {
    /*
     * Temporary support for old plain-text
     * passwords.
     */
    $passwordIsCorrect = hash_equals(
        $storedPassword,
        $password
    );

    /*
     * Automatically convert the plain-text
     * password to a secure hash.
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
 * Incorrect password.
 */
if (!$passwordIsCorrect) {
    header('Location: login_failed.php');
    exit();
}

/*
 * Prevent session fixation.
 */
session_regenerate_id(true);

/*
 * Store logged-in user information.
 */
$_SESSION['user_id'] =
    (int) $user['id'];

$_SESSION['name'] =
    (string) $user['name'];

$_SESSION['username'] =
    (string) $user['username'];

$_SESSION['role'] =
    (string) $user['role'];

$_SESSION['logged_in'] = true;

$_SESSION['last_activity'] = time();

/*
 * Admin login.
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

/*
 * Field officer login.
 */
if ($user['role'] === 'user') {
    header('Location: user_panel.php');
    exit();
}

/*
 * Block an unknown role.
 */
$_SESSION = [];

session_destroy();

header('Location: login_failed.php');
exit();