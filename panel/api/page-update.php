<?php

$route_path = $_GET['routePath'] ?? null;

if ($route_path === null) {
    json_error('Missing routePath.', 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$slug = $_POST['slug'] ?? '';

if ($slug === '') {
    json_error('Missing slug.', 400);
}

$errors = validate_fields($_POST, [
    'title' => ['required', 'max:200'],
    'date'  => ['date'],
]);

if (!empty($errors)) {
    json_validation_error($errors);
}

$markdown = new Markdown($route_path);
$existing = $markdown->getRawPage($route_path, $slug);

if ($existing === null) {
    json_error('Page not found.', 404);
}

// `writePage()` re-sanitizes the slug. If this file's name would change under
// that rule, saving would create a second file instead of updating this one.
$normalized_slug = sanitize_fields(['slug' => $existing['slug']], ['slug' => ['trim', 'lowercase', 'slug']])['slug'];
if ($normalized_slug !== $existing['slug']) {
    json_error('This page\'s file name is not a valid slug, so it cannot be edited here.', 409);
}

$data = sanitize_fields($_POST, [
    'title'     => ['trim'],
    'category'  => ['trim'],
    'thumbnail' => ['trim'],
    'body'      => ['trim'],
]);

$tags = array_values(array_filter((array) ($_POST['tags'] ?? []), fn($t) => $t !== ''));

// Start from the existing frontmatter so fields the panel doesn't edit
// survive the save; only the fields below are replaced. Empty values are
// dropped by `writePage()`, which is how a cleared field is removed.
$meta = $existing;
unset($meta['route'], $meta['slug'], $meta['body'], $meta['_file']);

$meta = array_merge($meta, [
    'title'     => $data['title'],
    'date'      => $_POST['date'] ?? null,
    'category'  => $data['category'] ?? null,
    'tags'      => $tags ?? [],
    'thumbnail' => $data['thumbnail'] ?? null,
]);

// Renaming the slug itself isn't handled here (that would mean
// delete-old + create-new).
$savedSlug = $markdown->writePage($route_path, $existing['slug'], $meta, $data['body'] ?? '');

if ($savedSlug === null) {
    json_error('Failed to write the page file.', 500);
}

json_success([
    'route' => $savedSlug['route'],
    'slug' => $savedSlug['slug'],
]);
