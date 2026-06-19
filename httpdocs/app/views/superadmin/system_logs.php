<?php
/**
 * Super Admin - System Logs — Warm Ember Ops (.q-*)
 */
require_once __DIR__ . '/../../helpers/translations.php';

$logs = $logs ?? [];
$selectedLog = $selectedLog ?? '';
$logContent = $logContent ?? '';
?>
<div class="q-page animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Platform</p>
        <h1 class="q-page-header__title">Sistem Logları</h1>
        <p class="q-page-header__subtitle">Uygulama loglarını görüntüleyin ve analiz edin.</p>
      </div>
    </header>

    <?php if (empty($logs)): ?>
      <section class="q-card q-card--pad q-empty">
        <div class="q-empty__icon" aria-hidden="true" style="width:64px;height:64px;border-radius:50%;background:var(--color-surface-2);display:flex;align-items:center;justify-content:center;">
          <svg width="32" height="32" fill="none" stroke="var(--color-text-muted)" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h2 class="q-empty__title">Log Dosyası Bulunamadı</h2>
        <p>Henüz log kaydı oluşturulmamış.</p>
      </section>
    <?php else: ?>

      <div class="q-grid q-grid--2" style="grid-template-columns:minmax(0,1fr) minmax(0,3fr);">
        <aside class="q-card q-card--pad">
          <h2 class="q-card__title" style="margin-bottom:var(--space-4);">Log Dosyaları</h2>
          <nav class="q-stack" aria-label="Log dosyaları">
            <?php foreach ($logs as $log): ?>
              <?php $active = $selectedLog === $log['name']; ?>
              <a href="?log=<?php echo urlencode($log['name']); ?>"
                 class="q-btn q-btn--ghost"
                 style="display:block;text-align:left;width:100%;<?php echo $active ? 'border-color:var(--color-brand-accent);background:var(--color-amber-soft);' : ''; ?>">
                <div style="font-weight:var(--font-weight-bold);font-size:var(--font-size-sm);"><?php echo htmlspecialchars($log['name']); ?></div>
                <div class="q-hint" style="margin-top:4px;">
                  <?php echo number_format($log['size'] / 1024, 2); ?> KB · <?php echo date('d.m.Y H:i', $log['modified']); ?>
                </div>
              </a>
            <?php endforeach; ?>
          </nav>
        </aside>

        <section class="q-card q-card--pad">
          <?php if (empty($selectedLog)): ?>
            <div class="q-empty" style="padding:var(--space-8);">
              <p>Görüntülemek için bir log dosyası seçin.</p>
            </div>
          <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);flex-wrap:wrap;margin-bottom:var(--space-4);">
              <h2 class="q-card__title" style="margin:0;"><?php echo htmlspecialchars($selectedLog); ?></h2>
              <button type="button" class="q-btn q-btn--soft q-btn--sm"
                      onclick="document.getElementById('logContent').scrollTop = document.getElementById('logContent').scrollHeight">
                En Alta Git
              </button>
            </div>

            <div id="logContent"
                 style="background:var(--color-ink);color:var(--color-brand-lime);padding:var(--space-4);border-radius:var(--radius-md);font-family:ui-monospace,monospace;font-size:var(--font-size-xs);overflow:auto;max-height:600px;">
              <?php if (empty($logContent)): ?>
                <div style="color:var(--color-text-muted);">Log dosyası boş.</div>
              <?php else: ?>
                <pre style="margin:0;white-space:pre-wrap;word-break:break-word;"><?php echo htmlspecialchars($logContent); ?></pre>
              <?php endif; ?>
            </div>

            <p class="q-hint" style="margin-top:var(--space-4);">Son 1000 satır gösteriliyor. Tam dosya için sunucudan indirin.</p>
          <?php endif; ?>
        </section>
      </div>

    <?php endif; ?>

  </div>
</div>
