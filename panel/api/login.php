<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

if (auth_stored_password() === null) {
    json_error('No password is set. Put a password in the ".password" file in the panel root.', 500);
}

// Read the raw JSON body instead of `$_POST`: "engine/start.php" rewrites
// `$_POST` values (trims, casts numeric strings, ...) and that would corrupt
// a password such as "007".
$body     = json_decode((string) file_get_contents('php://input'), true);
$password = is_array($body) && is_string($body['password'] ?? null) ? $body['password'] : '';

if (!auth_check_password($password)) {
    usleep(500_000); // slow down guessing
    json_error('Incorrect password.', 401);
}

auth_login();
json_success();
