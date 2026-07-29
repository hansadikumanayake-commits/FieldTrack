<?php

require_once 'session_config.php';
require_once 'db.php';

/*
|--------------------------------------------------------------------------
| Allow only System Administrators
|--------------------------------------------------------------------------
*/

$currentRole = $_SESSION['role'] ?? '';
$currentRoles = $_SESSION['roles'] ?? [];

$isSystemAdmin =
    $currentRole === 'system_admin' ||
    in_array(
        'system_admin',
        $currentRoles,
        true
    );

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!$isSystemAdmin) {
    http_response_code(403);
    exit('Access denied.');
}

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function escapeValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectWithMessage(
    string $message,
    string $type = 'success'
): void {
    $_SESSION['manage_users_message'] = $message;
    $_SESSION['manage_users_message_type'] = $type;

    header('Location: manage_users.php');
    exit();
}

function getRoleName(
    mysqli $conn,
    int $roleId
): ?string {
    $sql = "
        SELECT role_name
        FROM roles
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $roleId
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $role = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $role['role_name'] ?? null;
}

function getLegacyRole(string $rbacRole): string
{
    return $rbacRole === 'field_officer'
        ? 'user'
        : 'admin';
}

/*
|--------------------------------------------------------------------------
| CSRF token
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Process user-management forms
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken =
        (string) ($_POST['csrf_token'] ?? '');

    if (
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {
        redirectWithMessage(
            'Invalid form request. Please try again.',
            'error'
        );
    }

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Create a new user
    |--------------------------------------------------------------------------
    */

    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $roleId = filter_input(
            INPUT_POST,
            'role_id',
            FILTER_VALIDATE_INT
        );

        if (
            $name === '' ||
            $username === '' ||
            $password === '' ||
            !$roleId
        ) {
            redirectWithMessage(
                'Name, username, password and role are required.',
                'error'
            );
        }

        if (strlen($name) > 100) {
            redirectWithMessage(
                'The name must not exceed 100 characters.',
                'error'
            );
        }

        if (strlen($username) > 100) {
            redirectWithMessage(
                'The username must not exceed 100 characters.',
                'error'
            );
        }

        if (strlen($password) < 3) {
            redirectWithMessage(
                'The password must contain at least 3 characters.',
                'error'
            );
        }

        $roleName = getRoleName(
            $conn,
            (int) $roleId
        );

        if ($roleName === null) {
            redirectWithMessage(
                'The selected role is invalid.',
                'error'
            );
        }

        $legacyRole = getLegacyRole($roleName);

        mysqli_begin_transaction($conn);

        try {
            /*
            |--------------------------------------------------------------
            | Store plain-text password
            |--------------------------------------------------------------
            */

            $insertUserSql = "
                INSERT INTO users
                (
                    name,
                    username,
                    password,
                    role
                )
                VALUES (?, ?, ?, ?)
            ";

            $insertUserStmt = mysqli_prepare(
                $conn,
                $insertUserSql
            );

            if (!$insertUserStmt) {
                throw new RuntimeException(
                    'Unable to prepare user creation.'
                );
            }

            mysqli_stmt_bind_param(
                $insertUserStmt,
                'ssss',
                $name,
                $username,
                $password,
                $legacyRole
            );

            if (!mysqli_stmt_execute($insertUserStmt)) {
                throw new RuntimeException(
                    'Unable to create the user.'
                );
            }

            $newUserId =
                mysqli_insert_id($conn);

            mysqli_stmt_close($insertUserStmt);

            /*
            |--------------------------------------------------------------
            | Assign the RBAC role
            |--------------------------------------------------------------
            */

            $assignRoleSql = "
                INSERT INTO user_roles
                (
                    user_id,
                    role_id
                )
                VALUES (?, ?)
            ";

            $assignRoleStmt = mysqli_prepare(
                $conn,
                $assignRoleSql
            );

            if (!$assignRoleStmt) {
                throw new RuntimeException(
                    'Unable to prepare role assignment.'
                );
            }

            mysqli_stmt_bind_param(
                $assignRoleStmt,
                'ii',
                $newUserId,
                $roleId
            );

            if (!mysqli_stmt_execute($assignRoleStmt)) {
                throw new RuntimeException(
                    'Unable to assign the role.'
                );
            }

            mysqli_stmt_close($assignRoleStmt);

            mysqli_commit($conn);

            redirectWithMessage(
                'User created successfully.'
            );
        } catch (Throwable $exception) {
            mysqli_rollback($conn);

            if (mysqli_errno($conn) === 1062) {
                redirectWithMessage(
                    'That username is already being used.',
                    'error'
                );
            }

            redirectWithMessage(
                'The user could not be created.',
                'error'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update an existing user
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_user') {
        $userId = filter_input(
            INPUT_POST,
            'user_id',
            FILTER_VALIDATE_INT
        );

        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $newPassword =
            trim($_POST['new_password'] ?? '');

        $roleId = filter_input(
            INPUT_POST,
            'role_id',
            FILTER_VALIDATE_INT
        );

        if (
            !$userId ||
            !$roleId ||
            $name === '' ||
            $username === ''
        ) {
            redirectWithMessage(
                'Invalid user information.',
                'error'
            );
        }

        if (
            $newPassword !== '' &&
            strlen($newPassword) < 3
        ) {
            redirectWithMessage(
                'The new password must contain at least 3 characters.',
                'error'
            );
        }

        $roleName = getRoleName(
            $conn,
            (int) $roleId
        );

        if ($roleName === null) {
            redirectWithMessage(
                'The selected role is invalid.',
                'error'
            );
        }

        $legacyRole = getLegacyRole($roleName);

        mysqli_begin_transaction($conn);

        try {
            /*
            |--------------------------------------------------------------
            | Update user with a new plain-text password
            |--------------------------------------------------------------
            */

            if ($newPassword !== '') {
                $updateUserSql = "
                    UPDATE users
                    SET
                        name = ?,
                        username = ?,
                        password = ?,
                        role = ?
                    WHERE id = ?
                ";

                $updateUserStmt = mysqli_prepare(
                    $conn,
                    $updateUserSql
                );

                if (!$updateUserStmt) {
                    throw new RuntimeException(
                        'Unable to prepare user update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $updateUserStmt,
                    'ssssi',
                    $name,
                    $username,
                    $newPassword,
                    $legacyRole,
                    $userId
                );
            } else {
                /*
                |----------------------------------------------------------
                | Keep the existing password
                |----------------------------------------------------------
                */

                $updateUserSql = "
                    UPDATE users
                    SET
                        name = ?,
                        username = ?,
                        role = ?
                    WHERE id = ?
                ";

                $updateUserStmt = mysqli_prepare(
                    $conn,
                    $updateUserSql
                );

                if (!$updateUserStmt) {
                    throw new RuntimeException(
                        'Unable to prepare user update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $updateUserStmt,
                    'sssi',
                    $name,
                    $username,
                    $legacyRole,
                    $userId
                );
            }

            if (!mysqli_stmt_execute($updateUserStmt)) {
                throw new RuntimeException(
                    'Unable to update the user.'
                );
            }

            mysqli_stmt_close($updateUserStmt);

            /*
            |--------------------------------------------------------------
            | Remove old role assignments
            |--------------------------------------------------------------
            */

            $deleteRolesSql = "
                DELETE FROM user_roles
                WHERE user_id = ?
            ";

            $deleteRolesStmt = mysqli_prepare(
                $conn,
                $deleteRolesSql
            );

            if (!$deleteRolesStmt) {
                throw new RuntimeException(
                    'Unable to update the user role.'
                );
            }

            mysqli_stmt_bind_param(
                $deleteRolesStmt,
                'i',
                $userId
            );

            mysqli_stmt_execute($deleteRolesStmt);
            mysqli_stmt_close($deleteRolesStmt);

            /*
            |--------------------------------------------------------------
            | Assign selected role
            |--------------------------------------------------------------
            */

            $assignRoleSql = "
                INSERT INTO user_roles
                (
                    user_id,
                    role_id
                )
                VALUES (?, ?)
            ";

            $assignRoleStmt = mysqli_prepare(
                $conn,
                $assignRoleSql
            );

            if (!$assignRoleStmt) {
                throw new RuntimeException(
                    'Unable to assign the selected role.'
                );
            }

            mysqli_stmt_bind_param(
                $assignRoleStmt,
                'ii',
                $userId,
                $roleId
            );

            if (!mysqli_stmt_execute($assignRoleStmt)) {
                throw new RuntimeException(
                    'Unable to assign the selected role.'
                );
            }

            mysqli_stmt_close($assignRoleStmt);

            mysqli_commit($conn);

            /*
            |--------------------------------------------------------------
            | Refresh current System Administrator session
            |--------------------------------------------------------------
            */

            if (
                $userId ===
                (int) $_SESSION['user_id']
            ) {
                $_SESSION['name'] = $name;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $roleName;
                $_SESSION['roles'] = [$roleName];
            }

            redirectWithMessage(
                'User updated successfully.'
            );
        } catch (Throwable $exception) {
            mysqli_rollback($conn);

            redirectWithMessage(
                'The user could not be updated. Check whether the username already exists.',
                'error'
            );
        }
    }

    redirectWithMessage(
        'Invalid user-management action.',
        'error'
    );
}

/*
|--------------------------------------------------------------------------
| Load available roles
|--------------------------------------------------------------------------
*/

$roles = [];

$rolesResult = mysqli_query(
    $conn,
    "
        SELECT
            id,
            role_name,
            description
        FROM roles
        ORDER BY id
    "
);

if ($rolesResult) {
    while (
        $role =
        mysqli_fetch_assoc($rolesResult)
    ) {
        $roles[] = $role;
    }
}

/*
|--------------------------------------------------------------------------
| Load selected user for editing
|--------------------------------------------------------------------------
*/

$editUser = null;
$editUserId = filter_input(
    INPUT_GET,
    'edit',
    FILTER_VALIDATE_INT
);

if ($editUserId) {
    $editSql = "
        SELECT
            users.id,
            users.name,
            users.username,
            users.password,
            user_roles.role_id
        FROM users
        LEFT JOIN user_roles
            ON user_roles.user_id = users.id
        WHERE users.id = ?
        LIMIT 1
    ";

    $editStmt = mysqli_prepare(
        $conn,
        $editSql
    );

    if ($editStmt) {
        mysqli_stmt_bind_param(
            $editStmt,
            'i',
            $editUserId
        );

        mysqli_stmt_execute($editStmt);

        $editResult =
            mysqli_stmt_get_result($editStmt);

        $editUser =
            mysqli_fetch_assoc($editResult);

        mysqli_stmt_close($editStmt);
    }
}

/*
|--------------------------------------------------------------------------
| Load all users
|--------------------------------------------------------------------------
*/

$users = [];

$userQuery = "
    SELECT
        users.id,
        users.name,
        users.username,
        users.password,
        users.role AS legacy_role,
        roles.id AS role_id,
        roles.role_name
    FROM users
    LEFT JOIN user_roles
        ON user_roles.user_id = users.id
    LEFT JOIN roles
        ON roles.id = user_roles.role_id
    ORDER BY users.id
";

$userResult = mysqli_query(
    $conn,
    $userQuery
);

if ($userResult) {
    while (
        $userRow =
        mysqli_fetch_assoc($userResult)
    ) {
        $users[] = $userRow;
    }
}

/*
|--------------------------------------------------------------------------
| Flash message
|--------------------------------------------------------------------------
*/

$message =
    $_SESSION['manage_users_message'] ?? '';

$messageType =
    $_SESSION['manage_users_message_type'] ??
    'success';

unset(
    $_SESSION['manage_users_message'],
    $_SESSION['manage_users_message_type']
);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Users | FieldTrack</title>

    <link
        rel="stylesheet"
        href="admin_style.css"
    >
</head>

<body>

<div class="admin-page">

    <div class="admin-shell">

        <header class="admin-header">

            <div class="admin-header-left">

                <h1>FieldTrack</h1>

                <p>
                    User and Role Management
                </p>

            </div>

            <div class="admin-header-right">

                <div class="admin-profile">

                    <div class="admin-avatar">
                        <?= escapeValue(
                            strtoupper(
                                substr(
                                    $_SESSION['name'] ?? 'A',
                                    0,
                                    1
                                )
                            )
                        ) ?>
                    </div>

                    <div class="admin-profile-info">

                        <span class="admin-profile-name">
                            <?= escapeValue(
                                $_SESSION['name'] ??
                                'System Administrator'
                            ) ?>
                        </span>

                        <span class="admin-profile-role">
                            System Administrator
                        </span>

                    </div>

                </div>

                <a
                    href="logout.php"
                    class="logout-btn"
                >
                    Logout
                </a>

            </div>

        </header>

        <nav class="admin-nav">

            <a
                href="admin_panel.php"
                class="admin-nav-link"
            >
                Dashboard
            </a>

            <a
                href="manage_users.php"
                class="admin-nav-link active"
            >
                Manage Users
            </a>

            <a
                href="audit_logs.php"
                class="admin-nav-link"
            >
                Audit Logs
            </a>

        </nav>

        <main class="admin-content">

            <div class="page-heading">

                <div>

                    <h2>Manage Users</h2>

                    <p>
                        Create users, assign roles and change passwords.
                    </p>

                </div>

            </div>

            <?php if ($message !== ''): ?>

                <div
                    class="alert <?= $messageType === 'error'
                        ? 'alert-error'
                        : 'alert-success'
                    ?>"
                >
                    <?= escapeValue($message) ?>
                </div>

            <?php endif; ?>

            <?php if ($editUser !== null): ?>

                <section class="panel">

                    <div class="panel-header">

                        <div class="panel-title">

                            <h3>Edit User</h3>

                            <p>
                                Leave the password empty to keep the current password.
                            </p>

                        </div>

                        <a
                            href="manage_users.php"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>

                    </div>

                    <div class="panel-body">

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= escapeValue(
                                    $csrfToken
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="update_user"
                            >

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= (int) $editUser['id'] ?>"
                            >

                            <div class="form-grid">

                                <div class="form-group">

                                    <label for="edit_name">
                                        Full Name
                                    </label>

                                    <input
                                        type="text"
                                        id="edit_name"
                                        name="name"
                                        maxlength="100"
                                        value="<?= escapeValue(
                                            $editUser['name']
                                        ) ?>"
                                        required
                                    >

                                </div>

                                <div class="form-group">

                                    <label for="edit_username">
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        id="edit_username"
                                        name="username"
                                        maxlength="100"
                                        value="<?= escapeValue(
                                            $editUser['username']
                                        ) ?>"
                                        required
                                    >

                                </div>

                                <div class="form-group">

                                    <label for="new_password">
                                        New Password
                                    </label>

                                    <input
                                        type="text"
                                        id="new_password"
                                        name="new_password"
                                        placeholder="Leave empty to keep current"
                                    >

                                </div>

                                <div class="form-group">

                                    <label for="edit_role">
                                        RBAC Role
                                    </label>

                                    <select
                                        id="edit_role"
                                        name="role_id"
                                        required
                                    >

                                        <option value="">
                                            Select role
                                        </option>

                                        <?php foreach ($roles as $role): ?>

                                            <option
                                                value="<?= (int) $role['id'] ?>"
                                                <?= (int) $editUser['role_id'] ===
                                                    (int) $role['id']
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                <?= escapeValue(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $role['role_name']
                                                        )
                                                    )
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            <div class="form-actions">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Update User
                                </button>

                                <a
                                    href="manage_users.php"
                                    class="btn btn-secondary"
                                >
                                    Cancel
                                </a>

                            </div>

                        </form>

                    </div>

                </section>

            <?php else: ?>

                <section class="panel">

                    <div class="panel-header">

                        <div class="panel-title">

                            <h3>Create New User</h3>

                            <p>
                                Passwords are stored as plain text in this local version.
                            </p>

                        </div>

                    </div>

                    <div class="panel-body">

                        <form method="POST">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= escapeValue(
                                    $csrfToken
                                ) ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="create_user"
                            >

                            <div class="form-grid">

                                <div class="form-group">

                                    <label for="name">
                                        Full Name
                                    </label>

                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        maxlength="100"
                                        required
                                    >

                                </div>

                                <div class="form-group">

                                    <label for="username">
                                        Username
                                    </label>

                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        maxlength="100"
                                        required
                                    >

                                </div>

                                <div class="form-group">

                                    <label for="password">
                                        Password
                                    </label>

                                    <input
                                        type="text"
                                        id="password"
                                        name="password"
                                        required
                                    >

                                </div>

                                <div class="form-group">

                                    <label for="role_id">
                                        RBAC Role
                                    </label>

                                    <select
                                        id="role_id"
                                        name="role_id"
                                        required
                                    >

                                        <option value="">
                                            Select role
                                        </option>

                                        <?php foreach ($roles as $role): ?>

                                            <option
                                                value="<?= (int) $role['id'] ?>"
                                            >
                                                <?= escapeValue(
                                                    ucwords(
                                                        str_replace(
                                                            '_',
                                                            ' ',
                                                            $role['role_name']
                                                        )
                                                    )
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            <div class="form-actions">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Create User
                                </button>

                            </div>

                        </form>

                    </div>

                </section>

            <?php endif; ?>

            <section class="panel">

                <div class="panel-header">

                    <div class="panel-title">

                        <h3>Existing Users</h3>

                        <p>
                            <?= count($users) ?> user account(s)
                        </p>

                    </div>

                </div>

                <div class="panel-body">

                    <div class="table-responsive">

                        <table class="admin-table">

                            <thead>

                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Password</th>
                                    <th>RBAC Role</th>
                                    <th>Old Role</th>
                                    <th>Action</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php if (empty($users)): ?>

                                <tr>

                                    <td
                                        colspan="7"
                                        class="text-center"
                                    >
                                        No users found.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($users as $user): ?>

                                    <tr>

                                        <td>
                                            <?= (int) $user['id'] ?>
                                        </td>

                                        <td>
                                            <?= escapeValue(
                                                $user['name']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escapeValue(
                                                $user['username']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= escapeValue(
                                                $user['password']
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="role-badge">

                                                <?= escapeValue(
                                                    $user['role_name']
                                                        ? ucwords(
                                                            str_replace(
                                                                '_',
                                                                ' ',
                                                                $user['role_name']
                                                            )
                                                        )
                                                        : 'No role'
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>
                                            <?= escapeValue(
                                                $user['legacy_role']
                                            ) ?>
                                        </td>

                                        <td>

                                            <a
                                                href="manage_users.php?edit=<?= (int) $user['id'] ?>"
                                                class="btn btn-small btn-primary"
                                            >
                                                Edit
                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </section>

        </main>

    </div>

</div>

</body>

</html>