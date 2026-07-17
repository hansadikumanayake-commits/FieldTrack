<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'audit_log.php';

/*
 * Record administrator logout before
 * deleting the session.
 */
if (
    !empty($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'admin'
) {
    writeAuditLog(
        $conn,
        (int) $_SESSION['user_id'],
        'ADMIN_LOGOUT'
    );
}

/*
 * Remove all session values.
 */
$_SESSION = [];

/*
 * Remove the session cookie.
 */
if (ini_get('session.use_cookies')) {
    $cookieParameters =
        session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParameters['path'],
        $cookieParameters['domain'],
        $cookieParameters['secure'],
        $cookieParameters['httponly']
    );
}

/*
 * Destroy the session.
 */
session_destroy();

header('Location: login.php');
exit();