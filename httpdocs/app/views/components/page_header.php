<?php
/**
 * Reusable page header (eyebrow + title + subtitle + actions).
 * Replaces the 357x duplicated q-page-header markup.
 *
 * Usage:
 * echo renderPageHeader('İŞLETMELER', 'Müşteriler', 'Toplam 124 aktif müşteri', '<a class="q-btn q-btn--primary">Yeni</a>');
 */
if (!function_exists('renderPageHeader')) {
    function renderPageHeader(string $eyebrow, string $title, ?string $subtitle = null, ?string $actionsHtml = null): string
    {
        $subtitleHtml = $subtitle !== null
            ? '<p class="q-page-header__subtitle">' . htmlspecialchars($subtitle) . '</p>'
            : '';
        $actionsHtmlFinal = $actionsHtml !== null && $actionsHtml !== ''
            ? '<div class="q-page-header__actions">' . $actionsHtml . '</div>'
            : '';

        return '<header class="q-page-header mb-6">'
            . '<div>'
            . '<p class="q-page-header__eyebrow">' . htmlspecialchars($eyebrow) . '</p>'
            . '<h1 class="q-page-header__title">' . htmlspecialchars($title) . '</h1>'
            . $subtitleHtml
            . '</div>'
            . $actionsHtmlFinal
            . '</header>';
    }
}
