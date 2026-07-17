<?php

declare(strict_types=1);

/**
 * Save an administrator action in the audit_logs table.
 */
function writeAuditLog(
    mysqli $conn,
    int $userId,
    string $action,
    ?string $targetType = null,
    ?int $targetId = null
): bool {
    try {
        $action = trim($action);

        if ($userId <= 0 || $action === '') {
            return false;
        }

        /*
         * Get the visitor's IP address.
         */
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $conn->prepare(
            "INSERT INTO audit_logs
                (
                    user_id,
                    action,
                    target_type,
                    target_id,
                    ip_address
                )
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            'issis',
            $userId,
            $action,
            $targetType,
            $targetId,
            $ipAddress
        );

        $success = $stmt->execute();

        $stmt->close();

        return $success;
    } catch (Throwable $error) {
        /*
         * Do not stop the whole system if audit logging fails.
         * Store the error in the PHP/Apache error log.
         */
        error_log(
            'FieldTrack audit logging error: ' .
            $error->getMessage()
        );

        return false;
    }
}