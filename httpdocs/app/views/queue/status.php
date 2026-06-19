<?php
/**
 * Queue status page — mobile-first, brand-aware.
 *
 * Vars: $business, $settings, $entry, $status, $csrf_token
 */

require_once __DIR__ . '/_helpers.php';

[$themeColor, $accentColor] = qd_accent_pair($settings);
$businessName = qd_business_name($business);

$lang  = $entry['language'] ?? ($settings['default_language'] ?? 'tr');
$isRtl = $lang === 'ar';
$dict  = qd_dict($lang);

$firstName = trim((string) ($entry['name'] ?? ''));

// Oturdu / afiyet ekranındaki sosyal ve Google yorum linki işletme ayarlarından
// gelir. Google yorum URL'si yoksa place_id'den otomatik üretilebilir fakat
// manuel alan daha güvenilir; işletme sahibi kendi seçer.
$socialLinks = [
    'instagram'     => trim((string) ($settings['social_instagram'] ?? '')),
    'facebook'      => trim((string) ($settings['social_facebook']  ?? '')),
    'tiktok'        => trim((string) ($settings['social_tiktok']    ?? '')),
    'whatsapp'      => trim((string) ($settings['social_whatsapp']  ?? '')),
    'website'       => trim((string) ($settings['social_website']   ?? '')),
    'menu'          => trim((string) ($settings['social_menu_url']  ?? '')),
    'phone'         => trim((string) ($settings['social_phone']     ?? '')),
    'address'       => trim((string) ($settings['social_address']   ?? '')),
];
$googleReviewUrl = trim((string) ($settings['social_google_review'] ?? ''));

