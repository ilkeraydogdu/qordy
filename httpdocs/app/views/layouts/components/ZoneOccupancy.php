<?php
/**
 * ZoneOccupancy - Dark "Bölge Yoğunluğu" panel.
 *
 * Renders a single dark surface (matches the original design intent) with
 * one progress bar per zone. If $zones is empty, an empty state is shown
 * instead of a confusing "%0" placeholder for non-existent zones.
 *
 * Usage:
 * echo ZoneOccupancy::render([
 * 'zones' => ['Üst Kat' => ['total' => 10, 'occupied' => 6], ...],
 * ]);
 */
namespace App\Views\Components;

class ZoneOccupancy {
 public static function render(array $props = []): string {
 $zones = $props['zones'] ?? [];
 $isEmpty = empty($zones);

 $body = '';
 if ($isEmpty) {
 $body = EmptyState::render([
 'title' => 'Henüz bölge tanımlı değil',
 'subtitle' => 'Masaları bölgelere atayarak doluluk takibi yapabilirsiniz.',
 'icon' => 'grid',
 'tone' => 'dark',
 ]);
 } else {
 $rows = '';
 foreach ($zones as $name => $data) {
 $total = max(0, (int)($data['total'] ?? 0));
 $occupied = max(0, (int)($data['occupied'] ?? 0));
 $pct = $total > 0 ? (int)round(($occupied / $total) * 100) : 0;
 $displayName = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');

 $rows .= sprintf(
 '<div>'
 . '<div class="flex justify-between mb-1.5 sm:mb-2 text-[10px] sm:text-xs font-black uppercase tracking-wider text-white">'
 . '<span class="truncate">%s</span>'
 . '<span class="tabular-nums shrink-0 ml-2">%d/%d · %%%d</span>'
 . '</div>'
 . '<div class="h-2 sm:h-2.5 bg-white/10 rounded-full overflow-hidden p-0.5">'
 . '<div class="h-full bg-orange-500 rounded-full transition-all duration-1000 shadow-[0_0_10px_rgba(249,115,22,0.5)]" style="width: %d%%" role="progressbar" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="100"></div>'
 . '</div>'
 . '</div>',
 $displayName, $occupied, $total, $pct, $pct, $pct
 );
 }
 $body = sprintf('<div class="space-y-2.5 sm:space-y-3 md:space-y-4">%s</div>', $rows);
 }

 $title = sprintf(
 '<h3 class="text-base sm:text-lg md:text-xl font-black tracking-tight text-white">Bölge Yoğunluğu</h3>'
 );

 return sprintf(
 '<section class="bg-slate-900 text-white p-3 sm:p-4 md:p-5 rounded-xl sm:rounded-2xl shadow-2xl flex flex-col min-h-[200px]">'
 . '<div class="flex items-center justify-between mb-3 sm:mb-4">%s</div>'
 . '<div class="flex-1" data-zone-occupancy>%s</div>'
 . '</section>',
 $title,
 $body
 );
 }
}
