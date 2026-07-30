<?php

require_once 'session_config.php';

/*
|--------------------------------------------------------------------------
| Session timeout
|--------------------------------------------------------------------------
|
| The user will be logged out after 30 minutes of inactivity.
|
*/

const SESSION_TIMEOUT_SECONDS = 1800;

/*
|--------------------------------------------------------------------------
| Check whether the user is logged in
|--------------------------------------------------------------------------
*/

function isLoggedIn(): bool
{
    return isset(
        $_SESSION['user_id'],
        $_SESSION['role']
    );
}
