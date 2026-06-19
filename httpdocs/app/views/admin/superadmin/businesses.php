<?php /** Superadmin businesses — Warm Ember Ops (.q-*) */ ?>
<div class="q-page animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Platform</p>
        <h1 class="q-page-header__title">Tüm İşletmeler</h1>
        <p class="q-page-header__subtitle">Sistemdeki tüm işletmeleri ve aboneliklerini görüntüleyin</p>
      </div>
      <div class="q-page-header__actions">
        <button type="button" class="q-btn q-btn--primary">Yeni Ekle</button>
      </div>
    </header>

    <section class="q-card q-card--pad" style="padding:0;overflow:hidden;">
      <div style="padding:var(--space-5);display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);flex-wrap:wrap;border-bottom:1px solid var(--color-border-1);">
        <h2 class="q-card__title" style="margin:0;">İşletme Listesi</h2>
        <input type="search" placeholder="İşletme ara..." class="q-input" style="max-width:220px;">
      </div>

      <div style="overflow-x:auto;">
        <table class="q-table">
          <thead>
            <tr>
              <th scope="col">İşletme</th>
              <th scope="col">Sahibi</th>
              <th scope="col">Konum</th>
              <th scope="col">Paket</th>
              <th scope="col">Abonelik Tarihi</th>
              <th scope="col">Durum</th>
              <th scope="col">Gelir</th>
              <th scope="col" style="text-align:right;">İşlemler</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($businesses ?? [] as $business): ?>
            <tr>
              <td style="font-weight:var(--font-weight-bold);"><?php echo htmlspecialchars($business['name'] ?? 'Bilinmeyen'); ?></td>
              <td><?php echo htmlspecialchars($business['owner_name'] ?? 'Bilinmeyen'); ?></td>
              <td class="q-hint" style="margin:0;"><?php echo htmlspecialchars($business['location'] ?? ''); ?></td>
              <td><span class="q-badge q-badge--info"><?php echo htmlspecialchars($business['package_name'] ?? 'Bilinmeyen'); ?></span></td>
              <td><?php echo htmlspecialchars($business['subscription_date'] ?? ''); ?></td>
              <td><span class="q-badge q-badge--success">Aktif</span></td>
              <td style="font-weight:var(--font-weight-bold);"><?php echo number_format($business['revenue'] ?? 0, 2); ?> ₺</td>
              <td style="text-align:right;">
                <div style="display:flex;gap:var(--space-2);justify-content:flex-end;">
                  <button type="button" class="q-btn q-btn--ghost q-btn--sm">Görüntüle</button>
                  <button type="button" class="q-btn q-btn--soft q-btn--sm">Düzenle</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="q-card q-card--pad" style="margin-top:var(--space-6);">
      <h2 class="q-section-title">Abonelikler</h2>
      <div class="q-grid q-grid--3" style="margin-top:var(--space-4);">
        <?php foreach ($subscriptions ?? [] as $subscription): ?>
        <div class="q-card q-card--pad" style="background:var(--color-surface-2);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-2);">
            <div>
              <h3 style="font-family:var(--font-display);font-weight:var(--font-weight-bold);margin:0;"><?php echo htmlspecialchars($subscription['business_name'] ?? 'Bilinmeyen'); ?></h3>
              <p class="q-hint"><?php echo htmlspecialchars($subscription['package_name'] ?? 'Bilinmeyen'); ?></p>
            </div>
            <span class="q-badge q-badge--success"><?php echo htmlspecialchars($subscription['status'] ?? 'Aktif'); ?></span>
          </div>
          <div style="margin-top:var(--space-3);padding-top:var(--space-3);border-top:1px solid var(--color-border-1);font-size:var(--font-size-sm);">
            <div style="display:flex;justify-content:space-between;"><span class="q-hint" style="margin:0;">Başlangıç</span><span style="font-weight:var(--font-weight-bold);"><?php echo htmlspecialchars($subscription['start_date'] ?? ''); ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-top:var(--space-1);"><span class="q-hint" style="margin:0;">Bitiş</span><span style="font-weight:var(--font-weight-bold);"><?php echo htmlspecialchars($subscription['end_date'] ?? ''); ?></span></div>
            <div style="display:flex;justify-content:space-between;margin-top:var(--space-1);"><span class="q-hint" style="margin:0;">Fiyat</span><span style="font-weight:var(--font-weight-bold);"><?php echo number_format($subscription['price'] ?? 0, 2); ?> ₺</span></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

  </div>
</div>
