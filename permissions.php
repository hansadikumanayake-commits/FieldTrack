<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Confirm database connection exists
|--------------------------------------------------------------------------
*/

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {
    exit('Database connection is unavailable.');
}

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
    $permissionName = trim($permissionName);

    if (
        $userId < 1 ||
        $permissionName === ''
    ) {
        return false;
    }

    try {
        $statement = $conn->prepare(
            "SELECT 1

             FROM users

             INNER JOIN user_roles
                ON user_roles.user_id =
                   users.id

             INNER JOIN role_permissions
                ON role_permissions.role_id =
                   user_roles.role_id

             INNER JOIN permissions
                ON permissions.id =
                   role_permissions.permission_id

             WHERE users.id = ?
             AND users.is_active = 1
             AND permissions.permission_name = ?

             LIMIT 1"
        );

        $statement->bind_param(
            'is',
            $userId,
            $permissionName
        );

        $statement->execute();

        $permissionExists =
            $statement
                ->get_result()
                ->fetch_assoc();

        $statement->close();

        return $permissionExists !== null;
    } catch (Throwable $error) {
        error_log(
            'FieldTrack permission check error: ' .
            $error->getMessage()
        );

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Check permission for logged-in user
|--------------------------------------------------------------------------
*/

function currentUserHasPermission(
    string $permissionName
): bool {
    global $conn;

    if (!($conn instanceof mysqli)) {
        return false;
    }

    return userHasPermission(
        $conn,
        currentUserId(),
        $permissionName
    );
}

/*
|--------------------------------------------------------------------------
| Require a specific permission
|--------------------------------------------------------------------------
*/

function requirePermission(
    string $permissionName
): void {
    if (
        !currentUserHasPermission(
            $permissionName
        )
    ) {
        http_response_code(403);

        exit(
            'Access denied. You do not have the required permission.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Check whether a user has any listed permission
|--------------------------------------------------------------------------
*/

function userHasAnyPermission(
    mysqli $conn,
    int $userId,
    array $permissionNames
): bool {
    if (
        $userId < 1 ||
        empty($permissionNames)
    ) {
        return false;
    }

    foreach ($permissionNames as $permissionName) {
        if (!is_string($permissionName)) {
            continue;
        }

        $permissionName = trim(
            $permissionName
        );

        if (
            $permissionName !== '' &&
            userHasPermission(
                $conn,
                $userId,
                $permissionName
            )
        ) {
            return true;
        }
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Check any permission for logged-in user
|--------------------------------------------------------------------------
*/

function currentUserHasAnyPermission(
    array $permissionNames
): bool {
    global $conn;

    if (!($conn instanceof mysqli)) {
        return false;
    }

    return userHasAnyPermission(
        $conn,
        currentUserId(),
        $permissionNames
    );
}

/*
|--------------------------------------------------------------------------
| Require at least one permission
|--------------------------------------------------------------------------
*/

function requireAnyPermission(
    array $permissionNames
): void {
    if (
        !currentUserHasAnyPermission(
            $permissionNames
        )
    ) {
        http_response_code(403);

        exit(
            'Access denied. You do not have any of the required permissions.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Check whether a user has all listed permissions
|--------------------------------------------------------------------------
*/

function userHasAllPermissions(
    mysqli $conn,
    int $userId,
    array $permissionNames
): bool {
    if (
        $userId < 1 ||
        empty($permissionNames)
    ) {
        return false;
    }

    $validPermissionFound = false;

    foreach ($permissionNames as $permissionName) {
        if (!is_string($permissionName)) {
            return false;
        }

        $permissionName = trim(
            $permissionName
        );

        if ($permissionName === '') {
            return false;
        }

        $validPermissionFound = true;

        if (
            !userHasPermission(
                $conn,
                $userId,
                $permissionName
            )
        ) {
            return false;
        }
    }

    return $validPermissionFound;
}

/*
|--------------------------------------------------------------------------
| Check all permissions for logged-in user
|--------------------------------------------------------------------------
*/

function currentUserHasAllPermissions(
    array $permissionNames
): bool {
    global $conn;

    if (!($conn instanceof mysqli)) {
        return false;
    }

    return userHasAllPermissions(
        $conn,
        currentUserId(),
        $permissionNames
    );
}

/*
|--------------------------------------------------------------------------
| Require all listed permissions
|--------------------------------------------------------------------------
*/

function requireAllPermissions(
    array $permissionNames
): void {
    if (
        !currentUserHasAllPermissions(
            $permissionNames
        )
    ) {
        http_response_code(403);

        exit(
            'Access denied. You do not have all required permissions.'
        );
    }
}