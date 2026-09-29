<?php

$site  = [];
$page  = [];
$pages = [];
$pagination = [];
$is = [];

$site['title'] = STATE['title'] ?? null;

$page['route']       = $route;
$page['route:last']  = basename($route) === '' ? null : basename($route);
$page['route:query'] = substr($_SERVER['REQUEST_URI'], strlen($base_url));
$page['title']       = $site['title'];
$page['content']     = null;

foreach ($_GET as $key => $value) {
    $page['param:' . $key] = $value;
}

$is['home']         = false;
$is['static']       = false;
$is['dynamic']      = false;
$is['dynamic_list'] = false;
$is['dynamic_item'] = false;
$is['custom']       = false;
$is['404']          = false;
$is['markdown']     = false;

/**
 * Home page
 */
if ($route === '') {
    $page['title'] = HOME_PAGE['title'] ?? $page['title'];
    $is['home'] = true;
    if (isset(HOME_PAGE['content'])) {
        $content_file = HOME_PAGE['content'];
        if (is_file($content_file)) {
            $render_md = render_md_from_file($content_file);
            $page = array_merge($page, $render_md);
            $is['markdown'] = true;
        }
    }
    require HOME_PAGE['template'];
    exit;
}

/**
 * Static pages
 */
if (defined('STATIC_PAGES') && !empty(STATIC_PAGES)) {
    foreach (STATIC_PAGES as $static_page) {
        $pattern = trim($static_page['route'] ?? '', '/');

        // Build a regex from the URL pattern: literal segments are matched
        // as-is, [name] segments become named capture groups.
        $regexParts = [];
        foreach (explode('/', $pattern) as $segment) {
            if (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $segment, $m)) {
                $regexParts[] = '(?P<' . $m[1] . '>[^/]+)';
            } else {
                $regexParts[] = preg_quote($segment, '#');
            }
        }
        $regex = '#^' . implode('/', $regexParts) . '$#';

        if (!preg_match($regex, $route, $matches)) {
            continue;
        }

        // Expose each named placeholder as $page['route:whatever'].
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $page['route:' . $key] = $value;
            }
        }

        $page['title'] = $static_page['title'] ?? $page['title'];
        $is['static'] = true;

        if (isset($static_page['content'])) {
            $content_file = $static_page['content'];
            if (is_file($content_file)) {
                $render_md = render_md_from_file($content_file);
                $page = array_merge($page, $render_md);
                $is['markdown'] = true;
            }
        }

        require $static_page['template'];
        exit;
    }
}

/**
 * Dynamic pages
 *   /{list}/       → list or index page
 *   /{list}/{slug} → item page
 */
if (defined('DYNAMIC_PAGES') && !empty(DYNAMIC_PAGES)) {
    foreach (DYNAMIC_PAGES as $list_route => $dynamic_page) {
        $template     = $dynamic_page['template'] ?? [];
        $list_route_t = trim($list_route, '/');
        $markdown     = null;

        $isMarkdown = isset($dynamic_page['content']['dir']);

        $page['route:list'] = '/' . $list_route_t;
        $page['title']      = $dynamic_page['title'] ?? $page['title'];
        $page['title:list'] = $page['title'];

        $is['dynamic'] = true;

        if ($route !== $list_route_t && !str_starts_with($route, $list_route_t . '/')) {
            continue;
        }

        if ($isMarkdown) {
            $markdown = new Markdown($dynamic_page['content']['dir'], get_template_renderer());
            $is['markdown'] = true;
        }

        // /{list}/
        if ($route === $list_route_t) {
            if (empty($template['list'])) {
                break;
            }

            // Fields that may be used as filters (per page config, with a default)
            $allowedFilters = $dynamic_page['content']['filters'] ?? ['category', 'tags'];

            // Only whitelisted fields from the URL become filters
            $filters = array_intersect_key($_GET, array_flip($allowedFilters));

            $page = array_merge($page, [
                'param:page'      => isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1,
                'param:per_page'  => max(1, (int) ($_GET['per_page'] ?? $dynamic_page['content']['per_page'] ?? 10)),
                'param:order_by'  => $_GET['order_by'] ?? $dynamic_page['content']['order_by'] ?? 'title',
                'param:order_dir' => $_GET['order_dir'] ?? $dynamic_page['content']['order_dir'] ?? 'asc',
            ]);

            // Make sure these keys always exist, even when missing from the URL
            foreach (array_merge(['q'], $allowedFilters) as $key) {
                $page['param:' . $key] = $page['param:' . $key] ?? null;
            }

            $is['dynamic_list'] = true;

            if ($isMarkdown) {
                $pages = array_merge($pages, $markdown->getPages(
                    page: $page['param:page'],
                    route: $list_route,
                    perPage: $page['param:per_page'],
                    filters: $filters,
                    search: $page['param:q'],
                    orderBy: $page['param:order_by'],
                    orderDir: $page['param:order_dir'],
                ));

                $pagination = array_merge($pagination, renderPaginationLinks(
                    $pages['current_page'],
                    $pages['last_page'],
                    baseUrl: '/' . $list_route_t,
                    // Every active filter is carried over to the pagination links
                    extraParams: array_merge($filters, [
                        'q' => $page['param:q'],
                    ]),
                ));
            }

            require $template['list'];
            exit;
        }

        $pid = substr($route, strlen($list_route_t) + 1);
        $page['route:id'] = $pid;

        if ($isMarkdown && ($pid === '' || str_contains($pid, '/'))) {
            break;
        }

        // /{list}/{slug}
        if (empty($template['item'])) {
            break;
        }
        if ($isMarkdown) {
            $found = $markdown->getPage($list_route, $pid);
            if (!$found) {
                break;
            }
            $page = array_merge($page, $found);
        }
        $is['dynamic_item'] = true;

        require $page['template'] ?? null
            ? rtrim(dirname($page['_file']), '/') . '/' . ltrim($page['template'], '/')
            : $template['item'];
        exit;
    }
}

/**
 * Custom pages
 */
if (defined('CUSTOM_PAGES') && !empty(CUSTOM_PAGES)) {
    foreach (CUSTOM_PAGES as $custom_page) {
        $is['custom'] = true;
        require $custom_page['template'];
    }
}

/**
 * 404 page
 */
http_response_code(404);
$page['title'] = ERROR_PAGE['title'] ?? $page['title'];
$is['404'] = true;
if (isset(ERROR_PAGE['content'])) {
    $content_file = ERROR_PAGE['content'];
    if (is_file($content_file)) {
        $render_md = render_md_from_file($content_file);
        $page = array_merge($page, $render_md);
        $is['markdown'] = true;
    }
}
require ERROR_PAGE['template'];
exit;
