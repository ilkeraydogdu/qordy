<?php
/**
 * Categories Management View
 */

if (!function_exists('safeJsonEncodeForJs')) {
    require_once __DIR__ . '/../../helpers/json_helper.php';
}

// Ensure UI helpers are loaded
require_once __DIR__ . '/../../helpers/ui.php';
require_once __DIR__ . '/../waiter/pos_icons.php';

$categories = $categories ?? [];
$categoriesFlat = $categoriesFlat ?? $categories; // Flat list for dropdowns (parent selection)
$baseUrl = BASE_URL;
// Ensure getAdminUrl helper is available
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';

// Breadcrumbs
$breadcrumbs = $breadcrumbs ?? [
    ['label' => t('nav.home', 'Ana Sayfa'), 'url' => BASE_URL . '/admin'],
    ['label' => t('navigation.categories', 'Kategoriler'), 'url' => '']
];
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <?php if (!empty($breadcrumbs)): ?>
    <nav class="q-hint text-xs sm:text-sm overflow-x-auto mb-2">
        <ol class="q-toolbar min-w-max" style="gap:var(--space-2);">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <li class="q-toolbar" style="gap:var(--space-2);">
                    <?php if ($index > 0): ?><span>/</span><?php endif; ?>
                    <?php if (!empty($crumb['url']) && $index < count($breadcrumbs) - 1): ?>
                        <a href="<?php echo htmlspecialchars($crumb['url']); ?>" class="whitespace-nowrap"><?php echo htmlspecialchars($crumb['label']); ?></a>
                    <?php else: ?>
                        <span class="font-bold whitespace-nowrap" style="color:var(--color-text-primary);"><?php echo htmlspecialchars($crumb['label']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php endif; ?>

    <?php if ($is_super_admin ?? false): ?>
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Menü</p>
                <h1 class="q-page-header__title">Kategori Yönetimi</h1>
                <p class="q-page-header__subtitle">Kategorilerini görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions q-field" style="min-width:16rem;margin:0;">
                <input type="text" id="business-search" placeholder="İşletme ara..." onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input"/>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="col-span-full text-center py-12">
                <div class="q-spinner" style="margin:0 auto;"></div>
                <p class="q-hint mt-4">İşletmeler yükleniyor...</p>
            </div>
        </div>
    </div>

    <div id="category-management-view" class="hidden">
        <header class="q-page-header">
            <div class="q-toolbar" style="align-items:flex-start;">
                <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--sm" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div>
                    <p class="q-page-header__eyebrow" id="selected-business-name"></p>
                    <h1 class="q-page-header__title">Kategoriler</h1>
                    <p class="q-page-header__subtitle">İşletme kategorilerini yönetin</p>
                </div>
            </div>
            <?php if (hasPermissionForRole('menu.categories')): ?>
            <div class="q-page-header__actions">
                <button type="button" onclick="openModal()" class="q-btn q-btn--primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Yeni Kategori</span>
                </button>
            </div>
            <?php endif; ?>
        </header>

    <?php else: ?>
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Menü</p>
            <h1 class="q-page-header__title"><?php echo t('navigation.categories', 'Kategoriler'); ?></h1>
            <p class="q-page-header__subtitle">Menü kategorilerinizi buradan yönetebilirsiniz</p>
        </div>
        <?php if (hasPermissionForRole('menu.categories')): ?>
        <div class="q-page-header__actions">
            <button type="button" onclick="openModal()" class="q-btn q-btn--primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>Yeni Kategori</span>
            </button>
        </div>
        <?php endif; ?>
    </header>
    <?php endif; ?>

    <!-- Categories List - Modern Card Design -->
    <div class="q-grid q-grid--3" id="categories-container">
        <?php 
        // Organize categories by parent_id
        $parentCategories = [];
        $childCategories = [];
        foreach ($categories as $category) {
            if (empty($category['parent_id'])) {
                $parentCategories[] = $category;
            } else {
                if (!isset($childCategories[$category['parent_id']])) {
                    $childCategories[$category['parent_id']] = [];
                }
                $childCategories[$category['parent_id']][] = $category;
            }
        }
        
        if (empty($parentCategories)): ?>
            <div class="col-span-full q-card q-card--pad text-center" style="border:2px dashed var(--color-border-1);">
                <div class="q-empty__icon mx-auto mb-4">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--color-accent-primary);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                </div>
                <h3 class="font-bold mb-2">Henüz kategori yok</h3>
                <p class="q-hint text-sm mb-6">İlk kategorinizi oluşturmak için yukarıdaki butona tıklayın.</p>
                <?php if (hasPermissionForRole('menu.categories')): ?>
                <button onclick="openModal()" class="q-btn q-btn--primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    <span>İlk Kategoriyi Oluştur</span>
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($parentCategories as $parentCategory): 
                $hasChildren = !empty($childCategories[$parentCategory['category_id']]);
                $childrenCount = $hasChildren ? count($childCategories[$parentCategory['category_id']]) : 0;
 $parentVisual = resolveCategoryVisual($parentCategory);
            ?>
                <!-- Parent Category Card -->
                <div class="q-card q-card--pad overflow-hidden group">
                    <!-- Card Header -->
                    <div class="q-stack q-stack--sm">
                        <div class="q-toolbar q-toolbar--between" style="gap:var(--space-3);align-items:flex-start;">
                             <div class="w-14 h-14 rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center shrink-0 ring-1" style="border-color:var(--color-border-1);">
 <?php if ($parentVisual['type'] === 'image'): ?>
 <img src="<?php echo htmlspecialchars($parentVisual['image_url']); ?>" alt="" class="w-full h-full object-cover">
 <?php else: ?>
 <span class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0 ring-1 shadow-sm <?php echo $parentVisual['bg']; ?> <?php echo $parentVisual['ring']; ?>" title="<?php echo htmlspecialchars($parentVisual['label'] ?? $parentVisual['icon']); ?>">
 <span class="<?php echo $parentVisual['text']; ?> inline-flex items-center justify-center" style="width:28px;height:28px;">
 <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $parentVisual['svg']; ?></svg>
 </span>
 </span>
 <?php endif; ?>
 </div>
