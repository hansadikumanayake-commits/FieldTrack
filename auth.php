<?php

require_once 'session_config.php';

/*
|--------------------------------------------------------------------------
| Session timeout
|--------------------------------------------------------------------------
|
| The user will be logged out after 30 minutes of inactivity.
|
*/

const SESSION_TIMEOUT_SECONDS = 1800;

/*
|--------------------------------------------------------------------------
| Check whether the user is logged in
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset(
        $_SESSION['user_id'],
        $_SESSION['role']
    );
}
/*
|--------------------------------------------------------------------------
| Completely clear the current session
|--------------------------------------------------------------------------
*/

function clearAuthenticationSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParameters = session_get_cookie_params();

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
|--------------------------------------------------------------------------
| Check session timeout
|--------------------------------------------------------------------------
*/

function checkSessionTimeout(): void
{
    if (!isLoggedIn()) {
        return;
    }

    $lastActivity =
        (int) ($_SESSION['last_activity'] ?? 0);

    if (
        $lastActivity > 0 &&
        time() - $lastActivity >
        SESSION_TIMEOUT_SECONDS
    ) {
        clearAuthenticationSession();

        header(
            'Location: login.php?message=session_expired'
        );

        exit();
    }

    $_SESSION['last_activity'] = time();
}
/*
|--------------------------------------------------------------------------
| Require a logged-in user
|--------------------------------------------------------------------------
*/

function requireLogin(): void
{
    checkSessionTimeout();

    if (!isLoggedIn()) {
        $_SESSION['login_error'] =
            'Please log in to continue.';

        header('Location: login.php');
        exit();
    }
}
/*
|--------------------------------------------------------------------------
| Get logged-in user ID
|--------------------------------------------------------------------------
*/

function currentUserId(): int
{
    requireLogin();

    return (int) $_SESSION['user_id'];
}

/*
|--------------------------------------------------------------------------
| Get logged-in user name
|--------------------------------------------------------------------------
*/

function currentUserName(): string
{
    requireLogin();

    return (string) (
        $_SESSION['name'] ??
        'Unknown User'
    );
}
/*
|--------------------------------------------------------------------------
| Get primary role
|--------------------------------------------------------------------------
*/

function currentRole(): string
{
    requireLogin();

    return (string) $_SESSION['role'];
}
/*
|--------------------------------------------------------------------------
| Get all roles
|--------------------------------------------------------------------------
*/

function currentRoles(): array
{
    requireLogin();

    $roles = $_SESSION['roles'] ?? [];

    if (!is_array($roles)) {
        $roles = [];
    }

    $primaryRole =
        (string) ($_SESSION['role'] ?? '');

    if (
        $primaryRole !== '' &&
        !in_array(
            $primaryRole,
            $roles,
            true
        )
    ) {
        $roles[] = $primaryRole;
    }

    return array_values(
        array_unique($roles)
    );
}
/*
|--------------------------------------------------------------------------
| Check whether the user has a role
|--------------------------------------------------------------------------
*/

function hasRole(string $requiredRole): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    return in_array(
        $requiredRole,
        currentRoles(),
        true
    );
}
