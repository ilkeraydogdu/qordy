<?php
/**
 * QuickAction - Reusable Quick Actions Grid
 *
 * Usage:
 * echo QuickAction::renderMultiple([
 * [
 * 'text' => 'Operasyonlar',
 * 'href' => '/business/operations',
 * 'icon' => 'orders',
 * 'color' => 'blue'
 * ],
 * ...
 * ]);
 */
namespace App\Views\Components;

class QuickAction {
 public static function render(array $props): string {
 $text = $props['text'] ?? '';
 $href = $props['href'] ?? '';
 $icon = $props['icon'] ?? 'link';
 $color = $props['color'] ?? 'blue';
 $description = $props['description'] ?? '';
 $active = $props['active'] ?? false;

 // Icon mappings
 $icons = [
 'orders' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>',
 'finance' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>',
 'settings' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
 'chart' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>',
 'menu' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>',
 'category' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>',
 'table' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 14a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 14a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2z"></path>',
 'printer' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>',
 'queue' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>',
 'kitchen' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 9s13-8 12 14.657A8 8 0 0120.343 18.657zM9.293 13.293A2 2 0 0112.707 14H15a2 2 0 012 2c0 .68-.332 1.296-.866 1.678M19.121 20.121a2 2 0 002.828 2.828A2 2 0 0021.657 22.657 2 2 0 0018.828 20.121z"></path>',
 'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>',
 'logout' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m10 4v-1a3 3 0 00-3-3h-4m0 8a3 3 0 01-3-3V9a3 3 0 013-3h4a3 3 0 013 3v4a3 3 0 01-3 3z"></path>',
 'link' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>'
 ];

 // Color mappings (Q-System tokens)
 $colors = [
 'blue' => ['bg' => 'var(--color-status-info-bg)', 'icon' => 'var(--color-status-info)'],
 'green' => ['bg' => 'var(--color-lime-soft)', 'icon' => '#4d7c0f'],
 'purple' => ['bg' => 'var(--color-surface-3)', 'icon' => 'var(--color-text-secondary)'],
 'orange' => ['bg' => 'var(--color-amber-soft)', 'icon' => 'var(--color-brand-accent-hover)'],
 'red' => ['bg' => 'var(--color-status-danger-bg)', 'icon' => 'var(--color-status-danger)'],
 'cyan' => ['bg' => 'var(--color-surface-2)', 'icon' => 'var(--color-text-primary)'],
 'pink' => ['bg' => 'var(--color-surface-2)', 'icon' => 'var(--color-text-primary)'],
 'amber' => ['bg' => 'var(--color-amber-soft)', 'icon' => 'var(--color-brand-accent-hover)'],
 'lime' => ['bg' => 'var(--color-lime-soft)', 'icon' => '#4d7c0f'],
 'slate' => ['bg' => 'var(--color-surface-2)', 'icon' => 'var(--color-text-primary)']
 ];

 $colorStyle = $colors[$color] ?? $colors['blue'];
 $iconSvg = $icons[$icon] ?? $icons['link'];
 $activeClass = $active ? ' q-quick-action--active' : '';
 $descriptionHtml = $description ? "<span class='q-quick-action__desc'>{$description}</span>" : '';

 $html = <<<HTML
 <a href="{$href}" class="q-quick-action{$activeClass}">
 <span class="q-quick-action__icon" style="background:{$colorStyle['bg']};color:{$colorStyle['icon']};">
 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 {$iconSvg}
 </svg>
 </span>
 <span class="q-quick-action__label">{$text}</span>
 {$descriptionHtml}
 </a>
HTML;

 return $html;
 }

 public static function renderMultiple(array $actions, int $cols = 4): string {
 $colClass = match($cols) {
 2 => 'q-grid q-grid--2',
 3 => 'q-grid q-grid--3',
 4 => 'q-grid q-grid--4',
 6 => 'q-grid q-grid--4',
 default => 'q-grid q-grid--4'
 };

 $html = '<div class="' . $colClass . '">';
 foreach ($actions as $action) {
 $html .= self::render($action);
 }
 $html .= '</div>';
 return $html;
 }

 // Predefined business menu
 public static function businessMenu(): array {
 return [
 [
 'text' => 'Operasyonlar',
 'href' => BASE_URL . '/business/operations',
 'icon' => 'orders',
 'color' => 'blue',
 'active' => false
 ],
 [
 'text' => 'Finans',
 'href' => BASE_URL . '/business/finance',
 'icon' => 'finance',
 'color' => 'green',
 'active' => false
 ],
 [
 'text' => 'Ayarlar',
 'href' => BASE_URL . '/business/settings',
 'icon' => 'settings',
 'color' => 'purple',
 'active' => false
 ],
 [
 'text' => 'Analiz',
 'href' => BASE_URL . '/business/analysis',
 'icon' => 'chart',
 'color' => 'orange',
 'active' => false
 ]
 ];
 }
}