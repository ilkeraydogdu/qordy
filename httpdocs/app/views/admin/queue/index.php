<?php
/**
 * Queue admin — unified single-page dashboard.
 *
 * Sections (tabs) rendered on one route; no separate settings page needed.
 *   1. Canlı Sıra  — KPI + DataTable of active queue + CRM recent (filtering,
 *                    search, pagination via the central datatable component)
 *   2. Tasarım     — Live preview iframe + theme/colors/copy/toggles (AJAX)
 *   3. Ayarlar     — Advanced rules (inline AJAX saves)
 *
 * Vars: $settings, $active, $recent, $displayUrl, $csrf_token
 */

require_once __DIR__ . '/../../../support/QueueThemeRegistry.php';
require_once __DIR__ . '/../../queue/_helpers.php';
require_once __DIR__ . '/../../components/datatable.php';

if (!isset($settings) || !is_array($settings)) $settings = [];
if (!isset($active) || !is_array($active))     $active = [];
if (!isset($recent) || !is_array($recent))     $recent = [];
// Tenant context (business row) — used for placeholder generation so that
// social-media example text reflects the real business slug instead of any
// hard-coded mock data. Falls back to an empty array when out of context.
$business = (class_exists('\\App\\Core\\TenantContext') ? (\App\Core\TenantContext::get() ?: []) : []);
if (!is_array($business)) $business = [];

$displayUrl   = $displayUrl ?? '/sira';
$displayHost  = parse_url($displayUrl, PHP_URL_HOST) ?: '';
$hasSubdomain = $displayHost !== '' && substr_count($displayHost, '.') >= 2;
$qrImg        = rtrim(BASE_URL, '/') . '/qr?size=360&margin=5&data=' . urlencode($displayUrl);

$themes        = \App\Support\QueueThemeRegistry::all();
$currentTheme  = $settings['display_theme'] ?? \App\Support\QueueThemeRegistry::DEFAULT;
if (!isset($themes[$currentTheme])) $currentTheme = \App\Support\QueueThemeRegistry::DEFAULT;

$defaultLang  = $settings['default_language'] ?? 'tr';
$languages    = is_array($settings['languages'] ?? null) ? $settings['languages'] : ['tr','en'];
$allKnownLang = ['tr','en','de','ar','fr','es','ru'];
$titleMap     = is_array($settings['display_title'] ?? null) ? $settings['display_title'] : [];
$subtitleMap  = is_array($settings['display_subtitle'] ?? null) ? $settings['display_subtitle'] : [];
$ctaMap       = is_array($settings['display_call_to_action'] ?? null) ? $settings['display_call_to_action'] : [];

// Pre-process datatable rows so the render templates can use simple ${item.x}
$statusLabels = [
    'WAITING'   => ['Bekliyor',    'q-badge--warning'],
    'NOTIFIED'  => ['Çağrıldı',    'q-badge--info'],
    'SEATED'    => ['Oturdu',      'q-badge--success'],
    'CANCELLED' => ['İptal',       'q-badge--neutral'],
    'NO_SHOW'   => ['Gelmedi',     'q-badge--danger'],
    'EXPIRED'   => ['Süre bitti',  'q-badge--neutral'],
];
$statusFilterOptions = [];
foreach ($statusLabels as $code => $meta) $statusFilterOptions[$code] = $meta[0];

$mapEntry = function (array $e) use ($statusLabels): array {
    $status = (string) ($e['status'] ?? 'WAITING');
    [$label, $cls] = $statusLabels[$status] ?? [$status, 'bg-gray-100 text-gray-600'];
    $tags = '';
    if (!empty($e['has_baby']))          $tags .= '<span class="q-badge q-badge--warning text-[10px] mr-1">Bebek</span>';
    if (!empty($e['has_accessibility'])) $tags .= '<span class="q-badge q-badge--info text-[10px] mr-1">Erişim</span>';
    $tags .= '<span class="q-badge q-badge--neutral text-[10px] uppercase">' . htmlspecialchars((string) ($e['language'] ?? 'tr')) . '</span>';
    $ca = (string) ($e['created_at'] ?? '');
    $ts = $ca !== '' ? strtotime($ca) : false;
    $dateDisplay = ($ts && $ts > 0) ? date('d.m.Y H:i', $ts) : '—';
    $dateYmd     = ($ts && $ts > 0) ? date('Y-m-d', $ts) : '';
    return [
        'id'              => (int) ($e['id'] ?? 0),
        'queue_number'    => (int) ($e['queue_number'] ?? 0),
        'display_number'  => '#' . (int) ($e['queue_number'] ?? 0),
        'full_name'       => trim(($e['name'] ?? '') . ' ' . ($e['surname'] ?? '')),
        'tags_html'       => $tags,
        'phone'           => (string) ($e['phone'] ?? ''),
        'email'           => (string) ($e['email'] ?? ''),
        'party_size'      => (int) ($e['party_size'] ?? 1),
        'party_label'     => (int) ($e['party_size'] ?? 1) . ' kişi',
        'note'            => (string) ($e['note'] ?? ''),
        'time_label'      => !empty($e['created_at']) ? (date('H:i', strtotime((string) $e['created_at'])) ?: '') : '',
        'created_at'      => $ca,
        'date_display'    => $dateDisplay,
        'created_date'    => $dateYmd,
        'status'          => $status,
        'status_label'    => $label,
        'status_badge'    => '<span class="q-badge ' . $cls . '">' . $label . '</span>',
        'language'        => (string) ($e['language'] ?? 'tr'),
        'can_notify'      => $status === 'WAITING' ? 1 : 0,
        'can_progress'    => in_array($status, ['WAITING','NOTIFIED'], true) ? 1 : 0,
    ];
};

$activeRows = array_map($mapEntry, $active);
$recentRows = array_map($mapEntry, $recent);

$toggleMap = [
    'show_logo'            => ['label' => 'İşletme logosu',         'default' => true],
    'show_waiting_count'   => ['label' => 'Bekleyen sayısı',        'default' => false],
    'show_estimated_wait'  => ['label' => 'Tahmini bekleme süresi', 'default' => false],
    'show_active_numbers'  => ['label' => 'Aktif sıra numaraları',  'default' => false],
    'show_powered_by'      => ['label' => '"Powered by Qordy"',     'default' => true],
];

// Misafir form alanları artık ürün kararı: ad soyad + telefon + e-posta + kişi
// sayısı + bebek çubukları her zaman gösterilir. Not ve erişilebilirlik alanları
// kaldırıldı (çok az restoran kullanıyordu ve formu gereksiz uzatıyordu).

// Meta WhatsApp Cloud API durumu — Qordy geneli (qodmin/settings > Meta API).
// Queue notification bu global ayarı otomatik kullanıyor. Template adı da
// artık global; işletme kendi template'ini girmiyor, süper admin belirliyor.
$metaConfigured = false;
$metaGlobalTemplate = '';
try {
    $sys = \App\Core\DependencyFactory::getSystemSettingsService();
    $metaConfigured = trim((string) $sys->getSetting('meta_access_token', '')) !== ''
                   && trim((string) $sys->getSetting('meta_phone_number_id', '')) !== '';
    $metaGlobalTemplate = trim((string) $sys->getSetting('meta_queue_template_name', ''));
} catch (\Throwable $e) { /* non-fatal */ }

// Per-tenant Meta izni — customers.meta_whatsapp_enabled (süper admin tarafından yönetilir).
// Controller bunu `metaWhatsappAllowed` olarak veriyor; eski view'larda set
// edilmemiş olabilir, bu yüzden null-safe okuyoruz.
$metaWhatsappAllowed = !empty($metaWhatsappAllowed ?? null);
$canDeleteCrm  = !empty($can_delete_crm ?? false);
$crmFromParam    = isset($crm_from) && is_string($crm_from) ? $crm_from : '';
$crmToParam      = isset($crm_to) && is_string($crm_to) ? $crm_to : '';
$crmStatusParam  = isset($crm_status) && is_string($crm_status) ? $crm_status : '';
$crmLimitParam   = isset($crm_limit) ? (int) $crm_limit : 200;
?>

<!-- Shared helper functions (DataTable requires formatReservationDate/getStatusLabel/escapeHtml in global scope). -->
<script>
window.formatReservationDate = window.formatReservationDate || function(v){ return v || ''; };
window.escapeHtml = window.escapeHtml || function(t){ if (t == null) return ''; const d = document.createElement('div'); d.textContent = String(t); return d.innerHTML; };
window.getStatusLabel = window.getStatusLabel || function(s){ return s || ''; };
</script>

