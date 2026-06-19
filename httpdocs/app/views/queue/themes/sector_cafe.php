<?php
/**
 * SECTOR_CAFE — "Kasa fişi / kahve fişi" kompozisyonu.
 *
 * Merkezde termal kasa fişi kağıdı; üstte işletme başlığı, sipariş satırları,
 * QR'ın altında barkod-benzeri çizgiler ve tarih-saat. Çevresinde buharı
 * temsil eden yumuşak bulut shape'leri ve kahve çekirdeği dekor.
 */
?>
<style>
  body.qd-door {
    background:
      radial-gradient(1000px 600px at 20% 10%, #3d2b1e 0%, transparent 55%),
      radial-gradient(1000px 600px at 80% 90%, #1f1410 0%, transparent 60%),
      linear-gradient(180deg, #1c1410 0%, #0d0807 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color:#fef3c7;
    background-blend-mode: multiply;
  }
  .steam {
    position: fixed; inset: 0; pointer-events: none; z-index: 0; opacity:.55;
    filter: blur(60px);
  }
  .steam::before, .steam::after {
    content:''; position:absolute; border-radius:50%; background:#d6a77a;
    animation: drift 18s ease-in-out infinite alternate;
  }
  .steam::before { width: 44vw; height: 44vw; top:-16vw; left:-8vw; }
  .steam::after  { width: 38vw; height: 38vw; bottom:-12vw; right:-10vw; background:#78350f; animation-duration: 22s; }
  @keyframes drift { to { transform: translate(6vw, -4vh) scale(1.04);} }

  .receipt {
    position: relative;
    background:
      repeating-linear-gradient(0deg, rgba(0,0,0,.03) 0 1px, transparent 1px 4px),
      #fffdf4;
    color:#2a1a0f;
    font-family: 'Roboto Mono', ui-monospace, Menlo, monospace;
    box-shadow: 0 45px 90px -20px rgba(0,0,0,.55), 0 0 0 1px rgba(0,0,0,.04);
    max-width: min(96%, 560px);
    width: 100%;
    margin: auto;
  }
  /* torn-edge top and bottom */
  .receipt::before, .receipt::after {
    content:''; position:absolute; left:0; right:0; height: 14px;
    background:
      radial-gradient(circle at 10px 0, transparent 6px, #fffdf4 6.5px) 0 0/20px 14px;
  }
  .receipt::before { top:-14px; transform: scaleY(-1); }
  .receipt::after  { bottom:-14px; }
  .receipt-inner { padding: clamp(18px, 3.5vw, 36px) clamp(16px, 4vw, 44px); }
  .receipt .dash { border-top: 1px dashed rgba(0,0,0,.25); }
  .receipt-row { display:flex; justify-content:space-between; gap:8px; font-size: clamp(12px, 1.6vw, 14px); padding: 2px 0; }
  .receipt-row .v { font-weight: 700; }

  .cafe-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-weight: 700;
    letter-spacing: .02em;
  }
  .stamp {
    display:inline-block; border: 2px solid #a16207; color:#a16207;
    padding: 4px 12px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase;
    font-size: clamp(10px, 1.4vw, 12px); transform: rotate(-6deg); border-radius: 4px;
    background: rgba(161,98,7,.05);
  }

  .barcode {
    display:flex; gap: 2px; height: 44px; align-items:stretch; justify-content:center; margin: 10px auto 0;
    max-width: 320px;
  }
  .barcode span { display:block; width: 3px; background:#2a1a0f; border-radius:1px; }
  .bar-w { width: 5px; }
  .bar-t { height: 32px; align-self: center; }

  .bean { position: absolute; width: 34px; height: 22px; border-radius: 50%; background: #3e2a1a; box-shadow: inset -2px -3px 0 #2a1a0f; }
  .bean::before { content:''; position:absolute; inset:0; border-radius:50%; background: linear-gradient(90deg, transparent 48%, #1c100a 48%, #1c100a 52%, transparent 52%); }

  .qd-num {
    aspect-ratio:1/1; display:flex; align-items:center; justify-content:center;
    font-family: 'Roboto Mono', ui-monospace, monospace; font-weight:800;
    font-size: clamp(1rem, 2vw, 1.6rem); border-radius: 8px;
    background: rgba(255,253,244,.08); color:#fff7ed; border: 1px solid rgba(255,253,244,.12);
  }
  .qd-num-first{ background:#d97706; color:#1c1410; border-color:transparent; }
  .qd-num-second{ background:rgba(217,119,6,.3); }
  .qd-empty{ grid-column:1/-1; text-align:center; font-size:11px; color:rgba(255,253,244,.5); padding:8px; }
  .qd-kicker { color: rgba(255,253,244,.55); text-transform: uppercase; letter-spacing:.28em; font-size:11px; font-weight:700; }
</style>

<div class="steam"></div>
<!-- bean dekor -->
<div class="bean hidden sm:block" style="top:8%; left:8%; transform: rotate(-14deg);"></div>
<div class="bean hidden sm:block" style="top:14%; right:10%; transform: rotate(22deg);"></div>
<div class="bean hidden sm:block" style="bottom:10%; left:12%; transform: rotate(44deg);"></div>
<div class="bean hidden sm:block" style="bottom:6%; right:14%; transform: rotate(-34deg);"></div>

<?php
$doorShowMetrics = !empty($doorShowMetrics);
$now = new DateTime();
$orderNo = '#' . str_pad((string) ((int) ($waitingCount ?? 0) + 1337), 4, '0', STR_PAD_LEFT);
?>
<div class="relative box-border h-dvh max-h-dvh w-full flex items-center justify-center p-3 sm:p-5 lg:p-8 overflow-hidden">
  <div class="receipt">
    <div class="receipt-inner">
      <div class="text-center">
        <?php if ($showLogo): ?>
          <div class="flex justify-center mb-2"><?php echo qd_logo_markup($business, 48, 'ring-2 ring-amber-700/30'); ?></div>
        <?php endif; ?>
        <div class="cafe-title text-3xl sm:text-4xl lg:text-5xl leading-tight"><?php echo qd_safe($businessName); ?></div>
        <div class="mt-1 tracking-[0.3em] text-[10px] sm:text-[11px] uppercase opacity-70">— café · bar —</div>
        <div class="mt-3 stamp"><?php echo qd_safe($dict['all_full']); ?></div>
      </div>

      <div class="dash my-4"></div>

      <div class="space-y-0.5">
        <div class="receipt-row"><span>Order no</span><span class="v"><?php echo qd_safe($orderNo); ?></span></div>
        <div class="receipt-row"><span>Date</span><span class="v"><?php echo $now->format('d.m.Y'); ?></span></div>
        <div class="receipt-row"><span>Time</span><span class="v clock"><?php echo $now->format('H:i'); ?></span></div>
        <?php if ($doorShowMetrics && $showWaitingCount): ?>
        <div class="receipt-row"><span><?php echo qd_safe($dict['waiting_lbl']); ?></span><span class="v"><span id="qdWaiting"><?php echo (int) $waitingCount; ?></span> × <?php echo qd_safe($dict['groups']); ?></span></div>
        <?php endif; ?>
        <?php if ($doorShowMetrics && $showEta): ?>
        <div class="receipt-row"><span><?php echo qd_safe($dict['eta']); ?></span><span class="v"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span> <?php echo qd_safe($dict['minutes']); ?></span></div>
        <?php endif; ?>
      </div>

      <div class="dash my-4"></div>

      <div class="text-center">
        <div class="cafe-title text-xl sm:text-2xl leading-tight"><?php echo qd_safe($title); ?></div>
        <div class="mt-1 text-xs sm:text-sm opacity-70"><?php echo qd_safe($subtitle); ?></div>
      </div>

      <div class="qd-qr-slot flex items-center justify-center mt-4">
        <div class="relative bg-white p-2 sm:p-3" style="border: 2px solid #2a1a0f; max-width: min(72%, 280px);">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 -right-2 text-[9px] font-bold px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none" style="background:#d97706;color:#1c1410"><?php echo qd_safe($dict['qr_rotating']); ?></div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>

      <div class="text-center mt-3 text-sm sm:text-base font-bold"><?php echo qd_safe($cta); ?></div>
      <div class="text-center text-xs opacity-70"><?php echo qd_safe($dict['scan_cta']); ?></div>

      <div class="barcode" aria-hidden="true">
        <?php
          // pseudo-random but stable bar pattern
          $seed = crc32($businessName ?? 'qordy');
          for ($i = 0; $i < 40; $i++) {
              $w = (($seed >> ($i % 28)) & 3);
              $cls = [($w === 0 ? 'bar-t' : ''), ($w === 2 ? 'bar-w' : '')];
              echo '<span class="' . trim(implode(' ', $cls)) . '"></span>';
          }
        ?>
      </div>
      <div class="text-center text-[10px] tracking-[0.4em] mt-2 opacity-80">QORDY · <?php echo qd_safe($orderNo); ?></div>

      <?php if ($showActiveNums): ?>
      <div class="dash my-4"></div>
      <div>
        <div class="flex items-center justify-between mb-2">
          <div class="qd-kicker" style="color:#7a5227;"><?php echo qd_safe($dict['active_now']); ?></div>
          <div id="qdClock" class="text-sm" style="color:#7a5227;"></div>
        </div>
        <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-8 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
          <?php if (empty($active)): ?>
            <div class="qd-empty" style="color:#7a5227;"><?php echo qd_safe($dict['no_line']); ?></div>
          <?php else: foreach (array_slice($active, 0, 12) as $i => $e): ?>
            <div class="qd-num <?php echo $i === 0 ? 'qd-num-first' : ($i === 1 ? 'qd-num-second' : ''); ?>" style="<?php echo $i > 1 ? 'background:rgba(42,26,15,.08);color:#2a1a0f;border-color:rgba(42,26,15,.15);' : ''; ?>"><?php echo (int) $e['queue_number']; ?></div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($showPowered): ?>
        <div class="text-center mt-4 text-[10px] tracking-[0.3em] uppercase opacity-55">— Powered by Qordy —</div>
      <?php endif; ?>
    </div>
  </div>
</div>
