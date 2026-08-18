<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (isLoggedIn()) {
    try {
        $userId = currentUserId();
        $ip = getClientIpAddress();
        $stmt = $conn->prepare(
            "INSERT INTO audit_logs
                (user_id, action, target_type, target_id, details, ip_address)
             VALUES (?, 'LOGOUT', 'authentication', ?, 'User logged out', ?)"
        );
        $stmt->bind_param('iis', $userId, $userId, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('Logout audit error: ' . $e->getMessage());
    }
}

clearCurrentSession();
redirectTo('login.php?logout=1');