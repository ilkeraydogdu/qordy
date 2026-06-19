<?php
/**
 * SECTOR_BAKERY — "Tarif defteri" kompozisyonu.
 *
 * Keten dokulu bej arka plan; ortada satırlı tarif defteri sayfası, "recipe card"
 * formatı (ingredients / method), QR bir un damgası gibi sunulur.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(900px 600px at 20% 10%, rgba(255, 236, 205, .6) 0%, transparent 60%),
      radial-gradient(800px 600px at 85% 90%, rgba(255, 222, 180, .55) 0%, transparent 55%),
      /* jute texture */
      repeating-linear-gradient(0deg, rgba(180,122,65,.08) 0 1px, transparent 1px 4px),
      repeating-linear-gradient(90deg, rgba(180,122,65,.06) 0 1px, transparent 1px 4px),
      #fdf5e6
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color: #3e2a17;
  }

  .page {
    background:
      repeating-linear-gradient(0deg, transparent 0 31px, rgba(71,85,105,.18) 31px 32px),
      linear-gradient(180deg, #fffdf6, #fffaef);
    border-radius: 16px; position: relative;
    box-shadow: 0 45px 85px -30px rgba(61, 40, 20, .35), inset 0 0 0 1px rgba(180,122,65,.18);
  }
  .page::before {
    content:''; position:absolute; top:0; bottom:0; left: 36px; width: 2px;
    background: #ef4444; opacity: .75;
  }
  .page::after {
    content:''; position:absolute; top:0; bottom:0; left: 24px; width: 10px;
    background:
      radial-gradient(circle at 50% 20px, #fff 0 8px, transparent 9px),
      radial-gradient(circle at 50% 60px, #fff 0 8px, transparent 9px),
      radial-gradient(circle at 50% 100px, #fff 0 8px, transparent 9px),
      radial-gradient(circle at 50% 140px, #fff 0 8px, transparent 9px);
    background-repeat: repeat-y; background-size: 100% 40px;
    border-right: 1px solid rgba(180,122,65,.25);
  }

  .hand { font-family: 'Caveat', 'Brush Script MT', cursive; color:#7c2d12; }
  .serif { font-family: 'Cormorant Garamond', Georgia, serif; }

  .stamp {
    display:inline-flex; align-items:center; justify-content:center; padding: 6px 14px;
    font-family: 'Cormorant Garamond', serif; font-weight: 700; letter-spacing: .2em;
    color: #92400e; border: 2px solid #92400e; transform: rotate(-4deg); font-size: 11px;
    background: rgba(146,64,14,.06); border-radius: 4px;
  }

  .wheat {
    position: absolute; width: 80px; opacity: .55; color: #b8860b;
  }

  .recipe-ingredients { list-style: none; margin: 0; padding: 0; }
  .recipe-ingredients li { padding: 4px 0; display:flex; align-items:center; gap:8px; color:#3e2a17; }
  .recipe-ingredients li::before { content: '•'; color:#b8860b; font-weight: 900; }

  .qd-num {
    aspect-ratio:1/1; display:flex; align-items:center; justify-content:center;
    font-family: 'Caveat', cursive; font-weight: 700; font-size: clamp(1.1rem, 2.2vw, 1.8rem);
    border-radius: 10px; background:#fff; color:#7c2d12; border:1px dashed rgba(180,122,65,.4);
  }
  .qd-num-first{ background:#b45309; color:#fff7e6; border-color:transparent; }
  .qd-num-second{ background:#f59e0b; color:#3e2a17; border-color:transparent; }
  .qd-empty{ grid-column:1/-1; text-align:center; color: rgba(124,45,18,.55); font-size:11px; padding:8px; }
  .qd-kicker { color: rgba(124,45,18,.75); text-transform: uppercase; letter-spacing:.28em; font-size:11px; font-weight:800; }
</style>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full flex items-center justify-center p-3 sm:p-6 lg:p-10 overflow-hidden">
  <!-- wheat deco -->
  <svg viewBox="0 0 24 80" class="wheat" style="top:4%; left:4%;"><path fill="currentColor" d="M12 0l2 10-2 3-2-3zm0 13l3 6-3 3-3-3zm0 12l3 8-3 3-3-3zm0 13l3 8-3 3-3-3z"/></svg>
  <svg viewBox="0 0 24 80" class="wheat" style="bottom:4%; right:6%; transform: rotate(25deg);"><path fill="currentColor" d="M12 0l2 10-2 3-2-3zm0 13l3 6-3 3-3-3zm0 12l3 8-3 3-3-3zm0 13l3 8-3 3-3-3z"/></svg>

  <div class="page w-full max-w-5xl h-full max-h-full p-5 sm:p-7 lg:p-9 pl-14 sm:pl-20 lg:pl-24 flex flex-col min-h-0 min-w-0">
    <header class="flex items-start justify-between shrink-0">
      <div>
        <div class="stamp">Signature recipe</div>
        <h1 class="hand text-5xl sm:text-6xl lg:text-7xl leading-none mt-2" style="color:#7c2d12;"><?php echo qd_safe($businessName); ?></h1>
        <div class="serif text-xl sm:text-2xl italic mt-1"><?php echo qd_safe($title); ?></div>
      </div>
      <?php if ($showLogo): ?>
        <div><?php echo qd_logo_markup($business, 52, 'ring-2 ring-amber-700/40'); ?></div>
      <?php endif; ?>
    </header>

    <div class="flex-1 min-h-0 min-w-0 grid md:grid-cols-[1fr_1fr] gap-5 mt-4">
      <div class="min-w-0">
        <div class="qd-kicker">Ingredients</div>
        <ul class="recipe-ingredients mt-2 text-sm sm:text-base">
          <li>4 cups love, sifted</li>
          <li>2 tbsp morning warmth</li>
          <li>1 cup golden crust</li>
          <li>a pinch of patience</li>
          <li><?php echo qd_safe($subtitle); ?></li>
        </ul>

        <div class="qd-kicker mt-5">Method</div>
        <ol class="mt-2 text-sm sm:text-base space-y-1 list-decimal list-inside" style="color:#5a3a1b;">
          <li>QR kodu tarayın.</li>
          <li>Sıranızı alın.</li>
          <li>Kahvenizi ısmarlayın &amp; bekleyin.</li>
          <li>Sıcak krüvasan hazır!</li>
        </ol>

        <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
        <div class="mt-5 grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-3">
          <?php if ($showWaitingCount): ?>
          <div class="bg-amber-100/60 border border-amber-300 rounded-xl px-3 py-2">
            <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
            <div id="qdWaiting" class="hand text-4xl leading-none mt-0"><?php echo (int) $waitingCount; ?></div>
          </div>
          <?php endif; ?>
          <?php if ($showEta): ?>
          <div class="bg-orange-100/70 border border-orange-300 rounded-xl px-3 py-2">
            <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
            <div class="hand text-4xl leading-none"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <span class="serif text-sm italic"><?php echo qd_safe($dict['minutes']); ?></span></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="min-w-0 flex flex-col items-center">
        <div class="qd-kicker">Baker's stamp</div>
        <div class="qd-qr-slot w-full mt-2 min-h-0">
          <div class="relative w-full h-full min-h-0 max-w-[min(100%,18rem)] aspect-square mx-auto rounded-full bg-white flex items-center justify-center"
               style="box-shadow: 0 0 0 2px #b45309, 0 0 0 10px #fff7e6, 0 0 0 12px #b45309, 0 30px 60px -15px rgba(0,0,0,.35);">
            <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img" style="border-radius: 50%; clip-path: circle(46% at 50% 50%);"/>
            <div id="qdQrRefresh" class="absolute -top-2 right-3 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none" style="background:#b45309;color:#fff7e6"><?php echo qd_safe($dict['qr_rotating']); ?></div>
            <span id="qdCountdown" class="hidden"></span>
          </div>
        </div>
        <div class="hand text-2xl mt-3"><?php echo qd_safe($cta); ?></div>
        <div class="text-xs opacity-75"><?php echo qd_safe($dict['scan_cta']); ?></div>

        <?php if ($showActiveNums): ?>
        <div class="w-full mt-4">
          <div class="flex items-center justify-between mb-2">
            <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
            <div id="qdClock" class="text-xs opacity-70 font-mono"></div>
          </div>
          <div id="qdActiveList" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
            <?php if (empty($active)): ?>
              <div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
            <?php else: foreach (array_slice($active, 0, 12) as $i => $e): ?>
              <div class="qd-num <?php echo $i === 0 ? 'qd-num-first' : ($i === 1 ? 'qd-num-second' : ''); ?>"><?php echo (int) $e['queue_number']; ?></div>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($showPowered): ?>
      <div class="mt-3 text-center text-[10px] tracking-[0.3em] uppercase opacity-55 shrink-0">— Powered by Qordy —</div>
    <?php endif; ?>
  </div>
</div>
