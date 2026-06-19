<?php
/**
 * Compact social strip for door / queue display (not shown on welcome-only layout).
 *
 * Expects: $socialLinks (from display.php), $dict
 */
if (empty($socialLinks) || !is_array($socialLinks)) {
    return;
}
$items = qd_social_nav_items($socialLinks);
if ($items === []) {
    return;
}
$isLight = isset($theme['intensity']) && $theme['intensity'] === 'light';
$icon = static function (string $platform): string {
    $ic = [
        'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>',
        'facebook'  => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M13 22v-8h3l1-4h-4V7.5c0-1.1.4-2 2-2h2V2h-3c-3 0-5 1.8-5 5v3H6v4h3v8h4z"/></svg>',
        'tiktok'    => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M16 3v3a5 5 0 0 0 5 5v3a8 8 0 0 1-5-1.8V16a6 6 0 1 1-6-6h1v4h-1a2 2 0 1 0 2 2V3h4z"/></svg>',
        'whatsapp'  => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2z"/></svg>',
        'website'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>',
        'menu'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M5 4h14v16H5z"/></svg>',
        'phone'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M3 5a2 2 0 0 1 2-2h2.2a1 1 0 0 1 .96.73l1.07 3.73a1 1 0 0 1-.28 1L7.5 10a12 12 0 0 0 6.5 6.5l1.55-1.45a1 1 0 0 1 1-.28l3.73 1.07a1 1 0 0 1 .73.96V19a2 2 0 0 1-2 2h-1A15 15 0 0 1 3 6V5z"/></svg>',
        'address'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M12 22s7-7.3 7-12a7 7 0 0 0-14 0c0 4.7 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    ];
    return $ic[$platform] ?? $ic['website'];
};
$aCls = $isLight
  ? 'inline-flex items-center justify-center w-12 h-12 rounded-2xl border border-slate-200 bg-white text-slate-800 shadow-sm hover:border-slate-300 hover:shadow transition-colors'
  : 'inline-flex items-center justify-center w-12 h-12 rounded-2xl border border-white/20 bg-white/10 text-white hover:bg-white/20 transition-colors shadow-sm';
$capCls = $isLight ? 'text-slate-500' : 'text-white/80';
?>
<div class="qd-door-social mt-6 flex flex-wrap justify-center gap-2.5 max-w-lg mx-auto" role="navigation" aria-label="Social">
  <?php foreach ($items as $item): ?>
    <a
      class="<?php echo qd_safe($aCls); ?>"
      href="<?php echo qd_safe($item['url']); ?>"
      target="_blank"
      rel="noopener"
      title="<?php echo qd_safe($item['platform']); ?>"
    >
      <?php echo $icon($item['platform']); ?>
    </a>
  <?php endforeach; ?>
</div>
<div class="mt-2 text-center text-[11px] font-semibold tracking-wide <?php echo qd_safe($capCls); ?>">
  <?php echo qd_safe($dict['follow_us'] ?? 'Bizi takip edin'); ?>
</div>
