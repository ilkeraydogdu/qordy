<?php
/**
 * SECTOR_TEAHOUSE — "İnce belli bardak / kilim" kompozisyonu.
 *
 * Üstte ve altta kilim şerit deseni (CSS rombus), solda-sağda ince belli
 * Türk çay bardakları (büyük boyutta çizim), merkezde bakır semaver silüetli
 * QR kartı, köşelerde nane yaprağı SVG.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(900px 500px at 50% 10%, #fde68a 0%, transparent 60%),
      linear-gradient(180deg, #7c2d12 0%, #422006 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color:#fef3c7;
    background-blend-mode: multiply;
  }
  .kilim { height: 44px; position: relative; overflow: hidden; }
  .kilim-bg { position: absolute; inset: 0;
    background:
      repeating-linear-gradient(45deg, #991b1b 0 10px, #7f1d1d 10px 20px),
      #7c2d12;
  }
  .kilim-pat { position: absolute; inset: 0; display:flex; align-items:center; justify-content:space-around; }
  .kilim-pat span { width: 26px; height: 26px; background: #fef3c7; transform: rotate(45deg); box-shadow: inset 0 0 0 4px #991b1b, 0 0 0 2px #fef3c7; }

  .lamp { position: absolute; top: 44px; left: 50%; transform: translateX(-50%); width: 78px; height: 60px; background: radial-gradient(ellipse at top, #fbbf24, #b45309); border-radius: 0 0 100px 100px; box-shadow: 0 10px 30px rgba(251,191,36,.35); z-index: 1; }
  .lamp::before { content:''; position:absolute; top:-20px; left:50%; transform:translateX(-50%); width:2px; height:20px; background:#fef3c7; }

  .glass { position: relative; width: 90px; height: 130px; margin: 0 auto; filter: drop-shadow(0 12px 14px rgba(0,0,0,.35)); }
  .glass-body { position: absolute; inset: 0;
    background: linear-gradient(180deg, rgba(255,255,255,.15) 0 20%, rgba(255,255,255,.05) 40%, rgba(255,255,255,.2) 100%);
    clip-path: polygon(0 0, 100% 0, 94% 28%, 62% 44%, 62% 56%, 94% 72%, 100% 100%, 0 100%, 6% 72%, 38% 56%, 38% 44%, 6% 28%);
  }
  .glass-tea { position: absolute; inset: 0;
    background: linear-gradient(180deg, #b91c1c 0 20%, #991b1b 100%);
    clip-path: polygon(4% 3%, 96% 3%, 92% 28%, 60% 44%, 60% 56%, 92% 72%, 96% 97%, 4% 97%, 8% 72%, 40% 56%, 40% 44%, 8% 28%);
  }
  .glass-rim { position: absolute; left:0; right:0; top: 2px; height: 3px; background: rgba(255,255,255,.4); }
  .saucer { width: 110px; height: 16px; border-radius:50%; background: linear-gradient(180deg, #fde68a, #b45309); margin: 4px auto 0; box-shadow: 0 8px 20px rgba(0,0,0,.4);}

  .samovar { background: linear-gradient(180deg, #c9a86a 0%, #78350f 100%); border-radius: 28px 28px 16px 16px; box-shadow: inset 0 2px 0 rgba(255,255,255,.25), 0 30px 60px -20px rgba(0,0,0,.5); padding: clamp(20px,3vh,36px); position: relative; }
  .samovar::before { content:''; position:absolute; top:-14px; left: 50%; transform: translateX(-50%); width: 60px; height: 18px; background: #c9a86a; border-radius: 10px 10px 0 0; box-shadow: inset 0 2px 0 rgba(255,255,255,.3);}
  .samovar::after { content:''; position:absolute; top:-22px; left: 50%; transform: translateX(-50%); width: 12px; height: 10px; border-radius: 4px 4px 0 0; background:#78350f;}

  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:800; font-size: clamp(1rem,2vw,1.6rem); border-radius:8px; background:rgba(254,243,199,.1); color:#fef3c7; border:1px solid rgba(254,243,199,.25); font-family:'Cormorant Garamond',serif; }
  .qd-num-first{ background:#fbbf24; color:#7c2d12; }
  .qd-num-second{ background:rgba(251,191,36,.3); }
  .qd-empty{ grid-column:1/-1; text-align:center; color:rgba(254,243,199,.55); font-size:11px; padding:8px; }
  .qd-kicker { color: rgba(254,243,199,.7); text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="kilim"><div class="kilim-bg"></div><div class="kilim-pat"><?php for ($i=0;$i<14;$i++) echo '<span></span>'; ?></div></div>

<div class="relative box-border h-[calc(100dvh-88px)] w-full flex flex-col p-4 sm:p-8 lg:p-12 overflow-hidden">
  <div class="lamp"></div>

  <div class="text-center mt-8 mb-3">
    <?php if ($showLogo): ?><div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 52, 'ring-4 ring-amber-200/50'); ?></div><?php endif; ?>
    <div class="tracking-[0.4em] text-[11px] uppercase opacity-70">— çay bahçesi · nargile · lokanta —</div>
    <h1 class="text-3xl sm:text-5xl font-black mt-1 italic" style="font-family:'Cormorant Garamond',serif; color:#fde68a;"><?php echo qd_safe($businessName); ?></h1>
  </div>

  <div class="flex-1 min-h-0 grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] items-center gap-3">
    <div class="hidden md:flex flex-col items-center"><div class="glass"><div class="glass-body"></div><div class="glass-tea"></div><div class="glass-rim"></div></div><div class="saucer"></div></div>

    <div class="samovar text-center max-w-md mx-auto">
      <div class="text-2xl sm:text-3xl font-black italic" style="font-family:'Cormorant Garamond',serif; color:#7c2d12;"><?php echo qd_safe($title); ?></div>
      <div class="text-xs opacity-80 mb-3" style="color:#7c2d12;"><?php echo qd_safe($subtitle); ?></div>
      <div class="qd-qr-slot flex items-center justify-center">
        <div class="relative bg-white p-2 rounded-md shadow-xl max-w-[min(72%,240px)] ring-4 ring-red-700">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 bg-red-700 text-amber-50 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="mt-2 font-bold text-sm sm:text-base" style="color:#7c2d12;"><?php echo qd_safe($cta); ?></div>
    </div>

    <div class="hidden md:flex flex-col items-center"><div class="glass"><div class="glass-body"></div><div class="glass-tea"></div><div class="glass-rim"></div></div><div class="saucer"></div></div>
  </div>

  <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
  <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-3 mt-3">
    <?php if ($showWaitingCount): ?>
    <div class="rounded-2xl p-3 text-center" style="background: rgba(254,243,199,.1); border:1px solid rgba(251,191,36,.3);">
      <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
      <div id="qdWaiting" class="text-4xl font-black" style="color:#fde68a;"><?php echo (int) $waitingCount; ?></div>
    </div>
    <?php endif; ?>
    <?php if ($showEta): ?>
    <div class="rounded-2xl p-3 text-center" style="background: rgba(185,28,28,.25); border:1px solid rgba(254,243,199,.3);">
      <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
      <div class="text-4xl font-black" style="color:#fef3c7;"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <span class="text-sm opacity-70"><?php echo qd_safe($dict['minutes']); ?></span></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($showActiveNums): ?>
  <div class="mt-3">
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

  <?php if ($showPowered): ?><div class="text-center mt-3 text-[10px] tracking-[0.3em] uppercase opacity-60">Powered by Qordy</div><?php endif; ?>
</div>

<div class="kilim"><div class="kilim-bg"></div><div class="kilim-pat"><?php for ($i=0;$i<14;$i++) echo '<span></span>'; ?></div></div>
