<?php
/**
 * DateRangeFilter — Tarih aralığı seçim butonları.
 *
 * Dashboard'da "Bugün / Bu Hafta / Bu Ay / Son 3 Ay / Son 6 Ay / Son 9 Ay / Bu Yıl" butonları.
 * Tıklandığında sayfayı ?range=... ile yeniden yükler.
 * AJAX ile veri günceller (js-dashboard-period-reload).
 *
 * Kullanım:
 * echo DateRangeFilter::render([
 * 'current' => $rangeKey ?? 'today',
 * 'base_url' => '/admin/dashboard',
 * 'mode' => 'page', // 'page' (PHP reload) | 'ajax' (JS callback)
 * 'container_id' => 'dashboard-kpis', // ajax modda DOM container
 * ]);
 */

namespace App\Views\Components;

class DateRangeFilter {
 public static function render(array $props): string {
 $current = htmlspecialchars((string)($props['current'] ?? 'today'), ENT_QUOTES, 'UTF-8');
 $baseUrl = htmlspecialchars((string)($props['base_url'] ?? '/business/dashboard'), ENT_QUOTES, 'UTF-8');
 $mode = $props['mode'] ?? 'page';
 $containerId = htmlspecialchars((string)($props['container_id'] ?? 'dashboard-root'), ENT_QUOTES, 'UTF-8');

 $ranges = [
 'today' => ['label' => 'Bugün', 'icon' => 'clock'],
 'week' => ['label' => 'Bu Hafta', 'icon' => 'calendar'],
 'month' => ['label' => 'Bu Ay', 'icon' => 'calendar'],
 '3months' => ['label' => 'Son 3 Ay', 'icon' => 'chart-pie'],
 '6months' => ['label' => 'Son 6 Ay', 'icon' => 'chart-pie'],
 '9months' => ['label' => 'Son 9 Ay', 'icon' => 'chart-pie'],
 'year' => ['label' => 'Bu Yıl', 'icon' => 'presentation'],
 ];

 $items = '';
 foreach ($ranges as $key => $r) {
 $active = ($key === $current);
 $variant = $active ? 'q-btn--primary' : 'q-btn--ghost';
 $url = rtrim($baseUrl, '/') . '/' . urlencode($key);
 $iconHtml = Icons::svg($r['icon'], 'q-range-filter__icon');
 $dataAttr = ($mode === 'ajax')
 ? 'data-range="' . $key . '" data-ajax-range="1"'
 : 'data-range="' . $key . '" data-page-range="1" data-page-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
 $items .= sprintf(
 '<button type="button" %s class="q-btn q-btn--sm %s" aria-pressed="%s">%s<span>%s</span></button>',
 $dataAttr,
 $variant,
 $active ? 'true' : 'false',
 $iconHtml,
 htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8')
 );
 }

 $containerAttr = $mode === 'ajax' ? ' data-ajax-mode="1" data-target="' . $containerId . '"' : '';

 return sprintf(
 '<div class="q-range-filter"%s role="group" aria-label="Tarih aralığı filtresi" data-date-range-filter data-current="%s">%s</div>',
 $containerAttr,
 $current,
 $items
 );
 }
}
