<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['system_admin']);
requirePermission('roles.manage');

function roleBack(string $message): never
{
    redirectTo('manage_roles.php?msg=' . rawurlencode($message));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);

    if (!$userId || !$roleId) roleBack('Invalid role assignment.');

    $checkRole = $conn->prepare("SELECT role_name FROM roles WHERE id = ? LIMIT 1");
    $checkRole->bind_param('i', $roleId);
    $checkRole->execute();
    $roleRow = $checkRole->get_result()->fetch_assoc();
    $checkRole->close();

    if (!$roleRow) roleBack('Selected role does not exist.');

    try {
        $conn->begin_transaction();

        $delete = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $delete->bind_param('i', $userId);
        $delete->execute();
        $delete->close();

        $insert = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $insert->bind_param('ii', $userId, $roleId);
        $insert->execute();
        $insert->close();

        /*
         * Keep reporting assignments consistent with the user's new role.
         * If an account is no longer in a hierarchy role, remove stale links.
         */
        $roleName = (string)$roleRow['role_name'];

        if ($roleName !== 'field_officer') {
            $clean = $conn->prepare("DELETE FROM officer_assignments WHERE field_officer_id = ?");
            $clean->bind_param('i', $userId);
            $clean->execute();
            $clean->close();
        }

        if ($roleName !== 'admin_officer') {
            $clean = $conn->prepare("DELETE FROM officer_assignments WHERE admin_officer_id = ?");
            $clean->bind_param('i', $userId);
            $clean->execute();
            $clean->close();
        }

        if ($roleName !== 'admin_manager') {
            $clean = $conn->prepare("DELETE FROM officer_assignments WHERE admin_manager_id = ?");
            $clean->bind_param('i', $userId);
            $clean->execute();
            $clean->close();
        }

        $actorId = currentUserId();
        $details = 'User #' . $userId . ' role changed to ' . $roleName;
        $ip = getClientIpAddress();

        $audit = $conn->prepare(
            "INSERT INTO audit_logs
                (user_id, action, target_type, target_id, details, ip_address)
             VALUES (?, 'USER_ROLE_CHANGED', 'user', ?, ?, ?)"
        );
        $audit->bind_param('iiss', $actorId, $userId, $details, $ip);
        $audit->execute();
        $audit->close();

        $conn->commit();

        if ((int)$userId === currentUserId()) {
            clearCurrentSession();
            redirectTo('login.php?error=role_changed');
        }

        roleBack('User role updated successfully.');
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable) {}
        error_log('Role update failed: ' . $e->getMessage());
        roleBack('Role update failed.');
    }
}

$roles = [];
$result = $conn->query("SELECT id, role_name, description FROM roles ORDER BY id");
while ($row = $result->fetch_assoc()) $roles[] = $row;

$users = [];
$result = $conn->query(
    "SELECT u.id, u.name, u.username, r.id AS role_id, r.role_name
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     ORDER BY u.name"
);
while ($row = $result->fetch_assoc()) $users[] = $row;

$permissionMatrix = [];
$result = $conn->query(
    "SELECT r.role_name, p.permission_name
     FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     ORDER BY r.role_name, p.permission_name"
);
while ($row = $result->fetch_assoc()) {
    $permissionMatrix[(string)$row['role_name']][] = (string)$row['permission_name'];
}

$message = trim((string)($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Roles</title>
<link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
<div><h1>FieldTrack</h1><p>Manage Roles</p></div>
<div class="topbar-links">
<a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a>
<a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
</div>
</header>

<main class="container">
<?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>

<section class="panel">
<h2>User Role Assignment</h2>
<p>Each FieldTrack account should have one main operational role.</p>
<div class="table-wrap">
<table>
<thead><tr><th>User</th><th>Current Role</th><th>Change Role</th></tr></thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
<td><?= h($user['name']) ?> (@<?= h($user['username']) ?>)</td>
<td><?= h($user['role_name'] ?? 'No role') ?></td>
<td>
<form method="POST" class="actions">
<?= csrfInput() ?>
<input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
<select name="role_id" required>
<?php foreach ($roles as $role): ?>
<option value="<?= (int)$role['id'] ?>" <?= (int)($user['role_id'] ?? 0) === (int)$role['id'] ? 'selected' : '' ?>>
<?= h($role['role_name']) ?>
</option>
<?php endforeach; ?>
</select>
<button class="approve-button" type="submit">Save Role</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

<section class="panel">
<h2>Role Permission Matrix</h2>
<?php foreach ($roles as $role): ?>
<div class="submission-card">
<div>
<h3><?= h($role['role_name']) ?></h3>
<p><?= h($role['description'] ?? '') ?></p>
<ul>
<?php foreach ($permissionMatrix[(string)$role['role_name']] ?? [] as $permission): ?>
<li><?= h($permission) ?></li>
<?php endforeach; ?>
</ul>
</div>
</div>
<?php endforeach; ?>
</section>
</main>
</body>
</html>