<div class="q-page q-biz-theme">
  <div class="q-container" id="queue-admin-root" style="max-width:1600px;">

    <?php if (!empty($superadmin_context) && is_array($superadmin_context)):
        // Süper admin, /qodmin/queue/{id} üzerinden işletmenin sıra panelini
        // aynı tasarımla görür. Salt-okunur banner; düzenleme için "İşletmeye
        // giriş yap" akışını öneriyoruz.
        $saCompany   = (string)($superadmin_context['company_name'] ?? '—');
        $saTenantId  = (string)($superadmin_context['customer_id'] ?? '');
    ?>
    <div class="q-callout mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="flex items-start gap-3 min-w-0">
        <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="min-w-0">
          <div class="text-sm font-black text-amber-900 truncate">Süper admin görünümü — <?php echo htmlspecialchars($saCompany); ?></div>
          <div class="text-xs text-amber-800 mt-0.5">Bu panel salt-okunur. Sıra/ayar işlemleri için <b>İşletmeye giriş yap</b> akışını kullanın.</div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
        <a href="<?php echo BASE_URL; ?>/qodmin/queue" class="q-btn q-btn--soft q-btn--sm">İşletme seçimine dön</a>
        <?php if ($saTenantId !== ''): ?>
        <a href="<?php echo BASE_URL; ?>/qodmin/businesses/<?php echo htmlspecialchars($saTenantId); ?>/login-as" class="q-btn q-btn--primary q-btn--sm">İşletmeye giriş yap</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <header class="q-page-header mb-6">
      <div>
        <p class="q-page-header__eyebrow">Sıra</p>
        <h1 class="q-page-header__title">Sıra Yönetimi</h1>
        <p class="q-page-header__subtitle">QR sıramatik – canlı sıra, tasarım, kurallar tek panelden.</p>
      </div>
      <div class="q-page-header__actions flex flex-wrap items-center gap-2">
        <label class="q-toggle-row cursor-pointer" title="Kapı ekranı ve API bu anahtarla açılır/kapanır.">
          <span class="q-toggle"><input type="checkbox" data-q-toggle="is_enabled" <?php echo !empty($settings['is_enabled']) ? 'checked' : ''; ?>><span class="slider"></span></span>
          <span class="font-bold">Sistem aktif</span>
        </label>
        <label class="q-toggle-row cursor-pointer" title="Masalar dolduğunda açın; kapı ekranı QR'a geçsin. Kapalıyken kapı ekranı karşılama/sosyal medya modunda kalır.">
          <span class="q-toggle"><input type="checkbox" data-q-toggle="is_accepting_queue" <?php echo !empty($settings['is_accepting_queue']) ? 'checked' : ''; ?>><span class="slider"></span></span>
          <span class="font-bold">Masalar dolu · sıra al</span>
        </label>
        <a href="<?php echo htmlspecialchars($displayUrl, ENT_QUOTES); ?>" target="_blank" class="q-btn q-btn--primary inline-flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          Kapı ekranını aç
        </a>
      </div>
    </header>

    <!-- Tabs -->
    <nav class="q-tab-row mb-6" role="tablist">
      <button type="button" class="q-tab active" data-q-tab="live">Canlı Sıra</button>
      <button type="button" class="q-tab" data-q-tab="design">Tasarım</button>
      <button type="button" class="q-tab" data-q-tab="rules">Ayarlar</button>
    </nav>

    <!-- ================ TAB: CANLI SIRA ================ -->
    <section id="q-tab-live" class="q-tab-panel">

      <!-- KPIs -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="q-stat">
          <div class="q-kicker">Aktif Sıra</div>
          <div class="q-stat__value" id="kpiActive"><?php echo count($activeRows); ?></div>
          <div class="q-hint text-xs mt-1">bekleyen misafir</div>
        </div>
        <div class="q-stat">
          <div class="q-kicker">Ortalama Bekleme</div>
          <div class="q-stat__value"><?php echo (int) ($settings['average_wait_minutes'] ?? 15); ?></div>
          <div class="q-hint text-xs mt-1">dakika</div>
        </div>
        <div class="q-stat">
          <div class="q-kicker">CRM kayıt</div>
          <div class="q-stat__value"><?php echo count($recentRows); ?></div>
          <div class="q-hint text-xs mt-1">(filtreyle)</div>
        </div>
        <div class="q-stat">
          <div class="q-kicker">Bildirim Kanalı</div>
          <div class="mt-2 flex gap-1.5 flex-wrap">
            <?php if (!empty($settings['whatsapp_enabled'])): ?>
              <span class="px-2 py-1 rounded-md bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase">WhatsApp</span>
            <?php endif; ?>
            <?php if (!empty($settings['email_enabled'])): ?>
              <span class="px-2 py-1 rounded-md bg-blue-100 text-blue-700 text-[10px] font-bold uppercase">E-posta</span>
            <?php endif; ?>
            <?php if (empty($settings['whatsapp_enabled']) && empty($settings['email_enabled'])): ?>
              <span class="q-badge q-badge--neutral text-[10px]">Kapalı</span>
            <?php endif; ?>
          </div>
          <div class="q-hint text-xs mt-1">sıra geldiğinde</div>
        </div>
      </div>

      <!-- Active queue DataTable -->
      <div class="q-card p-4 sm:p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <div class="q-kicker">Şu an sırada</div>
            <div class="q-card__title mt-0.5">Aktif Misafirler</div>
          </div>
          <div class="text-xs text-slate-400 flex items-center gap-1.5">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            Canlı
          </div>
        </div>
        <?php renderDataTable([
            'id'      => 'qd-active',
            'columns' => [
                ['label' => '#',        'field' => 'display_number', 'render' => '<span class="font-black" style="color:var(--color-ink)">${item.display_number}</span>'],
                ['label' => 'Misafir',  'field' => 'full_name',      'render' => '<div class="font-semibold" style="color:var(--color-text-primary)">${item.full_name}</div><div class="mt-1">${item.tags_html|raw}</div>'],
                ['label' => 'İletişim', 'field' => 'phone',          'render' => '<div class="text-sm">${item.phone}</div><div class="q-hint text-xs">${item.email}</div>'],
                ['label' => 'Kişi',     'field' => 'party_label'],
                ['label' => 'Not',      'field' => 'note'],
                ['label' => 'Saat',     'field' => 'time_label'],
                ['label' => 'Durum',    'field' => 'status_label',   'render' => '${item.status_badge|raw}'],
            ],
            'data'    => $activeRows,
            'filters' => [
                'status'   => ['type' => 'select', 'label' => 'Durum',   'field' => 'status',   'options' => $statusFilterOptions],
                'language' => ['type' => 'select', 'label' => 'Dil',     'field' => 'language', 'options' => ['tr' => 'TR', 'en' => 'EN', 'de' => 'DE', 'ar' => 'AR']],
            ],
            'search' => true,
            'searchPlaceholder' => 'Misafir, telefon, e-posta ara...',
            'pagination' => true,
            'perPage' => 10,
            'actions' => [
                ['type' => 'button', 'label' => 'Çağır',   'onClick' => 'qdCallGuest(${item.id})',  'class' => 'q-action-btn bg-blue-600 hover:bg-blue-700 text-white'],
                ['type' => 'button', 'label' => 'Oturdu',  'onClick' => 'qdQueueAct(${item.id}, \'seat\')',    'class' => 'q-action-btn bg-emerald-600 hover:bg-emerald-700 text-white'],
                ['type' => 'button', 'label' => 'Gelmedi', 'onClick' => 'qdQueueAct(${item.id}, \'no-show\')', 'class' => 'q-action-btn bg-rose-500 hover:bg-rose-600 text-white'],
                ['type' => 'button', 'label' => 'İptal',   'onClick' => 'qdQueueAct(${item.id}, \'cancel\')',  'class' => 'q-btn q-btn--ghost q-btn--sm q-action-btn'],
            ],
            'emptyMessage' => 'Şu an sırada bekleyen misafir yok.',
        ]); ?>
      </div>

      <!-- CRM Recent DataTable -->
      <div class="q-card p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
          <div>
            <div class="q-kicker">Kayıtlar</div>
            <div class="q-card__title mt-0.5">Misafir geçmişi (CRM)</div>
            <p class="text-xs text-slate-500 mt-1 max-w-xl">Sunucu tarafı filtre: tarih aralığı ve üst sınır. Tablo içi arama/ek filtre ayrı uygulanır. Sil: yalnızca tamamlanmış kayıtlar; aktif sırada bekleyen/çağrıldı satırları silinmez (önce iptal veya tamamlayın).</p>
          </div>
        </div>
        <form method="get" action="<?php echo BASE_URL; ?>/business/queue" class="q-filter-bar flex flex-wrap items-end gap-2 sm:gap-3 mb-4 text-xs sm:text-sm">
          <div>
            <label class="q-filter-group__label mb-0.5">Başlangıç</label>
            <input type="date" name="crm_from" value="<?php echo htmlspecialchars($crmFromParam, ENT_QUOTES, 'UTF-8'); ?>"
                   class="q-input py-1.5 text-xs sm:text-sm w-[150px]">
          </div>
          <div>
            <label class="q-filter-group__label mb-0.5">Bitiş</label>
            <input type="date" name="crm_to" value="<?php echo htmlspecialchars($crmToParam, ENT_QUOTES, 'UTF-8'); ?>"
                   class="q-input py-1.5 text-xs sm:text-sm w-[150px]">
          </div>
          <div>
            <label class="q-filter-group__label mb-0.5">Durum (sunucu)</label>
            <select name="crm_status" class="q-input py-1.5 text-xs sm:text-sm min-w-[130px]">
              <option value=""<?php echo $crmStatusParam === '' ? ' selected' : ''; ?>>Tümü</option>
              <?php foreach ($statusFilterOptions as $k => $lab): ?>
                <option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $crmStatusParam === (string) $k ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="q-filter-group__label mb-0.5">Listele en fazla</label>
            <select name="crm_limit" class="q-input py-1.5 text-xs sm:text-sm">
              <?php foreach ([50, 100, 200, 500] as $lim): ?>
                <option value="<?php echo (int) $lim; ?>"<?php echo (int) $crmLimitParam === (int) $lim ? ' selected' : ''; ?>><?php echo (int) $lim; ?> kayıt</option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="q-btn q-btn--primary q-btn--sm">Uygula</button>
          <a href="<?php echo BASE_URL; ?>/business/queue" class="q-btn q-btn--ghost q-btn--sm">Sıfırla</a>
        </form>
        <?php if (!empty($canDeleteCrm)): ?>
        <div class="flex flex-wrap items-center gap-2 mb-3 text-xs text-slate-600">
          <span class="font-bold text-slate-700">Toplu sil (ID’ler, virgülle):</span>
          <input type="text" id="qd-bulk-ids" class="q-input w-48 max-w-full font-mono" placeholder="ör. 12, 34" autocomplete="off">
          <button type="button" class="px-2 py-1 rounded-lg bg-rose-100 text-rose-800 font-bold hover:bg-rose-200" id="qd-bulk-del">Seçilenleri sil</button>
        </div>
        <?php endif; ?>
        <?php
        $crmFiltersUi = [
            'status' => ['type' => 'select', 'label' => 'Durum (tabloda)', 'field' => 'status', 'options' => $statusFilterOptions],
            'crmdate' => ['type' => 'daterange', 'label' => 'Tarih', 'field' => 'created_date'],
        ];
        $crmActions = [];
        if ($canDeleteCrm) {
            $crmActions[] = [
                'type'    => 'button',
                'label'   => 'Sil',
                'onClick' => 'qdDeleteCrm(${item.id})',
                'class'   => 'q-action-btn bg-rose-100 hover:bg-rose-200 text-rose-800',
            ];
        }
        renderDataTable([
            'id'      => 'qd-recent',
            'columns' => [
                ['label' => 'Tarih',    'field' => 'date_display'],
                ['label' => 'Misafir',  'field' => 'full_name',   'render' => '<span class="font-semibold" style="color:var(--color-text-primary)">${item.full_name}</span>'],
                ['label' => 'Telefon',  'field' => 'phone'],
                ['label' => 'E-posta',  'field' => 'email'],
                ['label' => 'Kişi',     'field' => 'party_label'],
                ['label' => 'Durum',    'field' => 'status_label','render' => '${item.status_badge|raw}'],
            ],
            'data'    => $recentRows,
            'filters' => $crmFiltersUi,
            'search' => true,
            'searchPlaceholder' => 'Ara (isim, telefon, e-posta)…',
            'pagination' => true,
            'perPage' => 10,
            'actions' => $crmActions,
            'emptyMessage' => 'Henüz kayıt yok.',
        ]); ?>
      </div>
    </section>

    <!-- ================ TAB: TASARIM ================ -->
    <section id="q-tab-design" class="q-tab-panel hidden">
      <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <!-- LEFT: sticky live preview -->
        <aside class="xl:col-span-5">
          <div class="xl:sticky xl:top-4 space-y-4">
            <div class="q-card p-5 relative overflow-hidden">
              <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
                <div>
                  <div class="q-kicker">Canlı Önizleme</div>
                  <div class="q-card__title mt-0.5">Kapı Ekranı</div>
                </div>
                <div class="flex gap-1 flex-wrap">
                  <button type="button" data-device="tv"     class="qd-device q-btn q-btn--primary q-btn--sm">TV</button>
                  <button type="button" data-device="tablet" class="qd-device px-2.5 py-1.5 text-[11px] font-bold rounded-lg bg-slate-100 text-slate-600">Tablet</button>
                  <button type="button" id="previewReload"   class="px-2 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600" title="Yenile">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  </button>
                </div>
              </div>

              <!-- Preview mode switcher: live / welcome / queue override -->
              <div class="mb-3 flex gap-1 p-1 bg-slate-100 rounded-xl text-[11px] font-bold">
                <button type="button" data-pmode="live"    class="qd-pmode flex-1 py-1.5 rounded-lg bg-white shadow text-slate-900" title="İşletmenin o anki ayarını göster (Masalar dolu anahtarına bağlı).">Canlı</button>
                <button type="button" data-pmode="welcome" class="qd-pmode flex-1 py-1.5 rounded-lg text-slate-600 hover:bg-white/60"   title="Masalar boş iken nasıl gözükeceğini gör.">Karşılama</button>
                <button type="button" data-pmode="queue"   class="qd-pmode flex-1 py-1.5 rounded-lg text-slate-600 hover:bg-white/60"   title="Masalar dolu iken nasıl gözükeceğini gör.">Sıra · QR</button>
              </div>

              <!-- Preview: container takes the full column width; the iframe
                   renders at the real device resolution and is scaled DOWN to
                   fit via a ResizeObserver (JS below). This keeps proportions
                   pixel-perfect no matter the admin's window size. -->
              <div id="previewWrap" class="q-preview relative" style="aspect-ratio: 16/9">
                <iframe id="previewFrame"
                        src="<?php echo htmlspecialchars($displayUrl, ENT_QUOTES); ?>?preview=1"
                        class="absolute top-0 left-0 border-0"
                        style="transform-origin: top left; width: 1600px; height: 900px;"
                        loading="lazy"></iframe>

                <!-- Subtle "Güncelleniyor" overlay instead of visible reload flicker -->
                <div id="previewBusy" class="absolute top-2 right-2 px-2.5 py-1 rounded-full bg-slate-900/70 text-white text-[10px] font-bold tracking-wider uppercase backdrop-blur opacity-0 transition-opacity pointer-events-none">
                  Güncelleniyor…
                </div>
              </div>

              <div class="mt-3 flex items-center justify-end gap-2 text-[11px]">
                <a href="<?php echo htmlspecialchars($displayUrl, ENT_QUOTES); ?>" target="_blank" class="font-bold text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1">
                  Yeni sekmede aç
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
              </div>
            </div>

            <!-- Compact share block (replaces the duplicated QR card) -->
            <div class="q-card p-4">
              <div class="flex items-start justify-between gap-2 mb-2">
                <div>
                  <div class="q-kicker">Paylaş</div>
                  <div class="text-sm font-bold text-slate-900 mt-0.5">Kapı ekranı URL'si</div>
                </div>
                <button id="rotateBtn" type="button" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold" title="QR Token'ı yenile (güvenlik için).">QR'ı Yenile</button>
              </div>
              <div class="flex items-center gap-1.5">
                <code id="displayUrlText" class="flex-1 px-2 py-2 rounded-lg bg-slate-100 text-[11px] font-mono text-slate-800 break-all"><?php echo htmlspecialchars($displayUrl, ENT_QUOTES); ?></code>
                <button type="button" id="copyUrlBtn" class="q-btn q-btn--primary q-btn--sm">Kopyala</button>
              </div>
              <?php if (!$hasSubdomain): ?>
              <div class="mt-2.5 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-[11px] leading-relaxed">
                <b>Uyarı:</b> İşletmeye <i>subdomain</i> atanmadığı için QR ana domain'e düşüyor. Profil ayarlarından subdomain belirleyin.
              </div>
              <?php endif; ?>
            </div>
          </div>
        </aside>

        <!-- RIGHT: settings with sub-tabs -->
        <div class="xl:col-span-7">
          <!-- Sub-tabs -->
          <nav class="qd-subtabbar mb-5" role="tablist">
            <button type="button" class="qd-subtab active" data-qd-subtab="welcome">
              <span>Karşılama</span>
              <span class="text-[9px] font-semibold opacity-60 uppercase tracking-wider">Masalar boş</span>
            </button>
            <button type="button" class="qd-subtab" data-qd-subtab="queue">
              <span>Sıra · QR</span>
              <span class="text-[9px] font-semibold opacity-60 uppercase tracking-wider">Masalar dolu</span>
            </button>
            <button type="button" class="qd-subtab" data-qd-subtab="brand">
              <span>Tema · Renk</span>
              <span class="text-[9px] font-semibold opacity-60 uppercase tracking-wider">Ortak</span>
            </button>
          </nav>

          <!-- ===== SUB-TAB: KARŞILAMA (welcome) ===== -->
          <section class="qd-subpanel" data-qd-sub="welcome">
            <div class="q-card p-5 mb-5">
              <div class="mb-4">
                <div class="q-kicker">Karşılama Metinleri</div>
                <div class="q-card__title mt-0.5">Masalar boşken ekrandaki yazılar</div>
              </div>
              <div class="grid grid-cols-1 gap-3">
                <div>
                  <label class="q-field__label">Slogan rozeti</label>
                  <input type="text" data-qd-text="welcome_tagline" maxlength="60" value="<?php echo htmlspecialchars((string) ($settings['welcome_tagline'] ?? ''), ENT_QUOTES); ?>" class="q-input" placeholder="Lezzet, sohbet, sıcak bir mola">
                </div>
                <div>
                  <label class="q-field__label">Başlık</label>
                  <input type="text" data-qd-text="welcome_title" maxlength="80" value="<?php echo htmlspecialchars((string) ($settings['welcome_title'] ?? ''), ENT_QUOTES); ?>" class="q-input" placeholder="<?php echo htmlspecialchars($business['company_name'] ?? 'İşletme adı', ENT_QUOTES); ?>">
                </div>
                <div>
                  <label class="q-field__label">Alt başlık</label>
                  <textarea data-qd-text="welcome_subtitle" rows="2" maxlength="200" class="q-input" placeholder="İçeride yerimiz var — buyurun."><?php echo htmlspecialchars((string) ($settings['welcome_subtitle'] ?? ''), ENT_QUOTES); ?></textarea>
                </div>
                <div>
                  <label class="q-field__label">Çalışma saatleri</label>
                  <input type="text" data-qd-text="welcome_hours" maxlength="80" value="<?php echo htmlspecialchars((string) ($settings['welcome_hours'] ?? ''), ENT_QUOTES); ?>" class="q-input" placeholder="Her gün 09.00 – 23.00">
                </div>
                <div>
                  <label class="q-field__label">YouTube tanıtım / reklam (masalar boşken)</label>
                  <input type="url" data-qd-text="welcome_youtube_url" maxlength="512"
                         value="<?php echo htmlspecialchars((string) ($settings['welcome_youtube_url'] ?? ''), ENT_QUOTES); ?>"
                         class="q-input" placeholder="https://www.youtube.com/watch?v=… veya youtu.be/…">
                  <p class="text-[11px] text-slate-500 mt-1">Boş bırakırsanız sadece metin ve sosyal alanlar gösterilir. Doluysa ekran ikiye bölünür; video otomatik oynar (sessiz). TV’de tarayıcı politikası engelleyebilir.</p>
                </div>
              </div>
            </div>

            <div class="q-card p-5">
              <div class="mb-3">
                <div class="q-kicker">Sosyal Medya · İletişim</div>
                <div class="q-card__title mt-0.5">Karşılama ekranındaki bağlantılar</div>
                <p class="text-[11px] text-slate-500 mt-1">Boş alanlar ekranda gösterilmez.</p>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php
                  // Placeholders derive from the current business so the
                  // fields look meaningful without any hard-coded mock data.
                  $bizSlug  = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($business['company_name'] ?? 'isletme')));
                  if ($bizSlug === '') $bizSlug = 'isletme';
                  $bizDomain = $bizSlug . '.com';
                  $socialFields = [
                    'social_instagram' => ['Instagram',  '@' . $bizSlug],
                    'social_facebook'  => ['Facebook',   'facebook.com/' . $bizSlug],
                    'social_tiktok'    => ['TikTok',     '@' . $bizSlug],
                    'social_whatsapp'  => ['WhatsApp',   '+90 5xx xxx xx xx'],
                    'social_website'   => ['Web sitesi', $bizDomain],
                    'social_menu_url'  => ['Menü linki', $bizDomain . '/menu'],
                    'social_phone'     => ['Telefon',    '+90 2xx xxx xx xx'],
                    'social_address'   => ['Adres',      'İl, İlçe, Mahalle'],
                    'social_google_review' => ['Google yorum linki', 'https://g.page/r/.../review'],
                  ];
                  foreach ($socialFields as $fkey => [$lbl, $ph]): ?>
                <div>
                  <label class="q-field__label"><?php echo htmlspecialchars($lbl, ENT_QUOTES); ?></label>
                  <input type="text" data-qd-text="<?php echo htmlspecialchars($fkey, ENT_QUOTES); ?>" maxlength="200"
                         value="<?php echo htmlspecialchars((string) ($settings[$fkey] ?? ''), ENT_QUOTES); ?>"
                         placeholder="<?php echo htmlspecialchars($ph, ENT_QUOTES); ?>"
                         class="q-input">
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <!-- ===== SUB-TAB: SIRA (queue) ===== -->
          <section class="qd-subpanel hidden" data-qd-sub="queue">
            <div class="q-card p-5 mb-5">
              <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                  <div class="q-kicker">Sıra Modu Metinleri</div>
                  <div class="q-card__title mt-0.5">Masalar doluyken QR yanında ne yazsın?</div>
                  <p class="text-[11px] text-slate-500 mt-1">Her dil için ayrı giriş yapılır. Otomatik çeviri yoktur.</p>
                </div>
                <button type="button" id="copyCloneBtn"
                        class="shrink-0 q-btn q-btn--primary q-btn--sm"
                        title="Seçili dildeki metinleri diğer tüm dillere kopyalar.">
                  Diğer dillere kopyala
                </button>
              </div>

              <div id="copyLangTabs" class="flex flex-wrap gap-1 mb-4 p-1 bg-slate-100 rounded-xl" role="tablist">
                <?php foreach ($languages as $l):
                  $isDefault = $l === $defaultLang;
                ?>
                  <button type="button" data-lang="<?php echo htmlspecialchars($l, ENT_QUOTES); ?>"
                          class="qd-copy-lang-tab flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo $isDefault ? 'bg-white shadow text-slate-900' : 'text-slate-600 hover:bg-white/60'; ?>">
                    <span class="uppercase"><?php echo htmlspecialchars($l, ENT_QUOTES); ?></span>
                    <span class="qd-copy-lang-dot w-1.5 h-1.5 rounded-full bg-slate-300" title="Boş"></span>
                    <?php if ($isDefault): ?>
                      <span class="text-[9px] uppercase tracking-wider opacity-60">var.</span>
                    <?php endif; ?>
                  </button>
                <?php endforeach; ?>
              </div>
              <select id="copyLang" class="hidden">
                <?php foreach ($languages as $l): ?>
                  <option value="<?php echo htmlspecialchars($l, ENT_QUOTES); ?>" <?php echo $l === $defaultLang ? 'selected' : ''; ?>><?php echo strtoupper($l); ?></option>
                <?php endforeach; ?>
              </select>

              <div class="grid grid-cols-1 gap-3" id="copyEditor"
                   data-titles='<?php echo htmlspecialchars(json_encode($titleMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'
                   data-subs='<?php echo htmlspecialchars(json_encode($subtitleMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'
                   data-ctas='<?php echo htmlspecialchars(json_encode($ctaMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'>
                <div>
                  <label class="q-field__label">Başlık</label>
                  <input type="text" id="copyTitle" class="q-input" placeholder="Tüm masalarımız dolu" value="<?php echo htmlspecialchars($titleMap[$defaultLang] ?? '', ENT_QUOTES); ?>">
                </div>
                <div>
                  <label class="q-field__label">Alt başlık</label>
                  <input type="text" id="copySub" class="q-input" placeholder="QR kodu okutarak sıra alın" value="<?php echo htmlspecialchars($subtitleMap[$defaultLang] ?? '', ENT_QUOTES); ?>">
                </div>
                <div>
                  <label class="q-field__label">QR çağrı metni</label>
                  <input type="text" id="copyCta" class="q-input" placeholder="Telefonunuzla QR'ı okutun" value="<?php echo htmlspecialchars($ctaMap[$defaultLang] ?? '', ENT_QUOTES); ?>">
                </div>
              </div>
            </div>

            <div class="q-card p-5">
              <div class="mb-3">
                <div class="q-kicker">Sıra Modu Görünümü</div>
                <div class="q-card__title mt-0.5">Kapı ekranında neler görünsün?</div>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <?php foreach ($toggleMap as $tkey => $meta):
                  $checked = isset($settings[$tkey]) ? !empty($settings[$tkey]) : (bool) $meta['default'];
                ?>
                <label class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 cursor-pointer">
                  <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES); ?></span>
                  <span class="q-toggle">
                    <input type="checkbox" data-q-toggle="<?php echo htmlspecialchars($tkey, ENT_QUOTES); ?>" <?php echo $checked ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                  </span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <!-- ===== SUB-TAB: TEMA / RENK (brand) ===== -->
          <section class="qd-subpanel hidden" data-qd-sub="brand">
            <div class="q-card p-5 mb-5">
              <div class="mb-3">
                <div class="q-kicker">Görünüm şablonu</div>
                <div class="q-card__title mt-0.5">Tema kitaplığı</div>
                <p class="text-[11px] text-slate-500 mt-1">Genel düzenler tüm işletmelere uygundur. Sektör paketleri aynı düzeni markanıza göre renk/ambiyans ile özelleştirir (Sıra · QR + karşılama vurguları). Önizlemeyi görmek için <b>Sıra · QR</b> veya <b>Karşılama</b> sekmesine geçin.</p>
              </div>
              <?php
                $themesGeneral = \App\Support\QueueThemeRegistry::generalLibrary();
                $themesSector = \App\Support\QueueThemeRegistry::sectorLibrary();
              ?>
              <div class="space-y-6">
                <div>
                  <div class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Genel</div>
                  <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                    <?php foreach ($themesGeneral as $tkey => $t):
                      $isActive = $currentTheme === $tkey;
                      $previewSvg = \App\Support\QueueThemeRegistry::previewSvg($t);
                      $badge = trim((string) ($t['sector_tr'] ?? ''));
                    ?>
                    <button type="button" data-qd-theme="<?php echo htmlspecialchars($tkey, ENT_QUOTES); ?>"
                            class="q-theme-card <?php echo $isActive ? 'active' : ''; ?> text-left">
                      <div class="h-24 overflow-hidden bg-slate-100 relative">
                        <?php echo $previewSvg; ?>
                        <?php if ($badge !== ''): ?>
                          <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded-md bg-slate-900/80 text-white text-[8px] font-bold uppercase tracking-wide max-w-[90%] truncate"><?php echo htmlspecialchars($badge, ENT_QUOTES); ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="px-3 py-2">
                        <div class="flex items-center justify-between gap-1">
                          <div class="text-sm font-bold text-slate-900 leading-tight"><?php echo htmlspecialchars($t['name_tr'], ENT_QUOTES); ?></div>
                          <?php if ($isActive): ?>
                            <span class="shrink-0 text-[9px] font-black text-white bg-indigo-500 px-1.5 py-0.5 rounded-full tracking-widest uppercase">Aktif</span>
                          <?php endif; ?>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5 leading-tight line-clamp-2"><?php echo htmlspecialchars($t['description_tr'], ENT_QUOTES); ?></div>
                      </div>
                    </button>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div>
                  <div class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Sektör kitaplığı</div>
                  <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                    <?php foreach ($themesSector as $tkey => $t):
                      $isActive = $currentTheme === $tkey;
                      $previewSvg = \App\Support\QueueThemeRegistry::previewSvg($t);
                      $badge = trim((string) ($t['sector_tr'] ?? ''));
                    ?>
                    <button type="button" data-qd-theme="<?php echo htmlspecialchars($tkey, ENT_QUOTES); ?>"
                            class="q-theme-card <?php echo $isActive ? 'active' : ''; ?> text-left">
                      <div class="h-24 overflow-hidden bg-slate-100 relative">
                        <?php echo $previewSvg; ?>
                        <?php if ($badge !== ''): ?>
                          <span class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded-md bg-slate-900/80 text-white text-[8px] font-bold uppercase tracking-wide max-w-[90%] truncate"><?php echo htmlspecialchars($badge, ENT_QUOTES); ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="px-3 py-2">
                        <div class="flex items-center justify-between gap-1">
                          <div class="text-sm font-bold text-slate-900 leading-tight"><?php echo htmlspecialchars($t['name_tr'], ENT_QUOTES); ?></div>
                          <?php if ($isActive): ?>
                            <span class="shrink-0 text-[9px] font-black text-white bg-indigo-500 px-1.5 py-0.5 rounded-full tracking-widest uppercase">Aktif</span>
                          <?php endif; ?>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5 leading-tight line-clamp-2"><?php echo htmlspecialchars($t['description_tr'], ENT_QUOTES); ?></div>
                      </div>
                    </button>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="q-card p-5">
              <div class="mb-3">
                <div class="q-kicker">Renkler & Arka Plan</div>
                <div class="q-card__title mt-0.5">Marka dokunuşu</div>
                <p class="text-[11px] text-slate-500 mt-1">Hem Karşılama hem Sıra modunu etkiler.</p>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="q-field__label">Tema rengi</label>
                  <input type="color" data-qd-color="display_theme_color" value="<?php echo htmlspecialchars($settings['display_theme_color'] ?? '#0f172a', ENT_QUOTES); ?>" class="h-10 w-full border border-slate-200 rounded-lg cursor-pointer">
                </div>
                <div>
                  <label class="q-field__label">Vurgu rengi</label>
                  <input type="color" data-qd-color="display_accent_color" value="<?php echo htmlspecialchars($settings['display_accent_color'] ?? '#f97316', ENT_QUOTES); ?>" class="h-10 w-full border border-slate-200 rounded-lg cursor-pointer">
                </div>
                <div>
                  <label class="q-field__label">Arka plan görsel URL (opsiyonel)</label>
                  <input type="url" data-qd-text="display_bg_image_url" value="<?php echo htmlspecialchars($settings['display_bg_image_url'] ?? '', ENT_QUOTES); ?>" class="q-input" placeholder="https://...">
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </section>

    <!-- ================ TAB: AYARLAR ================ -->
    <section id="q-tab-rules" class="q-tab-panel hidden">
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <div class="q-card p-5">
          <div class="mb-4">
            <div class="q-kicker">Kurallar</div>
            <div class="q-card__title mt-0.5">Sıra davranışı</div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="q-field__label">Ortalama bekleme (dk)</label>
              <input type="number" min="1" max="240" data-qd-num="average_wait_minutes" value="<?php echo (int) ($settings['average_wait_minutes'] ?? 15); ?>" class="q-input">
            </div>
            <div>
              <label class="q-field__label">Maksimum grup (kişi)</label>
              <input type="number" min="1" max="50" data-qd-num="max_party_size" value="<?php echo (int) ($settings['max_party_size'] ?? 12); ?>" class="q-input">
            </div>
            <div>
              <label class="q-field__label">QR token ömrü (sn)</label>
              <input type="number" min="15" max="3600" data-qd-num="qr_token_ttl_seconds" value="<?php echo (int) ($settings['qr_token_ttl_seconds'] ?? 90); ?>" class="q-input">
              <p class="text-[11px] text-slate-400 mt-1">Düşük değer daha güvenli. Tavsiye 60-120 sn.</p>
            </div>
            <div>
              <label class="q-field__label">Oto "gelmedi" (dk)</label>
              <input type="number" min="0" max="120" data-qd-num="auto_no_show_minutes" value="<?php echo (int) ($settings['auto_no_show_minutes'] ?? 5); ?>" class="q-input">
              <p class="text-[11px] text-slate-400 mt-1">0 = kapalı.</p>
            </div>
            <div class="sm:col-span-2">
              <label class="q-field__label">Aynı telefon soğuma süresi (dk)</label>
              <input type="number" min="0" max="1440" data-qd-num="entry_cooldown_minutes" value="<?php echo (int) ($settings['entry_cooldown_minutes'] ?? 90); ?>" class="q-input">
            </div>
          </div>
        </div>

        <div class="q-card p-5">
          <div class="mb-3">
            <div class="q-kicker">Form</div>
            <div class="q-card__title mt-0.5">Misafir formu alanları</div>
          </div>
          <ul class="grid grid-cols-2 gap-y-1.5 text-[13px] text-slate-700">
            <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ad &amp; Soyad</li>
            <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Telefon</li>
            <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> E-posta</li>
            <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Kişi sayısı</li>
            <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Bebek koltuğu</li>
            <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> KVKK izni</li>
          </ul>
          <p class="mt-3 text-[11px] text-slate-500">Alanlar standarttır. Ek bilgi, sıra geldiğinde WhatsApp/e-posta ile sorulabilir.</p>
        </div>

        <div class="q-card p-5">
          <div class="mb-4">
            <div class="q-kicker">Diller</div>
            <div class="q-card__title mt-0.5">Form ve kapı ekranı dilleri</div>
          </div>
          <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-4">
            <?php foreach ($allKnownLang as $code):
              $chk = in_array($code, $languages, true);
            ?>
              <label class="flex items-center justify-center gap-2 p-2.5 rounded-xl border-2 <?php echo $chk ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200 bg-white'; ?> cursor-pointer font-bold uppercase text-sm">
                <input type="checkbox" data-qd-lang value="<?php echo $code; ?>" <?php echo $chk ? 'checked' : ''; ?> class="sr-only">
                <span><?php echo $code; ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div>
            <label class="q-field__label">Varsayılan dil</label>
            <select data-qd-text="default_language" class="q-input">
              <?php foreach ($allKnownLang as $code): ?>
                <option value="<?php echo $code; ?>" <?php echo $defaultLang === $code ? 'selected' : ''; ?>><?php echo strtoupper($code); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="q-card p-5">
          <div class="mb-4">
            <div class="q-kicker">Bildirimler</div>
            <div class="q-card__title mt-0.5">Sıra geldiğinde misafire</div>
            <p class="text-[11px] text-slate-500 mt-1">Meta WhatsApp Cloud API kullanılır.</p>
          </div>

          <?php
            // İşletmenin WhatsApp'ı kullanabilmesi için hem Meta API'nin global
            // olarak yapılandırılmış olması HEM DE süper adminin bu işletmeye
            // Meta iznini (customers.meta_whatsapp_enabled) açmış olması gerekir.
            // Ayrıca süper admin global template adını da (meta_queue_template_name)
            // girmelidir; aksi halde gönderim atlanır.
            $metaTemplateReady = $metaGlobalTemplate !== '';
            $waReady = $metaConfigured && $metaWhatsappAllowed && $metaTemplateReady;
          ?>

          <!-- Meta API / per-tenant permission status indicator -->
          <?php if (!$metaWhatsappAllowed): ?>
            <div class="mb-4 p-3 rounded-xl border bg-slate-50 border-slate-200">
              <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-slate-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0h.01M12 9v4m6.364-8.364a9 9 0 11-12.728 0 9 9 0 0112.728 0z"/></svg>
                <div>
                  <div class="text-sm font-extrabold text-slate-700">WhatsApp bildirimleri kapalı</div>
                  <div class="text-[11px] text-slate-600 mt-0.5">
                    Bu hesap için <b>Meta WhatsApp kullanım izni</b> süper admin tarafından etkinleştirilmemiş.
                    Açılması için Qordy destek ekibiyle iletişime geçin; sadece e-posta bildirimleri gönderilecek.
                  </div>
                </div>
              </div>
            </div>
          <?php elseif (!$metaConfigured): ?>
            <div class="mb-4 p-3 rounded-xl border bg-amber-50 border-amber-200">
              <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div>
                  <div class="text-sm font-extrabold text-amber-800">Meta API yapılandırılmamış</div>
                  <div class="text-[11px] text-amber-700 mt-0.5">
                    Meta global erişim bilgileri (access token / phone number ID) girilmemiş.
                    Qordy sistem yöneticisinin <code>qodmin/settings</code> → <b>Meta API</b> bölümünden bu bilgileri girmesi gerekiyor.
                  </div>
                </div>
              </div>
            </div>
          <?php elseif (!$metaTemplateReady): ?>
            <div class="mb-4 p-3 rounded-xl border bg-amber-50 border-amber-200">
              <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                <div>
                  <div class="text-sm font-extrabold text-amber-800">Meta şablonu tanımlı değil</div>
                  <div class="text-[11px] text-amber-700 mt-0.5">
                    Süper admin, sıra bildirimleri için onaylanmış bir Meta template adını henüz <code>qodmin/settings</code> → <b>Meta API</b> altına eklemedi. Template eklenene kadar WhatsApp gönderimi yapılamaz.
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="mb-4 p-3 rounded-xl border bg-emerald-50 border-emerald-200">
              <div class="flex items-start gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                  <div class="text-sm font-extrabold text-emerald-800">Meta WhatsApp hazır</div>
                  <div class="text-[11px] text-emerald-700 mt-0.5">
                    Qordy global Meta hesabı ve <b>onaylı template</b> (<code><?php echo htmlspecialchars($metaGlobalTemplate, ENT_QUOTES); ?></code>) kullanılarak müşterilere bildirim gönderilir. Template ayarını süper admin yönetir.
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="space-y-3">
            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-50 <?php echo $waReady ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'; ?>">
              <span class="flex items-center gap-2 text-sm font-bold text-slate-700">
                <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.966-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                WhatsApp bildirimi
                <?php if (!$metaWhatsappAllowed): ?>
                  <span class="ml-1 px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 text-[10px] font-black uppercase">Kapalı</span>
                <?php endif; ?>
              </span>
              <span class="q-toggle">
                <input type="checkbox"
                       data-q-toggle="whatsapp_enabled"
                       <?php echo (!empty($settings['whatsapp_enabled']) && $metaWhatsappAllowed) ? 'checked' : ''; ?>
                       <?php echo $metaWhatsappAllowed ? '' : 'disabled'; ?>>
                <span class="slider"></span>
              </span>
            </label>
            <p class="text-[11px] text-slate-500 -mt-1">
              Kullanılacak şablonu Qordy sistem yöneticisi belirler; bu ekranda ayrıca şablon adı girilmez.
            </p>

            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-50 cursor-pointer">
              <span class="flex items-center gap-2 text-sm font-bold text-slate-700">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                E-posta bildirimi
              </span>
              <span class="q-toggle"><input type="checkbox" data-q-toggle="email_enabled" <?php echo !empty($settings['email_enabled']) ? 'checked' : ''; ?>><span class="slider"></span></span>
            </label>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Global toast stack — all save/error confirmations render here. -->
