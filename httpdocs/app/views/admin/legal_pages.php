<?php
/**
 * Legal pages list — Warm Ember Ops (.q-*)
 */
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$pages = $pages ?? [];
$pageTypes = $pageTypes ?? [];

$activeCount = count(array_filter($pages, fn($p) => !empty($p['is_active'])));
$footerCount = count(array_filter($pages, fn($p) => !empty($p['show_in_footer'])));
$registerCount = count(array_filter($pages, fn($p) => !empty($p['show_in_register'])));
?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">İçerik</p>
        <h1 class="q-page-header__title">Hukuksal Sayfalar</h1>
        <p class="q-page-header__subtitle">Sözleşme, politika ve bilgi sayfaları</p>
      </div>
      <div class="q-page-header__actions">
        <a href="<?php echo getAdminUrl('legal-pages/create'); ?>" class="q-btn q-btn--primary">+ Yeni Sayfa</a>
      </div>
    </header>

    <section class="q-grid q-grid--4" aria-label="Özet">
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Toplam</span></div>
        <div class="q-stat__value"><?php echo count($pages); ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Aktif</span></div>
        <div class="q-stat__value" style="color:var(--color-status-success);"><?php echo $activeCount; ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Footer'da</span></div>
        <div class="q-stat__value" style="color:var(--color-status-info);"><?php echo $footerCount; ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Kayıt Sayfasında</span></div>
        <div class="q-stat__value"><?php echo $registerCount; ?></div>
      </div>
    </section>

    <section class="q-card q-card--pad" style="margin-top:var(--space-6);padding:0;overflow:hidden;">
      <?php if (empty($pages)): ?>
        <div class="q-empty">
          <div class="q-empty__icon" aria-hidden="true" style="width:56px;height:56px;border-radius:50%;background:var(--color-surface-2);display:flex;align-items:center;justify-content:center;">
            <svg width="28" height="28" fill="none" stroke="var(--color-text-muted)" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <h2 class="q-empty__title">Henüz sayfa oluşturulmamış</h2>
          <a href="<?php echo getAdminUrl('legal-pages/create'); ?>" class="q-btn q-btn--primary" style="margin-top:var(--space-4);">İlk Sayfayı Oluştur</a>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="q-table">
            <thead>
              <tr>
                <th scope="col">Sayfa</th>
                <th scope="col" class="q-sr-only sm:not-sr-only" style="display:none;">Tür</th>
                <th scope="col">Konum</th>
                <th scope="col">Durum</th>
                <th scope="col">Sıra</th>
                <th scope="col" style="text-align:right;">İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pages as $p): ?>
              <tr>
                <td>
                  <div style="font-weight:var(--font-weight-bold);"><?php echo htmlspecialchars($p['title']); ?></div>
                  <div class="q-hint">/sayfa/<?php echo htmlspecialchars($p['slug']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($pageTypes[$p['page_type']] ?? $p['page_type']); ?></td>
                <td>
                  <div style="display:flex;gap:var(--space-1);flex-wrap:wrap;">
                    <?php if (!empty($p['show_in_footer'])): ?><span class="q-badge q-badge--info">Footer</span><?php endif; ?>
                    <?php if (!empty($p['show_in_register'])): ?><span class="q-badge q-badge--neutral">Kayıt</span><?php endif; ?>
                  </div>
                </td>
                <td>
                  <button type="button" onclick="togglePage(<?php echo (int)$p['id']; ?>)"
                          class="q-badge <?php echo !empty($p['is_active']) ? 'q-badge--success' : 'q-badge--danger'; ?>"
                          style="cursor:pointer;border:0;">
                    <?php echo !empty($p['is_active']) ? 'Aktif' : 'Pasif'; ?>
                  </button>
                </td>
                <td><?php echo (int)$p['display_order']; ?></td>
                <td style="text-align:right;">
                  <div style="display:flex;align-items:center;justify-content:flex-end;gap:var(--space-2);flex-wrap:wrap;">
                    <a href="<?php echo BASE_URL; ?>/sayfa/<?php echo htmlspecialchars($p['slug']); ?>" target="_blank" rel="noopener" class="q-btn q-btn--ghost q-btn--sm">Önizle</a>
                    <a href="<?php echo getAdminUrl('legal-pages/' . $p['id'] . '/edit'); ?>" class="q-btn q-btn--soft q-btn--sm">Düzenle</a>
                    <button type="button" onclick="deletePage(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['title'])); ?>')" class="q-btn q-btn--danger q-btn--sm">Sil</button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  </div>
</div>

<script>
async function togglePage(id) {
    try {
        const r = await fetch(`<?php echo rtrim(BASE_URL, '/'); ?>/api/qodmin/legal-pages/${id}/toggle`, {
            method: 'POST',
            headers: {'X-CSRF-Token': window.CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest'}
        });
        const d = await r.json();
        if (d.success) { location.reload(); }
        else if (window.NotificationManager) window.NotificationManager.error(d.message || 'Hata');
    } catch(e) { if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası'); }
}

async function deletePage(id, title) {
    if (!confirm(`"${title}" sayfasını silmek istediğinize emin misiniz?`)) return;
    try {
        const r = await fetch(`<?php echo rtrim(BASE_URL, '/'); ?>/api/qodmin/legal-pages/${id}/delete`, {
            method: 'POST',
            headers: {'X-CSRF-Token': window.CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest'}
        });
        const d = await r.json();
        if (d.success) {
            if (window.NotificationManager) window.NotificationManager.success('Sayfa silindi');
            location.reload();
        } else if (window.NotificationManager) window.NotificationManager.error(d.message || 'Hata');
    } catch(e) { if (window.NotificationManager) window.NotificationManager.error('Bağlantı hatası'); }
}
</script>
