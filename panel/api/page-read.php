<?php

$route_path = $_GET['routePath'] ?? null;

if ($route_path === null) {
    json_error('Missing routePath.', 400);
}

$slug = $_GET['slug'] ?? '';

if ($slug === '') {
    json_error('Missing slug.', 400);
}

$markdown = new Markdown($route_path);
$data = $markdown->getRawPage($route_path,$slug);

if ($data === null) {
    json_error('Page not found.', 404);
}

// Only expose what the edit form needs (no `_file` absolute path)
$date = $data['date'] ?? null;
json_success([
    'data' => [
        'route'     => $data['route'] ?? null,
        'slug'      => $data['slug'],
        'title'     => $data['title'] ?? '',
        'date'      => $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (is_scalar($date) ? (string) $date : null),
        'category'  => $data['category'] ?? null,
        'tags'      => array_values((array) ($data['tags'] ?? [])),
        'thumbnail' => $data['thumbnail'] ?? null,
        'body'      => $data['body'],
    ],
]);
