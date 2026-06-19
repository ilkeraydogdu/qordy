<?php
/**
 * Operations Page - Q-System Edition
 *
 * Refactored from 24+ hardcoded Tailwind utilities to Qordy design tokens.
 * Modern: glass cards, gradient stat cards, amber hover tables, q-tab pattern.
 */
require_once __DIR__ . '/../../helpers/translations.php';

$orders = $orders ?? [];
$reservations = $reservations ?? [];
$tables = $tables ?? [];

$activeOrdersCount = count(array_filter($orders, fn($o) => in_array($o['status'] ?? 'pending', ['pending', 'preparing', 'ready'])));
$completedOrdersCount = count(array_filter($orders, fn($o) => ($o['status'] ?? 'pending') === 'completed'));
$upcomingReservationsCount = count($reservations);
?>
<div class="q-page q-biz-theme q-hero-glow" data-page="business-operations">
 <div class="q-container q-stack q-stack--lg">

 <!-- ===== HERO HEADER ===== -->
 <header class="q-card q-card--glass q-card--pad q-hero-glow stagger-item" role="banner">
 <div class="q-page-header">
 <div>
 <span class="q-page-header__eyebrow--accent">
 <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"></path></svg>
 OPERASYONLAR
 </span>
 <h1 class="q-page-header__title q-page-header__title--gradient" style="margin-top:8px;">Operasyonlar</h1>
 <p class="q-page-header__subtitle">İşletme operasyonlarınızı takip edin</p>
 </div>
 </div>
 </header>

 <!-- ===== STATS GRID (gradient + progress bar) ===== -->
 <section class="q-bento" aria-label="Operasyon istatistikleri">
 <!-- Active Orders -->
 <article class="q-card q-card--gradient q-card--pad q-card--elevated stagger-item">
 <div class="q-stat__top">
 <div>
 <p class="q-text-label">Aktif Siparişler</p>
 <p class="q-text-metric" style="margin-top:var(--space-1);"><?php echo $activeOrdersCount; ?></p>
 </div>
 <span class="q-stat__icon" style="background:var(--color-status-info-bg);color:var(--color-status-info);">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
 </span>
 </div>
 <div class="q-progress-bar"><div class="q-progress-bar__fill" style="width:66%"></div></div>
 </article>

 <!-- Completed Orders -->
 <article class="q-card q-card--gradient q-card--pad q-card--elevated stagger-item">
 <div class="q-stat__top">
 <div>
 <p class="q-text-label">Tamamlanan Siparişler</p>
 <p class="q-text-metric" style="margin-top:var(--space-1);"><?php echo $completedOrdersCount; ?></p>
 </div>
 <span class="q-stat__icon" style="background:var(--color-status-success-bg);color:var(--color-status-success);">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
 </span>
 </div>
 <div class="q-progress-bar"><div class="q-progress-bar__fill" style="width:75%"></div></div>
 </article>

 <!-- Reservations -->
 <article class="q-card q-card--gradient q-card--pad q-card--elevated stagger-item">
 <div class="q-stat__top">
 <div>
 <p class="q-text-label">Yaklaşan Randevular</p>
 <p class="q-text-metric" style="margin-top:var(--space-1);"><?php echo $upcomingReservationsCount; ?></p>
 </div>
 <span class="q-stat__icon" style="background:var(--color-amber-soft);color:var(--color-brand-accent-hover);">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
 </span>
 </div>
 <div class="q-progress-bar"><div class="q-progress-bar__fill" style="width:50%"></div></div>
 </article>
 </section>

 <!-- ===== TABS PANEL ===== -->
 <section class="q-card q-card--glass q-card--pad stagger-item" aria-label="Operasyon listeleri">
 <div class="q-tab-row q-tab-row--card" role="tablist" aria-label="Operasyon sekmeleri">
 <button type="button" class="q-tab selected" role="tab" aria-selected="true" data-tab="orders">Siparişler</button>
 <button type="button" class="q-tab" role="tab" aria-selected="false" data-tab="reservations">Randevular</button>
 <button type="button" class="q-tab" role="tab" aria-selected="false" data-tab="tables">Masalar</button>
 </div>

 <!-- Orders Tab -->
 <div id="orders-tab" class="q-tab-content">
 <?php if (!empty($orders)): ?>
 <div class="overflow-x-auto">
 <table class="q-table q-table-row-hover-amber">
 <thead>
 <tr>
 <th>Sipariş #</th>
 <th>Müşteri</th>
 <th>Tutar</th>
 <th>Durum</th>
 <th>Tarih</th>
 <th>İşlem</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($orders as $order):
 $status = strtolower($order['status'] ?? 'pending');
 $pillClass = match($status) {
 'completed', 'served' => 'q-status-pill--success',
 'cancelled' => 'q-status-pill--danger',
 'preparing' => 'q-status-pill--warning',
 'ready' => 'q-status-pill--info',
 'pending' => 'q-status-pill--pending',
 default => 'q-status-pill'
 };
 $statusLabel = function_exists('getOrderStatusLabel') ? getOrderStatusLabel($order['status'] ?? '') : ($order['status'] ?? 'pending');
 ?>
 <tr>
 <td><strong>#<?php echo htmlspecialchars($order['order_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></strong></td>
 <td><?php echo htmlspecialchars($order['customer_name'] ?? 'Misafir', ENT_QUOTES, 'UTF-8'); ?></td>
 <td><strong>₺<?php echo number_format($order['total_amount'] ?? 0, 2); ?></strong></td>
 <td><span class="q-status-pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
 <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'] ?? date('Y-m-d H:i:s'))); ?></td>
 <td><a href="#" class="q-btn q-btn--ghost q-btn--sm">Detay</a></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php else: ?>
 <div class="q-empty">
 <div class="q-empty__icon-wrapper" style="width:80px;height:80px;margin:0 auto var(--space-4);background:var(--color-surface-2);border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;">
 <svg class="w-10 h-10" style="color:var(--color-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
 </svg>
 </div>
 <p class="q-empty__title">Henüz sipariş yok</p>
 <p class="q-hint">Yeni siparişler burada görünecek</p>
 </div>
 <?php endif; ?>
 </div>

 <!-- Reservations Tab -->
 <div id="reservations-tab" class="q-tab-content" hidden>
 <?php if (!empty($reservations)): ?>
 <div class="overflow-x-auto">
 <table class="q-table q-table-row-hover-amber">
 <thead>
 <tr>
 <th>Randevu #</th>
 <th>Müşteri</th>
 <th>Tarih</th>
 <th>Saat</th>
 <th>Durum</th>
 <th>İşlem</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($reservations as $reservation): ?>
 <tr>
 <td><strong>#<?php echo htmlspecialchars($reservation['reservation_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></strong></td>
 <td><?php echo htmlspecialchars($reservation['customer_name'] ?? 'Misafir', ENT_QUOTES, 'UTF-8'); ?></td>
 <td><?php echo date('d.m.Y', strtotime($reservation['date'] ?? date('Y-m-d'))); ?></td>
 <td><?php echo date('H:i', strtotime($reservation['time'] ?? date('H:i'))); ?></td>
 <td><span class="q-status-pill q-status-pill--info"><?php echo htmlspecialchars($reservation['status'] ?? 'confirmed', ENT_QUOTES, 'UTF-8'); ?></span></td>
 <td><a href="#" class="q-btn q-btn--ghost q-btn--sm">Detay</a></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php else: ?>
 <div class="q-empty">
 <p class="q-empty__title">Yaklaşan randevu yok</p>
 <p class="q-hint">Randevular burada görünecek</p>
 </div>
 <?php endif; ?>
 </div>

 <!-- Tables Tab -->
 <div id="tables-tab" class="q-tab-content" hidden>
 <?php if (!empty($tables)): ?>
 <div class="q-bento">
 <?php foreach ($tables as $table): ?>
 <article class="q-card q-card--warm q-card--pad q-card--elevated" style="text-align:center;">
 <div class="q-quick-action__icon" style="margin:0 auto var(--space-3);">
 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
 </svg>
 </div>
 <h3 class="q-card__title" style="font-size:var(--font-size-md);"><?php echo htmlspecialchars($table['name'] ?? 'Masa', ENT_QUOTES, 'UTF-8'); ?></h3>
 <p class="q-hint"><?php echo htmlspecialchars($table['capacity'] ?? '0', ENT_QUOTES, 'UTF-8'); ?> Kişi</p>
 <span class="q-status-pill q-status-pill--success" style="margin-top:var(--space-2);">Boş</span>
 </article>
 <?php endforeach; ?>
 </div>
 <?php else: ?>
 <div class="q-empty">
 <p class="q-empty__title">Masa bilgisi yok</p>
 <p class="q-hint">Masalar burada görünecek</p>
 </div>
 <?php endif; ?>
 </div>
 </section>

 </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
 const tabButtons = document.querySelectorAll('[role="tab"][data-tab]');
 const tabContents = document.querySelectorAll('.q-tab-content');

 tabButtons.forEach(button => {
 button.addEventListener('click', () => {
 tabButtons.forEach(btn => {
 btn.classList.remove('selected');
 btn.setAttribute('aria-selected', 'false');
 });
 tabContents.forEach(content => content.setAttribute('hidden', ''));

 button.classList.add('selected');
 button.setAttribute('aria-selected', 'true');
 const tabId = button.getAttribute('data-tab');
 const target = document.getElementById(tabId + '-tab');
 if (target) target.removeAttribute('hidden');
 });
 });
});
</script>