<div class="flex-1 min-w-0">
                                <h3 class="font-bold mb-2 line-clamp-2">
                                    <?php echo htmlspecialchars($parentCategory['name'] ?? ''); ?>
                                </h3>
                                <?php if (!empty($parentCategory['description'])): ?>
                                    <p class="q-hint text-sm line-clamp-2 mb-3"><?php echo htmlspecialchars($parentCategory['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (hasPermissionForRole('menu.categories')): ?>
                            <div class="q-toolbar q-toolbar--wrap" style="gap:var(--space-1);">
                                <button onclick="openModal('<?php echo htmlspecialchars($parentCategory['category_id'] ?? ''); ?>')" class="q-icon-btn" title="Düzenle" aria-label="Düzenle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button aria-label="Kategoriyi sil" onclick="deleteCategory('<?php echo htmlspecialchars($parentCategory['category_id'] ?? ''); ?>')" class="q-icon-btn" style="color:var(--color-status-danger);" title="Sil" aria-label="Sil">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Badges -->
                        <div class="flex flex-wrap items-center gap-2">
                            <?php if ($hasChildren): ?>
                                <span class="q-badge">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                    <?php echo $childrenCount; ?> alt kategori
                                </span>
                            <?php endif; ?>
                            <span class="q-badge <?php echo empty($parentCategory['requires_kitchen']) || !$parentCategory['requires_kitchen'] ? 'q-badge--success' : ''; ?>">
                                <?php if (empty($parentCategory['requires_kitchen']) || !$parentCategory['requires_kitchen']): ?>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Servis Yok
                                <?php else: ?>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Servis Gerekli
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Child Categories (if any) -->
                    <?php if ($hasChildren): ?>
                        <div class="px-6 pb-6 pt-0">
                            <div style="border-top:1px solid var(--color-border-1);padding-top:var(--space-4);">
                                <p class="q-label mb-3">Alt Kategoriler</p>
                                <div class="q-stack q-stack--sm">
                                    <?php foreach ($childCategories[$parentCategory['category_id']] as $childCategory): ?>
                                        <div class="q-card q-card--pad q-toolbar group/item" style="padding:var(--space-3);background:var(--color-surface-muted);">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h5 class="text-sm font-semibold truncate">
                                                        <?php echo htmlspecialchars($childCategory['name'] ?? ''); ?>
                                                    </h5>
                                                    <span class="q-badge <?php echo empty($childCategory['requires_kitchen']) || !$childCategory['requires_kitchen'] ? 'q-badge--success' : ''; ?>">
                                                        <?php echo empty($childCategory['requires_kitchen']) || !$childCategory['requires_kitchen'] ? 'Servis Yok' : 'Servis Var'; ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($childCategory['description'])): ?>
                                                    <p class="q-hint text-xs truncate"><?php echo htmlspecialchars($childCategory['description']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (hasPermissionForRole('menu.categories')): ?>
                                            <div class="flex gap-1 ml-3 shrink-0 opacity-0 group-hover/item:opacity-100 transition-opacity">
                                                <button onclick="openModal('<?php echo htmlspecialchars($childCategory['category_id'] ?? ''); ?>')" class="q-icon-btn" title="Düzenle" aria-label="Düzenle">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </button>
                                                <button aria-label="Kategoriyi sil" onclick="deleteCategory('<?php echo htmlspecialchars($childCategory['category_id'] ?? ''); ?>')" class="q-icon-btn" style="color:var(--color-status-danger);" title="Sil" aria-label="Sil">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="category-modal" class="q-modal-backdrop hidden">
        <div class="q-modal-backdrop__scrim" onclick="closeModal()"></div>
        <div class="q-modal q-modal--wide">
            <div class="q-modal__header">
                <h2 class="q-modal__title" id="modal-title">Yeni Kategori</h2>
                <button type="button" onclick="closeModal()" class="q-btn q-btn--ghost q-btn--icon" aria-label="Kapat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <form id="category-form" method="POST" class="q-modal__body q-stack q-stack--md" novalidate>
                    <input type="hidden" id="edit-id" name="id">
                    <?php echo csrf_field(); ?>
                    
                    <div>
                        <label class="q-label">Kategori Adı (Türkçe) <span class="q-text-status-danger">*</span></label>
                        <input aria-required="true" type="text" id="category-name" name="name" required onblur="autoTranslateCategoryName()"
                               class="q-input">
                    </div>
                    
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="q-label">Kategori Adı (İngilizce)</label>
                            <button type="button" onclick="translateCategoryName()" id="category-translate-btn" class="q-btn q-btn--primary q-btn--sm">
                                <svg id="category-translate-icon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                                </svg>
                                <span id="category-translate-text">Çevir</span>
                                <span id="category-translate-loading" class="hidden items-center gap-2">
                                    <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Çeviriliyor...
                                </span>
                            </button>
                        </div>
                        <input type="text" id="category-name-en" name="name_en"
                               class="q-input">
                    </div>
                    
                    <div>
                        <label class="q-label">Ana Kategori</label>
                        <select id="category-parent-id" name="parent_id"
                                class="q-input">
                            <option value="">Ana Kategori (Üst kategori yok)</option>
                            <?php foreach ($categoriesFlat as $cat): ?>
                                <?php if (empty($cat['parent_id'])): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category_id'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <p class="q-hint text-xs mt-1.5">Bu kategoriyi bir alt kategori yapmak için ana kategori seçin</p>
                    </div>
                    
                    <div>
                        <label class="q-label">Açıklama</label>
                        <textarea id="category-description" name="description" rows="3"
                                  class="q-input" style="resize:none;"></textarea>
                    </div>

                    <div class="space-y-3">
                        <label class="q-label">Kategori Görseli</label>
                        <p class="q-hint text-xs">Görsel URL veya hazır ikon seçebilirsiniz. Görsel URL varsa öncelikli gösterilir.</p>

                        <div class="q-tab-row--card">
                            <button type="button" id="category-visual-url-tab" class="q-tab selected flex-1">Görsel URL</button>
                            <button type="button" id="category-visual-icon-tab" class="q-tab flex-1">Hazır İkon</button>
                        </div>

                        <div id="category-visual-url-panel">
                            <input type="url" id="category-image-url" name="image_url"
                                   placeholder="https://..."
                                   class="q-input"/>
                        </div>

                        <div id="category-visual-icon-panel" class="hidden">
                            <input type="hidden" id="category-icon" name="icon" value="">
                            <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 max-h-72 overflow-y-auto p-1" id="category-icon-picker">
                                <?php foreach (posCategoryIconLibrary() as $iconKey => $iconMeta): ?>
                                <button type="button"
                                        class="category-icon-option flex flex-col items-center gap-1.5 p-2 rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/60 hover:shadow-sm transition-all bg-white"
                                        data-icon="<?php echo htmlspecialchars($iconKey); ?>"
                                        title="<?php echo htmlspecialchars($iconMeta['label']); ?>">
                                    <span class="w-10 h-10 rounded-xl flex items-center justify-center shadow-sm ring-1 <?php echo $iconMeta['bg']; ?> <?php echo $iconMeta['ring']; ?>">
                                        <span class="<?php echo $iconMeta['text']; ?> inline-flex items-center justify-center" style="width:22px;height:22px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $iconMeta['svg']; ?></svg>
                                        </span>
                                    </span>
                                    <span class="text-[10px] text-center leading-tight text-slate-600 font-medium"><?php echo htmlspecialchars($iconMeta['label']); ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="q-hint text-xs mt-2">Tasarıma uygun line-icon seti (Lucide uyumlu). İkon rengi tema ile uyumludur.</p>
                        </div>

                        <div id="category-visual-preview" class="hidden">
                            <div class="q-card q-card--pad flex items-center gap-3">
                                <div id="category-visual-preview-thumb" class="w-16 h-16 rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center shrink-0"></div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-700">Önizleme</p>
                                    <p id="category-visual-preview-label" class="text-xs text-slate-500 truncate"></p>
                                </div>
                                <button type="button" id="category-visual-clear" class="q-btn q-btn--ghost q-btn--sm ml-auto">Temizle</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="q-card q-card--pad q-toolbar" style="align-items:flex-start;background:var(--color-surface-muted);">
                        <input type="checkbox" id="category-requires-kitchen" name="requires_kitchen" value="1">
                        <label for="category-requires-kitchen" class="q-label flex-1" style="font-weight:400;">
                            <span class="font-semibold">Servise gerek yok</span>
                            <span class="block q-hint text-xs mt-0.5">Bu seçili ise hazırlık panellerine gönderilmeyecek</span>
                        </label>
                    </div>
                    
                    <div class="q-modal__footer q-toolbar">
                        <button type="button" onclick="closeModal()" class="q-btn q-btn--ghost">İptal</button>
                        <button type="submit" class="q-btn q-btn--primary">Kaydet</button>
                    </div>
                </form>
        </div>
    </div>
  </div>
</div>

<?php
// Load notification translations for NotificationManager
require_once __DIR__ . '/../../helpers/translations.php';
?>
<script>
// Notification translations for NotificationManager
window.notificationTranslations = window.notificationTranslations || {};
window.notificationTranslations['notifications.warning.category_has_items'] = <?php echo json_encode(t('notifications.warning.category_has_items', 'Bu kategoriye ait ürün bulunmakta. Kategori silinemez.')); ?>;
// Kategori ikon kütüphanesi (PHP -> JS) - font-tabanli SVG ikonlar (Lucide uyumlu)
window.POS_ICON_LIBRARY = <?php
$jsLib = [];
foreach (posCategoryIconLibrary() as $key => $meta) {
 $jsLib[$key] = [
 'label' => $meta['label'],
 'svg' => $meta['svg'],
 'gradient' => $meta['gradient'],
 'ring' => $meta['ring'],
 'text' => $meta['text'],
 'bg' => $meta['bg'],
 ];
}
echo json_encode($jsLib, JSON_UNESCAPED_UNICODE);
?>;

window.resolveCategoryIcon = function(category) {
 if (!category) return null;
 if (category.image_url) return { type: 'image', image_url: category.image_url };
 var key = (category.icon || '').trim();
 if (key && window.POS_ICON_LIBRARY[key]) {
 var m = window.POS_ICON_LIBRARY[key];
 return Object.assign({ type: 'icon', icon: key }, m);
 }
 return null;
};

window.renderCategoryIconHTML = function(category, size) {
 size = size || 26;
  var v = window.resolveCategoryIcon(category);
 if (!v) return '';
 if (v.type === 'image') {
 return '<div class="w-14 h-14 rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center shrink-0 ring-1" style="border-color:var(--color-border-1);"><img src="' + v.image_url.replace(/"/g, '&quot;') + '" alt="" class="w-full h-full object-cover"></div>';
 }
 return '<span class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0 ring-1 shadow-sm ' + v.bg + ' ' + v.ring + '" title="' + (v.label || v.icon) + '">'
  + '<span class="' + v.text + ' inline-flex items-center justify-center" style="width:28px;height:28px;">'
 + '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + v.svg + '</svg>'
 + '</span></span>';
};

const baseUrl = <?php echo json_encode($baseUrl); ?>;
const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
let editingCategoryId = null;

function openModal(id = null) {
    editingCategoryId = id;
    const modal = document.getElementById('category-modal');
    const form = document.getElementById('category-form');
    const title = document.getElementById('modal-title');
    
    if (!modal || !form) return;
    
    if (id) {
        title.textContent = 'Kategori Düzenle';
        form.action = `<?php echo getAdminUrl('categories/edit'); ?>/${id}`;
        form.method = 'POST';
        
        // Load category data from API
        fetch(`${baseUrl}/api/menu/category?id=${id}`)
            .then(response => response.json())
            .then(data => {
                // API returns category directly or wrapped in data
                const category = data.data || data;
                if (category && category.category_id) {
                    document.getElementById('edit-id').value = category.category_id || '';
                    document.getElementById('category-name').value = category.name || '';
                    document.getElementById('category-name-en').value = category.name_en || '';
                    document.getElementById('category-description').value = category.description || '';
                    const parentIdSelect = document.getElementById('category-parent-id');
                    if (parentIdSelect) {
                        parentIdSelect.value = category.parent_id || '';
                    }
                    // Checkbox: Servise gerek yok = requires_kitchen == 0 (ters mantık)
                    document.getElementById('category-requires-kitchen').checked = !category.requires_kitchen || category.requires_kitchen == 0;
                    setCategoryVisualFields(category.image_url || '', category.icon || '');
                }
            })
            .catch(error => {
                console.error('Error loading category:', error);
            });
    } else {
        title.textContent = 'Yeni Kategori';
        form.action = `<?php echo getAdminUrl('categories/add'); ?>`;
        form.reset();
        document.getElementById('edit-id').value = '';
        setCategoryVisualFields('', '');
        const parentIdSelect = document.getElementById('category-parent-id');
        if (parentIdSelect) {
            parentIdSelect.value = '';
        }
    }
    
    modal.classList.remove('hidden');
}

function closeModal() {
    const modal = document.getElementById('category-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
    editingCategoryId = null;
}

async function deleteCategory(id) {
    if (!window.NotificationManager) {
        console.error('NotificationManager not available');
        return;
    }
    
    if (!id || id.trim() === '') {
        console.error('Invalid category ID:', id);
        if (window.NotificationManager) {
            window.NotificationManager.error('Geçersiz kategori ID.');
        }
        return;
    }
    
    const confirmed = await window.NotificationManager.confirm('Bu kategoriyi silmek istediğinizden emin misiniz?', 'Kategori Silme');
    if (!confirmed) return;
    
    try {
        console.log('Deleting category:', id);
        const csrfToken = document.querySelector('input[name="_token"]')?.value || 
                         document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                         (window.CSRF_TOKEN || '');
        
        const response = await fetch(`${baseUrl}/api/categories/${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', Object.fromEntries(response.headers.entries()));
        
        // Get response text first
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        // Try to parse JSON response
        let result;
        try {
            if (responseText && responseText.trim() !== '') {
                result = JSON.parse(responseText);
            } else {
                // Empty response - check status
                if (response.ok || response.status === 200) {
                    result = { success: true, message: 'Kategori silindi.' };
                } else {
                    result = { success: false, error: 'Kategori silinirken bir hata oluştu.' };
                }
            }
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text was:', responseText);
            // If response is redirect or HTML, check status
            if (response.ok || response.status === 200 || response.status === 302) {
                result = { success: true, message: 'Kategori silindi.' };
            } else {
                result = { success: false, error: 'Sunucu yanıtı işlenemedi. Lütfen tekrar deneyin.' };
            }
        }
        
        console.log('Parsed result:', result);
        
        // Handle response based on result
        if (result && (result.success === true || result.success === 'success')) {
            const successMsg = result.message || 'Kategori silindi.';
            console.log('Success:', successMsg);
            if (window.NotificationManager) {
                window.NotificationManager.success(successMsg);
            }
            // Reload after a short delay to show the success message
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            // Error response
            let errorMsg = 'Kategori silinirken bir hata oluştu.';
            
            if (result) {
                if (result.error) {
                    errorMsg = typeof result.error === 'string' ? result.error : JSON.stringify(result.error);
                } else if (result.message) {
                    errorMsg = result.message;
                } else if (result.translation_key) {
                    // Try to translate using NotificationManager's getTranslation method
                    if (window.NotificationManager && window.NotificationManager.getTranslation) {
                        errorMsg = window.NotificationManager.getTranslation(result.translation_key, errorMsg);
                    } else if (window.notificationTranslations && window.notificationTranslations[result.translation_key]) {
                        // Fallback: use window.notificationTranslations if NotificationManager not available
                        errorMsg = window.notificationTranslations[result.translation_key];
                    }
                }
            }
            
            console.error('Delete failed:', result);
            if (window.NotificationManager) {
                window.NotificationManager.error(errorMsg);
            }
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        console.error('Error stack:', error.stack);
        if (window.NotificationManager) {
            window.NotificationManager.error('Kategori silinirken bir hata oluştu: ' + (error.message || 'Bilinmeyen hata'));
        }
    }
}

document.getElementById('category-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const id = document.getElementById('edit-id').value;
    
    const parentIdSelect = document.getElementById('category-parent-id');
    const data = {
        name: formData.get('name'),
        name_en: formData.get('name_en'),
        description: formData.get('description'),
        parent_id: parentIdSelect ? (parentIdSelect.value || null) : null,
        // Checkbox: Servise gerek yok = requires_kitchen == 0 (ters mantık)
        requires_kitchen: document.getElementById('category-requires-kitchen').checked ? 0 : 1,
        image_url: document.getElementById('category-image-url')?.value?.trim() || '',
        icon: document.getElementById('category-icon')?.value?.trim() || ''
    };
    
    const url = id ? `${baseUrl}${adminPrefix}/categories/edit/${id}` : `${baseUrl}${adminPrefix}/categories/add`;
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        // Check for errors first (error responses have success: false)
        if (result.error || result.success === false) {
            const errorMsg = typeof result.error === 'string' ? result.error : (result.message || 'İşlem başarısız oldu.');
            if (window.NotificationManager) {
                window.NotificationManager.error(errorMsg);
            }
            return;
        }
        
        // Success response
        if (result.success === true) {
            if (window.NotificationManager) {
                window.NotificationManager.success(result.message || 'İşlem başarılı!');
            }
            closeModal();
            location.reload();
        } else {
            // Unclear status
            if (window.NotificationManager) {
                window.NotificationManager.warning(result.message || 'Yanıt alındı ancak durum belirsiz.');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('Bir hata oluştu.');
        }
    }
});

function setCategoryVisualTab(tab) {
    const urlTab = document.getElementById('category-visual-url-tab');
    const iconTab = document.getElementById('category-visual-icon-tab');
    const urlPanel = document.getElementById('category-visual-url-panel');
    const iconPanel = document.getElementById('category-visual-icon-panel');
    if (!urlTab || !iconTab || !urlPanel || !iconPanel) return;

    const isUrl = tab === 'url';
    urlTab.classList.toggle('selected', isUrl);
    iconTab.classList.toggle('selected', !isUrl);
    urlPanel.classList.toggle('hidden', !isUrl);
    iconPanel.classList.toggle('hidden', isUrl);
}

function updateCategoryVisualPreview() {
    const preview = document.getElementById('category-visual-preview');
    const thumb = document.getElementById('category-visual-preview-thumb');
    const label = document.getElementById('category-visual-preview-label');
    const imageUrl = document.getElementById('category-image-url')?.value?.trim() || '';
    const icon = document.getElementById('category-icon')?.value?.trim() || '';

    if (!preview || !thumb || !label) return;

    if (!imageUrl && !icon) {
        preview.classList.add('hidden');
        return;
    }

    preview.classList.remove('hidden');

    if (imageUrl) {
        thumb.innerHTML = `<img src="${imageUrl.replace(/"/g, '&quot;')}" alt="" class="w-full h-full object-cover">`;
        label.textContent = 'Görsel URL';
        return;
    }

    const selectedBtn = document.querySelector(`.category-icon-option[data-icon="${icon}"]`);
    const emoji = selectedBtn?.querySelector('span')?.textContent?.trim() || '🍽️';
    const iconLabel = selectedBtn?.title || icon;
    thumb.innerHTML = `<span class="text-2xl">${emoji}</span>`;
    label.textContent = iconLabel;
}

function setCategoryVisualFields(imageUrl, icon) {
    const imageInput = document.getElementById('category-image-url');
    const iconInput = document.getElementById('category-icon');
    if (imageInput) imageInput.value = imageUrl || '';
    if (iconInput) iconInput.value = icon || '';

    document.querySelectorAll('.category-icon-option').forEach(btn => {
        btn.classList.toggle('border-indigo-400', btn.dataset.icon === icon);
        btn.classList.toggle('bg-indigo-50', btn.dataset.icon === icon);
    });

    setCategoryVisualTab(imageUrl ? 'url' : (icon ? 'icon' : 'url'));
    updateCategoryVisualPreview();
}

document.getElementById('category-visual-url-tab')?.addEventListener('click', () => setCategoryVisualTab('url'));
document.getElementById('category-visual-icon-tab')?.addEventListener('click', () => setCategoryVisualTab('icon'));
document.getElementById('category-image-url')?.addEventListener('input', updateCategoryVisualPreview);
document.getElementById('category-visual-clear')?.addEventListener('click', () => {
    setCategoryVisualFields('', '');
});
document.querySelectorAll('.category-icon-option').forEach(btn => {
    btn.addEventListener('click', () => {
        const icon = btn.dataset.icon || '';
        document.getElementById('category-icon').value = icon;
        document.getElementById('category-image-url').value = '';
        document.querySelectorAll('.category-icon-option').forEach(el => {
            el.classList.toggle('border-indigo-400', el.dataset.icon === icon);
            el.classList.toggle('bg-indigo-50', el.dataset.icon === icon);
        });
        updateCategoryVisualPreview();
    });
});

// Auto-translate category name to English (on blur)
async function autoTranslateCategoryName() {
    const turkishNameField = document.getElementById('category-name');
    const englishNameField = document.getElementById('category-name-en');
    
    if (!turkishNameField || !englishNameField) return;
    
    const turkishName = turkishNameField.value.trim();
    if (!turkishName) return;
    
    // If English field already has value, don't auto-translate
    if (englishNameField.value.trim()) {
        return;
    }
    
    try {
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || window.CSRF_TOKEN || '';
        
        const response = await fetch(`${baseUrl}${adminPrefix}/menu/translate-category-name`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                name: turkishName
            })
        });
        
        if (!response.ok) {
            // Silently fail - don't show error for auto-translate
            return;
        }
        
        const data = await response.json().catch(err => {
            return null;
        });
        
        if (data && data.success && data.english_name) {
            englishNameField.value = data.english_name;
        }
    } catch (error) {
        // Log error for debugging (auto-translate errors are logged but not shown to user)
        console.error('Error translating category name:', error);
    }
}

