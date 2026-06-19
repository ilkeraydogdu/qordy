<?php
/**
 * SECTOR_KEBAB — "Alev & şiş" kompozisyonu.
 *
 * Kömür karası arka plan üzerinde yanan alev efekti (SVG), üstte yatay şiş
 * (et küpleri dizili), altta QR & sıra. Sağda dönen döner silindir ikonu.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(1200px 700px at 50% 120%, #ea580c 0%, transparent 60%),
      radial-gradient(800px 500px at 10% -10%, #b45309 0%, transparent 60%),
      linear-gradient(180deg, #1a0806 0%, #0a0302 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color:#fef3c7;
    background-blend-mode: multiply;
  }
  .ember { position: fixed; inset: 0; pointer-events: none; z-index:0; overflow:hidden; }
  .ember span { position: absolute; bottom: -10px; width: 6px; height: 6px; border-radius:50%; background: radial-gradient(#fde68a, #f97316 60%, transparent 70%); opacity:.8; animation: rise 8s linear infinite; filter: blur(.5px);}
  @keyframes rise { to { transform: translateY(-110vh) scale(.3); opacity:0; } }

  .flame-row { position: relative; height: 72px; margin-top: -10px; pointer-events:none; }
  .flame { position: absolute; bottom:0; width: 28px; height: 52px;
    background: radial-gradient(closest-side, #facc15 0 20%, #f97316 40%, #7f1d1d 75%, transparent 80%);
    border-radius: 50% 50% 30% 30%; animation: flick 1.4s ease-in-out infinite alternate; filter: blur(.4px);}
  @keyframes flick { from { transform: translateY(0) scaleY(1); opacity:.9; } to { transform: translateY(-6px) scaleY(1.1); opacity:1; } }

  .skewer { position: relative; height: 44px; background: linear-gradient(180deg, #d1d5db, #6b7280 50%, #9ca3af); border-radius: 8px; box-shadow: inset 0 1px 0 rgba(255,255,255,.3), 0 4px 12px rgba(0,0,0,.4); }
  .skewer::before, .skewer::after { content:''; position:absolute; top:50%; transform:translateY(-50%); width:14px; height:14px; border-radius:50%; background:#9ca3af; box-shadow: inset -1px -1px 0 rgba(0,0,0,.3);}
  .skewer::before { left:-10px; } .skewer::after { right:-10px; }
  .meat { position:absolute; top:-4px; width:52px; height:52px; border-radius:8px; box-shadow: inset 0 -4px 0 rgba(0,0,0,.3), 0 4px 10px rgba(0,0,0,.4);}
  .meat.a { background: linear-gradient(180deg,#991b1b,#450a0a); }
  .meat.b { background: linear-gradient(180deg,#b45309,#451a03); }
  .meat.v { background: linear-gradient(180deg,#16a34a,#14532d); } /* biber */
  .meat.t { background: linear-gradient(180deg,#ef4444,#991b1b); border-radius:50%; } /* domates */

  .qr-card { background:#0a0a0a; border: 2px solid #ea580c; border-radius: 20px; box-shadow: 0 0 0 4px #1c0a00, 0 0 30px rgba(234,88,12,.35); padding: clamp(14px,2vh,22px); }
  .qr-box { background:#fff; padding: clamp(8px,1.2vmin,14px); border-radius: 8px; box-shadow: inset 0 0 0 3px #0a0a0a, 0 10px 30px -10px rgba(0,0,0,.6); }

  .doner { width: min(28vmin, 220px); aspect-ratio: 1/2; background: repeating-linear-gradient(180deg, #92400e 0 10px, #78350f 10px 16px, #422006 16px 22px); border-radius: 40px; box-shadow: inset -4px 0 0 rgba(0,0,0,.3), inset 4px 0 0 rgba(255,255,255,.1), 0 20px 40px rgba(0,0,0,.5); animation: rot 10s linear infinite; }
  @keyframes rot { from { filter: brightness(1); } 50% { filter: brightness(1.15); } to { filter: brightness(1); } }

  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:900; font-size: clamp(1rem,2vw,1.6rem); border-radius:8px; background: rgba(251,191,36,.1); color:#fef3c7; border:1px solid rgba(251,191,36,.3); }
  .qd-num-first{ background:#f97316; color:#1a0806; }
  .qd-num-second{ background: rgba(249,115,22,.4); }
  .qd-empty{ grid-column:1/-1; text-align:center; color:rgba(254,243,199,.55); font-size:11px; padding:8px; }
  .qd-kicker { color: rgba(254,243,199,.65); text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<div class="ember">
  <?php for ($i=0;$i<22;$i++): $l = rand(0,100); $d = rand(0,8); ?><span style="left: <?php echo $l; ?>%; animation-delay: <?php echo $d; ?>s;"></span><?php endfor; ?>
</div>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full flex flex-col p-3 sm:p-6 lg:p-10 overflow-hidden">
  <div class="text-center">
    <?php if ($showLogo): ?><div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 52, 'ring-2 ring-orange-500/50'); ?></div><?php endif; ?>
    <div class="tracking-[0.4em] text-[11px] uppercase opacity-70">— ocakbaşı · kebapçı —</div>
    <h1 class="mt-1 text-3xl sm:text-5xl font-black" style="font-family:'Cormorant Garamond',Georgia,serif; color:#fde68a; text-shadow:0 0 12px rgba(234,88,12,.6);"><?php echo qd_safe($businessName); ?></h1>
  </div>

  <!-- Şiş -->
  <div class="relative mt-5 sm:mt-8 px-6 sm:px-12">
    <div class="skewer">
      <div class="meat a" style="left: 8%;"></div>
      <div class="meat v" style="left: 22%;"></div>
      <div class="meat b" style="left: 36%;"></div>
      <div class="meat t" style="left: 50%;"></div>
      <div class="meat a" style="left: 64%;"></div>
      <div class="meat v" style="left: 78%;"></div>
    </div>
    <div class="flame-row">
      <?php for ($i=1;$i<=10;$i++): ?>
        <div class="flame" style="left: calc(<?php echo $i*9; ?>% - 14px); animation-delay: <?php echo ($i%5)*0.2; ?>s; height: <?php echo 40 + ($i%4)*8; ?>px;"></div>
      <?php endfor; ?>
    </div>
  </div>

  <div class="flex-1 min-h-0 grid grid-cols-1 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] gap-4 mt-2 items-center">
    <div class="qr-card">
      <div class="text-center mb-2">
        <div class="text-xl sm:text-2xl font-black" style="color:#fde68a; font-family:'Cormorant Garamond',serif;"><?php echo qd_safe($title); ?></div>
        <div class="text-xs opacity-70"><?php echo qd_safe($subtitle); ?></div>
      </div>
      <div class="qd-qr-slot flex items-center justify-center">
        <div class="qr-box relative max-w-[min(72%,260px)]">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none" style="background:#f97316;color:#1a0806;"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="text-center mt-2 font-bold text-sm sm:text-base"><?php echo qd_safe($cta); ?></div>
    </div>
    <div class="flex flex-col items-center gap-3">
      <div class="doner"></div>
      <?php if ($doorShowMetrics && $showWaitingCount): ?>
      <div class="rounded-2xl px-5 py-3 text-center" style="background: rgba(234,88,12,.12); border: 1px solid rgba(249,115,22,.35); min-width: 60%;">
        <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
        <div id="qdWaiting" class="text-4xl sm:text-5xl font-black text-orange-300"><?php echo (int) $waitingCount; ?></div>
      </div>
      <?php endif; ?>
      <?php if ($doorShowMetrics && $showEta): ?>
      <div class="rounded-2xl px-5 py-3 text-center" style="background: rgba(254,243,199,.08); border: 1px solid rgba(251,191,36,.3); min-width:60%;">
        <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
        <div class="text-3xl sm:text-4xl font-black text-amber-200"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <span class="text-sm opacity-70"><?php echo qd_safe($dict['minutes']); ?></span></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($showActiveNums): ?>
  <div class="mt-3">
    <div class="flex items-center justify-between mb-2">
      <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
      <div id="qdClock" class="text-sm opacity-70"></div>
    </div>
    <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-8 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?><div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else: foreach (array_slice($active,0,12) as $i=>$e): ?>
        <div class="qd-num <?php echo $i===0?'qd-num-first':($i===1?'qd-num-second':''); ?>"><?php echo (int)$e['queue_number']; ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showPowered): ?><div class="text-center mt-3 text-[10px] tracking-[0.3em] uppercase opacity-60">Powered by Qordy</div><?php endif; ?>
</div>
