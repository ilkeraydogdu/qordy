<?php
/**
 * Super Admin - Analytics — Warm Ember Ops (.q-*)
 */
require_once __DIR__ . '/../../helpers/translations.php';

$monthlyRevenue = $monthlyRevenue ?? [];
$customerGrowth = $customerGrowth ?? [];
$churnRate = $churnRate ?? 0;
$mrr = $mrr ?? 0;

$planned = [
    'Aylık ve yıllık gelir grafikleri (MRR, ARR)',
    'Müşteri büyüme trendi',
    'Paket popülarite analizi',
    'Churn rate (iptal oranı) takibi',
    'Dönüşüm oranı analizi',
];
?>
<div class="q-page animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Platform</p>
        <h1 class="q-page-header__title">Genel Analitik</h1>
        <p class="q-page-header__subtitle">Platform performansını ve büyümeyi takip edin.</p>
      </div>
    </header>

    <section class="q-card q-card--pad q-empty" style="padding:var(--space-12);">
      <div class="q-empty__icon" aria-hidden="true" style="width:72px;height:72px;border-radius:50%;background:var(--color-status-info-bg);display:flex;align-items:center;justify-content:center;">
        <svg width="36" height="36" fill="none" stroke="var(--color-status-info)" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
      </div>
      <h2 class="q-empty__title" style="font-size:var(--font-size-xl);margin-top:var(--space-4);">Yakında!</h2>
      <p style="margin-top:var(--space-2);color:var(--color-text-secondary);">Detaylı analitik ve grafikler yakında eklenecektir.</p>

      <div class="q-card q-card--pad" style="margin-top:var(--space-6);max-width:36rem;margin-inline:auto;text-align:left;background:var(--color-surface-2);">
        <h3 class="q-section-title" style="margin-bottom:var(--space-3);">Planlanan Özellikler</h3>
        <ul class="q-stack" style="list-style:none;padding:0;margin:0;">
          <?php foreach ($planned as $item): ?>
          <li style="display:flex;align-items:flex-start;gap:var(--space-2);color:var(--color-text-primary);">
            <svg width="20" height="20" fill="none" stroke="var(--color-status-success)" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:2px;" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span><?php echo htmlspecialchars($item); ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

  </div>
</div>
