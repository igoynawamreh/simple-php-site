<?php

// Simple single-password auth for the panel.
//
// The password lives in the file ".password" in the panel root.
// The file holds the password and nothing else. It is
// either plain text or a hash made by `password_hash()`:
//
//   echo 'my-secret' > .password
//   php -r "echo password_hash('my-secret', PASSWORD_DEFAULT);" > .password
//
// The file name starts with a dot, so ".htaccess" already denies access to
// it over HTTP.

const PANEL_PASSWORD_FILE = __DIR__ . '/../.password';
const PANEL_SESSION_KEY   = 'panel_auth';

/**
 * Start the session with a cookie scoped to the panel only.
 * Safe to call more than once.
 */
function auth_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    global $base_url;

    session_name('panel_session');
    session_set_cookie_params([
        'lifetime' => 0, // until the browser is closed
        'path'     => ($base_url ?? '') . '/panel',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function auth_is_logged_in(): bool {
    auth_start_session();
    return ($_SESSION[PANEL_SESSION_KEY] ?? false) === true;
}

/** The stored password (or hash), or null when it is missing/empty. */
function auth_stored_password(): ?string {
    if (!is_file(PANEL_PASSWORD_FILE)) {
        return null;
    }
    $stored = trim((string) file_get_contents(PANEL_PASSWORD_FILE));
    return $stored === '' ? null : $stored;
}

function auth_check_password(string $input): bool {
    $stored = auth_stored_password();
    if ($stored === null || $input === '') {
        return false;
    }

    // A hash from password_hash(), otherwise a plain-text password
    if (password_get_info($stored)['algo'] !== null) {
        return password_verify($input, $stored);
    }
    return hash_equals($stored, $input);
}

function auth_login(): void {
    auth_start_session();
    session_regenerate_id(true); // prevent session fixation
    $_SESSION[PANEL_SESSION_KEY] = true;
}

function auth_logout(): void {
    auth_start_session();

    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $params['path'],
        'secure'   => $params['secure'],
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_destroy();
}
