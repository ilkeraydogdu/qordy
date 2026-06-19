<?php
/**
 * SECTOR_SEAFOOD — "İskele / martı & dalga" kompozisyonu.
 *
 * Gök-deniz gradient, ufuk çizgisinde güneş, alt yarıda animasyonlu dalga
 * SVG'leri, kenar halatı, martı silüetleri. Ortada ahşap iskele üstünde
 * "Bugünün avı" panosu ve QR.
 */
?>
<style>
  body.qd-door {
    background:
      linear-gradient(180deg, #e0f2fe 0%, #7dd3fc 38%, #0ea5e9 44%, #0c4a6e 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color:#0c4a6e;
    background-blend-mode: multiply;
  }
  .sun-bay { position: absolute; top: 12%; left: 50%; transform: translateX(-50%); width: 140px; height: 140px; border-radius:50%; background: radial-gradient(#fef9c3, #f59e0b 65%); box-shadow: 0 0 80px rgba(245,158,11,.45); z-index:0; }
  .gull { position: absolute; font-size: 22px; color: #0c4a6e; opacity:.75; animation: fly 20s linear infinite; z-index:0; }
  .gull::before { content:'\\2040'; /* ⁀ */ }
  @keyframes fly { 0% { transform: translateX(-10vw);} 100% { transform: translateX(110vw);} }

  .waves { position: absolute; left: 0; right: 0; bottom: 0; height: 56%; overflow: hidden; z-index:0; }
  .waves svg { position: absolute; left:0; right:0; width: 200%; height: 100%; animation: swell 9s linear infinite; }
  .waves svg.b { animation-duration: 13s; opacity:.55; }
  @keyframes swell { from { transform: translateX(0);} to { transform: translateX(-50%);} }

  .rope { position: absolute; inset: 18px; border: 4px dashed #f59e0b; border-radius: 22px; pointer-events:none; opacity:.7; z-index:1; }

  .pier { background: repeating-linear-gradient(90deg, #8b5a2b 0 14px, #6b3d18 14px 18px); border: 4px solid #3e2411; border-radius: 14px; box-shadow: 0 30px 60px -20px rgba(0,0,0,.45); padding: clamp(16px,3vh,32px); }
  .catch-board { background: #0c4a6e; color:#fef3c7; border:3px solid #fbbf24; border-radius:12px; padding: 10px 14px; font-family:'Cormorant Garamond',serif; }
  .catch-board h4 { font-weight: 800; letter-spacing:.2em; text-transform:uppercase; font-size: 11px; color:#fbbf24; }
  .catch-board li { display:flex; justify-content:space-between; font-size: 13px; padding: 2px 0; border-bottom: 1px dashed rgba(254,243,199,.2); }

  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:800; font-size: clamp(1rem,2vw,1.6rem); border-radius:8px; background:#fff; color:#0c4a6e; border:2px solid #0c4a6e; font-family:'Cormorant Garamond',serif; }
  .qd-num-first{ background:#0c4a6e; color:#fef3c7; }
  .qd-num-second{ background: #bae6fd; }
  .qd-empty{ grid-column:1/-1; text-align:center; color:#0c4a6e; opacity:.6; font-size:11px; padding:8px; }
  .qd-kicker { color:#fef3c7; text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<div class="sun-bay"></div>
<div class="gull" style="top: 14%; animation-delay: 0s;"></div>
<div class="gull" style="top: 22%; animation-delay: -7s; font-size: 16px;"></div>
<div class="gull" style="top: 8%; animation-delay: -13s; font-size: 18px;"></div>

<div class="waves">
  <svg class="a" viewBox="0 0 1200 60" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 40 Q 150 10 300 40 T 600 40 T 900 40 T 1200 40 T 1500 40 T 1800 40 T 2100 40 T 2400 40 V60 H0 Z" fill="#38bdf8"/></svg>
  <svg class="b" viewBox="0 0 1200 60" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><path d="M0 30 Q 150 0 300 30 T 600 30 T 900 30 T 1200 30 T 1500 30 T 1800 30 T 2100 30 T 2400 30 V60 H0 Z" fill="#0284c7"/></svg>
</div>

<div class="rope"></div>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full flex flex-col p-6 sm:p-10 lg:p-12 overflow-hidden" style="z-index:2;">
  <div class="text-center mb-3">
    <?php if ($showLogo): ?><div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 52, 'ring-4 ring-amber-200/70'); ?></div><?php endif; ?>
    <div class="text-[11px] tracking-[0.4em] uppercase opacity-75" style="color:#0c4a6e;">— iskele · balıkçı · meyhane —</div>
    <h1 class="text-3xl sm:text-5xl font-black mt-1 italic" style="font-family:'Cormorant Garamond',serif; color:#0c4a6e;"><?php echo qd_safe($businessName); ?></h1>
  </div>

  <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] gap-4 items-stretch">
    <div class="pier flex flex-col justify-between">
      <div class="catch-board">
        <h4>Bugünün avı</h4>
        <ul class="mt-1">
          <li><span>· Levrek</span><span>iskelede</span></li>
          <li><span>· Çipura</span><span>iskelede</span></li>
          <li><span>· Kalkan</span><span>günlük</span></li>
          <li><span>· Palamut</span><span>mevsim</span></li>
        </ul>
      </div>
      <?php if ($doorShowMetrics): ?>
      <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-2 mt-3">
        <?php if ($showWaitingCount): ?>
        <div class="rounded-xl p-2 text-center" style="background:#fef3c7; color:#0c4a6e; border:2px solid #0c4a6e;">
          <div class="qd-kicker" style="color:#0c4a6e;"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
          <div id="qdWaiting" class="text-3xl font-black"><?php echo (int) $waitingCount; ?></div>
        </div>
        <?php endif; ?>
        <?php if ($showEta): ?>
        <div class="rounded-xl p-2 text-center" style="background:#0c4a6e; color:#fef3c7;">
          <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
          <div class="text-3xl font-black"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <span class="text-xs"><?php echo qd_safe($dict['minutes']); ?></span></div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="rounded-3xl p-5 text-center" style="background: rgba(255,255,255,.92); box-shadow: 0 30px 60px -20px rgba(0,0,0,.3); border: 4px solid #fbbf24;">
      <div class="text-2xl sm:text-3xl font-black mb-1 italic" style="font-family:'Cormorant Garamond',serif; color:#0c4a6e;"><?php echo qd_safe($title); ?></div>
      <div class="text-xs opacity-75 mb-3"><?php echo qd_safe($subtitle); ?></div>
      <div class="qd-qr-slot flex items-center justify-center">
        <div class="relative bg-white p-2 rounded-lg shadow-lg max-w-[min(72%,260px)] ring-4 ring-sky-700">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none" style="background:#fbbf24;color:#0c4a6e"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="mt-2 font-bold text-sm sm:text-base" style="color:#0c4a6e;"><?php echo qd_safe($cta); ?></div>
    </div>
  </div>

  <?php if ($showActiveNums): ?>
  <div class="mt-3">
    <div class="flex items-center justify-between mb-2">
      <div class="qd-kicker" style="color:#fef3c7;"><?php echo qd_safe($dict['active_now']); ?></div>
      <div id="qdClock" class="text-sm" style="color:#fef3c7;"></div>
    </div>
    <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-8 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?><div class="qd-empty" style="color:#fef3c7;"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else: foreach (array_slice($active,0,12) as $i=>$e): ?>
        <div class="qd-num <?php echo $i===0?'qd-num-first':($i===1?'qd-num-second':''); ?>"><?php echo (int)$e['queue_number']; ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showPowered): ?><div class="text-center mt-3 text-[10px] tracking-[0.3em] uppercase" style="color:#fef3c7; opacity:.8;">Powered by Qordy</div><?php endif; ?>
</div>
