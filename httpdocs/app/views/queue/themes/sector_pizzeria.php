<?php
/**
 * SECTOR_PIZZERIA — "Trattoria kareli" kompozisyonu.
 *
 * Kırmızı-beyaz kareli masa örtüsü arka plan, üstte İtalyan bayrağı şeridi,
 * solda dönen pizza dilimi, sağda ahşap kesme tahtası üstünde menü & QR.
 */
?>
<style>
  body.qd-door {
    background:
      repeating-conic-gradient(#dc2626 0 25%, #fff1f2 0 50%) 0 0 / 64px 64px,
      #fff1f2;
    color:#7f1d1d;
  }
  <?php if ($bgUrl): ?>
  body.qd-door::before { content:''; position: fixed; inset: 0; background: url('<?php echo qd_safe($bgUrl); ?>') center/cover; opacity:.25; z-index:0; pointer-events:none; }
  <?php endif; ?>
  .flag { position: relative; display:flex; align-items:center; width:100%; height: 28px; border-radius: 999px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,.18); background:#fff; }
  .flag > div { flex: 1; height: 100%; }
  .flag .g { background:#166534; }
  .flag .w { background:#ffffff; position: relative; }
  .flag .r { background:#dc2626; }
  .flag .w::after { content:'TRATTORIA'; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-family: 'Cormorant Garamond', Georgia, serif; font-size: 13px; letter-spacing:.4em; font-weight:700; color:#0f172a; }
  .wood { background: linear-gradient(180deg,#c2926b 0%,#8b5a2b 100%); border: 8px solid #3e2411; border-radius: 22px; box-shadow: inset 0 0 0 2px rgba(255,255,255,.15), 0 30px 70px -20px rgba(0,0,0,.45); padding: clamp(16px, 3vh, 32px) clamp(14px, 3vw, 28px); }
  .wood-title { font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 700; }
  .pizza { width: min(34vmin, 280px); aspect-ratio:1/1; border-radius:50%; background: #f4a261;
    border: 10px solid #a16207; box-shadow: 0 30px 60px -15px rgba(0,0,0,.45);
    position: relative; animation: spin 40s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg);} }
  .pizza::before { content:''; position:absolute; inset:6%; border-radius:50%; background: radial-gradient(#fed7aa 0 15%, #fbbf24 16% 40%, #ea580c 41% 70%, #fed7aa 70% 100%); }
  .pep { position:absolute; width: 18%; height: 18%; border-radius:50%; background:#b91c1c; box-shadow: inset -2px -2px 0 rgba(0,0,0,.25); }
  .olive { position:absolute; width: 8%; height: 8%; border-radius:50%; background:#166534; }
  .basil { position:absolute; width: 12%; height: 8%; background:#22c55e; border-radius: 60% 40% 50% 50%; transform: rotate(20deg); }
  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:800; font-size: clamp(1rem,2vw,1.6rem); border-radius:10px; background:#fff7ed; color:#7f1d1d; border:2px solid #b91c1c; font-family:'Cormorant Garamond',serif; }
  .qd-num-first{ background:#b91c1c; color:#fff7ed; }
  .qd-num-second{ background:#fecaca; }
  .qd-empty{ grid-column:1/-1; text-align:center; color:#7f1d1d; font-size:11px; padding:8px; }
  .qd-kicker { color:#7f1d1d; opacity:.75; text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; font-family:'Cormorant Garamond',serif; }
</style>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full flex flex-col items-stretch gap-4 p-3 sm:p-6 lg:p-10 overflow-hidden">
  <div class="flag"><div class="g"></div><div class="w"></div><div class="r"></div></div>

  <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] gap-4 flex-1 min-h-0 items-center">
    <!-- Pizza dilimi (dairesel pizza) -->
    <div class="flex items-center justify-center">
      <div class="pizza">
        <div class="pep" style="top:12%; left:34%;"></div>
        <div class="pep" style="top:36%; right:18%;"></div>
        <div class="pep" style="bottom:18%; left:22%;"></div>
        <div class="pep" style="bottom:32%; right:36%;"></div>
        <div class="olive" style="top:28%; left:24%;"></div>
        <div class="olive" style="bottom:24%; right:28%;"></div>
        <div class="olive" style="top:46%; right:12%;"></div>
        <div class="basil" style="top:18%; right:30%;"></div>
        <div class="basil" style="bottom:28%; left:36%;"></div>
      </div>
    </div>

    <!-- Menü + QR board -->
    <div class="wood h-full min-h-0 flex flex-col">
      <div class="text-center text-[#fff7ed]">
        <?php if ($showLogo): ?><div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 52, 'ring-2 ring-amber-200/60'); ?></div><?php endif; ?>
        <div class="wood-title text-3xl sm:text-4xl lg:text-5xl leading-tight italic" style="text-shadow:0 2px 0 rgba(0,0,0,.25);">Ristorante</div>
        <div class="wood-title text-xl sm:text-2xl leading-tight"><?php echo qd_safe($businessName); ?></div>
      </div>
      <div class="mt-3 grid grid-cols-2 gap-2 text-sm text-[#fff7ed]">
        <div class="wood-title opacity-85">· Margherita</div><div class="text-right opacity-85">d.a.c.</div>
        <div class="wood-title opacity-85">· Quattro formaggi</div><div class="text-right opacity-85">d.a.c.</div>
        <div class="wood-title opacity-85">· Diavola</div><div class="text-right opacity-85">d.a.c.</div>
      </div>
      <div class="mt-3 text-center text-[#fff7ed]">
        <div class="wood-title italic text-2xl sm:text-3xl"><?php echo qd_safe($title); ?></div>
        <div class="text-xs opacity-80 mt-0.5"><?php echo qd_safe($subtitle); ?></div>
      </div>
      <div class="qd-qr-slot flex items-center justify-center mt-3">
        <div class="relative bg-white p-2 rounded-lg shadow-xl max-w-[min(70%,240px)]">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 bg-red-700 text-amber-50 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="text-center mt-2 text-[#fff7ed] font-bold text-sm sm:text-base"><?php echo qd_safe($cta); ?></div>
    </div>
  </div>

  <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
  <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-3">
    <?php if ($showWaitingCount): ?>
    <div class="rounded-2xl p-3 text-center bg-white shadow-md border-2 border-red-700">
      <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
      <div id="qdWaiting" class="text-4xl sm:text-5xl font-black text-red-700"><?php echo (int) $waitingCount; ?></div>
    </div>
    <?php endif; ?>
    <?php if ($showEta): ?>
    <div class="rounded-2xl p-3 text-center bg-white shadow-md border-2 border-green-800">
      <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
      <div class="text-4xl sm:text-5xl font-black text-green-800"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span></div>
      <div class="text-[10px] opacity-70"><?php echo qd_safe($dict['minutes']); ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
