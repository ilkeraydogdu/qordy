<?php
/**
 * Recent Orders Table Partial
 */

namespace App\Views\BusinessAdmin;

class RecentOrdersTablePartial {
 public static function render(array $props = []): string {
 $orders = $props['orders'] ?? [];
 $limit = $props['limit'] ?? 5;
 $baseUrl = BASE_URL ?? '';
 $currency = '₺';

 $label = function_exists('getOrderStatusLabel') ? 'getOrderStatusLabel' : function($status) use ($limit, $orders) {
 $labels = [
 'pending' => 'Beklemede',
 'preparing' => 'Hazırlanıyor',
 'ready' => 'Hazırlandı',
 'served' => 'Teslim Edildi',
 'cancelled' => 'İptal Edildi',
 'completed' => 'Tamamlandı'
 ];
 return $labels[$status] ?? $status;
 };

 if (!empty($orders)) {
 // Ensure Badge component is available
 require_once __DIR__ . '/../../layouts/components/Badge.php';
 $html = <<<HTML
 <section class="q-card q-card--glass q-card--pad q-card--elevated stagger-item" aria-label="Son siparişler tablosu">
 <div class="q-card__header">
 <h2 class="q-card__title">Son Siparişler</h2>
 <a href="{$baseUrl}/business/operations" class="q-btn q-btn--ghost q-btn--sm">
 Tümünü Gör →
 </a>
 </div>
 <div class="overflow-x-auto">
 <table class="q-table q-table-row-hover-amber">
 <thead>
 <tr>
 <th>Sipariş #</th>
 <th>Müşteri</th>
 <th>Tutar</th>
 <th>Durum</th>
 <th>Tarih</th>
 </tr>
 </thead>
 <tbody>
HTML;

 foreach (array_slice($orders, 0, $limit) as $order) {
 $statusClass = match(strtolower($order['status'] ?? 'pending')) {
 'completed', 'served' => 'success',
 'cancelled' => 'error',
 'preparing' => 'warning',
 'ready' => 'info',
 'pending' => 'warning',
 default => 'default'
 };

 $formattedTotal = number_format($order['total_amount'] ?? 0, 2);
 $badgeHtml = \App\Views\Components\Badge::render(['text' => $label($order['status'] ?? 'pending'), 'type' => $statusClass]);
 $createdAt = $order['created_at'] ?? date('Y-m-d H:i:s');
 $formattedDate = date('d.m.Y H:i', strtotime($createdAt));
 $orderId = $order['order_id'] ?? 'N/A';
 $customerName = $order['customer_name']
 ?? $order['staff_or_creator']
 ?? $order['staff_name']
 ?? null;
 if (empty($customerName) || $customerName === '' || $customerName === null) {
 $firstItem = $order['first_item_name'] ?? null;
 $itemCount = (int)($order['item_count'] ?? 0);
 if (!empty($firstItem)) {
 $customerName = ($itemCount > 1)
 ? htmlspecialchars($firstItem, ENT_QUOTES, 'UTF-8') . ' +' . ($itemCount - 1)
 : htmlspecialchars($firstItem, ENT_QUOTES, 'UTF-8');
 } else {
 $tableLabel = $order['table_name'] ?? '';
 $tableId = $order['table_id'] ?? '';
 $customerName = !empty($tableLabel) && $tableLabel !== 'TEST'
 ? $tableLabel
 : (!empty($tableId) ? ('Masa ' . substr((string)$tableId, -3)) : 'Misafir');
 }
 } else {
 $customerName = htmlspecialchars((string)$customerName, ENT_QUOTES, 'UTF-8');
 }

 $html .= <<<HTML
 <tr>
 <td><strong>#{$orderId}</strong></td>
 <td>{$customerName}</td>
 <td><strong>{$currency} {$formattedTotal}</strong></td>
 <td>{$badgeHtml}</td>
 <td>{$formattedDate}</td>
 </tr>
HTML;
 }

 $html .= <<<HTML
 </tbody>
 </table>
 </div>
 </section>
HTML;
 } else {
 $html = <<<HTML
 <section class="q-card q-card--pad q-empty">
 <svg class="q-empty__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
 </svg>
 <p class="q-empty__title">Henüz sipariş yok</p>
 <p class="q-hint">Yeni siparişler burada görünecek</p>
 </section>
HTML;
 }

 return $html;
 }
}