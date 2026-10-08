<?php

require_once __DIR__ . '/vendor/taufik-nurrohman/markdown/from.php';
require_once __DIR__ . '/vendor/taufik-nurrohman/y-a-m-l/from.php';
require_once __DIR__ . '/vendor/taufik-nurrohman/y-a-m-l/to.php';
require_once __DIR__ . '/renderer.php';

/**
 * Reads, lists, and writes the Markdown pages of one content directory.
 */
class Markdown {
    private string $contentDir;
    private TemplateRenderer $renderer;

    /**
     * `$contentDir` can be a route from `PAGES` (its `content.dir` is used),
     * a directory, or a file (its folder is used).
     */
    public function __construct(string $contentDir, ?TemplateRenderer $renderer = null) {
        $page_config = resolve_page_config($contentDir);

        if ($page_config !== null && !empty($page_config['content']['dir'])) {
            $contentDir = $page_config['content']['dir'];
        }
        if (is_file($contentDir)) {
            $contentDir = dirname($contentDir);
        }

        $this->contentDir = rtrim($contentDir, '/');
        $this->renderer = $renderer ?? get_template_renderer();
    }

    /**
     * Strips the directory and the `.md` extension.
     */
    private function filenameToSlug(string $filename): string {
        return preg_replace('/\.md$/', '', basename($filename));
    }

    private function getCacheFile(): string {
        return $this->contentDir . '/.cache.php';
    }

