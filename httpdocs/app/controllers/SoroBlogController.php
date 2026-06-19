<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/../core/HelperLoader.php';
require_once __DIR__ . '/../services/SoroBlogMirrorService.php';

/**
 * SoroBlogController
 *
 * Public-facing blog powered by the Soro AI blog widget
 * (https://app.trysoro.com/). Renders a server-rendered SEO shell
 * that embeds the widget while also:
 *   - Exposing full meta / OpenGraph / Twitter / JSON-LD structured data
 *   - Providing crawler-friendly fallback content
 *   - Cooperating with an autonomous mirror service that maintains
 *     a cached list of articles for the dynamic sitemap
 *   - Gracefully serving any legacy internal blog posts that were
 *     published through the built-in BlogService (so historical
 *     URLs keep working and retain their SEO value)
 */
class SoroBlogController extends \App\Core\Controller {
    /** Soro project identifier (single source of truth). */
    public const SORO_PROJECT_ID = '2b98501d-b680-4b23-bedf-4a49592dba8d';
    public const SORO_EMBED_URL  = 'https://app.trysoro.com/api/embed/' . self::SORO_PROJECT_ID;

    /** @var \App\Services\BlogService|null */
    protected $blogService;
    /** @var \App\Services\SoroBlogMirrorService */
    protected $mirror;

    public function __construct() {
        // Blog is 100% public, skip auth but still initialize the session
        \App\Core\SessionManager::ensureSession(true);
        \App\Core\HelperLoader::ensureLoaded();

        try {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->translationService = getTranslationService();
            $this->seoService         = getSEOService();
        } catch (\Throwable $e) {
            // Services are optional – graceful degradation
        }

        try {
            $this->blogService = \App\Core\DependencyFactory::getBlogService();
        } catch (\Throwable $e) {
            $this->blogService = null;
        }

        $this->mirror = new \App\Services\SoroBlogMirrorService();
    }

    /**
     * Blog index: /blog
     */
    public function index() {
        // Warm mirror cache opportunistically (non-blocking, rate-limited)
        $this->mirror->refreshIfStale();

        $articles   = $this->mirror->getArticles();
        $categories = $this->mirror->getCategories();

        // Fallback: blend legacy internal posts when Soro has not yet
        // generated anything – keeps the page informative for crawlers.
        $legacyPosts = [];
        if ($this->blogService) {
            try {
                $legacyPosts = $this->blogService->getPublished(6, 0) ?: [];
            } catch (\Throwable $e) {
                $legacyPosts = [];
            }
        }

        $data = [
            'soroProjectId' => self::SORO_PROJECT_ID,
            'soroEmbedUrl'  => self::SORO_EMBED_URL,
            'articles'      => $articles,
            'categories'    => $categories,
            'legacyPosts'   => $legacyPosts,
            'pageType'      => 'index',
            'canonical'     => rtrim(BASE_URL, '/') . '/blog',
        ];

        $this->renderView('index', $data, [
            'title'       => 'Qordy Blog - Restoran Yönetimi, Dijitalleşme ve Menü Trendleri',
            'description' => 'Qordy blog; restoran yönetimi, QR menü, POS sistemleri, dijital ödeme, müşteri deneyimi ve restoran büyüme stratejileri hakkında uzman içerikler sunar.',
            'keywords'    => 'restoran yönetimi, QR menü, POS sistemi, dijital menü, restoran blogu, qordy blog, restoran dijitalleşmesi, yemek teknolojisi, menü yönetimi',
        ]);
    }

