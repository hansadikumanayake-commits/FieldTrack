<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'weekly_helpers.php';
require_once 'review_helpers.php';

requireRole(['system_admin']);

$counts = [];

foreach ([
    'users' => 'SELECT COUNT(*) AS total FROM users',
    'officers' =>
        "SELECT COUNT(DISTINCT ur.user_id) AS total
         FROM user_roles ur
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE r.role_name = 'field_officer'",
    'submissions' =>
        'SELECT COUNT(*) AS total FROM weekly_submissions',
    'audit_logs' =>
        'SELECT COUNT(*) AS total FROM audit_logs',
] as $key => $sql) {
    $row = $conn->query($sql)->fetch_assoc();
    $counts[$key] = (int) ($row['total'] ?? 0);
}

$users = $conn->query(
    "SELECT
        u.id,
        u.name,
        u.username,
        u.is_active,
        GROUP_CONCAT(
            r.role_name
            ORDER BY r.role_name
            SEPARATOR ', '
        ) AS roles
     FROM users u
     LEFT JOIN user_roles ur
        ON ur.user_id = u.id
     LEFT JOIN roles r
        ON r.id = ur.role_id
     GROUP BY
        u.id,
        u.name,
        u.username,
        u.is_active
     ORDER BY u.id"
);

$assignments = $conn->query(
    "SELECT
        oa.id,
        fo.name AS field_officer,
        ao.name AS admin_officer,
        am.name AS admin_manager
     FROM officer_assignments oa
     INNER JOIN users fo
        ON fo.id = oa.field_officer_id
     INNER JOIN users ao
        ON ao.id = oa.admin_officer_id
     INNER JOIN users am
        ON am.id = oa.admin_manager_id
     ORDER BY fo.name"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administrator Panel</title>
    <link rel="stylesheet" href="review_panel.css">
</head>
<body>
<header class="topbar">
    <div>
        <h1>FieldTrack</h1>
        <p>System Administrator Dashboard</p>
    </div>
    <div class="topbar-links">
        <a href="audit_logs.php">Audit Logs</a>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<main class="container">
    <div class="summary-grid">
        <div class="summary-card">Users<strong><?= $counts['users'] ?></strong></div>
        <div class="summary-card">Field Officers<strong><?= $counts['officers'] ?></strong></div>
        <div class="summary-card">Submissions<strong><?= $counts['submissions'] ?></strong></div>
        <div class="summary-card">Audit Logs<strong><?= $counts['audit_logs'] ?></strong></div>
    </div>

    <section class="panel">
        <h2>Users and RBAC Roles</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Active</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= h((string) $user['name']) ?></td>
                        <td><?= h((string) $user['username']) ?></td>
                        <td><?= h((string) ($user['roles'] ?? 'No role')) ?></td>
                        <td><?= (int) $user['is_active'] === 1 ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Officer Reporting Hierarchy</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Field Officer</th>
                    <th>Admin Officer</th>
                    <th>Admin Manager</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($assignments->num_rows === 0): ?>
                    <tr><td colspan="3">No assignments found.</td></tr>
                <?php endif; ?>

                <?php while ($assignment = $assignments->fetch_assoc()): ?>
                    <tr>
                        <td><?= h((string) $assignment['field_officer']) ?></td>
                        <td><?= h((string) $assignment['admin_officer']) ?></td>
                        <td><?= h((string) $assignment['admin_manager']) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
