<?php
/**
 * Door display entry point.
 *
 * Instead of rendering a fixed design, this file now resolves the tenant's
 * chosen theme (from queue_settings.display_theme) and delegates to the
 * corresponding theme partial under app/views/queue/themes/{theme}.php.
 *
 * Input vars (provided by PublicQueueController@display):
 *   $business (array)
 *   $settings (array)
 *   $token    (array|null)
 *   $active   (array[])
 *   $waitingCount (int)
 *   $formUrl  (string)
 */

require_once __DIR__ . '/_helpers.php';

/** @var array $settings */
/** @var array $business */
/** @var array $active */
$doorMenuItems = $doorMenuItems ?? [];

$theme = \App\Support\QueueThemeRegistry::resolve($settings['display_theme'] ?? null);

[$themeColor, $accentColor] = qd_accent_pair($settings);
$businessName = qd_business_name($business);

$defaultLang = $settings['default_language'] ?? 'tr';
$dict = qd_display_dict($defaultLang);

$titleMap    = is_array($settings['display_title'] ?? null)          ? $settings['display_title']          : [];
$subtitleMap = is_array($settings['display_subtitle'] ?? null)       ? $settings['display_subtitle']       : [];
$ctaMap      = is_array($settings['display_call_to_action'] ?? null) ? $settings['display_call_to_action'] : [];

// Two operating modes:
//   (a) welcome  – tables available, show hospitality content + social links, no QR
//   (b) queue    – tables full, show QR + short CTA so visitors can join the line
$isAcceptingQueue = !empty($settings['is_accepting_queue']);

// Admin live preview can force a specific mode via ?mode=welcome|queue so the
// owner can compare both layouts side-by-side without flipping the live switch.
$modeOverride = strtolower((string) ($_GET['mode'] ?? ''));
if ($modeOverride === 'welcome') {
    $isAcceptingQueue = false;
} elseif ($modeOverride === 'queue') {
    $isAcceptingQueue = true;
}

/**
 * Kapı ekranında "bekleyen sayı / tahmini süre / anlık sıra numaraları" (personel/operasyon)
 * gösterimleri. Hepsi kapalıyken aynı URL tek sütun, marka + QR + metin + sosyal odaklı olur
 * (TV’de dolaşan müşteri için; bilet ekranı /sira/bilet/ değil).
 */
$doorShowMetrics = qd_queue_bool_setting($settings['show_waiting_count'] ?? null)
    || qd_queue_bool_setting($settings['show_estimated_wait'] ?? null)
    || qd_queue_bool_setting($settings['show_active_numbers'] ?? null);

$titleRaw = trim((string) ($titleMap[$defaultLang] ?? ''));
$subtitleRaw = trim((string) ($subtitleMap[$defaultLang] ?? ''));
if ($isAcceptingQueue) {
    if ($titleRaw !== '') {
        $title = $titleRaw;
    } else {
        $title = $doorShowMetrics
            ? $dict['all_full']
            : ($dict['queue_mode_cta'] ?? $dict['all_full']);
    }
    if ($subtitleRaw !== '') {
        $subtitle = $subtitleRaw;
    } else {
        $subtitle = $doorShowMetrics
            ? $dict['all_full_sub']
            : ($dict['queue_mode_sub'] ?? $dict['all_full_sub']);
    }
} else {
    $title    = $titleRaw !== '' ? $titleRaw : $dict['all_full'];
    $subtitle = $subtitleRaw !== '' ? $subtitleRaw : $dict['all_full_sub'];
}
$cta = $ctaMap[$defaultLang] ?? $dict['join_title'];

$welcomeTitle    = trim((string) ($settings['welcome_title']    ?? '')) ?: ($businessName);
$welcomeSubtitle = trim((string) ($settings['welcome_subtitle'] ?? '')) ?: ($dict['welcome_subtitle'] ?? 'Hoş geldiniz, afiyet olsun.');
$welcomeTagline  = trim((string) ($settings['welcome_tagline']  ?? '')) ?: ($dict['welcome_tagline']  ?? '');

$socialLinks = [
    'instagram' => trim((string) ($settings['social_instagram'] ?? '')),
    'facebook'  => trim((string) ($settings['social_facebook']  ?? '')),
    'tiktok'    => trim((string) ($settings['social_tiktok']    ?? '')),
    'whatsapp'  => trim((string) ($settings['social_whatsapp']  ?? '')),
    'website'   => trim((string) ($settings['social_website']   ?? '')),
    'menu'      => trim((string) ($settings['social_menu_url']  ?? '')),
    'phone'     => trim((string) ($settings['social_phone']     ?? '')),
    'address'   => trim((string) ($settings['social_address']   ?? '')),
];

$formUrl = $formUrl ?? '';
$qrImg = rtrim(BASE_URL, '/') . '/qr?size=500&margin=10&data=' . urlencode($formUrl);

$waitingCount = (int) ($waitingCount ?? count($active ?? []));
$estimatedWait = $waitingCount * max(1, (int) ($settings['average_wait_minutes'] ?? 15));

$themeDir = __DIR__ . '/themes/';

// Prefer the theme's own file: themes/{key}.php — this is how sector
// variants get genuinely different layouts instead of inheriting color tweaks.
$ownTpl = $themeDir . $theme['key'] . '.php';
if (is_file($ownTpl)) {
    $themeBodyTpl = $ownTpl;
    // Sector template owns its layout; extra CSS overrides no longer needed.
    $qdThemeQueueExtraCss = '';
} else {
    $templateKey = \App\Support\QueueThemeRegistry::templateKey($theme);
    $themeBodyTpl = $themeDir . $templateKey . '.php';
    if (!is_file($themeBodyTpl)) {
        $themeBodyTpl = $themeDir . \App\Support\QueueThemeRegistry::DEFAULT . '.php';
    }
    $qdThemeQueueExtraCss = (string) ($theme['queue_extra_css'] ?? '');
}

include $themeDir . '_base.php';