    /**
     * Single post: /blog/{slug}
     */
    public function post($slug) {
        $slug = is_string($slug) ? trim($slug) : '';
        if ($slug === '') {
            return $this->index();
        }

        // 1) Prefer legacy internal post when it exists – keeps old URLs 200 OK.
        $legacyPost = null;
        if ($this->blogService) {
            try {
                $legacyPost = $this->blogService->getBySlug($slug);
            } catch (\Throwable $e) {
                $legacyPost = null;
            }
        }

        // 2) Otherwise use mirrored Soro article metadata for rich SEO.
        $soroArticle = $this->mirror->getArticleBySlug($slug);

        // Refresh mirror lazily if we have no metadata yet.
        if (!$soroArticle) {
            $this->mirror->refreshIfStale();
            $soroArticle = $this->mirror->getArticleBySlug($slug);
        }

        $title       = '';
        $description = '';
        $image       = '';
        $publishedAt = '';
        $updatedAt   = '';
        $authorName  = 'Qordy';

        if ($legacyPost) {
            $title       = $legacyPost['seo_title']       ?? $legacyPost['title']   ?? '';
            $description = $legacyPost['seo_description'] ?? $legacyPost['excerpt'] ?? '';
            $image       = $legacyPost['featured_image']  ?? '';
            $publishedAt = $legacyPost['published_at']    ?? '';
            $updatedAt   = $legacyPost['updated_at']      ?? $publishedAt;
            $authorName  = $legacyPost['author_name']     ?? 'Qordy';
        } elseif ($soroArticle) {
            $title       = $soroArticle['title']       ?? '';
            $description = $soroArticle['description'] ?? '';
            $image       = $soroArticle['image']       ?? '';
            $publishedAt = $soroArticle['published_at'] ?? '';
            $updatedAt   = $soroArticle['updated_at']  ?? $publishedAt;
            $authorName  = $soroArticle['author']      ?? 'Qordy';
        } else {
            // Unknown slug yet — still render the Soro shell so the widget
            // can hydrate it. This keeps the URL 200 OK (Soro SPA routing).
            $title       = $this->prettifySlug($slug) . ' | Qordy Blog';
            $description = 'Qordy blog yazısı – restoran yönetimi, dijital menü ve müşteri deneyimi üzerine güncel içerik.';
        }

        $data = [
            'soroProjectId' => self::SORO_PROJECT_ID,
            'soroEmbedUrl'  => self::SORO_EMBED_URL,
            'slug'          => $slug,
            'legacyPost'    => $legacyPost,
            // Primary variable the new post.php view expects
            'article'       => $soroArticle,
            // Legacy-named aliases (kept for any other consumers)
            'soroArticle'   => $soroArticle,
            'articleTitle'  => $title,
            'articleDesc'   => $description,
            'articleImage'  => $image,
            'publishedAt'   => $publishedAt,
            'updatedAt'     => $updatedAt,
            'authorName'    => $authorName,
            'articles'      => $this->mirror->getArticles(),
            'categories'    => $this->mirror->getCategories(),
            'pageType'      => 'post',
            'canonical'     => rtrim(BASE_URL, '/') . '/blog/' . rawurlencode($slug),
        ];

        // If we have a legacy post, use its built-in template for fidelity.
        if ($legacyPost) {
            $this->renderLegacyPost($legacyPost);
            return;
        }

        $this->renderView('post', $data, [
            'title'       => $title ?: 'Qordy Blog',
            'description' => $description ?: 'Qordy blog yazısı',
            'keywords'    => implode(', ', array_filter([
                'qordy blog',
                'restoran yönetimi',
                'QR menü',
                $this->prettifySlug($slug),
            ])),
        ]);
    }

    /**
     * Category listing: /blog/category/{slug}
     */
    public function category($slug) {
        $slug = is_string($slug) ? trim($slug) : '';
        if ($slug === '') {
            return $this->index();
        }

        $category   = $this->mirror->getCategoryBySlug($slug);
        $articles   = $this->mirror->getArticlesByCategory($slug);

        $data = [
            'soroProjectId' => self::SORO_PROJECT_ID,
            'soroEmbedUrl'  => self::SORO_EMBED_URL,
            'category'      => $category,
            'categorySlug'  => $slug,
            'articles'      => $articles,
            'categories'    => $this->mirror->getCategories(),
            'pageType'      => 'category',
            'canonical'     => rtrim(BASE_URL, '/') . '/blog/category/' . rawurlencode($slug),
        ];

        $catName = $category['name'] ?? $this->prettifySlug($slug);
        $this->renderView('category', $data, [
            'title'       => $catName . ' | Qordy Blog',
            'description' => $catName . ' kategorisindeki en güncel Qordy blog yazıları. Restoran yönetimi ve dijitalleşme hakkında uzman içerikler.',
            'keywords'    => $catName . ', qordy blog, restoran, kategori',
        ]);
    }

