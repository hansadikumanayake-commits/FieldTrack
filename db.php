<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MySQL error reporting
|--------------------------------------------------------------------------
*/

mysqli_report(
    MYSQLI_REPORT_ERROR |
    MYSQLI_REPORT_STRICT
);

/*
|--------------------------------------------------------------------------
| Database configuration
|--------------------------------------------------------------------------
*/

$databaseHost = 'localhost';
$databaseUsername = 'root';
$databasePassword = '';
$databaseName = 'fieldtrack_db';
$databasePort = 3306;

/*
|--------------------------------------------------------------------------
| Create database connection
|--------------------------------------------------------------------------
*/

try {
    $conn = new mysqli(
        $databaseHost,
        $databaseUsername,
        $databasePassword,
        $databaseName,
        $databasePort
    );

    /*
    |--------------------------------------------------------------------------
    | Set character encoding
    |--------------------------------------------------------------------------
    */

    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $error) {
    error_log(
        'FieldTrack database connection error: ' .
        $error->getMessage()
    );

    http_response_code(500);

    exit(
        'The database connection could not be established.'
    );
}