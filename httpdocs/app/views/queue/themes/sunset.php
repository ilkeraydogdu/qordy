<?php
/**
 * SUNSET theme — sıcak gün batımı gradient’leri, kafe / lounge için.
 */
?>
<style>
  body {
    background: #1a0a0f;
    color: #fff7f0;
  }
  .aurora { position: fixed; inset: 0; pointer-events: none; filter: blur(80px); opacity: .6; }
  .aurora::before, .aurora::after { content: ''; position: absolute; border-radius: 50%; }
  .aurora::before { width: 50vw; height: 50vw; background: #f97316; top: -12vw; left: -8vw; animation: aurora-a 16s ease-in-out infinite alternate; }
  .aurora::after  { width: 45vw; height: 45vw; background: #ec4899; bottom: -10vw; right: -6vw; animation: aurora-b 20s ease-in-out infinite alternate; }
  @keyframes aurora-a { 0% { transform: translate(0,0) } 100% { transform: translate(8vw, 12vh) } }
  @keyframes aurora-b { 0% { transform: translate(0,0) } 100% { transform: translate(-10vw, -8vh) } }
  <?php if ($bgUrl): ?>
  body { background: url('<?php echo qd_safe($bgUrl); ?>') center/cover fixed, #1a0a0f; background-blend-mode: soft-light; }
  <?php endif; ?>

  .glass {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.10);
    backdrop-filter: blur(16px) saturate(140%);
    -webkit-backdrop-filter: blur(16px) saturate(140%);
    border-radius: 32px;
    box-shadow: 0 20px 60px -20px rgba(0,0,0,.6);
  }
  .qd-kicker { color: rgba(255,255,255,.55); text-transform: uppercase; letter-spacing: .28em; font-size: 11px; font-weight: 700; }
  .qr-wrap {
    position: relative; margin: 0; padding: clamp(8px, 1.2vmin, 20px); background: #fff; border-radius: 28px;
    box-shadow: 0 0 0 8px rgba(255,255,255,.05), 0 30px 80px -20px var(--accent);
  }
  .qd-qr-slot .qr-wrap { max-height: 100%; }
  .qr-wrap::after {
    content: ''; position: absolute; inset: -14px; border-radius: 36px;
    background: linear-gradient(135deg, var(--accent), transparent 60%);
    filter: blur(20px); opacity: .55; z-index: -1;
  }
  .qd-num { position: relative; aspect-ratio: 1/1; display:flex; align-items:center; justify-content:center; font-weight: 900; font-size: clamp(0.9rem, 1.4vw, 1.5rem); border-radius: 20px; color:#fff; }
  .qd-num-first  { background: var(--accent); color:#0b1020; box-shadow: 0 10px 40px rgba(249,115,22,.45); }
  .qd-num-second { background: rgba(255,255,255,.18); }
  .qd-num-rest   { background: rgba(255,255,255,.06); }
  .qd-empty { grid-column: 1/-1; text-align:center; padding: 8px 6px; font-size: 11px; line-height: 1.3; color: rgba(255,255,255,.45); }
</style>
<?php $doorShowMetrics = !empty($doorShowMetrics); ?>
<div class="aurora"></div>
<div class="relative box-border h-dvh max-h-dvh min-h-0 w-full grid grid-cols-1 <?php echo $doorShowMetrics ? 'grid-rows-1 max-lg:grid-rows-[1fr_1fr] lg:grid-cols-[1.1fr_0.9fr]' : 'place-content-center max-w-3xl lg:max-w-5xl mx-auto'; ?> gap-2 lg:gap-5 p-2 sm:p-3 lg:p-5 overflow-hidden">
  <!-- LEFT: QR hero -->
  <section class="glass p-2 sm:p-4 lg:p-5 flex flex-col h-full min-h-0 min-w-0 items-center text-center overflow-hidden">
    <div class="shrink-0 w-full max-w-lg mx-auto px-0.5">
    <?php if ($showLogo): ?>
      <div class="mb-1 lg:mb-2 flex justify-center"><?php echo qd_logo_markup($business, 60, 'ring-4 ring-white/10'); ?></div>
    <?php endif; ?>
    <div class="qd-kicker"><?php echo qd_safe($dict['welcome']); ?></div>
    <h1 class="mt-0.5 text-xl sm:text-2xl md:text-3xl lg:text-4xl font-extrabold tracking-tight leading-tight line-clamp-2"><?php echo qd_safe($businessName); ?></h1>
    <?php if (!$doorShowMetrics): ?>
    <p class="mt-1.5 sm:mt-2 text-base sm:text-lg text-white/90 font-semibold max-w-prose mx-auto leading-snug line-clamp-3"><?php echo qd_safe($title); ?></p>
    <p class="mt-0.5 text-sm sm:text-base text-white/70 max-w-prose mx-auto leading-snug line-clamp-3"><?php echo qd_safe($subtitle); ?></p>
    <?php endif; ?>
    </div>

    <div class="qd-qr-slot w-full min-h-0">
    <div class="qr-wrap relative w-full h-full min-h-0 max-w-[min(100%,22rem)] flex items-center justify-center">
      <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
      <div id="qdQrRefresh" class="absolute -top-1 -right-1 sm:-top-2 sm:-right-2 text-[9px] sm:text-[10px] font-bold px-2 py-1 sm:px-3 sm:py-1.5 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none" style="background: var(--accent); color:#000">
        <?php echo qd_safe($dict['qr_rotating']); ?>
      </div>
      <span id="qdCountdown" class="hidden"></span>
    </div>
    </div>

    <div class="shrink-0 w-full max-w-lg mx-auto mt-1 sm:mt-2 px-0.5">
    <div class="text-sm sm:text-base md:text-lg lg:text-xl font-bold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)] leading-tight line-clamp-2"><?php echo qd_safe($cta); ?></div>
    <div class="mt-0.5 text-xs sm:text-sm text-white/95 font-medium max-w-md mx-auto leading-snug line-clamp-2" style="text-shadow:0 1px 3px rgba(0,0,0,0.45)"><?php echo qd_safe($dict['scan_cta']); ?></div>
    <div class="mt-1 scale-90 sm:scale-100 origin-top"><?php require __DIR__ . '/_door_social_row.php'; ?></div>

    <div class="mt-1 flex gap-1.5 flex-wrap justify-center">
      <?php foreach ($languages as $l): ?>
        <span class="px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-[9px] sm:text-[10px] uppercase tracking-wide border border-white/15 bg-white/5"><?php echo qd_safe($l); ?></span>
      <?php endforeach; ?>
    </div>
    </div>
  </section>

  <?php if ($doorShowMetrics): ?>
  <section class="flex flex-col gap-1.5 lg:gap-2.5 h-full min-h-0 min-w-0 overflow-hidden">
    <div class="glass p-3 sm:p-4 lg:p-5 shrink-0 min-h-0">
      <div class="qd-kicker"><?php echo qd_safe($dict['all_full']); ?></div>
      <div class="mt-1.5 sm:mt-2 text-lg sm:text-xl lg:text-2xl leading-tight font-bold line-clamp-2"><?php echo qd_safe($title); ?></div>
      <div class="mt-1 text-white/70 text-sm sm:text-base line-clamp-2"><?php echo qd_safe($subtitle); ?></div>
    </div>

    <?php if ($showWaitingCount || $showEta): ?>
    <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-1.5 sm:gap-2 shrink-0 min-h-0">
      <?php if ($showWaitingCount): ?>
      <div class="glass p-3 sm:p-4">
        <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
        <div id="qdWaiting" class="mt-1.5 sm:mt-2 text-4xl sm:text-5xl lg:text-6xl font-black clock tracking-tighter leading-none"><?php echo (int) $waitingCount; ?></div>
        <div class="mt-0.5 text-white/50 text-xs"><?php echo qd_safe($dict['groups']); ?></div>
      </div>
      <?php endif; ?>
      <?php if ($showEta): ?>
      <div class="glass p-3 sm:p-4">
        <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
        <div class="mt-1.5 sm:mt-2 text-4xl sm:text-5xl lg:text-6xl font-black clock tracking-tighter leading-none"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span></div>
        <div class="mt-0.5 text-white/50 text-xs"><?php echo qd_safe($dict['minutes']); ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showActiveNums): ?>
    <div class="glass p-2 sm:p-3 lg:p-4 flex-1 min-h-0 flex flex-col overflow-hidden">
      <div class="flex items-center justify-between mb-1 sm:mb-2 shrink-0">
        <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
        <div id="qdClock" class="text-white/70 clock text-sm sm:text-base"></div>
      </div>
      <div id="qdActiveList" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-1.5 sm:gap-2 min-h-0 overflow-hidden content-start" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
        <?php if (empty($active)): ?>
          <div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
        <?php else: foreach (array_slice($active, 0, 12) as $i => $e): ?>
          <div class="qd-num <?php echo $i === 0 ? 'qd-num-first' : ($i === 1 ? 'qd-num-second' : 'qd-num-rest'); ?>">
            <?php if ($i === 0): ?><span class="absolute inset-0 rounded-2xl pulse"></span><?php endif; ?>
            <span class="relative"><?php echo (int) $e['queue_number']; ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($showPowered): ?>
      <div class="mt-auto text-center text-white/35 text-xs tracking-[0.2em] uppercase">Powered by Qordy</div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
