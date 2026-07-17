<?php

declare(strict_types=1);

/*
 * Start or resume the session.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * Check whether the user is logged in.
 */
function requireLogin(): void
{
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
 *
 * Example:
 * requireRole(['admin']);
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
        exit('You do not have permission to access this page.');
    }
}