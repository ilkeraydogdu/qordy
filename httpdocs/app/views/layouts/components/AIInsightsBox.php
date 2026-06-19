<?php
namespace App\Views\Components;

/**
 * Dashboard AI Danışman feed — compact panel-native markup.
 */
class AIInsightsBox {
    public static function render(array $props = []): string {
        $placeholder = htmlspecialchars(
            (string)($props['placeholder'] ?? 'Öneriler yükleniyor…'),
            ENT_QUOTES,
            'UTF-8'
        );
        $savedUrl = htmlspecialchars(
            (string)($props['saved_url'] ?? (defined('BASE_URL') ? BASE_URL . '/business/ai-onerileri' : '/business/ai-onerileri')),
            ENT_QUOTES,
            'UTF-8'
        );

        return sprintf(
            '<div id="ai-insights-container" class="q-ai-feed-wrap">'
            . '<div class="q-ai-feed__toolbar">'
            . '<span class="q-ai-feed__hint" id="ai-feed-meta">Veri tabanlı öneriler</span>'
            . '<div class="q-ai-feed__actions">'
            . '<a href="%2$s" class="q-ai-feed__action-btn" title="Kaydedilenler" aria-label="Kaydedilen öneriler">'
            . '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>'
            . '</svg><span class="q-ai-feed__action-label">Kaydedilenler</span></a>'
            . '<button type="button" data-ai-trigger class="q-ai-feed__action-btn" aria-label="Önerileri yenile">'
            . '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>'
            . '</svg><span class="q-ai-feed__action-label">Yenile</span></button>'
            . '</div></div>'
            . '<div id="ai-insights-text" class="q-ai-feed" role="list" aria-live="polite">'
            . '<div class="q-ai-feed__loading" data-ai-loading>'
            . '<span class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></span>'
            . '<span class="q-hint">%1$s</span>'
            . '</div></div></div>',
            $placeholder,
            $savedUrl
        );
    }
}
