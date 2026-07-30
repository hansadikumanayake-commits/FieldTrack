<?php

require_once 'session_config.php';
require_once 'db.php';

/*
|--------------------------------------------------------------------------
| Record login activity
|--------------------------------------------------------------------------
*/

function recordLoginAudit(
    mysqli $conn,
    ?int $userId,
    string $action,
    string $details
): void {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $sql = "
        INSERT INTO audit_logs
        (
            user_id,
            action,
            target_type,
            details,
            ip_address
        )
        VALUES (?, ?, 'authentication', ?, ?)
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isss',
        $userId,
        $action,
        $details,
        $ipAddress
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| Redirect after failed login
|--------------------------------------------------------------------------
*/

function loginFailed(
    mysqli $conn,
    string $message = 'Invalid username or password.',
    ?int $userId = null
): void {
    recordLoginAudit(
        $conn,
        $userId,
        'LOGIN_FAILED',
        'Failed login attempt'
    );

    $_SESSION['login_error'] = $message;

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

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    loginFailed($conn);
}

/*
|--------------------------------------------------------------------------
| Find active user
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        name,
        username,
        password,
        is_active
    FROM users
    WHERE username = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    exit('Unable to process the login request.');
}

mysqli_stmt_bind_param(
    $stmt,
    's',
    $username
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$user) {
    loginFailed($conn);
}

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Check whether account is active
|--------------------------------------------------------------------------
*/

if ((int) $user['is_active'] !== 1) {
    loginFailed(
        $conn,
        'Your account has been disabled.',
        $userId
    );
}

/*
|--------------------------------------------------------------------------
| Compare plain-text password
|--------------------------------------------------------------------------
*/

if (
    !hash_equals(
        (string) $user['password'],
        $password
    )
) {
    loginFailed(
        $conn,
        'Invalid username or password.',
        $userId
    );
}

/*
|--------------------------------------------------------------------------
| Load RBAC roles
|--------------------------------------------------------------------------
*/

$roleSql = "
    SELECT
        roles.role_name
    FROM user_roles
    INNER JOIN roles
        ON roles.id = user_roles.role_id
    WHERE user_roles.user_id = ?
";

$roleStmt = mysqli_prepare(
    $conn,
    $roleSql
);

if (!$roleStmt) {
    exit('Unable to load the account role.');
}

mysqli_stmt_bind_param(
    $roleStmt,
    'i',
    $userId
);

mysqli_stmt_execute($roleStmt);

$roleResult = mysqli_stmt_get_result($roleStmt);

$roles = [];

while ($roleRow = mysqli_fetch_assoc($roleResult)) {
    $roles[] = $roleRow['role_name'];
}

mysqli_stmt_close($roleStmt);

if (empty($roles)) {
    loginFailed(
        $conn,
        'No role has been assigned to this account.',
        $userId
    );
}

/*
|--------------------------------------------------------------------------
| Select primary role
|--------------------------------------------------------------------------
*/

$rolePriority = [
    'system_admin',
    'admin_manager',
    'admin_officer',
    'field_officer'
];

$primaryRole = null;

foreach ($rolePriority as $roleName) {
    if (in_array($roleName, $roles, true)) {
        $primaryRole = $roleName;
        break;
    }
}

if ($primaryRole === null) {
    loginFailed(
        $conn,
        'Unsupported account role.',
        $userId
    );
}

/*
|--------------------------------------------------------------------------
| Create login session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION['user_id'] = $userId;
$_SESSION['name'] = $user['name'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $primaryRole;
$_SESSION['roles'] = $roles;
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();

/*
|--------------------------------------------------------------------------
| Record successful login
|--------------------------------------------------------------------------
*/

recordLoginAudit(
    $conn,
    $userId,
    'LOGIN_SUCCESS',
    'User logged in as ' . $primaryRole
);

/*
|--------------------------------------------------------------------------
| Redirect according to role
|--------------------------------------------------------------------------
*/

if ($primaryRole === 'field_officer') {
    header('Location: user_panel.php');
    exit();
}

header('Location: admin_panel.php');
exit();