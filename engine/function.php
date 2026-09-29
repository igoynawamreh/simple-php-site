<?php

require_once __DIR__ . '/lib/vendor/taufik-nurrohman/markdown/from.php';
require_once __DIR__ . '/lib/vendor/taufik-nurrohman/y-a-m-l/from.php';

require_once __DIR__ . '/lib/renderer.php';
require_once __DIR__ . '/lib/markdown.php';

require_once __DIR__ . '/lib/typecast.php';
require_once __DIR__ . '/lib/sanitizer.php';
require_once __DIR__ . '/lib/validator.php';
require_once __DIR__ . '/lib/response.php';

/**
 * [E]scape HTML [at]tribute’s value
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_HTML5 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL helper
 *   url()            -> "https://example.com"
 *   url('/')         -> "https://example.com"
 *   url('blog/foo')  -> "https://example.com/blog/foo"
 *   url('/blog/bar') -> "https://example.com/blog/bar"
 */
function url(string $route = ''): string {
    global $home_url;
    $route = trim($route, '/');
    return $route === '' ? $home_url : $home_url . '/' . $route;
}

/**
 * Format a date string (e.g. '2026-01-01' or '2026-01-01 00:00')
 * using a PHP date() format string. Returns '' if $value is empty/null
 * or can't be parsed.
 */
function format_date(DateTimeInterface|null|string $value, string $format = 'Y-m-d'): string {
    if ($value === null) {
        return '';
    }
    if (is_string($value) && trim($value) === '') {
        return '';
    }

    $timestamp = $value instanceof DateTimeInterface ? $value->getTimestamp() : strtotime($value);

    if ($timestamp === false) {
        return '';
    }

    return date($format, $timestamp);
}

/**
 * Merge a target URL's query params with the CURRENT request's query
 * params ($_GET) — the target URL's own params win on key collisions,
 * everything else from the current URL is preserved.
 */
function merge_query_url(string $url): string {
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';

    $newParams = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $newParams);
    }

    // Current URL's params, then overlay with the new ones (new wins on conflict)
    $merged = array_merge($_GET, $newParams);

    $query = http_build_query($merged);

    return $path . ($query !== '' ? '?' . $query : '');
}

/**
 * Find the DYNAMIC_PAGES config entry matching $route, tolerant of
 * leading/trailing slash variations ('article', '/article', '/article/'
 * all match the same config key).
 */
function resolve_dynamic_page(string $route): ?array {
    $normalized = '/' . trim($route, '/');

    foreach (DYNAMIC_PAGES as $key => $page) {
        if ('/' . trim($key, '/') === $normalized) {
            return $page;
        }
    }

    return null;
}

/**
 * Filter a list of page metadata by arbitrary fields.
 * - array field (e.g. tags)       -> matches if any item equals the expected value
 * - scalar field (e.g. category)  -> matches if the value equals the expected value
 * - $expected is an array         -> matches if any of the values equals (OR)
 * - multiple fields at once       -> all of them must match (AND)
 * - null / '' / [] filters are ignored
 */
