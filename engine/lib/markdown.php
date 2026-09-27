<?php

require_once __DIR__ . '/vendor/Parsedown.php';
require_once __DIR__ . '/vendor/Spyc.php';
require_once __DIR__ . '/renderer.php';

class Markdown {
    private string $contentDir;
    private Parsedown $parser;
    private TemplateRenderer $renderer;

    public function __construct(string $contentDir, ?TemplateRenderer $renderer = null) {
        $this->contentDir = rtrim($contentDir, '/');
        $this->parser = new Parsedown();
        $this->renderer = $renderer ?? new TemplateRenderer();
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
     * Split frontmatter (real YAML, parsed via Spyc) from the markdown body.
     * Supports nested values, lists, quoted strings, etc. — not just flat key: value.
     * Returns [meta_array, body_string]
     */
    public function parseFrontmatter(string $raw): array {
        $meta = [];
        $body = $raw;

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $raw, $matches)) {
            $body = $matches[2];
            $parsed = Spyc::YAMLLoadString($matches[1]);
            $meta = is_array($parsed) ? $parsed : [];
        }

        return [$meta, $body];
    }

    public function getRenderer(): TemplateRenderer {
        return $this->renderer;
    }

    public function getAllPagesMeta(string $path = ''): array {
        $files       = glob($this->contentDir . '/*.md');
        $cacheFile   = $this->getCacheFile();
        $newestMtime = $this->getNewestMtime($files);

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
                return $cached['pages'];
            }
        }

        // Cache miss / stale — do the real work
        $pages = [];
        foreach ($files as $file) {
            [$meta] = $this->parseFrontmatter(file_get_contents($file));
            $slug = $this->filenameToSlug($file);

            // Flatten frontmatter fields to the top level (category, tags, etc.
            // come straight from $meta), but always force slug/url/title/date
            // to the computed values — merge them LAST so they win even if the
            // frontmatter accidentally defines a field with the same name.
            $pages[] = array_merge($meta, [
                'slug'  => $slug,
                'url'   => '/' . trim($path, '/') . '/' . $slug,
                'title' => $meta['title'] ?? $slug,
                'date'  => $meta['date'] ?? null,
            ]);
        }

        usort($pages, fn($a, $b) => strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01'));

        $title = DYNAMIC_PAGES[$path]['title'] ?? null;
        file_put_contents(
            $cacheFile,
            '<?php return ' . var_export([
                'title'        => $title,
                'url'          => '/' . trim($path, '/'),
                'pages'        => $pages,
                'newest_mtime' => $newestMtime,
                'file_count'   => count($files),
            ], true) . ';',
            LOCK_EX
        );

        return $pages;
    }

    /**
     * Get a single page (frontmatter + markdown parsed to HTML).
     * Template placeholders like {{ home_url }} and {{ url('...') }} inside
     * the body are resolved BEFORE the body is parsed by Parsedown.
     * Returns null if the file doesn't exist.
     */
    public function getPage(string $slug): ?array {
        $slug = $this->filenameToSlug(basename($slug));
        $path = $this->contentDir . '/' . $slug . '.md';

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        [$meta, $body] = $this->parseFrontmatter($raw);

        $body = $this->renderer->render($body);

        return array_merge($meta, [
            'slug'    => $slug,
            'title'   => $meta['title'] ?? $slug,
            'date'    => $meta['date'] ?? null,
            'content' => $this->parser->text($body), // markdown -> HTML
        ]);
    }

    /**
     * @param string  $orderBy  Field to sort by — 'title', 'date', 'slug', or
     *                          any custom frontmatter field (e.g. 'author').
     * @param ?string $orderDir 'asc' or 'desc'. Defaults to 'desc' when
     *                          $orderBy is 'date', otherwise 'asc'.
     */
    public function getPages(
        int $page = 1,
        string $path = '',
        int $perPage = 10,
        ?string $category = null,
        ?string $tag = null,
        string $orderBy = 'title',
        ?string $orderDir = null
    ): array {
        $allPages = $this->getAllPagesMeta($path);

        // Filter by category (exact match)
        if ($category !== null && $category !== '') {
            $allPages = array_values(array_filter(
                $allPages,
                fn($p) => ($p['category'] ?? null) === $category
            ));
        }

        // Filter by tag (tags is a YAML list -> PHP array via Spyc)
        if ($tag !== null && $tag !== '') {
            $allPages = array_values(array_filter(
                $allPages,
                fn($p) => in_array($tag, $p['tags'] ?? [], true)
            ));
        }

        // Sort by the requested field. Checks top-level keys (slug, title,
        // date) first, then falls back to frontmatter 'meta' for custom
        // fields (e.g. 'author', 'priority').
        $orderDir = $orderDir ?? ($orderBy === 'date' ? 'desc' : 'asc');

        $getSortValue = fn($p) => $p[$orderBy] ?? null;

        usort($allPages, function ($a, $b) use ($getSortValue, $orderBy, $orderDir) {
            $va = $getSortValue($a);
            $vb = $getSortValue($b);

            if ($orderBy === 'date') {
                $cmp = strtotime($va ?? '1970-01-01') <=> strtotime($vb ?? '1970-01-01');
            } elseif (is_numeric($va) && is_numeric($vb)) {
                $cmp = $va <=> $vb;
            } else {
                $cmp = strcasecmp((string) $va, (string) $vb);
            }

            return $orderDir === 'desc' ? -$cmp : $cmp;
        });

        $total      = count($allPages);
        $totalPages = max(1, (int) ceil($total / $perPage));

        $page   = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'list'        => array_slice($allPages, $offset, $perPage),
            'total'       => $total,
            'perPage'     => $perPage,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
        ];
    }
}

/**
 * Build pagination data (prev, next, list of page numbers with a window
 * around the current page + "..." markers).
 *
 * $baseUrl example: '/article' -> result '/article?page=2'
 */
function renderPaginationLinks(int $currentPage, int $totalPages, string $baseUrl = '', array $extraParams = [], int $window = 2): array {
    $baseUrl = '/' . trim($baseUrl, '/');

    $urlFor = function (int $p) use ($baseUrl, $extraParams) {
        $params = array_filter(array_merge($extraParams, ['page' => $p]), fn($v) => $v !== null && $v !== '');
        return $baseUrl . '?' . http_build_query($params);
    };

    $pages = [];
    $lastAdded = 0;

    for ($i = 1; $i <= $totalPages; $i++) {
        $isEdge   = $i === 1 || $i === $totalPages;
        $isNearBy = $i >= $currentPage - $window && $i <= $currentPage + $window;

        if ($isEdge || $isNearBy) {
            $pages[] = ['page' => $i, 'url' => $urlFor($i), 'active' => $i === $currentPage];
            $lastAdded = $i;
        } elseif ($lastAdded !== -1 && $i - $lastAdded > 1) {
            $pages[] = ['page' => '...', 'url' => null, 'active' => false];
            $lastAdded = -1;
        }
    }

    return [
        'prev'        => $currentPage > 1 ? $urlFor($currentPage - 1) : null,
        'next'        => $currentPage < $totalPages ? $urlFor($currentPage + 1) : null,
        'currentPage' => $currentPage,
        'totalPages'  => $totalPages,
        'pages'       => $pages,
    ];
}
