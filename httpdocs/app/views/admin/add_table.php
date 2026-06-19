<?php
$title = 'Yeni Masa Ekle - ' . getAppConfig()->getAppName();
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$adminPrefix = ($is_super_admin ?? false) ? '/qodmin' : '/business';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Masalar</p>
        <h1 class="q-page-header__title">Yeni Masa Ekle</h1>
        <p class="q-page-header__subtitle">Salon, teras veya bölgeye yeni masa tanımlayın</p>
      </div>
      <div class="q-page-header__actions">
        <a href="<?php echo BASE_URL . $adminPrefix; ?>/tables" class="q-btn q-btn--ghost q-btn--sm">← Masalara dön</a>
      </div>
    </header>

    <div class="q-card q-card--pad max-w-2xl w-full mx-auto min-w-0">
      <form method="POST" action="<?php echo BASE_URL . $adminPrefix; ?>/tables/add" class="q-stack q-stack--lg">
        <?php echo csrf_field(); ?>

        <div class="q-stack q-stack--sm">
          <label class="q-label" for="table-name">Masa adı</label>
          <input type="text" id="table-name" name="name" required class="q-input" placeholder="Örn: Masa 12" autocomplete="off">
        </div>

        <div class="q-stack q-stack--sm">
          <label class="q-label" for="table-zone">Bölge</label>
          <select id="table-zone" name="zone" required class="q-input">
            <option value="Salon">Salon</option>
            <option value="Teras">Teras</option>
            <option value="Bahçe">Bahçe</option>
            <option value="VIP">VIP</option>
          </select>
        </div>

        <div class="q-stack q-stack--sm">
          <label class="q-label" for="table-capacity">Kapasite</label>
          <input type="number" id="table-capacity" name="capacity" value="4" min="1" required class="q-input">
        </div>

        <div class="q-toolbar q-toolbar--between flex-wrap gap-3 pt-2">
          <a href="<?php echo BASE_URL . $adminPrefix; ?>/tables" class="q-btn q-btn--ghost flex-1 sm:flex-none min-w-0">İptal</a>
          <button type="submit" class="q-btn q-btn--primary flex-1 sm:flex-none min-w-0">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>
