<?php
/**
 * Public queue join form — mobile-first, brand-aware, theme-color driven.
 *
 * Vars:
 *   $business, $settings, $tokenValue, $tokenValid, $tokenRow,
 *   $sessionKey, $csrf_token
 */

require_once __DIR__ . '/_helpers.php';

[$themeColor, $accentColor] = qd_accent_pair($settings);
$businessName = qd_business_name($business);

$languages    = $settings['languages'] ?? ['tr', 'en'];
$defaultLang  = $settings['default_language'] ?? 'tr';
$maxParty     = (int) ($settings['max_party_size'] ?? 12);
// Misafir formu alanları ürün kararı: telefon zorunlu, e-posta opsiyonel,
// bebek opsiyonel. Not ve erişilebilirlik alanları kaldırıldı (form uzunluğu
// dönüşümü düşürdüğü için minimal tutuldu).
$allowBaby    = true;
$requireEmail = false;

$dict = qd_dict($defaultLang);
$dictAll = [];
foreach (['tr', 'en', 'de', 'ar'] as $lng) { $dictAll[$lng] = qd_dict($lng); }
?>
<!DOCTYPE html>
<html lang="<?php echo qd_safe($defaultLang); ?>" dir="<?php echo $defaultLang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, , viewport-fit=cover">
<meta name="theme-color" content="<?php echo qd_safe($themeColor); ?>">
<title><?php echo qd_safe($businessName); ?> — <?php echo qd_safe($dict['join_title']); ?></title>
<meta name="csrf-token" content="<?php echo qd_safe($csrf_token ?? ''); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<style>
  :root { --theme: <?php echo qd_safe($themeColor); ?>; --accent: <?php echo qd_safe($accentColor); ?>; }
  html, body { -webkit-tap-highlight-color: transparent; }
  body {
    font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    color: #0f172a;
    background:
      radial-gradient(120% 60% at 50% 0%, color-mix(in srgb, var(--accent) 12%, transparent) 0%, transparent 55%),
      linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    min-height: 100dvh;
  }
  .qd-hero {
    background: linear-gradient(135deg, var(--theme) 0%, color-mix(in srgb, var(--theme) 70%, #111827) 100%);
    color: #ffffff;
  }
  .qd-logo {
    display:inline-flex; align-items:center; justify-content:center; overflow:hidden; border-radius: 9999px;
    background: #fff; flex-shrink: 0; box-sizing: border-box; line-height: 1;
  }
  .qd-logo:has(> img) { padding: 4px; }
  .qd-logo img { width:100%; height:100%; object-fit: contain; object-position: center; display: block; }
  .qd-logo-initials { font-weight: 800; color: var(--theme); }
  .qd-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; box-shadow: 0 10px 30px -15px rgba(15,23,42,.15); }
  .qd-label { font-size: 13px; font-weight: 600; color: #475569; letter-spacing: .01em; }
  .qd-input {
    width: 100%; padding: 14px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0;
    font-size: 16px; color: #0f172a; outline: none; transition: border-color .15s, box-shadow .15s, background .15s;
  }
  .qd-input:focus { background: #fff; border-color: var(--accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent); }
  .qd-input[readonly] { background: #f1f5f9; }
  /* Phone field with country code prefix */
  .qd-phone { display:flex; align-items:stretch; background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; transition: border-color .15s, box-shadow .15s, background .15s; }
  .qd-phone:focus-within { background:#fff; border-color: var(--accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 22%, transparent); }
  .qd-phone-cc { position: relative; display:flex; align-items:center; gap:6px; padding: 0 12px; border-right: 1px solid #e2e8f0; font-weight: 700; color:#0f172a; font-size: 15px; cursor: pointer; user-select: none; }
  .qd-phone-cc .qd-flag { font-size: 20px; line-height: 1; }
  .qd-phone-cc .qd-caret { width: 10px; height: 10px; color:#94a3b8; }
  .qd-phone input { flex:1; border:0; background: transparent; padding: 14px 16px; font-size: 16px; color: #0f172a; outline: none; border-radius: 14px; }
  .qd-phone input::placeholder { color:#94a3b8; letter-spacing: .02em; }
  .qd-cc-menu { position: absolute; top: calc(100% + 6px); left: 0; min-width: 240px; max-height: 280px; overflow: auto;
    background:#fff; border:1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 18px 45px -20px rgba(15,23,42,.35); z-index: 50; padding: 6px; }
  .qd-cc-menu[hidden] { display: none; }
  .qd-cc-item { display:flex; align-items:center; gap:10px; padding: 9px 10px; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; color:#0f172a; }
  .qd-cc-item:hover, .qd-cc-item[aria-selected=true] { background: color-mix(in srgb, var(--accent) 12%, transparent); }
  .qd-cc-item .qd-cc-dial { color:#64748b; font-weight: 600; font-variant-numeric: tabular-nums; margin-left: auto; }
  .qd-chip {
    padding: 10px 14px; border-radius: 999px; border: 1px solid #e2e8f0; background: #fff;
    color: #0f172a; font-weight: 700; font-size: 14px; cursor: pointer; transition: .15s; min-width: 46px;
  }
  .qd-chip:hover { border-color: color-mix(in srgb, var(--accent) 50%, #e2e8f0); }
  .qd-chip.active { background: var(--accent); color: #0b0b0b; border-color: var(--accent); box-shadow: 0 6px 18px -8px color-mix(in srgb, var(--accent) 60%, transparent); }
  .qd-btn {
    width: 100%; padding: 16px 20px; border-radius: 16px;
    background: var(--accent); color: #0b0b0b; font-weight: 800; font-size: 17px; letter-spacing: .01em;
    transition: transform .1s, filter .15s; box-shadow: 0 12px 32px -14px color-mix(in srgb, var(--accent) 70%, transparent);
  }
  .qd-btn:hover { filter: brightness(1.05); }
  .qd-btn:active { transform: translateY(1px); }
  .qd-btn:disabled { opacity: .55; }
  .qd-check { display:flex; align-items:center; gap:10px; padding: 14px; border-radius: 14px; border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-weight: 600; font-size: 14px; color: #334155; }
  .qd-check input { accent-color: var(--accent); width: 18px; height: 18px; }
  .qd-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 14px; padding: 12px 14px; font-size: 14px; font-weight: 600; }
  [dir=rtl] .qd-input, [dir=rtl] .qd-label { text-align: right; }
</style>
</head>
<body>

<div class="qd-hero px-4 sm:px-5 pt-6 sm:pt-8 pb-24 relative overflow-hidden">
  <div class="max-w-md mx-auto flex items-center justify-between relative gap-3 min-w-0">
    <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
      <div class="shrink-0"><?php echo qd_logo_markup($business, 44); ?></div>
      <div class="min-w-0 flex-1">
        <div class="text-white/60 text-[10px] sm:text-[11px] uppercase tracking-widest"><?php echo qd_safe($dict['welcome']); ?></div>
        <div class="text-[15px] sm:text-base font-extrabold leading-tight line-clamp-2" style="word-break:break-word"><?php echo qd_safe($businessName); ?></div>
      </div>
    </div>
    <select id="langSelect" class="shrink-0 bg-white/10 border border-white/20 rounded-full px-2.5 py-1.5 text-xs font-semibold text-white" aria-label="Language">
      <?php foreach ($languages as $l): ?>
        <option value="<?php echo qd_safe($l); ?>" <?php echo $l === $defaultLang ? 'selected' : ''; ?> style="color:#0f172a"><?php echo strtoupper(qd_safe($l)); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="max-w-md mx-auto px-5 -mt-16 pb-20 relative z-10">
  <div class="qd-card p-6 lg:p-7">

    <?php if (!$tokenValid): ?>
      <div class="py-10 text-center">
        <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center mb-4" style="background: color-mix(in srgb, var(--accent) 15%, transparent);">
          <svg class="w-7 h-7" style="color: var(--accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 5a2 2 0 00-3.46 0L3.2 16a2 2 0 001.73 3z"/></svg>
        </div>
        <div class="text-xl font-extrabold text-slate-900 mb-2" data-i18n="invalid_tok"><?php echo qd_safe($dict['invalid_tok']); ?></div>
        <div class="text-slate-500 text-sm" data-i18n="refresh_tok"><?php echo qd_safe($dict['refresh_tok']); ?></div>
      </div>
    <?php elseif (empty($settings['is_accepting_queue'])): ?>
      <div class="py-10 text-center">
        <div class="mx-auto w-14 h-14 rounded-full flex items-center justify-center mb-4" style="background: color-mix(in srgb, var(--accent) 12%, transparent);">
          <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
        </div>
        <div class="text-lg font-extrabold text-slate-900 mb-2" data-i18n="queue_not_accepting"><?php echo qd_safe($dict['queue_not_accepting'] ?? 'Queue not open'); ?></div>
        <p class="text-slate-500 text-sm leading-relaxed" data-i18n="qr_refresh"><?php echo qd_safe($dict['qr_refresh'] ?? ''); ?></p>
      </div>
    <?php else: ?>

      <div class="text-2xl font-extrabold text-slate-900" data-i18n="join_title"><?php echo qd_safe($dict['join_title']); ?></div>
      <div class="mt-1.5 text-slate-500 text-[14px] leading-relaxed" data-i18n="join_sub"><?php echo qd_safe($dict['join_sub']); ?></div>

      <form id="qdForm" class="mt-6 space-y-4" autocomplete="on" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo qd_safe($csrf_token ?? ''); ?>">
        <input type="hidden" name="token" value="<?php echo qd_safe($tokenValue); ?>">
        <input type="hidden" name="language" id="langInput" value="<?php echo qd_safe($defaultLang); ?>">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="qd-label" data-i18n="name"><?php echo qd_safe($dict['name']); ?></label>
            <input type="text" name="name" required maxlength="80" class="qd-input mt-1.5">
          </div>
          <div>
            <label class="qd-label" data-i18n="surname"><?php echo qd_safe($dict['surname']); ?></label>
            <input type="text" name="surname" maxlength="80" class="qd-input mt-1.5">
          </div>
        </div>

        <div>
          <label class="qd-label" data-i18n="phone"><?php echo qd_safe($dict['phone']); ?></label>
          <div class="qd-phone mt-1.5" id="phoneWrap">
            <div class="qd-phone-cc" id="ccToggle" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
              <span class="qd-flag" id="ccFlag" aria-hidden="true">&#127481;&#127479;</span>
              <span id="ccDial">+90</span>
              <svg class="qd-caret" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clip-rule="evenodd"/></svg>
              <div class="qd-cc-menu" id="ccMenu" role="listbox" hidden></div>
            </div>
            <input type="tel" name="phone" id="phoneInput" required inputmode="tel" maxlength="25" autocomplete="tel-national" placeholder="5XX XXX XX XX" aria-label="Phone number">
            <input type="hidden" name="phone_country" id="phoneCountry" value="+90">
          </div>
        </div>

        <div>
          <label class="qd-label" data-i18n="email">
            <?php echo qd_safe($dict['email']); ?><?php if ($requireEmail): ?> *<?php endif; ?>
          </label>
          <input type="email" name="email" <?php echo $requireEmail ? 'required' : ''; ?> maxlength="120" class="qd-input mt-1.5">
        </div>

        <div>
          <label class="qd-label block mb-2" data-i18n="party"><?php echo qd_safe($dict['party']); ?></label>
          <div class="flex flex-wrap gap-2" id="partyChips">
            <?php for ($i = 1; $i <= min(10, $maxParty); $i++): ?>
              <button type="button" data-val="<?php echo $i; ?>" class="qd-chip"><?php echo $i; ?></button>
            <?php endfor; ?>
            <?php if ($maxParty > 10): ?>
              <input type="number" name="party_size_manual" min="1" max="<?php echo $maxParty; ?>" class="qd-input" style="width:96px" placeholder="10+">
            <?php endif; ?>
          </div>
          <input type="hidden" name="party_size" id="partySize" value="2">
        </div>

        <div>
          <label class="qd-check">
            <input type="checkbox" name="has_baby" value="1">
            <span data-i18n="baby"><?php echo qd_safe($dict['baby']); ?></span>
          </label>
        </div>

        <label class="flex items-start gap-2 text-[12px] text-slate-500 leading-relaxed">
          <input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5" style="accent-color: var(--accent)">
          <span data-i18n="consent"><?php echo qd_safe($dict['consent']); ?></span>
        </label>

        <button type="submit" class="qd-btn mt-2">
          <span data-i18n="submit"><?php echo qd_safe($dict['submit']); ?></span>
        </button>

        <div id="formError" class="qd-error hidden"></div>
      </form>

      <p class="text-[11px] text-slate-400 text-center mt-5" data-i18n="kvkk"><?php echo qd_safe($dict['kvkk']); ?></p>

    <?php endif; ?>
  </div>

  <div class="text-center mt-8 text-[11px] text-slate-400 tracking-widest uppercase">
    Powered by Qordy
  </div>
</div>

<script>
(function(){
  const DICT = <?php echo json_encode($dictAll, JSON_UNESCAPED_UNICODE); ?>;
  const langSelect = document.getElementById('langSelect');
  const langInput  = document.getElementById('langInput');

  function applyLang(lang){
    if (!DICT[lang]) return;
    document.documentElement.lang = lang;
    document.documentElement.dir = (lang === 'ar') ? 'rtl' : 'ltr';
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const k = el.getAttribute('data-i18n');
      if (DICT[lang][k]) el.textContent = DICT[lang][k];
    });
    if (langInput) langInput.value = lang;
  }
  if (langSelect) langSelect.addEventListener('change', e => applyLang(e.target.value));

  const chips = document.querySelectorAll('#partyChips .qd-chip');
  const partyInput = document.getElementById('partySize');
  function selectChip(btn){
    chips.forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    if (partyInput) partyInput.value = btn.dataset.val;
  }
  chips.forEach(c => c.addEventListener('click', () => selectChip(c)));
  if (chips.length >= 2) selectChip(chips[1]);

  const manual = document.querySelector('[name="party_size_manual"]');
  if (manual) manual.addEventListener('input', () => {
    chips.forEach(c => c.classList.remove('active'));
    if (partyInput) partyInput.value = manual.value || 1;
  });

  // --- Phone country picker + live formatter --------------------------
  const COUNTRIES = [
    { code:'TR', dial:'+90',  flag:'\uD83C\uDDF9\uD83C\uDDF7', name:'Türkiye',        pattern:[3,3,2,2] },
    { code:'DE', dial:'+49',  flag:'\uD83C\uDDE9\uD83C\uDDEA', name:'Deutschland',    pattern:[3,4,4] },
    { code:'GB', dial:'+44',  flag:'\uD83C\uDDEC\uD83C\uDDE7', name:'United Kingdom', pattern:[4,3,3] },
    { code:'US', dial:'+1',   flag:'\uD83C\uDDFA\uD83C\uDDF8', name:'United States',  pattern:[3,3,4] },
    { code:'FR', dial:'+33',  flag:'\uD83C\uDDEB\uD83C\uDDF7', name:'France',         pattern:[1,2,2,2,2] },
    { code:'NL', dial:'+31',  flag:'\uD83C\uDDF3\uD83C\uDDF1', name:'Nederland',      pattern:[3,3,3] },
    { code:'RU', dial:'+7',   flag:'\uD83C\uDDF7\uD83C\uDDFA', name:'Россия',         pattern:[3,3,2,2] },
    { code:'AE', dial:'+971', flag:'\uD83C\uDDE6\uD83C\uDDEA', name:'الإمارات',        pattern:[2,3,4] },
    { code:'SA', dial:'+966', flag:'\uD83C\uDDF8\uD83C\uDDE6', name:'السعودية',        pattern:[2,3,4] },
    { code:'IT', dial:'+39',  flag:'\uD83C\uDDEE\uD83C\uDDF9', name:'Italia',         pattern:[3,3,4] },
    { code:'ES', dial:'+34',  flag:'\uD83C\uDDEA\uD83C\uDDF8', name:'España',         pattern:[3,3,3] },
    { code:'AZ', dial:'+994', flag:'\uD83C\uDDE6\uD83C\uDDFF', name:'Azərbaycan',      pattern:[2,3,2,2] },
    { code:'IR', dial:'+98',  flag:'\uD83C\uDDEE\uD83C\uDDF7', name:'ایران',           pattern:[3,3,4] },
    { code:'GR', dial:'+30',  flag:'\uD83C\uDDEC\uD83C\uDDF7', name:'Ελλάδα',         pattern:[3,3,4] },
    { code:'BG', dial:'+359', flag:'\uD83C\uDDE7\uD83C\uDDEC', name:'България',       pattern:[2,3,4] },
    { code:'UA', dial:'+380', flag:'\uD83C\uDDFA\uD83C\uDDE6', name:'Україна',        pattern:[2,3,2,2] },
  ];
  const ccToggle = document.getElementById('ccToggle');
  const ccMenu   = document.getElementById('ccMenu');
  const ccFlag   = document.getElementById('ccFlag');
  const ccDial   = document.getElementById('ccDial');
  const ccHidden = document.getElementById('phoneCountry');
  const phoneInput = document.getElementById('phoneInput');
  let currentCC = COUNTRIES[0];

  function renderCcMenu(){
    ccMenu.innerHTML = COUNTRIES.map(c =>
      '<div class="qd-cc-item" role="option" data-dial="'+c.dial+'" data-code="'+c.code+'" aria-selected="'+(c.code===currentCC.code)+'">'+
        '<span class="qd-flag">'+c.flag+'</span>'+
        '<span>'+c.name+'</span>'+
        '<span class="qd-cc-dial">'+c.dial+'</span>'+
      '</div>'
    ).join('');
  }
  function openMenu(open){ ccMenu.hidden = !open; ccToggle.setAttribute('aria-expanded', open ? 'true':'false'); }
  function selectCountry(code){
    const c = COUNTRIES.find(x => x.code === code) || COUNTRIES[0];
    currentCC = c;
    ccFlag.textContent = c.flag;
    ccDial.textContent = c.dial;
    ccHidden.value = c.dial;
    renderCcMenu();
    formatPhone();
  }

  ccToggle.addEventListener('click', () => openMenu(ccMenu.hidden));
  ccToggle.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openMenu(ccMenu.hidden); }});
  ccMenu.addEventListener('click', (e) => {
    const item = e.target.closest('.qd-cc-item');
    if (!item) return;
    selectCountry(item.dataset.code);
    openMenu(false);
    phoneInput.focus();
  });
  document.addEventListener('click', (e) => {
    if (!ccMenu.hidden && !ccToggle.contains(e.target)) openMenu(false);
  });

  function groupDigits(digits, pattern){
    if (!pattern || !pattern.length) return digits;
    let out = '', i = 0;
    for (const size of pattern){
      if (i >= digits.length) break;
      if (out) out += ' ';
      out += digits.substr(i, size);
      i += size;
    }
    if (i < digits.length) out += (out ? ' ' : '') + digits.substr(i);
    return out;
  }

  function formatPhone(){
    let raw = (phoneInput.value || '').replace(/\D+/g, '');
    // If the user typed the dial code or a leading 0, strip it (only at the very beginning)
    const ccDigits = currentCC.dial.replace(/\D+/g,'');
    if (raw.startsWith(ccDigits)) raw = raw.slice(ccDigits.length);
    if (currentCC.code === 'TR' && raw.startsWith('0')) raw = raw.replace(/^0+/, '');
    // Cap to reasonable national length (most countries ≤ 12)
    raw = raw.slice(0, 14);
    phoneInput.value = groupDigits(raw, currentCC.pattern);
  }
  phoneInput.addEventListener('input', formatPhone);
  phoneInput.addEventListener('paste', () => setTimeout(formatPhone, 0));
  selectCountry('TR');

  // --- Form submit ----------------------------------------------------
  const form = document.getElementById('qdForm');
  if (!form) return;
  const errBox = document.getElementById('formError');

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    errBox.classList.add('hidden');
    const btn = form.querySelector('button[type=submit]');

    // Client-side phone sanity check – must be at least 7 national digits.
    const natDigits = (phoneInput.value || '').replace(/\D+/g, '');
    if (natDigits.length < 7) {
      const d = DICT[langInput.value] || DICT.en;
      errBox.textContent = d.invalid_phone || 'Please enter a valid phone number.';
      errBox.classList.remove('hidden');
      phoneInput.focus();
      return;
    }

    btn.disabled = true; btn.style.opacity = .6;
    const fd = new FormData(form);
    try {
      const res = await fetch('/sira/kayit', {
        method: 'POST', body: fd,
        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (!json.success) {
        const lng = langInput.value || 'tr';
        const d = DICT[lng] || DICT.en;
        let msg;
        switch (json.error) {
          case 'recently_used':            msg = d.recent_err; break;
          case 'invalid_or_expired_token': msg = d.invalid_tok; break;
          case 'invalid_phone':            msg = d.invalid_phone || d.net_err; break;
          case 'missing_fields':           msg = d.missing_fields || d.net_err; break;
          case 'party_too_large':          msg = (d.party_too_large || 'Party size too large') + (json.max ? ' ('+json.max+')' : ''); break;
          case 'queue_disabled':           msg = d.queue_disabled || d.net_err; break;
          case 'queue_not_accepting':      msg = d.queue_not_accepting || d.net_err; break;
          default:                         msg = json.error || d.net_err;
        }
        errBox.textContent = msg;
        errBox.classList.remove('hidden');
        btn.disabled = false; btn.style.opacity = 1;
        return;
      }
      if (json.redirect) window.location.href = json.redirect;
    } catch (e) {
      const d = DICT[langInput.value] || DICT.en;
      errBox.textContent = d.net_err;
      errBox.classList.remove('hidden');
      btn.disabled = false; btn.style.opacity = 1;
    }
  });
})();
</script>
</body>
</html>