// Translate category name button handler
async function translateCategoryName() {
    const turkishNameField = document.getElementById('category-name');
    const englishNameField = document.getElementById('category-name-en');
    const btn = document.getElementById('category-translate-btn');
    const text = document.getElementById('category-translate-text');
    const icon = document.getElementById('category-translate-icon');
    const loading = document.getElementById('category-translate-loading');
    
    if (!turkishNameField || !englishNameField) return;
    
    const turkishName = turkishNameField.value.trim();
    if (!turkishName) {
        if (window.NotificationManager) {
            window.NotificationManager.warning('Lütfen önce Türkçe kategori adını girin.');
        }
        return;
    }
    
    // Disable button and show loading
    if (btn) {
        btn.disabled = true;
        if (text) text.classList.add('hidden');
        if (icon) icon.classList.add('hidden');
        if (loading) {
            loading.classList.remove('hidden');
            loading.classList.add('flex');
        }
    }
    
    try {
        // Get CSRF token
        let csrfToken = null;
        if (typeof window.CSRF_TOKEN !== 'undefined' && window.CSRF_TOKEN) {
            csrfToken = window.CSRF_TOKEN;
        }
        if (!csrfToken) {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag && metaTag.content) {
                csrfToken = metaTag.content;
            }
        }
        
        if (!csrfToken || csrfToken.trim() === '') {
            if (window.NotificationManager) {
                window.NotificationManager.error('Güvenlik hatası: CSRF token bulunamadı. Sayfayı yenileyin.');
            }
            if (btn) {
                btn.disabled = false;
                if (text) text.classList.remove('hidden');
                if (icon) icon.classList.remove('hidden');
                if (loading) {
                    loading.classList.add('hidden');
                    loading.classList.remove('flex');
                }
            }
            return;
        }
        
        const response = await fetch(`${baseUrl}${adminPrefix}/menu/translate-category-name`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                name: turkishName
            })
        });
        
        if (!response.ok) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Çeviri servisi şu anda kullanılamıyor. Lütfen İngilizce ismi manuel olarak girin.');
            }
            if (btn) {
                btn.disabled = false;
                if (text) text.classList.remove('hidden');
                if (icon) icon.classList.remove('hidden');
                if (loading) {
                    loading.classList.add('hidden');
                    loading.classList.remove('flex');
                }
            }
            return;
        }
        
        const data = await response.json().catch(err => {
            return null;
        });
        
        if (data && data.success && data.english_name) {
            englishNameField.value = data.english_name;
            if (window.NotificationManager) {
                window.NotificationManager.success('Çeviri başarılı!');
            }
        } else {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Çeviri yapılamadı. Lütfen İngilizce ismi manuel olarak girin.');
            }
        }
    } catch (error) {
        console.error('Error translating category name:', error);
        if (window.NotificationManager) {
            window.NotificationManager.warning('Çeviri sırasında bir hata oluştu. Lütfen İngilizce ismi manuel olarak girin.');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            if (text) text.classList.remove('hidden');
            if (icon) icon.classList.remove('hidden');
            if (loading) {
                loading.classList.add('hidden');
                loading.classList.remove('flex');
            }
        }
    }
}

