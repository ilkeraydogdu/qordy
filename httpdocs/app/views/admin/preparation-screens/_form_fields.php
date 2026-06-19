<?php
/**
 * Shared minimal form fields for create/edit preparation screens.
 *
 * @var array<string, mixed>|null $screen
 * @var array<int, array<string, mixed>> $categories
 * @var array<int, string> $screenCategoryIds
 * @var bool $isEdit
 */
$screen = $screen ?? null;
$categories = $categories ?? [];
$screenCategoryIds = $screenCategoryIds ?? [];
$isEdit = !empty($isEdit);
$selectedCount = count($screenCategoryIds);
?>

<div class="q-prep-form q-stack q-stack--md">
    <?php if ($isEdit && !empty($screen['screen_id'])): ?>
    <input type="hidden" id="screen_id" value="<?php echo htmlspecialchars((string) $screen['screen_id']); ?>">
    <?php endif; ?>

    <div class="q-field">
        <label class="q-label" for="name"><?php echo t('preparation_screens.screen_name', 'Ekran Adı'); ?></label>
        <input type="text"
               id="name"
               name="name"
               required
               class="q-input"
               value="<?php echo htmlspecialchars((string) ($screen['name'] ?? ''), ENT_QUOTES); ?>"
               placeholder="<?php echo htmlspecialchars(t('preparation_screens.screen_name_placeholder', 'Örn: Çaycı, Cafe, Nargile')); ?>">
    </div>

    <div class="q-field">
        <div class="q-toolbar q-toolbar--between q-prep-form__section-head">
            <div>
                <label class="q-label" style="margin:0;"><?php echo t('preparation_screens.categories', 'Kategoriler'); ?></label>
                <p class="q-hint text-xs mt-0.5"><?php echo t('preparation_screens.categories_hint', 'Bu ekranda görüntülenecek siparişlerin kategorilerini seçin'); ?></p>
            </div>
            <span class="q-badge q-badge--info" id="prep-category-count"><?php echo $selectedCount; ?> <?php echo t('preparation_screens.selected', 'seçili'); ?></span>
        </div>

        <?php if (empty($categories)): ?>
            <div class="q-prep-form__empty">
                <?php echo t('preparation_screens.no_categories_available', 'Henüz kategori bulunmamaktadır. Önce menü kategorileri oluşturun.'); ?>
            </div>
        <?php else: ?>
            <input type="search"
                   id="prep-category-search"
                   class="q-input q-prep-form__search"
                   placeholder="<?php echo htmlspecialchars(t('preparation_screens.search_categories', 'Kategori ara...')); ?>"
                   autocomplete="off">
            <div class="q-prep-category-chips" id="prep-category-chips">
                <?php foreach ($categories as $category):
                    $categoryId = (string) ($category['category_id'] ?? '');
                    $categoryName = (string) ($category['name'] ?? '');
                    $isChecked = in_array($categoryId, $screenCategoryIds, true);
                ?>
                <label class="q-prep-category-chip<?php echo $isChecked ? ' is-selected' : ''; ?>"
                       data-name="<?php echo htmlspecialchars(mb_strtolower($categoryName), ENT_QUOTES); ?>">
                    <input type="checkbox"
                           name="category_ids[]"
                           value="<?php echo htmlspecialchars($categoryId, ENT_QUOTES); ?>"
                           <?php echo $isChecked ? 'checked' : ''; ?>
                           onchange="updatePrepCategoryCount()">
                    <span><?php echo htmlspecialchars($categoryName, ENT_QUOTES); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <input type="hidden" id="production_point" name="production_point" value="<?php echo htmlspecialchars((string) ($screen['production_point'] ?? ''), ENT_QUOTES); ?>">

    <?php if ($isEdit): ?>
    <details class="q-prep-form__details">
        <summary><?php echo t('preparation_screens.assigned_printers', 'Atanan Yazıcılar'); ?></summary>
        <div class="q-stack q-stack--sm q-prep-form__details-body">
            <p class="q-hint text-xs"><?php echo t('preparation_screens.printers_hint', 'Bu ekrana atanan yazıcılar, bu ekran için oluşturulan adisyon fişlerini yazdıracaktır.'); ?></p>
            <div id="assigned-printers-list" class="q-prep-form__printer-list">
                <div class="q-hint text-sm text-center py-3"><?php echo t('common.loading', 'Yükleniyor...'); ?></div>
            </div>
            <div class="q-toolbar" style="gap:var(--space-2);">
                <select id="printer-select" class="q-input flex-1">
                    <option value=""><?php echo t('preparation_screens.select_printer', 'Yazıcı Seçin...'); ?></option>
                </select>
                <button type="button" onclick="assignPrinter()" class="q-btn q-btn--secondary q-btn--sm">
                    <?php echo t('preparation_screens.assign_printer', 'Yazıcı Ata'); ?>
                </button>
            </div>
        </div>
    </details>
    <?php endif; ?>

    <label class="q-prep-form__active">
        <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo (!$isEdit || !empty($screen['is_active'])) ? 'checked' : ''; ?>>
        <span><?php echo t('common.active', 'Aktif'); ?></span>
    </label>

    <div class="q-prep-form__footer">
        <button type="submit" class="q-btn q-btn--primary">
            <?php echo $isEdit ? t('common.update', 'Güncelle') : t('common.create', 'Oluştur'); ?>
        </button>
        <a href="<?php echo getAdminUrl('preparation-screens'); ?>" class="q-btn q-btn--ghost">
            <?php echo t('common.cancel', 'İptal'); ?>
        </a>
    </div>
</div>

<script>
function updatePrepCategoryCount() {
    const checked = document.querySelectorAll('#prep-category-chips input[type="checkbox"]:checked').length;
    const badge = document.getElementById('prep-category-count');
    if (badge) {
        badge.textContent = checked + ' <?php echo addslashes(t('preparation_screens.selected', 'seçili')); ?>';
    }
    document.querySelectorAll('.q-prep-category-chip').forEach(chip => {
        const input = chip.querySelector('input[type="checkbox"]');
        chip.classList.toggle('is-selected', !!(input && input.checked));
    });
}

document.getElementById('prep-category-search')?.addEventListener('input', function() {
    const query = this.value.trim().toLowerCase();
    document.querySelectorAll('.q-prep-category-chip').forEach(chip => {
        const name = chip.dataset.name || '';
        chip.style.display = !query || name.includes(query) ? '' : 'none';
    });
});

updatePrepCategoryCount();
</script>
