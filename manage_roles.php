<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
*/

requireSystemAdmin();
requirePermission('roles.manage');

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function escapeRoleValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectManageRoles(string $message): never
{
    header(
        'Location: manage_roles.php?msg=' .
        rawurlencode($message)
    );

    exit();
}

function formatRoleName(string $roleName): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $roleName
        )
    );
}

function recordRoleAudit(
    mysqli $conn,
    int $performedBy,
    string $action,
    int $targetUserId,
    string $details
): void {
    $targetType = 'user_role';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

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
        $performedBy,
        $action,
        $targetType,
        $targetUserId,
        $details,
        $ipAddress
    );

    $statement->execute();
    $statement->close();
}

/*
|--------------------------------------------------------------------------
| Current System Administrator
|--------------------------------------------------------------------------
*/

$currentAdminId = currentUserId();
$currentAdminName = currentUserName();

/*
|--------------------------------------------------------------------------
| Process form actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim(
        (string) ($_POST['form_action'] ?? '')
    );

    $userIdValue = trim(
        (string) ($_POST['user_id'] ?? '')
    );

    $roleIdValue = trim(
        (string) ($_POST['role_id'] ?? '')
    );

    if (
        $userIdValue === '' ||
        $roleIdValue === '' ||
        !ctype_digit($userIdValue) ||
        !ctype_digit($roleIdValue)
    ) {
        redirectManageRoles('invalid_selection');
    }

    $userId = (int) $userIdValue;
    $roleId = (int) $roleIdValue;

    if (
        $userId < 1 ||
        $roleId < 1
    ) {
        redirectManageRoles('invalid_selection');
    }

    /*
    |--------------------------------------------------------------------------
    | Load selected user
    |--------------------------------------------------------------------------
    */

    $userStatement = $conn->prepare(
        "SELECT
            id,
            name,
            username,
            is_active
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    $userStatement->bind_param(
        'i',
        $userId
    );

    $userStatement->execute();

    $selectedUser = $userStatement
        ->get_result()
        ->fetch_assoc();

    $userStatement->close();

    if (!$selectedUser) {
        redirectManageRoles('invalid_user');
    }

    /*
    |--------------------------------------------------------------------------
    | Load selected role
    |--------------------------------------------------------------------------
    */

    $roleStatement = $conn->prepare(
        "SELECT
            id,
            role_name,
            description
         FROM roles
         WHERE id = ?
         LIMIT 1"
    );

    $roleStatement->bind_param(
        'i',
        $roleId
    );

    $roleStatement->execute();

    $selectedRole = $roleStatement
        ->get_result()
        ->fetch_assoc();

    $roleStatement->close();

    if (!$selectedRole) {
        redirectManageRoles('invalid_role');
    }

    $roleName = (string) $selectedRole['role_name'];
    $username = (string) $selectedUser['username'];

    /*
    |--------------------------------------------------------------------------
    | Assign role
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'assign_role') {
        $existingStatement = $conn->prepare(
            "SELECT user_id
             FROM user_roles
             WHERE user_id = ?
             AND role_id = ?
             LIMIT 1"
        );

        $existingStatement->bind_param(
            'ii',
            $userId,
            $roleId
        );

        $existingStatement->execute();

        $existingAssignment = $existingStatement
            ->get_result()
            ->fetch_assoc();

        $existingStatement->close();

        if ($existingAssignment) {
            redirectManageRoles('role_already_assigned');
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();

            $transactionStarted = true;

            $assignStatement = $conn->prepare(
                "INSERT INTO user_roles
                (
                    user_id,
                    role_id
                )
                VALUES (?, ?)"
            );

            $assignStatement->bind_param(
                'ii',
                $userId,
                $roleId
            );

            $assignStatement->execute();
            $assignStatement->close();

            recordRoleAudit(
                $conn,
                $currentAdminId,
                'ROLE_ASSIGNED',
                $userId,
                'Assigned role ' .
                $roleName .
                ' to user @' .
                $username .
                '.'
            );

            $conn->commit();

            $transactionStarted = false;

            redirectManageRoles('role_assigned');
        } catch (Throwable $error) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable) {
                    // Keep original error.
                }
            }

            error_log(
                'FieldTrack role assignment error: ' .
                $error->getMessage()
            );

            redirectManageRoles(
                'role_assignment_failed'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Remove role
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'remove_role') {
        /*
         * Prevent the logged-in System Administrator
         * from removing their own system_admin role.
         */

        if (
            $userId === $currentAdminId &&
            $roleName === 'system_admin'
        ) {
            redirectManageRoles(
                'cannot_remove_own_admin'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Confirm role is assigned
        |--------------------------------------------------------------------------
        */

        $assignedStatement = $conn->prepare(
            "SELECT user_id
             FROM user_roles
             WHERE user_id = ?
             AND role_id = ?
             LIMIT 1"
        );

        $assignedStatement->bind_param(
            'ii',
            $userId,
            $roleId
        );

        $assignedStatement->execute();

        $assignedRole = $assignedStatement
            ->get_result()
            ->fetch_assoc();

        $assignedStatement->close();

        if (!$assignedRole) {
            redirectManageRoles(
                'role_not_assigned'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent removal while officer assignment exists
        |--------------------------------------------------------------------------
        */

        if ($roleName === 'field_officer') {
            $assignmentCheck = $conn->prepare(
                "SELECT id
                 FROM officer_assignments
                 WHERE field_officer_id = ?
                 LIMIT 1"
            );

            $assignmentCheck->bind_param(
                'i',
                $userId
            );

            $assignmentCheck->execute();

            $hasAssignment = $assignmentCheck
                ->get_result()
                ->fetch_assoc();

            $assignmentCheck->close();

            if ($hasAssignment) {
                redirectManageRoles(
                    'field_officer_has_assignment'
                );
            }
        }

        if ($roleName === 'admin_officer') {
            $assignmentCheck = $conn->prepare(
                "SELECT id
                 FROM officer_assignments
                 WHERE admin_officer_id = ?
                 LIMIT 1"
            );

            $assignmentCheck->bind_param(
                'i',
                $userId
            );

            $assignmentCheck->execute();

            $hasAssignment = $assignmentCheck
                ->get_result()
                ->fetch_assoc();

            $assignmentCheck->close();

            if ($hasAssignment) {
                redirectManageRoles(
                    'admin_officer_has_assignment'
                );
            }
        }

        if ($roleName === 'admin_manager') {
            $assignmentCheck = $conn->prepare(
                "SELECT id
                 FROM officer_assignments
                 WHERE admin_manager_id = ?
                 LIMIT 1"
            );

            $assignmentCheck->bind_param(
                'i',
                $userId
            );

            $assignmentCheck->execute();

            $hasAssignment = $assignmentCheck
                ->get_result()
                ->fetch_assoc();

            $assignmentCheck->close();

            if ($hasAssignment) {
                redirectManageRoles(
                    'admin_manager_has_assignment'
                );
            }
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();

            $transactionStarted = true;

            $removeStatement = $conn->prepare(
                "DELETE FROM user_roles
                 WHERE user_id = ?
                 AND role_id = ?"
            );

            $removeStatement->bind_param(
                'ii',
                $userId,
                $roleId
            );

            $removeStatement->execute();
            $removeStatement->close();

            recordRoleAudit(
                $conn,
                $currentAdminId,
                'ROLE_REMOVED',
                $userId,
                'Removed role ' .
                $roleName .
                ' from user @' .
                $username .
                '.'
            );

            $conn->commit();

            $transactionStarted = false;

            redirectManageRoles('role_removed');
        } catch (Throwable $error) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable) {
                    // Keep original error.
                }
            }

            error_log(
                'FieldTrack role removal error: ' .
                $error->getMessage()
            );

            redirectManageRoles(
                'role_removal_failed'
            );
        }
    }

    redirectManageRoles('invalid_action');
}

