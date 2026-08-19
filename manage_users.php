<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['system_admin']);
requirePermission('users.manage');

function usersBack(string $message): never
{
    redirectTo('manage_users.php?msg=' . rawurlencode($message));
}

function writeUserAudit(mysqli $conn, string $action, int $targetUserId, string $details): void
{
    $actorId = currentUserId();
    $ip = getClientIpAddress();

    $stmt = $conn->prepare(
        "INSERT INTO audit_logs
            (user_id, action, target_type, target_id, details, ip_address)
         VALUES (?, ?, 'user', ?, ?, ?)"
    );
    $stmt->bind_param('isiss', $actorId, $action, $targetUserId, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);

        if ($name === '' || $username === '' || strlen($password) < 6 || !$roleId) {
            usersBack('Name, username, role and a password of at least 6 characters are required.');
        }

        try {
            $conn->begin_transaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, username, password, is_active) VALUES (?, ?, ?, 1)");
            $stmt->bind_param('sss', $name, $username, $hash);
            $stmt->execute();
            $newUserId = (int) $conn->insert_id;
            $stmt->close();

            $roleStmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $roleStmt->bind_param('ii', $newUserId, $roleId);
            $roleStmt->execute();
            $roleStmt->close();

            writeUserAudit(
                $conn,
                'USER_CREATED',
                $newUserId,
                'Created user ' . $username . ' with role ID ' . (int) $roleId
            );

            $conn->commit();
            usersBack('User created successfully.');
        } catch (Throwable $error) {
            try { $conn->rollback(); } catch (Throwable) {}
            error_log('Create user error: ' . $error->getMessage());
            usersBack('User could not be created. The username may already exist.');
        }
    }

    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId || $userId < 1) usersBack('Invalid user.');

    if (in_array($action, ['activate', 'deactivate'], true)) {
        if ($userId === currentUserId() && $action === 'deactivate') {
            usersBack('You cannot deactivate your own account.');
        }
        $active = $action === 'activate' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param('ii', $active, $userId);
        $stmt->execute();
        $stmt->close();

        writeUserAudit(
            $conn,
            $action === 'activate' ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
            (int) $userId,
            'User account status changed to ' . ($active === 1 ? 'active' : 'inactive')
        );

        usersBack('User status updated.');
    }

    if ($action === 'reset_password') {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 6) usersBack('New password must contain at least 6 characters.');
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $stmt->close();

        writeUserAudit(
            $conn,
            'USER_PASSWORD_RESET',
            (int) $userId,
            'Password reset by System Administrator'
        );

        usersBack('Password reset successfully.');
    }

    usersBack('Invalid user action.');
}

$rolesResult = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name");
$roles = [];
while ($row = $rolesResult->fetch_assoc()) $roles[] = $row;

$result = $conn->query(
    "SELECT u.id, u.name, u.username, u.is_active,
            GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     GROUP BY u.id, u.name, u.username, u.is_active
     ORDER BY u.name"
);
$users = [];
while ($row = $result->fetch_assoc()) $users[] = $row;
$message = trim((string) ($_GET['msg'] ?? ''));
?>