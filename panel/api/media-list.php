<?php

// Query: page, count, q, type (image|document|other), order_by (date|name|size), order_dir

require_once __DIR__ . '/../helper/media.php';

$q = strtolower(trim((string) ($_GET['q'] ?? '')));

$type     = in_array($_GET['type'] ?? null, ['image', 'document', 'other'], true) ? $_GET['type'] : '';
$orderBy  = in_array($_GET['order_by'] ?? null, ['date', 'name', 'size'], true) ? $_GET['order_by'] : 'date';
$orderDir = ($_GET['order_dir'] ?? null) === 'asc' ? 1 : -1;

$count = min(100, max(1, (int) ($_GET['count'] ?? 24)));
$page  = max(1, (int) ($_GET['page'] ?? 1));

$items = [];

foreach (new DirectoryIterator(media_dir()) as $file) {
    $name = $file->getFilename();

    // Skip ".", "..", sub-folders and dot-files (".htaccess")
    if ($name[0] === '.' || !$file->isFile()) {
        continue;
    }

    if ($q !== '' && !str_contains(strtolower($name), $q)) {
        continue;
    }

    $item = media_item($file->getPathname());

    if ($type !== '' && $item['type'] !== $type) {
        continue;
    }

    $items[] = $item;
}

usort($items, static function (array $a, array $b) use ($orderBy, $orderDir): int {
    $cmp = match ($orderBy) {
        'name'  => strcasecmp($a['name'], $b['name']),
        'size'  => $a['size'] <=> $b['size'],
        default => $a['modified'] <=> $b['modified'],
    };

    return ($cmp ?: strcasecmp($a['name'], $b['name'])) * ($cmp ? $orderDir : 1);
});

$total    = count($items);
$lastPage = max(1, (int) ceil($total / $count));
$page     = min($page, $lastPage);
$items    = array_slice($items, ($page - 1) * $count, $count);

json_success([
    'items' => $items,
    'meta'  => [
        'total'        => $total,
        'count'        => $count,
        'current_page' => $page,
        'last_page'    => $lastPage,
    ],
    'limits' => [
        'max_size'   => media_max_size(),
        'extensions' => media_allowed_extensions(),
    ],
]);
