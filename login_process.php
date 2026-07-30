<?php

declare(strict_types=1);

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Confirm database connection
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
| Record login activity
|--------------------------------------------------------------------------
*/

function recordLoginAudit(
    mysqli $conn,
    ?int $userId,
    string $action,
    ?int $targetId,
    string $details
): void {
    try {
        $targetType = 'authentication';

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
         * Audit logging failure must not stop
         * the login process.
         */

        error_log(
            'FieldTrack login audit error: ' .
            $error->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Redirect after failed login
|--------------------------------------------------------------------------
*/

function loginFailed(
    mysqli $conn,
    string $message =
        'Invalid username or password.',
    ?int $userId = null,
    string $auditDetails =
        'Failed login attempt.'
): never {
    recordLoginAudit(
        $conn,
        $userId,
        'LOGIN_FAILED',
        $userId,
        $auditDetails
    );

    $_SESSION['login_error'] =
        $message;

    header('Location: login.php');

    exit();
}

/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Read submitted username and password
|--------------------------------------------------------------------------
*/

$username = trim(
    (string) ($_POST['username'] ?? '')
);

/*
 * Do not trim the password because spaces
 * may be part of the password.
 */

$password = (string) (
    $_POST['password'] ?? ''
);

/*
|--------------------------------------------------------------------------
| Validate submitted values
|--------------------------------------------------------------------------
*/

if (
    $username === '' ||
    $password === ''
) {
    loginFailed(
        $conn,
        'Please enter your username and password.',
        null,
        'Login failed because required fields were empty.'
    );
}

if (
    strlen($username) > 100 ||
    strlen($password) > 255
) {
    loginFailed(
        $conn,
        'Invalid username or password.',
        null,
        'Login failed because the submitted values were too long.'
    );
}

/*
|--------------------------------------------------------------------------
| Process login
|--------------------------------------------------------------------------
*/

try {
    /*
    |--------------------------------------------------------------------------
    | Find the user account
    |--------------------------------------------------------------------------
    */

    $userStatement = $conn->prepare(
        "SELECT
            id,
            name,
            username,
            password,
            is_active

         FROM users

         WHERE username = ?

         LIMIT 1"
    );

    $userStatement->bind_param(
        's',
        $username
    );

    $userStatement->execute();

    $user = $userStatement
        ->get_result()
        ->fetch_assoc();

    $userStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Username not found
    |--------------------------------------------------------------------------
    */

    if (!$user) {
        loginFailed(
            $conn,
            'Invalid username or password.',
            null,
            'Failed login attempt for username @' .
            $username .
            '. The username was not found.'
        );
    }

    $userId = (int) $user['id'];

    /*
    |--------------------------------------------------------------------------
    | Check whether the account is active
    |--------------------------------------------------------------------------
    */

    if ((int) $user['is_active'] !== 1) {
        recordLoginAudit(
            $conn,
            $userId,
            'LOGIN_BLOCKED',
            $userId,
            'Login was blocked because account @' .
            $username .
            ' is inactive.'
        );

        $_SESSION['login_error'] =
            'Your account has been disabled.';

        header('Location: login.php');

        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | Compare coursework plain-text password
    |--------------------------------------------------------------------------
    */

    $storedPassword =
        (string) $user['password'];

    if (
        !hash_equals(
            $storedPassword,
            $password
        )
    ) {
        loginFailed(
            $conn,
            'Invalid username or password.',
            $userId,
            'Failed login attempt for account @' .
            $username .
            '. The password was incorrect.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Load RBAC roles
    |--------------------------------------------------------------------------
    */

    $roleStatement = $conn->prepare(
        "SELECT DISTINCT
            roles.role_name

         FROM user_roles

         INNER JOIN roles
            ON roles.id =
               user_roles.role_id

         WHERE user_roles.user_id = ?

         ORDER BY FIELD(
            roles.role_name,
            'system_admin',
            'admin_manager',
            'admin_officer',
            'field_officer'
         )"
    );

    $roleStatement->bind_param(
        'i',
        $userId
    );

    $roleStatement->execute();

    $roleResult =
        $roleStatement->get_result();

    $roles = [];

    while (
        $roleRow =
        $roleResult->fetch_assoc()
    ) {
        $roleName = trim(
            (string) (
                $roleRow['role_name'] ??
                ''
            )
        );

        if (
            $roleName !== '' &&
            !in_array(
                $roleName,
                $roles,
                true
            )
        ) {
            $roles[] = $roleName;
        }
    }

    $roleStatement->close();

    /*
    |--------------------------------------------------------------------------
    | Confirm that at least one role exists
    |--------------------------------------------------------------------------
    */

    if (empty($roles)) {
        recordLoginAudit(
            $conn,
            $userId,
            'LOGIN_BLOCKED',
            $userId,
            'Login was blocked because account @' .
            $username .
            ' has no assigned role.'
        );

        $_SESSION['login_error'] =
            'No role has been assigned to this account.';

        header('Location: login.php');

        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | Keep only valid FieldTrack roles
    |--------------------------------------------------------------------------
    */

    $allowedRoles = [
        'system_admin',
        'admin_manager',
        'admin_officer',
        'field_officer'
    ];

    $roles = array_values(
        array_filter(
            $roles,
            static function (
                string $roleName
            ) use ($allowedRoles): bool {
                return in_array(
                    $roleName,
                    $allowedRoles,
                    true
                );
            }
        )
    );

    if (empty($roles)) {
        recordLoginAudit(
            $conn,
            $userId,
            'LOGIN_BLOCKED',
            $userId,
            'Login was blocked because account @' .
            $username .
            ' has no supported FieldTrack role.'
        );

        $_SESSION['login_error'] =
            'Unsupported account role.';

        header('Location: login.php');

        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | Select the primary role
    |--------------------------------------------------------------------------
    */

    $rolePriority = [
        'system_admin',
        'admin_manager',
        'admin_officer',
        'field_officer'
    ];

    $primaryRole = '';

    foreach ($rolePriority as $roleName) {
        if (
            in_array(
                $roleName,
                $roles,
                true
            )
        ) {
            $primaryRole = $roleName;
            break;
        }
    }

    if ($primaryRole === '') {
        recordLoginAudit(
            $conn,
            $userId,
            'LOGIN_BLOCKED',
            $userId,
            'Login was blocked because no primary role could be selected.'
        );

        $_SESSION['login_error'] =
            'Unsupported account role.';

        header('Location: login.php');

        exit();
    }

    /*
    |--------------------------------------------------------------------------
    | Create authenticated session
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    $_SESSION['user_id'] =
        $userId;

    $_SESSION['name'] =
        (string) $user['name'];

    $_SESSION['username'] =
        (string) $user['username'];

    $_SESSION['role'] =
        $primaryRole;

    $_SESSION['roles'] =
        $roles;

    $_SESSION['logged_in'] =
        true;

    $_SESSION['login_time'] =
        time();

    $_SESSION['last_activity'] =
        time();

    /*
    |--------------------------------------------------------------------------
    | Remove any previous login error
    |--------------------------------------------------------------------------
    */

    unset(
        $_SESSION['login_error']
    );

    /*
    |--------------------------------------------------------------------------
    | Record successful login
    |--------------------------------------------------------------------------
    */

    recordLoginAudit(
        $conn,
        $userId,
        'LOGIN_SUCCESS',
        $userId,
        'User @' .
        $username .
        ' logged in successfully with primary role ' .
        $primaryRole .
        '.'
    );

    /*
    |--------------------------------------------------------------------------
    | Redirect to the correct dashboard
    |--------------------------------------------------------------------------
    */

    redirectToDashboard();
} catch (Throwable $error) {
    error_log(
        'FieldTrack login process error: ' .
        $error->getMessage()
    );

    $_SESSION['login_error'] =
        'The login request could not be processed.';

    header('Location: login.php');

    exit();
}