function filter_pages_by_fields(array $pages, array $filters): array {
    // URL values are always strings, while YAML values can be int/bool
    $normalize = fn($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;

    foreach ($filters as $field => $expected) {
        if ($expected === null || $expected === '' || $expected === []) {
            continue;
        }

        $expectedValues = array_map($normalize, (array) $expected);

        $pages = array_values(array_filter(
            $pages,
            function ($p) use ($field, $expectedValues, $normalize) {
                $actual = $p[$field] ?? null;
                $actual = is_array($actual) ? $actual : [$actual];

                foreach ($actual as $v) {
                    if (is_scalar($v) && in_array($normalize($v), $expectedValues, true)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    return $pages;
}

/**
 * Generate a sorted/filtered list of pages for a DYNAMIC_PAGES entry,
 * reading from its markdown metadata cache file (<dir>/.cache.php).
 *
 * $order_by/$order_dir default to whatever is set in the page's config
 * ('content.order_by' / 'content.order_dir') when not explicitly passed.
 */
function generate_page_list(
    string $route,
    int $count = 0,
    ?string $order_by = null,
    ?string $order_dir = null,
    array $filters = []
): array {
    $page_config = resolve_dynamic_page($route);

    if ($page_config === null || empty($page_config['content']['dir'])) {
        return [];
    }

    $dir = rtrim($page_config['content']['dir'], '/');

    $order_by  = $order_by  ?? ($page_config['content']['order_by']  ?? 'title');
    $order_dir = $order_dir ?? ($page_config['content']['order_dir'] ?? 'asc');

    // Reads a valid cache directly, or rebuilds it internally if
    // missing/stale.
    $markdown = new Markdown($dir);
    $pages = $markdown->getAllPagesMeta($route);

    if (empty($pages)) {
        return [
            'title' => $page_config['title'] ?? null,
            'route' => '/' . trim($route, '/'),
            'list'  => [],
        ];
    }

    // Filter by any metadata field
    $pages = filter_pages_by_fields($pages, $filters);

    $getSortValue = fn($p) => $p[$order_by] ?? null;

    usort($pages, function ($a, $b) use ($getSortValue, $order_by, $order_dir) {
        $va = $getSortValue($a);
        $vb = $getSortValue($b);

        if ($order_by === 'date') {
            $cmp = strtotime($va ?? '1970-01-01') <=> strtotime($vb ?? '1970-01-01');
        } elseif (is_numeric($va) && is_numeric($vb)) {
            $cmp = $va <=> $vb;
        } else {
            $cmp = strcasecmp((string) $va, (string) $vb);
        }

        return strtolower($order_dir) === 'desc' ? -$cmp : $cmp;
    });

    if ($count > 0) {
        $pages = array_slice($pages, 0, $count);
    }

    return [
        'title' => $page_config['title'] ?? null,
        'route' => '/' . trim($route, '/'),
        'list'  => $pages,
    ];
}

/**
 * Generate a de-duplicated list of values used for a given metadata field
 * across all pages in a DYNAMIC_PAGES entry, each with a link to the
 * filtered listing (?{param}=...). Works for scalar fields (e.g. category)
 * and list fields (e.g. tags). Reads from the markdown metadata cache.
 *
 * @param string      $route  Dynamic page route.
 * @param string      $field Metadata field name (e.g. 'category', 'tags', 'author').
 * @param string|null $param URL query parameter name. Defaults to $field.
 */
function generate_field_list(string $route, string $field, ?string $param = null): array {
    $param = $param ?? $field;

    $page_config = resolve_dynamic_page($route);

    if ($page_config === null || empty($page_config['content']['dir'])) {
        return [];
    }

    $dir = rtrim($page_config['content']['dir'], '/');

    // Reads a valid cache directly, or rebuilds it internally if missing/stale.
    $markdown = new Markdown($dir);
    $pages = $markdown->getAllPagesMeta($route);

    if (empty($pages)) {
        return [];
    }

    // Collect unique values: use the value as array key to dedupe cheaply.
    // (array) cast handles both scalar fields and list fields.
    $values = [];
    foreach ($pages as $page) {
        foreach ((array) ($page[$field] ?? []) as $value) {
            if (is_scalar($value) && $value !== '') {
                $values[(string) $value] = true;
            }
        }
    }

    // array_keys() turns numeric-looking keys into ints, so cast back to string.
    $titles = array_map('strval', array_keys($values));
    sort($titles, SORT_STRING | SORT_FLAG_CASE);

    $base = '/' . trim($route, '/');

    // The URL value may be a string (?tags=php) or an array (?tags[]=php&tags[]=js).
    $selected = $_GET[$param] ?? null;
    $selectedValues = $selected === null ? [] : array_map('strval', (array) $selected);

    return [
        'selected' => $selected,
        'list'     => array_map(
            fn($title) => [
                'title'  => $title,
                'route'  => $base . '?' . http_build_query([$param => $title]),
                'active' => in_array($title, $selectedValues, true),
            ],
            $titles
        ),
    ];
}
