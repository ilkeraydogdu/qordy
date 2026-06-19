<?php
/**
 * MINIMAL theme — Swiss typography / Muji / Apple Store.
 *
 * Composition: single vertical axis, extreme whitespace, thin hair
 * rules, tiny all-caps metadata bar, QR as the single dense element,
 * queue numbers as a thin dot-separated ribbon.
 * Inspiration: Dieter Rams, Kinfolk magazine, Apple Store retail signage.
 */
?>
<style>
  body {
    background: #ffffff;
    color: #0f172a;
    font-weight: 300;
    letter-spacing: -.005em;
    <?php if ($bgUrl): ?>background: url('<?php echo qd_safe($bgUrl); ?>') center/cover; <?php endif; ?>
  }
  .mini-wrap {
    max-width: 720px; width: 100%; margin: 0 auto;
    box-sizing: border-box;
    height: 100dvh; max-height: 100dvh; min-height: 0;
    display: flex; flex-direction: column; overflow: hidden;
    padding: clamp(12px, 2vh, 24px) clamp(16px, 3vw, 28px);
  }
  .meta-bar {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 10px; letter-spacing: .3em; text-transform: uppercase;
    font-weight: 500; color: #64748b;
  }
  .hair { height: 1px; background: #e2e8f0; }
  .hair.strong { background: #0f172a; }
  .serif-num { font-feature-settings: "tnum" 1; }
  .qr-plate {
    display: flex; align-items: center; justify-content: center;
    padding: clamp(6px, 1.2vmin, 12px); background: #fff;
    border: 1px solid #0f172a; max-width: 100%; max-height: 100%;
    box-sizing: border-box;
  }
  .qd-qr-slot .qr-plate { width: 100%; height: 100%; min-height: 0; }
  .qr-plate img { display: block; }
  .dot-queue { display: flex; flex-wrap: wrap; justify-content: center; gap: 0 8px; font-variant-numeric: tabular-nums; font-weight: 500; font-size: clamp(12px, 2.5vw, 18px); color: #0f172a; }
  .dot-queue .dot { color: #cbd5e1; font-weight: 300; }
  .dot-queue .first { font-weight: 700; color: var(--accent, #0f172a); position: relative; }
  .dot-queue .first::after { content: ''; position: absolute; left: 50%; bottom: -6px; width: 6px; height: 6px; border-radius: 50%; background: var(--accent); transform: translateX(-50%); }
  .lead-num { font-weight: 200; font-size: clamp(40px, 10vw, 120px); line-height: .9; letter-spacing: -.05em; font-feature-settings: "tnum" 1; }
  .lead-unit { font-size: .28em; letter-spacing: .2em; text-transform: uppercase; color: #64748b; font-weight: 500; vertical-align: super; margin-left: 12px; }
  .qd-empty { font-size: 9px; letter-spacing: .2em; text-transform: uppercase; color: #94a3b8; text-align: center; padding: 8px 0; }
</style>

<div class="mini-wrap">
  <!-- Metadata bar -->
  <div class="shrink-0">
  <div class="meta-bar">
    <span class="truncate max-w-[30%]"><?php echo qd_safe(strtoupper($businessName)); ?></span>
    <span id="qdClock" class="clock"></span>
    <span class="text-right max-w-[30%]"><?php echo strtoupper(implode(' · ', $languages)); ?></span>
  </div>

  <div class="hair mt-2"></div>
  </div>

  <!-- Hero title: tiny tag + big thin number -->
  <div class="shrink-0 text-center pt-2">
    <?php if ($showLogo): ?>
      <div class="inline-block mb-2"><?php echo qd_logo_markup($business, 44, 'ring-1 ring-slate-900'); ?></div>
    <?php endif; ?>
    <div class="text-[10px] tracking-[0.3em] uppercase text-slate-500 font-medium"><?php echo qd_safe($dict['all_full']); ?></div>
    <h1 class="mt-1 font-light text-2xl sm:text-3xl tracking-tight max-w-[480px] mx-auto line-clamp-2"><?php echo qd_safe($title); ?></h1>
    <p class="mt-1 text-sm text-slate-500 max-w-[420px] mx-auto leading-snug line-clamp-2"><?php echo qd_safe($subtitle); ?></p>
  </div>

  <!-- QR: fills middle band -->
  <div class="qd-qr-slot w-full min-h-0 flex-1">
    <div class="qr-plate relative w-full h-full min-h-0 min-w-0 max-w-sm mx-auto">
      <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
      <div id="qdQrRefresh" class="absolute -bottom-1 right-0 text-[8px] tracking-[0.2em] uppercase text-slate-400 opacity-0 transition-opacity duration-500 pointer-events-none">
        · <?php echo qd_safe($dict['qr_rotating']); ?>
      </div>
      <span id="qdCountdown" class="hidden"></span>
    </div>
  </div>

  <div class="shrink-0 text-center pt-1">
    <div class="text-base sm:text-lg font-medium tracking-tight text-slate-900 line-clamp-2"><?php echo qd_safe($cta); ?></div>
    <div class="mt-0.5 text-xs sm:text-sm text-slate-600 max-w-md mx-auto leading-snug font-medium px-2 line-clamp-2"><?php echo qd_safe($dict['scan_cta']); ?></div>
    <div class="mt-1"><?php require __DIR__ . '/_door_social_row.php'; ?></div>
  </div>

  <!-- Big lead stat (count or ETA) -->
  <?php if ($showWaitingCount || $showEta): ?>
  <div class="shrink-0 pt-1 grid grid-cols-2 gap-3 sm:gap-5">
    <?php if ($showWaitingCount): ?>
    <div class="text-center border-r border-slate-200">
      <div id="qdWaiting" class="lead-num serif-num"><?php echo (int) $waitingCount; ?></div>
      <div class="text-[10px] tracking-[0.3em] uppercase text-slate-500 mt-1"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
    </div>
    <?php endif; ?>
    <?php if ($showEta): ?>
    <div class="text-center">
      <div class="lead-num serif-num"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span><span class="lead-unit">min</span></div>
      <div class="text-[10px] tracking-[0.3em] uppercase text-slate-500 mt-1"><?php echo qd_safe($dict['eta']); ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Dot-separated queue numbers -->
  <?php if ($showActiveNums): ?>
  <div class="shrink-0 min-h-0 max-h-[22vh] flex flex-col overflow-hidden pt-1">
    <div class="text-center text-[9px] tracking-[0.25em] uppercase text-slate-500 mb-1"><?php echo qd_safe($dict['active_now']); ?></div>
    <div class="hair"></div>
    <div id="qdActiveList" class="dot-queue py-1 overflow-y-hidden flex-1 min-h-0" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?>
        <div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else: foreach (array_slice($active, 0, 12) as $i => $e): ?>
        <span class="<?php echo $i === 0 ? 'first' : ''; ?>"><?php echo (int) $e['queue_number']; ?></span>
        <?php if ($i < count($active) - 1 && $i < 11): ?><span class="dot">·</span><?php endif; ?>
      <?php endforeach; endif; ?>
    </div>
    <div class="hair"></div>
  </div>
  <?php endif; ?>

  <?php if ($showPowered): ?>
    <div class="shrink-0 text-center py-1 text-[8px] tracking-[0.3em] uppercase text-slate-400">Powered by Qordy</div>
  <?php endif; ?>
</div>

<script>
window.__qdRenderActive = function(list) {
  var el = document.getElementById('qdActiveList');
  if (!el) return;
  if (!list || list.length === 0) {
    el.innerHTML = '<div class="qd-empty">' + (el.dataset.empty || '') + '</div>';
    return;
  }
  var html = '';
  list.slice(0, 12).forEach(function(e, i, a) {
    html += '<span class="' + (i === 0 ? 'first' : '') + '">' + (e.queue_number || '—') + '</span>';
    if (i < Math.min(a.length, 12) - 1) html += '<span class="dot">·</span>';
  });
  el.innerHTML = html;
};
</script>
