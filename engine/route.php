<?php

$site  = [];
$page  = [];
$pages = [];
$pagination = [];
$is = [];

$site['title'] = STATE['title'] ?? null;

$page['route']   = $route;
$page['url']     = '/' . trim($route, '/');
$page['slug']    = basename($route) === '' ? null : basename($route);
$page['title']   = $site['title']; // Default to site title
$page['content'] = null; // Page with markdown content only

$is['home']          = false;
$is['static']        = false;
$is['dynamic']       = false;
$is['dynamic_list']  = false;
$is['dynamic_item']  = false;
$is['custom']        = false;
$is['404']           = false;
$is['markdown']      = false;

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
    $STATIC_PAGES_INDEXED = array_column(
        array_map(function ($page) {
            $page['url'] = trim($page['url'], '/');
            return $page;
        }, STATIC_PAGES),
        null,
        'url'
    );
    if (array_key_exists($route, $STATIC_PAGES_INDEXED)) {
        $page['title'] = $STATIC_PAGES_INDEXED[$route]['title'] ?? $page['title'];
        $is['static'] = true;
        if (isset($STATIC_PAGES_INDEXED[$route]['content'])) {
            $content_file = $STATIC_PAGES_INDEXED[$route]['content'];
            if (is_file($content_file)) {
                $render_md = render_md_from_file($content_file);
                $page = array_merge($page, $render_md);
                $is['markdown'] = true;
            }
        }
        require $STATIC_PAGES_INDEXED[$route]['template'];
        exit;
    }
}

/**
 * Dynamic pages
 *   /{prefix}/       → list or index
 *   /{prefix}/{slug} → read/view page
 */
if (defined('DYNAMIC_PAGES') && !empty(DYNAMIC_PAGES)) {
    foreach (DYNAMIC_PAGES as $prefix => $dynamic_page) {
        $views       = $dynamic_page['view'] ?? [];
        $prefix_trim = trim($prefix, '/');
        $markdown    = null;

        $isMarkdown = isset($dynamic_page['content'])
            && $dynamic_page['content'] !== false
            && isset($dynamic_page['content']['dir'])
            && $dynamic_page['content']['dir'] !== false;

        $page['index_url']   = '/' . $prefix_trim;
        $page['title']       = $dynamic_page['title'] ?? $page['title'];
        $page['index_title'] = $page['title'];
        $is['dynamic'] = true;

        if ($route !== $prefix_trim && !str_starts_with($route, $prefix_trim . '/')) {
            continue;
        }

        if ($isMarkdown) {
            require_once __DIR__ . '/lib/Markdown.php';
            $markdown = new Markdown($dynamic_page['content']['dir']);
            $markdown->getRenderer()->setVar('site_title', STATE['title'] ?? 'My Site');
            $markdown->getRenderer()->setVar('home_url', $home_url);
            $markdown->getRenderer()->setFunction('url', fn($path) => url($path));
            $markdown->getRenderer()->setFunction('img', function ($path, $alt = '', $class = '') {
                $src = url($path);
                $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';
                return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"' . $classAttr . '>';
            });
            $is['markdown'] = true;
        }

        // /{prefix}/ — list
        if ($route === $prefix_trim) {
            if (empty($views['list'])) {
                break;
            }
            $is['dynamic_list'] = true;
            if ($isMarkdown) {
                $page['param_page']      = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
                $page['param_category']  = $_GET['category'] ?? null;
                $page['param_tag']       = $_GET['tag'] ?? null;
                $page['param_per_page']  = $_GET['per_page'] ?? $dynamic_page['content']['per_page'] ?? 10;
                $page['param_order_by']  = $_GET['order_by'] ?? $dynamic_page['content']['order_by'] ?? 'title';
                $page['param_order_dir'] = $_GET['order_dir'] ?? $dynamic_page['content']['order_dir'] ?? 'asc';

                $pages = array_merge($pages, $markdown->getPages(
                    page: $page['param_page'],
                    prefix: $prefix,
                    perPage: $page['param_per_page'],
                    category: $page['param_category'],
                    tag: $page['param_tag'],
                    orderBy: $page['param_order_by'],
                    orderDir: $page['param_order_dir'],
                ));
                $pagination = array_merge($pagination, renderPaginationLinks(
                    $pages['currentPage'],
                    $pages['totalPages'],
                    baseUrl: '/' . $prefix_trim,
                    extraParams: [
                        'category' => $page['param_category'],
                        'tag'      => $page['param_tag'],
                    ],
                ));
            }
            require $views['list'];
            exit;
        }

        $pid = substr($route, strlen($prefix_trim) + 1);
        if ($pid === '' || str_contains($pid, '/')) {
            break;
        }

        // /{prefix}/{slug} — read
        if (empty($views['item'])) {
            break;
        }

        if ($isMarkdown) {
            $found = $markdown->getPage($pid);
            if (!$found) {
                break;
            }
            $page = array_merge($page, $found);
        }

        $is['dynamic_item'] = true;

        require $views['item'];
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
