<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

requireLogin();

if (hasRole('admin_officer')) {
    redirectTo('admin_officer_panel.php');
}

if (hasRole('admin_manager')) {
    redirectTo('admin_manager_panel.php');
}

redirectToDashboard();