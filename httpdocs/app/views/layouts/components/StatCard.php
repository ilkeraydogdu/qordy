<?php
/**
 * StatCard (KPI Card) - Reusable Stats Card Component (.q-stat design system)
 */
namespace App\Views\Components;

class StatCard {
 private const COLOR_ROLES = [
 'revenue' => ['icon_bg' => 'var(--color-amber-soft)', 'icon_fg' => 'var(--color-brand-accent-hover)'],
 'occupancy' => ['icon_bg' => 'var(--color-status-info-bg)', 'icon_fg' => 'var(--color-status-info)'],
 'pending' => ['icon_bg' => 'var(--color-status-warning-bg)', 'icon_fg' => 'var(--color-status-warning)'],
 'profit' => ['icon_bg' => 'var(--color-status-success-bg)', 'icon_fg' => 'var(--color-status-success)'],
 'volume' => ['icon_bg' => 'var(--color-status-info-bg)', 'icon_fg' => 'var(--color-status-info)'],
 'average' => ['icon_bg' => 'var(--color-status-danger-bg)', 'icon_fg' => 'var(--color-status-danger)'],
 'customers' => ['icon_bg' => 'var(--color-status-info-bg)', 'icon_fg' => 'var(--color-status-info)'],
 'served' => ['icon_bg' => 'var(--color-lime-soft)', 'icon_fg' => '#4d7c0f'],
 'approval' => ['icon_bg' => 'var(--color-amber-soft)', 'icon_fg' => 'var(--color-brand-accent-hover)'],
 'neutral' => ['icon_bg' => 'var(--color-surface-2)', 'icon_fg' => 'var(--color-text-secondary)'],
 ];

 private const ICON_FALLBACK = [
 'wallet' => 'M3 8h18M3 8a2 2 0 012-2h14a2 2 0 012 2M3 8v10a2 2 0 002 2h14a2 2 0 002-2V8m-9 4h2',
 'bar-chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
 ];

 public static function render(array $props): string {
 $title = htmlspecialchars((string)($props['title'] ?? ''), ENT_QUOTES, 'UTF-8');
 $value = (string)($props['value'] ?? '0');
 $iconKey = $props['icon'] ?? 'bar-chart';
 $colorKey = $props['color'] ?? 'neutral';
 $subtitle = $props['subtitle'] ?? '';
 $href = $props['href'] ?? '';
 $trend = $props['trend'] ?? null;
 $highlight = !empty($props['highlight']);
 $kpiKey = htmlspecialchars((string)($props['kpi_key'] ?? ''), ENT_QUOTES, 'UTF-8');

 $role = self::COLOR_ROLES[$colorKey] ?? self::COLOR_ROLES['neutral'];

 if (\App\Views\Components\Icons::exists($iconKey)) {
 $iconHtml = \App\Views\Components\Icons::svg($iconKey, 'w-5 h-5');
 } else {
 $fallbackPath = self::ICON_FALLBACK[$iconKey] ?? self::ICON_FALLBACK['bar-chart'];
 $iconHtml = sprintf(
 '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
 . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/>'
 . '</svg>',
 $fallbackPath
 );
 }

 $iconWrap = sprintf(
 '<span class="q-stat__icon" style="background:%s;color:%s;">%s</span>',
 $role['icon_bg'],
 $role['icon_fg'],
 $iconHtml
 );

 $trendHtml = '';
 if (is_array($trend) && !empty($trend['label'])) {
 $dir = $trend['direction'] ?? 'flat';
 $deltaClass = $dir === 'up' ? 'q-stat__delta--up' : ($dir === 'down' ? 'q-stat__delta--down' : 'q-stat__delta--flat');
 $arrow = $dir === 'up' ? '↑' : ($dir === 'down' ? '↓' : '→');
 $trendHtml = sprintf(
 '<div class="q-stat__delta %s" aria-label="trend %s"><span aria-hidden="true">%s</span><span>%s</span></div>',
 $deltaClass,
 $dir,
 $arrow,
 htmlspecialchars((string)$trend['label'], ENT_QUOTES, 'UTF-8')
 );
 }

 $subtitleHtml = $subtitle !== ''
 ? sprintf('<div class="q-hint" style="margin-top:4px;">%s</div>', htmlspecialchars((string)$subtitle, ENT_QUOTES, 'UTF-8'))
 : '';

 $tag = $href !== '' ? 'a' : 'div';
 $hrefAttr = $href !== '' ? sprintf(' href="%s"', htmlspecialchars($href, ENT_QUOTES, 'UTF-8')) : '';
 $highlightStyle = $highlight ? ' border-color:var(--color-brand-accent); box-shadow:var(--shadow-md);' : '';
 $linkStyle = $href !== '' ? ' text-decoration:none; color:inherit;' : '';

 return sprintf(
 '<%s%s class="q-stat" style="%s">'
 . '<div class="q-stat__top"><span class="q-stat__label">%s</span>%s</div>'
 . '<div class="q-stat__value" data-kpi="%s">%s</div>'
 . '%s%s'
 . '</%s>',
 $tag,
 $hrefAttr,
 $highlightStyle . $linkStyle,
 $title,
 $iconWrap,
 $kpiKey,
 $value,
 $subtitleHtml,
 $trendHtml,
 $tag
 );
 }

 public static function renderGrid(array $cards, int $cols = 4): string {
 $colClasses = [
 2 => 'q-grid q-grid--2',
 3 => 'q-grid q-grid--3',
 4 => 'q-grid q-grid--4',
 6 => 'q-grid q-grid--4',
 ];
 $cls = $colClasses[$cols] ?? $colClasses[4];

 $html = sprintf('<section class="%s" aria-label="KPI göstergeleri">', $cls);
 foreach ($cards as $card) {
 $html .= self::render($card);
 }
 $html .= '</section>';
 return $html;
 }

 public static function renderMultiple(array $cards): string {
 return self::renderGrid($cards, 4);
 }
}
