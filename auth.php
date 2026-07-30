<?php

declare(strict_types=1);

require_once __DIR__ . '/session_config.php';

const SESSION_TIMEOUT = 1800;

/*
|--------------------------------------------------------------------------
| Check whether the user is logged in
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return (
        isset(
            $_SESSION['user_id'],
            $_SESSION['role']
        ) &&
        is_numeric($_SESSION['user_id']) &&
        (int) $_SESSION['user_id'] > 0 &&
        trim((string) $_SESSION['role']) !== ''
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
        $cookieParameters =
            session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookieParameters['path'] ?? '/',
            $cookieParameters['domain'] ?? '',
            (bool) (
                $cookieParameters['secure'] ??
                false
            ),
            (bool) (
                $cookieParameters['httponly'] ??
                true
            )
        );
    }

    if (
        session_status() ===
        PHP_SESSION_ACTIVE
    ) {
        session_destroy();
    }
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

    $lastActivity = (int) (
        $_SESSION['last_activity'] ?? 0
    );

    if (
        $lastActivity > 0 &&
        (time() - $lastActivity) >
        SESSION_TIMEOUT
    ) {
        destroyCurrentSession();

        header(
            'Location: login.php?session=expired'
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
| Current user ID
|--------------------------------------------------------------------------
*/

function currentUserId(): int
{
    requireLogin();

    return (int) $_SESSION['user_id'];
}

/*
|--------------------------------------------------------------------------
| Current user's full name
|--------------------------------------------------------------------------
|
| Example: Kamal Perera
|
*/

function currentUserName(): string
{
    requireLogin();

    return trim(
        (string) (
            $_SESSION['name'] ??
            'Unknown User'
        )
    );
}

/*
|--------------------------------------------------------------------------
| Current user's login username
|--------------------------------------------------------------------------
|
| Example: kamal
|
*/

function currentLoginUsername(): string
{
    requireLogin();

    return trim(
        (string) (
            $_SESSION['username'] ?? ''
        )
    );
}

/*
|--------------------------------------------------------------------------
| Current primary role
|--------------------------------------------------------------------------
*/

function currentRole(): string
{
    requireLogin();

    return trim(
        (string) $_SESSION['role']
    );
}

/*
|--------------------------------------------------------------------------
| Current user's assigned roles
|--------------------------------------------------------------------------
*/

function currentRoles(): array
{
    requireLogin();

    $roles =
        $_SESSION['roles'] ?? [];

    if (!is_array($roles)) {
        $roles = [];
    }

    $cleanRoles = [];

    foreach ($roles as $roleName) {
        if (!is_string($roleName)) {
            continue;
        }

        $roleName = trim($roleName);

        if (
            $roleName !== '' &&
            !in_array(
                $roleName,
                $cleanRoles,
                true
            )
        ) {
            $cleanRoles[] = $roleName;
        }
    }

    $primaryRole = trim(
        (string) (
            $_SESSION['role'] ?? ''
        )
    );

    if (
        $primaryRole !== '' &&
        !in_array(
            $primaryRole,
            $cleanRoles,
            true
        )
    ) {
        $cleanRoles[] = $primaryRole;
    }

    return array_values($cleanRoles);
}

/*
|--------------------------------------------------------------------------
| Check one role
|--------------------------------------------------------------------------
*/

function hasRole(string $roleName): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $roleName = trim($roleName);

    if ($roleName === '') {
        return false;
    }

    $roles =
        $_SESSION['roles'] ?? [];

    if (!is_array($roles)) {
        $roles = [];
    }

    $primaryRole = trim(
        (string) (
            $_SESSION['role'] ?? ''
        )
    );

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

    return in_array(
        $roleName,
        $roles,
        true
    );
}

/*
|--------------------------------------------------------------------------
| Check multiple roles
|--------------------------------------------------------------------------
*/

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
| Require an allowed role
|--------------------------------------------------------------------------
*/

function requireRole(
    string|array $allowedRoles
): void {
    requireLogin();

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    if (!hasAnyRole($allowedRoles)) {
        http_response_code(403);

        exit(
            'Access denied. You do not have permission to access this page.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Field Officer access
|--------------------------------------------------------------------------
*/

function requireFieldOfficer(): void
{
    requireRole('field_officer');
}

/*
|--------------------------------------------------------------------------
| Admin Officer access
|--------------------------------------------------------------------------
*/

function requireAdminOfficer(): void
{
    requireRole('admin_officer');
}

/*
|--------------------------------------------------------------------------
| Admin Manager access
|--------------------------------------------------------------------------
*/

function requireAdminManager(): void
{
    requireRole('admin_manager');
}

/*
|--------------------------------------------------------------------------
| System Administrator access
|--------------------------------------------------------------------------
*/

function requireSystemAdmin(): void
{
    requireRole('system_admin');
}

/*
|--------------------------------------------------------------------------
| All administrative users
|--------------------------------------------------------------------------
*/

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

    if (
        $fieldOfficerId > 0 &&
        currentUserId() ===
        $fieldOfficerId
    ) {
        http_response_code(403);

        exit(
            'You cannot approve or reject your own attendance submission.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Redirect to the correct dashboard
|--------------------------------------------------------------------------
*/

function redirectToDashboard(): void
{
    requireLogin();

    $primaryRole = currentRole();

    if (
        in_array(
            $primaryRole,
            [
                'system_admin',
                'admin_manager',
                'admin_officer'
            ],
            true
        )
    ) {
        header(
            'Location: admin_panel.php'
        );

        exit();
    }

    if (
        $primaryRole ===
        'field_officer'
    ) {
        header(
            'Location: user_panel.php'
        );

        exit();
    }

    destroyCurrentSession();

    header('Location: login.php');

    exit();
}