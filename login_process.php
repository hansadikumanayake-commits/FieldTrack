<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('login.php');
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '' || strlen($username) > 100) {
    redirectTo('login.php?error=missing');
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

    $stmt->bind_param('s', $username);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Throwable $error) {
    error_log('FieldTrack login error: ' . $error->getMessage());
    redirectTo('login.php?error=database');
}

$validPassword = false;

if ($user !== null) {
    $storedPassword = (string) $user['password'];
    $passwordInfo = password_get_info($storedPassword);
    $isHash = ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown';

    $validPassword = $isHash
        ? password_verify($password, $storedPassword)
        : hash_equals($storedPassword, $password);
}

if ($user === null || !$validPassword) {
    redirectTo('login.php?error=invalid');
}

session_regenerate_id(true);

$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['name'] = (string) $user['name'];
$_SESSION['username'] = (string) $user['username'];
$_SESSION['role'] = (string) $user['role_name'];
$_SESSION['last_activity'] = time();

try {
    $userId = (int) $user['id'];
    $roleName = (string) $user['role_name'];
    $details = 'Logged in as ' . $roleName;
    $ip = getClientIpAddress();

    $audit = $conn->prepare(
        "INSERT INTO audit_logs
            (user_id, action, target_type, target_id, details, ip_address)
         VALUES
            (?, 'LOGIN_SUCCESS', 'authentication', ?, ?, ?)"
    );

    $audit->bind_param('iiss', $userId, $userId, $details, $ip);
    $audit->execute();
    $audit->close();
} catch (Throwable $error) {
    error_log('FieldTrack login audit error: ' . $error->getMessage());
}

redirectToDashboard();
