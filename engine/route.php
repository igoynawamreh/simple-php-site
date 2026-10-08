<?php

$state = [];
$site  = [];
$page  = [];
$pages = [];
$pagination = [];
$is = [];

$state['debug'] = STATE['debug'] ?? false;
$state['env']   = STATE['env'] ?? 'production';
$state['title'] = STATE['title'] ?? null;
$state['zone']  = STATE['zone'] ?? null;

$site['title'] = $state['title'];
$site['url']   = $home_url;

$page['route']       = $route;
$page['route:last']  = basename($route) === '' ? null : basename($route);
$page['route:query'] = substr($_SERVER['REQUEST_URI'], strlen($base_url));
$page['title']       = $site['title'];
$page['content']     = null;

$is['home']      = false;
$is['page']      = false;
$is['page_list'] = false;
$is['page_item'] = false;
$is['custom']    = false;
$is['404']       = false;
$is['markdown']  = false;

/**
 * Home page
 */
if ($route === '') {
    $page['title'] = HOME_PAGE['title'] ?? $page['title'];
    $is['home'] = true;
    if (isset(HOME_PAGE['content'])) {
        $content_file = HOME_PAGE['content'];
        if (is_file($content_file)) {
            $markdown = new Markdown($content_file);
            $page = array_merge(
                $page,
                $markdown->getPage('/', $content_file)
            );
            $is['markdown'] = true;
        }
    }
    require HOME_PAGE['template'];
    exit;
}

/**
 * Pages
 */
if (defined('PAGES') && !empty(PAGES)) {
    foreach (PAGES as $page_config) {
        // A `template` of `{ list, item }` registers two patterns:
        // the route itself (list) and the route + `/[slug]` (item)
        $hasListItemTemplate = is_array($page_config['template'] ?? null)
            && isset($page_config['template']['list'], $page_config['template']['item']);

        // `true` when `content` has a `dir`. Independent of `$hasListItemTemplate`.
        $hasContentDir = isset($page_config['content']['dir']);

        $candidates = $hasListItemTemplate
            ? [
                'list' => $page_config['route'],
                'item' => rtrim($page_config['route'], '/') . '/[slug]',
            ]
            : ['plain' => $page_config['route']];

        foreach ($candidates as $mode => $routePattern) {
            $pattern = trim($routePattern ?? '', '/');

            // Turns the pattern into a regex: literal segments match as-is,
            // `[name]` captures one segment, and `[...name]` captures the rest
            // of the path (slashes included), so it must be the last segment.
            $regexParts = [];
            $segments   = explode('/', $pattern);

            foreach ($segments as $i => $segment) {
                if (preg_match('/^\[\.\.\.([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $segment, $m)) {
                    // Segments after a catch-all would be unreachable
                    $regexParts[] = '(?P<' . $m[1] . '>.*)';
                    break;
                } elseif (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $segment, $m)) {
                    $regexParts[] = '(?P<' . $m[1] . '>[^/]+)';
                } else {
                    $regexParts[] = preg_quote($segment, '#');
                }
            }
            $regex = '#^' . implode('/', $regexParts) . '$#';

            if (!preg_match($regex, $route, $matches)) {
                continue;
            }

            // Captured segments become `$page['route:<name>']`
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $page['route:' . $key] = $value;
                }
            }

            $page['title'] = $page_config['title'] ?? $page['title'];
            $is['page']    = true;

            if ($mode === 'item') {
                $is['page_item'] = true;
            } elseif ($mode === 'list') {
                $is['page_list'] = true;
            }

            // `content` is a single Markdown file
            if (isset($page_config['content']) && !is_array($page_config['content'])) {
                $content_file = $page_config['content'];
                if (is_file($content_file)) {
                    $markdown = new Markdown($content_file);
                    $page = array_merge(
                        $page,
                        $markdown->getPage($page_config['route'], $content_file)
                    );
                    $is['markdown'] = true;
                }
            }

            // `content.dir` is a Markdown directory: fills `$page` (item) or `$pages` (list)
            if ($hasContentDir) {
                $dir      = rtrim($page_config['content']['dir'], '/');
                $markdown = new Markdown($dir);

                $page['route:list'] = '/' . $page_config['route'];
                $page['title:list'] = $page['title'];

                if ($mode === 'item') {
                    $slug = $page['route:slug'] ?? '';
                    $item = $markdown->getPage($page_config['route'], $slug);

                    // No file for this slug: try the next `PAGES` entry, then the 404 page
                    if ($item === null) {
                        continue 2;
                    }

                    $page = array_merge($page, $item);
                } elseif ($mode === 'list') {
                    // Allowed filter fields: `content.filter`, default `category` and `tags`
                    $allowedFilters = $page_config['content']['filter'] ?? ['category', 'tags'];

                    // Only allowed fields from `$_GET` become filters
                    $filters = array_intersect_key($_GET, array_flip($allowedFilters));

                    $pages['fields'] = $markdown->getPagesFields(
                        route: $page_config['route'],
                        fields: $allowedFilters,
                    );

                    $pages = array_merge($pages, $markdown->getPages(
                        route: $page_config['route'],
                        page: max(1, (int) ($_GET['page'] ?? 1)),
                        count: max(1, (int) ($_GET['count'] ??$page_config['content']['count'] ?? 10)),
                        search: $_GET['q'] ?? null,
                        filters: $filters,
                        orderBy: $_GET['order_by'] ?? $page_config['content']['order_by'] ?? 'date',
                        orderDir: $params['order_dir'] = $_GET['order_dir'] ?? $page_config['content']['order_dir'] ?? 'desc',
                    ));

                    $pagination = array_merge($pagination, $markdown->getPagination(
                        baseUrl: '/' . trim($page_config['route'], '/'),
                        currentPage: $pages['current_page'],
                        lastPage: $pages['last_page'],
                        extraParams: array_merge($filters, [
                            'count'     => $_GET['count'] ?? null,
                            'q'         => $_GET['q'] ?? null,
                            'order_by'  => $_GET['order_by'] ?? null,
                            'order_dir' => $_GET['order_dir'] ?? null,
                        ]),
                    ));
                }
            }

            $template = $hasListItemTemplate
                ? ($page_config['template'][$mode] ?? $page_config['template']['list'])
                : $page_config['template'];

            require $template;
            exit;
        }
    }
}

/**
 * Custom pages. Each template is included without `exit`, so a template must
 * call `exit` itself; otherwise the 404 page below is rendered as well.
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
        $markdown = new Markdown($content_file);
        $page = array_merge(
            $page,
            $markdown->getPage('/', $content_file)
        );
        $is['markdown'] = true;
    }
}
require ERROR_PAGE['template'];
exit;
