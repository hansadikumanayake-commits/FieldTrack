<?php

declare(strict_types=1);

const SESSION_TIMEOUT_SECONDS = 1800;

/*
 * Start or resume the session.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * Completely clear the current session.
 */
function clearCurrentSession(): void
{
    $_SESSION = [];

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

    session_destroy();
}

/*
 * Check whether the session has expired.
 */
function checkSessionTimeout(): void
{
    if (empty($_SESSION['logged_in'])) {
        return;
    }

    $lastActivity = (int) (
        $_SESSION['last_activity'] ?? 0
    );

    if (
        $lastActivity > 0 &&
        time() - $lastActivity >
        SESSION_TIMEOUT_SECONDS
    ) {
        clearCurrentSession();

        header(
            'Location: login.php?session=expired'
        );

        exit();
    }

    /*
     * Update activity time whenever the user
     * opens a protected page.
     */
    $_SESSION['last_activity'] = time();
}

/*
 * Check whether the user is logged in.
 */
function requireLogin(): void
{
    checkSessionTimeout();

    if (
        empty($_SESSION['logged_in']) ||
        empty($_SESSION['user_id']) ||
        empty($_SESSION['role'])
    ) {
        header('Location: login.php');
        exit();
    }
}

/*
 * Check whether the logged-in user has
 * permission to open the page.
 */
function requireRole(array $allowedRoles): void
{
    requireLogin();

    $currentRole = (string) (
        $_SESSION['role'] ?? ''
    );

    if (!in_array(
        $currentRole,
        $allowedRoles,
        true
    )) {
        http_response_code(403);

        exit(
            'You do not have permission ' .
            'to access this page.'
        );
    }
}