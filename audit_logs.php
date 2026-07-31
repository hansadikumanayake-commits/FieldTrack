<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';
require_once 'review_helpers.php';

requireRole([
    'admin_manager',
    'system_admin',
]);

$stmt = $conn->prepare(
    "SELECT
        al.id,
        al.action,
        al.target_type,
        al.target_id,
        al.details,
        al.ip_address,
        al.created_at,
        u.name AS user_name,
        u.username
     FROM audit_logs al
     LEFT JOIN users u
        ON u.id = al.user_id
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT 200"
);

$stmt->execute();
$result = $stmt->get_result();

$backPage =
    currentRole() === 'admin_manager'
        ? 'admin_manager_panel.php'
        : 'admin_panel.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="review_panel.css">
</head>
<body>
<header class="topbar">
    <div>
        <h1>FieldTrack Audit Logs</h1>
        <p>Most recent 200 activities</p>
    </div>
    <div class="topbar-links">
        <a href="<?= h($backPage) ?>">Back</a>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<main class="container">
    <section class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= h((string) $row['created_at']) ?></td>
                        <td>
                            <?= h((string) ($row['user_name'] ?? 'System')) ?>
                            <?php if (!empty($row['username'])): ?>
                                (@<?= h((string) $row['username']) ?>)
                            <?php endif; ?>
                        </td>
                        <td><?= h((string) $row['action']) ?></td>
                        <td>
                            <?= h((string) ($row['target_type'] ?? '')) ?>
                            #<?= h((string) ($row['target_id'] ?? '')) ?>
                        </td>
                        <td><?= h((string) ($row['details'] ?? '')) ?></td>
                        <td><?= h((string) ($row['ip_address'] ?? '')) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
