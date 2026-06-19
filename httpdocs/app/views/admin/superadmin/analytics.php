<?php /** Superadmin analytics — Warm Ember Ops (.q-*) */ ?>
<div class="q-page animate-slide-up">
  <div class="q-container">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Platform</p>
        <h1 class="q-page-header__title">Genel Analitik</h1>
        <p class="q-page-header__subtitle">Tüm işletmelerin performans ve analiz verileri</p>
      </div>
    </header>

    <section class="q-grid q-grid--4" aria-label="Özet göstergeler">
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Aylık Gelir</span></div>
        <div class="q-stat__value"><?php echo number_format($monthly_revenue['current_month'] ?? 0, 2); ?> ₺</div>
        <div class="q-stat__delta q-stat__delta--up">+<?php echo number_format($monthly_revenue['growth_rate'] ?? 0, 2); ?>%</div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Paket Satışları</span></div>
        <div class="q-stat__value"><?php echo number_format($package_analytics['total_sales'] ?? 0); ?></div>
        <div class="q-stat__delta q-stat__delta--up">+<?php echo number_format($package_analytics['growth_rate'] ?? 0, 2); ?>%</div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">İşletme Performansı</span></div>
        <div class="q-stat__value"><?php echo number_format($business_performance['top_performer_score'] ?? 0, 2); ?></div>
        <div class="q-hint"><?php echo htmlspecialchars($business_performance['top_performer_name'] ?? 'Bilinmeyen'); ?></div>
      </div>
      <div class="q-stat">
        <div class="q-stat__top"><span class="q-stat__label">Kullanıcı Artışı</span></div>
        <div class="q-stat__value">+<?php echo number_format($user_growth['monthly_growth'] ?? 0); ?></div>
        <div class="q-stat__delta q-stat__delta--up">+<?php echo number_format($user_growth['growth_rate'] ?? 0, 2); ?>%</div>
      </div>
    </section>

    <div class="q-grid q-grid--2" style="margin-top:var(--space-6);">
      <section class="q-card q-card--pad">
        <h2 class="q-section-title">Aylık Gelir Grafiği</h2>
        <div style="height:16rem;display:flex;align-items:center;justify-content:center;background:var(--color-surface-2);border-radius:var(--radius-md);margin-top:var(--space-4);">
          <p class="q-hint" style="margin:0;">Grafik verileri yüklenecek</p>
        </div>
      </section>
      <section class="q-card q-card--pad">
        <h2 class="q-section-title">Paket Satış Dağılımı</h2>
        <div style="height:16rem;display:flex;align-items:center;justify-content:center;background:var(--color-surface-2);border-radius:var(--radius-md);margin-top:var(--space-4);">
          <p class="q-hint" style="margin:0;">Grafik verileri yüklenecek</p>
        </div>
      </section>
    </div>

    <div class="q-grid q-grid--2" style="margin-top:var(--space-6);">
      <section class="q-card q-card--pad">
        <h2 class="q-section-title">İşletme Performansı</h2>
        <div class="q-stack" style="margin-top:var(--space-4);">
          <?php foreach ($business_performance['rankings'] ?? [] as $index => $business): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);padding:var(--space-3);background:var(--color-surface-2);border-radius:var(--radius-md);">
            <div style="display:flex;align-items:center;gap:var(--space-3);">
              <span style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:var(--color-ink);color:#fff;border-radius:var(--radius-sm);font-weight:var(--font-weight-black);font-size:var(--font-size-sm);"><?php echo $index + 1; ?></span>
              <div>
                <p style="font-weight:var(--font-weight-bold);margin:0;"><?php echo htmlspecialchars($business['name'] ?? 'Bilinmeyen'); ?></p>
                <p class="q-hint" style="margin:0;"><?php echo htmlspecialchars($business['location'] ?? ''); ?></p>
              </div>
            </div>
            <div style="text-align:right;">
              <p style="font-weight:var(--font-weight-black);margin:0;"><?php echo number_format($business['score'] ?? 0, 2); ?> puan</p>
              <p class="q-hint" style="margin:0;"><?php echo number_format($business['revenue'] ?? 0, 2); ?> ₺</p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="q-card q-card--pad">
        <h2 class="q-section-title">Kullanıcı Artışı</h2>
        <div class="q-stack" style="margin-top:var(--space-4);">
          <?php
          $growthBlocks = [
            ['Toplam Kullanıcı', number_format($user_growth['total_users'] ?? 0), min(100, ($user_growth['total_users'] ?? 0) / 1000 * 100), 'var(--color-status-info)'],
            ['Bu Ayki Yeni Kullanıcı', '+' . number_format($user_growth['monthly_new'] ?? 0), min(100, ($user_growth['monthly_new'] ?? 0) / 100 * 100), 'var(--color-status-success)'],
            ['Aktif Kullanıcı Oranı', number_format($user_growth['active_ratio'] ?? 0, 2) . '%', min(100, ($user_growth['active_ratio'] ?? 0)), 'var(--color-brand-accent)'],
          ];
          foreach ($growthBlocks as [$label, $val, $pct, $color]):
          ?>
          <div style="padding:var(--space-4);background:var(--color-surface-2);border-radius:var(--radius-md);">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <p style="font-weight:var(--font-weight-bold);margin:0;"><?php echo htmlspecialchars($label); ?></p>
              <p style="font-size:var(--font-size-xl);font-weight:var(--font-weight-black);margin:0;color:<?php echo $color; ?>;"><?php echo $val; ?></p>
            </div>
            <div style="margin-top:var(--space-2);width:100%;background:var(--color-border-1);border-radius:999px;height:8px;">
              <div style="height:8px;border-radius:999px;background:<?php echo $color; ?>;width:<?php echo (float)$pct; ?>%;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

  </div>
</div>
