<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if (
    $username === '' ||
    $password === '' ||
    strlen($username) > 100
) {
    header('Location: login_failed.php');
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT
            u.id,
            u.name,
            u.username,
            u.password,
            r.role_name
         FROM users u
         INNER JOIN user_roles ur
            ON ur.user_id = u.id
         INNER JOIN roles r
            ON r.id = ur.role_id
         WHERE u.username = ?
         AND u.is_active = 1
         ORDER BY FIELD(
            r.role_name,
            'system_admin',
            'admin_manager',
            'admin_officer',
            'field_officer'
         )
         LIMIT 1"
    );

    if ($stmt === false) {
        throw new RuntimeException(
            'Prepare failed: ' . $conn->error
        );
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack login query error: ' .
        $error->getMessage()
    );

    header('Location: login_failed.php');
    exit;
}

$validPassword = false;

if ($user !== null) {
    $storedPassword = (string) $user['password'];
    $passwordInfo = password_get_info($storedPassword);
    $isHash =
        ($passwordInfo['algoName'] ?? 'unknown') !==
        'unknown';

    $validPassword = $isHash
        ? password_verify($password, $storedPassword)
        : hash_equals($storedPassword, $password);
}

if ($user === null || !$validPassword) {
    header('Location: login_failed.php');
    exit;
}

session_regenerate_id(true);

$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['name'] = (string) $user['name'];
$_SESSION['username'] = (string) $user['username'];
$_SESSION['role'] = (string) $user['role_name'];
$_SESSION['last_activity'] = time();

try {
    $ipAddress = substr(
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        0,
        45
    );

    $auditStmt = $conn->prepare(
        "INSERT INTO audit_logs
            (
                user_id,
                action,
                target_type,
                target_id,
                details,
                ip_address
            )
         VALUES
            (
                ?,
                'LOGIN_SUCCESS',
                'authentication',
                ?,
                ?,
                ?
            )"
    );

    if ($auditStmt !== false) {
        $userId = (int) $user['id'];
        $details =
            'Logged in as ' .
            (string) $user['role_name'];

        $auditStmt->bind_param(
            'iiss',
            $userId,
            $userId,
            $details,
            $ipAddress
        );

        $auditStmt->execute();
        $auditStmt->close();
    }
} catch (Throwable $error) {
    error_log(
        'FieldTrack login audit error: ' .
        $error->getMessage()
    );
}

redirectToDashboard();
