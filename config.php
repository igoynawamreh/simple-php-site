<?php

// ============================================================================
// State configuration
// ============================================================================

const STATE = [
    'debug' => false,
    // Used to decide between a frontend framework's dev server (e.g. Vite)
    // and its production build — has no effect if none is in use.
    // 'development', 'production', 'staging'
    'env'   => 'production',
    'title' => 'Site Title',
    'zone'  => '',
];

// ============================================================================
// Route and template configuration
// ============================================================================

const HOME_PAGE   = [
    'title'    => 'Home',
    'template' => __DIR__ . '/site/home.html.php',
];

const ERROR_PAGE  = [
    'title'    => 'Page Not Found',
    'template' => __DIR__ . '/site/404.html.php',
];

const PAGES = [
    [
        'title'    => 'About',
        'route'    => '/about',
        'template' => __DIR__ . '/site/about/about.html.php',
        // Single file markdown (optional)
        'content'  => __DIR__ . '/site/about/about.md',
    ],
    [
        'title'    => 'About Segment 1',
        'route'    => '/about/[foo]',
        'template' => __DIR__ . '/site/about/about-[foo].html.php',
    ],
    [
        'title'    => 'About Segment 2',
        'route'    => '/about/[foo]/[bar]',
        'template' => __DIR__ . '/site/about/about-[foo]-[bar].html.php',
    ],
    [
        'title'    => 'FAQ',
        'route'    => '/faq',
        'template' => __DIR__ . '/site/faq/faq.html.php',
        'content'  => __DIR__ . '/site/faq/faq.md',
    ],
    [
        // Example auto list/item routing and directory-based markdown
        'title'    => 'Blog',
        'route'    => '/blog',
        // `template` as { list, item } (instead of a single file) makes this
        // one entry automatically cover two routes: `/blog` (list) and
        // `/blog/[slug]` (item)
        // the `[slug]` segment is appended by the router automatically.
        'template' => [
            'list' => __DIR__ . '/site/blog/list.html.php',
            'item' => __DIR__ . '/site/blog/item.html.php',
        ],
        // `content.dir` reads markdown files from this directory for both
        // routes above.
        'content'  => [
            'dir'       => __DIR__ . '/site/blog/content',
            'count'     => 5,
            'order_by'  => 'date',
            'order_dir' => 'desc',
            // Allowed filters
            'filter'    => ['category', 'tags'],
        ],
    ],
    [
        'title'    => 'Panel',
        'route'    => '/panel',
        'template' => __DIR__ . '/panel/panel.html.php',
    ],
    [
        'title'    => 'Panel',
        'route'    => '/panel/[...rest]',
        'template' => __DIR__ . '/panel/panel.html.php',
    ],
];

// const CUSTOM_PAGES = [
//     [
//         'name'     => 'custom_page_1',
//         'template' => __DIR__ . '/path/to/file.php',
//     ],
// ];
