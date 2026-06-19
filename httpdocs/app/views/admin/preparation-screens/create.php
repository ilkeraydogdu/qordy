<?php
require_once __DIR__ . '/../../../helpers/translations.php';
require_once __DIR__ . '/../../../helpers/url_helper.php';

$categories = $categories ?? [];
$baseUrl = BASE_URL;
$isEdit = false;
$screenCategoryIds = [];
?>

<div class="q-page q-biz-theme q-prep-screens-page animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Mutfak</p>
            <h1 class="q-page-header__title"><?php echo t('preparation_screens.create_title', 'Yeni Hazırlık Ekranı'); ?></h1>
            <p class="q-page-header__subtitle"><?php echo t('preparation_screens.create_subtitle', 'Yeni bir hazırlık ekranı oluştur'); ?></p>
        </div>
        <div class="q-page-header__actions">
            <a href="<?php echo getAdminUrl('preparation-screens'); ?>" class="q-btn q-btn--ghost q-btn--sm">
                <?php echo t('common.back', 'Geri Dön'); ?>
            </a>
        </div>
    </header>

    <div class="q-prep-form-shell">
        <form id="screenForm">
            <?php echo csrf_field(); ?>
            <?php include __DIR__ . '/_form_fields.php'; ?>
        </form>
    </div>
  </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function generateSlug(text) {
    const turkish = ['ş', 'Ş', 'ı', 'İ', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'];
    const english = ['s', 'S', 'i', 'I', 'g', 'G', 'u', 'U', 'o', 'O', 'c', 'C'];
    let slug = text;
    turkish.forEach((char, index) => {
        slug = slug.replace(new RegExp(char, 'g'), english[index]);
    });
    slug = slug.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return slug;
}

document.getElementById('screenForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const categoryIds = Array.from(formData.getAll('category_ids[]'));

    if (categoryIds.length === 0) {
        window.NotificationManager.warning(<?php echo json_encode(t('preparation_screens.categories_required', 'En az bir kategori seçilmelidir'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        return;
    }

    const name = formData.get('name');
    const data = {
        name: name,
        slug: generateSlug(name),
        production_point: formData.get('production_point') || null,
        is_active: formData.get('is_active') ? 1 : 0,
        category_ids: categoryIds
    };

    fetch(`<?php echo getAdminUrl('preparation-screens'); ?>`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN || '',
            'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = `<?php echo getAdminUrl('preparation-screens'); ?>`;
        } else {
            window.NotificationManager.error(<?php echo json_encode(t('preparation_screens.create_failed', 'Oluşturma işlemi başarısız oldu: '), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + (data.message || <?php echo json_encode(t('common.unknown_error', 'Bilinmeyen hata'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error(<?php echo json_encode(t('common.error_occurred', 'Bir hata oluştu.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    });
});
</script>