<?php if ($is_super_admin ?? false): ?>
// Super Admin: Load BusinessSelector and handle business selection
(function() {
    // Remove old business selector logic
    const oldSelector = document.getElementById('business-selector');
    if (oldSelector) {
        oldSelector.remove();
    }
    
    // Load BusinessSelector script
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo time(); ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') {
            console.error('BusinessSelector not loaded');
            return;
        }
        
        // Initialize BusinessSelector
        BusinessSelector.init({
            baseUrl: baseUrl,
            containerSelector: '#business-grid'
        });
        
        // Check if business_id is in URL (page reload scenario)
        const urlParams = new URLSearchParams(window.location.search);
        const businessIdFromUrl = urlParams.get('business_id');
        
        if (businessIdFromUrl) {
            // Business ID in URL - load business info directly from API and show categories view
            const apiPrefix = <?php echo json_encode($isSuperAdmin ? '/api/qodmin' : '/api/business'); ?>;
            fetch(`${BusinessSelector.config.baseUrl}${apiPrefix}/businesses`)
                .then(response => response.json())
                .then(data => {
                    const businesses = Array.isArray(data) ? data : (data.businesses || data.data || []);
                    const business = businesses.find(b => 
                        (b.business_id || b.id) === businessIdFromUrl
                    );
                    
                    if (business) {
                        // Determine business name with improved fallback logic
                        let businessName = business.company_name || business.business_name || business.name;
                        if (!businessName || businessName.trim() === '') {
                            // Try owner name
                            const ownerName = business.owner_name || business.owner || '';
                            if (ownerName && ownerName.trim() !== '') {
                                businessName = ownerName;
                            } else {
                                // Try email
                                const email = business.email || business.business_email || '';
                                if (email && email.trim() !== '') {
                                    businessName = email.split('@')[0]; // Use email username part
                                } else {
                                    // Last resort: use generic name
                                    businessName = 'İşletme';
                                }
                            }
                        }
                        
                        // Set in session storage
                        sessionStorage.setItem('selected_business_id', businessIdFromUrl);
                        sessionStorage.setItem('selected_business_name', businessName);
                        window.currentBusinessId = businessIdFromUrl;
                        
                        // Load categories for this business
                        loadBusinessCategories(businessIdFromUrl, businessName);
                    } else {
                        console.error('Business not found:', businessIdFromUrl);
                    }
                })
                .catch(error => {
                    console.error('Error loading business info:', error);
                });
        } else {
            // No business_id in URL - show business selection
            BusinessSelector.loadBusinesses().then(businesses => {
                // Render business grid
                BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
                    // Set business ID in session storage
                    sessionStorage.setItem('selected_business_id', businessId);
                    sessionStorage.setItem('selected_business_name', businessName);
                    
                    // Update URL without page reload
                    const url = new URL(window.location.href);
                    url.searchParams.set('business_id', businessId);
                    window.history.pushState({ businessId, businessName }, '', url.toString());
                    
                    // Business selected - load categories for this business
                    loadBusinessCategories(businessId, businessName);
                });
            });
        }
    };
    document.head.appendChild(bsScript);
})();

