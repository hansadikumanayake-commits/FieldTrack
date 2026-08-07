<?php

declare(strict_types=1);

$databaseHost = 'localhost';
$databaseUsername = 'root';
$databasePassword = '';
$databaseName = 'fieldtrack_db';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $databaseHost,
        $databaseUsername,
        $databasePassword,
        $databaseName
    );

    $conn->set_charset('utf8mb4');
} catch (Throwable $error) {
    error_log('FieldTrack database connection error: ' . $error->getMessage());
    http_response_code(500);
    exit('Database connection failed. Make sure MySQL is running and fieldtrack_db exists.');
}
