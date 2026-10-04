<?php

$route_path = $_GET['routePath'] ?? null;

if ($route_path === null) {
    json_error('Missing routePath.', 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$errors = validate_fields($_POST, [
    'title' => ['required', 'max:200'],
    'date'  => ['date'],
]);

if (!empty($errors)) {
    json_validation_error($errors);
}

$_POST['slug'] = $_POST['slug'] ?? null;

$data = sanitize_fields($_POST, [
    'title'     => ['trim'],
    'slug'      => ['trim', 'lowercase', 'slug'],
    'category'  => ['trim'],
    'thumbnail' => ['trim'],
    'body'      => ['trim'],
]);

// Fall back to deriving the slug from the title if left blank
$slug = $data['slug'] ?: sanitize_fields(['slug' => $data['title']], ['slug' => ['trim', 'lowercase', 'slug']])['slug'];

if ($slug === null) {
    json_validation_error(['title' => 'Could not derive a valid slug from this title.']);
}

$markdown = new Markdown($route_path);

// Refuse to silently overwrite an existing page via "create"
if ($markdown->getRawPage($route_path,$slug) !== null) {
    json_validation_error(['slug' => 'An page with this slug already exists.']);
}

$tags = array_values(array_filter((array) ($_POST['tags'] ?? []), fn($t) => $t !== ''));

$meta = [
    'title'     => $data['title'],
    'date'      => $_POST['date'] ?? date('Y-m-d'),
    'category'  => $data['category'] ?? null,
    'tags'      => $tags ?? [],
    'thumbnail' => $data['thumbnail'] ?? null,
];

$savedSlug = $markdown->writePage($route_path, $slug, $meta, $data['body'] ?? '');

if ($savedSlug === null) {
    json_error('Failed to write the page file. Check directory permissions.', 500);
}

json_success([
    'route' => $savedSlug['route'],
    'slug' => $savedSlug['slug'],
]);