// Global function to go back to business selection
window.backToBusinessSelection = function() {
    BusinessSelector.showSelectionView('business-selection-view', 'category-management-view');
    // Clear categories
    const container = document.getElementById('categories-container');
    if (container) {
        container.innerHTML = '';
    }
    
    // Remove business_id from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    // Clear session storage
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
};

// Load categories for selected business
function loadBusinessCategories(businessId, businessName) {
    // Show loading state
    const container = document.getElementById('categories-container');
    if (container) {
        container.innerHTML = '<div class="col-span-full text-center py-12"><div class="q-spinner q-spinner--lg" style="margin:0 auto;"></div><p class="q-hint mt-4">Kategoriler yükleniyor...</p></div>';
    }
    
    // Fetch categories for this business
    const apiPrefix = <?php echo json_encode($isSuperAdmin ? '/api/qodmin' : '/api/business'); ?>;
    fetch(`${baseUrl}${apiPrefix}/businesses/${businessId}/categories`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && Array.isArray(data.categories)) {
                // Store categories globally for form operations
                window.currentBusinessId = businessId;
                window.currentCategories = data.categories;
                
                // Render categories
                renderCategories(data.categories);
                
                // Show category management view
                BusinessSelector.showContentView('business-selection-view', 'category-management-view', businessName);
            } else {
                const errorMsg = data.error || data.message || 'Kategoriler yüklenirken hata oluştu';
                console.error('Failed to load categories:', data);
                window.NotificationManager.error(errorMsg);
            }
        })
        .catch(error => {
            console.error('Error loading categories:', error);
            const errorMsg = 'Kategoriler yüklenirken bir hata oluştu: ' + (error.message || error);
            window.NotificationManager.error(errorMsg);
        });
}

