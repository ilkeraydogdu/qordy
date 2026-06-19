<?php
/**
 * SECTOR_ICECREAM — "Pastel külah" kompozisyonu.
 *
 * Pembe-pastel arka plan, süzülen polka-dot ve serpme şekerleme; solda büyük
 * waffle külah (CSS çizim), sağda dondurma fiyat-tag kartı üstünde QR.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(600px 400px at 0% 0%, #fce7f3 0%, transparent 60%),
      radial-gradient(800px 500px at 100% 100%, #cffafe 0%, transparent 60%),
      linear-gradient(180deg, #fff1f7 0%, #ffe4e6 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color:#831843;
    background-blend-mode: multiply;
  }
  .sprinkle { position: fixed; width: 10px; height: 3px; border-radius: 2px; z-index: 0; pointer-events:none; opacity:.9; animation: fall 7s linear infinite; }
  @keyframes fall { from { transform: translateY(-20vh) rotate(0);} to { transform: translateY(120vh) rotate(540deg);} }

  .cone-wrap { display:flex; align-items:flex-end; justify-content:center; height: 100%; }
  .cone { position: relative; width: min(60%, 280px); aspect-ratio: 1/2.2; }
  .scoop { position: absolute; left: 50%; transform: translateX(-50%); border-radius:50%; box-shadow: inset -6px -8px 0 rgba(0,0,0,.1), 0 6px 10px rgba(0,0,0,.15); }
  .scoop.s1 { width: 92%; aspect-ratio:1/1; bottom: 40%; background: radial-gradient(at 30% 30%, #fbcfe8, #f472b6 70%);}
  .scoop.s2 { width: 78%; aspect-ratio:1/1; bottom: 54%; background: radial-gradient(at 30% 30%, #fef9c3, #facc15 70%);}
  .scoop.s3 { width: 62%; aspect-ratio:1/1; bottom: 68%; background: radial-gradient(at 30% 30%, #cffafe, #67e8f9 70%);}
  .cherry { position:absolute; left:50%; bottom: 86%; transform:translateX(-50%); width:14%; aspect-ratio:1/1; border-radius:50%; background: radial-gradient(#ef4444, #7f1d1d); }
  .cherry::after { content:''; position:absolute; left: 60%; bottom: 90%; width: 4px; height: 16px; border-radius: 2px; background:#166534; transform: rotate(20deg); }
  .waffle { position: absolute; bottom: 0; left: 0; right: 0; height: 48%;
    background:
      repeating-linear-gradient(45deg, transparent 0 8px, rgba(120,53,15,.25) 8px 9px),
      repeating-linear-gradient(-45deg, transparent 0 8px, rgba(120,53,15,.25) 8px 9px),
      linear-gradient(180deg, #fbbf24 0%, #d97706 100%);
    clip-path: polygon(0 0, 100% 0, 50% 100%);
    box-shadow: inset 0 0 0 2px rgba(120,53,15,.35);
  }
  .drip { position:absolute; bottom: 50%; left: 10%; width: 14%; height: 10%; background: radial-gradient(at 30% 30%, #fbcfe8, #f472b6 70%); border-radius: 50% 50% 70% 70%; }
  .drip.b { left: auto; right: 12%; bottom: 48%; background: radial-gradient(at 30% 30%, #fef9c3, #facc15 70%); }
  .tag { background:#fff; border-radius: 26px; padding: clamp(18px,3vh,28px); box-shadow: 0 30px 60px -20px rgba(131,24,67,.3); border: 4px dashed #f472b6; position: relative; }
  .tag::before { content:''; position:absolute; left:-10px; top: 30px; width: 20px; height: 20px; border-radius:50%; background:#fff1f7; box-shadow: inset 3px 0 0 rgba(131,24,67,.15); }

  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:800; font-size: clamp(1rem,2vw,1.6rem); border-radius:12px; background:#fff; color:#831843; border:2px solid #f472b6; font-family:'Caveat',cursive; }
  .qd-num-first{ background:#f472b6; color:#fff; }
  .qd-num-second{ background:#cffafe; border-color:#67e8f9; }
  .qd-empty{ grid-column:1/-1; text-align:center; color:#831843; opacity:.55; font-size:11px; padding:8px; }
  .qd-kicker { color:#831843; opacity:.75; text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<?php
  $colors = ['#f472b6','#67e8f9','#facc15','#a78bfa','#34d399','#fb7185'];
  for ($i=0;$i<14;$i++) {
    $l = rand(0,100); $d = rand(0,7); $c = $colors[$i % count($colors)]; $r = rand(0,360);
    echo '<span class="sprinkle" style="left:' . $l . '%;animation-delay:' . $d . 's;background:' . $c . ';transform:rotate(' . $r . 'deg);"></span>';
  }
?>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full grid grid-rows-[auto_minmax(0,1fr)_auto] gap-3 p-4 sm:p-8 lg:p-12 overflow-hidden">
  <div class="text-center">
    <?php if ($showLogo): ?><div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 50, 'ring-4 ring-pink-200'); ?></div><?php endif; ?>
    <div class="text-[11px] tracking-[0.4em] uppercase opacity-70">— dondurma · tatlı · gelato —</div>
    <h1 class="text-3xl sm:text-5xl font-black mt-1" style="font-family:'Caveat',cursive; color:#db2777;"><?php echo qd_safe($businessName); ?></h1>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-4 items-center">
    <div class="cone-wrap">
      <div class="cone">
        <div class="waffle"></div>
        <div class="drip"></div>
        <div class="drip b"></div>
        <div class="scoop s1"></div>
        <div class="scoop s2"></div>
        <div class="scoop s3"></div>
        <div class="cherry"></div>
      </div>
    </div>

    <div class="tag text-center">
      <div class="text-2xl sm:text-3xl font-black mb-1" style="font-family:'Caveat',cursive; color:#db2777;"><?php echo qd_safe($title); ?></div>
      <div class="text-xs opacity-75 mb-3"><?php echo qd_safe($subtitle); ?></div>
      <div class="qd-qr-slot flex items-center justify-center">
        <div class="relative bg-white p-2 rounded-lg shadow-lg max-w-[min(70%,240px)] ring-4 ring-pink-200">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 bg-pink-500 text-white text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="mt-2 font-bold text-sm sm:text-base"><?php echo qd_safe($cta); ?></div>

      <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
      <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-2 mt-3">
        <?php if ($showWaitingCount): ?>
        <div class="rounded-2xl p-2 text-center bg-pink-100 border-2 border-pink-300">
          <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
          <div id="qdWaiting" class="text-3xl font-black text-pink-600" style="font-family:'Caveat',cursive;"><?php echo (int) $waitingCount; ?></div>
        </div>
        <?php endif; ?>
        <?php if ($showEta): ?>
        <div class="rounded-2xl p-2 text-center bg-cyan-100 border-2 border-cyan-300">
          <div class="qd-kicker" style="color:#0c4a6e;"><?php echo qd_safe($dict['eta']); ?></div>
          <div class="text-3xl font-black text-cyan-700" style="font-family:'Caveat',cursive;"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <span class="text-xs"><?php echo qd_safe($dict['minutes']); ?></span></div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($showActiveNums): ?>
  <div>
    <div class="flex items-center justify-between mb-2">
      <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
      <div id="qdClock" class="text-sm opacity-75"></div>
    </div>
    <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-8 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?><div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else: foreach (array_slice($active,0,12) as $i=>$e): ?>
        <div class="qd-num <?php echo $i===0?'qd-num-first':($i===1?'qd-num-second':''); ?>"><?php echo (int)$e['queue_number']; ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showPowered): ?><div class="text-center text-[10px] tracking-[0.3em] uppercase opacity-60">Powered by Qordy</div><?php endif; ?>
</div>
