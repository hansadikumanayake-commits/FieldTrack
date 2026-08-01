<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* Remove all session variables */
$_SESSION = [];

/* Delete the session cookie */
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookie['path'],
        $cookie['domain'],
        (bool) $cookie['secure'],
        (bool) $cookie['httponly']
    );
}

/* Destroy the session */
session_destroy();

/* Prevent the browser from reopening a cached dashboard */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* Return to login page */
header('Location: login.php?logout=1');
exit;