    /**
     * JSON feed of mirrored Soro articles – convenient for debugging
     * and for any internal consumers (e.g. homepage featured widgets).
     * Exposed via /api/soro/articles.
     */
    public function apiArticles() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=600');

        $payload = [
            'project_id' => self::SORO_PROJECT_ID,
            'generated'  => date('c'),
            'count'      => count($this->mirror->getArticles()),
            'articles'   => $this->mirror->getArticles(),
            'categories' => $this->mirror->getCategories(),
        ];

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    /**
     * Render the appropriate Soro view with SEO meta tags injected
     * into the shared layout via $customSEOTags.
     */
    private function renderView(string $view, array $data, array $meta): void {
        $data['customSEOTags'] = $this->buildSeoTags($meta, $data);
        $data['page']          = 'blog';

        $viewFile = __DIR__ . '/../views/soro-blog/' . $view . '.php';
        if (!file_exists($viewFile)) {
            http_response_code(500);
            echo 'Soro blog view bulunamadı: ' . htmlspecialchars($view);
            return;
        }

        $this->sendPerformanceHeaders();

        extract($data);
        require $viewFile;
    }

    /**
     * Emit HTTP cache + performance headers for the blog HTML shell.
     *
     * The Soro widget loads content client-side (it is a separate
     * third-party script), so the shell itself is effectively static per
     * language + auth state. We can cache it aggressively without the
     * article list going stale — article freshness is owned by Soro.
     */
    private function sendPerformanceHeaders(): void {
        // Override the global .htaccess "no-cache" default. header() calls
        // issued here take precedence over Apache's Header set directives
        // when sent from PHP.
        header('Cache-Control: public, max-age=300, s-maxage=600, stale-while-revalidate=86400');
        header_remove('Pragma');
        header_remove('Expires');
        header('Vary: Accept-Encoding, Accept-Language, Cookie');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Use HTTP 103 "Early Hints" if the reverse proxy supports it so the
        // browser can start the TLS handshake + CSS download before PHP has
        // finished rendering. Safe to call — unsupported proxies ignore it.
        if (function_exists('headers_sent') && !headers_sent()) {
            @header('Link: </assets/css/tailwind.min.css>; rel=preload; as=style; fetchpriority=high', false);
            @header('Link: <https://app.trysoro.com>; rel=preconnect; crossorigin', false);
            @header('Link: <https://fonts.gstatic.com>; rel=preconnect; crossorigin', false);
        }
    }

    /**
     * Render a legacy internal post so historical URLs keep working
     * with the original template.
     */
    private function renderLegacyPost(array $post): void {
        if (!$this->blogService) {
            http_response_code(500);
            echo 'Blog servisi kullanılamıyor.';
            return;
        }

        $data = [
            'post'          => $post,
            'categories'    => $this->blogService->getCategories(),
            'relatedPosts'  => $post['category_id']
                ? $this->blogService->getRepository()->getRelated($post['post_id'], $post['category_id'], 5)
                : [],
        ];

        $viewPath = __DIR__ . '/../views/blog/post.php';
        if (!file_exists($viewPath)) {
            http_response_code(500);
            echo 'Blog post view dosyası bulunamadı.';
            return;
        }

        $this->sendPerformanceHeaders();

        extract($data);
        require $viewPath;
    }

