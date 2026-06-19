<?php
/**
 * Badge - Reusable Status Badge Component
 *
 * Usage:
 * echo Badge::render(['text' => 'Hazırlandı', 'type' => 'success']);
 */
namespace App\Views\Components;

class Badge {
 public static function render(array $props): string {
 $text = $props['text'] ?? '';
 $type = $props['type'] ?? 'default';
 $size = $props['size'] ?? 'sm';
 $href = $props['href'] ?? '';
 $tooltip = $props['tooltip'] ?? '';

 // Style variants (Q-System tokens)
 $styles = [
 'success' => 'q-badge--success',
 'error' => 'q-status-pill--danger',
 'warning' => 'q-status-pill--warning',
 'info' => 'q-status-pill--info',
 'primary' => 'q-status-pill--info',
 'secondary' => 'q-badge--neutral',
 'default' => 'q-badge--neutral'
 ];

 $badgeClass = $styles[$type] ?? $styles['default'];
 $classes = "q-badge {$badgeClass}";

 if ($href) {
 $content = '<a href="' . $href . '" class="' . $classes . '">' . htmlspecialchars($text) . '</a>';
 } else {
 $content = '<span class="' . $classes . '">' . htmlspecialchars($text) . '</span>';
 }

 if ($tooltip) {
 return <<<HTML
 <div class="q-tooltip-wrap">
 {$content}
 <div class="q-tooltip">
 {$tooltip}
 </div>
 </div>
HTML;
 }

 return $content;
 }

 public static function forOrderStatus(string $status): string {
 $map = [
 'pending' => ['text' => 'Beklemede', 'type' => 'warning'],
 'preparing' => ['text' => 'Hazırlanıyor', 'type' => 'info'],
 'ready' => ['text' => 'Hazırlandı', 'type' => 'success'],
 'served' => ['text' => 'Teslim Edildi', 'type' => 'success'],
 'cancelled' => ['text' => 'İptal Edildi', 'type' => 'error']
 ];
 $statusMap = $map[$status] ?? ['text' => $status, 'type' => 'default'];
 return self::render($statusMap);
 }
}