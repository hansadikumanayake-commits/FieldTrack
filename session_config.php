<?php

$isHttps =
    (!empty($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off')
    ||
    (
        isset($_SERVER['SERVER_PORT']) &&
        (int) $_SERVER['SERVER_PORT'] === 443
    );

if (session_status() !== PHP_SESSION_ACTIVE) {

    session_name('fieldtrack_session');

    session_start([
        'use_strict_mode' => 1,
        'use_only_cookies' => 1,
        'cookie_lifetime' => 0,
        'cookie_path' => '/',
        'cookie_secure' => $isHttps,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}