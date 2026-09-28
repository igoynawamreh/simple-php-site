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
 * Find the DYNAMIC_PAGES config entry matching $path, tolerant of
 * leading/trailing slash variations ('article', '/article', '/article/'
 * all match the same config key).
 */
function resolve_dynamic_page(string $path): ?array {
    $normalized = '/' . trim($path, '/');

    foreach (DYNAMIC_PAGES as $key => $page) {
        if ('/' . trim($key, '/') === $normalized) {
            return $page;
        }
    }

    return null;
}

/**
 * Generate a sorted/filtered list of pages for a DYNAMIC_PAGES entry,
 * reading from its markdown metadata cache file (<dir>/.cache.php).
 *
 * $order_by/$order_dir default to whatever is set in the page's config
 * ('markdown.order_by' / 'markdown.order_dir') when not explicitly passed.
 */
function generate_list(
    string $path,
    int $count = 0,
    ?string $order_by = null,
    ?string $order_dir = null,
    ?string $category = null,
    ?string $tag = null
): array {
    $page_config = resolve_dynamic_page($path);

    if ($page_config === null || empty($page_config['content']['dir'])) {
        return [];
    }

    $dir = rtrim($page_config['content']['dir'], '/');

    $order_by  = $order_by  ?? ($page_config['content']['order_by']  ?? 'title');
    $order_dir = $order_dir ?? ($page_config['content']['order_dir'] ?? 'asc');

    // Reads a valid cache directly, or rebuilds it internally if
    // missing/stale.
    $markdown = new Markdown($dir);
    $pages = $markdown->getAllPagesMeta($path);

    if (empty($pages)) {
        return ['title' => $page_config['title'] ?? null, 'list' => []];
    }

    if ($category !== null && $category !== '') {
        $pages = array_values(array_filter(
            $pages,
            fn($p) => ($p['category'] ?? null) === $category
        ));
    }

    if ($tag !== null && $tag !== '') {
        $tag = "\x1a" . $tag . "\x1a";
        $tags = "\x1a" . implode("\x1a", (array) ($p['tags'] ?? [])) . "\x1a";
        $pages = array_values(array_filter(
            $pages,
            fn($p) => strpos($tags, $tag) !== false
        ));
    }

    $getSortValue = fn($p) => $p[$order_by] ?? $p[$order_by] ?? null;

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
        'url'   => '/' . trim($path, '/'),
        'list'  => $pages,
    ];
}

/**
 * Generate a de-duplicated list of categories used across all pages in a
 * DYNAMIC_PAGES entry, each with a link to the filtered listing
 * (?category=...). Reads from the markdown metadata cache file.
 */
function generate_category_list(string $path): array {
    $page_config = resolve_dynamic_page($path);

    if ($page_config === null || empty($page_config['content']['dir'])) {
        return [];
    }

    $dir = rtrim($page_config['content']['dir'], '/');

    // Reads a valid cache directly, or rebuilds it internally if missing/stale.
    $markdown = new Markdown($dir);
    $pages = $markdown->getAllPagesMeta($path);

    if (empty($pages)) {
        return [];
    }

    // Collect unique categories — use category name as array key to dedupe cheaply.
    $categories = [];
    foreach ($pages as $page) {
        $category = $page['category'] ?? null;
        if ($category !== null && $category !== '') {
            $categories[$category] = true;
        }
    }

    $categoryTitles = array_keys($categories);
    sort($categoryTitles, SORT_STRING | SORT_FLAG_CASE);

    $base = '/' . trim($path, '/');
    $currentCategory = $_GET['category'] ?? null;

    return [
        'selected' => $currentCategory,
        'list'     => array_map(
            fn($category) => [
                'title'  => $category,
                'url'    => $base . '?category=' . urlencode($category),
                'active' => $currentCategory !== null && $currentCategory === $category,
            ],
            $categoryTitles
        ),
    ];
}

/**
 * Generate a de-duplicated list of tags used across all pages in a
 * DYNAMIC_PAGES entry, each with a link to the filtered listing
 * (?tag=...). Reads from the markdown metadata cache file.
 */
function generate_tag_list(string $path): array {
    $page_config = resolve_dynamic_page($path);

    if ($page_config === null || empty($page_config['content']['dir'])) {
        return [];
    }

    $dir = rtrim($page_config['content']['dir'], '/');

    // Reads a valid cache directly, or rebuilds it internally if missing/stale.
    $markdown = new Markdown($dir);
    $pages = $markdown->getAllPagesMeta($path);

    if (empty($pages)) {
        return [];
    }

    // Collect unique tags — use tag name as array key to dedupe cheaply.
    $tags = [];
    foreach ($pages as $page) {
        foreach ((array) ($page['tags'] ?? []) as $tag) {
            $tags[$tag] = true;
        }
    }

    $tagTitles = array_keys($tags);
    sort($tagTitles, SORT_STRING | SORT_FLAG_CASE);

    $base = '/' . trim($path, '/');
    $currentTag = $_GET['tag'] ?? null;

    return [
        'selected' => $currentTag,
        'list'     => array_map(
            fn($tag) => [
                'title' => $tag,
                'url'   => $base . '?tag=' . urlencode($tag),
                'active' => $currentTag !== null && $currentTag === $tag,
            ],
            $tagTitles
        ),
    ];
}

/**
 * Read a markdown file, split its YAML frontmatter from the body, resolve
 * {{ ... }} template placeholders in the body, then parse it to HTML.
 * Returns an array of all frontmatter fields plus 'content' (rendered HTML).
 * Returns an empty array if the file doesn't exist.
 */
function render_md_from_file(string $file_path): array {
    if (!file_exists($file_path)) {
        return [];
    }

    $markdown = new Markdown(dirname($file_path));

    $raw = file_get_contents($file_path);
    [$meta, $body] = $markdown->parseFrontmatter($raw);

    $body = get_template_renderer()->render($body);

    return array_merge($meta, [
        'content' => x\markdown\from($body),
    ]);
}
