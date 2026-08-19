<?php
declare(strict_types=1);

const FIELDTRACK_BASE_PATH = '/FieldTrack';
const FIELDTRACK_SESSION_TIMEOUT = 1800; // 30 minutes

date_default_timezone_set('Asia/Colombo');

function startFieldTrackSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