    /**
     * Returns the newest mtime of `$files`. Only a `stat()` is needed, which is
     * much cheaper than parsing every file's YAML to detect changes.
     */
    private function getNewestMtime(array $files): int {
        $newest = 0;
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime > $newest) {
                $newest = $mtime;
            }
        }
        return $newest;
    }

    /**
     * Splits the YAML frontmatter from the Markdown body.
     * Returns `[$meta, $body]`; `$meta` is `[]` when there is no frontmatter.
     */
    public function parseFrontmatter(string $raw): array {
        $meta = [];
        $body = $raw;

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $raw, $matches)) {
            $body = $matches[2];
            $parsed = x\y_a_m_l\from($matches[1], true);
            $meta = is_array($parsed) ? $parsed : [];
        }

        return [$meta, $body];
    }

    private function encodeFrontmatter(array $meta, string $tab = '  '): string {
        $lines = [];

        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                if ($value === []) continue;
                $lines[] = $key . ':';
                foreach ($value as $item) {
                    $lines[] = $tab . '- ' . x\y_a_m_l\to\v($item, $tab, 1);
                }
                continue;
            }

            $lines[] = $key . ': ' . x\y_a_m_l\to\v($value, $tab, 1);
        }

        return implode("\n", $lines);
    }

    public function getRenderer(): TemplateRenderer {
        return $this->renderer;
    }

    /**
     * Returns the metadata of every page in the directory, newest first.
     * The result is cached in `.cache.php` inside the directory.
     */
    public function getAllPagesMeta(string $route): array {
        $files       = glob($this->contentDir . '/*.md');
        $cacheFile   = $this->getCacheFile();
        $newestMtime = $this->getNewestMtime($files);

        $page_config = resolve_page_config($route);

        if ($page_config === null || empty($page_config['content']['dir'])) {
            // `$route` can also be a directory path, which has no `PAGES` entry
            if (is_dir($route)) {
                $page_config = [
                    'title' => null,
                ];
            }
        }

        // The cache is valid while no file is newer than it and the file count
        // matches (a deletion doesn't change the mtime of any other file)
        if (is_file($cacheFile)) {
            $cached = include $cacheFile;
            if (
                is_array($cached)
                && ($cached['newest_mtime'] ?? 0) >= $newestMtime
                && ($cached['file_count'] ?? -1) === count($files)
            ) {
                return $cached;
            }
        }

        $pages = [];
        foreach ($files as $file) {
            [$meta] = $this->parseFrontmatter(file_get_contents($file));
            $slug = $this->filenameToSlug($file);

            // Frontmatter fields (`category`, `tags`, ...) are flattened to the
            // top level. `route`, `slug`, `title`, `date` and `_file` are merged
            // last so the frontmatter can't override them.
            $pages[] = array_merge($meta, [
                'route' => (trim($route, '/') !== '' ? '/' : '') . trim($route, '/') . '/' . $slug,
                'slug'  => $slug,
                'title' => $meta['title'] ?? $slug,
                'date'  => $meta['date'] ?? null,
                '_file' => $file,
            ]);
        }

        usort($pages, function ($a, $b) {
            $a = $a['date'] ?? '1970-01-01';
            $b = $b['date'] ?? '1970-01-01';
            if ($a instanceof DateTimeInterface) {
                $a = $a->getTimestamp();
            } else if (is_string($a)) {
                $a = strtotime($a);
            }
            if ($b instanceof DateTimeInterface) {
                $b = $b->getTimestamp();
            } else if (is_string($b)) {
                $b = strtotime($b);
            }
            return $b <=> $a;
        });

        $title = $page_config['title'] ?? null;

        $data = [
            'route'         => '/' . trim($route, '/'),
            'title'         => $title,
            'newest_mtime'  => $newestMtime,
            'file_count'    => count($files),
            'pages'         => $pages,
            '_content_path' => $this->contentDir,
            '_cache_file'   => $cacheFile,
        ];

        if (is_dir($this->contentDir)) {
            file_put_contents(
                $cacheFile,
                '<?php return ' . var_export($data, true) . ';',
                LOCK_EX
            );
        }

        return $data;
    }

    /**
     * Filters `$pages` by frontmatter fields; `$filters` is `field => expected`.
     * - a list field (e.g. `tags`) matches if any item equals an expected value
     * - a scalar field (e.g. `category`) matches if it equals an expected value
     * - an array `$expected` matches if any of its values matches (OR)
     * - several fields must all match (AND)
     * - `null`, `''` and `[]` filters are ignored
     */
    public function filter_pages_by_fields(array $pages, array $filters): array {
        // URL values are strings, while YAML values can be `int` or `bool`
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
     * Returns a page with its Markdown rendered to HTML, or `null` if the file
     * doesn't exist. Placeholders such as `{{ home_url }}` in the body are
     * resolved before the Markdown is parsed.
     */
    public function getPage(string $route, string $slug): ?array {
        $slug = $this->filenameToSlug($slug);
        $path = $this->contentDir . '/' . $slug . '.md';

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        [$meta, $body] = $this->parseFrontmatter($raw);

        $body = $this->renderer->render($body);

        return array_merge($meta, [
            'route'   => (trim($route, '/') !== '' ? '/' : '') . trim($route, '/') . '/' . $slug,
            'slug'    => $slug,
            'title'   => $meta['title'] ?? $slug,
            'date'    => $meta['date'] ?? null,
            'content' => x\markdown\from($body),
            '_file'   => $path,
        ]);
    }

    /**
     * Returns one page of the filtered, searched and sorted list, with paging info.
     * When `$search` is set, results are ranked by relevance first, then by `$orderBy`.
     *
     * @param string  $orderBy  Field to sort by: `title`, `date`, `slug`, or any frontmatter field.
     * @param ?string $orderDir `asc` or `desc`. Defaults to `desc` for `date`, otherwise `asc`.
     */
    public function getPages(
        string $route,
        ?int $page = null,
        int $count = 10,
        ?string $search = null,
        array $filters = [],
        string $orderBy = 'title',
        ?string $orderDir = null,
    ): array {
        $allPagesMeta = $this->getAllPagesMeta($route);

        $allPages = $this->filter_pages_by_fields($allPagesMeta['pages'], $filters);

        // Keep the pages that match `$search`, scored by title and body
        if ($search !== null && $search !== '') {
            $searchWords = preg_split('/\s+/', trim($search));

            // A title match is worth more than a body match
            $titleWeight = 10;
            $bodyWeight  = 1;

            $allPages = array_values(array_filter(array_map(
                function ($p) use ($searchWords, $titleWeight, $bodyWeight) {
                    $title = $p['title'] ?? '';
                    $score = 0;

                    // Each search word counts once per title
                    foreach ($searchWords as $word) {
                        if (stripos($title, $word) !== false) {
                            $score += $titleWeight;
                        }
                    }

                    // Each search word counts once across the whole body, so
                    // repeating one word doesn't raise the score
                    $matchedInBody = array_fill_keys($searchWords, false);

                    if (!empty($p['_file']) && ($stream = fopen($p['_file'], 'r'))) {
                        $separatorCount = 0;

                        while (($line = fgets($stream)) !== false) {
                            if (trim($line) === '---') {
                                $separatorCount++;
                                continue;
                            }

                            // Only the body is searched: after the second `---`
                            if ($separatorCount >= 2) {
                                foreach ($matchedInBody as $word => $found) {
                                    if (!$found && stripos($line, $word) !== false) {
                                        $matchedInBody[$word] = true;
                                    }
                                }

                                // Stop early once every word is found
                                if (!in_array(false, $matchedInBody, true)) {
                                    break;
                                }
                            }
                        }

                        fclose($stream);
                    }

                    $score += count(array_filter($matchedInBody)) * $bodyWeight;

                    $p['_searchScore'] = $score;
                    return $p;
                },
                $allPages
            ), fn($p) => $p['_searchScore'] > 0));
        }

        $orderDir = $orderDir ?? ($orderBy === 'date' ? 'desc' : 'asc');

        $getSortValue = fn($p) => $p[$orderBy] ?? null;

        $toTimestamp = static function ($value): int {
            if ($value instanceof DateTimeInterface) {
                return $value->getTimestamp();
            }

            if ($value === null || $value === '') {
                return 0;
            }

            $timestamp = strtotime((string) $value);

            return $timestamp !== false ? $timestamp : 0;
        };

        usort($allPages, function ($a, $b) use ($getSortValue, $orderBy, $orderDir, $search, $toTimestamp) {
            // Searching: highest score first
            if ($search !== null && $search !== '') {
                $scoreCmp = ($b['_searchScore'] ?? 0) <=> ($a['_searchScore'] ?? 0);
                if ($scoreCmp !== 0) {
                    return $scoreCmp;
                }
            }

            // Same score, or no search: use the requested order
            $va = $getSortValue($a);
            $vb = $getSortValue($b);

            if ($orderBy === 'date') {
                $cmp = $toTimestamp($va) <=> $toTimestamp($vb);
            } elseif (is_numeric($va) && is_numeric($vb)) {
                $cmp = $va <=> $vb;
            } else {
                $cmp = strcasecmp((string) $va, (string) $vb);
            }

            return $orderDir === 'desc' ? -$cmp : $cmp;
        });

        $total      = count($allPages);
        $totalPages = max(1, (int) ceil($total / $count));

        // `$page` is clamped to the valid range
        $page   = $page === null ? 1 : max(1, min($page, $totalPages));
        $offset = ($page - 1) * $count;

        return [
            'route'         => $allPagesMeta['route'],
            'title'         => $allPagesMeta['title'],
            'count'         => $count,
            'total'         => $total,
            'current_page'  => $page,
            'last_page'     => $totalPages,
            'newest_mtime'  => $allPagesMeta['newest_mtime'],
            'file_count'    => $allPagesMeta['file_count'],
            'pages'         => array_slice($allPages, $offset, $count),
            '_content_path' => $allPagesMeta['_content_path'],
            '_cache_file'   => $allPagesMeta['_cache_file'],
        ];
    }

    /**
     * Collects every distinct value of each field in `$fields` across the pages,
     * e.g. for a category or tag filter menu. Each field gets the `selected`
     * values from `$_GET` and a `state` list of `title`, `route` and `active`.
     * `$params` maps a field to its URL param name when it differs.
     */
    public function getPagesFields(string $route, array $fields = [], array $params = []): array {
        if (empty($fields)) {
            return [];
        }

        $allPagesMeta = $this->getAllPagesMeta($route);
        $pages = $allPagesMeta['pages'];

        if (empty($pages)) {
            return [];
        }

        $base = '/' . trim($route, '/');
        $result = [];

        foreach ($fields as $field) {
            // URL param name; defaults to the field name
            $param = $params[$field] ?? $field;

            // Distinct values as array keys; the `(array)` cast covers both
            // scalar and list fields
            $values = [];
            foreach ($pages as $page) {
                foreach ((array) ($page[$field] ?? []) as $value) {
                    if (is_scalar($value) && $value !== '') {
                        $values[(string) $value] = true;
                    }
                }
            }

            // `array_keys()` turns numeric-looking keys into `int`s, so cast them back
            $titles = array_map('strval', array_keys($values));
            sort($titles, SORT_STRING | SORT_FLAG_CASE);

            // Selected values from `$_GET`, always an array whether the URL has
            // one value (`?category=news`) or several (`?tags[]=php&tags[]=js`)
            $selected = array_values(array_filter(
                array_map('strval', (array) ($_GET[$param] ?? [])),
                fn($v) => $v !== ''
            ));

            $result[$field] = [
                'selected' => $selected,
                'state'    => array_map(
                    fn($title) => [
                        'title'  => $title,
                        'route'  => $base . '?' . http_build_query([$param => $title]),
                        'active' => in_array($title, $selected, true),
                    ],
                    $titles
                ),
            ];
        }

        return $result;
    }

    /**
     * Builds the links of a pager: `prev`, `next`, and `pages` with the first
     * and last page, the current page and `$window` pages on each side of it.
     * A skipped range is a single `...` entry. `$extraParams` are kept in every
     * link, except `null` and `''` values.
     */
    public function getPagination(int $currentPage, int $lastPage, string $baseUrl = '', array $extraParams = [], int $window = 2): array {
        $baseUrl = '/' . trim($baseUrl, '/');

        $urlFor = function (int $p) use ($baseUrl, $extraParams) {
            $params = array_filter(array_merge($extraParams, ['page' => $p]), fn($v) => $v !== null && $v !== '');
            return $baseUrl . '?' . http_build_query($params);
        };

        $pages = [];
        $lastAdded = 0;

        for ($i = 1; $i <= $lastPage; $i++) {
            $isEdge   = $i === 1 || $i === $lastPage;
            $isNearBy = $i >= $currentPage - $window && $i <= $currentPage + $window;

            if ($isEdge || $isNearBy) {
                $pages[] = ['page' => $i, 'route' => $urlFor($i), 'active' => $i === $currentPage];
                $lastAdded = $i;
            } elseif ($lastAdded !== -1 && $i - $lastAdded > 1) {
                $pages[] = ['page' => '...', 'route' => null, 'active' => false];
                // `-1`: this gap already has its `...`
                $lastAdded = -1;
            }
        }

        return [
            'prev'         => $currentPage > 1 ? $urlFor($currentPage - 1) : null,
            'next'         => $currentPage < $lastPage ? $urlFor($currentPage + 1) : null,
            'current_page' => $currentPage,
            'last_page'    => $lastPage,
            'pages'        => $pages,
        ];
    }

    /**
     * Returns a page's raw frontmatter and Markdown body, without rendering to
     * HTML or resolving `{{ ... }}` placeholders. Use it to fill an edit form,
     * and `getPage()` for display. Returns `null` if the file doesn't exist.
     */
    public function getRawPage(string $route, string $slug): ?array {
        $slug = $this->filenameToSlug($slug);
        $path = $this->contentDir . '/' . $slug . '.md';

        if (!is_file($path)) {
            return null;
        }

        [$meta, $body] = $this->parseFrontmatter(file_get_contents($path));

        return array_merge($meta, [
            'route' => (trim($route, '/') !== '' ? '/' : '') . trim($route, '/') . '/' . $slug,
            'slug'  => $slug,
            'body'  => trim($body),
            '_file' => $path,
        ]);
    }

    /**
     * Creates or overwrites `$slug`.md with the YAML frontmatter `$meta` and the
     * raw `$body`. `null`, `''` and `[]` values are dropped from `$meta`.
     *
     * `$slug` is sanitized again here, so the method is safe to call directly.
     * Returns `['route', 'slug']`, or `null` if the slug ends up empty or the
     * file can't be written.
     */
    public function writePage(string $route, string $slug, array $meta, string $body = ''): ?array {
        $slug = $this->filenameToSlug($slug);
        $slug = sanitize_fields(['slug' => $slug], ['slug' => ['trim', 'lowercase', 'slug']])['slug'];

        if ($slug === null || $slug === '') {
            return null;
        }

        if (!is_dir($this->contentDir) && !mkdir($this->contentDir, 0755, true)) {
            return null;
        }

        $path = $this->contentDir . '/' . $slug . '.md';

        $meta = array_filter($meta, fn($v) => $v !== null && $v !== '' && $v !== []);
        $frontmatter = $meta === [] ? '' : $this->encodeFrontmatter($meta);

        $content = $frontmatter !== '' ? "---\n{$frontmatter}\n---\n\n{$body}\n" : $body . "\n";

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            return null;
        }

        return [
            'route' => (trim($route, '/') !== '' ? '/' : '') . trim($route, '/') . '/' . $slug,
            'slug'  => $slug,
        ];
    }

    /**
     * Deletes `$slug`.md. A missing file counts as success; returns `false`
     * only if `unlink()` fails.
     */
    public function deletePage(string $slug): bool {
        $slug = $this->filenameToSlug($slug);
        $path = $this->contentDir . '/' . $slug . '.md';

        if (!is_file($path)) {
            return true;
        }

        return unlink($path);
    }
}
