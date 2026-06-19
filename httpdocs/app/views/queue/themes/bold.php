<?php
/**
 * BOLD theme — street-food / NY deli / Tokyo ramen shop.
 *
 * Composition: horizontal marquee ribbons top+bottom, oversized diagonal
 * colour bands behind QR, stickered/tilted queue numbers scattered on
 * a dot-grid background, big graffiti-style counters.
 * Inspiration: Supreme drops, Momofuku, Shake Shack signage.
 */
?>
<style>
  body {
    background: #0a0a12;
    color: #fff;
    overflow-x: hidden;
  }
  /* Dot grid wallpaper */
  .bg-dots {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image: radial-gradient(rgba(255,255,255,.08) 1.2px, transparent 1.2px);
    background-size: 28px 28px;
  }
  <?php if ($bgUrl): ?>
  body::after { content:''; position:fixed; inset:0; background: url('<?php echo qd_safe($bgUrl); ?>') center/cover; opacity:.25; pointer-events:none; z-index:0; }
  <?php endif; ?>

  /* Diagonal colour bands */
  .stripe {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
      linear-gradient(115deg, transparent 0%, transparent 38%, var(--accent) 38%, var(--accent) 46%, transparent 46%, transparent 54%, #22d3ee 54%, #22d3ee 60%, transparent 60%);
    opacity: .18;
  }

  /* Marquee ribbons */
  .marquee {
    overflow: hidden; white-space: nowrap; background: var(--accent); color: #0a0a12;
    border-top: 4px solid #0a0a12; border-bottom: 4px solid #0a0a12;
    transform: rotate(-2deg);
  }
  .marquee.alt { background: #22d3ee; transform: rotate(2deg); }
  .marquee-track {
    display: inline-block; padding: 8px 0; font-weight: 900;
    font-size: clamp(12px, 2.2vw, 22px); letter-spacing: .04em; text-transform: uppercase;
    animation: marquee 18s linear infinite;
  }
  .marquee.alt .marquee-track { animation-duration: 22s; animation-direction: reverse; }
  @keyframes marquee {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
  }
  .marquee span { padding: 0 34px; }
  .marquee span::before { content: '★ '; color: #0a0a12; opacity: .4; }

  /* Hero QR block */
  .qr-stage {
    position: relative; z-index: 1;
    background: #fff; padding: clamp(8px, 1.5vmin, 20px);
    max-width: min(100%, 20rem);
    width: 100%;
    box-sizing: border-box;
    border: 4px solid #fff;
    border-radius: 6px;
    transform: rotate(-2deg);
    box-shadow:
      8px 8px 0 0 var(--accent),
      16px 16px 0 0 #22d3ee,
      0 24px 50px -10px rgba(0,0,0,.6);
  }
  .qd-qr-slot .qr-stage { max-height: 100%; }
  .qr-stage::before {
    content: 'SCAN';
    position: absolute; top: -28px; left: 50%; transform: translateX(-50%) rotate(2deg);
    background: #0a0a12; color: #fff; padding: 4px 14px; font-weight: 900; letter-spacing: .2em;
    font-size: 14px; border: 3px solid #fff;
  }

  /* Scattered queue stickers */
  .sticker-wrap { position: relative; min-height: 100px; max-height: 22vh; overflow: hidden; }
  .sticker {
    position: absolute; display: inline-flex; align-items: center; justify-content: center;
    width: clamp(48px, 12vw, 80px); height: clamp(48px, 12vw, 80px); border-radius: 12px;
    background: #fff; color: #0a0a12;
    font-weight: 900; font-size: clamp(18px, 5vw, 32px);
    box-shadow: 0 10px 24px rgba(0,0,0,.5);
    border: 3px solid #0a0a12;
  }
  .sticker.s0 { background: var(--accent); color: #0a0a12; transform: rotate(-6deg) scale(1.15); box-shadow: 0 14px 40px rgba(249,115,22,.55); z-index: 5; }
  .sticker.s1 { background: #22d3ee; color: #0a0a12; transform: rotate(5deg); }
  .sticker.s2 { background: #f43f5e; color: #fff; transform: rotate(-3deg); }
  .sticker.s3 { background: #fbbf24; color: #0a0a12; transform: rotate(7deg); }
  .sticker.s4 { background: #a855f7; color: #fff; transform: rotate(-8deg); }
  .sticker.s5 { background: #34d399; color: #0a0a12; transform: rotate(4deg); }
  .sticker.s6 { background: #fff; color: #0a0a12; transform: rotate(-2deg); }

  .stat-huge { font-weight: 900; font-size: clamp(36px, 8vw, 120px); line-height: .85; letter-spacing: -.04em; }
  .stat-huge.accent { color: var(--accent); text-shadow: 4px 4px 0 #0a0a12; }
  .stat-huge.cyan { color: #22d3ee; text-shadow: 4px 4px 0 #0a0a12; }
  .tape {
    display: inline-block; background: #fff; color: #0a0a12; padding: 4px 14px;
    font-weight: 900; text-transform: uppercase; letter-spacing: .15em; font-size: 13px;
    transform: rotate(-1deg);
  }
  .qd-empty { padding: 20px; color: rgba(255,255,255,.45); text-align:center; grid-column: 1/-1; }
</style>

<div class="bg-dots"></div>
<div class="stripe"></div>

<div class="relative z-10 h-dvh max-h-dvh min-h-0 flex flex-col overflow-hidden">
  <!-- Top marquee -->
  <div class="marquee shrink-0">
    <div class="marquee-track">
      <?php $msg = strtoupper(qd_safe($title) . ' · ' . qd_safe($cta) . ' · ' . qd_safe($businessName) . ' · SCAN QR'); ?>
      <span><?php echo $msg; ?></span><span><?php echo $msg; ?></span><span><?php echo $msg; ?></span><span><?php echo $msg; ?></span>
    </div>
  </div>

  <div class="flex-1 min-h-0 min-w-0 grid grid-cols-1 lg:grid-cols-[1fr_1fr] gap-3 lg:gap-6 p-3 lg:p-6 items-stretch overflow-hidden">
    <!-- LEFT: HUGE stats + business -->
    <div class="min-h-0 flex flex-col justify-center overflow-hidden">
      <?php if ($showLogo): ?>
        <div class="inline-block mb-2"><?php echo qd_logo_markup($business, 56, 'ring-2 ring-white'); ?></div>
      <?php endif; ?>
      <div class="tape"><?php echo qd_safe($dict['welcome']); ?></div>
      <h1 class="mt-1 text-2xl sm:text-4xl lg:text-5xl font-black leading-tight tracking-tighter uppercase line-clamp-2"><?php echo qd_safe($businessName); ?></h1>
      <p class="mt-1 text-sm sm:text-base lg:text-lg text-white/80 font-semibold max-w-[520px] line-clamp-2"><?php echo qd_safe($subtitle); ?></p>

      <?php if ($showWaitingCount || $showEta): ?>
      <div class="mt-3 flex flex-wrap gap-3 sm:gap-5 items-end">
        <?php if ($showWaitingCount): ?>
        <div>
          <div class="tape" style="background: var(--accent)"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
          <div id="qdWaiting" class="stat-huge accent mt-2"><?php echo (int) $waitingCount; ?></div>
        </div>
        <?php endif; ?>
        <?php if ($showEta): ?>
        <div>
          <div class="tape" style="background: #22d3ee"><?php echo qd_safe($dict['eta']); ?> · <?php echo qd_safe($dict['minutes']); ?></div>
          <div class="stat-huge cyan mt-2"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span><span class="text-[0.4em] align-top">'</span></div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="mt-2 sm:mt-3 flex gap-1.5 flex-wrap">
        <?php foreach ($languages as $l): ?>
          <span class="px-3 py-1 bg-white text-black text-[11px] font-black uppercase tracking-wider rounded-sm border-2 border-black"><?php echo qd_safe($l); ?></span>
        <?php endforeach; ?>
        <span id="qdClock" class="px-3 py-1 bg-black text-white text-[11px] font-black tracking-wider rounded-sm border-2 border-white clock"></span>
      </div>
    </div>

    <!-- RIGHT: QR stage + CTA (column so metinler QR altında okunur) -->
    <div class="flex flex-col h-full min-h-0 min-w-0 items-stretch relative">
      <div class="qd-qr-slot w-full min-h-0">
        <div class="qr-stage flex items-center justify-center h-full min-h-0 w-full min-w-0">
        <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
        <div id="qdQrRefresh" class="absolute -bottom-2 -left-2 text-[9px] sm:text-[10px] font-black px-2 py-1 sm:px-3 sm:py-1.5 rounded shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none uppercase tracking-widest" style="background:#22d3ee; color:#0a0a12; border: 2px solid #0a0a12">
          <?php echo qd_safe($dict['qr_rotating']); ?>
        </div>
        <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="shrink-0 mt-2 text-center max-w-sm mx-auto px-1">
        <div class="text-sm sm:text-lg font-black text-white" style="text-shadow:0 2px 8px rgba(0,0,0,.5)"><?php echo qd_safe($cta); ?></div>
        <div class="mt-0.5 text-xs sm:text-sm text-white/95 font-semibold leading-snug line-clamp-2"><?php echo qd_safe($dict['scan_cta']); ?></div>
        <?php require __DIR__ . '/_door_social_row.php'; ?>
      </div>
    </div>
  </div>

  <!-- Scattered queue stickers -->
  <?php if ($showActiveNums): ?>
  <div class="shrink-0 min-h-0 max-h-[24vh] overflow-hidden px-3 lg:px-8 pb-1">
    <div class="tape mb-1"><?php echo qd_safe($dict['active_now']); ?></div>
    <div id="qdActiveList" class="sticker-wrap" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?>
        <div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else:
        // Pseudo-random but deterministic scattering positions
        $slots = [
          ['left'=>'2%','top'=>'10%'],['left'=>'12%','top'=>'55%'],['left'=>'22%','top'=>'18%'],
          ['left'=>'33%','top'=>'62%'],['left'=>'44%','top'=>'12%'],['left'=>'55%','top'=>'58%'],
          ['left'=>'66%','top'=>'22%'],['left'=>'77%','top'=>'50%'],['left'=>'86%','top'=>'8%'],
          ['left'=>'90%','top'=>'65%'],
        ];
        foreach (array_slice($active, 0, count($slots)) as $i => $e):
          $pos = $slots[$i];
      ?>
        <div class="sticker s<?php echo $i % 7; ?>" style="left:<?php echo $pos['left']; ?>; top:<?php echo $pos['top']; ?>">
          <?php echo (int) $e['queue_number']; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Bottom marquee -->
  <div class="marquee alt shrink-0 mt-auto">
    <div class="marquee-track">
      <?php $bmsg = strtoupper(qd_safe($businessName) . ' · ' . qd_safe($dict['scan_cta']) . ' · ' . qd_safe($dict['qr_refresh'])); ?>
      <span><?php echo $bmsg; ?></span><span><?php echo $bmsg; ?></span><span><?php echo $bmsg; ?></span><span><?php echo $bmsg; ?></span>
    </div>
  </div>

  <?php if ($showPowered): ?>
    <div class="text-center text-white/40 text-[10px] tracking-[0.3em] uppercase py-2 bg-black">Powered by Qordy</div>
  <?php endif; ?>
</div>
<script>
(function(){
  var slots = [
    ['2%','10%'],['12%','55%'],['22%','18%'],['33%','62%'],['44%','12%'],
    ['55%','58%'],['66%','22%'],['77%','50%'],['86%','8%'],['90%','65%']
  ];
  window.__qdRenderActive = function(list) {
    var el = document.getElementById('qdActiveList');
    if (!el) return;
    if (!list || list.length === 0) {
      el.innerHTML = '<div class="qd-empty">' + (el.dataset.empty || '') + '</div>';
      return;
    }
    el.innerHTML = list.slice(0, slots.length).map(function(e, i) {
      var p = slots[i];
      return '<div class="sticker s' + (i % 7) + '" style="left:' + p[0] + '; top:' + p[1] + '">' + (e.queue_number || '—') + '</div>';
    }).join('');
  };
})();
</script>
