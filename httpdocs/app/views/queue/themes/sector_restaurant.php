<?php
/**
 * SECTOR_RESTAURANT — "Kara tahta menü" kompozisyonu.
 *
 * Sol panel: tebeşir dokulu kara tahta üzerinde el yazısı menü satırları,
 *            altın menü başlığı, küçük peçete/çerçeve.
 * Sağ panel: QR'ı bir "menü kartı" gibi sunan krem kağıt, mum alevi vurgusu,
 *            altında sıra numaraları ve opsiyonel bekleme metriği.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(1200px 700px at 50% -20%, #3a2410 0%, transparent 55%),
      linear-gradient(180deg, #1a0e06 0%, #0b0604 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    background-blend-mode: multiply;
    color: #fff7ed;
  }
  .chalkboard {
    position: relative;
    background:
      radial-gradient(circle at 25% 30%, rgba(255,255,255,.05), transparent 60%),
      radial-gradient(circle at 75% 70%, rgba(255,255,255,.04), transparent 55%),
      linear-gradient(180deg, #12160f 0%, #0a0e08 100%);
    border-radius: 22px;
    box-shadow: 0 0 0 6px #3a2410, 0 0 0 10px #5a3a1b, 0 30px 60px -20px rgba(0,0,0,.7);
    overflow: hidden;
  }
  .chalkboard::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background-image:
      repeating-linear-gradient(0deg, rgba(255,255,255,.02) 0 1px, transparent 1px 3px),
      repeating-linear-gradient(90deg, rgba(255,255,255,.015) 0 1px, transparent 1px 4px);
    mix-blend-mode: overlay;
  }
  .chalk {
    font-family: 'Caveat', 'Brush Script MT', cursive;
    color: #fdf6e3; text-shadow: 0 0 1px rgba(253,246,227,.5);
    letter-spacing: .01em;
  }
  .chalk-title { font-family: 'Cormorant Garamond', Georgia, serif; font-style: italic; }
  .gold-line { height: 1px; background: linear-gradient(90deg, transparent, var(--accent) 20%, var(--accent) 80%, transparent); }
  .menu-row { display:flex; align-items:center; gap:10px; opacity:.92; }
  .menu-row .leader { flex:1; border-bottom: 1px dotted rgba(255,255,255,.25); margin-bottom: 6px; }
  .menu-row .price { color: var(--accent); font-weight:700; font-family: 'Cormorant Garamond', serif; }

  .menu-card {
    background: #fdf3d9;
    color: #3a2410;
    border-radius: 14px;
    box-shadow:
      0 40px 80px -30px rgba(0,0,0,.65),
      inset 0 0 0 1px rgba(90,58,27,.25);
    position: relative;
  }
  .menu-card::after {
    content:''; position:absolute; inset:0; pointer-events:none; border-radius: inherit;
    background:
      radial-gradient(circle at 30% 20%, rgba(122,82,39,.06) 0 1px, transparent 2px) 0 0/4px 4px,
      radial-gradient(circle at 70% 80%, rgba(122,82,39,.05) 0 1px, transparent 2px) 2px 2px/4px 4px;
  }
  .menu-card .qr-frame {
    background:#fff; padding: clamp(8px, 1.2vmin, 16px); border-radius: 8px;
    box-shadow: inset 0 0 0 1px #3a2410, 0 10px 30px -10px rgba(58,36,16,.45);
  }
  .candle { position:absolute; left: 50%; top: -16px; transform: translateX(-50%); }
  .candle .flame {
    width: 12px; height: 18px; border-radius: 50% 50% 45% 45% / 60% 60% 40% 40%;
    background: radial-gradient(circle at 50% 70%, #fde68a 0%, #f59e0b 55%, #b45309 100%);
    box-shadow: 0 0 20px rgba(251,191,36,.65), 0 0 40px rgba(251,146,60,.35);
    animation: flicker 1.2s ease-in-out infinite alternate;
  }
  .candle .wick { width: 2px; height: 6px; background:#1f1209; margin: -2px auto 0; }
  .candle .stick { width: 6px; height: 28px; background: linear-gradient(180deg,#fef3c7,#fbbf24); margin: 0 auto; border-radius: 3px; }
  @keyframes flicker {
    0%{ transform: translateY(0) scale(1);} 100%{ transform: translateY(-1px) scale(1.05) rotate(-2deg);}
  }

  .qd-num {
    aspect-ratio: 1/1; display:flex; align-items:center; justify-content:center;
    font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: clamp(1rem, 2vw, 1.6rem);
    border-radius: 10px;
    color:#fef3c7; background: rgba(253,230,138,.06); border: 1px solid rgba(253,230,138,.15);
  }
  .qd-num-first  { background: var(--accent); color:#1a0e06; border-color: transparent; box-shadow: 0 10px 30px rgba(234,88,12,.45); position: relative; }
  .qd-num-second { background: rgba(253,230,138,.18); color:#fef3c7; }
  .qd-empty { grid-column: 1/-1; text-align:center; color: rgba(254,243,199,.5); font-size: 11px; padding: 8px; }
  .qd-kicker { color: rgba(254,243,199,.55); text-transform: uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<?php
$doorShowMetrics = !empty($doorShowMetrics);
$menuItems = [
    ['Soup of the day',       '₺ 120'],
    ['Grilled lamb chops',    '₺ 680'],
    ['Sea bass, lemon butter','₺ 540'],
    ['Mezze selection',       '₺ 320'],
    ['House tiramisù',        '₺ 160'],
];
?>
<div class="box-border h-dvh max-h-dvh w-full grid grid-cols-1 lg:grid-cols-[1fr_1.1fr] gap-3 sm:gap-4 lg:gap-6 p-3 sm:p-4 lg:p-6 overflow-hidden">
  <!-- LEFT — chalkboard menu -->
  <section class="chalkboard p-5 sm:p-6 lg:p-8 flex flex-col min-h-0 min-w-0 overflow-hidden">
    <div class="shrink-0 text-center">
      <?php if ($showLogo): ?>
        <div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 56, 'ring-2 ring-amber-300/30'); ?></div>
      <?php endif; ?>
      <div class="qd-kicker" style="color: rgba(251,191,36,.8)"><?php echo qd_safe($dict['welcome']); ?></div>
      <h1 class="chalk-title mt-1 text-3xl sm:text-4xl lg:text-5xl font-bold italic" style="color:#fde68a;"><?php echo qd_safe($businessName); ?></h1>
      <div class="gold-line my-3 sm:my-4"></div>
    </div>

    <div class="flex-1 min-h-0 min-w-0 flex flex-col justify-center gap-2 sm:gap-3 chalk">
      <?php foreach ($menuItems as [$name, $price]): ?>
        <div class="menu-row text-lg sm:text-xl lg:text-2xl">
          <span><?php echo qd_safe($name); ?></span>
          <span class="leader"></span>
          <span class="price"><?php echo qd_safe($price); ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="shrink-0 mt-3 sm:mt-4 text-center chalk text-lg sm:text-xl italic opacity-85">
      <?php echo qd_safe($title); ?>
    </div>
  </section>

  <!-- RIGHT — menu card + QR + (optional metrics) -->
  <section class="flex flex-col min-h-0 min-w-0 gap-3 overflow-hidden">
    <div class="menu-card p-5 sm:p-6 lg:p-8 flex-1 min-h-0 flex flex-col items-center justify-center text-center">
      <div class="candle" aria-hidden="true">
        <div class="flame"></div>
        <div class="wick"></div>
        <div class="stick"></div>
      </div>

      <div class="qd-kicker mt-1" style="color:#7a5227;"><?php echo qd_safe($dict['all_full']); ?></div>
      <div class="chalk-title text-2xl sm:text-3xl lg:text-4xl font-bold italic mt-1" style="color:#3a2410;"><?php echo qd_safe($title); ?></div>
      <div class="mt-1 text-sm sm:text-base" style="color:#6b4423;"><?php echo qd_safe($subtitle); ?></div>

      <div class="qd-qr-slot w-full mt-4 sm:mt-5 min-h-0">
        <div class="qr-frame relative w-full h-full min-h-0 max-w-[min(100%,22rem)] flex items-center justify-center mx-auto">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-1 -right-1 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none" style="background:var(--accent); color:#1a0e06"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>

      <div class="mt-3 text-base sm:text-lg font-bold" style="color:#3a2410;"><?php echo qd_safe($cta); ?></div>
      <div class="mt-0.5 text-xs sm:text-sm" style="color:#6b4423;"><?php echo qd_safe($dict['scan_cta']); ?></div>
    </div>

    <?php if ($doorShowMetrics): ?>
    <div class="grid grid-cols-2 gap-3 shrink-0">
      <?php if ($showWaitingCount): ?>
      <div class="chalkboard p-3 sm:p-4 text-center">
        <div class="qd-kicker"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
        <div id="qdWaiting" class="chalk-title text-4xl sm:text-5xl font-bold leading-none mt-1"><?php echo (int) $waitingCount; ?></div>
        <div class="text-[11px] mt-1 opacity-60"><?php echo qd_safe($dict['groups']); ?></div>
      </div>
      <?php endif; ?>
      <?php if ($showEta): ?>
      <div class="chalkboard p-3 sm:p-4 text-center">
        <div class="qd-kicker"><?php echo qd_safe($dict['eta']); ?></div>
        <div class="chalk-title text-4xl sm:text-5xl font-bold leading-none mt-1"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span></div>
        <div class="text-[11px] mt-1 opacity-60"><?php echo qd_safe($dict['minutes']); ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showActiveNums): ?>
    <div class="chalkboard p-3 sm:p-4 shrink-0 min-h-0">
      <div class="flex items-center justify-between mb-2">
        <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
        <div id="qdClock" class="chalk-title text-base opacity-80"></div>
      </div>
      <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-8 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
        <?php if (empty($active)): ?>
          <div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
        <?php else: foreach (array_slice($active, 0, 12) as $i => $e): ?>
          <div class="qd-num <?php echo $i === 0 ? 'qd-num-first' : ($i === 1 ? 'qd-num-second' : ''); ?>"><?php echo (int) $e['queue_number']; ?></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($showPowered): ?>
      <div class="text-center text-white/40 text-[10px] tracking-[0.3em] uppercase shrink-0">Powered by Qordy</div>
    <?php endif; ?>
  </section>
</div>