/*
|--------------------------------------------------------------------------
| Page messages
|--------------------------------------------------------------------------
*/

$messageCode = trim(
    (string) ($_GET['msg'] ?? '')
);

$messageText = '';
$messageClass = '';

$messageMap = [
    'role_assigned' => [
        'The role was assigned successfully.',
        'success-message'
    ],

    'role_removed' => [
        'The role was removed successfully.',
        'success-message'
    ],

    'role_already_assigned' => [
        'This role is already assigned to the selected user.',
        'error-message'
    ],

    'role_not_assigned' => [
        'This role is not assigned to the selected user.',
        'error-message'
    ],

    'invalid_selection' => [
        'Select a valid user and role.',
        'error-message'
    ],

    'invalid_user' => [
        'The selected user is invalid.',
        'error-message'
    ],

    'invalid_role' => [
        'The selected role is invalid.',
        'error-message'
    ],

    'cannot_remove_own_admin' => [
        'You cannot remove your own System Administrator role.',
        'error-message'
    ],

    'field_officer_has_assignment' => [
        'Remove the Field Officer assignment before removing this role.',
        'error-message'
    ],

    'admin_officer_has_assignment' => [
        'This Admin Officer is assigned to Field Officers. Update those assignments first.',
        'error-message'
    ],

    'admin_manager_has_assignment' => [
        'This Admin Manager is assigned to Field Officers. Update those assignments first.',
        'error-message'
    ],

    'role_assignment_failed' => [
        'The role could not be assigned.',
        'error-message'
    ],

    'role_removal_failed' => [
        'The role could not be removed.',
        'error-message'
    ],

    'invalid_action' => [
        'The requested role action is invalid.',
        'error-message'
    ]
];

