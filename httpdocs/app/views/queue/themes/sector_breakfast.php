<?php
/**
 * SECTOR_BREAKFAST — "Serpme kahvaltı sofrası" kompozisyonu.
 *
 * Güneşli pastel arka plan. Üstte "serpme" küçük tabak ızgarası (domates,
 * peynir, zeytin, bal, reçel, yumurta, salatalık, ekmek). Ortada ince belli
 * çay bardaklı QR kartı. Altta metrikler.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(600px 400px at 80% 0%, #fde68a 0%, transparent 60%),
      radial-gradient(800px 500px at 0% 90%, #fed7aa 0%, transparent 60%),
      linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color:#78350f;
    background-blend-mode: multiply;
  }
  .sun { position: fixed; top: 28px; right: 28px; width: 96px; height: 96px; border-radius:50%; background: radial-gradient(#fef9c3, #fbbf24 70%); box-shadow: 0 0 60px #fbbf24, 0 0 120px #fde68a; animation: glow 4s ease-in-out infinite alternate; z-index: 0;}
  @keyframes glow { to { transform: scale(1.05); filter: brightness(1.05);} }
  .sun-ray { position: fixed; top: 76px; right: 76px; width: 240px; height: 240px; z-index: 0; opacity:.35; pointer-events:none; }

  .plates { display:grid; grid-template-columns: repeat(8, 1fr); gap: 10px; }
  .plate { position: relative; aspect-ratio: 1/1; border-radius:50%; background:#ffffff; box-shadow: 0 10px 20px -6px rgba(120,53,15,.25), inset 0 0 0 3px #fef3c7;}
  .plate > .food { position:absolute; inset: 14%; border-radius:50%; }
  .plate .tom { background: radial-gradient(#f87171, #dc2626); }
  .plate .che { background: repeating-linear-gradient(45deg, #fef08a 0 6px, #fde68a 6px 12px); border-radius: 8px; }
  .plate .oli { background: radial-gradient(#166534, #052e16); }
  .plate .hon { background: radial-gradient(#fde047, #ca8a04); }
  .plate .jam { background: radial-gradient(#f472b6, #be185d); }
  .plate .egg { background: radial-gradient(#fef3c7 0 42%, #fef9c3 42% 55%, #fff 55%); }
  .plate .cuc { background: repeating-radial-gradient(#86efac 0 3px, #16a34a 3px 7px); border-radius: 40%; }
  .plate .bre { background: linear-gradient(160deg,#fde68a,#d97706); border-radius: 10px 14px 14px 10px; }

  .tea { position: relative; width: 64px; height: 88px; background: linear-gradient(180deg,#b91c1c,#7f1d1d); clip-path: polygon(0 0, 100% 0, 94% 28%, 62% 44%, 62% 56%, 94% 72%, 100% 100%, 0 100%, 6% 72%, 38% 56%, 38% 44%, 6% 28%); margin: 0 auto; box-shadow: 0 10px 22px rgba(0,0,0,.25); }
  .tea::after { content:''; position:absolute; left:50%; top: 10px; transform: translateX(-50%); width: 36px; height: 6px; border-radius: 4px; background: rgba(255,255,255,.6); }
  .saucer { width: 92px; height: 14px; border-radius:50%; background:#ffffff; margin: 4px auto 0; box-shadow: 0 6px 14px rgba(120,53,15,.25); }

  .qr-card { background:#fffdf4; border-radius:28px; padding: clamp(18px,3vh,28px); box-shadow: 0 30px 60px -20px rgba(120,53,15,.35), inset 0 0 0 2px rgba(120,53,15,.06); position: relative; }
  .qr-card::before { content:''; position: absolute; top: -14px; left: 50%; transform: translateX(-50%); width: 60%; height: 28px; background: repeating-linear-gradient(90deg, #fbbf24 0 18px, #f59e0b 18px 36px); border-radius: 12px; box-shadow: 0 6px 14px rgba(120,53,15,.25);} /* bal süzgeci barı */

  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:800; font-size: clamp(1rem,2vw,1.6rem); border-radius:10px; background:#fff7ed; color:#78350f; border:1px solid rgba(120,53,15,.2); font-family:'Caveat',cursive; }
  .qd-num-first{ background:#f59e0b; color:#fff; }
  .qd-num-second{ background:#fed7aa; }
  .qd-empty{ grid-column:1/-1; text-align:center; color:rgba(120,53,15,.55); font-size:11px; padding:8px; }
  .qd-kicker { color:#78350f; opacity:.7; text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<div class="sun"></div>

<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="relative box-border h-dvh max-h-dvh w-full flex flex-col p-3 sm:p-6 lg:p-10 overflow-hidden">
  <div class="text-center mb-3">
    <?php if ($showLogo): ?><div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 50, 'ring-4 ring-amber-300/50'); ?></div><?php endif; ?>
    <div class="text-[11px] sm:text-xs tracking-[0.4em] uppercase opacity-70">— serpme kahvaltı · günaydın —</div>
    <h1 class="text-3xl sm:text-5xl font-black mt-1" style="font-family:'Caveat',cursive; color:#b45309;"><?php echo qd_safe($businessName); ?></h1>
  </div>

  <div class="plates mb-4">
    <div class="plate"><div class="food tom"></div></div>
    <div class="plate"><div class="food cuc"></div></div>
    <div class="plate"><div class="food che"></div></div>
    <div class="plate"><div class="food oli"></div></div>
    <div class="plate"><div class="food hon"></div></div>
    <div class="plate"><div class="food jam"></div></div>
    <div class="plate"><div class="food egg"></div></div>
    <div class="plate"><div class="food bre"></div></div>
  </div>

  <div class="flex-1 min-h-0 grid grid-cols-1 md:grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-4">
    <div class="hidden md:flex flex-col items-center"><div class="tea"></div><div class="saucer"></div></div>

    <div class="qr-card text-center">
      <div class="text-2xl sm:text-3xl font-black mb-1" style="font-family:'Caveat',cursive; color:#b45309;"><?php echo qd_safe($title); ?></div>
      <div class="text-xs opacity-75 mb-3"><?php echo qd_safe($subtitle); ?></div>
      <div class="qd-qr-slot flex items-center justify-center">
        <div class="relative bg-white p-2 rounded-lg shadow-xl max-w-[min(72%,260px)] ring-4 ring-amber-200">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 bg-amber-500 text-amber-50 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      <div class="mt-2 font-bold text-sm sm:text-base"><?php echo qd_safe($cta); ?></div>
    </div>

    <div class="hidden md:flex flex-col items-center"><div class="tea"></div><div class="saucer"></div></div>
  </div>

  <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
  <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-3 mt-4">
    <?php if ($showWaitingCount): ?>
    <div class="rounded-2xl p-3 text-center bg-white shadow-md border-2 border-amber-300">
      <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
      <div id="qdWaiting" class="text-4xl sm:text-5xl font-black text-amber-600" style="font-family:'Caveat',cursive;"><?php echo (int) $waitingCount; ?></div>
    </div>
    <?php endif; ?>
    <?php if ($showEta): ?>
    <div class="rounded-2xl p-3 text-center bg-white shadow-md border-2 border-lime-400">
      <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
      <div class="text-4xl sm:text-5xl font-black text-lime-700" style="font-family:'Caveat',cursive;"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <span class="text-sm"><?php echo qd_safe($dict['minutes']); ?></span></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
