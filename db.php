<?php

declare(strict_types=1);

$databaseHost = 'localhost';
$databaseUsername = 'root';
$databasePassword = '';
$databaseName = 'fieldtrack_db';

$conn = new mysqli(
    $databaseHost,
    $databaseUsername,
    $databasePassword,
    $databaseName
);

if ($conn->connect_error) {
    error_log(
        'FieldTrack database connection failed: ' .
        $conn->connect_error
    );

    exit('Database connection failed.');
}

$conn->set_charset('utf8mb4');