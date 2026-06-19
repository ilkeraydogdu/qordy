<?php
/**
 * NotificationList - "Canlı Bildirimler" panel.
 *
 * Server-renders an initial list from $notifications, and exposes an
 * empty container with id="notifications-list" that JS can append to.
 */
namespace App\Views\Components;

require_once __DIR__ . '/SectionCard.php';

class NotificationList {
 public static function render(array $props = []): string {
 $container = '<div id="notifications-list" class="q-stack" data-list-target="notifications">'
 . '<div class="q-empty q-empty--inline" data-list-placeholder>'
 . '<svg class="q-empty__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
 . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5m-3-4v3m-4.333-3.667V17m-4.333-3.667V17m-4.334-3.667V17m-4.333-3.667V17m12.998-6.333L20.333 7.333l-3-3m-12.666 3L8.667 4.333l-3 3"/>'
 . '</svg>'
 . '<p class="q-empty__title">Henüz bildirim yok</p>'
 . '<p class="q-hint" style="margin-top:var(--space-1);max-width:220px;margin-inline:auto;">Yeni siparişler ve önemli olaylar burada görünecek</p>'
 . '</div>'
 . '</div>';

 return SectionCard::render([
 'title' => 'Canlı Bildirimler',
 'icon' => 'bell',
 'accent' => 'orange',
 'body' => $container,
 ]);
 }
}
