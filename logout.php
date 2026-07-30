<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

/*
|--------------------------------------------------------------------------
| Record logout activity
|--------------------------------------------------------------------------
*/

function recordLogoutAudit(
    mysqli $conn,
    int $userId,
    string $username
): void {
    try {
        $action = 'LOGOUT';
        $targetType = 'authentication';
        $targetId = $userId;

        $details =
            'User @' .
            $username .
            ' logged out successfully.';

        $ipAddress =
            $_SERVER['REMOTE_ADDR'] ??
            'Unknown';

        $statement = $conn->prepare(
            "INSERT INTO audit_logs
            (
                user_id,
                action,
                target_type,
                target_id,
                details,
                ip_address
            )
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $statement->bind_param(
            'ississ',
            $userId,
            $action,
            $targetType,
            $targetId,
            $details,
            $ipAddress
        );

        $statement->execute();
        $statement->close();
    } catch (Throwable $error) {
        /*
         * Logout must continue even when
         * audit logging fails.
         */

        error_log(
            'FieldTrack logout audit error: ' .
            $error->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Record audit before destroying the session
|--------------------------------------------------------------------------
*/

if (isLoggedIn()) {
    $userId = (int) (
        $_SESSION['user_id'] ?? 0
    );

    $username = trim(
        (string) (
            $_SESSION['username'] ??
            'Unknown'
        )
    );

    if (
        isset($conn) &&
        $conn instanceof mysqli &&
        $userId > 0
    ) {
        recordLogoutAudit(
            $conn,
            $userId,
            $username
        );
    }
}

/*
|--------------------------------------------------------------------------
| Destroy current login session
|--------------------------------------------------------------------------
*/

destroyCurrentSession();

/*
|--------------------------------------------------------------------------
| Return to login page
|--------------------------------------------------------------------------
*/

header('Location: login.php?logout=success');

exit();