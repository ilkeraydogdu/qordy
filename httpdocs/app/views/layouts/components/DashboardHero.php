<?php
/**
 * DashboardHero — Veri olsun olmasın güzel görünen hero kompozisyonu.
 *
 * Üst kısımda tarih filtresi + AI butonu + istatistik özeti.
 * Veri varken: gerçek sayılar + trend.
 * Veri yokken: onboarding CTA + "İlk siparişinizi bekliyoruz" mesajı.
 *
 * Kullanım:
 * echo DashboardHero::render([
 * 'has_data' => $hasData,
 * 'period_revenue' => $periodRevenue,
 * 'period_orders' => $periodOrderCount,
 * 'revenue_trend_dir' => $revenueTrendDir,
 * 'revenue_trend_label' => $revenueTrendLabel,
 * 'range_key' => $rangeKey,
 * 'start_date' => $startDate,
 * 'end_date' => $endDate,
 * ]);
 */

namespace App\Views\Components;

class DashboardHero {
 public static function render(array $props): string {
 $hasData = (bool)($props['has_data'] ?? false);
 $revenue = (float)($props['period_revenue'] ?? 0);
 $orders = (int)($props['period_orders'] ?? 0);
 $profit = (float)($props['period_profit'] ?? $props['estimated_profit'] ?? 0);
 $avgOrder = (float)($props['period_avg_order_value'] ?? $props['avg_order_value'] ?? 0);
 $trendDir = $props['revenue_trend_dir'] ?? 'flat';
 $trendLabel = (string)($props['revenue_trend_label'] ?? '0%');
 $rangeKey = $props['range_key'] ?? 'today';
 $startDate = htmlspecialchars((string)($props['start_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8');
 $endDate = htmlspecialchars((string)($props['end_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8');
 $rangeLabel = self::rangeLabel($rangeKey);
 $businessName = htmlspecialchars((string)($props['business_name'] ?? ''), ENT_QUOTES, 'UTF-8');

 $trendColor = $trendDir === 'up'
 ? 'bg-emerald-100 text-emerald-700'
 : ($trendDir === 'down' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600');
 $trendIcon = $trendDir === 'up' ? 'trending-up' : ($trendDir === 'down' ? 'trending-down' : 'minus');
 $trendIconHtml = Icons::svg($trendIcon, 'w-3 h-3 mr-1 inline');

 if ($hasData) {
 $body = sprintf(
 '<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">'
 . '<div class="min-w-0">'
 . '<div class="flex items-center gap-2 mb-2">'
 . '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold %s">%s<span>%s</span></span>'
 . '<span class="text-xs text-slate-500 font-medium">%s</span>'
 . '</div>'
 . '<div class="flex items-baseline gap-2">'
 . '<span class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">%s</span>'
 . '<span class="text-sm text-slate-500 font-bold">·</span>'
 . '<span class="text-sm text-slate-600 font-semibold">%d sipariş</span>'
 . '</div>'
 . '<div class="flex items-center gap-4 mt-2 text-xs text-slate-600">'
 . '<span class="inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>Kar: %s</span>'
 . '<span class="inline-flex items-center"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>Ort. Sepet: %s</span>'
 . '</div>'
 . '</div>'
 . '<div class="text-xs text-slate-500 font-medium whitespace-nowrap">%s → %s</div>'
 . '</div>',
 $trendColor,
 $trendIconHtml,
 $trendLabel,
 htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'),
 htmlspecialchars(number_format($revenue, 2, ',', '.') . ' ₺', ENT_QUOTES, 'UTF-8'),
 $orders,
 htmlspecialchars(number_format($profit, 2, ',', '.') . ' ₺', ENT_QUOTES, 'UTF-8'),
 htmlspecialchars(number_format($avgOrder, 2, ',', '.') . ' ₺', ENT_QUOTES, 'UTF-8'),
 $startDate,
 $endDate
 );
 } else {
 $body = sprintf(
 '<div class="text-center py-6 sm:py-8">'
 . '<div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-100 to-amber-50 mb-4">%s</div>'
 . '<h2 class="text-lg sm:text-xl font-black text-slate-900 mb-1">%s</h2>'
 . '<p class="text-sm text-slate-600 max-w-md mx-auto mb-5">%s</p>'
 . '<div class="flex flex-wrap items-center justify-center gap-2">'
 . '<a href="%s" class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold shadow-md hover:bg-slate-800 transition">%s<span>QR Menü Oluştur</span></a>'
 . '<a href="%s" class="inline-flex items-center px-4 py-2 rounded-xl bg-white text-slate-700 border border-slate-200 text-xs font-bold hover:bg-slate-50 transition">%s<span>Masa Ekle</span></a>'
 . '</div>'
 . '</div>',
 Icons::svg('sparkles', 'w-8 h-8 text-amber-600'),
 'Hoş geldiniz! Henüz veri yok',
 'İlk sipariş geldiğinde burada gerçek zamanlı analizler göreceksiniz. Şimdi başlamak için QR menü oluşturun veya masalarınızı ekleyin.',
 (defined('BASE_URL') ? BASE_URL : '') . '/business/menu',
 Icons::svg('plus', 'w-4 h-4 mr-2'),
 (defined('BASE_URL') ? BASE_URL : '') . '/business/tables',
 Icons::svg('grid', 'w-4 h-4 mr-2')
 );
 }

 return sprintf(
 '<section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-white via-amber-50/30 to-amber-100/20 border border-amber-100 shadow-sm p-5 sm:p-6 mb-4 sm:mb-5">'
 . '<div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-amber-200/20 blur-3xl pointer-events-none"></div>'
 . '<div class="relative">%s</div>'
 . '</section>',
 $body
 );
 }

 private static function rangeLabel(string $key): string {
 $map = [
 'today' => 'Bugün',
 'week' => 'Bu Hafta',
 'month' => 'Bu Ay',
 '3months' => 'Son 3 Ay',
 '6months' => 'Son 6 Ay',
 '9months' => 'Son 9 Ay',
 'year' => 'Bu Yıl',
 'custom' => 'Özel Aralık',
 ];
 return $map[$key] ?? $key;
 }
}
