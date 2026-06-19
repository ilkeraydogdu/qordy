<?php
/**
 * SECTOR_NIGHTBAR — "Neon tabela" kompozisyonu.
 *
 * Tuğla duvar + ortada asılı mor/cyan neon tabela; iki yanda şişe silüetleri,
 * alt şerit marquee "open late · cocktails · live music".
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(ellipse 80% 60% at 50% 0%, rgba(168,85,247,.35) 0%, transparent 60%),
      radial-gradient(ellipse 80% 60% at 50% 100%, rgba(34,211,238,.28) 0%, transparent 60%),
      linear-gradient(180deg, #0a0118 0%, #050013 100%);
    color:#f5f3ff;
  }
  .brick { position: fixed; inset: 0; z-index:0; pointer-events:none; opacity:.15;
    background-image:
      linear-gradient(0deg, rgba(255,255,255,.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
    background-size: 120px 56px; }
  <?php if ($bgUrl): ?>
  body.qd-door::after { content:''; position:fixed; inset:0; background:url('<?php echo qd_safe($bgUrl); ?>') center/cover; opacity:.2; z-index:0; pointer-events:none; }
  <?php endif; ?>
  .neon-sign { position: relative; border: 3px solid #a855f7; border-radius: 26px;
    padding: clamp(18px, 3vh, 36px) clamp(18px, 3vw, 36px);
    box-shadow: 0 0 6px #a855f7, 0 0 18px #a855f7, inset 0 0 12px rgba(168,85,247,.35), 0 40px 80px -20px rgba(0,0,0,.7);
    background: rgba(10,1,24,.55);
    animation: sign-hum 5s ease-in-out infinite; }
  @keyframes sign-hum { 0%,96%,100%{opacity:1;} 97%{opacity:.6;} 98%{opacity:1;} 99%{opacity:.55;} }
  .neon-brand { font-family: 'Caveat', 'Brush Script MT', cursive; font-weight:700; font-size: clamp(2.5rem, 7vw, 5.5rem); line-height:1; color:#f0abfc;
    text-shadow: 0 0 4px #f0abfc, 0 0 10px #a855f7, 0 0 22px #a855f7, 0 0 40px rgba(168,85,247,.6);
    animation: flicker 4s infinite; }
  @keyframes flicker { 0%,19%,21%,23%,25%,54%,56%,100% { opacity:1 } 20%,22%,24%,55% { opacity:.45 } }
  .neon-sub { font-family: 'Space Grotesk', ui-sans-serif, sans-serif; font-weight:600; letter-spacing:.28em; text-transform:uppercase;
    font-size: clamp(10px, 1.4vw, 12px); color:#67e8f9; text-shadow: 0 0 5px #22d3ee, 0 0 14px #22d3ee; }
  .qr-neon { position:relative; background:#fff; padding: clamp(8px, 1.2vmin, 16px); border-radius: 18px;
    box-shadow: 0 0 0 3px #22d3ee, 0 0 16px #22d3ee, 0 0 32px rgba(34,211,238,.55), 0 30px 60px -15px rgba(0,0,0,.7); }
  .chain { width: 2px; background: linear-gradient(180deg, rgba(203,213,225,.65) 0 6px, transparent 6px 10px); background-size: 100% 10px; margin: 0 auto; height: 42px; }
  .bottle { position: absolute; bottom: 3vh; width: 30px; opacity:.55; filter: drop-shadow(0 0 10px rgba(168,85,247,.5)); }
  .bottle::before { content:''; display:block; width: 8px; height: 22px; background:#0f172a; margin: 0 auto; border-radius: 3px 3px 0 0; }
  .bottle::after { content:''; display:block; width: 22px; height: 58px; border-radius: 4px 4px 10px 10px; background: linear-gradient(180deg, rgba(22,78,99,.9), rgba(9,9,11,.95)); margin: -2px auto 0; box-shadow: inset 0 0 0 1px rgba(255,255,255,.08); }
  .ticker { white-space:nowrap; overflow:hidden; border-top:1px solid rgba(168,85,247,.35); border-bottom:1px solid rgba(34,211,238,.35); background: rgba(10,1,24,.55); }
  .ticker-track { display:inline-block; padding:10px 0; animation: marquee 22s linear infinite; font-weight:800; letter-spacing:.1em; text-transform:uppercase; font-size: clamp(12px, 1.6vw, 16px); color:#f5f3ff; }
  .ticker-track b { color:#f0abfc; text-shadow:0 0 8px rgba(168,85,247,.7); padding:0 24px; }
  .ticker-track i { color:#67e8f9; text-shadow:0 0 8px rgba(34,211,238,.7); padding:0 24px; font-style:normal; }
  @keyframes marquee { from{ transform:translateX(0);} to { transform: translateX(-50%);} }
  .qd-num { aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:900; font-size: clamp(1rem, 2vw, 1.6rem); border-radius: 12px;
    background: rgba(168,85,247,.15); color:#f5f3ff; border:1px solid rgba(168,85,247,.3); }
  .qd-num-first{ background:#a855f7; color:#0a0118; box-shadow: 0 0 18px rgba(168,85,247,.7); }
  .qd-num-second{ background: rgba(34,211,238,.25); border-color: rgba(34,211,238,.35); }
  .qd-empty{ grid-column:1/-1; text-align:center; color:rgba(245,243,255,.55); font-size:11px; padding:8px; }
  .qd-kicker { color: rgba(245,243,255,.6); text-transform: uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<div class="brick"></div>
<div class="bottle" style="left: 6%;"></div>
<div class="bottle" style="right: 6%;"></div>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full flex flex-col items-center justify-between p-3 sm:p-6 lg:p-10 overflow-hidden">
  <div class="chain hidden sm:block"></div>
  <div class="flex-1 min-h-0 w-full flex items-center justify-center">
    <div class="neon-sign w-full max-w-xl text-center flex flex-col items-center gap-3">
      <?php if ($showLogo): ?><div class="mb-1"><?php echo qd_logo_markup($business, 56, 'ring-2 ring-fuchsia-400/50'); ?></div><?php endif; ?>
      <div class="neon-sub"><?php echo qd_safe($dict['welcome']); ?></div>
      <h1 class="neon-brand"><?php echo qd_safe($businessName); ?></h1>
      <div class="neon-sub">— <?php echo qd_safe($title); ?> —</div>
      <div class="qd-qr-slot w-full mt-2 min-h-0">
        <div class="qr-neon w-full h-full min-h-0 max-w-[min(100%,18rem)] mx-auto flex items-center justify-center relative">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none" style="background:#22d3ee;color:#0a0118"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="text-sm sm:text-base font-bold"><?php echo qd_safe($cta); ?></div>
      <div class="text-xs opacity-80"><?php echo qd_safe($subtitle); ?></div>
    </div>
  </div>

  <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
  <div class="w-full grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-3 max-w-xl mt-3">
    <?php if ($showWaitingCount): ?>
    <div class="text-center p-3 rounded-2xl" style="background:rgba(168,85,247,.12);border:1px solid rgba(168,85,247,.35);">
      <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
      <div id="qdWaiting" class="text-4xl sm:text-5xl font-black mt-1" style="color:#f0abfc;text-shadow:0 0 8px rgba(168,85,247,.7);"><?php echo (int) $waitingCount; ?></div>
    </div>
    <?php endif; ?>
    <?php if ($showEta): ?>
    <div class="text-center p-3 rounded-2xl" style="background:rgba(34,211,238,.12);border:1px solid rgba(34,211,238,.35);">
      <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
      <div class="text-4xl sm:text-5xl font-black mt-1" style="color:#67e8f9;text-shadow:0 0 8px rgba(34,211,238,.7);"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span></div>
      <div class="text-[10px] opacity-70 mt-1"><?php echo qd_safe($dict['minutes']); ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($showActiveNums): ?>
  <div class="w-full mt-3 max-w-xl">
    <div class="flex items-center justify-between mb-2">
      <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
      <div id="qdClock" class="text-sm opacity-75"></div>
    </div>
    <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-8 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?><div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else: foreach (array_slice($active, 0, 12) as $i => $e): ?>
        <div class="qd-num <?php echo $i === 0 ? 'qd-num-first' : ($i === 1 ? 'qd-num-second' : ''); ?>"><?php echo (int) $e['queue_number']; ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="ticker w-full mt-3">
    <div class="ticker-track">
      <?php for ($i=0;$i<2;$i++): ?><b>★ Open late ★</b><i>signature cocktails</i><b>★ <?php echo qd_safe($businessName); ?> ★</b><i>live music</i><?php endfor; ?>
    </div>
  </div>
  <?php if ($showPowered): ?><div class="mt-2 text-center text-white/35 text-[10px] tracking-[0.3em] uppercase">Powered by Qordy</div><?php endif; ?>
</div>
