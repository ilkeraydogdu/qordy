<?php
/**
 * ELEGANT theme — Parisian fine-dining menu.
 *
 * Composition: single centered column with wide margins, ornamental
 * filigree divider, business name in oversized serif with decorative
 * initial, gold-framed QR like a picture, Roman-numeral queue cards.
 * Inspiration: Le Bernardin menus, upscale wine bar signage.
 */
$romanMap = function(int $n): string {
    $map = [1000=>'M',900=>'CM',500=>'D',400=>'CD',100=>'C',90=>'XC',50=>'L',40=>'XL',10=>'X',9=>'IX',5=>'V',4=>'IV',1=>'I'];
    $out = '';
    foreach ($map as $v => $r) { while ($n >= $v) { $out .= $r; $n -= $v; } }
    return $out ?: '—';
};
?>
<style>
  body {
    background:
      radial-gradient(ellipse at top, #fbf3e2 0%, #f0e1c1 45%, #d9c296 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    background-blend-mode: multiply;
    color: #3b2a15;
    font-feature-settings: "liga" 1, "dlig" 1, "swsh" 1;
  }
  .el-paper {
    position: relative;
    background: #fff9ed;
    border: 1px solid rgba(201,168,106,.35);
    box-shadow:
      0 40px 80px -30px rgba(80,52,20,.35),
      inset 0 0 0 8px #fff9ed,
      inset 0 0 0 9px rgba(201,168,106,.45);
    border-radius: 4px;
  }
  /* Faint paper grain */
  .el-paper::before {
    content: ''; position: absolute; inset: 0; pointer-events: none; border-radius: inherit;
    background:
      radial-gradient(circle at 30% 20%, rgba(0,0,0,.025) 0 1px, transparent 2px) 0 0/3px 3px,
      radial-gradient(circle at 70% 80%, rgba(0,0,0,.02) 0 1px, transparent 2px) 1px 1px/3px 3px;
    opacity: .8;
  }
  .serif { font-family: 'Cormorant Garamond', 'Playfair Display', Georgia, serif; }
  .sans  { font-family: 'Inter', 'Helvetica Neue', sans-serif; font-feature-settings: "ss01" 1; }
  .gold  { color: #9d7b2f; }
  .gold-line { height: 1px; background: linear-gradient(90deg, transparent, #c9a86a 15%, #c9a86a 85%, transparent); }
  .initial {
    font-family: 'Cormorant Garamond', serif;
    font-size: 120px; line-height: .85; font-style: italic; font-weight: 600;
    color: #c9a86a; float: left; margin-right: 18px; margin-top: -6px;
  }
  .qr-frame {
    position: relative; padding: 30px;
    background:
      linear-gradient(45deg, #c9a86a, #e6cf9b 50%, #c9a86a),
      #f5ebd7;
    border-radius: 8px;
    box-shadow: 0 25px 60px -20px rgba(60,40,10,.5), inset 0 0 0 3px rgba(255,255,255,.35);
  }
  .qr-frame-inner {
    background: #fff; padding: clamp(6px, 1.2vmin, 14px); border-radius: 4px;
  }
  .qr-frame { max-width: 100%; max-height: 100%; }
  .qr-frame::before, .qr-frame::after {
    content: ''; position: absolute; width: 36px; height: 36px;
    border: 2px solid #c9a86a;
  }
  .qr-frame::before { top: -10px; left: -10px; border-right: 0; border-bottom: 0; }
  .qr-frame::after  { bottom: -10px; right: -10px; border-left: 0; border-top: 0; }

  .queue-ticket {
    display: inline-flex; align-items: baseline; gap: 10px;
    padding: 8px 18px; border: 1px solid rgba(157,123,47,.35);
    background: #fff9ed;
    border-radius: 2px;
    box-shadow: 0 2px 6px rgba(60,40,10,.08);
  }
  .queue-ticket .roman { font-family: 'Cormorant Garamond', serif; font-style: italic; font-weight: 600; font-size: 28px; color: #3b2a15; letter-spacing: .04em; }
  .queue-ticket .ord   { font-family: 'Inter', sans-serif; font-size: 10px; letter-spacing: .2em; color: #9d7b2f; text-transform: uppercase; }
  .queue-ticket.first  { background: linear-gradient(135deg, #fffaeb, #fcedc4); border-color: #c9a86a; box-shadow: 0 8px 24px rgba(201,168,106,.35); transform: scale(1.06); }
  .qd-empty { padding: 32px; color: #9d7b2f; font-style: italic; font-family: 'Cormorant Garamond', serif; text-align: center; }
</style>

<div class="h-dvh max-h-dvh min-h-0 w-full box-border flex items-center justify-center p-2 sm:p-4 lg:p-6 overflow-hidden">
  <div class="el-paper relative w-full max-w-[1020px] max-h-full min-h-0 overflow-hidden flex flex-col p-4 sm:p-6 lg:p-8">

    <!-- Top header with logo + clock -->
    <div class="flex items-center justify-between mb-3 sm:mb-4 shrink-0">
      <div class="flex items-center gap-2 min-w-0">
        <?php if ($showLogo): ?>
          <?php echo qd_logo_markup($business, 48, 'ring-2 ring-[#c9a86a]'); ?>
        <?php endif; ?>
        <div class="sans text-[8px] sm:text-[10px] tracking-[0.2em] uppercase gold font-semibold truncate">Établi · <?php echo qd_safe($businessName); ?></div>
      </div>
      <div id="qdClock" class="sans text-[12px] tracking-[0.3em] gold font-semibold"></div>
    </div>

    <!-- Ornamental filigree divider -->
    <div class="flex items-center justify-center gap-3 mb-3 sm:mb-4 shrink-0">
      <div class="gold-line flex-1"></div>
      <svg width="52" height="22" viewBox="0 0 52 22" fill="none" class="shrink-0">
        <path d="M26 1 C 36 1, 44 6, 50 11 C 44 16, 36 21, 26 21 C 16 21, 8 16, 2 11 C 8 6, 16 1, 26 1 Z" stroke="#c9a86a" stroke-width="1" fill="none"/>
        <circle cx="26" cy="11" r="2.5" fill="#c9a86a"/>
        <circle cx="10" cy="11" r="1.2" fill="#c9a86a"/>
        <circle cx="42" cy="11" r="1.2" fill="#c9a86a"/>
      </svg>
      <div class="gold-line flex-1"></div>
    </div>

    <!-- Hero title -->
    <h1 class="shrink-0 serif text-center text-2xl sm:text-3xl md:text-4xl lg:text-5xl leading-[.95] tracking-tight font-semibold line-clamp-2">
      <span class="italic"><?php echo qd_safe($title); ?></span>
    </h1>
    <p class="shrink-0 serif text-center mt-1 text-sm sm:text-base lg:text-lg italic text-[#6b4e22] line-clamp-2">
      <?php echo qd_safe($subtitle); ?>
    </p>

    <!-- QR framed like a picture -->
    <div class="qd-qr-slot w-full min-h-0 min-w-0 flex-1 my-1">
      <div class="flex justify-center items-center w-full h-full min-h-0 min-w-0">
      <div class="qr-frame max-h-full min-h-0">
        <div class="qr-frame-inner">
          <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
          <div id="qdQrRefresh" class="absolute -top-2 right-2 text-[9px] font-bold px-2 py-0.5 sm:px-3 sm:py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none serif italic" style="background:#c9a86a; color:#3b2a15">
            <?php echo qd_safe($dict['qr_rotating']); ?>
          </div>
          <span id="qdCountdown" class="hidden"></span>
        </div>
      </div>
      </div>
    </div>

    <div class="shrink-0 serif text-center text-base sm:text-lg md:text-xl italic font-semibold text-[#2a1a0a] line-clamp-2 px-1">&mdash; <?php echo qd_safe($cta); ?> &mdash;</div>
    <div class="sans text-center mt-0.5 text-xs sm:text-sm tracking-wide text-[#3b2a15] font-semibold max-w-md mx-auto leading-snug line-clamp-2 px-2"><?php echo qd_safe($dict['scan_cta']); ?></div>
    <div class="mt-1 sm:mt-2 shrink-0 scale-95 sm:scale-100 origin-top"><?php require __DIR__ . '/_door_social_row.php'; ?></div>

    <!-- Roman-numeral queue tickets -->
    <?php if ($showActiveNums): ?>
    <div class="shrink-0 min-h-0 max-h-[24vh] overflow-y-hidden mt-1">
      <div class="flex items-center justify-center gap-2 mb-1">
        <div class="gold-line w-8 sm:w-16"></div>
        <div class="sans text-[8px] sm:text-[10px] tracking-[0.2em] uppercase gold font-bold"><?php echo qd_safe($dict['active_now']); ?></div>
        <div class="gold-line w-8 sm:w-16"></div>
      </div>
      <div id="qdActiveList" class="flex flex-wrap justify-center gap-1.5 sm:gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
        <?php if (empty($active)): ?>
          <div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
        <?php else: foreach (array_slice($active, 0, 10) as $i => $e): ?>
          <div class="queue-ticket <?php echo $i === 0 ? 'first' : ''; ?>">
            <span class="ord"><?php echo $i === 0 ? 'Prochain' : 'N<sup>o</sup>'; ?></span>
            <span class="roman"><?php echo $romanMap((int) $e['queue_number']); ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Footer stats + powered by -->
    <?php if ($showWaitingCount || $showEta || $showPowered): ?>
    <div class="shrink-0 mt-1 pt-2 border-t border-[rgba(157,123,47,.25)] grid grid-cols-3 gap-1 sm:gap-3 text-center">
      <div>
        <?php if ($showWaitingCount): ?>
          <div class="serif text-2xl sm:text-3xl italic font-bold leading-none"><span id="qdWaiting"><?php echo (int) $waitingCount; ?></span></div>
          <div class="sans text-[8px] sm:text-[10px] tracking-[0.2em] uppercase gold mt-0.5"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
        <?php endif; ?>
      </div>
      <div>
        <div class="serif text-sm sm:text-lg italic truncate px-0.5"><?php echo count($languages) > 0 ? strtoupper(implode(' · ', $languages)) : ''; ?></div>
        <div class="sans text-[8px] sm:text-[10px] tracking-[0.2em] uppercase gold mt-0.5">Langues</div>
      </div>
      <div>
        <?php if ($showEta): ?>
          <div class="serif text-2xl sm:text-3xl italic font-bold leading-none"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span><span class="text-sm">&#39;</span></div>
          <div class="sans text-[8px] sm:text-[10px] tracking-[0.2em] uppercase gold mt-0.5"><?php echo qd_safe($dict['eta']); ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($showPowered): ?>
      <div class="text-center py-0.5 sans text-[8px] tracking-[0.25em] uppercase text-[#9d7b2f]/60">Powered by Qordy</div>
    <?php endif; ?>
  </div>
</div>
<script>
(function(){
  function intToRoman(n) {
    n = parseInt(n, 10) || 0;
    if (n <= 0) return '—';
    var map = [['M',1000],['CM',900],['D',500],['CD',400],['C',100],['XC',90],['L',50],['XL',40],['X',10],['IX',9],['V',5],['IV',4],['I',1]];
    var out = '';
    for (var i=0; i<map.length; i++) { while (n >= map[i][1]) { out += map[i][0]; n -= map[i][1]; } }
    return out;
  }
  window.__qdRenderActive = function(list) {
    var el = document.getElementById('qdActiveList');
    if (!el) return;
    if (!list || list.length === 0) {
      el.innerHTML = '<div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>';
      return;
    }
    el.innerHTML = list.slice(0, 10).map(function(e, i) {
      var label = i === 0 ? 'Prochain' : 'N<sup>o</sup>';
      return '<div class="queue-ticket ' + (i === 0 ? 'first' : '') + '"><span class="ord">' + label + '</span><span class="roman">' + intToRoman(e.queue_number) + '</span></div>';
    }).join('');
  };
})();
</script>
