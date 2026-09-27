<?php

if (defined('STATE') && !empty(STATE['zone'])) {
    date_default_timezone_set(STATE['zone']);
}

if (defined('STATE') && array_key_exists('debug', STATE)) {
    ini_set('error_log', __DIR__ . '/.errors');
    if (STATE['debug']) {
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        ini_set('display_startup_errors', true);
        ini_set('html_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', false);
        ini_set('display_startup_errors', false);
        ini_set('max_execution_time', 300); // 5 minute(s)
    }
}

// Normalize `$_GET`, `$_POST`, `$_REQUEST` value(s)
$method = [&$_GET, &$_POST, &$_REQUEST];
array_walk_recursive($method, static function (&$v) {
    $v = clean_value($v);
});
