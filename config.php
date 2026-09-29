<?php

// ============================================================================
// State configuration
// ============================================================================

const STATE = [
    'debug' => true,
    'title' => 'Site Title',
    'zone'  => 'Asia/Jakarta',
];

// ============================================================================
// Route and template configuration
// ============================================================================

const HOME_PAGE   = [
    'title'    => 'Home',
    'template' => __DIR__ . '/app/home.html.php',
];

const ERROR_PAGE  = [
    'title'    => 'Page Not Found',
    'template' => __DIR__ . '/app/404.html.php',
];

const STATIC_PAGES = [
    [
        'title'    => 'About',
        'route'    => '/about',
        'template' => __DIR__ . '/app/about/about.html.php',
        // Markdown `content` is optional
        'content'  => __DIR__ . '/app/about/about.md',
    ],
    [
        'title'    => 'About Segment 1',
        'route'    => '/about/[foo]',
        'template' => __DIR__ . '/app/about/about-[foo].html.php',
    ],
    [
        'title'    => 'About Segment 2',
        'route'    => '/about/[foo]/[bar]',
        'template' => __DIR__ . '/app/about/about-[foo]-[bar].html.php',
    ],
];

const DYNAMIC_PAGES = [
    '/article' => [
        'title'    => 'Article',
        // Markdown `content` is optional
        'content'  => [
            'dir'       => __DIR__ . '/app/article/content',
            'per_page'  => 5,
            'order_by'  => 'title',
            'order_dir' => 'asc',
        ],
        'template' => [
            'list' => __DIR__ . '/app/article/list.html.php',
            'item' => __DIR__ . '/app/article/item.html.php',
        ],
    ],
];

// const CUSTOM_PAGES = [
//     [
//         'name'     => 'custom_page_1',
//         'template' => __DIR__ . '/path/to/file.php',
//     ],
// ];
