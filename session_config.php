<?php

declare(strict_types=1);

/*
 * FieldTrack shared application/session configuration.
 * The project folder on XAMPP is:
 * C:\xampp\htdocs\FieldTrack
 * Therefore the browser base path is /FieldTrack
 */

const FIELDTRACK_BASE_PATH = '/FieldTrack';
const FIELDTRACK_SESSION_TIMEOUT = 1800; // 30 minutes

date_default_timezone_set('Asia/Colombo');

function startFieldTrackSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    );

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
