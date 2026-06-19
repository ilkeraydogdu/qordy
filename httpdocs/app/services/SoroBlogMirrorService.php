<?php
namespace App\Services;

/**
 * SoroBlogMirrorService
 *
 * Autonomous mirror of the Soro blog widget content.
 *
 * Soro (https://app.trysoro.com/) is a client-side-rendered embed, which
 * means search engines can crawl the shell but historically have trouble
 * discovering and deep-linking individual articles. This service
 * complements the widget by:
 *
 *   1. Continuously probing Soro's public surfaces (embed endpoint,
 *      potential JSON APIs and any public sitemap they expose) to
 *      discover article metadata: slug, title, description, image,
 *      publication dates, category.
 *   2. Caching that metadata on disk at storage/cache/soro/articles.json
 *      so /blog pages and the dynamic sitemap can enrich themselves
 *      with up-to-date structured data — even before Soro exposes a
 *      documented API.
 *   3. Degrading gracefully: if no data is discoverable, the service
 *      returns empty structures and the UI still works because the
 *      Soro widget renders client-side.
 *
 * The cache is considered fresh for `CACHE_TTL_SECONDS` after which a
 * lazy background refresh is attempted on the next page render. The
 * cron worker `cron/soro_mirror.php` performs a forced refresh on a
 * schedule, keeping the system autonomous.
 */
class SoroBlogMirrorService {
    public const CACHE_TTL_SECONDS  = 3600;         // 1 hour
    public const STALE_GRACE_SECONDS = 86400 * 7;  // 7 days
    public const HTTP_TIMEOUT        = 8;

    /** @var string */
    private $projectId;
    /** @var string */
    private $cacheDir;
    /** @var string */
    private $cacheFile;
    /** @var string */
    private $statusFile;

    public function __construct(?string $projectId = null) {
        $this->projectId  = $projectId ?: \App\Controllers\SoroBlogController::SORO_PROJECT_ID;
        $this->cacheDir   = dirname(__DIR__, 2) . '/storage/cache/soro';
        $this->cacheFile  = $this->cacheDir . '/articles.json';
        $this->statusFile = $this->cacheDir . '/status.json';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    // ----------------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function getArticles(): array {
        $data = $this->loadCache();
        return isset($data['articles']) && is_array($data['articles']) ? $data['articles'] : [];
    }

    /** @return array<int,array<string,string>> */
    public function getCategories(): array {
        $data = $this->loadCache();
        return isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
    }

    public function getArticleBySlug(string $slug): ?array {
        foreach ($this->getArticles() as $a) {
            if (($a['slug'] ?? null) === $slug) {
                return $a;
            }
        }
        return null;
    }

