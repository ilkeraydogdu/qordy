<?php
/**
 * Base layout partial for all door-display themes.
 *
 * Expects:
 *   $theme            - resolved theme array (QueueThemeRegistry::resolve)
 *   $themeColor       - primary color
 *   $accentColor      - accent color
 *   $businessName     - display name
 *   $cta, $title, $subtitle - localized copy
 *   $qrImg, $formUrl
 *   $active (array), $waitingCount (int), $estimatedWait (int)
 *   $dict             - i18n dictionary
 *   $settings         - settings array
 *   $business         - business array
 *   $themeBodyTpl     - file path of the body template to render
 */
require_once __DIR__ . '/../_helpers.php';

$showLogo         = !isset($settings['show_logo']) || !empty($settings['show_logo']);
// Canlı sıra / tahmini süre kapı ekranında varsayılan kapalı; müşteri kendi
// bilet ekranında (form sonrası) konum ve süreyi görür. Açmak: İşletme → Sıra → Ayarlar.
$showActiveNums   = qd_queue_bool_setting($settings['show_active_numbers'] ?? null);
$showEta          = qd_queue_bool_setting($settings['show_estimated_wait'] ?? null);
$showWaitingCount = qd_queue_bool_setting($settings['show_waiting_count'] ?? null);
$showPowered      = !isset($settings['show_powered_by']) || !empty($settings['show_powered_by']);
// $isAcceptingQueue is resolved upstream in display.php (supports ?mode= override)
if (!isset($isAcceptingQueue)) {
    $isAcceptingQueue = !empty($settings['is_accepting_queue']);
}

$qdThemeQueueExtraCss = $qdThemeQueueExtraCss ?? '';

// When not accepting queue, render the welcome / marketing layout instead of
// the theme-specific queue screen.
if (!$isAcceptingQueue) {
    $themeBodyTpl = __DIR__ . '/_welcome.php';
}

$fontParam = $theme['font'] ?? '';
$fontFamily = $theme['font_family'] ?? "ui-sans-serif,system-ui,sans-serif";
$bgUrl = trim((string) ($settings['display_bg_image_url'] ?? ''));

$languages = $settings['languages'] ?? ['tr'];
$defaultLang = $settings['default_language'] ?? 'tr';
?><!DOCTYPE html>
<html lang="<?php echo qd_safe($defaultLang); ?>" dir="<?php echo $defaultLang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo qd_safe($businessName); ?><?php if ($isAcceptingQueue): ?> — <?php echo qd_safe($dict['all_full']); ?><?php else: ?> — <?php echo qd_safe($welcomeTagline !== '' ? $welcomeTagline : ($dict['welcome_tagline'] ?? '')); ?><?php endif; ?></title>
<?php if ($fontParam): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?php echo $fontParam; ?>&display=swap">
<?php endif; ?>
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root {
  --theme: <?php echo qd_safe($themeColor); ?>;
  --accent: <?php echo qd_safe($accentColor); ?>;
}
html {
  height: 100%;
  height: 100dvh;
  overflow: hidden;
}
body {
  font-family: <?php echo $fontFamily; ?>;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
/* Tek ekran: scroll yok; içerik kalan yükseklik ve küçülen fontlarla sığıyor. */
body.qd-door {
  margin: 0;
  min-height: 100%;
  min-height: 100dvh;
  max-height: 100dvh;
  height: 100dvh;
  overflow: hidden;
  box-sizing: border-box;
}
.qd-logo {
  display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 9999px;
  flex-shrink: 0;
  background: rgba(255, 255, 255, 0.1);
  box-sizing: border-box;
}
/* Daire çizgisine bitişik "kesik" hissi: görseli hafif içeri al, contain ile tam göster. */
.qd-logo:has(> img) { padding: 7px; }
.qd-logo img { width: 100%; height: 100%; object-fit: contain; object-position: center; display: block; }
.qd-logo-initials { font-weight: 800; letter-spacing: .02em; }
/* Kalan sütun yüksekliğine orantılı QR; üst/alt sınır: slot boyutu */
.qd-qr-slot {
  flex: 1 1 0;
  min-height: 0;
  min-width: 0;
  display: flex;
  flex-direction: row;
  align-items: stretch;
  justify-content: center;
  width: 100%;
  padding: 0.1rem 0;
}
.qd-qr-slot > * {
  max-width: 100%;
  max-height: 100%;
  box-sizing: border-box;
}
img.qd-qr-img, .qd-qr-img {
  display: block;
  width: auto;
  height: auto;
  object-fit: contain;
  max-width: 100%;
  max-height: 100%;
}
.clock { font-variant-numeric: tabular-nums; }
@keyframes qd-pulse { 0% { transform: scale(.9); opacity:.8 } 80%,100% { transform: scale(1.45); opacity: 0 } }
.pulse::before { content:''; position:absolute; inset:0; border-radius:9999px; background: var(--accent); animation: qd-pulse 1.8s ease-out infinite; z-index:0; }
@keyframes qd-floaty { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-6px) } }
.floaty { animation: qd-floaty 4s ease-in-out infinite; }
</style>
<?php if (!empty($qdThemeQueueExtraCss) && !empty($isAcceptingQueue)) { ?>
<style id="qd-theme-variant">
/* Queue theme library variant (Sıra · QR modu) */
<?php echo $qdThemeQueueExtraCss; ?>

</style>
<?php } ?>
</head>
<body class="qd-door">
<?php include $themeBodyTpl; ?>

