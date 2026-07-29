<?php

require_once 'session_config.php';
require_once 'db.php';

function loginFailed(): void
{
    $_SESSION['login_error'] = 'Invalid username or password.';

    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    loginFailed();
}

/*
|--------------------------------------------------------------------------
| Find user using a prepared statement
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
    exit('Unable to process login.');
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
*/

if (
    !$user ||
    $password !== $user['password']
) {
    loginFailed();
}

$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Load RBAC roles
|--------------------------------------------------------------------------
*/

$roleSql = "
    SELECT roles.role_name
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
    exit('Unable to load user role.');
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
    $_SESSION['login_error'] =
        'Your account does not have an assigned role.';

    header('Location: login.php');
    exit();
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
    loginFailed();
}

/*
|--------------------------------------------------------------------------
| Create authenticated session
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