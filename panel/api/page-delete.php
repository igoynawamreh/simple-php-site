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

$markdown = new Markdown($route_path);

// deletePage() treats a missing file as success; the panel wants to know
if ($markdown->getRawPage($route_path, $slug) === null) {
    json_error('Page not found.', 404);
}

if (!$markdown->deletePage($slug)) {
    json_error('Failed to delete the page file. Check file permissions.', 500);
}

json_success(['slug' => $slug]);
