<?php
/**
 * BusinessPanelShell — White rounded content shell for /business/* and /qodmin/* pages.
 *
 * Navigation and topbar live in admin_layout (outer sidebar + page topbar).
 * This partial wraps page content only — no inner sidebar or topbar.
 */
namespace App\Views\Components;

class BusinessPanelShell {
    /**
     * Marketing #panel content card — main slot only.
     *
     * @param array{content: string, standalone?: bool, opsEmbed?: bool} $props
     */
    public static function render(array $props): string {
        $content = (string)($props['content'] ?? '');
        $standalone = (bool)($props['standalone'] ?? false);
        $opsEmbed = (bool)($props['opsEmbed'] ?? false);
        $shellMod = $opsEmbed ? ' q-panel-shell--ops' : '';

        $shell = <<<HTML
<div class="q-panel-shell q-biz-theme{$shellMod}">
  <div class="q-panel-main">{$content}</div>
</div>
HTML;

        if (!$standalone) {
            return $shell;
        }

        // Standalone: viewport'u dolduran flex column. Topbar sabit,
        // içerik q-panel-body içinde scroll olur. Eski body'nin dış
        // flex h-screen yapısını devralır; dış wrapper'lara ihtiyaç
        // kalmaz.
        return '<div class="q-biz-standalone">' . $shell . '</div>';
    }

    /** Zone occupancy fallback when branch map has real zone data. */
    public static function zonePerformance(array $zones): string {
        if (empty($zones)) {
            return self::turkeyMap();
        }
        $rows = '';
        foreach ($zones as $name => $data) {
            $total = max(0, (int)($data['total'] ?? 0));
            $occupied = max(0, (int)($data['occupied'] ?? 0));
            $pct = $total > 0 ? (int)round(($occupied / $total) * 100) : 0;
            $tier = $pct >= 70 ? 'high' : ($pct >= 35 ? 'mid' : 'low');
            $displayName = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
            $rows .= sprintf(
                '<div class="q-panel-zone-row"><span class="q-panel-map__dot q-panel-map__dot--%s"></span><span class="q-panel-zone-row__name">%s</span><span class="q-panel-zone-row__meta">%d/%d · %%%d</span><span class="q-panel-zone-row__bar"><span style="width:%d%%"></span></span></div>',
                $tier,
                $displayName,
                $occupied,
                $total,
                $pct,
                $pct
            );
        }
        return '<div class="q-panel-zones">' . $rows . '</div>';
    }

    /** Decorative Turkey map (matches landing TurkeyMap). */
    public static function turkeyMap(): string {
        return <<<'SVG'
<div class="q-panel-map" role="img" aria-label="Bölge performans haritası">
  <svg viewBox="0 0 1000 440" class="q-panel-map__svg">
    <defs>
      <linearGradient id="q-tr-fill" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#6366F1" stop-opacity="0.16"/>
        <stop offset="100%" stop-color="#8B5CF6" stop-opacity="0.05"/>
      </linearGradient>
    </defs>
    <path d="M58 150 C120 116 210 110 300 122 C402 136 520 110 660 124 C792 137 900 146 958 182 L982 214 C966 236 944 252 952 292 L922 322 C862 332 820 300 762 322 C706 342 648 360 566 358 L552 410 L536 358 C462 358 384 350 304 350 C226 350 162 360 112 330 L72 300 C92 272 62 250 84 220 C62 200 50 176 58 150 Z" fill="url(#q-tr-fill)" stroke="#6366F1" stroke-opacity="0.35" stroke-width="2.5" stroke-linejoin="round"/>
    <circle cx="210" cy="114" r="5" fill="#10B981" stroke="#fff" stroke-width="1.5"/>
    <circle cx="120" cy="246" r="5" fill="#10B981" stroke="#fff" stroke-width="1.5"/>
    <circle cx="410" cy="185" r="5" fill="#10B981" stroke="#fff" stroke-width="1.5"/>
    <circle cx="330" cy="326" r="5" fill="#F59E0B" stroke="#fff" stroke-width="1.5"/>
    <circle cx="240" cy="167" r="5" fill="#F59E0B" stroke="#fff" stroke-width="1.5"/>
    <circle cx="550" cy="308" r="5" fill="#F59E0B" stroke="#fff" stroke-width="1.5"/>
    <circle cx="720" cy="132" r="5" fill="#6366F1" stroke="#fff" stroke-width="1.5"/>
    <circle cx="760" cy="255" r="5" fill="#6366F1" stroke="#fff" stroke-width="1.5"/>
  </svg>
  <div class="q-panel-map__legend">
    <span><span class="q-panel-map__dot q-panel-map__dot--high"></span>Yüksek</span>
    <span><span class="q-panel-map__dot q-panel-map__dot--mid"></span>Orta</span>
    <span><span class="q-panel-map__dot q-panel-map__dot--low"></span>Düşük</span>
  </div>
</div>
SVG;
    }
}
