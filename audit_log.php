<?php

declare(strict_types=1);

require_once 'auth.php';
require_once 'db.php';

requireRole(['admin']);

function writeAuditLog(
    mysqli $conn,
    int $userId,
    string $action,
    ?string $targetType=null,
    ?int $targetId=null
):void{
    $ipAddress=$_SERVER['REMOTE_ADDR'] ?? null;

    $stmt=$conn->prepare(
        "INSERT INTO audit_logs(
            user_id,
            action,
            target_type,
            target_id,
            ip_address
        )
        VALUES (?,?,?,?,?)"
    );

    $stmt->bind_param(
        'issis',
        $userId,
        $action,
        $targetId,
        $ipAddress
    );
    $stmt->execute();
    $stmt->close();
}

?>