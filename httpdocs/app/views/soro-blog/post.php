<?php
/**
 * /blog/{slug} — Single article page.
 *
 * Gelen değişkenler: $soroEmbedUrl, $slug, $article (from mirror|null),
 * $customSEOTags, $canonical, $articles (all — for related)
 */
$base = defined('BASE_URL') ? BASE_URL : '';
$slug = $slug ?? '';
$article = $article ?? null;
$allArticles = $articles ?? [];

// Eğer article verisi mirror'dan gelmediyse minimum fallback oluştur.
$t   = $article['title']       ?? ucwords(str_replace('-', ' ', $slug));
$ex  = $article['description'] ?? '';
$img = $article['image']       ?? '';
$dt  = $article['published_at'] ?? '';
$upd = $article['updated_at']   ?? $dt;
$cat = $article['category']     ?? 'Qordy Blog';
$catSlug = $article['category_slug'] ?? '';
$articleId = $article['id'] ?? '';

// Tam makale içeriğini Soro'dan çek (cache'li).
$content = '';
if ($articleId) {
    try {
        require_once __DIR__ . '/../../services/SoroBlogMirrorService.php';
        $mirror = new \App\Services\SoroBlogMirrorService();
        $content = (string) $mirror->getArticleContent($articleId);
    } catch (\Throwable $e) { /* non-fatal */ }
}

// Okuma süresi tahmini
$wordCount = max(1, str_word_count(strip_tags($content . ' ' . $ex)));
$readingMin = max(1, (int) ceil($wordCount / 200));

// Layout değişkenleri
$page_title       = $t . ' — Qordy Blog';
$meta_description = $ex ?: 'Qordy Blog yazısı: ' . $t;
$canonical        = $canonical ?? ($base . '/blog/' . rawurlencode($slug));
$og_image         = $img ?: ($base . '/assets/images/og-default.jpg');
$body_class       = 'is-blog-post';

include __DIR__ . '/_layout.php';

$shareUrl = $canonical;
$shareTitle = $t;
?>

<div id="reading-progress-track"><div id="reading-progress"></div></div>

