<?php

$state = [];
$site  = [];
$page  = [];
$pages = [];
$pagination = [];
$is = [];

$state['debug'] = STATE['debug'] ?? false;
$state['env']   = STATE['env'] ?? 'development';
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
            $render_md = render_md_from_file($content_file);
            $page = array_merge($page, $render_md);
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
        // Auto list/item routing is driven by the shape of `template`: a route
        // gets both a list pattern (the route as-is) and an item pattern
        // (route + `/[slug]`) whenever `template` is { list, item } instead of
        // a single file path.
        $hasListItemTemplate = is_array($page_config['template'] ?? null)
            && isset($page_config['template']['list'], $page_config['template']['item']);

        // Whether markdown data actually comes from a directory. Independent
        // of `$hasListItemTemplate` — a list/item template pair could, in
        // principle, source its data from somewhere other than markdown.
        $hasContentDir = isset($page_config['content']['dir']);

        $candidates = $hasListItemTemplate
            ? [
                'list' => $page_config['route'],
                'item' => rtrim($page_config['route'], '/') . '/[slug]',
            ]
            : ['plain' => $page_config['route']];

        foreach ($candidates as $mode => $routePattern) {
            $pattern = trim($routePattern ?? '', '/');

            // Build a regex from the URL pattern:
            // - literal segments are matched as-is
            // - [name] captures exactly one segment (no slashes)
            // - [...name] is a catch-all: it must be the LAST segment, and
            //   captures everything after it, slashes included (e.g. for a
            //   client-side router / SPA shell mounted under this prefix).
            $regexParts = [];
            $segments   = explode('/', $pattern);

            foreach ($segments as $i => $segment) {
                if (preg_match('/^\[\.\.\.([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $segment, $m)) {
                    // Only meaningful as the final segment; anything after
                    // it in the pattern would be unreachable.
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

            // Single file Markdown
            if (isset($page_config['content']) && !is_array($page_config['content'])) {
                $content_file = $page_config['content'];
                if (is_file($content_file)) {
                    $render_md = render_md_from_file($content_file);
                    $page = array_merge($page, $render_md);
                    $is['markdown'] = true;
                }
            }

            // Directory-based Markdown — populates `$page`/`$pages` when this
            // route's data actually comes from a markdown directory.
            if ($hasContentDir) {
                $dir      = rtrim($page_config['content']['dir'], '/');
                $markdown = new Markdown($dir);

                $page['route:list'] = '/' . $page_config['route'];
                $page['title:list'] = $page['title'];

                if ($mode === 'item') {
                    $slug = $page['route:slug'] ?? '';
                    $item = $markdown->getPage($page_config['route'], $slug);

                    // No matching file for this slug,
                    // fall through to the next PAGES entry / 404.
                    if ($item === null) {
                        continue 2;
                    }

                    $page = array_merge($page, $item);
                } elseif ($mode === 'list') {
                    // Fields that may be used as filter (per page config, with a default)
                    $allowedFilters = $page_config['content']['filter'] ?? ['category', 'tags'];

                    // Only whitelisted fields from the URL become filters
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

            // Resolve which template file to require
            $template = $hasListItemTemplate
                ? ($page_config['template'][$mode] ?? $page_config['template']['list'])
                : $page_config['template'];

            require $template;
            exit;
        }
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
