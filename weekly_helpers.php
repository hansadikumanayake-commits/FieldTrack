<?php

declare(strict_types=1);

/*
 * Shared helpers for the FieldTrack weekly workflow.
 * Weeks run from Monday to Sunday.
 */

function getWeekBounds(?string $date = null): array
{
    $date ??= date('Y-m-d');

    $dateObject = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    if ($dateObject === false) {
        $dateObject = new DateTimeImmutable('today');
    }

    $dayOfWeek = (int) $dateObject->format('N');

    $weekStart = $dateObject->modify(
        '-' . ($dayOfWeek - 1) . ' days'
    );

    $weekEnd = $weekStart->modify('+6 days');

    return [
        $weekStart->format('Y-m-d'),
        $weekEnd->format('Y-m-d'),
    ];
}

function getWeeklySubmission(
    mysqli $conn,
    int $fieldOfficerId,
    string $weekStart
): ?array {
    $stmt = $conn->prepare(
        "SELECT
            id,
            id AS submission_id,
            field_officer_id,
            admin_officer_id,
            admin_manager_id,
            week_start,
            week_end,
            status,
            latest_rejection_reason,
            latest_rejection_reason AS rejection_reason,
            submitted_at,
            admin_reviewed_at,
            manager_reviewed_at,
            COALESCE(
                manager_reviewed_at,
                admin_reviewed_at
            ) AS reviewed_at,
            created_at,
            updated_at

         FROM weekly_submissions

         WHERE field_officer_id = ?
         AND week_start = ?

         LIMIT 1"
    );

    if ($stmt === false) {
        throw new RuntimeException(
            'Prepare failed (weekly submission): ' .
            $conn->error
        );
    }

    $stmt->bind_param(
        'is',
        $fieldOfficerId,
        $weekStart
    );

    $stmt->execute();

    $row = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

function getOfficerAssignment(
    mysqli $conn,
    int $fieldOfficerId
): ?array {
    $stmt = $conn->prepare(
        "SELECT
            admin_officer_id,
            admin_manager_id

         FROM officer_assignments

         WHERE field_officer_id = ?

         LIMIT 1"
    );

    if ($stmt === false) {
        throw new RuntimeException(
            'Prepare failed (officer assignment): ' .
            $conn->error
        );
    }

    $stmt->bind_param(
        'i',
        $fieldOfficerId
    );

    $stmt->execute();

    $row = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

function isWeekEditable(?array $submission): bool
{
    if ($submission === null) {
        return true;
    }

    return in_array(
        (string) $submission['status'],
        [
            'draft',
            'returned_for_correction',
            'admin_officer_rejected',
            'manager_rejected',
        ],
        true
    );
}

function isResubmittable(?array $submission): bool
{
    if ($submission === null) {
        return false;
    }

    return in_array(
        (string) $submission['status'],
        [
            'returned_for_correction',
            'admin_officer_rejected',
            'manager_rejected',
        ],
        true
    );
}

function getWeekDaySummary(
    mysqli $conn,
    int $fieldOfficerId,
    string $weekStart,
    string $weekEnd
): array {
    $days = [];

    $cursor = new DateTimeImmutable($weekStart);
    $end = new DateTimeImmutable($weekEnd);

    while ($cursor <= $end) {
        $days[$cursor->format('Y-m-d')] = [
            'in' => false,
            'out' => false,
        ];

        $cursor = $cursor->modify('+1 day');
    }

    $stmt = $conn->prepare(
        "SELECT
            DATE(created_at) AS attendance_day,
            action_type

         FROM attendance_events

         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?

         ORDER BY created_at ASC, id ASC"
    );

    if ($stmt === false) {
        throw new RuntimeException(
            'Prepare failed (weekly day summary): ' .
            $conn->error
        );
    }

    $stmt->bind_param(
        'iss',
        $fieldOfficerId,
        $weekStart,
        $weekEnd
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $day = (string) $row['attendance_day'];

        if (!isset($days[$day])) {
            continue;
        }

        if ($row['action_type'] === 'IN') {
            $days[$day]['in'] = true;
        }

        if ($row['action_type'] === 'OUT') {
            $days[$day]['out'] = true;
        }
    }

    $stmt->close();

    return $days;
}

function getWeekStatusLabel(string $status): string
{
    $labels = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'admin_officer_approved' =>
            'Approved by Admin Officer',
        'admin_officer_rejected' =>
            'Rejected by Admin Officer',
        'pending_manager_review' =>
            'Pending Manager Review',
        'manager_rejected' =>
            'Rejected by Manager',
        'returned_for_correction' =>
            'Returned for Correction',
        'resubmitted' => 'Resubmitted',
        'final_approved' => 'Final Approved',
    ];

    return $labels[$status]
        ?? ucfirst(str_replace('_', ' ', $status));
}

function getWeekStatusClass(string $status): string
{
    return 'status-badge-' .
        str_replace('_', '-', $status);
}

function getAllWeekStatuses(): array
{
    return [
        'draft',
        'submitted',
        'admin_officer_approved',
        'admin_officer_rejected',
        'pending_manager_review',
        'manager_rejected',
        'returned_for_correction',
        'resubmitted',
        'final_approved',
    ];
}

function getClientIpAddress(): string
{
    return substr(
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        0,
        45
    );
}
