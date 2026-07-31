<?php

declare(strict_types=1);

function h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function reviewerCanAccessSubmission(
    array $submission,
    int $userId,
    string $role
): bool {
    if ($role === 'system_admin') {
        return true;
    }

    if (
        $role === 'admin_officer' &&
        (int) $submission['admin_officer_id'] === $userId
    ) {
        return true;
    }

    return (
        $role === 'admin_manager' &&
        (int) $submission['admin_manager_id'] === $userId
    );
}

function loadSubmission(
    mysqli $conn,
    int $submissionId
): ?array {
    $stmt = $conn->prepare(
        "SELECT
            ws.*,
            fo.name AS field_officer_name,
            fo.username AS field_officer_username,
            ao.name AS admin_officer_name,
            am.name AS admin_manager_name
         FROM weekly_submissions ws
         INNER JOIN users fo
            ON fo.id = ws.field_officer_id
         INNER JOIN users ao
            ON ao.id = ws.admin_officer_id
         INNER JOIN users am
            ON am.id = ws.admin_manager_id
         WHERE ws.id = ?
         LIMIT 1"
    );

    if ($stmt === false) {
        throw new RuntimeException(
            'Prepare failed: ' . $conn->error
        );
    }

    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}
