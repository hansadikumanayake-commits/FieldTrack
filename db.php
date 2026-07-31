<?php

declare(strict_types=1);

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fieldtrack_db';

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    error_log(
        'FieldTrack database connection error: ' .
        $conn->connect_error
    );

    exit('The database connection failed.');
}

$conn->set_charset('utf8mb4');
