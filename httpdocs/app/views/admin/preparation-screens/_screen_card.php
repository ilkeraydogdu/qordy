<?php
/**
 * Minimal preparation screen list row.
 *
 * @var array<string, mixed> $screen
 */
$screenId = htmlspecialchars((string) ($screen['screen_id'] ?? ''), ENT_QUOTES);
$slug = htmlspecialchars((string) ($screen['slug'] ?? ''), ENT_QUOTES);
$name = htmlspecialchars((string) ($screen['name'] ?? ''), ENT_QUOTES);
$isActive = !empty($screen['is_active']);
$categories = $screen['categories'] ?? [];
$categoryCount = count($categories);
$categoryNames = array_map(static fn($c) => (string) ($c['name'] ?? ''), $categories);
$previewNames = array_slice($categoryNames, 0, 3);
$remainingCount = max(0, $categoryCount - count($previewNames));
$categoryPreview = $categoryCount > 0
    ? implode(', ', $previewNames) . ($remainingCount > 0 ? ' +' . $remainingCount : '')
    : t('preparation_screens.no_categories', 'Kategori atanmamış');
?>
<article class="q-prep-screen-row">
    <div class="q-prep-screen-row__lead">
        <span class="q-prep-screen-row__dot<?php echo $isActive ? ' is-active' : ''; ?>" aria-hidden="true"></span>
        <div class="q-prep-screen-row__body">
            <div class="q-prep-screen-row__title"><?php echo $name; ?></div>
            <div class="q-prep-screen-row__meta-line">
                <span class="q-prep-screen-row__slug"><?php echo $slug; ?></span>
                <span class="q-prep-screen-row__sep">·</span>
                <span class="q-prep-screen-row__cats" title="<?php echo htmlspecialchars(implode(', ', $categoryNames), ENT_QUOTES); ?>">
                    <?php echo $categoryCount; ?> <?php echo t('preparation_screens.category_short', 'kategori'); ?>
                </span>
            </div>
            <?php if ($categoryCount > 0): ?>
            <p class="q-prep-screen-row__cats-preview"><?php echo htmlspecialchars($categoryPreview, ENT_QUOTES); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="q-prep-screen-row__actions">
        <?php if (hasPermissionForRole('preparation-screens.edit')): ?>
        <label class="q-prep-toggle" title="<?php echo t('preparation_screens.toggle_active', 'Ekran durumu'); ?>">
            <input type="checkbox"
                   <?php echo $isActive ? 'checked' : ''; ?>
                   onchange="toggleActive('<?php echo $screenId; ?>', this)"
                   aria-label="<?php echo t('preparation_screens.toggle_active', 'Ekran durumu'); ?>">
            <span class="q-prep-toggle__track"></span>
        </label>
        <a href="<?php echo getAdminUrl('preparation-screens/edit/' . $screenId); ?>"
           class="q-icon-btn"
           title="<?php echo t('common.edit', 'Düzenle'); ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
        </a>
        <?php elseif ($isActive): ?>
        <span class="q-badge q-badge--success q-badge--sm"><?php echo t('common.active', 'Aktif'); ?></span>
        <?php else: ?>
        <span class="q-badge q-badge--neutral q-badge--sm"><?php echo t('common.inactive', 'Pasif'); ?></span>
        <?php endif; ?>

        <?php if (hasPermissionForRole('preparation-screens.delete')): ?>
        <button type="button"
                onclick="deleteScreen('<?php echo $screenId; ?>')"
                class="q-icon-btn q-icon-btn--danger"
                title="<?php echo t('common.delete', 'Sil'); ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        </button>
        <?php endif; ?>

        <a href="<?php echo getAdminUrl('preparation-screens/' . $slug); ?>"
           target="_blank"
           rel="noopener"
           class="q-btn q-btn--ghost q-btn--sm q-prep-screen-row__open"
           title="<?php echo t('common.open', 'Aç'); ?>">
            <?php echo t('common.open', 'Aç'); ?>
        </a>
    </div>
</article>
