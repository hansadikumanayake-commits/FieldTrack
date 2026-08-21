<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/csrf.php';

requireRole(['system_admin']);
requirePermission('assignments.manage');

function assignmentBack(string $message): never
{
    redirectTo('manage_assignments.php?msg=' . rawurlencode($message));
}

function usersByRole(mysqli $conn, string $roleName): array
{
    $stmt = $conn->prepare(
        "SELECT u.id, u.name, u.username
         FROM users u
         INNER JOIN user_roles ur ON ur.user_id = u.id
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE r.role_name = ? AND u.is_active = 1
         ORDER BY u.name"
    );
    $stmt->bind_param('s', $roleName);
    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $fieldOfficerId = filter_input(INPUT_POST, 'field_officer_id', FILTER_VALIDATE_INT);
    $adminOfficerId = filter_input(INPUT_POST, 'admin_officer_id', FILTER_VALIDATE_INT);
    $adminManagerId = filter_input(INPUT_POST, 'admin_manager_id', FILTER_VALIDATE_INT);

    if (!$fieldOfficerId || !$adminOfficerId || !$adminManagerId) {
        assignmentBack('Invalid assignment.');
    }

    $getRole = static function (mysqli $conn, int $userId): string {
        $stmt = $conn->prepare(
            "SELECT r.role_name
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $roleName = (string)($stmt->get_result()->fetch_assoc()['role_name'] ?? '');
        $stmt->close();
        return $roleName;
    };

    $fieldRole = $getRole($conn, (int)$fieldOfficerId);
    $adminRole = $getRole($conn, (int)$adminOfficerId);
    $managerRole = $getRole($conn, (int)$adminManagerId);

    if ($fieldRole !== 'field_officer' || $adminRole !== 'admin_officer' || $managerRole !== 'admin_manager') {
        assignmentBack('The selected accounts do not match the required hierarchy.');
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO officer_assignments
                (field_officer_id, admin_officer_id, admin_manager_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                admin_officer_id = VALUES(admin_officer_id),
                admin_manager_id = VALUES(admin_manager_id)"
        );
        $stmt->bind_param('iii', $fieldOfficerId, $adminOfficerId, $adminManagerId);
        $stmt->execute();
        $stmt->close();

        $actorId = currentUserId();
        $details = 'Field Officer #' . $fieldOfficerId .
            ' assigned to Admin Officer #' . $adminOfficerId .
            ' and Admin Manager #' . $adminManagerId;
        $ip = getClientIpAddress();

        $audit = $conn->prepare(
            "INSERT INTO audit_logs
                (user_id, action, target_type, target_id, details, ip_address)
             VALUES (?, 'OFFICER_ASSIGNMENT_CHANGED', 'officer_assignment', ?, ?, ?)"
        );
        $audit->bind_param('iiss', $actorId, $fieldOfficerId, $details, $ip);
        $audit->execute();
        $audit->close();

        assignmentBack('Officer assignment saved successfully.');
    } catch (Throwable $e) {
        error_log('Assignment error: ' . $e->getMessage());
        assignmentBack('Officer assignment could not be saved.');
    }
}

$fieldOfficers = usersByRole($conn, 'field_officer');
$adminOfficers = usersByRole($conn, 'admin_officer');
$adminManagers = usersByRole($conn, 'admin_manager');

$assignments = [];
$result = $conn->query(
    "SELECT oa.id,
            fo.name AS field_officer, fo.username AS field_username,
            ao.name AS admin_officer, ao.username AS admin_username,
            am.name AS admin_manager, am.username AS manager_username
     FROM officer_assignments oa
     INNER JOIN users fo ON fo.id = oa.field_officer_id
     INNER JOIN users ao ON ao.id = oa.admin_officer_id
     INNER JOIN users am ON am.id = oa.admin_manager_id
     ORDER BY fo.name"
);
while ($row = $result->fetch_assoc()) $assignments[] = $row;

$message = trim((string)($_GET['msg'] ?? ''));
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
<div><h1>FieldTrack</h1><p>Officer Reporting Hierarchy</p></div>
<div class="topbar-links">
<a href="<?= h(appUrl('admin_panel.php')) ?>">Dashboard</a>
<a class="logout" href="<?= h(appUrl('logout.php')) ?>">Logout</a>
</div>
</header>

<main class="container">
<?php if ($message !== ''): ?><div class="message"><?= h($message) ?></div><?php endif; ?>

<section class="panel">
<h2>Assign Field Officer → Admin Officer → Admin Manager</h2>
<form method="POST" class="form-grid">
<?= csrfInput() ?>
<div>
<label for="field_officer_id">Field Officer</label>
<select id="field_officer_id" name="field_officer_id" required>
<?php foreach ($fieldOfficers as $u): ?>
<option value="<?= (int)$u['id'] ?>"><?= h($u['name']) ?> (@<?= h($u['username']) ?>)</option>
<?php endforeach; ?>
</select>
</div>

<div>
<label for="admin_officer_id">Admin Officer</label>
<select id="admin_officer_id" name="admin_officer_id" required>
<?php foreach ($adminOfficers as $u): ?>
<option value="<?= (int)$u['id'] ?>"><?= h($u['name']) ?> (@<?= h($u['username']) ?>)</option>
<?php endforeach; ?>
</select>
</div>

<div>
<label for="admin_manager_id">Admin Manager</label>
<select id="admin_manager_id" name="admin_manager_id" required>
<?php foreach ($adminManagers as $u): ?>
<option value="<?= (int)$u['id'] ?>"><?= h($u['name']) ?> (@<?= h($u['username']) ?>)</option>
<?php endforeach; ?>
</select>
</div>

<div class="form-actions"><button class="approve-button" type="submit">Save Assignment</button></div>
</form>
</section>

<section class="panel">
<h2>Current Assignments</h2>
<div class="table-wrap">
<table>
<thead><tr><th>Field Officer</th><th>Admin Officer</th><th>Admin Manager</th></tr></thead>
<tbody>
<?php if (!$assignments): ?><tr><td colspan="3">No assignments yet.</td></tr><?php endif; ?>
<?php foreach ($assignments as $a): ?>
<tr>
<td><?= h($a['field_officer']) ?> (@<?= h($a['field_username']) ?>)</td>
<td><?= h($a['admin_officer']) ?> (@<?= h($a['admin_username']) ?>)</td>
<td><?= h($a['admin_manager']) ?> (@<?= h($a['manager_username']) ?>)</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
</main>
</body>
</html>
