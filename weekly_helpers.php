<?php

declare(strict_types=1);

/*
 * Shared helpers for FieldTrack weekly attendance.
 * Weeks run Monday -> Sunday.
 * Monday -> Friday are treated as required working days.
 */

const FIELDTRACK_REQUIRED_WEEKDAYS = [1, 2, 3, 4, 5];

function getWeekBounds(?string $date = null): array
{
    $date ??= date('Y-m-d');
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    if ($dateObject === false) {
        $dateObject = new DateTimeImmutable('today');
    }

    $dayOfWeek = (int) $dateObject->format('N');
    $weekStart = $dateObject->modify('-' . ($dayOfWeek - 1) . ' days');
    $weekEnd = $weekStart->modify('+6 days');

    return [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')];
}

function isValidWeekStart(string $weekStart): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $weekStart);

    return (
        $date !== false &&
        $date->format('Y-m-d') === $weekStart &&
        $date->format('N') === '1'
    );
}

function getWeeklySubmission(mysqli $conn, int $fieldOfficerId, string $weekStart): ?array
{
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
            COALESCE(manager_reviewed_at, admin_reviewed_at) AS reviewed_at,
            created_at,
            updated_at
         FROM weekly_submissions
         WHERE field_officer_id = ?
         AND week_start = ?
         LIMIT 1"
    );

    $stmt->bind_param('is', $fieldOfficerId, $weekStart);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function getOfficerAssignment(mysqli $conn, int $fieldOfficerId): ?array
{
    $stmt = $conn->prepare(
        "SELECT admin_officer_id, admin_manager_id
         FROM officer_assignments
         WHERE field_officer_id = ?
         LIMIT 1"
    );

    $stmt->bind_param('i', $fieldOfficerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
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
        ['draft', 'returned_for_correction', 'admin_officer_rejected', 'manager_rejected'],
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
        ['returned_for_correction', 'admin_officer_rejected', 'manager_rejected'],
        true
    );
}

function getWeekDaySummary(mysqli $conn, int $fieldOfficerId, string $weekStart, string $weekEnd): array
{
    $days = [];
    $cursor = new DateTimeImmutable($weekStart);
    $end = new DateTimeImmutable($weekEnd);

    while ($cursor <= $end) {
        $days[$cursor->format('Y-m-d')] = [
            'weekday' => (int) $cursor->format('N'),
            'label' => $cursor->format('l'),
            'in' => false,
            'out' => false,
            'in_time' => null,
            'out_time' => null,
            'record_count' => 0,
        ];
        $cursor = $cursor->modify('+1 day');
    }

    $stmt = $conn->prepare(
        "SELECT
            DATE(created_at) AS attendance_day,
            action_type,
            created_at
         FROM attendance_events
         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?
         ORDER BY created_at ASC, id ASC"
    );

    $stmt->bind_param('iss', $fieldOfficerId, $weekStart, $weekEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $day = (string) $row['attendance_day'];

        if (!isset($days[$day])) {
            continue;
        }

        $days[$day]['record_count']++;

        if ($row['action_type'] === 'IN') {
            $days[$day]['in'] = true;
            $days[$day]['in_time'] ??= (string) $row['created_at'];
        }

        if ($row['action_type'] === 'OUT') {
            $days[$day]['out'] = true;
            $days[$day]['out_time'] = (string) $row['created_at'];
        }
    }

    $stmt->close();
    return $days;
}

function getWeekCompleteness(mysqli $conn, int $fieldOfficerId, string $weekStart, string $weekEnd): array
{
    $summary = getWeekDaySummary($conn, $fieldOfficerId, $weekStart, $weekEnd);
    $missing = [];
    $requiredDays = 0;
    $completeDays = 0;

    foreach ($summary as $date => $day) {
        $weekday = (int) $day['weekday'];

        if (!in_array($weekday, FIELDTRACK_REQUIRED_WEEKDAYS, true)) {
            continue;
        }

        $requiredDays++;
        $hasIn = (bool) $day['in'];
        $hasOut = (bool) $day['out'];

        if ($hasIn && $hasOut) {
            $completeDays++;
            continue;
        }

        if (!$hasIn && !$hasOut) {
            $missing[] = $day['label'] . ' (' . date('d M', strtotime($date)) . '): IN and OUT missing';
        } elseif (!$hasIn) {
            $missing[] = $day['label'] . ' (' . date('d M', strtotime($date)) . '): IN missing';
        } else {
            $missing[] = $day['label'] . ' (' . date('d M', strtotime($date)) . '): OUT missing';
        }
    }

    return [
        'is_complete' => count($missing) === 0,
        'required_days' => $requiredDays,
        'complete_days' => $completeDays,
        'missing' => $missing,
        'days' => $summary,
    ];
}

function countWeekRecords(mysqli $conn, int $fieldOfficerId, string $weekStart, string $weekEnd): int
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM attendance_events
         WHERE user_id = ?
         AND DATE(created_at) BETWEEN ? AND ?"
    );

    $stmt->bind_param('iss', $fieldOfficerId, $weekStart, $weekEnd);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function getWeekStatusLabel(string $status): string
{
    $labels = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'admin_officer_approved' => 'Approved by Admin Officer',
        'admin_officer_rejected' => 'Rejected by Admin Officer',
        'pending_manager_review' => 'Pending Manager Review',
        'manager_rejected' => 'Rejected by Manager',
        'returned_for_correction' => 'Returned for Correction',
        'resubmitted' => 'Resubmitted',
        'final_approved' => 'Final Approved',
    ];

    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
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