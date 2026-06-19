<?php
/**
 * StatusPill — Standart durum pilli (durum badge)
 *
 * Kullanım:
 * echo StatusPill::render(['status' => 'pending', 'label' => 'Bekliyor']);
 * echo StatusPill::render(['status' => 'success', 'label' => 'Tamamlandı', 'icon' => 'check']);
 * echo StatusPill::render(['status' => 'danger', 'label' => 'İptal Edildi', 'size' => 'sm']);
 *
 * Variants: success | warning | danger | info | neutral | live
 * Sizes: sm | md
 *
 * Uses canonical .q-badge system from admin-components.css.
 */

namespace App\Views\Components;

class StatusPill {
 /**
 * Map raw status names to canonical q-badge variant.
 */
 private const VARIANT_MAP = [
 'success' => 'success',
 'completed' => 'success',
 'paid' => 'success',
 'served' => 'success',
 'active' => 'success',
 'closed' => 'success',
 'warning' => 'warning',
 'pending' => 'warning',
 'waiting' => 'warning',
 'contacted' => 'warning',
 'danger' => 'danger',
 'cancelled' => 'danger',
 'rejected' => 'danger',
 'failed' => 'danger',
 'no_show' => 'danger',
 'info' => 'info',
 'processing' => 'info',
 'new' => 'info',
 'approved' => 'info',
 'neutral' => 'neutral',
 'draft' => 'neutral',
 'live' => 'live',
 ];

 public static function render(array $props): string {
 $status = $props['status'] ?? 'neutral';
 $label = htmlspecialchars((string)($props['label'] ?? 'Durum'), ENT_QUOTES, 'UTF-8');
 $icon = $props['icon'] ?? null;
 $size = $props['size'] ?? 'md';
 $rawLabel = $props['label'] ?? null;

 // Resolve variant — accept both raw names (e.g. 'pending') and direct variants (e.g. 'warning')
 $variant = self::VARIANT_MAP[$status] ?? 'neutral';

 $sizeClass = $size === 'sm' ? 'q-badge--sm' : '';

 $content = '';
 if ($icon !== null) {
 if (\App\Views\Components\Icons::exists($icon)) {
 $iconClass = $size === 'sm' ? 'w-3 h-3 mr-1' : 'w-4 h-4 mr-1';
 $content .= Icons::svg($icon, $iconClass);
 } else {
 $iconClass = $size === 'sm' ? 'w-3 h-3 mr-1' : 'w-4 h-4 mr-1';
 $content .= sprintf(
 '<svg class="%s" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
 . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/>'
 . '</svg>',
 $iconClass,
 htmlspecialchars($icon, ENT_QUOTES, 'UTF-8')
 );
 }
 }

 $content .= '<span>' . $label . '</span>';

 $classes = trim('q-badge q-badge--' . $variant . ' ' . $sizeClass);

 return sprintf(
 '<span class="%s" data-status="%s">%s</span>',
 $classes,
 htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
 $content
 );
 }
}
