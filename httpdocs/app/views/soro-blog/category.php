<?php
/** /blog/category/{slug} — kategori sayfası */
$base = defined('BASE_URL') ? BASE_URL : '';
$slug = $categorySlug ?? $slug ?? '';
$category = $category ?? ['name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug];
$catArticles = $articles ?? [];

$page_title       = $category['name'] . ' — Qordy Blog';
$meta_description = ($category['name'] ?? 'Qordy Blog') . ' kategorisindeki tüm yazılar — restoran yönetimi ve dijitalleşme üzerine Qordy içerikleri.';
$canonical        = $canonical ?? ($base . '/blog/category/' . rawurlencode($slug));
$body_class       = 'is-blog-category';
$hero = [
    'eyebrow'  => 'Kategori',
    'title'    => htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'),
    'subtitle' => count($catArticles) . ' yazı — ' . ($category['name'] ?? 'bu kategori') . ' hakkında Qordy içerikleri.',
];

include __DIR__ . '/_layout.php';
?>

<div id="reading-progress-track"><div id="reading-progress"></div></div>

<section class="container py-10 sm:py-14">
  <nav class="crumb mb-8" aria-label="Breadcrumb">
    <a href="<?= $base ?>/">Ana Sayfa</a>
    <span class="sep">/</span>
    <a href="<?= $base ?>/blog">Blog</a>
    <span class="sep">/</span>
    <span class="text-white"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </nav>

  <?php if (!empty($categories)): ?>
  <div class="chip-row mb-10" aria-label="Kategoriler">
    <a href="<?= $base ?>/blog" class="chip">Tümü</a>
    <?php foreach ($categories as $c): $active = ($c['slug'] ?? '') === $slug; ?>
      <a href="<?= $base ?>/blog/category/<?= rawurlencode($c['slug'] ?? '') ?>" class="chip<?= $active ? ' is-active' : '' ?>">
        <?= htmlspecialchars($c['name'] ?? $c['slug'], ENT_QUOTES, 'UTF-8') ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($catArticles)): ?>
  <div class="article-grid">
    <?php foreach ($catArticles as $a):
      $url = $base . '/blog/' . rawurlencode($a['slug'] ?? '');
      $img = $a['image'] ?? '';
      $t   = $a['title'] ?? '';
      $ex  = $a['description'] ?? '';
      $dt  = $a['published_at'] ?? '';
    ?>
      <article class="article-card" itemscope itemtype="https://schema.org/BlogPosting">
        <a class="cover" href="<?= $url ?>" aria-label="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>">
          <?php if ($img): ?><img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async" width="640" height="360" itemprop="image"><?php endif; ?>
        </a>
        <div class="body">
          <span class="chip"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></span>
          <h3 itemprop="headline"><a href="<?= $url ?>"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></a></h3>
          <?php if ($ex): ?><p class="excerpt"><?= htmlspecialchars($ex, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
          <?php if ($dt): ?><div class="meta"><time datetime="<?= htmlspecialchars($dt, ENT_QUOTES, 'UTF-8') ?>" itemprop="datePublished"><?= date('d M Y', strtotime($dt)) ?></time></div><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state">
    <h3>Bu kategoride henüz içerik yok</h3>
    <p>Yakında yayınlanacak. Tüm yazıları görmek için <a class="text-amber-300 underline underline-offset-2 hover:text-white" href="<?= $base ?>/blog">Blog ana sayfasını</a> ziyaret edin.</p>
  </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/_layout_footer.php'; ?>