$socialUrl = static function (string $p, string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('#^https?://#i', $v)) return $v;
    $handle = ltrim($v, '@');
    switch ($p) {
        case 'instagram': return 'https://instagram.com/' . rawurlencode($handle);
        case 'facebook':  return 'https://facebook.com/' . rawurlencode($handle);
        case 'tiktok':    return 'https://tiktok.com/@' . rawurlencode($handle);
        case 'whatsapp':
            $d = preg_replace('/\D+/', '', $v);
            return $d ? 'https://wa.me/' . $d : '';
        case 'phone':
            $d = preg_replace('/[^\d+]/', '', $v);
            return $d ? 'tel:' . $d : '';
        case 'website':
        case 'menu':      return 'https://' . ltrim($v, '/');
        case 'address':   return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($v);
    }
    return $v;
};
$socialItems = [];
foreach (['instagram','facebook','tiktok','whatsapp','website','menu','phone','address'] as $p) {
    $url = $socialUrl($p, (string) ($socialLinks[$p] ?? ''));
    if ($url === '') continue;
    $socialItems[] = ['platform' => $p, 'url' => $url, 'value' => $socialLinks[$p]];
}
$socialIcon = static function (string $p): string {
    $m = [
        'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>',
        'facebook'  => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M13 22v-8h3l1-4h-4V7.5c0-1.1.4-2 2-2h2V2h-3c-3 0-5 1.8-5 5v3H6v4h3v8h4z"/></svg>',
        'tiktok'    => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M16 3v3a5 5 0 0 0 5 5v3a8 8 0 0 1-5-1.8V16a6 6 0 1 1-6-6h1v4h-1a2 2 0 1 0 2 2V3h4z"/></svg>',
        'whatsapp'  => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2z"/></svg>',
        'website'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>',
        'menu'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M5 4h14v16H5z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'phone'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M3 5a2 2 0 0 1 2-2h2.2a1 1 0 0 1 .96.73l1.07 3.73a1 1 0 0 1-.28 1L7.5 10a12 12 0 0 0 6.5 6.5l1.55-1.45a1 1 0 0 1 1-.28l3.73 1.07a1 1 0 0 1 .73.96V19a2 2 0 0 1-2 2h-1A15 15 0 0 1 3 6V5z"/></svg>',
        'address'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5"><path d="M12 22s7-7.3 7-12a7 7 0 0 0-14 0c0 4.7 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    ];
    return $m[$p] ?? '';
};
?>
<!DOCTYPE html>
<html lang="<?php echo qd_safe($lang); ?>" dir="<?php echo $isRtl ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, , viewport-fit=cover">
<meta name="theme-color" content="<?php echo qd_safe($themeColor); ?>">
<title><?php echo qd_safe($businessName); ?> — #<?php echo (int) $entry['queue_number']; ?></title>
<meta name="csrf-token" content="<?php echo qd_safe($csrf_token ?? ''); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  :root { --theme: <?php echo qd_safe($themeColor); ?>; --accent: <?php echo qd_safe($accentColor); ?>; }
  html, body { -webkit-tap-highlight-color: transparent; }
  body {
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    color: #0f172a;
    background:
      radial-gradient(120% 60% at 50% 0%, color-mix(in srgb, var(--accent) 18%, transparent) 0%, transparent 55%),
      linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    min-height: 100dvh;
  }
  .hero { background: linear-gradient(135deg, var(--theme) 0%, color-mix(in srgb, var(--theme) 70%, #111827) 100%); color:#fff; }
  .qd-logo { display:inline-flex; align-items:center; justify-content:center; overflow:hidden; border-radius: 9999px; background:#fff; flex-shrink:0; box-sizing:border-box; }
  .qd-logo:has(> img) { padding: 4px; }
  .qd-logo img { width:100%; height:100%; object-fit: contain; object-position: center; display: block; }
  .qd-logo-initials { font-weight:800; color: var(--theme); }
  .card { background:#fff; border:1px solid #e2e8f0; border-radius: 24px; box-shadow: 0 10px 30px -15px rgba(15,23,42,.15); }
  .ticket { position: relative; background: linear-gradient(135deg, #ffffff, #fafafa); border-radius: 24px; border: 1px solid #e2e8f0; }
  .ticket::before, .ticket::after {
    content:''; position: absolute; top: 50%; width: 22px; height: 22px; border-radius: 9999px; background: #f8fafc;
    box-shadow: inset 0 1px 2px rgba(15,23,42,.1); transform: translateY(-50%);
  }
  .ticket::before { left: -11px; } .ticket::after { right: -11px; }
  .clock { font-variant-numeric: tabular-nums; }
  .badge { padding: 5px 10px; border-radius: 9999px; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
  .badge-wait { background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--theme); border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent); }
  .badge-ready{ background: #dcfce7; color: #065f46; border: 1px solid #86efac; }
  .badge-seated{ background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
  .badge-off { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
  @keyframes pulse-num { 0%,100%{ transform: scale(1) } 50%{ transform: scale(1.04) } }
  .live { animation: pulse-num 1.8s ease-in-out infinite; }
  .pill {
    display:inline-flex; align-items:center; gap:.5rem; padding:.65rem 1rem;
    border-radius:9999px; background:#fff; border:1px solid #e2e8f0; color:#0f172a;
    font-weight:700; font-size:13px; text-decoration:none; transition: transform .1s, background .15s;
  }
  .pill:hover { background:#f8fafc; }
  .pill:active { transform: scale(.98); }
  .pill svg { color: var(--accent); }
  .cta-google {
    display:inline-flex; align-items:center; justify-content:center; gap:.6rem; width:100%;
    padding: 14px 18px; border-radius: 16px; background: #fff; color: #0f172a;
    font-weight: 800; font-size: 15px; border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px -12px rgba(15,23,42,.15);
  }
  .cta-google:hover { background:#f8fafc; }
</style>
</head>
<body>

<div class="hero px-4 sm:px-5 pt-6 sm:pt-8 pb-24">
  <div class="max-w-md mx-auto flex items-start sm:items-center justify-between gap-2 min-w-0">
    <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
      <?php echo qd_logo_markup($business, 48); ?>
      <div class="min-w-0">
        <div class="text-white/60 text-[10px] sm:text-[11px] uppercase tracking-widest"><?php echo qd_safe($dict['welcome']); ?></div>
        <div class="text-base sm:text-lg font-extrabold leading-tight line-clamp-2 sm:truncate sm:max-w-[200px]"><?php echo qd_safe($businessName); ?></div>
      </div>
    </div>
    <div class="text-end">
      <div class="text-white/60 text-[10px] uppercase tracking-widest"><?php echo qd_safe($dict['ticket']); ?></div>
      <div class="text-2xl font-black" style="color: var(--accent)">#<?php echo (int) $entry['queue_number']; ?></div>
    </div>
  </div>
</div>

<div class="max-w-md mx-auto px-5 -mt-16 pb-14 relative z-10 space-y-5">

  <!-- Kişiselleştirilmiş karşılama -->
  <div id="welcomeCard" class="card px-5 py-4">
    <div class="text-[10px] font-black tracking-[0.2em] uppercase text-slate-400">
      <?php echo qd_safe($firstName !== '' ? ($dict['welcome'] . ', ' . $firstName) : $dict['welcome_known']); ?>
    </div>
    <div class="mt-1 text-slate-700 text-sm leading-relaxed">
      <?php echo qd_safe(str_replace(
          ['{name}', '{business}'],
          [$firstName !== '' ? $firstName : $dict['welcome_known'], $businessName],
          $dict['join_confirmed']
      )); ?>
    </div>
  </div>

  <div id="mainCard" class="ticket px-6 py-7 text-center">
    <div class="flex justify-center mb-3">
      <span id="stateBadge" class="badge badge-wait"><?php echo qd_safe($dict['waiting']); ?></span>
    </div>
    <div id="headline" class="text-sm text-slate-500 font-semibold"><?php echo qd_safe($dict['position_of']); ?></div>
    <div id="bigNumber" class="mt-2 clock live text-[100px] leading-none font-black" style="color: var(--theme)">—</div>
    <div id="aheadText" class="mt-3 text-slate-500"><?php echo qd_safe(str_replace('{n}', '0', $dict['ahead'])); ?></div>

    <div class="mt-6 grid grid-cols-2 gap-3">
      <div class="bg-slate-50 rounded-2xl p-4">
        <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold"><?php echo qd_safe($dict['eta']); ?></div>
        <div class="mt-1 text-2xl font-extrabold clock" id="etaText">—</div>
        <div class="text-[11px] text-slate-400"><?php echo qd_safe($dict['minutes']); ?></div>
      </div>
      <div class="bg-slate-50 rounded-2xl p-4">
        <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold"><?php echo qd_safe($dict['party_short']); ?></div>
        <div class="mt-1 text-2xl font-extrabold clock"><?php echo (int) $entry['party_size']; ?></div>
        <div class="text-[11px] text-slate-400"><?php echo qd_safe($dict['people']); ?></div>
      </div>
    </div>

    <div class="mt-5 text-[11px] text-slate-400 clock" id="lastUpdated"><?php echo qd_safe($dict['last_update']); ?>: —</div>
  </div>

  <!-- Hazır / çağrıldı kartı -->
  <div id="readyCard" class="hidden card p-6 text-center" style="background: linear-gradient(135deg,#ecfdf5,#d1fae5); border-color:#6ee7b7">
    <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center" style="background:#10b981">
      <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
    </div>
    <div class="mt-3 text-2xl font-black text-emerald-900"><?php echo qd_safe($dict['ready_title']); ?></div>
    <div class="mt-1 text-emerald-800/80 text-sm"><?php echo qd_safe($dict['ready_sub']); ?></div>
    <div id="tableLabelWrap" class="hidden mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-emerald-200 text-emerald-900 font-extrabold text-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M6 7v10m12-10v10M4 17h16"/></svg>
      <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-700"><?php echo qd_safe($dict['your_table_is']); ?></span>
      <span id="tableLabelText" class="font-black">—</span>
    </div>
  </div>

  <!-- Oturdu / afiyet + sosyal + google yorum -->
  <div id="seatedCard" class="hidden space-y-4">
    <div class="card p-6 text-center">
      <div class="mx-auto w-16 h-16 rounded-full flex items-center justify-center mb-3" style="background: color-mix(in srgb, var(--accent) 18%, #fff)">
        <svg class="w-9 h-9" style="color: var(--accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9.5V20h5v-6h8v6h5V9.5L12 3 3 9.5z"/></svg>
      </div>
      <div class="text-2xl font-black text-slate-900"><?php echo qd_safe($dict['goodbye_title']); ?></div>
      <div class="mt-1 text-slate-500 text-sm"><?php echo qd_safe($dict['goodbye_sub']); ?></div>
    </div>

    <?php if ($googleReviewUrl !== ''): ?>
    <a href="<?php echo qd_safe($googleReviewUrl); ?>" target="_blank" rel="noopener" class="cta-google">
      <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22 12.2c0-.8-.1-1.5-.2-2.2H12v4.3h5.6c-.2 1.3-1 2.4-2.1 3.1v2.6h3.4c2-1.8 3.1-4.5 3.1-7.8z"/><path fill="#34A853" d="M12 22c2.8 0 5.2-.9 6.9-2.5l-3.4-2.6c-.9.6-2.1 1-3.5 1-2.7 0-5-1.8-5.8-4.3H2.7v2.7A10 10 0 0 0 12 22z"/><path fill="#FBBC05" d="M6.2 13.5c-.2-.6-.3-1.2-.3-1.9s.1-1.3.3-1.9V7H2.7a10 10 0 0 0 0 9.1l3.5-2.6z"/><path fill="#EA4335" d="M12 5.8c1.5 0 2.9.5 4 1.5l3-3A10 10 0 0 0 2.7 7l3.5 2.7C7 7.6 9.3 5.8 12 5.8z"/></svg>
      <span><?php echo qd_safe($dict['leave_review']); ?></span>
    </a>
    <?php endif; ?>

    <?php if (!empty($socialItems)): ?>
    <div class="card p-5">
      <div class="text-[10px] font-black tracking-[0.2em] uppercase text-slate-400 text-center"><?php echo qd_safe($dict['follow_social']); ?></div>
      <div class="mt-3 flex flex-wrap justify-center gap-2">
        <?php foreach ($socialItems as $item): ?>
          <a class="pill" target="_blank" rel="noopener" href="<?php echo qd_safe($item['url']); ?>">
            <?php echo $socialIcon($item['platform']); ?>
            <?php
              $label = $item['platform'] === 'menu' ? $dict['menu']
                : ($item['platform'] === 'website' ? $dict['website']
                : ($item['platform'] === 'phone' ? $dict['call_us']
                : ($item['platform'] === 'address' ? ($item['value'])
                : '@' . ltrim(preg_replace('#^https?://[^/]+/@?#i', '', (string) $item['value']), '@'))));
            ?>
            <span><?php echo qd_safe($label); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <button id="shareBtn" class="pill w-full justify-center" type="button">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7M16 6l-4-4-4 4M12 2v14"/></svg>
      <span><?php echo qd_safe($dict['share_restaurant']); ?></span>
    </button>
  </div>

  <!-- Gelmedi / iptal / kapalı -->
  <div id="closedCard" class="hidden card p-6 text-center">
    <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center mb-3 bg-slate-100">
      <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12"/></svg>
    </div>
    <div class="text-xl font-black text-slate-900" id="closedTitle"><?php echo qd_safe($dict['no_show_title']); ?></div>
    <div class="mt-1 text-slate-500 text-sm" id="closedSub"><?php echo qd_safe($dict['no_show_sub']); ?></div>
  </div>

  <div id="detailsCard" class="card p-5 text-sm">
    <div class="flex justify-between py-1">
      <span class="text-slate-500"><?php echo qd_safe($dict['name']); ?></span>
      <span class="font-semibold text-slate-900"><?php echo qd_safe(trim(($entry['name'] ?? '') . ' ' . ($entry['surname'] ?? ''))); ?></span>
    </div>
    <div class="flex justify-between py-1 border-t border-slate-100">
      <span class="text-slate-500"><?php echo qd_safe($dict['phone']); ?></span>
      <span class="font-semibold text-slate-900 font-mono"><?php echo qd_safe($entry['phone'] ?? ''); ?></span>
    </div>
    <?php if (!empty($entry['email'])): ?>
      <div class="flex justify-between py-1 border-t border-slate-100">
        <span class="text-slate-500"><?php echo qd_safe($dict['email']); ?></span>
        <span class="font-semibold text-slate-900 truncate max-w-[180px]"><?php echo qd_safe($entry['email']); ?></span>
      </div>
    <?php endif; ?>
  </div>

  <button id="cancelBtn" class="w-full text-center text-slate-500 underline underline-offset-4 hover:text-slate-800 py-3 text-sm">
    <?php echo qd_safe($dict['cancel_link']); ?>
  </button>

  <p class="text-center text-[11px] text-slate-400 leading-relaxed">
    <?php echo qd_safe($dict['keep_open']); ?><br>Powered by Qordy
  </p>
</div>

<script>
(function(){
  const DICT = <?php echo json_encode([
      'ahead' => $dict['ahead'], 'position_of' => $dict['position_of'],
      'ready_title' => $dict['ready_title'], 'ready_sub' => $dict['ready_sub'],
      'enjoy' => $dict['enjoy'], 'seated' => $dict['seated'], 'notified' => $dict['notified'],
      'waiting' => $dict['waiting'], 'inactive' => $dict['inactive'],
      'cancel_confirm' => $dict['cancel_confirm'], 'last_update' => $dict['last_update'],
      'no_show_title' => $dict['no_show_title'], 'no_show_sub' => $dict['no_show_sub'],
      'goodbye_title' => $dict['goodbye_title'], 'goodbye_sub' => $dict['goodbye_sub'],
  ], JSON_UNESCAPED_UNICODE); ?>;
  const queueId = <?php echo json_encode($entry['queue_id'] ?? ''); ?>;
  const businessName = <?php echo json_encode($businessName, JSON_UNESCAPED_UNICODE); ?>;
  const vibrateKey = 'qdQueueVibrated:' + queueId;
  const bigNumber = document.getElementById('bigNumber');
  const aheadText = document.getElementById('aheadText');
  const etaText   = document.getElementById('etaText');
  const stateBadge= document.getElementById('stateBadge');
  const headline  = document.getElementById('headline');
  const lastUpdated = document.getElementById('lastUpdated');
  const readyCard = document.getElementById('readyCard');
  const seatedCard= document.getElementById('seatedCard');
  const closedCard= document.getElementById('closedCard');
  const mainCard  = document.getElementById('mainCard');
  const welcomeCard = document.getElementById('welcomeCard');
  const detailsCard = document.getElementById('detailsCard');
  const cancelBtn = document.getElementById('cancelBtn');
  const tableLabelWrap = document.getElementById('tableLabelWrap');
  const tableLabelText = document.getElementById('tableLabelText');
  const shareBtn = document.getElementById('shareBtn');
  const closedTitle = document.getElementById('closedTitle');
  const closedSub   = document.getElementById('closedSub');

  function setBadge(cls, txt){ stateBadge.className = 'badge ' + cls; stateBadge.textContent = txt; }
  function hide(el){ if (el) el.classList.add('hidden'); }
  function show(el){ if (el) el.classList.remove('hidden'); }

  function vibrateOnce(){
    // Titreşim yalnızca bu bilet için bir kez. Sayfa yenilense ya da poll
    // tekrar NOTIFIED görse bile sessionStorage'da işaretli ise tekrar titretmez.
    try {
      if (sessionStorage.getItem(vibrateKey) === '1') return;
      sessionStorage.setItem(vibrateKey, '1');
    } catch (e) {}
    try { if (navigator.vibrate) navigator.vibrate([180, 90, 180]); } catch (e) {}
    try {
      if (typeof document !== 'undefined' && 'Notification' in window && Notification.permission === 'granted') {
        new Notification(DICT.ready_title, { body: DICT.ready_sub, tag: 'qordy-queue-' + queueId });
      }
    } catch (e) {}
  }

  function render(s){
    if (!s) return;
    const status = s.status;
    if (status === 'WAITING') {
      bigNumber.textContent = s.position || '—';
      aheadText.textContent = DICT.ahead.replace('{n}', String(s.ahead || 0));
      etaText.textContent   = (s.eta_minutes ?? '—');
      headline.textContent  = DICT.position_of;
      setBadge('badge-wait', DICT.waiting);
      hide(readyCard); hide(seatedCard); hide(closedCard);
      show(mainCard); show(welcomeCard); show(detailsCard); show(cancelBtn);
    } else if (status === 'NOTIFIED') {
      bigNumber.textContent = '✓';
      aheadText.textContent = DICT.ready_sub;
      etaText.textContent   = '0';
      headline.textContent  = DICT.ready_title;
      setBadge('badge-ready', DICT.notified);
      show(readyCard);
      if (s.table_label) {
        tableLabelText.textContent = s.table_label;
        tableLabelWrap.classList.remove('hidden');
      } else {
        tableLabelWrap.classList.add('hidden');
      }
      hide(seatedCard); hide(closedCard);
      show(mainCard); show(welcomeCard); show(detailsCard); hide(cancelBtn);
      vibrateOnce();
    } else if (status === 'SEATED') {
      hide(readyCard); hide(closedCard);
      hide(mainCard); hide(welcomeCard); hide(detailsCard); hide(cancelBtn);
      show(seatedCard);
      setBadge('badge-seated', DICT.seated);
    } else if (['CANCELLED','NO_SHOW','EXPIRED'].includes(status)) {
      hide(readyCard); hide(seatedCard);
      hide(mainCard); hide(welcomeCard); hide(detailsCard); hide(cancelBtn);
      show(closedCard);
      if (status === 'CANCELLED') {
        closedTitle.textContent = DICT.inactive;
        closedSub.textContent = '';
      } else {
        closedTitle.textContent = DICT.no_show_title;
        closedSub.textContent = DICT.no_show_sub;
      }
      setBadge('badge-off', status);
    }
    const d = new Date();
    lastUpdated.textContent = DICT.last_update + ': ' +
      String(d.getHours()).padStart(2,'0') + ':' +
      String(d.getMinutes()).padStart(2,'0') + ':' +
      String(d.getSeconds()).padStart(2,'0');
  }

  async function poll(){
    try {
      const r = await fetch('/api/sira/bilet/' + encodeURIComponent(queueId), { headers:{Accept:'application/json'}, credentials:'same-origin' });
      const j = await r.json();
      if (j && j.success) render(j.status);
    } catch(e) {}
  }

  render(<?php echo json_encode($status); ?>);
  poll(); setInterval(poll, 8000);

  // İlk açılışta izin varsa bildirim izni iste (tarayıcı kapanabilir diye).
  try {
    if ('Notification' in window && Notification.permission === 'default') {
      setTimeout(() => { try { Notification.requestPermission(); } catch (e) {} }, 2500);
    }
  } catch (e) {}

  if (cancelBtn) cancelBtn.addEventListener('click', async () => {
    if (!confirm(DICT.cancel_confirm)) return;
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const fd = new FormData(); fd.append('csrf_token', csrf);
    try {
      const r = await fetch('/sira/iptal/' + encodeURIComponent(queueId), {
        method:'POST', body: fd,
        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
        credentials:'same-origin'
      });
      const j = await r.json();
      if (j && j.success) render({status:'CANCELLED'});
    } catch(e) {}
  });

  if (shareBtn) shareBtn.addEventListener('click', async () => {
    const url = window.location.origin + '/';
    const shareData = { title: businessName, text: businessName, url };
    try {
      if (navigator.share) { await navigator.share(shareData); return; }
    } catch (e) {}
    try {
      await navigator.clipboard.writeText(url);
      const orig = shareBtn.textContent;
      shareBtn.innerHTML = '<span>✓</span>';
      setTimeout(() => shareBtn.textContent = orig, 1500);
    } catch (e) {}
  });
})();
</script>
</body>
</html>