<div class="qd-toasts" id="qdToasts" aria-live="polite" aria-atomic="true"></div>

<script>
(function(){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content
            || '<?php echo isset($csrf_token) ? htmlspecialchars($csrf_token, ENT_QUOTES) : ''; ?>';
  const PREVIEW_URL = <?php echo json_encode($displayUrl); ?>;
  const previewFrame = document.getElementById('previewFrame');
  const previewBusy  = document.getElementById('previewBusy');

  // Preview mode: "live" (follows server), "welcome" (force welcome), "queue" (force queue)
  let previewMode = 'live';

  // Toast stack — single, deduplicated, stays visible through scroll.
  const toastHost = document.getElementById('qdToasts');
  const SUCCESS_ICON = '<svg class="icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
  const ERROR_ICON   = '<svg class="icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.27 16A2 2 0 005 19z"/></svg>';
  let lastToastMsg = null;
  let lastToastAt  = 0;
  function showToast(kind, label, sublabel){
    if (!toastHost) return;
    const key = kind + '|' + label + '|' + (sublabel || '');
    const now = Date.now();
    // Dedupe identical toasts within 900ms (e.g. chained patches).
    if (key === lastToastMsg && (now - lastToastAt) < 900) return;
    lastToastMsg = key; lastToastAt = now;

    const el = document.createElement('div');
    el.className = 'qd-toast ' + (kind === 'error' ? 'error' : 'success');
    el.innerHTML = (kind === 'error' ? ERROR_ICON : SUCCESS_ICON)
      + '<div class="label">' + label + (sublabel ? '<div class="sublabel">' + sublabel + '</div>' : '') + '</div>';
    toastHost.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
      el.classList.remove('show');
      setTimeout(() => el.remove(), 220);
    }, kind === 'error' ? 4200 : 1800);
  }

  // Map patch-keys to human-readable labels so the toast tells the owner
  // WHAT was saved, not just "Kaydedildi".
  const KEY_LABELS = {
    is_enabled: 'Sistem durumu',
    is_accepting_queue: 'Sıra modu',
    show_logo: 'Logo görünümü',
    show_waiting_count: 'Bekleyen sayısı görünümü',
    show_estimated_wait: 'Tahmini süre görünümü',
    show_active_numbers: 'Sıra numaraları görünümü',
    show_powered_by: 'Alt bilgi görünümü',
    whatsapp_enabled: 'WhatsApp bildirimi',
    email_enabled: 'E-posta bildirimi',
    display_theme: 'Tema',
    display_theme_color: 'Tema rengi',
    display_accent_color: 'Vurgu rengi',
    display_bg_image_url: 'Arka plan görseli',
    welcome_title: 'Karşılama başlığı',
    welcome_subtitle: 'Karşılama alt başlık',
    welcome_tagline: 'Karşılama sloganı',
    welcome_hours: 'Çalışma saatleri',
    welcome_youtube_url: 'YouTube tanıtım bağlantısı',
    social_instagram: 'Instagram',
    social_facebook: 'Facebook',
    social_tiktok: 'TikTok',
    social_whatsapp: 'WhatsApp hattı',
    social_website: 'Web sitesi',
    social_menu_url: 'Menü linki',
    social_phone: 'Telefon',
    social_address: 'Adres',
    social_google_review: 'Google yorum linki',
    display_title: 'Sıra başlığı',
    display_subtitle: 'Sıra alt başlık',
    display_call_to_action: 'Sıra QR yazısı',
    average_wait_minutes: 'Ortalama bekleme',
    max_party_size: 'Maksimum grup',
    qr_token_ttl_seconds: 'QR token ömrü',
    auto_no_show_minutes: 'Otomatik gelmedi',
    entry_cooldown_minutes: 'Telefon soğuma',
    default_language: 'Varsayılan dil',
    languages: 'Aktif diller',
  };

  function labelForKey(key) { return KEY_LABELS[key] || key; }

  const ERROR_LABELS = {
    invalid_theme:     'Geçersiz tema',
    invalid_youtube_url: 'Geçerli bir YouTube bağlantısı girin (youtube.com veya youtu.be)',
    invalid_lang:      'Geçersiz dil kodu',
    key_not_allowed:   'Bu alan güncellenemez',
    csrf_invalid:      'Oturum zaman aşımına uğradı, sayfayı yenileyin',
    permission_denied: 'Bu işlem için yetkiniz yok',
  };
  function labelForError(err) { return ERROR_LABELS[err] || ('Kaydedilemedi (' + err + ')'); }

  // Debounced, batched iframe reload with a "Güncelleniyor" overlay so rapid
  // edits (color picker, typing) don't cause a flickering reload storm.
  let reloadTimer = null;
  function reloadPreview(delay){
    if (!previewFrame) return;
    if (delay === undefined) delay = 900;
    if (previewBusy) previewBusy.classList.add('show');
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(() => {
      const u = new URL(PREVIEW_URL, window.location.origin);
      u.searchParams.set('preview', '1');
      if (previewMode === 'welcome' || previewMode === 'queue') {
        u.searchParams.set('mode', previewMode);
      }
      // Two-level cache buster: random token plus timestamp. Some older CDN
      // configurations honour only specific query keys; using both (+ the
      // fact that the URL itself changes) forces a fresh fetch every time.
      u.searchParams.set('_', Date.now().toString());
      u.searchParams.set('r', Math.random().toString(36).slice(2, 10));
      const onLoad = () => { if (previewBusy) previewBusy.classList.remove('show'); previewFrame.removeEventListener('load', onLoad); };
      previewFrame.addEventListener('load', onLoad);
      previewFrame.src = u.toString();
      setTimeout(() => { if (previewBusy) previewBusy.classList.remove('show'); }, 4000);
    }, delay);
  }

  // The `_savedId` param is retained for backwards-compat (call-sites still
  // pass it) but we no longer display per-card badges. A single global toast
  // is surfaced instead so the confirmation is unmissable.
  async function patchSetting(body, _savedId, reloadDelay){
    try {
      const res = await fetch('/business/queue/settings/patch', {
        method: 'POST',
        headers: { 'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With':'XMLHttpRequest' },
        body: JSON.stringify({ ...body, csrf_token: csrf }),
        credentials: 'same-origin'
      });
      if (!res.ok && res.status === 401) {
        showToast('error', 'Oturum sona erdi', 'Sayfayı yenileyip tekrar giriş yapın.');
        return { success: false, error: 'unauthorized' };
      }
      const j = await res.json().catch(() => ({ success: false, error: 'bad_json' }));
      if (j && j.success) {
        showToast('success', labelForKey(body.key) + ' kaydedildi', 'Önizleme güncelleniyor…');
        reloadPreview(reloadDelay);
      } else {
        console.warn('[qordy] patch failed', body, j);
        showToast('error', labelForKey(body.key) + ' kaydedilemedi', labelForError((j && j.error) || 'unknown'));
      }
      return j;
    } catch(e) {
      console.error('[qordy] patch network error', e);
      showToast('error', 'Ağ hatası', 'Sunucuya ulaşılamadı, bağlantınızı kontrol edin.');
      return { success: false };
    }
  }

  // --- Tabs
  document.querySelectorAll('[data-q-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.qdTab;
      document.querySelectorAll('[data-q-tab]').forEach(b => b.classList.toggle('active', b === btn));
      document.querySelectorAll('.q-tab-panel').forEach(p => p.classList.add('hidden'));
      document.getElementById('q-tab-' + tab)?.classList.remove('hidden');
      try { history.replaceState(null, '', '#' + tab); } catch(_) {}
    });
  });
  const initial = (location.hash || '').replace('#','');
  if (['live','design','rules'].includes(initial)) {
    document.querySelector(`[data-q-tab="${initial}"]`)?.click();
  }

  // --- Design sub-tabs (Karşılama / Sıra / Tema)
  document.querySelectorAll('[data-qd-subtab]').forEach(btn => {
    btn.addEventListener('click', () => {
      const sub = btn.dataset.qdSubtab;
      document.querySelectorAll('[data-qd-subtab]').forEach(b => b.classList.toggle('active', b === btn));
      document.querySelectorAll('[data-qd-sub]').forEach(p => p.classList.toggle('hidden', p.dataset.qdSub !== sub));

      // Auto-match preview mode to the sub-tab the user is editing so what
      // they change is instantly what they see in the preview.
      if (sub === 'welcome' && previewMode !== 'welcome') setPreviewMode('welcome', true);
      if (sub === 'queue'   && previewMode !== 'queue')   setPreviewMode('queue',   true);
      // "brand" leaves preview mode unchanged (affects both).
    });
  });

  // --- Preview mode switcher
  function setPreviewMode(mode, skipReload){
    previewMode = mode;
    document.querySelectorAll('[data-pmode]').forEach(b => {
      const on = b.dataset.pmode === mode;
      b.classList.toggle('active', on);
      b.classList.toggle('bg-white', on);
      b.classList.toggle('shadow',   on);
      b.classList.toggle('text-slate-900', on);
      b.classList.toggle('text-slate-600', !on);
    });
    if (!skipReload) reloadPreview(50);
  }
  document.querySelectorAll('[data-pmode]').forEach(btn => {
    btn.addEventListener('click', () => setPreviewMode(btn.dataset.pmode));
  });

  // --- Themes
  // Tema sadece "Sıra · QR" modunu etkiler. Kullanıcı temaya tıkladığında
  // değişikliği görebilmesi için previewMode'ü otomatik 'queue' yapıyoruz.
  document.querySelectorAll('[data-qd-theme]').forEach(btn => {
    btn.addEventListener('click', async () => {
      // Optimistic UI: mark card active immediately and clear all "Aktif"
      // badges so the user gets instant feedback even before the server
      // responds.
      document.querySelectorAll('[data-qd-theme]').forEach(b => {
        b.classList.remove('active');
        const badge = b.querySelector('.px-1\\.5');
        if (badge) badge.remove();
      });
      btn.classList.add('active');
      const titleRow = btn.querySelector('.flex.items-center.justify-between');
      if (titleRow && !titleRow.querySelector('.px-1\\.5')) {
        const span = document.createElement('span');
        span.className = 'text-[9px] font-black text-white bg-indigo-500 px-1.5 py-0.5 rounded-full tracking-widest uppercase';
        span.textContent = 'Aktif';
        titleRow.appendChild(span);
      }
      if (previewMode !== 'queue') setPreviewMode('queue', true);
      // Short reload delay (120ms): theme changes are atomic and the owner
      // expects to see the swap immediately, not after the default 900ms
      // debounce window used by noisier inputs like typing.
      await patchSetting({ key: 'display_theme', value: btn.dataset.qdTheme }, null, 120);
    });
  });

  // --- Colors (debounce). Rengin etkisini görmek için önizlemeyi queue moduna al.
  document.querySelectorAll('[data-qd-color]').forEach(inp => {
    let t;
    inp.addEventListener('input', () => {
      clearTimeout(t);
      if (previewMode !== 'queue') setPreviewMode('queue', true);
      t = setTimeout(() => patchSetting({ key: inp.dataset.qdColor, value: inp.value }), 350);
    });
  });

  // --- Toggles
  document.querySelectorAll('[data-q-toggle]').forEach(inp => {
    inp.addEventListener('change', async () => {
      const key = inp.dataset.qdToggle;
      // Toggling "Masalar dolu · sıra al" switches the preview to the
      // corresponding mode so the owner sees the effect of the switch.
      if (key === 'is_accepting_queue') {
        setPreviewMode(inp.checked ? 'queue' : 'welcome', true);
      }
      await patchSetting({ key, value: inp.checked ? 1 : 0 });
    });
  });

  // --- Numeric rules (debounce)
  document.querySelectorAll('[data-qd-num]').forEach(inp => {
    let t;
    inp.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => patchSetting({ key: inp.dataset.qdNum, value: parseInt(inp.value || 0, 10) }), 500);
    });
  });

  // --- Text fields (debounce)
  document.querySelectorAll('[data-qd-text]').forEach(inp => {
    let t;
    const handler = () => {
      clearTimeout(t);
      t = setTimeout(() => patchSetting({ key: inp.dataset.qdText, value: inp.value }), 500);
    };
    inp.addEventListener('input', handler);
    inp.addEventListener('change', handler);
  });

  // --- Language chips
  const langChips = document.querySelectorAll('[data-qd-lang]');
  function syncLangChips(){
    langChips.forEach(inp => {
      const lbl = inp.closest('label');
      if (!lbl) return;
      if (inp.checked) { lbl.classList.add('border-indigo-400','bg-indigo-50'); lbl.classList.remove('border-slate-200','bg-white'); }
      else             { lbl.classList.remove('border-indigo-400','bg-indigo-50'); lbl.classList.add('border-slate-200','bg-white'); }
    });
  }
  langChips.forEach(inp => {
    inp.addEventListener('change', async () => {
      const codes = Array.from(langChips).filter(i => i.checked).map(i => i.value);
      syncLangChips();
      await patchSetting({ key: 'languages', value: codes.length ? codes : ['tr'] });
    });
  });
  syncLangChips();

  // --- Copy editor (multi-lingual copy with per-language tabs)
  const copyEditor = document.getElementById('copyEditor');
  const copyLang   = document.getElementById('copyLang');
  const copyTitle  = document.getElementById('copyTitle');
  const copySub    = document.getElementById('copySub');
  const copyCta    = document.getElementById('copyCta');
  const copyTabsEl = document.getElementById('copyLangTabs');
  const copyClone  = document.getElementById('copyCloneBtn');
  if (copyEditor && copyLang) {
    const titles = JSON.parse(copyEditor.dataset.titles || '{}');
    const subs   = JSON.parse(copyEditor.dataset.subs   || '{}');
    const ctas   = JSON.parse(copyEditor.dataset.ctas   || '{}');

    // Paint the "filled" dot next to each language tab.
    function paintTabStatus(){
      copyTabsEl?.querySelectorAll('.qd-copy-lang-tab').forEach(btn => {
        const l = btn.dataset.lang;
        const filled = !!(titles[l] || subs[l] || ctas[l]);
        const dot = btn.querySelector('.qd-copy-lang-dot');
        if (!dot) return;
        dot.classList.toggle('bg-emerald-500', filled);
        dot.classList.toggle('bg-slate-300',  !filled);
        dot.setAttribute('title', filled ? 'Bu dilde metin var' : 'Bu dil boş');
      });
    }

    function loadLang(l){
      copyLang.value  = l;
      copyTitle.value = titles[l] || '';
      copySub.value   = subs[l]   || '';
      copyCta.value   = ctas[l]   || '';
      copyTabsEl?.querySelectorAll('.qd-copy-lang-tab').forEach(btn => {
        const isActive = btn.dataset.lang === l;
        btn.classList.toggle('bg-white',    isActive);
        btn.classList.toggle('shadow',      isActive);
        btn.classList.toggle('text-slate-900', isActive);
        btn.classList.toggle('text-slate-600', !isActive);
      });
    }

    copyTabsEl?.querySelectorAll('.qd-copy-lang-tab').forEach(btn => {
      btn.addEventListener('click', () => loadLang(btn.dataset.lang));
    });

    function bind(inp, map, key){
      let t;
      inp.addEventListener('input', () => {
        clearTimeout(t);
        const l = copyLang.value;
        map[l] = inp.value;
        paintTabStatus();
        t = setTimeout(() => patchSetting({ key, value: inp.value, lang: l }), 600);
      });
    }
    bind(copyTitle, titles, 'display_title');
    bind(copySub,   subs,   'display_subtitle');
    bind(copyCta,   ctas,   'display_call_to_action');

    // "Copy to all languages": take the currently-visible strings and write
    // them into every language that is currently empty. Never overwrites an
    // existing translation without confirmation.
    copyClone?.addEventListener('click', async () => {
      const srcLang = copyLang.value;
      const activeLangs = Array.from(copyTabsEl.querySelectorAll('.qd-copy-lang-tab'))
        .map(b => b.dataset.lang).filter(l => l && l !== srcLang);
      if (!activeLangs.length) return;
      const anyFilled = activeLangs.some(l => titles[l] || subs[l] || ctas[l]);
      if (anyFilled && !confirm('Diğer dillerde mevcut metinler VAR. Üzerine yazmak istiyor musun?')) return;

      const patches = [];
      for (const l of activeLangs) {
        if (copyTitle.value) { titles[l] = copyTitle.value; patches.push({ key:'display_title',          value: copyTitle.value, lang: l }); }
        if (copySub.value)   { subs[l]   = copySub.value;   patches.push({ key:'display_subtitle',       value: copySub.value,   lang: l }); }
        if (copyCta.value)   { ctas[l]   = copyCta.value;   patches.push({ key:'display_call_to_action', value: copyCta.value,   lang: l }); }
      }
      for (const p of patches) await patchSetting(p);
      paintTabStatus();
    });

    paintTabStatus();
    loadLang(copyLang.value);
  }

  // --- Device toggle + responsive scaling
  // The iframe renders at a real device resolution; we compute the scale so
  // the whole frame fits the container width. Running this on every resize
  // keeps the preview sharp regardless of sidebar/layout changes.
  const DEVICES = {
    tv:     { w: 1600, h: 900,  ratio: '16/9' },
    tablet: { w: 1180, h: 820,  ratio: '1180/820' },
  };
  let currentDevice = 'tv';

  function applyPreviewScale(){
    const wrap = document.getElementById('previewWrap');
    if (!wrap || !previewFrame) return;
    const d = DEVICES[currentDevice] || DEVICES.tv;
    const containerW = wrap.clientWidth || 480;
    const scale = Math.min(1, containerW / d.w);
    previewFrame.style.width  = d.w + 'px';
    previewFrame.style.height = d.h + 'px';
    previewFrame.style.transform = 'scale(' + scale.toFixed(4) + ')';
    wrap.style.aspectRatio = d.ratio;
  }

  document.querySelectorAll('.qd-device').forEach(btn => {
    btn.addEventListener('click', () => {
      currentDevice = btn.dataset.device || 'tv';
      document.querySelectorAll('.qd-device').forEach(b => {
        const on = b === btn;
        b.classList.toggle('q-btn--primary', on);
        b.classList.toggle('q-btn--secondary', !on);
      });
      applyPreviewScale();
    });
  });

  // ResizeObserver keeps scale accurate when the admin window changes size.
  if (window.ResizeObserver && document.getElementById('previewWrap')) {
    new ResizeObserver(applyPreviewScale).observe(document.getElementById('previewWrap'));
  }
  window.addEventListener('resize', applyPreviewScale);
  applyPreviewScale();
  document.getElementById('previewReload')?.addEventListener('click', () => reloadPreview(50));

  // Initial preview-mode sync: show whichever sub-tab is active on load.
  // Critical: we must ALSO reload the iframe so the ?mode= query param is
  // actually applied — otherwise the iframe stays on the server's default
  // (live) state and the user sees the "wrong" mode while the UI highlights
  // another one.
  (function syncInitialPreview(){
    const activeSub = document.querySelector('[data-qd-subtab].active')?.dataset.qdSubtab;
    const server = <?php echo !empty($settings['is_accepting_queue']) ? "'queue'" : "'welcome'"; ?>;
    let target = 'live';
    if (activeSub === 'welcome' || activeSub === 'queue') target = activeSub;
    // If the chosen mode already matches the server state, stay on 'live' so
    // the preview polling auto-follows real state changes.
    if (target === server) target = 'live';
    if (target !== 'live') setPreviewMode(target); // triggers debounced reload with ?mode=
    else setPreviewMode('live', true);             // no reload needed
  })();

  // --- Queue actions (exposed globally so DataTable inline onclicks reach it)
  window.qdQueueAct = async function(id, action, extra){
    if (!id) return;
    if (action === 'cancel' && !confirm('Bu misafiri iptal etmek istediğinize emin misiniz?')) return;
    if (action === 'no-show' && !confirm('Bu misafir "Gelmedi" olarak işaretlensin mi? Sıradan çıkarılacak.')) return;
    const fd = new FormData(); fd.append('csrf_token', csrf);
    if (extra && typeof extra === 'object') {
      Object.entries(extra).forEach(([k,v]) => fd.append(k, String(v)));
    }
    try {
      const r = await fetch('/business/queue/' + id + '/' + action, {
        method: 'POST', body: fd,
        headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      const j = await r.json();
      if (j && j.success) location.reload();
      else alert('İşlem başarısız: ' + (j?.error || ''));
    } catch(e) { alert('Ağ hatası'); }
  };

  window.qdActiveEntries = <?php echo json_encode(array_map(static function ($e) {
      return [
          'id' => (int) ($e['id'] ?? 0),
          'name' => (string) (($e['name'] ?? '') . ' ' . ($e['surname'] ?? '')),
          'party' => (int) ($e['party_size'] ?? 0),
      ];
  }, $active), JSON_UNESCAPED_UNICODE); ?> || [];

  // --- Masa seçimli "Çağır" modalı
  window.qdCallGuest = async function(id){
    if (!id) return;
    const match = (window.qdActiveEntries || []).find(e => String(e.id) === String(id));
    const guestName = match ? (match.name || 'Misafir').trim() : 'Misafir';
    const partySize = match ? (match.party || 0) : 0;
    const existing = document.getElementById('qdCallModal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'qdCallModal';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;';
    overlay.innerHTML = `
      <div style="background:#fff;border-radius:18px;max-width:460px;width:100%;box-shadow:0 20px 60px -20px rgba(15,23,42,.45);overflow:hidden;font-family:inherit">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:10px;letter-spacing:.2em;text-transform:uppercase;color:#94a3b8;font-weight:800">Misafir çağır${partySize ? ' · '+partySize+' kişi' : ''}</div>
            <div style="font-weight:900;font-size:16px;color:#0f172a;margin-top:2px">${guestName}</div>
          </div>
          <button type="button" id="qdCallClose" style="background:#f1f5f9;border:0;border-radius:9999px;width:32px;height:32px;font-size:18px;cursor:pointer">×</button>
        </div>
        <div style="padding:16px 20px 4px 20px;">
          <label style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#475569">Masa seçin (boş masalar)</label>
          <div id="qdCallTables" style="margin-top:8px;max-height:260px;overflow:auto;display:flex;flex-direction:column;gap:6px;">
            <div style="padding:16px;text-align:center;color:#94a3b8;font-size:13px">Boş masalar yükleniyor...</div>
          </div>
          <p style="margin:10px 0 0;font-size:11px;color:#94a3b8;line-height:1.5">Seçilen masa bilgisi (ör. "Kat 1 · Masa 4") misafire bildirim metninde iletilir; kendi bilet ekranında da görünür.</p>
        </div>
        <div style="padding:14px 20px;border-top:1px solid #e2e8f0;display:flex;gap:8px;justify-content:space-between;align-items:center;background:#f8fafc;">
          <button type="button" id="qdCallSkip" style="padding:10px 14px;border-radius:10px;background:#fff;border:1px solid #e2e8f0;font-weight:700;font-size:13px;color:#475569;cursor:pointer">Masa belirtmeden çağır</button>
          <button type="button" id="qdCallSend" disabled style="padding:10px 16px;border-radius:10px;background:#2563eb;border:0;color:#fff;font-weight:800;font-size:13px;cursor:pointer;opacity:.5">Çağır ve bildirim gönder</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    const close = () => overlay.remove();
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.getElementById('qdCallClose').onclick = close;

    const tablesBox = document.getElementById('qdCallTables');
    let selectedId = '';
    let selectedLabel = '';
    const sendBtn = document.getElementById('qdCallSend');
    const skipBtn = document.getElementById('qdCallSkip');

    function renderTables(list){
      if (!list.length) {
        tablesBox.innerHTML = '<div style="padding:14px;text-align:center;color:#94a3b8;font-size:13px">Şu an boş masa yok. Yine de masa belirtmeden çağırabilirsiniz.</div>';
        return;
      }
      tablesBox.innerHTML = list.map(t => (
        `<label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px;cursor:pointer;background:#fff;font-size:14px;">
           <input type="radio" name="qdCallTbl" value="${t.table_id}" data-label="${(t.label || t.name).replace(/"/g,'&quot;')}" style="accent-color:#2563eb">
           <div style="flex:1;min-width:0">
             <div style="font-weight:800;color:#0f172a;line-height:1.2">${t.label || t.name}</div>
             <div style="font-size:11px;color:#64748b;margin-top:2px">Kapasite: ${t.capacity || '—'}</div>
           </div>
         </label>`
      )).join('');
      tablesBox.querySelectorAll('input[name=qdCallTbl]').forEach(inp => {
        inp.addEventListener('change', () => {
          selectedId = inp.value;
          selectedLabel = inp.getAttribute('data-label') || '';
          sendBtn.disabled = false;
          sendBtn.style.opacity = '1';
        });
      });
    }

    try {
      const r = await fetch('/api/business/queue/available-tables', { headers:{Accept:'application/json'}, credentials:'same-origin' });
      const j = await r.json();
      renderTables((j && j.tables) || []);
    } catch(e) {
      tablesBox.innerHTML = '<div style="padding:14px;text-align:center;color:#ef4444;font-size:13px">Masalar yüklenemedi</div>';
    }

    async function doCall(withTable){
      sendBtn.disabled = true; skipBtn.disabled = true;
      const extra = withTable && selectedId ? { table_id: selectedId } : {};
      await window.qdQueueAct(id, 'notify', extra);
    }
    sendBtn.onclick = () => doCall(true);
    skipBtn.onclick = () => doCall(false);
  };

  const qdDeleteCrmUrl = '<?php echo BASE_URL; ?>/business/queue/entries/delete';
  window.qdDeleteCrm = async function(id, includeActive) {
    if (!id) return;
    if (!confirm('Bu kayıt silinsin mi? Aktif sırada (bekleyen/çağrıldı) olan satırlar silinmez.')) return;
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('ids', String(id));
    if (includeActive) fd.append('include_active', '1');
    try {
      const r = await fetch(qdDeleteCrmUrl, { method: 'POST', body: fd, headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }, credentials: 'same-origin' });
      const j = await r.json().catch(() => ({}));
      if (j && j.success) { location.reload(); return; }
      alert(j?.message || j?.error || 'Silinemedi');
    } catch (e) { alert('Ağ hatası'); }
  };
  document.getElementById('qd-bulk-del')?.addEventListener('click', async function() {
    const raw = document.getElementById('qd-bulk-ids')?.value || '';
    const ids = raw.split(/[\s,;]+/).map(s => parseInt(s, 10)).filter(n => n > 0);
    if (ids.length < 1) { alert('En az geçerli bir ID girin.'); return; }
    if (!confirm(ids.length + ' kayıt silinecek. Onaylıyor musunuz? (Aktif sırada olanlar hariç tutulur.)')) return;
    const fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('ids', ids.join(','));
    try {
      const r = await fetch(qdDeleteCrmUrl, { method: 'POST', body: fd, headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }, credentials: 'same-origin' });
      const j = await r.json().catch(() => ({}));
      if (j && j.success) { location.reload(); return; }
      alert(j?.message || j?.error || 'Silinemedi');
    } catch (e) { alert('Ağ hatası'); }
  });

  // --- Copy URL
  document.getElementById('copyUrlBtn')?.addEventListener('click', async () => {
    const txt = document.getElementById('displayUrlText')?.textContent?.trim() || '';
    try {
      await navigator.clipboard.writeText(txt);
      const b = document.getElementById('copyUrlBtn');
      const o = b.textContent; b.textContent = 'Kopyalandı ✓';
      setTimeout(() => b.textContent = o, 1500);
    } catch(e) {}
  });

  // --- Rotate QR
  document.getElementById('rotateBtn')?.addEventListener('click', async () => {
    const b = document.getElementById('rotateBtn');
    b.disabled = true;
    const fd = new FormData(); fd.append('csrf_token', csrf);
    try {
      const r = await fetch('/business/queue/qr/rotate', { method:'POST', body: fd, headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }, credentials:'same-origin' });
      const j = await r.json();
      b.textContent = (j && j.success) ? 'QR yenilendi ✓' : 'Hata, tekrar deneyin';
      reloadPreview();
    } catch(e) { b.textContent = 'Hata'; }
    setTimeout(() => { b.textContent = "QR Token'ı yenile"; b.disabled = false; }, 2500);
  });

  // --- Poll active count
  setInterval(async () => {
    try {
      const r = await fetch('/api/business/queue/list', { headers:{Accept:'application/json'}, credentials:'same-origin' });
      const j = await r.json();
      if (j && j.success) document.getElementById('kpiActive').textContent = (j.active?.length ?? 0);
    } catch(e) {}
  }, 10000);
})();
</script>