    /**
     * Build full SEO meta + Open Graph + Twitter + JSON-LD for the page.
     * Returned string is injected into the <head> via $customSEOTags.
     */
    private function buildSeoTags(array $meta, array $data): string {
        $baseUrl   = rtrim(BASE_URL, '/');
        $canonical = $data['canonical'] ?? ($baseUrl . '/blog');
        $title     = $meta['title']       ?? 'Qordy Blog';
        $desc      = $meta['description'] ?? '';
        $keywords  = $meta['keywords']    ?? '';
        $ogImage   = $data['articleImage'] ?? ($baseUrl . '/assets/images/og-default.jpg');
        $pageType  = $data['pageType']     ?? 'index';

        $published = $data['publishedAt'] ?? '';
        $updated   = $data['updatedAt']   ?? $published;
        $author    = $data['authorName']  ?? 'Qordy';

        $lang = 'tr';
        if (is_object($this->translationService ?? null) && method_exists($this->translationService, 'getCurrentLanguage')) {
            try { $lang = $this->translationService->getCurrentLanguage() ?: 'tr'; } catch (\Throwable $e) {}
        }

        $supportedLangs = ['tr', 'en'];
        try {
            $settings = \App\Core\DependencyFactory::getSystemSettingsService();
            $json = $settings->getSetting('supported_languages', '["tr","en"]');
            $decoded = json_decode($json, true);
            if (is_array($decoded) && !empty($decoded)) {
                $supportedLangs = $decoded;
            }
        } catch (\Throwable $e) {}

        $esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

        // NOTE: The standalone Soro blog layout (_layout.php) already emits
        // <title>, <meta description>, <link canonical>, <meta theme-color>
        // and <meta charset>. We therefore omit them here to avoid duplicates.
        $html  = '';
        if ($keywords) {
            $html .= '<meta name="keywords" content="' . $esc($keywords) . '">' . "\n";
        }
        $html .= '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
        $html .= '<meta name="author" content="' . $esc($author) . '">' . "\n";
        $html .= '<meta name="language" content="' . $esc($lang) . '">' . "\n";

        // hreflang alternates
        foreach ($supportedLangs as $l) {
            $alt = $this->buildLangUrl($canonical, $l);
            $html .= '<link rel="alternate" hreflang="' . $esc($l) . '" href="' . $esc($alt) . '">' . "\n";
        }
        $html .= '<link rel="alternate" hreflang="x-default" href="' . $esc($canonical) . '">' . "\n";

        // Open Graph
        $ogType = ($pageType === 'post') ? 'article' : 'website';
        $html .= '<meta property="og:type" content="' . $ogType . '">' . "\n";
        $html .= '<meta property="og:site_name" content="Qordy">' . "\n";
        $html .= '<meta property="og:title" content="' . $esc($title) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . $esc($desc) . '">' . "\n";
        $html .= '<meta property="og:url" content="' . $esc($canonical) . '">' . "\n";
        $html .= '<meta property="og:locale" content="' . $esc($lang === 'tr' ? 'tr_TR' : ($lang . '_' . strtoupper($lang))) . '">' . "\n";
        if ($ogImage) {
            $html .= '<meta property="og:image" content="' . $esc($ogImage) . '">' . "\n";
            $html .= '<meta property="og:image:alt" content="' . $esc($title) . '">' . "\n";
        }
        if ($pageType === 'post') {
            if ($published) {
                $html .= '<meta property="article:published_time" content="' . $esc(date('c', strtotime($published))) . '">' . "\n";
            }
            if ($updated) {
                $html .= '<meta property="article:modified_time" content="' . $esc(date('c', strtotime($updated))) . '">' . "\n";
            }
            $html .= '<meta property="article:author" content="' . $esc($author) . '">' . "\n";
            $html .= '<meta property="article:section" content="Blog">' . "\n";
        }

        // Twitter
        $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '<meta name="twitter:title" content="' . $esc($title) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . $esc($desc) . '">' . "\n";
        if ($ogImage) {
            $html .= '<meta name="twitter:image" content="' . $esc($ogImage) . '">' . "\n";
        }

        // Preconnect to Soro (performance hint)
        $html .= '<link rel="preconnect" href="https://app.trysoro.com" crossorigin>' . "\n";
        $html .= '<link rel="dns-prefetch" href="https://app.trysoro.com">' . "\n";

        // JSON-LD structured data
        $html .= $this->buildJsonLd($pageType, $title, $desc, $canonical, $ogImage, $published, $updated, $author, $data);

        return $html;
    }

    /**
     * Build JSON-LD schema.org payloads that help search engines
     * understand the blog/article structure even though Soro renders
     * content client-side.
     */
    private function buildJsonLd(string $pageType, string $title, string $desc, string $canonical, string $image, string $published, string $updated, string $author, array $data): string {
        $baseUrl = rtrim(BASE_URL, '/');
        $siteLogo = $baseUrl . '/assets/images/logo.png';

        $organization = [
            '@type' => 'Organization',
            '@id'   => $baseUrl . '#organization',
            'name'  => 'Qordy',
            'url'   => $baseUrl,
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => $siteLogo,
            ],
        ];