<article class="container py-10 sm:py-14" itemscope itemtype="https://schema.org/BlogPosting">
  <meta itemprop="mainEntityOfPage" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
  <meta itemprop="author" content="Qordy">
  <meta itemprop="publisher" content="Qordy">

  <!-- Breadcrumb -->
  <nav class="crumb mb-8" aria-label="Breadcrumb">
    <a href="<?= $base ?>/">Ana Sayfa</a>
    <span class="sep">/</span>
    <a href="<?= $base ?>/blog">Blog</a>
    <?php if ($catSlug): ?>
      <span class="sep">/</span>
      <a href="<?= $base ?>/blog/category/<?= rawurlencode($catSlug) ?>"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
    <span class="sep">/</span>
    <span class="text-white line-clamp-1"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></span>
  </nav>

  <header class="max-w-3xl">
    <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-300 bg-white/5 border border-white/10 rounded-full px-3 py-1 mb-6">
      <span class="block w-1.5 h-1.5 rounded-full bg-amber-300"></span>
      <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <h1 class="font-display text-white font-semibold text-3xl sm:text-5xl lg:text-6xl leading-[1.1] tracking-tight" itemprop="headline">
      <?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <?php if ($ex): ?>
    <p class="mt-5 text-slate-300 text-lg sm:text-xl leading-relaxed max-w-2xl" itemprop="description">
      <?= htmlspecialchars($ex, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php endif; ?>

    <div class="flex flex-wrap items-center gap-3 mt-7 text-sm text-slate-400">
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-[#1F5AAB] to-[#3B82F6] text-white text-xs font-semibold">Q</span>
        <span>Qordy Ekibi</span>
      </div>
      <span class="text-slate-600">•</span>
      <?php if ($dt): ?>
      <time datetime="<?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?>" itemprop="datePublished"><?= date('d F Y', strtotime($dt)) ?></time>
      <span class="text-slate-600">•</span>
      <?php endif; ?>
      <span><?= $readingMin ?> dk okuma</span>
      <?php if ($upd && $upd !== $dt): ?>
      <meta itemprop="dateModified" content="<?= htmlspecialchars($upd, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>
    </div>
  </header>

  <?php if ($img): ?>
  <figure class="my-10 sm:my-14 rounded-2xl overflow-hidden border border-white/10 shadow-2xl shadow-black/40">
    <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
         alt="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"
         loading="eager" fetchpriority="high" decoding="async"
         width="1280" height="720" itemprop="image"
         class="w-full aspect-video object-cover">
  </figure>
  <?php endif; ?>

  <!-- Article body: server-rendered content + Soro widget fallback -->
  <div class="mx-auto max-w-3xl">
    <?php if ($content): ?>
      <div class="prose-blog soro-fallback" itemprop="articleBody">
        <?= $content /* trusted HTML from Soro */ ?>
      </div>
    <?php endif; ?>

    <!-- Soro widget — client-side'da aynı içeriği re-hydrate eder.
         server-rendered içerik varsa görünür kalır; yoksa widget görür. -->
    <?php if (!$content): ?>
      <div id="soro-blog" class="prose-blog"></div>
      <script src="<?= htmlspecialchars($soroEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" defer></script>
    <?php endif; ?>

    <!-- Share rail -->
    <div class="share-rail mt-12 pt-8 border-t border-white/10">
      <span class="share-label">Paylaş</span>
      <button class="share-btn is-twitter" data-share="twitter" data-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="X'te paylaş">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2H21.5l-7.62 8.71L22.77 22h-6.775l-5.3-6.94L4.6 22H1.34l8.16-9.32L1.23 2h6.94l4.77 6.31L18.244 2zm-1.18 18.4h1.85L7.04 3.52H5.06l12.004 16.88z"/></svg>
        X (Twitter)
      </button>
      <button class="share-btn is-facebook" data-share="facebook" data-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="Facebook'ta paylaş">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.776-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33V21.88C18.343 21.128 22 16.991 22 12z"/></svg>
        Facebook
      </button>
      <button class="share-btn is-linkedin" data-share="linkedin" data-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="LinkedIn'de paylaş">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.852 3.37-1.852 3.601 0 4.267 2.37 4.267 5.455v6.288zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
        LinkedIn
      </button>
      <button class="share-btn is-whatsapp" data-share="whatsapp" data-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="WhatsApp'ta paylaş">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        WhatsApp
      </button>
      <button class="share-btn is-telegram" data-share="telegram" data-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" data-title="<?= htmlspecialchars($shareTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="Telegram'da paylaş">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
        Telegram
      </button>
      <button class="share-btn" data-share="copy" data-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Bağlantıyı kopyala">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Bağlantıyı Kopyala
      </button>
    </div>
  </div>

  <!-- Related articles -->
  <?php
  $related = array_values(array_filter($allArticles, fn($a) => ($a['slug'] ?? '') !== $slug));
  $related = array_slice($related, 0, 3);
  ?>
  <?php if (!empty($related)): ?>
  <section class="mt-20" aria-label="İlgili yazılar">
    <h2 class="font-display text-white text-2xl sm:text-3xl font-semibold mb-6">İlgili Yazılar</h2>
    <div class="article-grid">
      <?php foreach ($related as $a):
        $url = $base . '/blog/' . rawurlencode($a['slug'] ?? '');
        $img = $a['image'] ?? '';
        $t   = $a['title'] ?? '';
        $ex  = $a['description'] ?? '';
      ?>
      <article class="article-card">
        <a class="cover" href="<?= $url ?>" aria-label="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async" width="640" height="360">
          <?php endif; ?>
        </a>
        <div class="body">
          <h3><a href="<?= $url ?>"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></a></h3>
          <?php if ($ex): ?><p class="excerpt"><?= htmlspecialchars($ex, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</article>

<?php include __DIR__ . '/_layout_footer.php'; ?>