// Render categories dynamically
function renderCategories(categories) {
    const container = document.getElementById('categories-container');
    if (!container) return;
    
    // Organize by parent
    const parentCategories = categories.filter(c => !c.parent_id);
    const childCategories = {};
    categories.forEach(c => {
        if (c.parent_id) {
            if (!childCategories[c.parent_id]) childCategories[c.parent_id] = [];
            childCategories[c.parent_id].push(c);
        }
    });
    
    if (parentCategories.length === 0) {
        container.innerHTML = `
            <div class="col-span-full q-card q-card--pad text-center" style="border:2px dashed var(--color-border-1);">
                <h3 class="font-bold mb-2">Henüz kategori yok</h3>
                <p class="q-hint mb-6">İlk kategorinizi oluşturmak için yukarıdaki butona tıklayın.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    parentCategories.forEach(parent => {
        const children = childCategories[parent.category_id] || [];
        const requiresKitchen = parent.requires_kitchen && parent.requires_kitchen != 0;
        
        html += `<div class="q-card q-card--pad">`;
        html += `<div class="p-5 flex items-start justify-between" style="gap:var(--space-3);">`;
        html += window.renderCategoryIconHTML(parent, 26);
        html += `<div class="flex-1 min-w-0">`;
        html += `<div class="flex items-center gap-3 mb-1 flex-wrap">`;
        html += `<h3 class="font-bold">${escapeHtml(parent.name || '')}</h3>`;
        if (children.length) {
            html += `<span class="q-badge">${children.length} alt kategori</span>`;
        }
        html += `<span class="q-badge ${requiresKitchen ? '' : 'q-badge--success'}">${requiresKitchen ? 'Servis Gerekli' : 'Servis Yok'}</span>`;
        html += `</div>`;
        if (parent.description) {
            html += `<p class="q-hint text-sm mt-1">${escapeHtml(parent.description)}</p>`;
        }
        html += `</div>`;
        html += `<div class="flex gap-2 ml-4 shrink-0">`;
        html += `<button type="button" onclick="openModal('${parent.category_id || ''}')" class="q-icon-btn" title="Düzenle" aria-label="Düzenle">`;
        html += `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>`;
        html += `</button>`;
        html += `<button type="button" aria-label="Kategoriyi sil" onclick="deleteCategory('${parent.category_id || ''}')" class="q-icon-btn" style="color:var(--color-status-danger);" title="Sil" aria-label="Sil">`;
        html += `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>`;
        html += `</button>`;
        html += `</div>`;
        html += `</div>`;
        
        if (children.length) {
            html += `<div class="px-5 pb-5 pt-0">`;
            html += `<div style="border-top:1px solid var(--color-border-1);padding-top:var(--space-4);">`;
            html += `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">`;
            children.forEach(child => {
                const childRequiresKitchen = child.requires_kitchen && child.requires_kitchen != 0;
                html += `<div class="q-card q-card--pad q-toolbar" style="padding:var(--space-3);background:var(--color-surface-muted);gap:var(--space-2);align-items:center;">`;
                // Alt kategori ikonu (küçük boyut)
                html += window.renderCategoryIconHTML(child, 18).replace(/w-14 h-14/g, 'w-9 h-9').replace(/width:28px;height:28px/g, 'width:18px;height:18px');
                html += `<div class="flex-1 min-w-0">`;
                html += `<div class="flex items-center gap-2 mb-1">`;
                html += `<h5 class="text-sm font-semibold truncate">${escapeHtml(child.name || '')}</h5>`;
                html += `<span class="q-badge ${childRequiresKitchen ? '' : 'q-badge--success'}">${childRequiresKitchen ? 'Servis Var' : 'Servis Yok'}</span>`;
                html += `</div>`;
                if (child.description) {
                    html += `<p class="q-hint text-xs truncate">${escapeHtml(child.description)}</p>`;
                }
                html += `</div>`;
                html += `<div class="flex gap-1 ml-3 shrink-0">`;
                html += `<button type="button" onclick="openModal('${child.category_id || ''}')" class="q-icon-btn" title="Düzenle" aria-label="Düzenle">`;
                html += `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>`;
                html += `</button>`;
                html += `<button type="button" aria-label="Kategoriyi sil" onclick="deleteCategory('${child.category_id || ''}')" class="q-icon-btn" style="color:var(--color-status-danger);" title="Sil" aria-label="Sil">`;
                html += `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>`;
                html += `</button>`;
                html += `</div>`;
                html += `</div>`;
            });
            html += `</div>`;
            html += `</div>`;
            html += `</div>`;
        }
        html += `</div>`;
    });
    
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
<?php else: ?>
// Handle business selection for regular business owner (if needed)
document.addEventListener('DOMContentLoaded', function() {
    const businessSelector = document.getElementById('business-selector');
    if (businessSelector) {
        businessSelector.addEventListener('change', function() {
            const selectedBusinessId = this.value;
            if (selectedBusinessId) {
                window.location.href = `${baseUrl}${adminPrefix}/categories?business_id=${selectedBusinessId}`;
            } else {
                window.location.href = `${baseUrl}${adminPrefix}/categories`;
            }
        });
    }
});
<?php endif; ?>

</script>

