<?php /** Superadmin system logs — Warm Ember Ops (.q-*) */ ?>
<div class="q-page animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Platform</p>
        <h1 class="q-page-header__title">Sistem Logları</h1>
        <p class="q-page-header__subtitle">Sistemdeki tüm log dosyalarını ve içeriklerini görüntüleyin</p>
      </div>
    </header>

    <section class="q-card q-card--pad q-stack">
      <div class="q-card__header" style="padding:0;margin-bottom:var(--space-4);">
        <h2 class="q-card__title">Log Dosyaları</h2>
        <div style="display:flex;gap:var(--space-2);flex-wrap:wrap;">
          <input type="search" placeholder="Log dosyası ara..." class="q-input" style="max-width:220px;">
          <button type="button" class="q-btn q-btn--ink q-btn--sm">Yenile</button>
        </div>
      </div>

      <div style="overflow-x:auto;">
        <table class="q-table">
          <thead>
            <tr>
              <th scope="col">Dosya Adı</th>
              <th scope="col">Boyut</th>
              <th scope="col">Değiştirilme Tarihi</th>
              <th scope="col" style="text-align:right;">İşlemler</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs ?? [] as $fileName => $logInfo): ?>
            <tr>
              <td style="font-weight:var(--font-weight-bold);"><?php echo htmlspecialchars($fileName); ?></td>
              <td><?php echo number_format(($logInfo['size'] ?? 0) / 1024, 2); ?> KB</td>
              <td class="q-hint" style="margin:0;"><?php echo htmlspecialchars($logInfo['modified'] ?? ''); ?></td>
              <td style="text-align:right;">
                <div style="display:flex;gap:var(--space-2);justify-content:flex-end;flex-wrap:wrap;">
                  <button type="button" class="q-btn q-btn--ink q-btn--sm">Görüntüle</button>
                  <button type="button" class="q-btn q-btn--soft q-btn--sm">İndir</button>
                  <button type="button" class="q-btn q-btn--danger q-btn--sm">Temizle</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="q-card q-card--pad q-stack" style="margin-top:var(--space-6);">
      <h2 class="q-section-title">Son Loglar</h2>
      <div style="background:var(--color-surface-2);border-radius:var(--radius-md);padding:var(--space-4);font-family:ui-monospace,monospace;font-size:var(--font-size-sm);max-height:24rem;overflow-y:auto;">
        <p class="q-hint" style="margin:0;">Log içeriği burada görüntülenecek...</p>
      </div>
    </section>

    <section class="q-card q-card--pad" style="margin-top:var(--space-6);">
      <h2 class="q-section-title">Sistem Durumu</h2>
      <div class="q-grid q-grid--4" style="margin-top:var(--space-4);">
        <div class="q-stat">
          <div class="q-stat__top"><span class="q-stat__label">Sunucu Durumu</span><span class="q-badge q-badge--live"><span class="q-badge__dot"></span>Canlı</span></div>
          <div class="q-stat__value" style="font-size:var(--font-size-lg);color:var(--color-status-success);">Çalışıyor</div>
        </div>
        <div class="q-stat">
          <div class="q-stat__top"><span class="q-stat__label">Veritabanı</span></div>
          <div class="q-stat__value" style="font-size:var(--font-size-lg);color:var(--color-status-info);">Bağlı</div>
        </div>
        <div class="q-stat">
          <div class="q-stat__top"><span class="q-stat__label">PHP Sürümü</span></div>
          <div class="q-stat__value" style="font-size:var(--font-size-lg);"><?php echo phpversion(); ?></div>
        </div>
        <div class="q-stat">
          <div class="q-stat__top"><span class="q-stat__label">Uygulama Sürümü</span></div>
          <div class="q-stat__value" style="font-size:var(--font-size-lg);">1.0.0</div>
        </div>
      </div>
    </section>

  </div>
</div>
