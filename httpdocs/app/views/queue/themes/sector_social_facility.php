<?php
/**
 * SECTOR_SOCIAL_FACILITY — "Günün menüsü / lokanta-yemekhane" kompozisyonu.
 *
 * Kurumsal temiz bir split-screen: solda "Günün Menüsü" büyük dijital pano
 * (çorba / ana yemek / yan / tatlı / salata + fiyat), sağda QR ve sıra
 * istatistikleri. Alt şeritte kampüs/tesis saati & kalori bilgisi bandı.
 */
?>
<style>
  body.qd-door {
    background:
      linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color:#0f172a;
    background-blend-mode: multiply;
  }
  .cafeteria-tile { position: fixed; inset: 0; pointer-events:none; z-index:0; opacity:.45;
    background:
      linear-gradient(90deg, rgba(148,163,184,.25) 1px, transparent 1px) 0 0/60px 60px,
      linear-gradient(0deg, rgba(148,163,184,.25) 1px, transparent 1px) 0 0/60px 60px;
  }
  .menu-board { background: #0f172a; color:#f8fafc; border-radius: 16px; padding: clamp(18px,3vh,30px); box-shadow: 0 40px 80px -20px rgba(0,0,0,.4); border: 3px solid #0891b2; }
  .menu-board-head { background: #f59e0b; color:#0f172a; padding: 8px 14px; border-radius: 6px; font-weight:900; letter-spacing:.3em; text-transform:uppercase; display:inline-block; }
  .menu-row { display:flex; justify-content:space-between; align-items:baseline; gap: 12px; padding: 8px 0; border-bottom: 1px dashed rgba(248,250,252,.2); font-size: clamp(14px,1.8vw,18px); }
  .menu-row .name { font-weight:700; }
  .menu-row .price { font-weight:900; color:#f59e0b; font-family: ui-monospace, Menlo, monospace; }
  .menu-row .dot { flex: 1; border-bottom: 2px dotted rgba(248,250,252,.15); margin: 0 4px; transform: translateY(-6px);}

  .tray { width: 56px; height: 40px; border-radius: 6px; background: linear-gradient(180deg, #cbd5e1, #94a3b8); border: 2px solid #475569; box-shadow: 0 4px 10px rgba(0,0,0,.2); position: relative; }
  .tray::before { content:''; position:absolute; top: 6px; left: 6px; width: 14px; height: 14px; border-radius:50%; background:#fbbf24; }
  .tray::after { content:''; position:absolute; top: 6px; right: 6px; width: 14px; height: 14px; border-radius:50%; background:#ef4444; }
  .tray-queue { display:flex; gap: 4px; flex-wrap:wrap; }

  .qd-num{ aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; font-weight:900; font-size: clamp(1rem,2vw,1.6rem); border-radius:8px; background:#fff; color:#0f172a; border:2px solid #0891b2; font-family: ui-monospace, Menlo, monospace; }
  .qd-num-first{ background:#f59e0b; color:#0f172a; border-color:#0f172a; }
  .qd-num-second{ background:#0891b2; color:#fff; border-color:#0f172a; }
  .qd-empty{ grid-column:1/-1; text-align:center; color:#64748b; font-size:11px; padding:8px; }
  .qd-kicker { color:#0891b2; text-transform:uppercase; letter-spacing:.28em; font-size:11px; font-weight:900; font-family: ui-monospace, Menlo, monospace; }
  .strip { background:#0891b2; color:#fff; padding: 6px 14px; font-family: ui-monospace, Menlo, monospace; font-size: 12px; letter-spacing:.15em; text-transform: uppercase; border-radius: 8px;}
</style>

<div class="cafeteria-tile"></div>

<?php
$doorShowMetrics = !empty($doorShowMetrics);
$menu = [
    ['name' => 'Mercimek çorbası',       'price' => '45 ₺',  'kcal' => '220 kcal'],
    ['name' => 'Et sote',                'price' => '120 ₺', 'kcal' => '540 kcal'],
    ['name' => 'Pirinç pilavı',          'price' => '35 ₺',  'kcal' => '280 kcal'],
    ['name' => 'Mevsim salatası',        'price' => '35 ₺',  'kcal' => '95 kcal'],
    ['name' => 'Sütlaç',                 'price' => '40 ₺',  'kcal' => '310 kcal'],
    ['name' => 'Ayran',                  'price' => '18 ₺',  'kcal' => '85 kcal'],
];
?>
<div class="relative box-border h-dvh max-h-dvh w-full flex flex-col p-4 sm:p-8 lg:p-10 overflow-hidden">
  <div class="flex items-center justify-between mb-3">
    <div class="flex items-center gap-3">
      <?php if ($showLogo): ?><?php echo qd_logo_markup($business, 44, 'ring-2 ring-cyan-600/40'); ?><?php endif; ?>
      <div>
        <div class="text-[10px] tracking-[0.4em] uppercase text-slate-500">sosyal tesis · yemekhane · lokanta</div>
        <div class="text-xl sm:text-2xl font-black"><?php echo qd_safe($businessName); ?></div>
      </div>
    </div>
    <div id="qdClock" class="strip"></div>
  </div>

  <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] gap-4">
    <div class="menu-board flex flex-col min-h-0">
      <div class="flex items-center justify-between">
        <div class="menu-board-head">Günün Menüsü</div>
        <div class="text-xs tracking-[0.3em] uppercase text-slate-400"><?php echo date('d.m.Y'); ?></div>
      </div>
      <div class="mt-3 flex-1 min-h-0 overflow-hidden">
        <?php foreach ($menu as $m): ?>
          <div class="menu-row">
            <span class="name">· <?php echo qd_safe($m['name']); ?></span>
            <span class="dot"></span>
            <span class="price"><?php echo qd_safe($m['price']); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-3 text-xs opacity-80" style="color:#f59e0b;">★ Tüm tabaklar kampüs dağıtım mutfağında günlük hazırlanır. ★</div>
    </div>

    <div class="flex flex-col gap-3 min-h-0">
      <div class="rounded-2xl p-4 bg-white shadow-md border-2 border-slate-200 text-center">
        <div class="text-xl sm:text-2xl font-black"><?php echo qd_safe($title); ?></div>
        <div class="text-xs opacity-75 mb-2"><?php echo qd_safe($subtitle); ?></div>
        <div class="qd-qr-slot flex items-center justify-center">
          <div class="relative bg-white p-2 rounded-md shadow-inner max-w-[min(70%,240px)] ring-4 ring-cyan-600">
            <img id="qdQr" src="<?php echo qd_safe($qrImg); ?>" alt="QR" class="qd-qr-img"/>
            <div id="qdQrRefresh" class="absolute -top-2 -right-2 bg-amber-500 text-white text-[9px] font-black px-2 py-1 rounded-full shadow-sm opacity-0 transition-opacity duration-500 pointer-events-none"><?php echo qd_safe($dict['qr_rotating']); ?></div>
            <span id="qdCountdown" class="hidden"></span>
          </div>
        </div>
        <div class="mt-2 font-black text-sm sm:text-base"><?php echo qd_safe($cta); ?></div>
      </div>

      <?php if ($doorShowMetrics && ($showWaitingCount || $showEta)): ?>
      <div class="grid <?php echo ($showWaitingCount && $showEta) ? 'grid-cols-2' : 'grid-cols-1'; ?> gap-3">
        <?php if ($showWaitingCount): ?>
        <div class="rounded-2xl p-3 text-center bg-cyan-600 text-white shadow-md">
          <div class="qd-kicker" style="color:rgba(255,255,255,.85);"><?php echo qd_safe($dict['waiting_lbl']); ?></div>
          <div id="qdWaiting" class="text-4xl font-black"><?php echo (int) $waitingCount; ?></div>
        </div>
        <?php endif; ?>
        <?php if ($showEta): ?>
        <div class="rounded-2xl p-3 text-center bg-amber-500 text-slate-900 shadow-md">
          <div class="qd-kicker" style="color:#0f172a;"><?php echo qd_safe($dict['eta']); ?></div>
          <div class="text-4xl font-black"><span id="qdEta"><?php echo (int) $estimatedWait; ?></span><span class="text-sm font-bold ml-1"><?php echo qd_safe($dict['minutes']); ?></span></div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="rounded-2xl p-3 bg-white shadow-md border-2 border-slate-200">
        <div class="qd-kicker mb-2">Tepsi sırası</div>
        <div class="tray-queue">
          <?php for ($i=0;$i<10;$i++): ?><div class="tray"></div><?php endfor; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($showActiveNums): ?>
  <div class="mt-3">
    <div class="flex items-center justify-between mb-2">
      <div class="qd-kicker"><?php echo qd_safe($dict['active_now']); ?></div>
      <div class="strip"><?php echo qd_safe($dict['groups']); ?></div>
    </div>
    <div id="qdActiveList" class="grid grid-cols-6 sm:grid-cols-10 gap-2" data-empty="<?php echo qd_safe($dict['no_line']); ?>">
      <?php if (empty($active)): ?><div class="qd-empty"><?php echo qd_safe($dict['no_line']); ?></div>
      <?php else: foreach (array_slice($active,0,14) as $i=>$e): ?>
        <div class="qd-num <?php echo $i===0?'qd-num-first':($i===1?'qd-num-second':''); ?>"><?php echo (int)$e['queue_number']; ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showPowered): ?><div class="text-center mt-3 text-[10px] tracking-[0.3em] uppercase text-slate-500">Powered by Qordy</div><?php endif; ?>
</div>
