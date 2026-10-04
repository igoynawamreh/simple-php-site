<?php

require_once __DIR__ . '/vendor/taufik-nurrohman/markdown/from.php';
require_once __DIR__ . '/vendor/taufik-nurrohman/y-a-m-l/from.php';
require_once __DIR__ . '/vendor/taufik-nurrohman/y-a-m-l/to.php';
require_once __DIR__ . '/renderer.php';

class Markdown {
    private string $contentDir;
    private TemplateRenderer $renderer;

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

    private function filenameToSlug(string $filename): string {
        return preg_replace('/\.md$/', '', basename($filename));
    }

    private function getCacheFile(): string {
        return $this->contentDir . '/.cache.php';
    }

    /**
     * Find the newest mtime among all content files. This only stat()s each
     * file (cheap, no file content read) — much cheaper than parsing YAML for
     * every file just to check whether anything changed.
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
     * Split frontmatter (real YAML, parsed via @taufik-nurrohman/y-a-m-l) from the markdown body.
     * Supports nested values, lists, quoted strings, etc. — not just flat key: value.
     * Returns [meta_array, body_string]
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

    public function getAllPagesMeta(string $route): array {
        $files       = glob($this->contentDir . '/*.md');
        $cacheFile   = $this->getCacheFile();
        $newestMtime = $this->getNewestMtime($files);

        $page_config = resolve_page_config($route);

        if ($page_config === null || empty($page_config['content']['dir'])) {
            if (is_dir($route)) {
                $page_config = [
                    'title' => null,
                ];
            }
        }

        // Cache is valid if: it exists, no file is newer than when it was built,
        // and the file count still matches (catches deletions, which don't
        // change any existing file's mtime).
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

        // Cache miss / stale — do the real work
        $pages = [];
        foreach ($files as $file) {
            [$meta] = $this->parseFrontmatter(file_get_contents($file));
            $slug = $this->filenameToSlug($file);

            // Flatten frontmatter fields to the top level (category, tags, etc.
            // come straight from $meta), but always force route/slug/title/date
            // to the computed values — merge them LAST so they win even if the
            // frontmatter accidentally defines a field with the same name.
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
            'title'         => $title,
            'route'         => '/' . trim($route, '/'),
            'newest_mtime'  => $newestMtime,
            'file_count'    => count($files),
            '_content_path' => $this->contentDir,
            '_cache_file'   => $cacheFile,
            'pages'         => $pages,
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
     * Filter a list of page metadata by arbitrary fields.
     * - array field (e.g. "tags")      -> matches if any item equals the expected value
     * - scalar field (e.g. "category") -> matches if the value equals the expected value
     * - `$expected` is an array        -> matches if any of the values equals (OR)
     * - multiple fields at once        -> all of them must match (AND)
     * - null / '' / [] filters are ignored
     */
    public function filter_pages_by_fields(array $pages, array $filters): array {
        // URL values are always strings, while YAML values can be int/bool
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
     * Get a single page (frontmatter + markdown parsed to HTML).
     * Template placeholders like {{ site_url }} and {{ url('...') }} inside
     * the body are resolved BEFORE the body is parsed by @taufik-nurrohman/markdown.
     * Returns null if the file doesn't exist.
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
            'content' => x\markdown\from($body), // markdown -> HTML
            '_file'   => $path,
        ]);
    }

    /**
     * @param string  $orderBy  Field to sort by — 'title', 'date', 'slug', or
     *                          any custom frontmatter field (e.g. 'author').
     * @param ?string $orderDir 'asc' or 'desc'. Defaults to 'desc' when
     *                          $orderBy is 'date', otherwise 'asc'.
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

        // Filter by any metadata field
        $allPages = $this->filter_pages_by_fields($allPagesMeta['pages'], $filters);

        // Filter + scoring by title and body
        if ($search !== null && $search !== '') {
            $searchWords = preg_split('/\s+/', trim($search));

            // Title matches are weighted higher than body matches, so a keyword
            // in the title always outranks the same keyword only appearing in the body.
            $titleWeight = 10;
            $bodyWeight  = 1;

            $allPages = array_values(array_filter(array_map(
                function ($p) use ($searchWords, $titleWeight, $bodyWeight) {
                    $title = $p['title'] ?? '';
                    $score = 0;

                    // Title: each search word found counts once, regardless of
                    // how many times it repeats in the title.
                    foreach ($searchWords as $word) {
                        if (stripos($title, $word) !== false) {
                            $score += $titleWeight;
                        }
                    }

                    // Body: each search word found counts once across the whole
                    // body, so a long article repeating one word doesn't outweigh
                    // pages that match more distinct words.
                    $matchedInBody = array_fill_keys($searchWords, false);

                    if (!empty($p['_file']) && ($stream = fopen($p['_file'], 'r'))) {
                        $separatorCount = 0;

                        while (($line = fgets($stream)) !== false) {
                            if (trim($line) === '---') {
                                $separatorCount++;
                                continue;
                            }

                            // Start the search after the second `---`
                            if ($separatorCount >= 2) {
                                foreach ($matchedInBody as $word => $found) {
                                    if (!$found && stripos($line, $word) !== false) {
                                        $matchedInBody[$word] = true;
                                    }
                                }

                                // Stop reading early once every word has been found
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

        // Sort by the requested field
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
            // When searching, the highest score comes first
            if ($search !== null && $search !== '') {
                $scoreCmp = ($b['_searchScore'] ?? 0) <=> ($a['_searchScore'] ?? 0);
                if ($scoreCmp !== 0) {
                    return $scoreCmp;
                }
            }

            // Same score (or no search) -> fall back to the requested order
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

        // Pagination
        $total      = count($allPages);
        $totalPages = max(1, (int) ceil($total / $count));

        $page   = $page === null ? 1 : max(1, min($page, $totalPages));
        $offset = ($page - 1) * $count;

        return [
            'pages'         => array_slice($allPages, $offset, $count),
            'total'         => $total,
            'count'         => $count,
            'current_page'  => $page,
            'last_page'     => $totalPages,
            'title'         => $allPagesMeta['title'],
            'route'         => $allPagesMeta['route'],
            'newest_mtime'  => $allPagesMeta['newest_mtime'],
            'file_count'    => $allPagesMeta['file_count'],
            '_content_path' => $allPagesMeta['_content_path'],
            '_cache_file'   => $allPagesMeta['_cache_file'],
        ];
    }

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
            // URL parameter name for this field; defaults to the field name itself.
            $param = $params[$field] ?? $field;

            // Collect unique values: use the value as array key to dedupe cheaply.
            // The (array) cast handles both scalar fields and list fields.
            $values = [];
            foreach ($pages as $page) {
                foreach ((array) ($page[$field] ?? []) as $value) {
                    if (is_scalar($value) && $value !== '') {
                        $values[(string) $value] = true;
                    }
                }
            }

            // array_keys() turns numeric-looking keys into ints, so cast back to string.
            $titles = array_map('strval', array_keys($values));
            sort($titles, SORT_STRING | SORT_FLAG_CASE);

            // Normalized list of currently selected values from the URL.
            // Always an array, whether the URL sent one value ("?category=news") or
            // several ("?tags[]=php&tags[]=js"), which keeps it predictable for callers.
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
     * Get a single page's RAW frontmatter + body — no markdown-to-HTML
     * rendering and no `{{ ... }}` placeholder resolution. Use this for
     * edit forms (a CMS/admin panel), where the raw markdown source is
     * what should populate a textarea — `getPage()` renders to HTML and
     * is NOT suitable for that. Returns null if the file doesn't exist.
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
     * Create or overwrite `$slug`.md with YAML frontmatter (`$meta`) +
     * raw `$body`, encoded via `x\y_a_m_l\to()`.
     *
     * Null, empty-string, and empty-array values are dropped before
     * encoding, so unset/empty fields don't clutter the frontmatter
     * with `field: ~` or `tags: []`.
     *
     * `$slug` is re-sanitized here (lowercase + slug rule) regardless of
     * what the caller already did, so this method is safe to call
     * directly. Returns the slug actually used, or null if the slug is
     * empty after sanitizing, or the file couldn't be written.
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
     * Delete `$slug`.md. Deleting is idempotent — a missing file counts
     * as success. Returns false only on an actual filesystem failure.
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

/**
 * Read a markdown file, split its YAML frontmatter from the body, resolve
 * {{ ... }} template placeholders in the body, then parse it to HTML.
 * Returns an array of all frontmatter fields plus 'content' (rendered HTML).
 * Returns an empty array if the file doesn't exist.
 */
function render_md_from_file(string $file): array {
    if (!file_exists($file)) {
        return [];
    }

    $markdown = new Markdown(dirname($file));

    $raw = file_get_contents($file);
    [$meta, $body] = $markdown->parseFrontmatter($raw);

    $body = get_template_renderer()->render($body);

    return array_merge($meta, [
        'content' => x\markdown\from($body),
        '_file'   => $file,
    ]);
}
