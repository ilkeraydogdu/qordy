<?php
require_once __DIR__ . '/../../../helpers/translations.php';
require_once __DIR__ . '/../../../helpers/url_helper.php';

$screens = $screens ?? [];
$baseUrl = BASE_URL;
$isSuperAdmin = $is_super_admin ?? false;
?>

<div class="q-page q-biz-theme q-prep-screens-page animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <?php if ($is_super_admin ?? false): ?>
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Mutfak</p>
                <h1 class="q-page-header__title">Hazırlık Ekranları</h1>
                <p class="q-page-header__subtitle">Hazırlık ekranlarını görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions q-field" style="min-width:16rem;margin:0;">
                <input type="text"
                       id="business-search"
                       placeholder="İşletme ara..."
                       onkeyup="BusinessSelector.searchBusinesses(this.value)"
                       class="q-input"/>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="col-span-full text-center py-12">
                <div class="q-spinner" style="margin:0 auto;"></div>
                <p class="q-hint mt-4">İşletmeler yükleniyor...</p>
            </div>
        </div>
    </div>

    <div id="preparation-screen-management-view" class="hidden q-stack q-stack--lg">
        <header class="q-page-header">
            <div class="q-toolbar" style="align-items:flex-start;">
                <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--sm" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div>
                    <p class="q-page-header__eyebrow" id="selected-business-name"></p>
                    <h1 class="q-page-header__title">Hazırlık Ekranları</h1>
                    <p class="q-page-header__subtitle"><?php echo t('preparation_screens.subtitle', 'Dinamik hazırlık ekranları yönetimi'); ?></p>
                </div>
            </div>
            <?php if (hasPermissionForRole('preparation-screens.create')): ?>
            <div class="q-page-header__actions">
                <a href="<?php echo getAdminUrl('preparation-screens/create'); ?>" class="q-btn q-btn--primary q-btn--sm">
                    <?php echo t('preparation_screens.new_screen', 'Yeni Ekran'); ?>
                </a>
            </div>
            <?php endif; ?>
        </header>
    <?php else: ?>
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Mutfak</p>
            <h1 class="q-page-header__title"><?php echo t('preparation_screens.title', 'Hazırlık Ekranları'); ?></h1>
            <p class="q-page-header__subtitle"><?php echo t('preparation_screens.subtitle', 'Dinamik hazırlık ekranları yönetimi'); ?></p>
        </div>
        <?php if (hasPermissionForRole('preparation-screens.create')): ?>
        <div class="q-page-header__actions">
            <a href="<?php echo getAdminUrl('preparation-screens/create'); ?>" class="q-btn q-btn--primary q-btn--sm">
                <?php echo t('preparation_screens.new_screen', 'Yeni Ekran'); ?>
            </a>
        </div>
        <?php endif; ?>
    </header>
    <?php endif; ?>

    <?php
    $renderScreensList = static function (array $screensList): void {
        if (empty($screensList)) {
            echo '<div class="q-prep-screens-empty"><p class="q-empty__title">' . htmlspecialchars(t('preparation_screens.no_screens', 'Henüz hazırlık ekranı oluşturulmamış.'), ENT_QUOTES) . '</p></div>';
            return;
        }
        echo '<div class="q-prep-screens-list">';
        foreach ($screensList as $screen) {
            include __DIR__ . '/_screen_card.php';
        }
        echo '</div>';
    };
    ?>

    <?php if ($is_super_admin ?? false): ?>
    <div id="screens-container">
        <?php $renderScreensList($screens); ?>
    </div>
    </div>
    <?php else: ?>
    <?php $renderScreensList($screens); ?>
    <?php endif; ?>
  </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

<?php if ($is_super_admin ?? false): ?>
const bsScript = document.createElement('script');
bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
bsScript.onload = function() {
    if (typeof BusinessSelector === 'undefined') return;

    BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
    BusinessSelector.loadBusinesses().then(function() {
        BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
            loadBusinessPreparationScreens(businessId, businessName);
        });
    });
};
document.head.appendChild(bsScript);

window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'preparation-screen-management-view');
    const container = document.getElementById('screens-container');
    if (container) container.innerHTML = '';
};

function loadBusinessPreparationScreens(businessId, businessName) {
    window.currentBusinessId = businessId;
    BusinessSelector.showContentView('business-selection-view', 'preparation-screen-management-view', businessName);
}
<?php endif; ?>

function toggleActive(screenId, toggleElement) {
    const isChecked = toggleElement.checked;
    const originalState = !isChecked;
    const row = toggleElement.closest('.q-prep-screen-row');
    const dot = row?.querySelector('.q-prep-screen-row__dot');

    toggleElement.disabled = true;

    fetch(`<?php echo getAdminUrl('preparation-screens/toggle-active'); ?>/${screenId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN || '',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        toggleElement.disabled = false;
        if (data.success) {
            if (dot) dot.classList.toggle('is-active', isChecked);
            if (typeof window.showToast === 'function') {
                window.showToast(<?php echo json_encode(t('notifications.success.updated', 'Güncellendi'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success');
            }
        } else {
            toggleElement.checked = originalState;
            window.NotificationManager.error(<?php echo json_encode(t('notifications.error.update_failed', 'Güncelleme başarısız oldu.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    })
    .catch(error => {
        toggleElement.disabled = false;
        toggleElement.checked = originalState;
        console.error('Error:', error);
        window.NotificationManager.error(<?php echo json_encode(t('common.error_occurred', 'Bir hata oluştu.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    });
}

async function deleteScreen(screenId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm(<?php echo json_encode(t('preparation_screens.delete_confirm', 'Bu hazırlık ekranını silmek istediğinize emin misiniz?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'Onay');
    } else {
        confirmed = confirm(<?php echo json_encode(t('preparation_screens.delete_confirm', 'Bu hazırlık ekranını silmek istediğinize emin misiniz?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    }
    if (!confirmed) return;

    fetch(`<?php echo getAdminUrl('preparation-screens/delete'); ?>/${screenId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN || '',
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            window.NotificationManager.error(<?php echo json_encode(t('preparation_screens.delete_failed', 'Silme işlemi başarısız oldu.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error(<?php echo json_encode(t('common.error_occurred', 'Bir hata oluştu.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    });
}
</script>
