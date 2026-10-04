<?php

require_once __DIR__ . '/../media.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$name = (string) ($_POST['name'] ?? '');

if ($name === '') {
    json_error('Missing file name.', 400);
}

$path = media_resolve($name);

if ($path === null) {
    json_error('File not found.', 404);
}

if (!@unlink($path)) {
    json_error('Failed to delete the file. Check the permissions of the media folder.', 500);
}

json_success(['name' => $name]);
