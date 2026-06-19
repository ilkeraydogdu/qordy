<?php
/**
 * Dashboard Header Partial — Q-System Edition
 *
 * Backward compatible render() signature. Returns a simple greeting
 * block; main dashboard now uses inline .q-page-header directly for
 * glass + gradient support.
 */
namespace App\Views\BusinessAdmin;

class HeaderPartial {
 public static function render(array $props = []): string {
 $businessName = $props['business_name'] ?? ($_SESSION['company_name'] ?? ($_SESSION['business_name'] ?? 'İşletmeniz'));
 $logo = $props['logo'] ?? '';
 $userName = $props['user_name'] ?? ($_SESSION['first_name'] ?? ($_SESSION['name'] ?? 'İşletme Sahibi'));

 $html = '<header class="q-card q-card--pad" style="margin-bottom:var(--space-6);">';
 $html .= '<div class="q-page-header">';
 $html .= '<div>';
 $html .= '<span class="q-page-header__eyebrow--accent">YÖNETİM PANELİ</span>';
 $html .= '<h1 class="q-page-header__title" style="margin-top:8px;">Hoş geldiniz, ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '</h1>';
 $html .= '<p class="q-page-header__subtitle">' . htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8') . ' — Yönetim Paneli</p>';
 $html .= '</div>';

 if ($logo) {
 $html .= '<div class="flex-shrink-0"><img src="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '" alt="İşletme Logosu" class="q-card__logo"></div>';
 }

 $html .= '</div></header>';

 return $html;
 }
}
