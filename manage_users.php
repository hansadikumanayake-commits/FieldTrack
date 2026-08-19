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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users</title>
<link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar"><div><h1>FieldTrack</h1><p>Manage Users</p></div><div class="topbar-links"><a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a><a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a></div></header>
<main class="container">
<?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>
<section class="panel">
<h2>Create User</h2>
<form method="POST" class="form-grid">
<?= csrfInput() ?><input type="hidden" name="action" value="create">
<div><label>Name</label><input type="text" name="name" maxlength="100" required></div>
<div><label>Username</label><input type="text" name="username" maxlength="100" required></div>
<div><label>Password</label><input type="password" name="password" minlength="6" required></div>
<div><label>Role</label><select name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= h($role['role_name']) ?></option><?php endforeach; ?></select></div>
<div class="form-actions"><button class="approve-button" type="submit">Create User</button></div>
</form>
</section>
<section class="panel">
<h2>User Accounts</h2>
<div class="table-wrap"><table>
<thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Active</th><th>Status</th><th>Password Reset</th></tr></thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
<td><?= h($user['name']) ?></td><td><?= h($user['username']) ?></td><td><?= h($user['roles'] ?? 'No role') ?></td><td><?= (int) $user['is_active'] === 1 ? 'Yes' : 'No' ?></td>
<td><form method="POST"><?= csrfInput() ?><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><?php if ((int) $user['is_active'] === 1): ?><input type="hidden" name="action" value="deactivate"><button class="reject-button" type="submit">Deactivate</button><?php else: ?><input type="hidden" name="action" value="activate"><button class="approve-button" type="submit">Activate</button><?php endif; ?></form></td>
<td><form method="POST" style="display:flex;gap:6px"><?= csrfInput() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><input type="password" name="new_password" minlength="6" placeholder="New password" required><button type="submit">Reset</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
</main>
</body>
</html>