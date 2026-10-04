<?php

// Helpers for the media API (panel/api/media-*.php).
//
// Files live in "<project root>/media", outside the panel folder, and the
// web server serves them directly at "<base url>/media/<name>".

// Largest single file we accept. The effective limit is also capped by
// php.ini (upload_max_filesize and post_max_size).
const MEDIA_MAX_SIZE = 5 * 1024 * 1024; // 5MB

// Allowed extensions, grouped by type. Anything else is rejected on upload.
// Left out on purpose:
//  - html, svg, js: they can run script in a visitor's browser
//  - txt, md: ".htaccess" already blocks downloading those
const MEDIA_EXTENSIONS = [
    'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
    'other'    => ['zip'],
];

/** The media folder (created on first use, with a hardened .htaccess). */
function media_dir(): string {
    $dir = dirname(__DIR__) . '/media';

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        json_error('Could not create the media folder. Check the permissions of the project root.', 500);
    }

    // Defence in depth: uploads are data, never code. (The extension
    // allow-list already keeps scripts out.)
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, <<<'HT'
# Uploaded files are data, never code: refuse anything that could be run.
<FilesMatch "\.(php[0-9]*|phtml|phar|pht|phps|cgi|pl|py|sh)$">
  Require all denied
</FilesMatch>

HT);
    }

    return $dir;
}

/** Every allowed extension, flat. */
function media_allowed_extensions(): array {
    return array_merge(...array_values(MEDIA_EXTENSIONS));
}

/** "image", "document" or "other" for an extension. */
function media_type(string $ext): string {
    foreach (['image', 'document'] as $type) {
        if (in_array($ext, MEDIA_EXTENSIONS[$type], true)) {
            return $type;
        }
    }
    return 'other';
}

/** php.ini size ("2M", "512K", ...) in bytes; 0 / empty means unlimited. */
function media_ini_bytes(string $name): int {
    $value = trim((string) ini_get($name));
    $bytes = (int) $value;

    $bytes *= match (strtolower(substr($value, -1))) {
        'g'     => 1024 ** 3,
        'm'     => 1024 ** 2,
        'k'     => 1024,
        default => 1,
    };

    return $bytes > 0 ? $bytes : PHP_INT_MAX;
}

/** Effective per-file limit in bytes. */
function media_max_size(): int {
    return min(MEDIA_MAX_SIZE, media_ini_bytes('upload_max_filesize'), media_ini_bytes('post_max_size'));
}

function media_format_size(int $bytes): string {
    foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
        if ($bytes < 1024 ** ($i + 1) || $unit === 'GB') {
            return ($i === 0 ? $bytes : round($bytes / 1024 ** $i, 1)) . ' ' . $unit;
        }
    }
    return $bytes . ' B';
}

/**
 * Turn an uploaded file name into a safe [base, extension] pair:
 * ASCII letters, digits, "-" and "_" only, and a single dot. Dots inside the
 * name are replaced, so "x.php.jpg" can never be read as a PHP file by a
 * server that looks at every extension.
 */
function media_sanitize_name(string $original): array {
    $original = basename(str_replace('\\', '/', $original));

    $ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $base = pathinfo($original, PATHINFO_FILENAME);
    $base = strtolower(trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $base), '-_'));
    $base = substr($base, 0, 100) ?: 'file';

    return [$base, $ext];
}

/** "name.ext", or "name-1.ext", "name-2.ext" ... when the name is taken. */
function media_unique_name(string $dir, string $base, string $ext): string {
    $name = "$base.$ext";

    for ($i = 1; file_exists("$dir/$name"); $i++) {
        $name = "$base-$i.$ext";
    }

    return $name;
}

/**
 * Full path of an existing media file, or null. Only plain file names are
 * accepted: no directories, no ".." and no dot-files (".htaccess").
 */
function media_resolve(string $name): ?string {
    if (
        $name === ''
        || $name[0] === '.'
        || $name !== basename($name)
        || str_contains($name, '\\')
        || str_contains($name, "\0")
    ) {
        return null;
    }

    $path = media_dir() . '/' . $name;

    return is_file($path) ? $path : null;
}

/** Site-relative URL, so it also works when the site is in a subfolder. */
function media_url(string $name): string {
    global $base_url;

    return ($base_url ?? '') . '/media/' . rawurlencode($name);
}

function media_item(string $path): array {
    $name = basename($path);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    return [
        'name'     => $name,
        'url'      => media_url($name),
        'ext'      => $ext,
        'type'     => media_type($ext),
        'size'     => (int) filesize($path),
        'modified' => (int) filemtime($path), // Unix timestamp
    ];
}
