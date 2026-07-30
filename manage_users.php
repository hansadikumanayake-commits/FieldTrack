<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
*/

requireSystemAdmin();
requirePermission('users.manage');

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function escapeManageUserValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectManageUsers(string $message): never
{
    header(
        'Location: manage_users.php?msg=' .
        rawurlencode($message)
    );

    exit();
}

function recordUserManagementAudit(
    mysqli $conn,
    int $performedBy,
    string $action,
    int $targetUserId,
    string $details
): void {
    $targetType = 'user';
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
| Process form submissions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim(
        (string) ($_POST['form_action'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | Create user
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'create_user') {
        $name = trim(
            (string) ($_POST['name'] ?? '')
        );

        $username = trim(
            (string) ($_POST['username'] ?? '')
        );

        $password = trim(
            (string) ($_POST['password'] ?? '')
        );

        if (
            $name === '' ||
            $username === '' ||
            $password === ''
        ) {
            redirectManageUsers(
                'required_fields'
            );
        }

        if (strlen($name) > 100) {
            redirectManageUsers(
                'invalid_name'
            );
        }

        if (
            strlen($username) < 3 ||
            strlen($username) > 100 ||
            !preg_match(
                '/^[A-Za-z0-9._-]+$/',
                $username
            )
        ) {
            redirectManageUsers(
                'invalid_username'
            );
        }

        if (strlen($password) > 255) {
            redirectManageUsers(
                'invalid_password'
            );
        }

        $usernameCheck = $conn->prepare(
            "SELECT id
             FROM users
             WHERE username = ?
             LIMIT 1"
        );

        $usernameCheck->bind_param(
            's',
            $username
        );

        $usernameCheck->execute();

        $existingUser = $usernameCheck
            ->get_result()
            ->fetch_assoc();

        $usernameCheck->close();

        if ($existingUser) {
            redirectManageUsers(
                'username_exists'
            );
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();

            $transactionStarted = true;

            $createStatement = $conn->prepare(
                "INSERT INTO users
                (
                    name,
                    username,
                    password,
                    is_active
                )
                VALUES (?, ?, ?, 1)"
            );

            $createStatement->bind_param(
                'sss',
                $name,
                $username,
                $password
            );

            $createStatement->execute();

            $createdUserId =
                (int) $conn->insert_id;

            $createStatement->close();

            recordUserManagementAudit(
                $conn,
                $currentAdminId,
                'USER_CREATED',
                $createdUserId,
                'Created user account @' .
                $username .
                '.'
            );

            $conn->commit();

            $transactionStarted = false;

            redirectManageUsers(
                'user_created'
            );
        } catch (Throwable $error) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            error_log(
                'FieldTrack create user error: ' .
                $error->getMessage()
            );

            redirectManageUsers(
                'user_create_failed'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update user
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'update_user') {
        $userIdValue = trim(
            (string) ($_POST['user_id'] ?? '')
        );

        $name = trim(
            (string) ($_POST['name'] ?? '')
        );

        $username = trim(
            (string) ($_POST['username'] ?? '')
        );

        $newPassword = trim(
            (string) ($_POST['password'] ?? '')
        );

        $isActiveValue = trim(
            (string) ($_POST['is_active'] ?? '1')
        );

        if (
            $userIdValue === '' ||
            !ctype_digit($userIdValue)
        ) {
            redirectManageUsers(
                'invalid_user'
            );
        }

        $userId = (int) $userIdValue;

        if (
            $userId < 1 ||
            $name === '' ||
            $username === ''
        ) {
            redirectManageUsers(
                'required_fields'
            );
        }

        if (strlen($name) > 100) {
            redirectManageUsers(
                'invalid_name'
            );
        }

        if (
            strlen($username) < 3 ||
            strlen($username) > 100 ||
            !preg_match(
                '/^[A-Za-z0-9._-]+$/',
                $username
            )
        ) {
            redirectManageUsers(
                'invalid_username'
            );
        }

        if (strlen($newPassword) > 255) {
            redirectManageUsers(
                'invalid_password'
            );
        }

        $isActive =
            $isActiveValue === '0'
                ? 0
                : 1;

        /*
         * Prevent the logged-in System Administrator
         * from disabling their own account.
         */

        if (
            $userId === $currentAdminId &&
            $isActive === 0
        ) {
            redirectManageUsers(
                'cannot_disable_self'
            );
        }

        $userCheck = $conn->prepare(
            "SELECT
                id,
                username
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

        $userCheck->bind_param(
            'i',
            $userId
        );

        $userCheck->execute();

        $existingUser = $userCheck
            ->get_result()
            ->fetch_assoc();

        $userCheck->close();

        if (!$existingUser) {
            redirectManageUsers(
                'invalid_user'
            );
        }

        $usernameCheck = $conn->prepare(
            "SELECT id
             FROM users
             WHERE username = ?
             AND id <> ?
             LIMIT 1"
        );

        $usernameCheck->bind_param(
            'si',
            $username,
            $userId
        );

        $usernameCheck->execute();

        $duplicateUsername = $usernameCheck
            ->get_result()
            ->fetch_assoc();

        $usernameCheck->close();

        if ($duplicateUsername) {
            redirectManageUsers(
                'username_exists'
            );
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();

            $transactionStarted = true;

            if ($newPassword !== '') {
                $updateStatement = $conn->prepare(
                    "UPDATE users
                     SET
                        name = ?,
                        username = ?,
                        password = ?,
                        is_active = ?
                     WHERE id = ?"
                );

                $updateStatement->bind_param(
                    'sssii',
                    $name,
                    $username,
                    $newPassword,
                    $isActive,
                    $userId
                );
            } else {
                $updateStatement = $conn->prepare(
                    "UPDATE users
                     SET
                        name = ?,
                        username = ?,
                        is_active = ?
                     WHERE id = ?"
                );

                $updateStatement->bind_param(
                    'ssii',
                    $name,
                    $username,
                    $isActive,
                    $userId
                );
            }

            $updateStatement->execute();
            $updateStatement->close();

            $auditDetails =
                'Updated user account @' .
                $username .
                '. Account status: ' .
                (
                    $isActive === 1
                        ? 'Active'
                        : 'Inactive'
                ) .
                '.';

            if ($newPassword !== '') {
                $auditDetails .=
                    ' Password was changed.';
            }

            recordUserManagementAudit(
                $conn,
                $currentAdminId,
                'USER_UPDATED',
                $userId,
                $auditDetails
            );

            $conn->commit();

            $transactionStarted = false;

            redirectManageUsers(
                'user_updated'
            );
        } catch (Throwable $error) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            error_log(
                'FieldTrack update user error: ' .
                $error->getMessage()
            );

            redirectManageUsers(
                'user_update_failed'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Activate or deactivate user
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'change_status') {
        $userIdValue = trim(
            (string) ($_POST['user_id'] ?? '')
        );

        $newStatusValue = trim(
            (string) ($_POST['new_status'] ?? '')
        );

        if (
            $userIdValue === '' ||
            !ctype_digit($userIdValue) ||
            !in_array(
                $newStatusValue,
                ['0', '1'],
                true
            )
        ) {
            redirectManageUsers(
                'invalid_user'
            );
        }

        $userId = (int) $userIdValue;
        $newStatus = (int) $newStatusValue;

        if (
            $userId === $currentAdminId &&
            $newStatus === 0
        ) {
            redirectManageUsers(
                'cannot_disable_self'
            );
        }

        $userStatement = $conn->prepare(
            "SELECT
                id,
                username
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

        $userStatement->bind_param(
            'i',
            $userId
        );

        $userStatement->execute();

        $targetUser = $userStatement
            ->get_result()
            ->fetch_assoc();

        $userStatement->close();

        if (!$targetUser) {
            redirectManageUsers(
                'invalid_user'
            );
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();

            $transactionStarted = true;

            $statusStatement = $conn->prepare(
                "UPDATE users
                 SET is_active = ?
                 WHERE id = ?"
            );

            $statusStatement->bind_param(
                'ii',
                $newStatus,
                $userId
            );

            $statusStatement->execute();
            $statusStatement->close();

            $auditAction =
                $newStatus === 1
                    ? 'USER_ACTIVATED'
                    : 'USER_DEACTIVATED';

            recordUserManagementAudit(
                $conn,
                $currentAdminId,
                $auditAction,
                $userId,
                (
                    $newStatus === 1
                        ? 'Activated'
                        : 'Deactivated'
                ) .
                ' user account @' .
                $targetUser['username'] .
                '.'
            );

            $conn->commit();

            $transactionStarted = false;

            redirectManageUsers(
                $newStatus === 1
                    ? 'user_activated'
                    : 'user_deactivated'
            );
        } catch (Throwable $error) {
            if ($transactionStarted) {
                $conn->rollback();
            }

            error_log(
                'FieldTrack user status error: ' .
                $error->getMessage()
            );

            redirectManageUsers(
                'status_update_failed'
            );
        }
    }

    redirectManageUsers(
        'invalid_action'
    );
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
    'user_created' => [
        'The user account was created successfully.',
        'success-message'
    ],
    'user_updated' => [
        'The user account was updated successfully.',
        'success-message'
    ],
    'user_activated' => [
        'The user account was activated.',
        'success-message'
    ],
    'user_deactivated' => [
        'The user account was deactivated.',
        'success-message'
    ],
    'required_fields' => [
        'Please complete all required fields.',
        'error-message'
    ],
    'invalid_name' => [
        'The name is invalid or too long.',
        'error-message'
    ],
    'invalid_username' => [
        'Username must contain 3–100 letters, numbers, dots, underscores or hyphens.',
        'error-message'
    ],
    'invalid_password' => [
        'The password is too long.',
        'error-message'
    ],
    'username_exists' => [
        'That username is already being used.',
        'error-message'
    ],
    'invalid_user' => [
        'The selected user account is invalid.',
        'error-message'
    ],
    'cannot_disable_self' => [
        'You cannot disable your own System Administrator account.',
        'error-message'
    ],
    'user_create_failed' => [
        'The user account could not be created.',
        'error-message'
    ],
    'user_update_failed' => [
        'The user account could not be updated.',
        'error-message'
    ],
    'status_update_failed' => [
        'The account status could not be changed.',
        'error-message'
    ],
    'invalid_action' => [
        'The requested user action is invalid.',
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
| Search and status filters
|--------------------------------------------------------------------------
*/

$searchTerm = trim(
    (string) ($_GET['search'] ?? '')
);

if (strlen($searchTerm) > 100) {
    $searchTerm = substr(
        $searchTerm,
        0,
        100
    );
}

$statusFilter = trim(
    (string) ($_GET['status'] ?? 'all')
);

if (
    !in_array(
        $statusFilter,
        ['all', 'active', 'inactive'],
        true
    )
) {
    $statusFilter = 'all';
}

$statusValue = match ($statusFilter) {
    'active' => 1,
    'inactive' => 0,
    default => -1
};

/*
|--------------------------------------------------------------------------
| Load selected user for editing
|--------------------------------------------------------------------------
*/

$editUser = null;

$editUserIdValue = trim(
    (string) ($_GET['edit'] ?? '')
);

if (
    $editUserIdValue !== '' &&
    ctype_digit($editUserIdValue)
) {
    $editUserId = (int) $editUserIdValue;

    if ($editUserId > 0) {
        $editStatement = $conn->prepare(
            "SELECT
                id,
                name,
                username,
                is_active,
                created_at,
                updated_at
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

        $editStatement->bind_param(
            'i',
            $editUserId
        );

        $editStatement->execute();

        $editUser = $editStatement
            ->get_result()
            ->fetch_assoc();

        $editStatement->close();
    }
}

/*
|--------------------------------------------------------------------------
| Load users
|--------------------------------------------------------------------------
*/

$users = [];
$dataError = '';

try {
    $userListStatement = $conn->prepare(
        "SELECT
            users.id,
            users.name,
            users.username,
            users.is_active,
            users.created_at,
            users.updated_at,

            GROUP_CONCAT(
                DISTINCT roles.role_name
                ORDER BY roles.role_name
                SEPARATOR ', '
            ) AS assigned_roles

         FROM users

         LEFT JOIN user_roles
            ON user_roles.user_id =
               users.id

         LEFT JOIN roles
            ON roles.id =
               user_roles.role_id

         WHERE
         (
            ? = ''
            OR users.name LIKE CONCAT('%', ?, '%')
            OR users.username LIKE CONCAT('%', ?, '%')
         )

         AND
         (
            ? = -1
            OR users.is_active = ?
         )

         GROUP BY
            users.id,
            users.name,
            users.username,
            users.is_active,
            users.created_at,
            users.updated_at

         ORDER BY
            users.name ASC,
            users.id ASC"
    );

    $userListStatement->bind_param(
        'sssii',
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $statusValue,
        $statusValue
    );

    $userListStatement->execute();

    $userListResult =
        $userListStatement->get_result();

    while (
        $userRow =
        $userListResult->fetch_assoc()
    ) {
        $users[] = $userRow;
    }

    $userListStatement->close();
} catch (Throwable $error) {
    error_log(
        'FieldTrack manage users error: ' .
        $error->getMessage()
    );

    $dataError =
        'The user account data could not be loaded.';
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

    <title>Manage Users | FieldTrack</title>

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

        .users-page {
            width: min(1450px, calc(100% - 32px));
            margin: 30px auto;
        }

        .users-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .users-header h1 {
            margin: 0 0 8px;
        }

        .users-header p {
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

        .page-button.danger {
            background: #dc2626;
        }

        .page-button.success {
            background: #16a34a;
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

        .form-card,
        .filter-card,
        .table-card {
            margin-bottom: 24px;
            padding: 22px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.08);
        }

        .form-card h2,
        .filter-card h2,
        .table-card h2 {
            margin: 0 0 18px;
        }

        .form-grid,
        .filter-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(180px, 1fr));
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

        .form-field input,
        .form-field select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            font: inherit;
        }

        .form-actions {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            margin-top: 17px;
            flex-wrap: wrap;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            min-width: 1100px;
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
            color: #64748b;
            font-size: 13px;
        }

        .role-badge {
            display: inline-block;
            margin: 2px;
            padding: 6px 9px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 10px;
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

        .row-actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .small-button {
            display: inline-block;
            padding: 8px 11px;
            border: 0;
            border-radius: 7px;
            color: #ffffff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .edit-button {
            background: #2563eb;
        }

        .activate-button {
            background: #16a34a;
        }

        .deactivate-button {
            background: #dc2626;
        }

        .empty-message {
            padding: 35px;
            text-align: center;
            color: #64748b;
        }

        .password-note {
            margin-top: 6px;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 1000px) {
            .form-grid,
            .filter-grid {
                grid-template-columns:
                    repeat(2, minmax(180px, 1fr));
            }
        }

        @media (max-width: 650px) {
            .users-page {
                width: calc(100% - 20px);
                margin-top: 18px;
            }

            .users-header {
                flex-direction: column;
            }

            .form-grid,
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .form-actions .page-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<main class="users-page">

    <header class="users-header">
        <div>
            <h1>Manage Users</h1>

            <p>
                Create, update, activate and deactivate
                FieldTrack user accounts.
            </p>

            <p>
                Logged in as
                <?= escapeManageUserValue(
                    $currentAdminName
                ) ?>
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
        <div class="message <?= escapeManageUserValue($messageClass) ?>">
            <?= escapeManageUserValue($messageText) ?>
        </div>
    <?php endif; ?>

    <?php if ($dataError !== ''): ?>
        <div class="message error-message">
            <?= escapeManageUserValue($dataError) ?>
        </div>
    <?php endif; ?>

    <section class="form-card">

        <h2>
            <?= $editUser
                ? 'Edit User Account'
                : 'Create User Account' ?>
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="form_action"
                value="<?= $editUser
                    ? 'update_user'
                    : 'create_user' ?>"
            >

            <?php if ($editUser): ?>
                <input
                    type="hidden"
                    name="user_id"
                    value="<?= (int) $editUser['id'] ?>"
                >
            <?php endif; ?>

            <div class="form-grid">

                <div class="form-field">
                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="100"
                        required
                        value="<?= escapeManageUserValue(
                            $editUser['name'] ?? ''
                        ) ?>"
                    >
                </div>

                <div class="form-field">
                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        maxlength="100"
                        required
                        value="<?= escapeManageUserValue(
                            $editUser['username'] ?? ''
                        ) ?>"
                    >
                </div>

                <div class="form-field">
                    <label for="password">
                        <?= $editUser
                            ? 'New Password'
                            : 'Password' ?>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        maxlength="255"
                        <?= $editUser ? '' : 'required' ?>
                    >

                    <?php if ($editUser): ?>
                        <div class="password-note">
                            Leave blank to keep the current password.
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($editUser): ?>

                    <div class="form-field">
                        <label for="is_active">
                            Account Status
                        </label>

                        <select
                            id="is_active"
                            name="is_active"
                        >
                            <option
                                value="1"
                                <?= (int) $editUser['is_active'] === 1
                                    ? 'selected'
                                    : '' ?>
                            >
                                Active
                            </option>

                            <option
                                value="0"
                                <?= (int) $editUser['is_active'] === 0
                                    ? 'selected'
                                    : '' ?>
                            >
                                Inactive
                            </option>
                        </select>
                    </div>

                <?php endif; ?>

            </div>

            <div class="form-actions">
                <button
                    class="page-button"
                    type="submit"
                >
                    <?= $editUser
                        ? 'Update User'
                        : 'Create User' ?>
                </button>

                <?php if ($editUser): ?>
                    <a
                        class="page-button secondary"
                        href="manage_users.php"
                    >
                        Cancel Editing
                    </a>
                <?php endif; ?>
            </div>

        </form>

    </section>

    <section class="filter-card">

        <h2>Search Users</h2>

        <form method="GET">

            <div class="filter-grid">

                <div class="form-field">
                    <label for="search">
                        Name or Username
                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        maxlength="100"
                        value="<?= escapeManageUserValue(
                            $searchTerm
                        ) ?>"
                    >
                </div>

                <div class="form-field">
                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >
                        <option
                            value="all"
                            <?= $statusFilter === 'all'
                                ? 'selected'
                                : '' ?>
                        >
                            All Accounts
                        </option>

                        <option
                            value="active"
                            <?= $statusFilter === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $statusFilter === 'inactive'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
                        </option>
                    </select>
                </div>

            </div>

            <div class="form-actions">
                <button
                    class="page-button"
                    type="submit"
                >
                    Apply Filters
                </button>

                <a
                    class="page-button secondary"
                    href="manage_users.php"
                >
                    Reset
                </a>
            </div>

        </form>

    </section>

    <section class="table-card">

        <h2>User Accounts</h2>

        <div class="table-wrapper">

            <table class="users-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Assigned Roles</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($users)): ?>

                    <tr>
                        <td
                            colspan="7"
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
                                    <?= escapeManageUserValue(
                                        $user['name']
                                    ) ?>
                                </strong>

                                <div class="username">
                                    @<?= escapeManageUserValue(
                                        $user['username']
                                    ) ?>
                                </div>
                            </td>

                            <td>
                                <?php
                                $assignedRoles =
                                    (string) (
                                        $user['assigned_roles'] ?? ''
                                    );

                                if ($assignedRoles === ''):
                                ?>

                                    No role assigned

                                <?php else: ?>

                                    <?php foreach (
                                        explode(
                                            ', ',
                                            $assignedRoles
                                        )
                                        as $assignedRole
                                    ): ?>

                                        <span class="role-badge">
                                            <?= escapeManageUserValue(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $assignedRole
                                                    )
                                                )
                                            ) ?>
                                        </span>

                                    <?php endforeach; ?>

                                <?php endif; ?>
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
                                <?= escapeManageUserValue(
                                    $user['created_at']
                                ) ?>
                            </td>

                            <td>
                                <?= escapeManageUserValue(
                                    $user['updated_at']
                                ) ?>
                            </td>

                            <td>
                                <div class="row-actions">

                                    <a
                                        class="small-button edit-button"
                                        href="manage_users.php?edit=<?= (int) $user['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <?php if (
                                        (int) $user['is_active'] === 1
                                    ): ?>

                                        <?php if (
                                            (int) $user['id'] !==
                                            $currentAdminId
                                        ): ?>

                                            <form method="POST">
                                                <input
                                                    type="hidden"
                                                    name="form_action"
                                                    value="change_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= (int) $user['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="new_status"
                                                    value="0"
                                                >

                                                <button
                                                    class="small-button deactivate-button"
                                                    type="submit"
                                                    onclick="return confirm('Deactivate this user account?');"
                                                >
                                                    Deactivate
                                                </button>
                                            </form>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="form_action"
                                                value="change_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= (int) $user['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="new_status"
                                                value="1"
                                            >

                                            <button
                                                class="small-button activate-button"
                                                type="submit"
                                            >
                                                Activate
                                            </button>
                                        </form>

                                    <?php endif; ?>

                                </div>
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