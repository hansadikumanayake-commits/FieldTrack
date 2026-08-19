<?php
declare(strict_types=1);

require_once __DIR__ . '/session_config.php';
startFieldTrackSession();

const ROLE_DASHBOARDS = [
    'field_officer' => 'user_panel.php',
    'admin_officer' => 'admin_officer_panel.php',
    'admin_manager' => 'admin_manager_panel.php',
    'system_admin' => 'admin_panel.php',
];

function appUrl(string $path = ''): string
{
    $base = rtrim(FIELDTRACK_BASE_PATH, '/');
    return $path === '' ? $base . '/' : $base . '/' . ltrim($path, '/');
}

function redirectTo(string $path): never
{
    header('Location: ' . appUrl($path));
    exit;
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
            $cookie['path'] ?? '/',
            $cookie['domain'] ?? '',
            (bool) ($cookie['secure'] ?? false),
            (bool) ($cookie['httponly'] ?? true)
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function clearLoginSession(): void { clearCurrentSession(); }

function isLoggedIn(): bool
{
    return !empty($_SESSION['logged_in'])
        && !empty($_SESSION['user_id'])
        && !empty($_SESSION['role']);
}

function checkSessionTimeout(): void
{
    if (!isLoggedIn()) return;

    $last = (int) ($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > FIELDTRACK_SESSION_TIMEOUT) {
        clearCurrentSession();
        redirectTo('login.php?session=expired');
    }
    $_SESSION['last_activity'] = time();
}

function requireLogin(): void
{
    checkSessionTimeout();
    if (!isLoggedIn()) redirectTo('login.php');
}

function requireRole(array $allowedRoles): void
{
    requireLogin();
    if (!in_array(currentRole(), $allowedRoles, true)) {
        http_response_code(403);
        exit('Access denied for this account.');
    }
}

function currentUserId(): int { return (int) ($_SESSION['user_id'] ?? 0); }
function currentDisplayName(): string { return (string) ($_SESSION['name'] ?? ''); }
function currentUsername(): string { return (string) ($_SESSION['username'] ?? ''); }
function currentRole(): string { return (string) ($_SESSION['role'] ?? ''); }
function hasRole(string $role): bool { return isLoggedIn() && currentRole() === $role; }

function redirectToDashboard(): never
{
    requireLogin();
    redirectTo(ROLE_DASHBOARDS[currentRole()] ?? 'login.php');
}

function getClientIpAddress(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}