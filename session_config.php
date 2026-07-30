<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Start the FieldTrack session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    /*
    |--------------------------------------------------------------------------
    | Session security settings
    |--------------------------------------------------------------------------
    */

    ini_set(
        'session.use_strict_mode',
        '1'
    );

    ini_set(
        'session.use_only_cookies',
        '1'
    );

    ini_set(
        'session.cookie_httponly',
        '1'
    );

    ini_set(
        'session.cookie_samesite',
        'Lax'
    );

    /*
     * Keep server-side session data available
     * for at least 30 minutes.
     */

    ini_set(
        'session.gc_maxlifetime',
        '1800'
    );

    /*
    |--------------------------------------------------------------------------
    | Detect HTTPS
    |--------------------------------------------------------------------------
    */

    $isHttps =
        (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== '' &&
            strtolower(
                (string) $_SERVER['HTTPS']
            ) !== 'off'
        ) ||
        (
            isset(
                $_SERVER[
                    'HTTP_X_FORWARDED_PROTO'
                ]
            ) &&
            strtolower(
                (string) $_SERVER[
                    'HTTP_X_FORWARDED_PROTO'
                ]
            ) === 'https'
        );

    /*
    |--------------------------------------------------------------------------
    | Configure session cookie
    |--------------------------------------------------------------------------
    */

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Use a custom session cookie name
    |--------------------------------------------------------------------------
    */

    session_name(
        'FIELDTRACK_SESSION'
    );

    /*
    |--------------------------------------------------------------------------
    | Start session
    |--------------------------------------------------------------------------
    */

    session_start();
}