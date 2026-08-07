<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin']);
requirePermission('roles.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);

    if (
        $userId !== false && $userId !== null && $userId > 0 &&
        $roleId !== false && $roleId !== null && $roleId > 0
    ) {
        try {
            $conn->begin_transaction();

            $delete = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $delete->bind_param('i', $userId);
            $delete->execute();
            $delete->close();

            $insert = $conn->prepare(
                "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)"
            );
            $insert->bind_param('ii', $userId, $roleId);
            $insert->execute();
            $insert->close();

            $conn->commit();

            if ((int) $userId === currentUserId()) {
                clearCurrentSession();
                redirectTo('login.php?error=role_changed');
            }

            redirectTo('manage_roles.php?msg=' . rawurlencode('User role updated.'));
        } catch (Throwable $error) {
            try { $conn->rollback(); } catch (Throwable) {}
            redirectTo('manage_roles.php?msg=' . rawurlencode('Role update failed: ' . $error->getMessage()));
        }
    }

    redirectTo('manage_roles.php?msg=' . rawurlencode('Invalid role assignment.'));
}

$roles = [];
$result = $conn->query("SELECT id, role_name, description FROM roles ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

$users = [];
$result = $conn->query(
    "SELECT
        u.id,
        u.name,
        u.username,
        r.id AS role_id,
        r.role_name
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     ORDER BY u.id"
);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$permissions = [];
$result = $conn->query(
    "SELECT
        r.role_name,
        p.permission_name
     FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     ORDER BY r.role_name, p.permission_name"
);
while ($row = $result->fetch_assoc()) {
    $permissions[] = $row;
}

$message = trim((string) ($_GET['msg'] ?? ''));
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
    <div><h1>FieldTrack</h1><p>Manage Roles and Permissions</p></div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>

    <section class="panel">
        <h2>User Role Assignment</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>User</th><th>Current Role</th><th>Change Role</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= h($user['name']) ?> (@<?= h($user['username']) ?>)</td>
                        <td><?= h($user['role_name'] ?? 'No role') ?></td>
                        <td>
                            <form method="POST" action="<?= h(appUrl('manage_roles.php')) ?>" class="actions">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <select name="role_id" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option
                                            value="<?= (int) $role['id'] ?>"
                                            <?= (int) ($user['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>
                                        >
                                            <?= h($role['role_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="approve-button" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Role Permissions</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Role</th><th>Permission</th></tr></thead>
                <tbody>
                <?php foreach ($permissions as $permission): ?>
                    <tr>
                        <td><?= h($permission['role_name']) ?></td>
                        <td><?= h($permission['permission_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
