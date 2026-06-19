<?php
/**
 * Stats Cards Partial
 */
namespace App\Views\BusinessAdmin;

class StatsCardsPartial {
 public static function render(array $data = []): string {
 $orders = $data['orders'] ?? 0;
 $revenue = $data['revenue'] ?? 0;
 $staff = $data['staff'] ?? 0;
 $activeOrders = $data['active_orders'] ?? 0;
 $currency = '₺';

 $cards = [
 [
 'title' => 'Toplam Sipariş',
 'value' => count($orders),
 'icon' => 'orders',
 'color' => 'approval'
 ],
 [
 'title' => 'Toplam Gelir',
 'value' => $currency . number_format($revenue, 2),
 'icon' => 'wallet',
 'color' => 'profit'
 ],
 [
 'title' => 'Personel Sayısı',
 'value' => $staff,
 'icon' => 'users',
 'color' => 'volume'
 ],
 [
 'title' => 'Aktif Sipariş',
 'value' => $activeOrders,
 'icon' => 'cart',
 'color' => 'pending'
 ]
 ];

 // Use component
 require_once __DIR__ . '/../../layouts/components/StatCard.php';
 $html = '<div class="q-grid q-grid--4" style="margin-bottom:var(--space-6);">';
 foreach ($cards as $card) {
 $html .= \App\Views\Components\StatCard::render($card);
 }
 $html .= '</div>';
 return $html;
 }
}