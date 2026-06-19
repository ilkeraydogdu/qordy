<?php
/**
 * SECTOR_FOODTRUCK — "Sokak lezzeti / food truck" kompozisyonu.
 *
 * Geceleyin sokak: üstte marquee ampullü tente, QR & menü bir food truck
 * servis penceresi içinde; altta tekerler ve plaka. Kaldırımda neon yazı.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(1200px 700px at 50% 0%, rgba(250,204,21,.2) 0%, transparent 50%),
      linear-gradient(180deg, #0f172a 0%, #020617 100%);
    color:#facc15;
  }
  <?php if ($bgUrl): ?>
  body.qd-door::before { content:''; position:fixed; inset:0; background:url('<?php echo qd_safe($bgUrl); ?>') center/cover; opacity:.22; z-index:0; pointer-events:none; }
  <?php endif; ?>
  /* gökyüzünde şehir siluet */
  .skyline { position: absolute; top: 20px; left: 0; right: 0; height: 52px; opacity:.25; z-index:0;
    background:
      linear-gradient(90deg, transparent 0 3%, #facc15 3% 3.5%, transparent 3.5%) 0 100%/100% 2px no-repeat,
      repeating-linear-gradient(90deg, transparent 0 40px, #1e293b 40px 60px, #334155 60px 80px, transparent 80px 120px);
  }
  .marquee-band { position: relative; border-top: 4px solid #facc15; border-bottom: 4px solid #facc15; padding: 6px 0; background: #ef4444; text-align:center; font-family:'Archivo Black','Space Grotesk',sans-serif; letter-spacing:.2em; text-transform:uppercase; color:#fff7ed; font-size: clamp(11px,1.4vw,13px); box-shadow: 0 0 0 2px #0f172a inset; }
  .marquee-band::before, .marquee-band::after { content:''; position: absolute; top:-10px; bottom:-10px; width: 100%; left: 0; background-image: radial-gradient(circle, #facc15 3.5px, transparent 4px); background-size: 22px 22px; background-position: 11px 0; pointer-events:none; }
  .marquee-band::before { bottom:auto; top:-14px; animation: blink 1.6s infinite; }
  .marquee-band::after { top: auto; bottom:-14px; animation: blink 1.6s infinite reverse; }
  @keyframes blink { 50% { filter: brightness(.45);} }

  .truck { background: #fff7ed; border-radius: 12px 12px 4px 4px; border: 5px solid #0f172a; position: relative; box-shadow: 0 40px 80px -20px rgba(0,0,0,.5); }
  .truck-top { background: #ef4444; color:#fff7ed; padding: 10px 14px; font-family:'Archivo Black','Space Grotesk',sans-serif; text-align:center; letter-spacing:.3em; text-transform:uppercase; font-size: clamp(14px,1.8vw,18px); border-bottom: 3px dashed #fff7ed;}
  .truck-body { padding: clamp(14px,3vh,30px); background: repeating-linear-gradient(180deg, #fff7ed 0 8px, #fef3c7 8px 9px); color:#0f172a; }
  .serving-window { background:#0f172a; border-radius: 10px; padding: clamp(14px,3vh,28px); color:#fff7ed; border: 3px solid #facc15; position: relative; }
  .awning { position: absolute; top:-18px; left:-8px; right:-8px; height: 20px;
    background: repeating-linear-gradient(90deg, #ef4444 0 24px, #fff7ed 24px 48px);
    border-radius: 8px 8px 4px 4px; box-shadow: 0 6px 16px rgba(0,0,0,.35);
  }
  .plate { background:#0f172a; color:#facc15; padding: 4px 10px; font-family:'Archivo Black','Space Grotesk',sans-serif; letter-spacing:.2em; border-radius: 4px; display:inline-block; border: 2px solid #fff7ed; }
  .wheel { width: 56px; height: 56px; border-radius:50%; background:#0f172a; border: 6px solid #334155; position: relative; margin: -10px auto 0; box-shadow: 0 8px 20px rgba(0,0,0,.5); }
  .wheel::before { content:''; position:absolute; inset:18%; border-radius:50%; background:#6b7280; }
  .wheel::after { content:''; position:absolute; inset: 42%; border-radius:50%; background:#0f172a; }

  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:900; font-size: clamp(1rem,2vw,1.6rem); border-radius:6px; background:#fff7ed; color:#0f172a; font-family:'Archivo Black','Space Grotesk',sans-serif; }
  .qd-num-first{ background:#facc15; color:#0f172a; }
  .qd-num-second{ background:#ef4444; color:#fff7ed; }
  .qd-empty{ grid-column:1/-1; text-align:center; color:#facc15; font-size:11px; padding:8px; }
  .qd-kicker { color:#facc15; text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:900; font-family:'Archivo Black','Space Grotesk',sans-serif; }
</style>

<div class="skyline"></div>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full flex flex-col p-3 sm:p-6 lg:p-10 overflow-hidden">
  <div class="marquee-band">★ Street food · burger · döner · wrap · 24/7 ★ <?php echo qd_safe($businessName); ?> ★</div>

  <div class="flex-1 min-h-0 my-3 flex items-center justify-center">
    <div class="truck w-full max-w-3xl">
      <div class="truck-top flex items-center justify-center gap-3">
        <?php if ($showLogo): ?><?php echo qd_logo_markup($business, 40, 'ring-2 ring-yellow-300/70'); ?><?php endif; ?>
        <span><?php echo qd_safe($businessName); ?></span>
        <span class="plate">QD · 34</span>
      </div>
      <div class="truck-body grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <div class="text-lg font-black tracking-[0.25em] uppercase opacity-80">Menü</div>
          <ul class="mt-2 text-sm font-bold space-y-1">
            <li class="flex justify-between border-b border-dashed border-slate-400 pb-1"><span>· Smash burger</span><span class="text-red-600">★</span></li>
            <li class="flex justify-between border-b border-dashed border-slate-400 pb-1"><span>· Döner wrap</span><span class="text-red-600">★</span></li>
            <li class="flex justify-between border-b border-dashed border-slate-400 pb-1"><span>· Chili fries</span><span class="text-red-600">★</span></li>
            <li class="flex justify-between"><span>· Soft drink</span><span></span></li>
          </ul>
          <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
          <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-2 mt-3">
            <?php if ($showWaitingCount): ?>
            <div class="rounded p-2 text-center bg-slate-900 text-yellow-300 border-2 border-yellow-300">
              <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
              <div id="qdWaiting" class="text-3xl font-black"><?php echo (int) $waitingCount; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($showEta): ?>
            <div class="rounded p-2 text-center bg-red-600 text-white border-2 border-white">
              <div class="qd-kicker" style="color:#fff7ed;"><?php echo qd_safe($dict['eta']); ?></div>
              <div class="text-3xl font-black"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <span class="text-xs"><?php echo qd_safe($dict['minutes']); ?></span></div>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="serving-window">
          <div class="awning"></div>
          <div class="text-center text-sm sm:text-base font-black tracking-[0.3em] uppercase opacity-80" style="color:#facc15;">Sipariş penceresi</div>
          <div class="text-center text-xl sm:text-2xl font-black mt-1"><?php echo qd_safe($title); ?></div>
          <div class="qd-qr-slot flex items-center justify-center mt-2">
            <div class="relative bg-white p-2 rounded max-w-[min(72%,220px)] ring-2 ring-yellow-300">
              <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
              <div id="qdQrRefresh" class="absolute -top-2 -right-2 bg-yellow-300 text-slate-900 text-[9px] font-black px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none"><?php echo qd_safe($dict['qr_rotating']); ?></div>
              <span id="qdCountdown" class="hidden"></span>
            </div>
          </div>
          <div class="text-center mt-2 text-sm font-black"><?php echo qd_safe($cta); ?></div>
          <div class="text-center text-xs opacity-80"><?php echo qd_safe($subtitle); ?></div>
        </div>
      </div>
      <div class="grid grid-cols-2 max-w-md mx-auto px-8">
        <div class="wheel"></div>
        <div class="wheel"></div>
      </div>
    </div>
  </div>

  <?php if ($showActiveNums): ?>
  <div>
    <div class="flex items-center justify-between mb-2">
      <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
      <div id="qdClock" class="text-sm" style="color:#facc15;"></div>
    </div>
    <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-8 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?><div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else: foreach (array_slice($active,0,12) as $i=>$e): ?>
        <div class="qd-num <?php echo $i===0?'qd-num-first':($i===1?'qd-num-second':''); ?>"><?php echo (int)$e['queue_number']; ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showPowered): ?><div class="text-center mt-3 text-[10px] tracking-[0.3em] uppercase opacity-60" style="color:#facc15;">Powered by Qordy</div><?php endif; ?>
</div>
