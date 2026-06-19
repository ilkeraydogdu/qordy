<?php
/**
 * Button — Standart buton (anchor veya <button>)
 *
 * Kullanım:
 * echo Button::render([
 * 'label' => 'Kaydet',
 * 'variant' => 'primary', // primary|secondary|danger|ghost|success|outline|warning|info|light|dark
 * 'size' => 'md', // sm|md|lg
 * 'icon' => 'check', // Icons::svg() adı
 * 'icon_position' => 'left', // left|right
 * 'type' => 'submit', // submit|button
 * 'onclick' => '...',
 * 'full' => false,
 * 'disabled' => false,
 * 'attrs' => ['data-id' => '5'],
 * ]);
 *
 * echo Button::renderLink([
 * 'label' => 'Detay', 'href' => '/orders/5', 'variant' => 'ghost', 'icon' => 'arrow-right',
 * ]);
 *
 * echo Button::renderIcon([
 * 'icon' => 'trash', 'variant' => 'danger', 'onclick' => '...',
 * ]);
 *
 * Eski API: 'text' kullanılıyorsa 'label' ile aynı kabul edilir (geriye uyumlu).
 */

namespace App\Views\Components;

class Button {
 public static function render(array $props): string {
 $label = $props['label'] ?? $props['text'] ?? 'Button';
 $href = $props['href'] ?? null;
 $variant = $props['variant'] ?? $props['color'] ?? 'primary';
 $size = $props['size'] ?? 'md';
 $icon = $props['icon'] ?? null;
 $iconPosition = $props['icon_position'] ?? 'left';
 $type = $props['type'] ?? 'button';
 $onclick = $props['onclick'] ?? null;
 $fullWidth = (bool)($props['full'] ?? false);
 $class = $props['class'] ?? '';
 $disabled = (bool)($props['disabled'] ?? false);
 $attrs = $props['attrs'] ?? [];

 $variantClass = self::variantClass($variant);
 $sizeClass = self::sizeClass($size);
 $iconSize = self::iconSize($size);

 $baseClasses = sprintf(
 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all duration-200 %s %s %s %s',
 $variantClass,
 $sizeClass,
 $fullWidth ? 'w-full' : '',
 $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '',
 $class
 );

 $iconHtml = '';
 if ($icon !== null && $icon !== '') {
 // Icon name ise Icons::svg kullan; path ise direkt path
 if (Icons::exists($icon)) {
 $iconHtml = Icons::svg($icon, $iconSize);
 } else {
 // Fallback: eski 'icon' => 'svg-path' API (geriye uyumlu)
 $iconHtml = sprintf(
 '<svg class="%s" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
 . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="%s"/>'
 . '</svg>',
 $iconSize,
 $icon
 );
 }
 }

 $labelHtml = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
 $content = $iconPosition === 'right'
 ? $labelHtml . $iconHtml
 : $iconHtml . $labelHtml;

 $attrStr = '';
 $attrStr .= $onclick ? sprintf(' onclick="%s"', htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8')) : '';
 $attrStr .= $disabled ? ' aria-disabled="true"' : '';
 foreach ($attrs as $k => $v) {
 $attrStr .= sprintf(' %s="%s"', htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8'),
 htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
 }

 if ($href !== null && $href !== '') {
 return sprintf('<a href="%s" class="%s"%s>%s</a>',
 htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
 htmlspecialchars($baseClasses, ENT_QUOTES, 'UTF-8'),
 $attrStr,
 $content
 );
 }

 return sprintf('<button type="%s" class="%s"%s>%s</button>',
 htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
 htmlspecialchars($baseClasses, ENT_QUOTES, 'UTF-8'),
 $attrStr,
 $content
 );
 }

 public static function renderLink(array $props): string {
 $props['href'] = $props['href'] ?? '#';
 return self::render($props);
 }

 public static function renderIcon(array $props): string {
 $props['label'] = '';
 $props['icon_position'] = 'left';
 $props['class'] = trim(($props['class'] ?? '') . ' !p-2 !rounded-full');
 return self::render($props);
 }

 public static function renderGroup(array $buttons, string $class = 'grid grid-cols-2 sm:grid-cols-4 gap-4'): string {
 $html = sprintf('<div class="%s">', htmlspecialchars($class, ENT_QUOTES, 'UTF-8'));
 foreach ($buttons as $button) {
 $html .= self::render($button);
 }
 $html .= '</div>';
 return $html;
 }

 // ----- Internal helpers -----

 private static function variantClass(string $variant): string {
 $variants = [
 'primary' => 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm hover:shadow-md',
 'secondary' => 'bg-slate-100 hover:bg-slate-200 text-slate-700',
 'success' => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm',
 'danger' => 'bg-rose-600 hover:bg-rose-700 text-white shadow-sm',
 'warning' => 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm',
 'info' => 'bg-cyan-600 hover:bg-cyan-700 text-white shadow-sm',
 'light' => 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200',
 'dark' => 'bg-slate-800 hover:bg-slate-900 text-white shadow-sm',
 'orange' => 'bg-orange-500 hover:bg-orange-600 text-white shadow-sm',
 'ghost' => 'bg-transparent hover:bg-slate-100 text-slate-700',
 'outline' => 'bg-transparent hover:bg-slate-50 text-slate-700 border border-slate-300',
 ];
 return $variants[$variant] ?? $variants['primary'];
 }

 private static function sizeClass(string $size): string {
 $sizes = [
 'xs' => 'px-2 py-1 text-xs',
 'sm' => 'px-3 py-1.5 text-sm',
 'md' => 'px-4 py-2 text-sm',
 'lg' => 'px-5 py-2.5 text-base',
 'xl' => 'px-6 py-3 text-base',
 ];
 return $sizes[$size] ?? $sizes['md'];
 }

 private static function iconSize(string $size): string {
 $sizes = [
 'xs' => 'w-3 h-3',
 'sm' => 'w-4 h-4',
 'md' => 'w-4 h-4',
 'lg' => 'w-5 h-5',
 'xl' => 'w-5 h-5',
 ];
 return $sizes[$size] ?? $sizes['md'];
 }
}
