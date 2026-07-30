<?php

require_once 'auth.php';
require_once 'db.php';

/*
|--------------------------------------------------------------------------
| Check whether a user has a permission
|--------------------------------------------------------------------------
*/

function userHasPermission(
    mysqli $conn,
    int $userId,
    string $permissionName
): bool {
    $sql = "
        SELECT 1
        FROM user_roles
        INNER JOIN role_permissions
            ON role_permissions.role_id = user_roles.role_id
        INNER JOIN permissions
            ON permissions.id = role_permissions.permission_id
        INNER JOIN users
            ON users.id = user_roles.user_id
        WHERE user_roles.user_id = ?
        AND permissions.permission_name = ?
        AND users.is_active = 1
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'is',
        $userId,
        $permissionName
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $hasPermission =
        mysqli_num_rows($result) > 0;

    mysqli_stmt_close($stmt);

    return $hasPermission;
}

/*
|--------------------------------------------------------------------------
| Check permission for the currently logged-in user
|--------------------------------------------------------------------------
*/

function currentUserHasPermission(
    string $permissionName
): bool {
    global $conn;

    if (!isLoggedIn()) {
        return false;
    }

    return userHasPermission(
        $conn,
        (int) $_SESSION['user_id'],
        $permissionName
    );
}

/*
|--------------------------------------------------------------------------
| Require one permission
|--------------------------------------------------------------------------
*/

function requirePermission(
    string $permissionName
): void {
    requireLogin();

    if (!currentUserHasPermission($permissionName)) {
        http_response_code(403);

        exit(
            'Access denied. You do not have permission to perform this action.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Check whether user has any permission from a list
|--------------------------------------------------------------------------
*/

function currentUserHasAnyPermission(
    array $permissionNames
): bool {
    foreach ($permissionNames as $permissionName) {
        if (
            is_string($permissionName) &&
            currentUserHasPermission($permissionName)
        ) {
            return true;
        }
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Require at least one permission from a list
|--------------------------------------------------------------------------
*/

function requireAnyPermission(
    array $permissionNames
): void {
    requireLogin();

    if (!currentUserHasAnyPermission($permissionNames)) {
        http_response_code(403);

        exit(
            'Access denied. You do not have the required permission.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Check whether user has every permission in a list
|--------------------------------------------------------------------------
*/

function currentUserHasAllPermissions(
    array $permissionNames
): bool {
    foreach ($permissionNames as $permissionName) {
        if (
            !is_string($permissionName) ||
            !currentUserHasPermission($permissionName)
        ) {
            return false;
        }
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| Require every permission in a list
|--------------------------------------------------------------------------
*/

function requireAllPermissions(
    array $permissionNames
): void {
    requireLogin();

    if (!currentUserHasAllPermissions($permissionNames)) {
        http_response_code(403);

        exit(
            'Access denied. One or more required permissions are missing.'
        );
    }
}