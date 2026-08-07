<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin']);
requirePermission('users.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $action = trim((string) ($_POST['action'] ?? ''));

    if (
        $userId !== false &&
        $userId !== null &&
        $userId > 0 &&
        in_array($action, ['activate', 'deactivate'], true)
    ) {
        if ((int) $userId === currentUserId() && $action === 'deactivate') {
            redirectTo('manage_users.php?msg=' . rawurlencode('You cannot deactivate your own account.'));
        }

        $activeValue = $action === 'activate' ? 1 : 0;

        $stmt = $conn->prepare(
            "UPDATE users SET is_active = ? WHERE id = ?"
        );
        $stmt->bind_param('ii', $activeValue, $userId);
        $stmt->execute();
        $stmt->close();

        redirectTo('manage_users.php?msg=' . rawurlencode('User status updated.'));
    }

    redirectTo('manage_users.php?msg=' . rawurlencode('Invalid user action.'));
}

$result = $conn->query(
    "SELECT
        u.id,
        u.name,
        u.username,
        u.is_active,
        GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     GROUP BY u.id, u.name, u.username, u.is_active
     ORDER BY u.id"
);

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$message = trim((string) ($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
    <div><h1>FieldTrack</h1><p>Manage Users</p></div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>

    <section class="panel">
        <h2>User Accounts</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Role</th><th>Active</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= h($user['name']) ?></td>
                        <td><?= h($user['username']) ?></td>
                        <td><?= h($user['roles'] ?? 'No role') ?></td>
                        <td><?= (int) $user['is_active'] === 1 ? 'Yes' : 'No' ?></td>
                        <td>
                            <form method="POST" action="<?= h(appUrl('manage_users.php')) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <?php if ((int) $user['is_active'] === 1): ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <button class="reject-button" type="submit">Deactivate</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="activate">
                                    <button class="approve-button" type="submit">Activate</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
