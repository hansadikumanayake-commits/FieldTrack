<?php

require_once 'session_config.php';

/*
|--------------------------------------------------------------------------
| Session timeout
|--------------------------------------------------------------------------
|
| User is logged out after 30 minutes without activity.
|
*/

const SESSION_TIMEOUT = 1800;

/*
|--------------------------------------------------------------------------
| Check whether a user is logged in
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
| Destroy the current session
|--------------------------------------------------------------------------
*/

function destroyCurrentSession(): void
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
        (time() - $lastActivity) > SESSION_TIMEOUT
    ) {
        destroyCurrentSession();

        header('Location: login.php?session=expired');
        exit();
    }

    $_SESSION['last_activity'] = time();
}

/*
|--------------------------------------------------------------------------
| Require login
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
| Current user information
|--------------------------------------------------------------------------
*/

function currentUserId(): int
{
    requireLogin();

    return (int) $_SESSION['user_id'];
}

function currentUserName(): string
{
    requireLogin();

    return (string) (
        $_SESSION['name'] ??
        'Unknown User'
    );
}

function currentUsername(): string
{
    requireLogin();

    return (string) (
        $_SESSION['username'] ??
        ''
    );
}

function currentRole(): string
{
    requireLogin();

    return (string) $_SESSION['role'];
}

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
        !in_array($primaryRole, $roles, true)
    ) {
        $roles[] = $primaryRole;
    }

    return array_values(
        array_unique($roles)
    );
}

/*
|--------------------------------------------------------------------------
| Role checks
|--------------------------------------------------------------------------
*/

function hasRole(string $roleName): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $roles = $_SESSION['roles'] ?? [];

    if (!is_array($roles)) {
        $roles = [];
    }

    $primaryRole =
        (string) ($_SESSION['role'] ?? '');

    if (
        $primaryRole !== '' &&
        !in_array($primaryRole, $roles, true)
    ) {
        $roles[] = $primaryRole;
    }

    return in_array(
        $roleName,
        $roles,
        true
    );
}

function hasAnyRole(array $allowedRoles): bool
{
    foreach ($allowedRoles as $roleName) {
        if (
            is_string($roleName) &&
            hasRole($roleName)
        ) {
            return true;
        }
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Require specific roles
|--------------------------------------------------------------------------
*/

function requireRole(array $allowedRoles): void
{
    requireLogin();

    if (!hasAnyRole($allowedRoles)) {
        http_response_code(403);

        exit(
            'Access denied. You do not have permission to access this page.'
        );
    }
}

function requireFieldOfficer(): void
{
    requireRole([
        'field_officer'
    ]);
}

function requireAdminOfficer(): void
{
    requireRole([
        'admin_officer'
    ]);
}

function requireAdminManager(): void
{
    requireRole([
        'admin_manager'
    ]);
}

function requireSystemAdmin(): void
{
    requireRole([
        'system_admin'
    ]);
}

function requireAdministrativeUser(): void
{
    requireRole([
        'admin_officer',
        'admin_manager',
        'system_admin'
    ]);
}

/*
|--------------------------------------------------------------------------
| Prevent self-approval
|--------------------------------------------------------------------------
*/

function preventSelfApproval(
    int $fieldOfficerId
): void {
    requireLogin();

    if (currentUserId() === $fieldOfficerId) {
        http_response_code(403);

        exit(
            'You cannot approve or reject your own attendance submission.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Redirect logged-in user to the correct dashboard
|--------------------------------------------------------------------------
*/

function redirectToDashboard(): void
{
    requireLogin();

    if (hasRole('field_officer')) {
        header('Location: user_panel.php');
        exit();
    }

    if (
        hasAnyRole([
            'admin_officer',
            'admin_manager',
            'system_admin'
        ])
    ) {
        header('Location: admin_panel.php');
        exit();
    }

    destroyCurrentSession();

    header('Location: login.php');
    exit();
}