<?php

require_once __DIR__ . '/lib/typecast.php';
require_once __DIR__ . '/lib/sanitizer.php';
require_once __DIR__ . '/lib/validator.php';
require_once __DIR__ . '/lib/response.php';
require_once __DIR__ . '/lib/markdown.php';

/**
 * Escapes `$value` for HTML text and attributes. `null` becomes `''`.
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_HTML5 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Builds an absolute URL from `$home_url`. Slashes around `$route` are ignored.
 *   url()            -> "https://example.com"
 *   url('/blog/foo') -> "https://example.com/blog/foo"
 */
function url(string $route = ''): string {
    global $home_url;
    $route = trim($route, '/');
    return $route === '' ? $home_url : $home_url . '/' . $route;
}

/**
 * Formats a date (`DateTimeInterface` or string) with a `date()` format.
 * Returns `''` if `$value` is empty or can't be parsed.
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
 * Merges the query params of `$url` into the current `$_GET`; params in `$url` win.
 * Keys in `$unset` are removed, e.g. `'page'` to go back to the first page.
 * Returns only the path and query of `$url`.
 */
function merge_query_url(string $url, array $unset = []): string {
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';

    $newParams = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $newParams);
    }

    $merged = array_merge($_GET, $newParams);

    foreach ($unset as $key) {
        unset($merged[$key]);
    }

    $query = http_build_query($merged);

    return $path . ($query !== '' ? '?' . $query : '');
}

/**
 * Adds `$value` to the multi-value param `$field` of the current `$_GET`
 * (e.g. `?tags[]=foo&tags[]=bar`), or removes it if already selected.
 * The param is dropped once no value is left.
 * Keys in `$unset` are removed, e.g. `'page'` to go back to the first page.
 */
function toggle_query_value(string $url, string $field, string $value, array $unset = []): string {
    $parsed = parse_url($url);
    $path   = $parsed['path'] ?? '';

    $params = $_GET;

    $selected = array_values(array_filter(
        (array) ($params[$field] ?? []),
        fn($v) => $v !== null && $v !== ''
    ));

    $selected = in_array($value, $selected, true)
        ? array_values(array_diff($selected, [$value]))
        : array_values(array_unique(array_merge($selected, [$value])));

    if (empty($selected)) {
        unset($params[$field]);
    } else {
        $params[$field] = $selected;
    }

    foreach ($unset as $key) {
        unset($params[$key]);
    }

    $query = http_build_query($params);

    return $path . ($query !== '' ? '?' . $query : '');
}

/**
 * Returns the `PAGES` entry whose `route` equals `$route`, ignoring slashes
 * around it (`'blog'`, `'/blog'` and `'/blog/'` match alike).
 * Placeholder routes such as `/blog/[foo]` are not matched against concrete paths.
 */
function resolve_page_config(string $route): ?array {
    $normalized = '/' . trim($route, '/');

    foreach (PAGES as $page) {
        $pageRoute = '/' . trim($page['route'] ?? '', '/');

        if ($pageRoute === $normalized) {
            return $page;
        }
    }

    return null;
}

/**
 * Converts an absolute path to a path relative to the project root, e.g.
 * `/var/www/simple-php-site/foo/bar` -> `foo/bar`.
 * Paths outside the project root are returned unchanged.
 */
function to_relative_path(string $absPath): string {
    // This file is in `engine/*`, so the project root is one level up
    $root    = rtrim(str_replace('\\', '/', dirname(__DIR__, 1)), '/');
    $absPath = str_replace('\\', '/', $absPath);

    if (strpos($absPath, $root . '/') !== 0) {
        return $absPath;
    }

    return ltrim(substr($absPath, strlen($root)), '/');
}
