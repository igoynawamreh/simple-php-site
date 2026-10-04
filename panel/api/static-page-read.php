<?php

$route_path = $_GET['routePath'] ?? null;

$root = dirname(__DIR__, 2);
$content_path = null;
if (!empty($_GET['contentPath'])) {
    $path = realpath($root . '/' . $_GET['contentPath']);
    if ($path !== false && str_starts_with($path, $root . '/')) {
        $content_path = $path;
    }
}

if ($route_path === null) {
    json_error('Missing routePath.', 400);
}
if ($content_path === null) {
    json_error('Missing contentPath.', 400);
}

$markdown = new Markdown($content_path);
$data = $markdown->getRawPage($route_path, $content_path);

if ($data === null) {
    json_error('Page not found.', 404);
}

// Only expose what the edit form needs (no `_file` absolute path)
$date = $data['date'] ?? null;
json_success([
    'data' => [
        'route'     => $route_path,
        'slug'      => $data['slug'],
        'title'     => $data['title'] ?? '',
        'date'      => $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (is_scalar($date) ? (string) $date : null),
        'body'      => $data['body'],
    ],
]);
