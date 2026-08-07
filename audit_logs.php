<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['admin_manager', 'system_admin']);
requirePermission('audit.view');

$result = $conn->query(
    "SELECT
        al.id,
        al.action,
        al.target_type,
        al.target_id,
        al.details,
        al.ip_address,
        al.created_at,
        u.name,
        u.username
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT 300"
);

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

$backPage = currentRole() === 'admin_manager'
    ? 'admin_manager_panel.php'
    : 'admin_panel.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar">
    <div><h1>FieldTrack</h1><p>Audit Logs</p></div>
    <div class="topbar-links">
        <a href="<?= h(appUrl($backPage)) ?>">Back</a>
        <a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
    </div>
</header>

<main class="container">
    <section class="panel">
        <h2>Recent Audit Activity</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($logs) === 0): ?>
                    <tr><td colspan="6">No audit logs yet.</td></tr>
                <?php endif; ?>

                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= h(formatDateTimeValue($log['created_at'])) ?></td>
                        <td>
                            <?= h($log['name'] ?? 'Unknown') ?>
                            <?= !empty($log['username']) ? '(@' . h($log['username']) . ')' : '' ?>
                        </td>
                        <td><?= h($log['action']) ?></td>
                        <td><?= h($log['target_type'] ?? '—') ?> #<?= h($log['target_id'] ?? '—') ?></td>
                        <td><?= h($log['details'] ?? '—') ?></td>
                        <td><?= h($log['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
