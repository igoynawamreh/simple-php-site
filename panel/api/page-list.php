<?php

$route_path = $_GET['routePath'] ?? null;

if ($route_path === null) {
    json_error('Missing routePath.', 400);
}

// Query: `page`, `count`, `q`, `category`, `tags[]`, `order_by`, `order_dir`
$allowedFilters = ['category', 'tags'];
// Only whitelisted fields from the URL become filters
$filters = array_intersect_key($_GET, array_flip($allowedFilters));

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$allowedOrderBy = ['date', 'title', 'slug', 'category'];
$orderBy  = in_array($_GET['order_by'] ?? null, $allowedOrderBy, true) ? $_GET['order_by'] : 'date';
$orderDir = ($_GET['order_dir'] ?? null) === 'asc' ? 'asc' : 'desc';

$markdown = new Markdown($route_path);

$result = $markdown->getPages(
    route: $route_path,
    page: max(1, (int) ($_GET['page'] ?? 1)),
    count: min(100, max(1, (int) ($_GET['count'] ?? 20))),
    search: $q,
    filters: $filters,
    orderBy: $orderBy,
    orderDir: $orderDir,
);

$pagination = $markdown->getPagination(
    currentPage: $result['current_page'],
    lastPage: $result['last_page'],
);

// Available filter options (every category/tag that exists, not only the
// ones on the current page).
$fields = $markdown->getPagesFields(route: $route_path, fields: $allowedFilters);

// Only expose what the panel needs: no absolute server paths (`_file`,
// `_cache_file`, ...) and no `DateTime` objects in the JSON.
$data = array_map(static function (array $p): array {
    $date = $p['date'] ?? null;
    return [
        'slug'     => $p['slug'],
        'title'    => $p['title'],
        'date'     => $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date,
        'category' => $p['category'] ?? null,
        'tags'     => array_values((array) ($p['tags'] ?? [])),
    ];
}, $result['pages']);

json_success([
    'data' => $data,
    'meta'  => [
        'total'        => $result['total'],
        'count'        => $result['count'],
        'current_page' => $result['current_page'],
        'last_page'    => $result['last_page'],
    ],
    // Page numbers only ('...' = gap); URLs are built on the client
    'pagination' => array_column($pagination['pages'], 'page'),
    'fields' => [
        'category' => array_column($fields['category']['state'] ?? [], 'title'),
        'tags'     => array_column($fields['tags']['state'] ?? [], 'title'),
    ],
]);
