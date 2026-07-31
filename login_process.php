<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim(
    (string) ($_POST['username'] ?? '')
);

$password = (string) (
    $_POST['password'] ?? ''
);

if (
    $username === '' ||
    $password === '' ||
    mb_strlen($username) > 100
) {
    header('Location: login_failed.php');
    exit;
}

try {
    /*
     * A user role is obtained through:
     * users -> user_roles -> roles
     *
     * The FIELD order gives one predictable role if a user
     * accidentally has more than one role.
     */
    $stmt = $conn->prepare(
        "SELECT
            u.id,
            u.name,
            u.username,
            u.password,
            u.is_active,
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

    $user = $stmt
        ->get_result()
        ->fetch_assoc();

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

    $isHash = (
        ($passwordInfo['algoName'] ?? 'unknown')
        !== 'unknown'
    );

    if ($isHash) {
        $validPassword = password_verify(
            $password,
            $storedPassword
        );
    } else {
        /*
         * Supports the plain-text test passwords currently
         * stored in the local FieldTrack database.
         */
        $validPassword = hash_equals(
            $storedPassword,
            $password
        );
    }
}

if (!$validPassword || $user === null) {
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

/*
 * Login auditing is useful, but a logging failure must not
 * prevent a valid user from signing in.
 */
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
         VALUES (?, 'LOGIN_SUCCESS', 'authentication', ?, ?, ?)"
    );

    if ($auditStmt !== false) {
        $userId = (int) $user['id'];
        $details =
            'User logged in with role: ' .
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
