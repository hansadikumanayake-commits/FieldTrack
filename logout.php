<?php

declare(strict_types=1);

require_once 'auth.php';

/*
 * Remove all session values.
 */
$_SESSION = [];

/*
 * Delete the session cookie from the browser.
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
 * Destroy the server-side session.
 */
session_destroy();

/*
 * Return the user to the login page.
 */
header('Location: login.php');
exit();