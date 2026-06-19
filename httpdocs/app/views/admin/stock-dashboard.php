<?php
/**
 * Stock Manager dashboard — Warm Ember Ops design system (.q-* components)
 *
 * Focused landing page for the STOCK_MANAGER role. Surfaces top-level KPIs
 * (low stock, waste today, 7-day purchases) and quick links to stock tools.
 */
$summary = $summary ?? [];
$baseUrl = BASE_URL;

// Robust accessors (PHP 8.4: avoid Undefined array key warnings)
$ingredientsTotal   = (int)($summary['ingredients_total']   ?? 0);
$ingredientsLow     = (int)($summary['ingredients_low']      ?? 0);
$ingredientsBlocked = (int)($summary['ingredients_blocked']  ?? 0);
$wasteTodayCost     = (float)($summary['waste_today_cost']   ?? 0);
$wasteTodayCount    = (int)($summary['waste_today_count']    ?? 0);
$purchaseWeekCost   = (float)($summary['purchase_week_cost'] ?? 0);
$purchaseWeekCount  = (int)($summary['purchase_week_count']  ?? 0);

$money = static fn(float $v): string => number_format($v, 2, ',', '.') . ' ₺';

$links = [
    ['title' => 'Stok', 'href' => '/business/stock', 'icon' => '📦'],
    ['title' => 'Kategoriler & Birimler', 'href' => '/business/stock-categories', 'icon' => '🗂️'],
    ['title' => 'İrsaliye / Satın Alma', 'href' => '/business/purchases', 'icon' => '📥'],
    ['title' => 'Fire Kayıtları', 'href' => '/business/finance/waste', 'icon' => '🧯'],
    ['title' => 'Düşük Stok Ayarları', 'href' => '/business/low-stock', 'icon' => '🔔'],
    ['title' => 'Tedarikçi Performansı', 'href' => '/business/supplier-performance', 'icon' => '📊'],
    ['title' => 'Tedarikçiler', 'href' => '/business/finance/suppliers', 'icon' => '🤝'],
];
?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Stok</p>
        <h1 class="q-page-header__title">Stok Yönetimi</h1>
        <p class="q-page-header__subtitle">Operasyonel stok, fire ve satın alma kontrolleri</p>
      </div>
      <div class="q-page-header__actions">
        <a href="<?php echo $baseUrl; ?>/business/stock" class="q-btn q-btn--primary">Stok Listesi</a>
      </div>
    </header>

    <section class="q-grid q-grid--4" aria-label="Özet göstergeler">
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Toplam Ürün</span></div>
        <div class="q-stat__value"><?php echo $ingredientsTotal; ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top">
          <span class="q-stat__label">Düşük Stok</span>
          <?php if ($ingredientsLow > 0): ?><span class="q-badge q-badge--warning">dikkat</span><?php endif; ?>
        </div>
        <div class="q-stat__value" style="color:var(--color-status-warning);"><?php echo $ingredientsLow; ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Bugünkü Fire</span></div>
        <div class="q-stat__value" style="color:var(--color-status-danger);"><?php echo htmlspecialchars($money($wasteTodayCost)); ?></div>
        <div class="q-hint"><?php echo $wasteTodayCount; ?> kayıt</div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">7 Günlük Alım</span></div>
        <div class="q-stat__value" style="color:var(--color-status-success);"><?php echo htmlspecialchars($money($purchaseWeekCost)); ?></div>
        <div class="q-hint"><?php echo $purchaseWeekCount; ?> irsaliye</div>
      </div>
    </section>

    <?php if ($ingredientsBlocked > 0): ?>
      <div class="q-card q-card--pad" role="alert" style="margin-top:var(--space-5);border-color:#fecaca;background:var(--color-status-danger-bg);display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);flex-wrap:wrap;">
        <div>
          <div style="font-family:var(--font-display);font-weight:800;color:#b91c1c;"><?php echo $ingredientsBlocked; ?> ürün otomatik satışa kapatıldı</div>
          <div class="q-hint" style="color:#ef4444;">Stok eşiği nedeniyle düşük stok dispatcher tarafından kapatıldı.</div>
        </div>
        <a href="<?php echo $baseUrl; ?>/business/low-stock" class="q-btn q-btn--danger q-btn--sm">İncele</a>
      </div>
    <?php endif; ?>

    <h2 class="q-section-title" style="margin-top:var(--space-6);">Hızlı Erişim</h2>
    <div class="q-grid q-grid--4">
      <?php foreach ($links as $l): ?>
        <a href="<?php echo $baseUrl . htmlspecialchars($l['href']); ?>" class="q-card q-card--pad q-card--hover" style="text-decoration:none;display:block;">
          <div style="font-size:1.75rem;line-height:1;" aria-hidden="true"><?php echo $l['icon']; ?></div>
          <div style="margin-top:var(--space-2);font-family:var(--font-display);font-weight:800;color:var(--color-ink);"><?php echo htmlspecialchars($l['title']); ?></div>
        </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>
