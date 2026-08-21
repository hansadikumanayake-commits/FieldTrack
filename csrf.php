<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function requireValidCsrf(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    $stored = (string) ($_SESSION['csrf_token'] ?? '');

    if (
        $submitted === '' ||
        $stored === '' ||
        !hash_equals($stored, $submitted)
    ) {
        http_response_code(419);
        exit('Invalid or expired form token. Please go back, refresh the page, and try again.');
    }
}