<script>
(function() {
  const qrImage = document.getElementById('qdQr');
  const countdownEl = document.getElementById('qdCountdown');
  const waitingEl = document.getElementById('qdWaiting');
  const etaEl = document.getElementById('qdEta');
  const clockEl = document.getElementById('qdClock');
  const activeListEl = document.getElementById('qdActiveList');

  let secondsLeft = 0;
  let lastAccepting = <?php echo $isAcceptingQueue ? 'true' : 'false'; ?>;
  let qrRefreshBadge = document.getElementById('qdQrRefresh');

  function updateClock() {
    if (!clockEl) return;
    const d = new Date();
    clockEl.textContent = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
  }

  function pingRefresh() {
    if (!qrRefreshBadge) return;
    qrRefreshBadge.classList.remove('opacity-0');
    qrRefreshBadge.classList.add('opacity-100');
    setTimeout(() => {
      qrRefreshBadge.classList.remove('opacity-100');
      qrRefreshBadge.classList.add('opacity-0');
    }, 1200);
  }

  function renderActive(list) {
    if (!activeListEl) return;
    // Themes may publish a custom renderer (window.__qdRenderActive) for
    // their own composition (Roman numerals, scattered stickers, dot
    // ribbon, etc.). Fall back to the generic grid when none is set.
    if (typeof window.__qdRenderActive === 'function') {
      try { window.__qdRenderActive(list); return; } catch (e) {}
    }
    if (!list || list.length === 0) {
      activeListEl.innerHTML = '<div class="qd-empty">' + (activeListEl.dataset.empty || '') + '</div>';
      return;
    }
    activeListEl.innerHTML = list.slice(0, 12).map((e, i) => {
      const cls = 'qd-num ' + (i === 0 ? 'qd-num-first' : (i === 1 ? 'qd-num-second' : 'qd-num-rest'));
      const pulse = i === 0 ? '<span class="absolute inset-0 rounded-2xl pulse"></span>' : '';
      return '<div class="' + cls + '">' + pulse + '<span class="relative">' + (e.queue_number || '—') + '</span></div>';
    }).join('');
  }

  // Detect admin preview overrides (?mode=welcome|queue). When the page is
  // rendered with a forced mode we must NOT auto-reload on server state drift
  // — the admin explicitly chose the mode to preview and the server would
  // otherwise flip it back.
  var qdForcedMode = (new URLSearchParams(location.search)).get('mode');
  var qdIsPreview  = (new URLSearchParams(location.search)).get('preview') === '1';

  function refresh() {
    fetch('/api/sira/token', { headers: { 'Accept':'application/json' }, credentials: 'same-origin' })
      .then(r => r.json())
      .then(j => {
        if (!j || !j.success) return;
        secondsLeft = j.seconds_left || 0;
        if (!qdForcedMode && typeof j.is_accepting_queue !== 'undefined' && j.is_accepting_queue !== lastAccepting) {
          // Operating mode flipped on the server (owner toggled) — reload to
          // switch between welcome/queue layouts.
          location.reload();
          return;
        }
        if (j.form_url && qrImage) {
          const src = '/qr?size=500&margin=10&data=' + encodeURIComponent(j.form_url);
          if (qrImage.getAttribute('src') !== src) { qrImage.src = src; pingRefresh(); }
        }
        if (typeof j.waiting_count !== 'undefined' && waitingEl) waitingEl.textContent = j.waiting_count;
        if (typeof j.estimated_wait !== 'undefined' && etaEl) etaEl.textContent = j.estimated_wait;
        if (Array.isArray(j.active)) renderActive(j.active);
      })
      .catch(() => {});
  }

  // Countdown kept for internal rotation heuristic (refresh near the end),
  // but we intentionally do NOT surface the raw seconds to the viewer anymore.
  if (qrImage) {
    setInterval(() => {
      if (secondsLeft > 0) secondsLeft--;
      if (countdownEl) countdownEl.textContent = '';
      if (secondsLeft <= 3) refresh();
    }, 1000);
  }

  setInterval(updateClock, 1000);
  updateClock();
  refresh();
  setInterval(refresh, 15000);
  // The display self-refreshes every 30 minutes to pick up settings changes,
  // but we skip that in preview/forced-mode so admin previews stay put.
  if (!qdIsPreview && !qdForcedMode) {
    setTimeout(() => location.reload(), 30 * 60 * 1000);
  }
})();
</script>
</body>
</html>
