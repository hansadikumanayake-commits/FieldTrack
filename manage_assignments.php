<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
*/

requireSystemAdmin();
requirePermission('assignments.manage');

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function escapeAssignmentValue(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectAssignments(string $message): never
{
    header(
        'Location: manage_assignments.php?msg=' .
        rawurlencode($message)
    );

    exit();
}

function formatAssignmentDateTime(?string $dateTime): string
{
    if (
        $dateTime === null ||
        $dateTime === ''
    ) {
        return '—';
    }

    try {
        return (new DateTime($dateTime))
            ->format('d M Y, h:i A');
    } catch (Throwable) {
        return $dateTime;
    }
}

/*
|--------------------------------------------------------------------------
| Load active users with a particular role
|--------------------------------------------------------------------------
*/

function loadAssignmentUsersByRole(
    mysqli $conn,
    string $roleName
): array {
    $users = [];

    $statement = $conn->prepare(
        "SELECT DISTINCT
            users.id,
            users.name,
            users.username
         FROM users

         INNER JOIN user_roles
            ON user_roles.user_id =
               users.id

         INNER JOIN roles
            ON roles.id =
               user_roles.role_id

         WHERE roles.role_name = ?
         AND users.is_active = 1

         ORDER BY
            users.name ASC,
            users.username ASC"
    );

    $statement->bind_param(
        's',
        $roleName
    );

    $statement->execute();

    $result = $statement->get_result();

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $statement->close();

    return $users;
}

/*
|--------------------------------------------------------------------------
| Confirm user is active and has required role
|--------------------------------------------------------------------------
*/

function findAssignmentUser(
    mysqli $conn,
    int $userId,
    string $requiredRole
): ?array {
    $statement = $conn->prepare(
        "SELECT DISTINCT
            users.id,
            users.name,
            users.username
         FROM users

         INNER JOIN user_roles
            ON user_roles.user_id =
               users.id

         INNER JOIN roles
            ON roles.id =
               user_roles.role_id

         WHERE users.id = ?
         AND users.is_active = 1
         AND roles.role_name = ?

         LIMIT 1"
    );

    $statement->bind_param(
        'is',
        $userId,
        $requiredRole
    );

    $statement->execute();

    $user = $statement
        ->get_result()
        ->fetch_assoc();

    $statement->close();

    return $user ?: null;
}

/*
|--------------------------------------------------------------------------
| Record assignment activity
|--------------------------------------------------------------------------
*/

function recordAssignmentAudit(
    mysqli $conn,
    int $performedBy,
    string $action,
    int $assignmentId,
    string $details
): void {
    $targetType = 'officer_assignment';
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
        $assignmentId,
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
| Process submitted forms
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim(
        (string) ($_POST['form_action'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | Create or update assignment
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'save_assignment') {
        $fieldOfficerIdValue = trim(
            (string) (
                $_POST['field_officer_id'] ?? ''
            )
        );

        $adminOfficerIdValue = trim(
            (string) (
                $_POST['admin_officer_id'] ?? ''
            )
        );

        $adminManagerIdValue = trim(
            (string) (
                $_POST['admin_manager_id'] ?? ''
            )
        );

        if (
            $fieldOfficerIdValue === '' ||
            $adminOfficerIdValue === '' ||
            $adminManagerIdValue === '' ||
            !ctype_digit($fieldOfficerIdValue) ||
            !ctype_digit($adminOfficerIdValue) ||
            !ctype_digit($adminManagerIdValue)
        ) {
            redirectAssignments(
                'invalid_selection'
            );
        }

        $fieldOfficerId =
            (int) $fieldOfficerIdValue;

        $adminOfficerId =
            (int) $adminOfficerIdValue;

        $adminManagerId =
            (int) $adminManagerIdValue;

        if (
            $fieldOfficerId < 1 ||
            $adminOfficerId < 1 ||
            $adminManagerId < 1
        ) {
            redirectAssignments(
                'invalid_selection'
            );
        }

        /*
         * One person cannot fill two positions
         * in the same approval chain.
         */

        if (
            $fieldOfficerId === $adminOfficerId ||
            $fieldOfficerId === $adminManagerId ||
            $adminOfficerId === $adminManagerId
        ) {
            redirectAssignments(
                'same_user_selected'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Confirm users and roles
        |--------------------------------------------------------------------------
        */

        $fieldOfficer = findAssignmentUser(
            $conn,
            $fieldOfficerId,
            'field_officer'
        );

        $adminOfficer = findAssignmentUser(
            $conn,
            $adminOfficerId,
            'admin_officer'
        );

        $adminManager = findAssignmentUser(
            $conn,
            $adminManagerId,
            'admin_manager'
        );

        if (!$fieldOfficer) {
            redirectAssignments(
                'invalid_field_officer'
            );
        }

        if (!$adminOfficer) {
            redirectAssignments(
                'invalid_admin_officer'
            );
        }

        if (!$adminManager) {
            redirectAssignments(
                'invalid_admin_manager'
            );
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();

            $transactionStarted = true;

            /*
            |--------------------------------------------------------------------------
            | Check whether Field Officer already has an assignment
            |--------------------------------------------------------------------------
            */

            $existingStatement = $conn->prepare(
                "SELECT
                    id,
                    admin_officer_id,
                    admin_manager_id
                 FROM officer_assignments
                 WHERE field_officer_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            $existingStatement->bind_param(
                'i',
                $fieldOfficerId
            );

            $existingStatement->execute();

            $existingAssignment =
                $existingStatement
                    ->get_result()
                    ->fetch_assoc();

            $existingStatement->close();

            if ($existingAssignment) {
                /*
                |--------------------------------------------------------------------------
                | Update existing assignment
                |--------------------------------------------------------------------------
                */

                $assignmentId = (int) (
                    $existingAssignment['id']
                );

                $updateStatement = $conn->prepare(
                    "UPDATE officer_assignments
                     SET
                        admin_officer_id = ?,
                        admin_manager_id = ?
                     WHERE id = ?"
                );

                $updateStatement->bind_param(
                    'iii',
                    $adminOfficerId,
                    $adminManagerId,
                    $assignmentId
                );

                $updateStatement->execute();
                $updateStatement->close();

                recordAssignmentAudit(
                    $conn,
                    $currentAdminId,
                    'OFFICER_ASSIGNMENT_UPDATED',
                    $assignmentId,
                    'Updated assignment: Field Officer @' .
                    $fieldOfficer['username'] .
                    ' reports to Admin Officer @' .
                    $adminOfficer['username'] .
                    ' and Admin Manager @' .
                    $adminManager['username'] .
                    '.'
                );

                $messageCode =
                    'assignment_updated';
            } else {
                /*
                |--------------------------------------------------------------------------
                | Create new assignment
                |--------------------------------------------------------------------------
                */

                $insertStatement = $conn->prepare(
                    "INSERT INTO officer_assignments
                    (
                        field_officer_id,
                        admin_officer_id,
                        admin_manager_id
                    )
                    VALUES (?, ?, ?)"
                );

                $insertStatement->bind_param(
                    'iii',
                    $fieldOfficerId,
                    $adminOfficerId,
                    $adminManagerId
                );

                $insertStatement->execute();

                $assignmentId =
                    (int) $conn->insert_id;

                $insertStatement->close();

                recordAssignmentAudit(
                    $conn,
                    $currentAdminId,
                    'OFFICER_ASSIGNMENT_CREATED',
                    $assignmentId,
                    'Created assignment: Field Officer @' .
                    $fieldOfficer['username'] .
                    ' reports to Admin Officer @' .
                    $adminOfficer['username'] .
                    ' and Admin Manager @' .
                    $adminManager['username'] .
                    '.'
                );

                $messageCode =
                    'assignment_created';
            }

            $conn->commit();

            $transactionStarted = false;

            redirectAssignments(
                $messageCode
            );
        } catch (Throwable $error) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable) {
                    // Preserve the original error.
                }
            }

            error_log(
                'FieldTrack save assignment error: ' .
                $error->getMessage()
            );

            redirectAssignments(
                'assignment_save_failed'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete assignment
    |--------------------------------------------------------------------------
    */

    if ($formAction === 'delete_assignment') {
        $assignmentIdValue = trim(
            (string) (
                $_POST['assignment_id'] ?? ''
            )
        );

        if (
            $assignmentIdValue === '' ||
            !ctype_digit($assignmentIdValue)
        ) {
            redirectAssignments(
                'invalid_assignment'
            );
        }

        $assignmentId =
            (int) $assignmentIdValue;

        if ($assignmentId < 1) {
            redirectAssignments(
                'invalid_assignment'
            );
        }

        $transactionStarted = false;

        try {
            $conn->begin_transaction();

            $transactionStarted = true;

            /*
            |--------------------------------------------------------------------------
            | Load assignment before deleting
            |--------------------------------------------------------------------------
            */

            $assignmentStatement = $conn->prepare(
                "SELECT
                    officer_assignments.id,

                    field_officer.username
                        AS field_officer_username,

                    admin_officer.username
                        AS admin_officer_username,

                    admin_manager.username
                        AS admin_manager_username

                 FROM officer_assignments

                 INNER JOIN users AS field_officer
                    ON field_officer.id =
                       officer_assignments.field_officer_id

                 INNER JOIN users AS admin_officer
                    ON admin_officer.id =
                       officer_assignments.admin_officer_id

                 INNER JOIN users AS admin_manager
                    ON admin_manager.id =
                       officer_assignments.admin_manager_id

                 WHERE officer_assignments.id = ?

                 LIMIT 1
                 FOR UPDATE"
            );

            $assignmentStatement->bind_param(
                'i',
                $assignmentId
            );

            $assignmentStatement->execute();

            $assignment = $assignmentStatement
                ->get_result()
                ->fetch_assoc();

            $assignmentStatement->close();

            if (!$assignment) {
                $conn->rollback();

                $transactionStarted = false;

                redirectAssignments(
                    'invalid_assignment'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Delete assignment
            |--------------------------------------------------------------------------
            */

            $deleteStatement = $conn->prepare(
                "DELETE FROM officer_assignments
                 WHERE id = ?"
            );

            $deleteStatement->bind_param(
                'i',
                $assignmentId
            );

            $deleteStatement->execute();
            $deleteStatement->close();

            recordAssignmentAudit(
                $conn,
                $currentAdminId,
                'OFFICER_ASSIGNMENT_DELETED',
                $assignmentId,
                'Deleted assignment for Field Officer @' .
                $assignment[
                    'field_officer_username'
                ] .
                '. Previous Admin Officer: @' .
                $assignment[
                    'admin_officer_username'
                ] .
                '. Previous Admin Manager: @' .
                $assignment[
                    'admin_manager_username'
                ] .
                '.'
            );

            $conn->commit();

            $transactionStarted = false;

            redirectAssignments(
                'assignment_deleted'
            );
        } catch (Throwable $error) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable) {
                    // Preserve the original error.
                }
            }

            error_log(
                'FieldTrack delete assignment error: ' .
                $error->getMessage()
            );

            redirectAssignments(
                'assignment_delete_failed'
            );
        }
    }

    redirectAssignments(
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
    'assignment_created' => [
        'The officer assignment was created successfully.',
        'success-message'
    ],

    'assignment_updated' => [
        'The officer assignment was updated successfully.',
        'success-message'
    ],

    'assignment_deleted' => [
        'The officer assignment was deleted successfully.',
        'success-message'
    ],

    'invalid_selection' => [
        'Select a valid Field Officer, Admin Officer and Admin Manager.',
        'error-message'
    ],

    'same_user_selected' => [
        'The same user cannot be selected for multiple positions.',
        'error-message'
    ],

    'invalid_field_officer' => [
        'The selected user is not an active Field Officer.',
        'error-message'
    ],

    'invalid_admin_officer' => [
        'The selected user is not an active Admin Officer.',
        'error-message'
    ],

    'invalid_admin_manager' => [
        'The selected user is not an active Admin Manager.',
        'error-message'
    ],

    'invalid_assignment' => [
        'The selected officer assignment is invalid.',
        'error-message'
    ],

    'assignment_save_failed' => [
        'The officer assignment could not be saved.',
        'error-message'
    ],

    'assignment_delete_failed' => [
        'The officer assignment could not be deleted.',
        'error-message'
    ],

    'invalid_action' => [
        'The requested assignment action is invalid.',
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
| Load dropdown users
|--------------------------------------------------------------------------
*/

$fieldOfficers = [];
$adminOfficers = [];
$adminManagers = [];
$assignments = [];
$editAssignment = null;
$dataError = '';

try {
    $fieldOfficers = loadAssignmentUsersByRole(
        $conn,
        'field_officer'
    );

    $adminOfficers = loadAssignmentUsersByRole(
        $conn,
        'admin_officer'
    );

    $adminManagers = loadAssignmentUsersByRole(
        $conn,
        'admin_manager'
    );

    /*
    |--------------------------------------------------------------------------
    | Load assignment selected for editing
    |--------------------------------------------------------------------------
    */

    $editIdValue = trim(
        (string) ($_GET['edit'] ?? '')
    );

    if (
        $editIdValue !== '' &&
        ctype_digit($editIdValue)
    ) {
        $editId = (int) $editIdValue;

        if ($editId > 0) {
            $editStatement = $conn->prepare(
                "SELECT
                    id,
                    field_officer_id,
                    admin_officer_id,
                    admin_manager_id
                 FROM officer_assignments
                 WHERE id = ?
                 LIMIT 1"
            );

            $editStatement->bind_param(
                'i',
                $editId
            );

            $editStatement->execute();

            $editAssignment = $editStatement
                ->get_result()
                ->fetch_assoc();

            $editStatement->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Load all current assignments
    |--------------------------------------------------------------------------
    */

    $assignmentResult = $conn->query(
        "SELECT
            officer_assignments.id,
            officer_assignments.field_officer_id,
            officer_assignments.admin_officer_id,
            officer_assignments.admin_manager_id,
            officer_assignments.assigned_at,
            officer_assignments.updated_at,

            field_officer.name
                AS field_officer_name,

            field_officer.username
                AS field_officer_username,

            field_officer.is_active
                AS field_officer_active,

            admin_officer.name
                AS admin_officer_name,

            admin_officer.username
                AS admin_officer_username,

            admin_officer.is_active
                AS admin_officer_active,

            admin_manager.name
                AS admin_manager_name,

            admin_manager.username
                AS admin_manager_username,

            admin_manager.is_active
                AS admin_manager_active

         FROM officer_assignments

         INNER JOIN users AS field_officer
            ON field_officer.id =
               officer_assignments.field_officer_id

         INNER JOIN users AS admin_officer
            ON admin_officer.id =
               officer_assignments.admin_officer_id

         INNER JOIN users AS admin_manager
            ON admin_manager.id =
               officer_assignments.admin_manager_id

         ORDER BY
            field_officer.name ASC,
            officer_assignments.id ASC"
    );

    while (
        $assignmentRow =
        $assignmentResult->fetch_assoc()
    ) {
        $assignments[] =
            $assignmentRow;
    }
} catch (Throwable $error) {
    error_log(
        'FieldTrack manage assignments error: ' .
        $error->getMessage()
    );

    $dataError =
        'The officer assignment data could not be loaded.';
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

    <title>
        Manage Officer Assignments | FieldTrack
    </title>

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

        .assignments-page {
            width: min(1450px, calc(100% - 32px));
            margin: 30px auto;
        }

        .assignments-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .assignments-header h1 {
            margin: 0 0 8px;
        }

        .assignments-header p {
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

        .assignment-form-card,
        .assignment-table-card {
            margin-bottom: 24px;
            padding: 22px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow:
                0 4px 16px rgba(15, 23, 42, 0.08);
        }

        .assignment-form-card h2,
        .assignment-table-card h2 {
            margin: 0 0 18px;
        }

        .assignment-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(220px, 1fr));
            gap: 16px;
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
            display: flex;
            gap: 10px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .assignment-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
        }

        .assignment-table th,
        .assignment-table td {
            padding: 13px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        .assignment-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 13px;
            text-transform: uppercase;
        }

        .assignment-table tbody tr:hover {
            background: #f8fafc;
        }

        .user-name {
            display: block;
            font-weight: 700;
        }

        .username {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .status-badge {
            display: inline-block;
            margin-top: 6px;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 11px;
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
            gap: 8px;
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

        .delete-button {
            background: #dc2626;
        }

        .empty-message {
            padding: 35px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 950px) {
            .assignment-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 650px) {
            .assignments-page {
                width: calc(100% - 20px);
                margin-top: 18px;
            }

            .assignments-header {
                flex-direction: column;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .page-button {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<main class="assignments-page">

    <header class="assignments-header">

        <div>
            <h1>Manage Officer Assignments</h1>

            <p>
                Connect each Field Officer to an Admin Officer
                and an Admin Manager.
            </p>

            <p>
                Logged in as
                <?= escapeAssignmentValue(
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

        <div
            class="message
            <?= escapeAssignmentValue(
                $messageClass
            ) ?>"
        >
            <?= escapeAssignmentValue(
                $messageText
            ) ?>
        </div>

    <?php endif; ?>

    <?php if ($dataError !== ''): ?>

        <div class="message error-message">
            <?= escapeAssignmentValue(
                $dataError
            ) ?>
        </div>

    <?php endif; ?>

    <section class="assignment-form-card">

        <h2>
            <?= $editAssignment
                ? 'Edit Officer Assignment'
                : 'Create Officer Assignment' ?>
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="form_action"
                value="save_assignment"
            >

            <div class="assignment-grid">

                <div class="form-field">

                    <label for="field_officer_id">
                        Field Officer
                    </label>

                    <select
                        id="field_officer_id"
                        name="field_officer_id"
                        required
                    >

                        <option value="">
                            Select Field Officer
                        </option>

                        <?php foreach (
                            $fieldOfficers
                            as $fieldOfficer
                        ): ?>

                            <option
                                value="<?= (int) $fieldOfficer['id'] ?>"
                                <?= $editAssignment &&
                                    (int) $editAssignment[
                                        'field_officer_id'
                                    ] ===
                                    (int) $fieldOfficer['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapeAssignmentValue(
                                    $fieldOfficer['name']
                                ) ?>

                                (@<?= escapeAssignmentValue(
                                    $fieldOfficer['username']
                                ) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-field">

                    <label for="admin_officer_id">
                        Admin Officer
                    </label>

                    <select
                        id="admin_officer_id"
                        name="admin_officer_id"
                        required
                    >

                        <option value="">
                            Select Admin Officer
                        </option>

                        <?php foreach (
                            $adminOfficers
                            as $adminOfficer
                        ): ?>

                            <option
                                value="<?= (int) $adminOfficer['id'] ?>"
                                <?= $editAssignment &&
                                    (int) $editAssignment[
                                        'admin_officer_id'
                                    ] ===
                                    (int) $adminOfficer['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapeAssignmentValue(
                                    $adminOfficer['name']
                                ) ?>

                                (@<?= escapeAssignmentValue(
                                    $adminOfficer['username']
                                ) ?>)
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-field">

                    <label for="admin_manager_id">
                        Admin Manager
                    </label>

                    <select
                        id="admin_manager_id"
                        name="admin_manager_id"
                        required
                    >

                        <option value="">
                            Select Admin Manager
                        </option>

                        <?php foreach (
                            $adminManagers
                            as $adminManager
                        ): ?>

                            <option
                                value="<?= (int) $adminManager['id'] ?>"
                                <?= $editAssignment &&
                                    (int) $editAssignment[
                                        'admin_manager_id'
                                    ] ===
                                    (int) $adminManager['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= escapeAssignmentValue(
                                    $adminManager['name']
                                ) ?>

                                (@<?= escapeAssignmentValue(
                                    $adminManager['username']
                                ) ?>)
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
                    <?= $editAssignment
                        ? 'Update Assignment'
                        : 'Create Assignment' ?>
                </button>

                <?php if ($editAssignment): ?>

                    <a
                        class="page-button secondary"
                        href="manage_assignments.php"
                    >
                        Cancel Editing
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>

    <section class="assignment-table-card">

        <h2>Current Officer Assignments</h2>

        <div class="table-wrapper">

            <table class="assignment-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Field Officer</th>
                        <th>Admin Officer</th>
                        <th>Admin Manager</th>
                        <th>Assigned At</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($assignments)): ?>

                    <tr>
                        <td
                            colspan="7"
                            class="empty-message"
                        >
                            No officer assignments were found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $assignments
                        as $assignment
                    ): ?>

                        <tr>

                            <td>
                                #<?= (int) $assignment['id'] ?>
                            </td>

                            <td>
                                <span class="user-name">
                                    <?= escapeAssignmentValue(
                                        $assignment[
                                            'field_officer_name'
                                        ]
                                    ) ?>
                                </span>

                                <span class="username">
                                    @<?= escapeAssignmentValue(
                                        $assignment[
                                            'field_officer_username'
                                        ]
                                    ) ?>
                                </span>

                                <span
                                    class="status-badge
                                    <?= (int) $assignment[
                                        'field_officer_active'
                                    ] === 1
                                        ? 'status-active'
                                        : 'status-inactive' ?>"
                                >
                                    <?= (int) $assignment[
                                        'field_officer_active'
                                    ] === 1
                                        ? 'Active'
                                        : 'Inactive' ?>
                                </span>
                            </td>

                            <td>
                                <span class="user-name">
                                    <?= escapeAssignmentValue(
                                        $assignment[
                                            'admin_officer_name'
                                        ]
                                    ) ?>
                                </span>

                                <span class="username">
                                    @<?= escapeAssignmentValue(
                                        $assignment[
                                            'admin_officer_username'
                                        ]
                                    ) ?>
                                </span>

                                <span
                                    class="status-badge
                                    <?= (int) $assignment[
                                        'admin_officer_active'
                                    ] === 1
                                        ? 'status-active'
                                        : 'status-inactive' ?>"
                                >
                                    <?= (int) $assignment[
                                        'admin_officer_active'
                                    ] === 1
                                        ? 'Active'
                                        : 'Inactive' ?>
                                </span>
                            </td>

                            <td>
                                <span class="user-name">
                                    <?= escapeAssignmentValue(
                                        $assignment[
                                            'admin_manager_name'
                                        ]
                                    ) ?>
                                </span>

                                <span class="username">
                                    @<?= escapeAssignmentValue(
                                        $assignment[
                                            'admin_manager_username'
                                        ]
                                    ) ?>
                                </span>

                                <span
                                    class="status-badge
                                    <?= (int) $assignment[
                                        'admin_manager_active'
                                    ] === 1
                                        ? 'status-active'
                                        : 'status-inactive' ?>"
                                >
                                    <?= (int) $assignment[
                                        'admin_manager_active'
                                    ] === 1
                                        ? 'Active'
                                        : 'Inactive' ?>
                                </span>
                            </td>

                            <td>
                                <?= escapeAssignmentValue(
                                    formatAssignmentDateTime(
                                        $assignment[
                                            'assigned_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= escapeAssignmentValue(
                                    formatAssignmentDateTime(
                                        $assignment[
                                            'updated_at'
                                        ]
                                    )
                                ) ?>
                            </td>

                            <td>

                                <div class="row-actions">

                                    <a
                                        class="small-button edit-button"
                                        href="manage_assignments.php?edit=<?= (int) $assignment['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Delete this officer assignment?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="form_action"
                                            value="delete_assignment"
                                        >

                                        <input
                                            type="hidden"
                                            name="assignment_id"
                                            value="<?= (int) $assignment['id'] ?>"
                                        >

                                        <button
                                            class="small-button delete-button"
                                            type="submit"
                                        >
                                            Delete
                                        </button>

                                    </form>

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