<?php
/**
 * ListSection - Generic container for live-rendered lists (.q-card design system).
 */
namespace App\Views\Components;

class ListSection {
 public static function render(array $props): string {
 $title = htmlspecialchars((string)($props['title'] ?? ''), ENT_QUOTES, 'UTF-8');
 $icon = (string)($props['icon'] ?? '');
 $listId = htmlspecialchars((string)($props['list_id'] ?? ''), ENT_QUOTES, 'UTF-8');
 $target = htmlspecialchars((string)($props['target'] ?? ''), ENT_QUOTES, 'UTF-8');
 $extra = (string)($props['extra_body'] ?? '');
 $minHeight = (string)($props['min_height'] ?? 'min-h-[120px]');

 $iconHtml = '';
 if ($icon !== '') {
 $path = self::iconPath($icon);
 if ($path !== '') {
 $iconHtml = sprintf(
 '<svg style="width:20px;height:20px;color:var(--color-brand-accent-hover);flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/></svg>',
 $path
 );
 }
 }

 $placeholder = '<div class="q-loading-inline" data-list-placeholder>'
 . '<div class="q-spinner q-spinner--lg" role="status" aria-label="Yükleniyor"></div>'
 . '<p class="q-hint" style="margin:0;font-weight:var(--font-weight-bold);color:var(--color-text-secondary);">Veriler yükleniyor</p>'
 . '<p class="q-hint" style="margin:0;">Canlı veriler getiriliyor…</p>'
 . '</div>';

 return sprintf(
 '<section class="q-card q-card--pad">'
 . '<h3 class="q-section-title" style="margin-bottom:var(--space-3);">%s<span>%s</span></h3>'
 . '<div id="%s" data-list-target="%s" class="q-stack %s" data-list-loading="1">'
 . '%s'
 . '</div>'
 . '%s'
 . '</section>',
 $iconHtml,
 $title,
 $listId,
 $target,
 $minHeight,
 $placeholder,
 $extra
 );
 }

 private static function iconPath(string $name): string {
 $paths = [
 'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
 'bar-chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
 'star' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
 'table' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 14a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 14a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2z',
 'orders' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
 'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
 'grid' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
 'wallet' => 'M3 8h18M3 8a2 2 0 012-2h14a2 2 0 012 2M3 8v10a2 2 0 002 2h14a2 2 0 002-2V8m-9 4h2',
 ];
 return $paths[$name] ?? '';
 }
}