if (isset($messageMap[$messageCode])) {
    $messageText =
        $messageMap[$messageCode][0];

    $messageClass =
        $messageMap[$messageCode][1];
}

/*
|--------------------------------------------------------------------------
| Load roles, users and role assignments
|--------------------------------------------------------------------------
*/

$roles = [];
$users = [];
$dataError = '';

try {
    /*
    |--------------------------------------------------------------------------
    | Load available roles
    |--------------------------------------------------------------------------
    */

    $roleResult = $conn->query(
        "SELECT
            id,
            role_name,
            description,
            created_at
         FROM roles
         ORDER BY role_name ASC"
    );

    while (
        $roleRow =
        $roleResult->fetch_assoc()
    ) {
        $roles[] = $roleRow;
    }

    /*
    |--------------------------------------------------------------------------
    | Load users and assigned roles
    |--------------------------------------------------------------------------
    */

    $userResult = $conn->query(
        "SELECT
            users.id,
            users.name,
            users.username,
            users.is_active,
            users.created_at,

            GROUP_CONCAT(
                DISTINCT CONCAT(
                    roles.id,
                    ':',
                    roles.role_name
                )
                ORDER BY roles.role_name
                SEPARATOR ','
            ) AS assigned_roles

         FROM users

         LEFT JOIN user_roles
            ON user_roles.user_id =
               users.id

         LEFT JOIN roles
            ON roles.id =
               user_roles.role_id

         GROUP BY
            users.id,
            users.name,
            users.username,
            users.is_active,
            users.created_at

         ORDER BY
            users.name ASC,
            users.id ASC"
    );

    while (
        $userRow =
        $userResult->fetch_assoc()
    ) {
        $users[] = $userRow;
    }
} catch (Throwable $error) {
    error_log(
        'FieldTrack manage roles error: ' .
        $error->getMessage()
    );

    $dataError =
        'The role information could not be loaded.';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Roles | FieldTrack</title>

    <link
        rel="stylesheet"
        href="admin_style.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .roles-page {
            width: min(1450px, calc(100% - 32px));
            margin: 30px auto;
        }

        .roles-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .roles-header h1 {
            margin: 0 0 8px;
        }

        .roles-header p {
            margin: 0;
            color: #64748b;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .page-button {
            display: inline-block;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            background: #0f172a;
            color: #ffffff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .page-button.secondary {
            background: #475569;
        }

        .message {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-weight: 700;
        }

        .success-message {
            border: 1px solid #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .error-message {
            border: 1px solid #fca5a5;
            background: #fee2e2;
            color: #991b1b;
        }

        .assign-card,
        .roles-card,
        .users-card {
            margin-bottom: 24px;
            padding: 22px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.08);
        }

        .assign-card h2,
        .roles-card h2,
        .users-card h2 {
            margin: 0 0 18px;
        }

        .assign-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(250px, 1fr));
            gap: 15px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-field label {
            font-size: 14px;
            font-weight: 700;
        }

        .form-field select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            font: inherit;
        }

        .form-actions {
            margin-top: 17px;
        }

        .roles-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(200px, 1fr));
            gap: 16px;
        }

        .role-card {
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
        }

        .role-card h3 {
            margin: 0 0 9px;
        }

        .role-card p {
            margin: 0;
            color: #64748b;
            line-height: 1.5;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        .users-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 13px;
            text-transform: uppercase;
        }

        .users-table tbody tr:hover {
            background: #f8fafc;
        }

        .username {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .status-badge,
        .role-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 3px;
            padding: 5px;
            border-radius: 8px;
            background: #dbeafe;
        }

        .role-badge {
            color: #1d4ed8;
        }

        .remove-role-button {
            padding: 5px 8px;
            border: 0;
            border-radius: 6px;
            background: #dc2626;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
        }

        .empty-message {
            padding: 30px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 1050px) {
            .roles-grid {
                grid-template-columns:
                    repeat(2, minmax(200px, 1fr));
            }
        }

        @media (max-width: 700px) {
            .roles-page {
                width: calc(100% - 20px);
                margin-top: 18px;
            }

            .roles-header {
                flex-direction: column;
            }

            .assign-grid,
            .roles-grid {
                grid-template-columns: 1fr;
            }

            .page-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<main class="roles-page">

    <header class="roles-header">

        <div>
            <h1>Manage Roles</h1>

            <p>
                Assign or remove FieldTrack roles from user accounts.
            </p>

            <p>
                Logged in as
                <?= escapeRoleValue($currentAdminName) ?>
            </p>
        </div>

        <div class="header-actions">

            <a
                class="page-button secondary"
                href="admin_panel.php"
            >
                Back to Dashboard
            </a>

            <a
                class="page-button"
                href="logout.php"
            >
                Logout
            </a>

        </div>

    </header>

    <?php if ($messageText !== ''): ?>

        <div class="message <?= escapeRoleValue($messageClass) ?>">
            <?= escapeRoleValue($messageText) ?>
        </div>

    <?php endif; ?>

    <?php if ($dataError !== ''): ?>

        <div class="message error-message">
            <?= escapeRoleValue($dataError) ?>
        </div>

    <?php endif; ?>

    <section class="assign-card">

        <h2>Assign Role to User</h2>

        <form method="POST">

            <input
                type="hidden"
                name="form_action"
                value="assign_role"
            >

            <div class="assign-grid">

                <div class="form-field">

                    <label for="user_id">
                        Select User
                    </label>

                    <select
                        id="user_id"
                        name="user_id"
                        required
                    >
                        <option value="">
                            Choose a user
                        </option>

                        <?php foreach ($users as $user): ?>

                            <option
                                value="<?= (int) $user['id'] ?>"
                            >
                                <?= escapeRoleValue(
                                    $user['name']
                                ) ?>

                                (@<?= escapeRoleValue(
                                    $user['username']
                                ) ?>)

                                <?= (int) $user['is_active'] === 1
                                    ? ''
                                    : ' — Inactive' ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-field">

                    <label for="role_id">
                        Select Role
                    </label>

                    <select
                        id="role_id"
                        name="role_id"
                        required
                    >
                        <option value="">
                            Choose a role
                        </option>

                        <?php foreach ($roles as $role): ?>

                            <option
                                value="<?= (int) $role['id'] ?>"
                            >
                                <?= escapeRoleValue(
                                    formatRoleName(
                                        $role['role_name']
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <div class="form-actions">

                <button
                    class="page-button"
                    type="submit"
                >
                    Assign Role
                </button>

            </div>

        </form>

    </section>

    <section class="roles-card">

        <h2>Available Roles</h2>

        <div class="roles-grid">

            <?php foreach ($roles as $role): ?>

                <article class="role-card">

                    <h3>
                        <?= escapeRoleValue(
                            formatRoleName(
                                $role['role_name']
                            )
                        ) ?>
                    </h3>

                    <p>
                        <?= escapeRoleValue(
                            $role['description'] ??
                            'No description available.'
                        ) ?>
                    </p>

                </article>

            <?php endforeach; ?>

        </div>

    </section>

    <section class="users-card">

        <h2>User Role Assignments</h2>

        <div class="table-wrapper">

            <table class="users-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Assigned Roles</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($users)): ?>

                    <tr>
                        <td
                            colspan="4"
                            class="empty-message"
                        >
                            No user accounts were found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($users as $user): ?>

                        <tr>

                            <td>
                                #<?= (int) $user['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= escapeRoleValue(
                                        $user['name']
                                    ) ?>
                                </strong>

                                <span class="username">
                                    @<?= escapeRoleValue(
                                        $user['username']
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <span
                                    class="status-badge
                                    <?= (int) $user['is_active'] === 1
                                        ? 'status-active'
                                        : 'status-inactive' ?>"
                                >
                                    <?= (int) $user['is_active'] === 1
                                        ? 'Active'
                                        : 'Inactive' ?>
                                </span>
                            </td>

                            <td>

                                <?php
                                $assignedRoles =
                                    trim(
                                        (string) (
                                            $user['assigned_roles'] ??
                                            ''
                                        )
                                    );
                                ?>

                                <?php if ($assignedRoles === ''): ?>

                                    No role assigned

                                <?php else: ?>

                                    <?php
                                    $assignedRoleItems =
                                        explode(
                                            ',',
                                            $assignedRoles
                                        );
                                    ?>

                                    <?php foreach (
                                        $assignedRoleItems
                                        as $assignedRoleItem
                                    ): ?>

                                        <?php
                                        $roleParts =
                                            explode(
                                                ':',
                                                $assignedRoleItem,
                                                2
                                            );

                                        $assignedRoleId =
                                            isset($roleParts[0])
                                                ? (int) $roleParts[0]
                                                : 0;

                                        $assignedRoleName =
                                            $roleParts[1] ?? '';
                                        ?>

                                        <?php if (
                                            $assignedRoleId > 0 &&
                                            $assignedRoleName !== ''
                                        ): ?>

                                            <span class="role-item">

                                                <span class="role-badge">
                                                    <?= escapeRoleValue(
                                                        formatRoleName(
                                                            $assignedRoleName
                                                        )
                                                    ) ?>
                                                </span>

                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Remove this role from the user?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="form_action"
                                                        value="remove_role"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?= (int) $user['id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="role_id"
                                                        value="<?= $assignedRoleId ?>"
                                                    >

                                                    <button
                                                        class="remove-role-button"
                                                        type="submit"
                                                        title="Remove role"
                                                    >
                                                        Remove
                                                    </button>

                                                </form>

                                            </span>

                                        <?php endif; ?>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>

</html>