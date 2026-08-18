<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

function requireAdministrativeUser(): void
{
    requireRole(['admin_officer', 'admin_manager', 'system_admin']);
}

function hasPermission(string $permissionName): bool
{
    global $conn;
    if (!isLoggedIn()) return false;

    $stmt = $conn->prepare(
        "SELECT 1
         FROM user_roles ur
         INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         WHERE ur.user_id = ? AND p.permission_name = ?
         LIMIT 1"
    );
    $userId = currentUserId();
    $stmt->bind_param('is', $userId, $permissionName);
    $stmt->execute();
    $allowed = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $allowed;
}

function requirePermission(string $permissionName): void
{
    if (!hasPermission($permissionName)) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}

function preventSelfApproval(int $fieldOfficerId): void
{
    if (currentUserId() === $fieldOfficerId) {
        http_response_code(403);
        exit('You cannot review your own weekly submission.');
    }
}