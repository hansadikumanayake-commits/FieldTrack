<?php

require_once 'session_config.php';
require_once 'db.php';

/*
|--------------------------------------------------------------------------
| Redirect failed login
|--------------------------------------------------------------------------
*/

function loginFailed(string $message = 'Invalid username or password.'): void
{
    $_SESSION['login_error'] = $message;

    header('Location: login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Only accept POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Read login form values
|--------------------------------------------------------------------------
*/

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    loginFailed();
}

/*
|--------------------------------------------------------------------------
| Find the user
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        name,
        username,
        password
    FROM users
    WHERE username = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    loginFailed('Unable to process the login request.');
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

/*
|--------------------------------------------------------------------------
| Compare plain-text password
|--------------------------------------------------------------------------
|
| No password_hash() or password_verify() is used here.
|
*/

if (
    !$user ||
    !hash_equals(
        (string) $user['password'],
        $password
    )
) {
    loginFailed();
}

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Load the user's RBAC roles
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
    loginFailed('Unable to load your account role.');
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

/*
|--------------------------------------------------------------------------
| Stop login when no RBAC role is assigned
|--------------------------------------------------------------------------
*/

if (empty($roles)) {
    loginFailed(
        'Your account does not have an assigned system role.'
    );
}

/*
|--------------------------------------------------------------------------
| Select the user's main role
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
    loginFailed('Your account role is not supported.');
}

/*
|--------------------------------------------------------------------------
| Create the authenticated session
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

$_SESSION['user_id'] = $userId;
$_SESSION['name'] = $user['name'];
$_SESSION['username'] = $user['username'];
$_SESSION['roles'] = $roles;
$_SESSION['role'] = $primaryRole;
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();

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