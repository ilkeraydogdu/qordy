<?php
/**
 * /blog — Blog index (landing-uyumlu dark tasarım).
 *
 * Gelen değişkenler:
 *   $soroProjectId, $soroEmbedUrl, $articles (array<mirror>),
 *   $categories, $legacyPosts, $customSEOTags, $canonical
 */
$base = defined('BASE_URL') ? BASE_URL : '';

// Layout değişkenleri
$page_title       = 'Qordy Blog — Restoran Yönetimi, QR Menü ve Dijitalleşme Rehberleri';
$meta_description = 'Restoran işletmeciliği, QR menü, POS sistemleri, mutfak ekranları ve dijital ödeme konularında Qordy uzman ekibinin güncel analiz ve rehberleri.';
$canonical        = $canonical ?? ($base . '/blog');
$body_class       = 'is-blog-index';
$hero             = [
    'eyebrow'  => 'Qordy Blog',
    'title'    => 'Restoranınızı büyüten <span class="text-amber-300">fikirler</span>, sahadan rehberler.',
    'subtitle' => 'Restoran operasyonları, QR menü deneyimi, POS entegrasyonları ve dijital dönüşüm üzerine Qordy\'nin yayınladığı içerikler.',
];

$ogImage = (!empty($articles[0]['image'])) ? $articles[0]['image'] : ($base . '/assets/images/og-default.jpg');
$og_image = $ogImage;

include __DIR__ . '/_layout.php';
?>

<div id="reading-progress-track"><div id="reading-progress"></div></div>

<section class="container py-10 sm:py-14" itemscope itemtype="https://schema.org/Blog">
  <!-- Breadcrumb -->
  <nav class="crumb mb-8" aria-label="Breadcrumb">
    <a href="<?= $base ?>/">Ana Sayfa</a>
    <span class="sep" aria-hidden="true">/</span>
    <span class="text-white">Blog</span>
  </nav>

  <!-- Category chips -->
  <?php if (!empty($categories)): ?>
  <div class="chip-row mb-10" aria-label="Kategoriler">
    <a href="<?= $base ?>/blog" class="chip is-active">Tümü</a>
    <?php foreach ($categories as $c): ?>
      <a href="<?= $base ?>/blog/category/<?= rawurlencode($c['slug'] ?? '') ?>" class="chip">
        <?= htmlspecialchars($c['name'] ?? $c['slug'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  // Server-rendered article grid — şu an Soro widget'ından çekilen mirror
  // verisi. Widget hydrate olunca bu bölüm gizlenir (soro-fallback sınıfı).
  $all = array_values($articles ?? []);
  $featured = !empty($all) ? array_shift($all) : null;
  ?>

  <?php if ($featured || !empty($all) || !empty($legacyPosts)): ?>
  <div class="article-grid soro-fallback">
    <?php if ($featured): ?>
      <?php
      $url = $base . '/blog/' . rawurlencode($featured['slug'] ?? '');
      $img = $featured['image'] ?? '';
      $ex  = $featured['description'] ?? '';
      $t   = $featured['title'] ?? '';
      $dt  = $featured['published_at'] ?? '';
      $cat = $featured['category'] ?? 'Qordy Blog';
      ?>
      <article class="article-card is-featured" itemscope itemtype="https://schema.org/BlogPosting">
        <a class="cover" href="<?= $url ?>" aria-label="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($img): ?>
          <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
               alt="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"
               loading="eager" fetchpriority="high" decoding="async"
               width="960" height="540" itemprop="image">
          <?php endif; ?>
        </a>
        <div class="body">
          <span class="chip"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></span>
          <h3 itemprop="headline"><a href="<?= $url ?>" itemprop="url"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></a></h3>
          <?php if ($ex): ?><p class="excerpt" itemprop="description"><?= htmlspecialchars($ex, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
          <div class="meta">
            <?php if ($dt): ?><time datetime="<?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?>" itemprop="datePublished"><?= date('d M Y', strtotime($dt)); ?></time><span class="dot"></span><?php endif; ?>
            <span>Qordy Ekibi</span>
            <span class="dot"></span>
            <span><?= max(2, (int) ceil(str_word_count(strip_tags($ex)) / 200)) ?> dk okuma</span>
          </div>
          <div class="pt-2">
            <a href="<?= $url ?>" class="btn-cta text-sm w-max">
              Yazıyı Oku
              <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
          </div>
        </div>
      </article>
    <?php endif; ?>

    <?php foreach ($all as $a):
      $url = $base . '/blog/' . rawurlencode($a['slug'] ?? '');
      $img = $a['image'] ?? '';
      $t   = $a['title'] ?? '';
      $ex  = $a['description'] ?? '';
      $dt  = $a['published_at'] ?? '';
      $cat = $a['category'] ?? 'Qordy Blog';
    ?>
      <article class="article-card" itemscope itemtype="https://schema.org/BlogPosting">
        <a class="cover" href="<?= $url ?>" aria-label="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($img): ?>
            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"
                 loading="lazy" decoding="async" width="640" height="360" itemprop="image">
          <?php endif; ?>
        </a>
        <div class="body">
          <span class="chip"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></span>
          <h3 itemprop="headline"><a href="<?= $url ?>" itemprop="url"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></a></h3>
          <?php if ($ex): ?><p class="excerpt" itemprop="description"><?= htmlspecialchars($ex, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
          <div class="meta">
            <?php if ($dt): ?><time datetime="<?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?>" itemprop="datePublished"><?= date('d M Y', strtotime($dt)); ?></time><?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>

    <?php foreach (($legacyPosts ?? []) as $p): ?>
      <article class="article-card" itemscope itemtype="https://schema.org/BlogPosting">
        <div class="cover" style="background:linear-gradient(135deg,#1F5AAB 0%,#3B82F6 100%)"></div>
        <div class="body">
          <span class="chip">Arşiv</span>
          <h3 itemprop="headline"><a href="<?= $base ?>/blog-archive/<?= rawurlencode($p['slug'] ?? '') ?>"><?= htmlspecialchars($p['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></a></h3>
          <?php if (!empty($p['excerpt'])): ?><p class="excerpt"><?= htmlspecialchars($p['excerpt'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Soro widget mount — JS hydrate olunca client-side render devreye girer -->
  <div class="mt-14" id="soro-blog-shell">
    <div id="soro-blog"></div>
    <?php if (empty($featured) && empty($all) && empty($legacyPosts)): ?>
    <noscript>
      <div class="empty-state mt-8">
        <h3>Blogu en iyi görüntülemek için JavaScript etkin olmalı</h3>
        <p>Qordy blog içerikleri dinamik olarak yüklenir. Lütfen tarayıcınızın JavaScript ayarını açın.</p>
      </div>
    </noscript>
    <?php endif; ?>
  </div>

  <script src="<?= htmlspecialchars($soroEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</section>

<?php include __DIR__ . '/_layout_footer.php'; ?>