    public function getCategoryBySlug(string $slug): ?array {
        foreach ($this->getCategories() as $c) {
            if (($c['slug'] ?? null) === $slug) {
                return $c;
            }
        }
        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public function getArticlesByCategory(string $categorySlug): array {
        $out = [];
        foreach ($this->getArticles() as $a) {
            $cat = $a['category_slug'] ?? null;
            if ($cat === $categorySlug) {
                $out[] = $a;
            }
        }
        return $out;
    }

    public function isStale(): bool {
        if (!is_file($this->cacheFile)) {
            return true;
        }
        $mtime = (int) @filemtime($this->cacheFile);
        return (time() - $mtime) > self::CACHE_TTL_SECONDS;
    }

    /**
     * Best-effort non-blocking refresh: only runs if the cache is stale
     * AND a refresh has not already been attempted in the last minute.
     */
    public function refreshIfStale(): bool {
        if (!$this->isStale()) {
            return false;
        }
        $status = $this->loadStatus();
        if (!empty($status['last_attempt']) && (time() - (int) $status['last_attempt']) < 60) {
            return false;
        }
        try {
            return $this->refresh();
        } catch (\Throwable $e) {
            $this->writeStatus([
                'last_attempt' => time(),
                'last_error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Force a refresh. Used by the cron worker and admin tools.
     *
     * @return bool true if something was written to the cache.
     */
    public function refresh(): bool {
        $status = [
            'last_attempt' => time(),
            'project_id'   => $this->projectId,
            'strategies'   => [],
        ];

        $articles  = [];
        $categories = [];

        // Strategy ladder: try several known/likely Soro endpoints in order.
        // Order matters — embed_js is authoritative (it is literally the
        // JavaScript Soro serves to end-users, so whatever the dashboard
        // publishes is embedded there as a JSON blob).
        $strategies = [
            ['name' => 'embed_js',            'fn' => fn() => $this->fetchEmbedJs('https://app.trysoro.com/api/embed/' . $this->projectId)],
            ['name' => 'public_api_articles', 'fn' => fn() => $this->fetchJson('https://app.trysoro.com/api/public/articles/' . $this->projectId)],
            ['name' => 'public_api_v1',       'fn' => fn() => $this->fetchJson('https://app.trysoro.com/api/v1/public/articles/' . $this->projectId)],
            ['name' => 'embed_data',          'fn' => fn() => $this->fetchJson('https://app.trysoro.com/api/embed/' . $this->projectId . '/data')],
            ['name' => 'embed_articles',      'fn' => fn() => $this->fetchJson('https://app.trysoro.com/api/embed/' . $this->projectId . '/articles')],
            ['name' => 'sitemap_xml',         'fn' => fn() => $this->fetchXml('https://app.trysoro.com/sitemap/' . $this->projectId . '.xml')],
        ];

        foreach ($strategies as $s) {
            $res = null;
            try {
                $res = ($s['fn'])();
            } catch (\Throwable $e) {
                $status['strategies'][$s['name']] = ['ok' => false, 'error' => $e->getMessage()];
                continue;
            }

            $parsed = $this->normalizeResponse($res, $s['name']);
            $status['strategies'][$s['name']] = [
                'ok'       => $parsed !== null,
                'count'    => $parsed ? count($parsed['articles'] ?? []) : 0,
            ];
            if ($parsed && !empty($parsed['articles'])) {
                $articles  = $parsed['articles'];
                $categories = $parsed['categories'] ?? [];
                break;
            }
        }

        // Persist even if articles are empty – so downstream callers can rely
        // on a stable file existing – but keep previous cache if the previous
        // run had articles and this run has none (do not regress).
        if (empty($articles)) {
            $existing = $this->loadCache();
            if (!empty($existing['articles']) && !empty($existing['generated_at'])
                && (time() - (int) strtotime($existing['generated_at'])) < self::STALE_GRACE_SECONDS) {
                // Keep existing articles, just refresh the timestamp so we do
                // not hammer Soro on every page load.
                $existing['generated_at'] = date('c');
                $this->atomicWrite($this->cacheFile, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                $this->writeStatus($status + ['kept_previous' => true]);
                return true;
            }
        }

        $payload = [
            'project_id'   => $this->projectId,
            'generated_at' => date('c'),
            'articles'     => $articles,
            'categories'   => $categories,
            'count'        => count($articles),
        ];

        $ok = $this->atomicWrite($this->cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) !== false;
        $status['wrote_cache'] = $ok;
        $this->writeStatus($status);
        return $ok && !empty($articles);
    }

    // ----------------------------------------------------------------------
    // Internal helpers
    // ----------------------------------------------------------------------

    private function loadCache(): array {
        if (!is_file($this->cacheFile)) {
            return ['articles' => [], 'categories' => []];
        }
        $raw = @file_get_contents($this->cacheFile);
        if ($raw === false || $raw === '') {
            return ['articles' => [], 'categories' => []];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : ['articles' => [], 'categories' => []];
    }

    private function loadStatus(): array {
        if (!is_file($this->statusFile)) {
            return [];
        }
        $raw = @file_get_contents($this->statusFile);
        $d = $raw ? json_decode($raw, true) : [];
        return is_array($d) ? $d : [];
    }

    private function writeStatus(array $s): void {
        $this->atomicWrite($this->statusFile, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function atomicWrite(string $file, string $content) {
        $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
        $bytes = @file_put_contents($tmp, $content, LOCK_EX);
        if ($bytes === false) {
            return false;
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return $bytes;
    }

    /**
     * Normalise a raw Soro response into a standard shape.
     *
     * @param mixed $res
     * @return array{articles:array,categories:array}|null
     */
    private function normalizeResponse($res, string $source): ?array {
        if ($res === null) {
            return null;
        }

        // embed_js returns data already in our canonical shape. Just
        // derive categories from the article metadata and return it.
        if ($source === 'soro_embed_js' || $source === 'embed_js') {
            if (!isset($res['articles']) || !is_array($res['articles'])) {
                return null;
            }
            $articles = $res['articles'];
            $categories = [];
            $seen = [];
            foreach ($articles as $a) {
                $s = $a['category_slug'] ?? '';
                if ($s && !isset($seen[$s])) {
                    $seen[$s] = true;
                    $categories[] = [
                        'slug' => $s,
                        'name' => $a['category'] ?: $this->titleFromSlug($s),
                    ];
                }
            }
            return ['articles' => $articles, 'categories' => $categories];
        }

        if ($source === 'sitemap_xml' && is_array($res) && !empty($res['urls'])) {
            $articles = [];
            foreach ($res['urls'] as $u) {
                $loc = is_array($u) ? ($u['loc'] ?? '') : (string) $u;
                if (!$loc) continue;
                // Detect paths like /blog/slug or /article/slug
                if (preg_match('#/(?:blog|article|post|articles)/([a-z0-9][a-z0-9\-_]*)/?$#i', $loc, $m)) {
                    $articles[] = [
                        'slug'         => $m[1],
                        'title'        => $this->titleFromSlug($m[1]),
                        'url'          => $loc,
                        'published_at' => $u['lastmod'] ?? '',
                        'updated_at'   => $u['lastmod'] ?? '',
                        'source'       => 'sitemap',
                    ];
                }
            }
            return ['articles' => $articles, 'categories' => []];
        }

        if (!is_array($res)) {
            return null;
        }

        // Common shapes: {articles:[...]}, {data:{articles:[...]}}, [...]
        $list = null;
        if (isset($res['articles']) && is_array($res['articles'])) {
            $list = $res['articles'];
        } elseif (isset($res['data']['articles']) && is_array($res['data']['articles'])) {
            $list = $res['data']['articles'];
        } elseif (isset($res['posts']) && is_array($res['posts'])) {
            $list = $res['posts'];
        } elseif (isset($res['data']) && is_array($res['data']) && array_is_list($res['data'])) {
            $list = $res['data'];
        } elseif (array_is_list($res)) {
            $list = $res;
        }

        if ($list === null) {
            return null;
        }

        $articles = [];
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            $slug = $row['slug'] ?? $row['url_slug'] ?? $row['path'] ?? '';
            if (is_string($slug) && strpos($slug, '/') !== false) {
                $slug = basename(rtrim($slug, '/'));
            }
            if (!$slug) continue;

            $articles[] = [
                'slug'          => (string) $slug,
                'title'         => (string) ($row['title'] ?? $row['headline'] ?? $this->titleFromSlug((string) $slug)),
                'description'   => (string) ($row['description'] ?? $row['excerpt'] ?? $row['summary'] ?? ''),
                'image'         => (string) ($row['image'] ?? $row['cover_image'] ?? $row['thumbnail'] ?? ''),
                'author'        => (string) ($row['author'] ?? $row['author_name'] ?? 'Qordy'),
                'published_at'  => (string) ($row['published_at'] ?? $row['publishedAt'] ?? $row['created_at'] ?? ''),
                'updated_at'    => (string) ($row['updated_at'] ?? $row['updatedAt']     ?? $row['published_at'] ?? ''),
                'category'      => (string) ($row['category'] ?? $row['category_name'] ?? ''),
                'category_slug' => (string) ($row['category_slug'] ?? $row['categorySlug'] ?? ''),
                'keywords'      => (string) (is_array($row['keywords'] ?? null) ? implode(',', $row['keywords']) : ($row['keywords'] ?? '')),
                'source'        => $source,
            ];
        }

        $categories = [];
        $catList = $res['categories'] ?? ($res['data']['categories'] ?? null);
        if (is_array($catList)) {
            foreach ($catList as $c) {
                if (!is_array($c)) continue;
                $slug = $c['slug'] ?? '';
                $name = $c['name'] ?? $c['title'] ?? '';
                if ($slug || $name) {
                    $categories[] = [
                        'slug' => (string) $slug,
                        'name' => (string) $name,
                    ];
                }
            }
        } else {
            // Derive categories from article metadata
            $seen = [];
            foreach ($articles as $a) {
                if (!empty($a['category_slug']) && !isset($seen[$a['category_slug']])) {
                    $seen[$a['category_slug']] = true;
                    $categories[] = [
                        'slug' => $a['category_slug'],
                        'name' => $a['category'] ?: $this->titleFromSlug($a['category_slug']),
                    ];
                }
            }
        }

        return ['articles' => $articles, 'categories' => $categories];
    }

    /**
     * Parse the Soro embed JavaScript blob.
     *
     * The embed file is a plain JS script that contains:
     *   var SORO_ARTICLES = [{…article metadata…}];
     *   var SORO_BLOG_TITLE = 'qordy.com';
     *
     * We extract the JSON array via regex (robust against minifier changes
     * because the variable name is stable) and return the parsed data in
     * the shape expected by normalizeResponse().
     *
     * @return mixed array suitable for normalizeResponse() or null.
     */
    private function fetchEmbedJs(string $url) {
        $body = $this->httpGet($url, ['Accept: application/javascript,text/javascript,*/*']);
        if ($body === null || $body === '' || stripos($body, 'Embed is disabled') !== false) {
            return null;
        }
        if (!preg_match('/SORO_ARTICLES\s*=\s*(\[.*?\]);/s', $body, $m)) {
            return null;
        }
        $json = json_decode($m[1], true);
        if (!is_array($json)) {
            return null;
        }
        // Normalize each entry to the shape normalizeResponse() expects.
        $articles = [];
        foreach ($json as $row) {
            if (!is_array($row) || empty($row['slug'])) { continue; }
            $published = $row['isoDate'] ?? $row['publishedAt'] ?? $row['date'] ?? '';
            $articles[] = [
                'id'            => (string) ($row['id'] ?? ''),
                'slug'          => (string) $row['slug'],
                'title'         => (string) ($row['title'] ?? ''),
                'description'   => (string) ($row['excerpt'] ?? $row['description'] ?? ''),
                'image'         => (string) ($row['image'] ?? ''),
                'author'        => (string) ($row['author'] ?? 'Qordy'),
                'published_at'  => (string) $published,
                'updated_at'    => (string) ($row['updatedAt'] ?? $row['updated_at'] ?? $published),
                'category'      => (string) ($row['category'] ?? ''),
                'category_slug' => (string) ($row['categorySlug'] ?? $row['category_slug'] ?? ''),
                'keywords'      => is_array($row['keywords'] ?? null) ? implode(',', $row['keywords']) : (string) ($row['keywords'] ?? ''),
                'source'        => 'soro_embed_js',
            ];
        }
        return ['articles' => $articles];
    }

    /**
     * Fetch (and cache) the full HTML content of a single Soro article.
     * Soro exposes this via /api/embed/{TOKEN}/article/{ID} as JSON with
     * a "content" field. We cache per-article to keep articles.json small.
     */
    public function getArticleContent(string $articleId): ?string {
        if ($articleId === '') { return null; }
        $safeId = preg_replace('/[^a-zA-Z0-9\-]/', '', $articleId);
        if ($safeId === '') { return null; }
        $file   = $this->cacheDir . '/article_' . $safeId . '.html';

        // Serve fresh cache (< 1h) without hitting the network.
        if (is_file($file) && (time() - (int) @filemtime($file)) < self::CACHE_TTL_SECONDS) {
            $c = @file_get_contents($file);
            if ($c !== false) { return (string) $c; }
        }

        $body = $this->httpGet(
            'https://app.trysoro.com/api/embed/' . $this->projectId . '/article/' . rawurlencode($articleId),
            ['Accept: application/json']
        );
        if ($body === null) {
            // Fallback to last-good cache even if stale.
            if (is_file($file)) { return @file_get_contents($file) ?: null; }
            return null;
        }
        $json = json_decode($body, true);
        $html = is_array($json) ? (string) ($json['content'] ?? '') : '';
        if ($html === '') {
            if (is_file($file)) { return @file_get_contents($file) ?: null; }
            return null;
        }
        $this->atomicWrite($file, $html);
        return $html;
    }

    /** @return mixed */
    private function fetchJson(string $url) {
        $body = $this->httpGet($url, ['Accept: application/json']);
        if ($body === null) {
            return null;
        }
        $json = json_decode($body, true);
        return is_array($json) ? $json : null;
    }

    private function fetchXml(string $url): ?array {
        $body = $this->httpGet($url, ['Accept: application/xml, text/xml']);
        if ($body === null || stripos($body, '<urlset') === false) {
            return null;
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if (!$xml) {
            return null;
        }
        $urls = [];
        foreach ($xml->url as $u) {
            $urls[] = [
                'loc'     => (string) $u->loc,
                'lastmod' => (string) ($u->lastmod ?? ''),
            ];
        }
        return ['urls' => $urls];
    }

    private function httpGet(string $url, array $headers = []): ?string {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
                CURLOPT_USERAGENT      => 'QordySoroMirror/1.0 (+https://qordy.com)',
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 300) {
                return null;
            }
            return (string) $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", array_merge([
                    'User-Agent: QordySoroMirror/1.0 (+https://qordy.com)',
                ], $headers)),
                'timeout' => self::HTTP_TIMEOUT,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false ? (string) $body : null;
    }

    private function titleFromSlug(string $slug): string {
        $clean = preg_replace('/[-_]+/', ' ', $slug);
        return mb_convert_case(trim($clean), MB_CASE_TITLE, 'UTF-8');
    }
}
