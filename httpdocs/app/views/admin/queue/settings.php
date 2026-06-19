<?php
/**
 * Queue settings admin page.
 * Vars: $settings
 */
require_once __DIR__ . '/../../../support/QueueThemeRegistry.php';
require_once __DIR__ . '/../../queue/_helpers.php';

if (!isset($settings) || !is_array($settings)) $settings = [];

$languages = $settings['languages'] ?? ['tr', 'en'];
$defaultLang = $settings['default_language'] ?? 'tr';
$allKnown = ['tr', 'en', 'de', 'ar', 'fr', 'es', 'ru'];
$titleMap = is_array($settings['display_title'] ?? null) ? $settings['display_title'] : [];
$subtitleMap = is_array($settings['display_subtitle'] ?? null) ? $settings['display_subtitle'] : [];
$ctaMap = is_array($settings['display_call_to_action'] ?? null) ? $settings['display_call_to_action'] : [];

$themes = \App\Support\QueueThemeRegistry::all();
$currentTheme = $settings['display_theme'] ?? \App\Support\QueueThemeRegistry::DEFAULT;
if (!isset($themes[$currentTheme])) $currentTheme = \App\Support\QueueThemeRegistry::DEFAULT;
?>

<div class="q-page q-biz-theme">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">SIRA</p>
        <h1 class="q-page-header__title">Sıra Ayarları</h1>
        <p class="q-page-header__subtitle">QR sıra sisteminin davranışını ve kapı ekranını özelleştirin.</p>
      </div>
      <div class="q-page-header__actions">
        <a href="/business/queue" class="q-btn q-btn--secondary">← Panele dön</a>
      </div>
    </header>

  <form method="POST" action="/business/queue/settings" class="q-stack q-stack--lg">
    <input type="hidden" name="csrf_token" value="<?php echo isset($csrf_token) ? htmlspecialchars($csrf_token, ENT_QUOTES) : ''; ?>">

    <!-- General -->
    <section class="q-card q-card--pad q-stack">
      <h2 class="q-card__title">Genel</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="q-toolbar q-card q-card--pad flex items-center gap-3" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="is_enabled" value="1" <?php echo !empty($settings['is_enabled']) ? 'checked' : ''; ?>>
          <span class="font-semibold">Sıra sistemi aktif</span>
        </label>
        <label class="q-toolbar q-card q-card--pad flex flex-col gap-1 md:col-span-2" style="background:var(--color-surface-muted);">
          <span class="flex items-center gap-3">
            <input type="checkbox" name="auto_queue_from_tables" value="1" <?php echo !empty($settings['auto_queue_from_tables']) ? 'checked' : ''; ?>>
            <span class="font-semibold">Masa kartları: tam doluyken otomatik sıra modu</span>
          </span>
          <span class="text-sm pl-9" style="color:var(--color-text-muted);">Açıkken: tüm masalar dolu (hiç &quot;boş&quot; yok) iken kapı ekranı sıra/QR moduna geçer. Boş masa varken yukarıdaki &quot;Sıra modu&quot; anahtarını (işletme paneli) kullanın.</span>
        </label>
        <div class="q-field">
          <label class="q-field__label">Ortalama bekleme süresi (dk)</label>
          <input type="number" min="1" max="120" name="average_wait_minutes" value="<?php echo (int) ($settings['average_wait_minutes'] ?? 15); ?>" class="q-input">
        </div>
        <div class="q-field">
          <label class="q-field__label">Maksimum grup (kişi sayısı)</label>
          <input type="number" min="1" max="50" name="max_party_size" value="<?php echo (int) ($settings['max_party_size'] ?? 12); ?>" class="q-input">
        </div>
        <div class="q-field">
          <label class="q-field__label">QR token ömrü (sn)</label>
          <input type="number" min="15" max="3600" name="qr_token_ttl_seconds" value="<?php echo (int) ($settings['qr_token_ttl_seconds'] ?? 90); ?>" class="q-input">
          <p class="text-xs mt-1" style="color:var(--color-text-muted);">Düşük değer = daha güvenli. Tavsiye: 60-120 sn.</p>
        </div>
        <div class="q-field">
          <label class="q-field__label">Oto "Gelmedi" süresi (dk)</label>
          <input type="number" min="0" max="120" name="auto_no_show_minutes" value="<?php echo (int) ($settings['auto_no_show_minutes'] ?? 5); ?>" class="q-input">
          <p class="text-xs mt-1" style="color:var(--color-text-muted);">Çağrıldıktan sonra bu süre içinde gelmezse otomatik NO_SHOW yapılır (0 = kapalı).</p>
        </div>
        <div class="q-field">
          <label class="q-field__label">Aynı telefon soğuma süresi (dk)</label>
          <input type="number" min="0" max="1440" name="entry_cooldown_minutes" value="<?php echo (int) ($settings['entry_cooldown_minutes'] ?? 90); ?>" class="q-input">
        </div>
      </div>
    </section>

    <!-- Form requirements -->
    <section class="q-card q-card--pad q-stack">
      <h2 class="q-card__title">Form alanları</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="require_email" value="1" <?php echo !empty($settings['require_email']) ? 'checked' : ''; ?>>
          E-posta zorunlu
        </label>
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="require_note" value="1" <?php echo !empty($settings['require_note']) ? 'checked' : ''; ?>>
          Not zorunlu
        </label>
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="allow_baby" value="1" <?php echo !empty($settings['allow_baby']) ? 'checked' : ''; ?>>
          Bebek seçeneği
        </label>
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="allow_accessibility" value="1" <?php echo !empty($settings['allow_accessibility']) ? 'checked' : ''; ?>>
          Erişilebilirlik
        </label>
      </div>
    </section>

    <!-- Door display design -->
    <section class="q-card q-card--pad q-stack">
      <h2 class="q-card__title">Kapı Ekranı Tasarımı</h2>
      <p class="text-sm" style="color:var(--color-text-muted);">Markana en çok yakışanı seç. İstediğin zaman değiştirebilirsin.</p>

      <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($themes as $t):
          $sw = $t['swatches'] ?? ['#0f172a','#f97316','#fff'];
          $isActive = ($currentTheme === $t['key']);
        ?>
          <label class="relative block cursor-pointer group">
            <input type="radio" name="display_theme" value="<?php echo htmlspecialchars($t['key'], ENT_QUOTES); ?>" <?php echo $isActive ? 'checked' : ''; ?> class="peer sr-only">
            <div class="q-card overflow-hidden transition peer-checked:ring-2 peer-checked:ring-[var(--color-brand-accent)]">
              <div class="h-32 flex items-center justify-center relative" style="background:linear-gradient(135deg,<?php echo htmlspecialchars($sw[0], ENT_QUOTES); ?> 0%,<?php echo htmlspecialchars($sw[0], ENT_QUOTES); ?> 70%,<?php echo htmlspecialchars($sw[1], ENT_QUOTES); ?> 100%)">
                <div class="absolute inset-2 rounded-xl border border-white/10"></div>
                <div class="absolute bottom-2 left-2 right-2 flex items-center gap-2">
                  <span class="w-5 h-5 rounded-full" style="background:<?php echo htmlspecialchars($sw[0], ENT_QUOTES); ?>;border:1px solid rgba(255,255,255,.3)"></span>
                  <span class="w-5 h-5 rounded-full" style="background:<?php echo htmlspecialchars($sw[1], ENT_QUOTES); ?>;border:1px solid rgba(255,255,255,.3)"></span>
                  <span class="w-5 h-5 rounded-full" style="background:<?php echo htmlspecialchars($sw[2], ENT_QUOTES); ?>;border:1px solid rgba(255,255,255,.3)"></span>
                  <?php if ($isActive): ?>
                    <span class="ml-auto q-badge q-badge--live">Seçili</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="p-4">
                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($t['name_tr'], ENT_QUOTES); ?></div>
                <div class="text-xs text-slate-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($t['description_tr'], ENT_QUOTES); ?></div>
              </div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <p class="text-xs text-slate-500 mt-4 leading-relaxed">
        <strong>Kapı ekranı</strong> adresi <code class="text-[11px] bg-slate-100 px-1 rounded">/sira</code> — işletme girişindeki ekran; logo, QR, metinler ve (isteğe bağlı) burada tanımladığınız sosyal linkler burada görünür.
        Formu dolduran misafirin kişisel bilet ekranı <code class="text-[11px] bg-slate-100 px-1 rounded">/sira/bilet/…</code> ile ayrıdır.
        Bekleyen sayısı, tahmini süre ve anlık sıra numaraları TV’de <strong>varsayılan kapalıdır</strong> (ekranda yalnızca marka + QR + metinler); personel aynı bilgiyi görmek isterse aşağıdakileri açabilir.
      </p>
      <div class="mt-5 border-t border-slate-100 pt-5">
        <h3 class="text-sm font-bold text-slate-800 mb-2">Karşılama ekranı (masalar boş)</h3>
        <p class="text-xs text-slate-500 mb-3">YouTube bağlantısı doluysa kapı ekranında tanıtım videosu alanı açılır (sessiz otomatik oynatma).</p>
        <label class="q-field">
          <span class="q-field__label">YouTube URL</span>
          <input type="url" name="welcome_youtube_url" maxlength="512"
               value="<?php echo htmlspecialchars((string) ($settings['welcome_youtube_url'] ?? ''), ENT_QUOTES); ?>"
               class="q-input max-w-xl"
               placeholder="https://www.youtube.com/watch?v=…">
        </label>
      </div>

      <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="show_logo" value="1" <?php echo (!isset($settings['show_logo']) || !empty($settings['show_logo'])) ? 'checked' : ''; ?>>
          Logoyu göster
        </label>
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="show_active_numbers" value="1" <?php echo qd_queue_bool_setting($settings['show_active_numbers'] ?? null) ? 'checked' : ''; ?>>
          Sıra numaralarını göster
        </label>
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="show_estimated_wait" value="1" <?php echo qd_queue_bool_setting($settings['show_estimated_wait'] ?? null) ? 'checked' : ''; ?>>
          Tahmini bekleme
        </label>
        <label class="q-toolbar q-card q-card--pad flex items-center gap-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="show_powered_by" value="1" <?php echo (!isset($settings['show_powered_by']) || !empty($settings['show_powered_by'])) ? 'checked' : ''; ?>>
          "Powered by Qordy" etiketi
        </label>
      </div>
    </section>

    <!-- Languages + display copy -->
    <section class="q-card q-card--pad q-stack">
      <h2 class="q-card__title">Diller ve Kapı Ekranı Metinleri</h2>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
        <?php foreach ($allKnown as $code): ?>
          <label class="q-toolbar q-card q-card--pad flex items-center gap-2 text-sm" style="background:var(--color-surface-muted);">
            <input type="checkbox" name="languages[]" value="<?php echo $code; ?>" <?php echo in_array($code, $languages, true) ? 'checked' : ''; ?>>
            <span class="uppercase"><?php echo $code; ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="q-field">
          <label class="q-field__label">Varsayılan dil</label>
          <select name="default_language" class="q-input">
            <?php foreach ($allKnown as $code): ?>
              <option value="<?php echo $code; ?>" <?php echo $defaultLang === $code ? 'selected' : ''; ?>><?php echo strtoupper($code); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="q-table">
          <thead>
            <tr>
              <th class="w-16">Dil</th>
              <th>Başlık</th>
              <th>Alt başlık</th>
              <th>Çağrı metni (CTA)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allKnown as $code): ?>
              <tr>
                <td class="font-semibold uppercase"><?php echo $code; ?></td>
                <td><input type="text" name="display_title[<?php echo $code; ?>]" value="<?php echo htmlspecialchars($titleMap[$code] ?? '', ENT_QUOTES); ?>" class="q-input"></td>
                <td><input type="text" name="display_subtitle[<?php echo $code; ?>]" value="<?php echo htmlspecialchars($subtitleMap[$code] ?? '', ENT_QUOTES); ?>" class="q-input"></td>
                <td><input type="text" name="display_call_to_action[<?php echo $code; ?>]" value="<?php echo htmlspecialchars($ctaMap[$code] ?? '', ENT_QUOTES); ?>" class="q-input"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="q-field">
          <label class="q-field__label">Tema rengi</label>
          <input type="color" name="display_theme_color" value="<?php echo htmlspecialchars($settings['display_theme_color'] ?? '#0f172a', ENT_QUOTES); ?>" class="q-input h-10">
        </div>
        <div class="q-field">
          <label class="q-field__label">Vurgu rengi</label>
          <input type="color" name="display_accent_color" value="<?php echo htmlspecialchars($settings['display_accent_color'] ?? '#f97316', ENT_QUOTES); ?>" class="q-input h-10">
        </div>
        <div class="q-field">
          <label class="q-field__label">Arka plan görsel URL (isteğe bağlı)</label>
          <input type="url" name="display_bg_image_url" value="<?php echo htmlspecialchars($settings['display_bg_image_url'] ?? '', ENT_QUOTES); ?>" class="q-input" placeholder="https://...">
        </div>
      </div>
    </section>

    <!-- Notifications -->
    <?php $metaAllowed = !empty($metaWhatsappAllowed); ?>
    <section class="q-card q-card--pad q-stack">
      <div class="flex items-start justify-between gap-3">
        <h2 class="q-card__title">Bildirimler</h2>
        <?php if (!$metaAllowed): ?>
          <span class="q-badge q-badge--warning">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/></svg>
            Meta WhatsApp iznini süper adminden isteyin
          </span>
        <?php endif; ?>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <label class="q-toolbar q-card q-card--pad flex items-center gap-3 <?php echo $metaAllowed ? '' : 'opacity-60 cursor-not-allowed'; ?>" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="whatsapp_enabled" value="1"
                 <?php echo !empty($settings['whatsapp_enabled']) && $metaAllowed ? 'checked' : ''; ?>
                 <?php echo $metaAllowed ? '' : 'disabled'; ?>>
          <span class="font-semibold text-slate-700">WhatsApp (Meta Cloud API)</span>
        </label>
        <div class="q-card q-card--pad text-xs" style="background:var(--color-surface-muted);color:var(--color-text-secondary);">
          <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span>
            <strong class="text-slate-800">WhatsApp şablonu</strong> Qordy sistem yöneticisi tarafından merkezi olarak yönetilir.
            Bu ekrandan şablon adı girilmez.
          </span>
        </div>
        <label class="q-toolbar q-card q-card--pad flex items-center gap-3 md:col-span-2" style="background:var(--color-surface-muted);">
          <input type="checkbox" name="email_enabled" value="1" <?php echo !empty($settings['email_enabled']) ? 'checked' : ''; ?>>
          <span class="font-semibold">E-posta</span>
        </label>
      </div>
      <?php if (!$metaAllowed): ?>
      <p class="text-xs q-card q-card--pad" style="background:var(--color-surface-muted);color:var(--color-text-muted);">
        <strong>Bilgi:</strong> Meta WhatsApp özellikleri henüz işletmenize açılmadı. Bu süre içinde sıra/randevu bildirimleri yalnızca e-posta ile gönderilir.
      </p>
      <?php endif; ?>
    </section>

    <div class="flex items-center justify-end gap-2">
      <a href="/business/queue" class="q-btn q-btn--secondary">İptal</a>
      <button type="submit" class="q-btn q-btn--primary">Kaydet</button>
    </div>
  </form>
  </div>
</div>
