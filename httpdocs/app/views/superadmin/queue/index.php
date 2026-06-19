<?php
/**
 * Super admin: business picker for queue — Warm Ember Ops (.q-*)
 */
require_once __DIR__ . '/../../../helpers/translations.php';

if (!isset($title)) {
    $title = 'Sıra Yönetimi - ' . getAppConfig()->getAppName();
}
$page = $page ?? 'queue';

$bsPath = $_SERVER['DOCUMENT_ROOT'] . '/assets/js/business-selector.js';
$bsVer = is_file($bsPath) ? filemtime($bsPath) : time();
?>
<div class="q-page animate-slide-up">
  <div class="q-container" id="queue-business-selection">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Operasyon</p>
        <h1 class="q-page-header__title">Sıra Yönetimi</h1>
        <p class="q-page-header__subtitle">Sıra durumunu görüntülemek istediğiniz işletmeyi seçin</p>
      </div>
      <div class="q-page-header__actions">
        <input type="search" id="queue-business-search" placeholder="İşletme ara..." onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input" style="min-width:220px;" aria-label="İşletme ara">
      </div>
    </header>

    <div id="business-grid" class="q-grid q-grid--4" aria-live="polite">
      <div style="grid-column:1/-1;text-align:center;padding:var(--space-12);">
        <div style="display:inline-block;width:48px;height:48px;border:3px solid var(--color-border-1);border-top-color:var(--color-brand-accent);border-radius:50%;animation:spin 1s linear infinite;" role="status" aria-label="Yükleniyor"></div>
        <p class="q-hint" style="margin-top:var(--space-4);">İşletmeler yükleniyor...</p>
      </div>
    </div>

  </div>
</div>

<script>
(function() {
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo $bsVer; ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') return;
        const baseUrl = <?php echo json_encode(BASE_URL, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        BusinessSelector.init({ baseUrl: baseUrl });
        BusinessSelector.loadBusinesses().then(function() {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId) {
                window.location.href = baseUrl + '/qodmin/queue/' + encodeURIComponent(businessId);
            });
        });
    };
    document.head.appendChild(bsScript);
})();
</script>
