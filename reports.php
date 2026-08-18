<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/review_helpers.php';

requireRole(['system_admin', 'admin_officer', 'admin_manager']);

$role = currentRole();
$userId = currentUserId();
$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
$export = (string) ($_GET['export'] ?? '');

$validDate = static function (string $value): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
};
if (!$validDate($from)) $from = date('Y-m-01');
if (!$validDate($to)) $to = date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$whereRole = '';
$params = [$from, $to];
$types = 'ss';
if ($role === 'admin_officer') {
    $whereRole = ' AND ws.admin_officer_id = ?';
    $params[] = $userId;
    $types .= 'i';
} elseif ($role === 'admin_manager') {
    $whereRole = ' AND ws.admin_manager_id = ?';
    $params[] = $userId;
    $types .= 'i';
}

$sql = "SELECT
            ws.id, ws.week_start, ws.week_end, ws.status, ws.submitted_at,
            fo.name AS field_officer_name, fo.username AS field_officer_username,
            ao.name AS admin_officer_name,
            am.name AS admin_manager_name,
            COUNT(wsr.id) AS record_count
        FROM weekly_submissions ws
        INNER JOIN users fo ON fo.id = ws.field_officer_id
        INNER JOIN users ao ON ao.id = ws.admin_officer_id
        INNER JOIN users am ON am.id = ws.admin_manager_id
        LEFT JOIN weekly_submission_records wsr ON wsr.submission_id = ws.id
        WHERE ws.week_start BETWEEN ? AND ? $whereRole
        GROUP BY ws.id, ws.week_start, ws.week_end, ws.status, ws.submitted_at,
                 fo.name, fo.username, ao.name, am.name
        ORDER BY ws.week_start DESC, ws.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
$stmt->close();

if ($export === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="fieldtrack_weekly_report_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Submission ID','Officer','Username','Week Start','Week End','Records','Status','Admin Officer','Admin Manager','Submitted At']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['id'], $row['field_officer_name'], $row['field_officer_username'],
            $row['week_start'], $row['week_end'], $row['record_count'], $row['status'],
            $row['admin_officer_name'], $row['admin_manager_name'], $row['submitted_at']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FieldTrack Reports</title>
<link rel="stylesheet" href="<?= h(appUrl('review_panel.css')) ?>">
</head>
<body>
<header class="topbar"><div><h1>FieldTrack</h1><p>Weekly Attendance Reports</p></div><div class="topbar-links"><a href="<?= h(appUrl(ROLE_DASHBOARDS[$role] ?? 'admin_panel.php')) ?>">Dashboard</a><a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a></div></header>
<main class="container">
<section class="panel">
<h2>Report Filter</h2>
<form method="GET" class="form-grid">
<div><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
<div><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
<div class="form-actions"><button class="approve-button" type="submit">View Report</button></div>
</form>
<p><a class="open-button" href="<?= h(appUrl('reports.php?from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . '&export=csv')) ?>">Export CSV</a></p>
</section>
<section class="panel">
<h2>Weekly Submissions</h2>
<div class="table-wrap"><table>
<thead><tr><th>ID</th><th>Officer</th><th>Week</th><th>Records</th><th>Status</th><th>Admin Officer</th><th>Manager</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td><?= (int) $row['id'] ?></td>
<td><?= h($row['field_officer_name']) ?> (@<?= h($row['field_officer_username']) ?>)</td>
<td><?= h(formatDateValue($row['week_start'])) ?> — <?= h(formatDateValue($row['week_end'])) ?></td>
<td><?= (int) $row['record_count'] ?></td>
<td><?= h($row['status']) ?></td>
<td><?= h($row['admin_officer_name']) ?></td>
<td><?= h($row['admin_manager_name']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7">No submissions in this date range.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
</main>
</body>
</html>