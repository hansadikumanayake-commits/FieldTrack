<?php

declare(strict_types=1);

/*
 * FieldTrack authentication and role protection.
 *
 * Database role source:
 * users -> user_roles -> roles
 */

const SESSION_TIMEOUT_SECONDS = 1800;

const ROLE_DASHBOARDS = [
    'field_officer' => 'user_panel.php',
    'admin_officer' => 'admin_officer_panel.php',
    'admin_manager' => 'admin_manager_panel.php',
    'system_admin' => 'admin_panel.php',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function clearCurrentSession(): void
{
    $_SESSION = [];

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

    session_destroy();
}

function isLoggedIn(): bool
{
    return (
        !empty($_SESSION['logged_in']) &&
        !empty($_SESSION['user_id']) &&
        !empty($_SESSION['role'])
    );
}

function checkSessionTimeout(): void
{
    if (!isLoggedIn()) {
        return;
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);

    if (
        $lastActivity > 0 &&
        (time() - $lastActivity) > SESSION_TIMEOUT_SECONDS
    ) {
        clearCurrentSession();
        header('Location: login.php?session=expired');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function requireLogin(): void
{
    checkSessionTimeout();

    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(array $allowedRoles): void
{
    requireLogin();

    $currentRole = (string) ($_SESSION['role'] ?? '');

    if (!in_array($currentRole, $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function currentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function currentRole(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

function redirectToDashboard(): never
{
    requireLogin();

    $dashboard =
        ROLE_DASHBOARDS[currentRole()] ??
        'login.php';

    header('Location: ' . $dashboard);
    exit;
}
