<?php

// POST multipart/form-data with one or more files in `files[]`.
//
// Request-level problems return an error. Otherwise the response is
// { success: true, results: [{ file, success, message?, item? }, ...] },
// with one entry per file, so one bad file doesn't lose the others.

require_once __DIR__ . '/../helper/media.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

// When the whole request is bigger than post_max_size, PHP throws the body
// away: $_FILES is empty even though data was sent.
$postMax = media_ini_bytes('post_max_size');
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMax) {
    json_error('The upload is larger than the server allows (' . media_format_size($postMax) . ' per request).', 413);
}

if (!isset($_FILES['files'])) {
    json_error('No files received.', 400);
}

$dir     = media_dir();
$maxSize = media_max_size();

$uploadErrors = [
    UPLOAD_ERR_INI_SIZE   => 'The file is larger than the server allows.',
    UPLOAD_ERR_FORM_SIZE  => 'The file is larger than the server allows.',
    UPLOAD_ERR_PARTIAL    => 'The upload was interrupted.',
    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
    UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads.',
    UPLOAD_ERR_CANT_WRITE => 'The server could not write the file.',
    UPLOAD_ERR_EXTENSION  => 'The upload was blocked by a PHP extension.',
];

// $_FILES['files'] is a column-per-field array: turn it into one entry per file
$files = [];
foreach ((array) $_FILES['files']['name'] as $i => $name) {
    $files[] = [
        'name'     => (string) $name,
        'tmp_name' => (string) ((array) $_FILES['files']['tmp_name'])[$i],
        'size'     => (int) ((array) $_FILES['files']['size'])[$i],
        'error'    => (int) ((array) $_FILES['files']['error'])[$i],
    ];
}

$fail = static fn(string $file, string $message): array => [
    'file' => $file, 'success' => false, 'message' => $message,
];

$results = [];

foreach ($files as $f) {
    $original = $f['name'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        $results[] = $fail($original, $uploadErrors[$f['error']] ?? 'The upload failed.');
        continue;
    }

    if (!is_uploaded_file($f['tmp_name'])) {
        $results[] = $fail($original, 'Not a valid upload.');
        continue;
    }

    if ($f['size'] === 0) {
        $results[] = $fail($original, 'The file is empty.');
        continue;
    }

    if ($f['size'] > $maxSize) {
        $results[] = $fail($original, 'The file is larger than ' . media_format_size($maxSize) . '.');
        continue;
    }

    [$base, $ext] = media_sanitize_name($original);

    if (!in_array($ext, media_allowed_extensions(), true)) {
        $results[] = $fail($original, $ext === '' ? 'The file has no extension.' : ".$ext files are not allowed.");
        continue;
    }

    // Images: check the content, not just the name
    if (media_type($ext) === 'image') {
        $isImage = class_exists('finfo')
            ? str_starts_with((string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']), 'image/')
            : @getimagesize($f['tmp_name']) !== false;

        if (!$isImage) {
            $results[] = $fail($original, 'The file is not a valid image.');
            continue;
        }
    }

    $name = media_unique_name($dir, $base, $ext);

    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) {
        $results[] = $fail($original, 'Could not save the file. Check the permissions of the media folder.');
        continue;
    }

    @chmod("$dir/$name", 0644);

    $results[] = [
        'file'    => $original,
        'success' => true,
        'item'    => media_item("$dir/$name"),
    ];
}

json_success(['results' => $results]);