        $breadcrumb = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => 'Ana Sayfa',
                    'item'     => $baseUrl . '/',
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => 'Blog',
                    'item'     => $baseUrl . '/blog',
                ],
            ],
        ];

        if ($pageType === 'post' || $pageType === 'category') {
            $breadcrumb['itemListElement'][] = [
                '@type'    => 'ListItem',
                'position' => 3,
                'name'     => $title,
                'item'     => $canonical,
            ];
        }

        $blocks = [];

        if ($pageType === 'index' || $pageType === 'category') {
            $blog = [
                '@context'    => 'https://schema.org',
                '@type'       => 'Blog',
                '@id'         => $baseUrl . '/blog#blog',
                'name'        => 'Qordy Blog',
                'description' => $desc,
                'url'         => $canonical,
                'publisher'   => $organization,
                'inLanguage'  => 'tr-TR',
            ];

            // Enrich with itemList of articles when we have them
            $articles = $data['articles'] ?? [];
            if (is_array($articles) && !empty($articles)) {
                $items = [];
                foreach (array_slice($articles, 0, 20) as $i => $a) {
                    $aUrl  = $baseUrl . '/blog/' . rawurlencode($a['slug'] ?? '');
                    $items[] = [
                        '@type'    => 'ListItem',
                        'position' => $i + 1,
                        'url'      => $aUrl,
                        'name'     => $a['title'] ?? '',
                    ];
                }
                $blog['blogPost'] = $items;
            }

            $blocks[] = $blog;
        }

        if ($pageType === 'post') {
            $article = [
                '@context'         => 'https://schema.org',
                '@type'            => 'BlogPosting',
                '@id'              => $canonical . '#article',
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id'   => $canonical,
                ],
                'headline'         => $title,
                'description'      => $desc,
                'image'            => $image ? [$image] : [],
                'url'              => $canonical,
                'datePublished'    => $published ? date('c', strtotime($published)) : date('c'),
                'dateModified'     => $updated   ? date('c', strtotime($updated))   : date('c'),
                'author'           => [
                    '@type' => 'Person',
                    'name'  => $author,
                ],
                'publisher'        => $organization,
                'inLanguage'       => 'tr-TR',
            ];
            $blocks[] = $article;
        }

        // WebSite schema — `SearchAction` YALNIZCA site üzerinde gerçek bir
        // arama endpoint'i varsa konur. Qordy'de `/search` gibi bir route
        // yok; bu yüzden Google, hatalı target URL'i algılayıp "Search Box
        // Sitelinks" işaretlemesini görmezden geliyor + GSC "Invalid
        // SearchAction" uyarısı veriyordu. Fake action'ı kaldırdık.
        $website = [
            '@context'  => 'https://schema.org',
            '@type'     => 'WebSite',
            '@id'       => $baseUrl . '#website',
            'url'       => $baseUrl,
            'name'      => 'Qordy',
            'publisher' => $organization,
            'inLanguage' => 'tr-TR',
        ];
        $blocks[] = $website;
        $blocks[] = $breadcrumb;

        $out = '';
        foreach ($blocks as $b) {
            $out .= '<script type="application/ld+json">' . "\n";
            $out .= json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $out .= "\n</script>\n";
        }
        return $out;
    }

    private function buildLangUrl(string $canonical, string $lang): string {
        $parts = parse_url($canonical);
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host']   ?? 'qordy.com';
        $path   = $parts['path']   ?? '/';

        // strip existing language prefix
        $path = preg_replace('#^/(tr|en|de|fr|ar|ru|es)(/|$)#', '/', $path);
        $path = '/' . ltrim($path, '/');
        $newPath = '/' . $lang . ($path === '/' ? '' : $path);
        return $scheme . '://' . $host . $newPath;
    }

    private function prettifySlug(string $slug): string {
        $clean = preg_replace('/[-_]+/', ' ', $slug);
        $clean = trim($clean);
        return $clean === '' ? 'Blog' : mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
    }
}
