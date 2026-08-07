<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin']);
requirePermission('assignments.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fieldOfficerId = filter_input(INPUT_POST, 'field_officer_id', FILTER_VALIDATE_INT);
    $adminOfficerId = filter_input(INPUT_POST, 'admin_officer_id', FILTER_VALIDATE_INT);
    $adminManagerId = filter_input(INPUT_POST, 'admin_manager_id', FILTER_VALIDATE_INT);

    if (
        $fieldOfficerId !== false && $fieldOfficerId !== null && $fieldOfficerId > 0 &&
        $adminOfficerId !== false && $adminOfficerId !== null && $adminOfficerId > 0 &&
        $adminManagerId !== false && $adminManagerId !== null && $adminManagerId > 0
    ) {
        $stmt = $conn->prepare(
            "INSERT INTO officer_assignments
                (field_officer_id, admin_officer_id, admin_manager_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                admin_officer_id = VALUES(admin_officer_id),
                admin_manager_id = VALUES(admin_manager_id)"
        );

        $stmt->bind_param(
            'iii',
            $fieldOfficerId,
            $adminOfficerId,
            $adminManagerId
        );
        $stmt->execute();
        $stmt->close();

        redirectTo('manage_assignments.php?msg=' . rawurlencode('Officer assignment saved.'));
    }

    redirectTo('manage_assignments.php?msg=' . rawurlencode('Invalid assignment.'));
}

function usersByRole(mysqli $conn, string $roleName): array
{
    $stmt = $conn->prepare(
        "SELECT u.id, u.name, u.username
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE r.role_name = ?
         AND u.is_active = 1
         ORDER BY u.name"
    );

    $stmt->bind_param('s', $roleName);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

$fieldOfficers = usersByRole($conn, 'field_officer');
$adminOfficers = usersByRole($conn, 'admin_officer');
$adminManagers = usersByRole($conn, 'admin_manager');

$assignments = [];
$result = $conn->query(
    "SELECT
        oa.id,
        fo.name AS field_officer,
        fo.username AS field_username,
        ao.name AS admin_officer,
        ao.username AS admin_username,
        am.name AS admin_manager,
        am.username AS manager_username
     FROM officer_assignments oa
     INNER JOIN users fo ON fo.id = oa.field_officer_id
     INNER JOIN users ao ON ao.id = oa.admin_officer_id
     INNER JOIN users am ON am.id = oa.admin_manager_id
     ORDER BY fo.name"
);
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}

$message = trim((string) ($_GET['msg'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments</title>
    <link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
    <div><h1>FieldTrack</h1><p>Manage Officer Hierarchy</p></div>
    <div class="topbar-links">
        <a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>

    <section class="panel">
        <h2>Assign Field Officer → Admin Officer → Admin Manager</h2>

        <form method="POST" action="<?= h(appUrl('manage_assignments.php')) ?>" class="form-grid">
            <div>
                <label for="field_officer_id">Field Officer</label>
                <select id="field_officer_id" name="field_officer_id" required>
                    <?php foreach ($fieldOfficers as $user): ?>
                        <option value="<?= (int) $user['id'] ?>">
                            <?= h($user['name']) ?> (@<?= h($user['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="admin_officer_id">Admin Officer</label>
                <select id="admin_officer_id" name="admin_officer_id" required>
                    <?php foreach ($adminOfficers as $user): ?>
                        <option value="<?= (int) $user['id'] ?>">
                            <?= h($user['name']) ?> (@<?= h($user['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="admin_manager_id">Admin Manager</label>
                <select id="admin_manager_id" name="admin_manager_id" required>
                    <?php foreach ($adminManagers as $user): ?>
                        <option value="<?= (int) $user['id'] ?>">
                            <?= h($user['name']) ?> (@<?= h($user['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button class="approve-button" type="submit">Save Assignment</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Current Assignments</h2>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Field Officer</th><th>Admin Officer</th><th>Admin Manager</th></tr></thead>
                <tbody>
                <?php if (count($assignments) === 0): ?>
                    <tr><td colspan="3">No assignments found.</td></tr>
                <?php endif; ?>

                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?= h($assignment['field_officer']) ?> (@<?= h($assignment['field_username']) ?>)</td>
                        <td><?= h($assignment['admin_officer']) ?> (@<?= h($assignment['admin_username']) ?>)</td>
                        <td><?= h($assignment['admin_manager']) ?> (@<?= h($assignment['manager_username']) ?>)</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
