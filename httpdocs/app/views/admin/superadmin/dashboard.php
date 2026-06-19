<div class="q-page animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Platform</p>
        <h1 class="q-page-header__title">SAAS Yönetim Paneli</h1>
        <p class="q-page-header__subtitle">Tüm işletmelerin genel performans ve analizleri</p>
      </div>
    </header>

    <div class="q-grid q-grid--4" style="grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));">
        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Toplam İşletme</span>
                <div class="q-stat__icon" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
            </div>
            <div class="q-stat__value"><?php echo number_format($total_businesses ?? 0); ?></div>
        </div>

        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Toplam Abone</span>
                <div class="q-stat__icon" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
            </div>
            <div class="q-stat__value"><?php echo number_format($active_subscriptions ?? 0); ?></div>
        </div>

        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Toplam Gelir</span>
                <div class="q-stat__icon" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <div class="q-stat__value"><?php echo number_format($total_revenue ?? 0, 2); ?> ₺</div>
        </div>

        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Toplam Kullanıcı</span>
                <div class="q-stat__icon" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
            <div class="q-stat__value"><?php echo number_format($total_users ?? 0); ?></div>
        </div>

        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Toplam Abonelik</span>
                <div class="q-stat__icon" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                </div>
            </div>
            <div class="q-stat__value"><?php echo number_format($total_subscriptions ?? 0); ?></div>
        </div>

        <div class="q-stat">
            <div class="q-stat__top">
                <span class="q-stat__label">Aktif Kullanıcı</span>
                <div class="q-stat__icon" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
            </div>
            <div class="q-stat__value"><?php echo count($active_users ?? []); ?></div>
        </div>
    </div>

    <div class="q-grid q-grid--2">
        <section class="q-card q-card--pad q-stack">
            <h2 class="q-card__title">En Çok Satan Paketler</h2>
            <div class="q-stack q-stack--sm">
                <?php foreach ($top_packages ?? [] as $index => $package): ?>
                    <div class="q-card q-card--pad q-toolbar" style="background:var(--color-surface-1);">
                        <div class="q-toolbar gap-3 min-w-0">
                            <span class="q-badge q-badge--neutral"><?php echo $index + 1; ?></span>
                            <div class="min-w-0">
                                <p class="font-bold truncate"><?php echo htmlspecialchars($package['name'] ?? 'Bilinmeyen'); ?></p>
                                <p class="q-hint text-sm truncate"><?php echo htmlspecialchars($package['description'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-bold"><?php echo number_format($package['sales_count'] ?? 0); ?> satış</p>
                            <p class="q-hint text-sm"><?php echo number_format($package['revenue'] ?? 0, 2); ?> ₺</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="q-card q-card--pad q-stack">
            <h2 class="q-card__title">İşletmelere Göre Gelir</h2>
            <div class="q-stack q-stack--sm">
                <?php foreach ($revenue_by_business ?? [] as $business): ?>
                    <div class="q-card q-card--pad q-toolbar" style="background:var(--color-surface-1);">
                        <div class="q-toolbar gap-3 min-w-0">
                            <span class="q-badge q-badge--info"><?php echo strtoupper(substr($business['name'] ?? 'B', 0, 1)); ?></span>
                            <div class="min-w-0">
                                <p class="font-bold truncate"><?php echo htmlspecialchars($business['name'] ?? 'Bilinmeyen'); ?></p>
                                <p class="q-hint text-sm truncate"><?php echo htmlspecialchars($business['location'] ?? ''); ?></p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-bold"><?php echo number_format($business['revenue'] ?? 0, 2); ?> ₺</p>
                            <p class="q-hint text-sm"><?php echo number_format($business['order_count'] ?? 0); ?> sipariş</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <section class="q-card q-card--pad" style="padding:0;overflow:hidden;">
        <div class="q-card--pad">
            <h2 class="q-card__title">Son Siparişler</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="q-table w-full">
                <thead>
                    <tr>
                        <th class="text-left">İşletme</th>
                        <th class="text-left">Müşteri</th>
                        <th class="text-left">Tutar</th>
                        <th class="text-left">Durum</th>
                        <th class="text-left">Tarih</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders ?? [] as $order): ?>
                    <tr>
                        <td class="font-bold"><?php echo htmlspecialchars($order['business_name'] ?? 'Bilinmeyen'); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Misafir'); ?></td>
                        <td class="font-bold"><?php echo number_format($order['total_amount'] ?? 0, 2); ?> ₺</td>
                        <td>
                            <span class="q-badge q-badge--success">
                                <?php echo htmlspecialchars(function_exists('getOrderStatusLabel') ? getOrderStatusLabel($order['status'] ?? '') : ($order['status'] ?? 'Beklemede')); ?>
                            </span>
                        </td>
                        <td class="q-hint"><?php echo htmlspecialchars($order['created_at'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

  </div>
</div>
