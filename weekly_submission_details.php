<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/review_helpers.php';
require_once __DIR__ . '/weekly_helpers.php';
require_once __DIR__ . '/csrf.php';

requireAdministrativeUser();

$submissionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($submissionId === false || $submissionId === null || $submissionId < 1) {
    redirectToDashboard();
}

$submission = loadSubmission($conn, (int) $submissionId);

if ($submission === null) {
    http_response_code(404);
    exit('Weekly submission not found.');
}

$role = currentRole();
$userId = currentUserId();

if (!reviewerCanAccessSubmission($submission, $userId, $role)) {
    http_response_code(403);
    exit('This weekly submission is not assigned to your account.');
}

preventSelfApproval((int) $submission['field_officer_id']);

$attendanceStmt = $conn->prepare(
    "SELECT
        ae.id,
        ae.action_type,
        ae.latitude,
        ae.longitude,
        ae.created_at
     FROM weekly_submission_records wsr
     INNER JOIN attendance_events ae
        ON ae.id = wsr.attendance_event_id
     WHERE wsr.submission_id = ?
     ORDER BY ae.created_at ASC, ae.id ASC"
);
$attendanceStmt->bind_param('i', $submissionId);
$attendanceStmt->execute();
$attendanceResult = $attendanceStmt->get_result();

$attendanceRecords = [];
while ($row = $attendanceResult->fetch_assoc()) {
    $attendanceRecords[] = $row;
}
$attendanceStmt->close();

$historyStmt = $conn->prepare(
    "SELECT
        ah.*,
        u.name AS reviewer_name
     FROM approval_history ah
     LEFT JOIN users u
        ON u.id = ah.reviewer_id
     WHERE ah.submission_id = ?
     ORDER BY ah.created_at ASC, ah.id ASC"
);
$historyStmt->bind_param('i', $submissionId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();

$history = [];
while ($row = $historyResult->fetch_assoc()) {
    $history[] = $row;
}
$historyStmt->close();

$status = (string) $submission['status'];

$canAdminOfficerReview = (
    $role === 'admin_officer' &&
    (int) $submission['admin_officer_id'] === $userId &&
    in_array($status, ['submitted', 'resubmitted'], true)
);

$canManagerReview = (
    $role === 'admin_manager' &&
    (int) $submission['admin_manager_id'] === $userId &&
    in_array($status, ['pending_manager_review', 'admin_officer_approved'], true)
);

$canReview = $canAdminOfficerReview || $canManagerReview;

$backPage = match ($role) {
    'admin_officer' => 'admin_officer_panel.php',
    'admin_manager' => 'admin_manager_panel.php',
    default => 'admin_panel.php',
};

$message = trim((string) ($_GET['msg'] ?? ''));
?>