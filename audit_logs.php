<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin', 'admin_manager']);
requirePermission('audit.view');

$role = currentRole();
$backPage = $role === 'admin_manager' ? 'admin_manager_panel.php' : 'admin_panel.php';

$userFilter = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$userFilter = ($userFilter === false || $userFilter === null) ? 0 : (int)$userFilter;

$actionFilter = trim((string)($_GET['action'] ?? ''));
$dateFilter = trim((string)($_GET['date'] ?? ''));

if ($dateFilter !== '') {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFilter);
    if ($d === false || $d->format('Y-m-d') !== $dateFilter) $dateFilter = '';
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$users = [];
$result = $conn->query("SELECT id, name, username FROM users ORDER BY name");
while ($row = $result->fetch_assoc()) $users[] = $row;

$actions = [];
$result = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
while ($row = $result->fetch_assoc()) $actions[] = (string)$row['action'];

$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM audit_logs al
     WHERE (? = 0 OR al.user_id = ?)
       AND (? = '' OR al.action = ?)
       AND (? = '' OR DATE(al.created_at) = ?)"
);
$countStmt->bind_param('iissss', $userFilter, $userFilter, $actionFilter, $actionFilter, $dateFilter, $dateFilter);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$stmt = $conn->prepare(
    "SELECT al.id, al.user_id, al.action, al.target_type, al.target_id,
            al.details, al.ip_address, al.created_at,
            u.name, u.username
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.user_id
     WHERE (? = 0 OR al.user_id = ?)
       AND (? = '' OR al.action = ?)
       AND (? = '' OR DATE(al.created_at) = ?)
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT ? OFFSET ?"
);
$stmt->bind_param(
    'iissssii',
    $userFilter, $userFilter,
    $actionFilter, $actionFilter,
    $dateFilter, $dateFilter,
    $perPage, $offset
);
$stmt->execute();
$result = $stmt->get_result();
$logs = [];
while ($row = $result->fetch_assoc()) $logs[] = $row;
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

function auditQuery(array $overrides = []): string
{
    $params = [
        'user_id' => $_GET['user_id'] ?? 0,
        'action' => $_GET['action'] ?? '',
        'date' => $_GET['date'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ];

    foreach ($overrides as $key => $value) $params[$key] = $value;
    return http_build_query($params);
}
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
<h2>Filter Audit Activity</h2>
<form method="GET" class="form-grid">
<div>
<label for="user_id">User</label>
<select id="user_id" name="user_id">
<option value="0">All Users</option>
<?php foreach ($users as $user): ?>
<option value="<?= (int)$user['id'] ?>" <?= $userFilter === (int)$user['id'] ? 'selected' : '' ?>>
<?= h($user['name']) ?> (@<?= h($user['username']) ?>)
</option>
<?php endforeach; ?>
</select>
</div>

<div>
<label for="action">Action</label>
<select id="action" name="action">
<option value="">All Actions</option>
<?php foreach ($actions as $action): ?>
<option value="<?= h($action) ?>" <?= $actionFilter === $action ? 'selected' : '' ?>><?= h($action) ?></option>
<?php endforeach; ?>
</select>
</div>

<div>
<label for="date">Date</label>
<input id="date" type="date" name="date" value="<?= h($dateFilter) ?>">
</div>

<div class="form-actions"><button type="submit">Apply Filter</button></div>
</form>
</section>

<section class="panel">
<h2>Audit Activity</h2>
<p><?= $total ?> matching log(s).</p>
<div class="table-wrap">
<table>
<thead>
<tr><th>Date / Time</th><th>User</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr>
</thead>
<tbody>
<?php if (!$logs): ?><tr><td colspan="6">No audit logs found.</td></tr><?php endif; ?>
<?php foreach ($logs as $log): ?>
<tr>
<td><?= h(formatDateTimeValue($log['created_at'])) ?></td>
<td><?= h($log['name'] ?? 'Unknown') ?><?= !empty($log['username']) ? ' (@' . h($log['username']) . ')' : '' ?></td>
<td><?= h($log['action']) ?></td>
<td><?= h($log['target_type'] ?? '—') ?> #<?= h($log['target_id'] ?? '—') ?></td>
<td><?= h($log['details'] ?? '—') ?></td>
<td><?= h($log['ip_address'] ?? '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div class="actions" style="margin-top:16px">
<?php if ($page > 1): ?>
<a class="open-button" href="<?= h(appUrl('audit_logs.php?' . auditQuery(['page'=>$page-1]))) ?>">Previous</a>
<?php endif; ?>
<span>Page <?= $page ?> of <?= $totalPages ?></span>
<?php if ($page < $totalPages): ?>
<a class="open-button" href="<?= h(appUrl('audit_logs.php?' . auditQuery(['page'=>$page+1]))) ?>">Next</a>
<?php endif; ?>
</div>
</section>
</main>
</body>
</html>
