<?php
/**
 * EmptyState - Placeholder shown when a list/section has no data.
 *
 * Renders an icon, a title, and optional helper text. Centred, low contrast,
 * non-interactive. Use `tone="dark"` on dark surfaces (e.g. zone occupancy).
 *
 * Usage:
 * echo EmptyState::render([
 * 'title' => 'Henüz bildirim yok',
 * 'subtitle' => 'Yeni sipariş geldiğinde burada görünecek',
 * 'icon' => 'bell',
 * 'tone' => 'light', // 'light' | 'dark'
 * ]);
 */
namespace App\Views\Components;

class EmptyState {
 public static function render(array $props): string {
 $title = htmlspecialchars((string)($props['title'] ?? 'Veri yok'), ENT_QUOTES, 'UTF-8');
 $subtitle = (string)($props['subtitle'] ?? '');
 $icon = (string)($props['icon'] ?? '');
 $tone = $props['tone'] ?? 'light';

 $textClass = $tone === 'dark' ? 'text-slate-400' : 'text-slate-300';
 $subtitleClass = $tone === 'dark' ? 'text-slate-500' : 'text-slate-400';

 $iconHtml = '';
 if ($icon !== '') {
 $path = self::iconPath($icon);
 if ($path !== '') {
 $iconHtml = sprintf(
 '<svg class="w-10 h-10 sm:w-12 sm:h-12 mx-auto %s mb-2 sm:mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="%s"/></svg>',
 $textClass,
 $path
 );
 }
 }

 $subtitleHtml = $subtitle !== ''
 ? sprintf('<p class="text-xs sm:text-sm font-bold %s mt-1">%s</p>', $subtitleClass, htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'))
 : '';

 return sprintf(
 '<div class="text-center py-6 sm:py-8 px-4">%s<p class="text-sm sm:text-base font-bold %s">%s</p>%s</div>',
 $iconHtml,
 $textClass,
 $title,
 $subtitleHtml
 );
 }

 private static function iconPath(string $name): string {
 $paths = [
 'bell' => 'M15 17h5m-3-4v3m-4.333-3.667V17m-4.333-3.667V17m-4.334-3.667V17m-4.333-3.667V17m12.998-6.333L20.333 7.333l-3-3m-12.666 3L8.667 4.333l-3 3',
 'orders' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
 'chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
 'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
 'table' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 14a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 14a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2z',
 'signal' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3',
 ];
 return $paths[$name] ?? '';
 }
}
