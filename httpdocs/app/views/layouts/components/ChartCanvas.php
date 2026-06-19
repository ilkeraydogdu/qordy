<?php
/**
 * ChartCanvas - Standard surface for a chart (.q-card design system).
 */
namespace App\Views\Components;

class ChartCanvas {
 public static function render(array $props): string {
 $title = htmlspecialchars((string)($props['title'] ?? ''), ENT_QUOTES, 'UTF-8');
 $canvasId = htmlspecialchars((string)($props['canvas_id'] ?? ''), ENT_QUOTES, 'UTF-8');
 $size = (string)($props['size'] ?? 'lg');
 $subtitle = (string)($props['subtitle'] ?? '');

 $wrapClass = match ($size) {
 'sm' => 'q-chart-wrap q-chart-wrap--sm',
 'md' => 'q-chart-wrap',
 default => 'q-chart-wrap q-chart-wrap--lg',
 };

 $subtitleHtml = $subtitle !== ''
 ? sprintf('<p class="q-hint" style="margin-top:4px;">%s</p>', htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'))
 : '';

 $bare = !empty($props['bare']);
 $headerHtml = ($title !== '' || $subtitleHtml !== '')
 ? sprintf(
 '<div style="margin-bottom:var(--space-3);">'
 . ($title !== '' ? sprintf('<h3 class="q-section-title" style="margin-bottom:0;">%s</h3>', $title) : '')
 . '%s'
 . '</div>',
 $subtitleHtml
 )
 : '';

 $body = sprintf(
 '<div class="%s" data-chart-loading="1">'
 . '<canvas id="%s" aria-label="%s grafiği"></canvas>'
 . '<div data-chart-placeholder>'
 . '<div class="q-spinner q-spinner--lg" role="status" aria-label="Grafik yükleniyor"></div>'
 . '<p class="q-hint" style="margin-top:var(--space-2);font-weight:var(--font-weight-bold);color:var(--color-text-secondary);">Grafik yükleniyor</p>'
 . '<p class="q-hint" style="margin:0;">Veriler hazırlanıyor…</p>'
 . '</div>'
 . '</div>',
 $wrapClass,
 $canvasId,
 $title
 );

 $sectionClass = $bare ? 'q-chart-section q-chart-section--bare' : 'q-card q-card--pad';

 return sprintf(
 '<section class="%s">'
 . '%s'
 . '%s'
 . '</section>',
 $sectionClass,
 $headerHtml,
 $body
 );
 }
}
