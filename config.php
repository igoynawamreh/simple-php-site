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
        'url'      => '/about',
        'template' => __DIR__ . '/app/about/about.html.php',
        'content'  => __DIR__ . '/app/about/about.md', // `content` is optional
    ],
];

const DYNAMIC_PAGES = [
    '/article' => [
        'title'    => 'Article',
        'content' => [ // `content` is optional
            'dir'       => __DIR__ . '/app/article/content',
            'per_page'  => 5,
            'order_by'  => 'title',
            'order_dir' => 'asc',
        ],
        'view' => [
            'list'   => __DIR__ . '/app/article/view/list.html.php',
            'item'   => __DIR__ . '/app/article/view/item.html.php',
        ],
    ],
];

// const CUSTOM_PAGES = [
//     [
//         'name'     => 'custom_page_1',
//         'template' => __DIR__ . '/path/to/file.php',
//     ],
// ];
