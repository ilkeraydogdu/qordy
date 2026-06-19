<?php
/**
 * SectionCard - Standard surface for any dashboard section (.q-card design system).
 */
namespace App\Views\Components;

class SectionCard {
 private const ACCENT_COLORS = [
 'orange' => 'var(--color-brand-accent-hover)',
 'amber' => 'var(--color-brand-accent-hover)',
 'lime' => '#4d7c0f',
 'success' => 'var(--color-status-success)',
 'info' => 'var(--color-status-info)',
 'neutral' => 'var(--color-text-secondary)',
 ];

 public static function render(array $props): string {
 $title = htmlspecialchars((string)($props['title'] ?? ''), ENT_QUOTES, 'UTF-8');
 $icon = $props['icon'] ?? null;
 $accent = $props['accent'] ?? 'neutral';
 $badge = $props['badge'] ?? null;
 $bodyId = htmlspecialchars((string)($props['body_id'] ?? ''), ENT_QUOTES, 'UTF-8');
 $body = (string)($props['body'] ?? '');
 $variant = $props['variant'] ?? 'default';
 $actions = (string)($props['actions'] ?? '');
 $extraClasses = (string)($props['class'] ?? '');

 $iconHtml = '';
 if ($icon) {
 $svgPath = self::iconPath($icon);
 if ($svgPath !== '') {
 $color = self::ACCENT_COLORS[$accent] ?? self::ACCENT_COLORS['neutral'];
 $iconHtml = sprintf(
 '<svg style="width:20px;height:20px;color:%s;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/></svg>',
 $color,
 $svgPath
 );
 }
 }

 $badgeHtml = '';
 if ($badge !== null && $badge !== '') {
 $badgeHtml = sprintf(
 '<span class="q-badge q-badge--warning">%s</span>',
 htmlspecialchars((string)$badge, ENT_QUOTES, 'UTF-8')
 );
 }

 $cardClass = 'q-card q-card--pad';
 if ($extraClasses !== '') { $cardClass .= ' ' . $extraClasses; }
 $cardStyle = '';
 if ($variant === 'dark') {
 $cardStyle = ' style="background:var(--color-ink);color:var(--color-text-inverse);border-color:var(--color-ink-2);"';
 } elseif ($variant === 'outlined') {
 $cardStyle = ' style="border-color:var(--color-brand-accent);"';
 }

 $titleHtml = sprintf(
 '<h3 class="q-section-title" style="margin:0;">%s<span>%s</span>%s</h3>',
 $iconHtml,
 $title,
 $badgeHtml
 );

 $bodyIdAttr = $bodyId !== '' ? sprintf(' id="%s"', $bodyId) : '';
 $actionsHtml = $actions !== '' ? sprintf('<div class="q-page-header__actions">%s</div>', $actions) : '';

 return sprintf(
 '<section class="%s"%s>'
 . '<div class="q-toolbar q-toolbar--between" style="margin-bottom:var(--space-3);">%s%s</div>'
 . '<div%s>%s</div>'
 . '</section>',
 $cardClass,
 $cardStyle,
 $titleHtml,
 $actionsHtml,
 $bodyIdAttr,
 $body
 );
 }

 private static function iconPath(string $name): string {
 $paths = [
 'signal' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3',
 'bell' => 'M15 17h5m-3-4v3m-4.333-3.667V17m-4.333-3.667V17m-4.334-3.667V17m-4.333-3.667V17m12.998-6.333L20.333 7.333l-3-3m-12.666 3L8.667 4.333l-3 3',
 'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
 'bar-chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
 'star' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
 'shield' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
 'sparkles' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
 ];
 return $paths[$name] ?? '';
 }
}
