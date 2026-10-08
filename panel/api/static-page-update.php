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

$markdown = new Markdown($content_path);
$existing = $markdown->getRawPage($route_path, $content_path);

if ($existing === null) {
    json_error('Page not found.', 404);
}

$data = sanitize_fields($_POST, [
    'title' => ['trim'],
    'body'  => ['trim'],
]);

// Start from the existing frontmatter so fields the panel doesn't edit
// survive the save; only the fields below are replaced. Empty values are
// dropped by `writePage()`, which is how a cleared field is removed.
$meta = $existing;
unset($meta['route'], $meta['slug'], $meta['body'], $meta['_file']);

$meta = array_merge($meta, [
    'title'     => $data['title'],
    'date'      => $_POST['date'] ?? null,
]);

$savedSlug = $markdown->writePage($route_path, $existing['slug'], $meta, $data['body'] ?? '');

if ($savedSlug === null) {
    json_error('Failed to write the page file.', 500);
}

json_success([
    'route' => $route_path,
    'slug' => $savedSlug['slug'],
]);
