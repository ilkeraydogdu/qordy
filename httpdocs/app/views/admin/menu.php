<?php
/**
 * Menu Management View - React MenuModule component'inin PHP versiyonu
 * Birebir aynı tasarım
 */

if (!function_exists('safeJsonEncodeForJs')) {
    require_once __DIR__ . '/../../helpers/json_helper.php';
}

// Ensure UI helpers are loaded
require_once __DIR__ . '/../../helpers/ui.php';


$menu_items = $menu_items ?? [];
$categories = $categories ?? [];
$preparation_screens = $preparation_screens ?? [];
$baseUrl = BASE_URL;
// Ensure getAdminUrl helper is available
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';

// HelperLoader already loaded translations.php
// DependencyFactory is loaded by Controller

// Get supported languages from settings
$settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
$supportedLanguagesJson = $settingsService->getSetting('supported_languages', '["tr","en"]');
$supportedLanguages = json_decode($supportedLanguagesJson, true);
if (!is_array($supportedLanguages) || empty($supportedLanguages)) {
    $supportedLanguages = ['tr', 'en'];
}
$defaultLanguage = $settingsService->getSetting('default_language', getAppConfig()->getDefaultLanguage());

// Breadcrumbs
$breadcrumbs = $breadcrumbs ?? [
    ['label' => t('nav.home', 'Ana Sayfa'), 'url' => BASE_URL . '/admin'],
    ['label' => t('menu.management', 'Menu Management'), 'url' => '']
];
?>

<div class="q-page q-biz-theme q-menu-page animate-slide-up">
  <div class="q-container q-stack q-stack--lg">

    <?php if ($is_super_admin ?? false): ?>
    <!-- SUPER ADMIN VIEW: Business Selection First -->
    
    <!-- Business Selection View -->
    <div id="business-selection-view">
        <header class="q-page-header">
            <div>
                <p class="q-page-header__eyebrow">Menü</p>
                <h1 class="q-page-header__title">Menü Yönetimi</h1>
                <p class="q-page-header__subtitle">Menülerini görüntülemek istediğiniz işletmeyi seçin</p>
            </div>
            <div class="q-page-header__actions q-field" style="min-width:16rem;margin:0;">
                <input type="text" id="business-search" placeholder="İşletme ara..." onkeyup="BusinessSelector.searchBusinesses(this.value)" class="q-input"/>
            </div>
        </header>
        <div id="business-grid" class="q-grid q-grid--4">
            <div class="col-span-full text-center py-16">
                <div class="q-spinner" style="margin:0 auto;"></div>
                <p class="q-hint mt-4">Yükleniyor...</p>
            </div>
        </div>
    </div>
    
    <!-- Menu Management View (shown after business selection) -->
    <div id="menu-management-view" class="hidden">
        <header class="q-page-header">
            <div class="q-toolbar" style="align-items:flex-start;">
                <button type="button" onclick="backToBusinessSelection()" class="q-btn q-btn--ghost q-btn--sm" aria-label="Geri">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <div>
                    <p class="q-page-header__eyebrow" id="selected-business-name"></p>
                    <h1 class="q-page-header__title">Menü Yönetimi</h1>
                    <p class="q-page-header__subtitle">Menü yönetimi</p>
                </div>
            </div>
            <?php if (hasPermissionForRole('menu.create')): ?>
            <div class="q-page-header__actions">
                <button id="btn-extract-menu" type="button" class="q-btn q-btn--ghost q-btn--sm">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Fotoğraftan Yükle</span>
                </button>
                <button id="btn-new-item" type="button" class="q-btn q-btn--primary q-btn--sm">
                    <?php echo icon_plus(['class' => 'w-4.5 h-4.5']); ?>
                    <span><?php echo t('menu.newItem'); ?></span>
                </button>
            </div>
            <?php endif; ?>
        </header>

    <?php else: ?>
    <!-- REGULAR BUSINESS OWNER VIEW -->
    <header class="q-page-header">
        <div>
            <h1 class="q-page-header__title"><?php echo t('menu.management'); ?></h1>
            <p class="q-page-header__subtitle">Ürünlerinizi yönetin ve düzenleyin</p>
        </div>
        <?php if (hasPermissionForRole('menu.create')): ?>
        <div class="q-page-header__actions">
            <button id="btn-extract-menu" type="button" class="q-btn q-btn--soft q-btn--sm">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Fotoğraftan Yükle</span>
                </button>
                <button id="btn-new-item" type="button" class="q-btn q-btn--primary q-btn--sm">
                    <?php echo icon_plus(['class' => 'w-4.5 h-4.5']); ?>
                    <span><?php echo t('menu.newItem'); ?></span>
                </button>
        </div>
        <?php endif; ?>
    </header>
    <?php endif; ?>

    <!-- Search and Filter Bar -->
    <div class="q-card q-card--pad q-menu-filter-card q-stack q-stack--md">
        <div class="q-input-icon-wrap q-menu-search">
                <svg class="q-input-icon-wrap__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" id="search-input" placeholder="Ürün ara..." class="q-input"/>
        </div>
        <div class="q-menu-filter-grid">
            <!-- Category Filter -->
            <div class="q-menu-filter-field">
                <select id="filter-category"
                        class="q-input">
                    <option value="">Tüm Kategoriler</option>
                    <?php 
                    $parentCategories = [];
                    $childCategories = [];
                    foreach ($categories as $cat) {
                        if (empty($cat['parent_id'])) {
                            $parentCategories[] = $cat;
                        } else {
                            $parentId = $cat['parent_id'];
                            if (!isset($childCategories[$parentId])) {
                                $childCategories[$parentId] = [];
                            }
                            $childCategories[$parentId][] = $cat;
                        }
                    }
                    
                    foreach ($parentCategories as $parent): 
                        $parentId = $parent['category_id'] ?? '';
                        $hasChildren = isset($childCategories[$parentId]) && !empty($childCategories[$parentId]);
                    ?>
                        <?php if ($hasChildren): ?>
                            <optgroup label="<?php echo htmlspecialchars($parent['name'] ?? ''); ?>">
                                <option value="<?php echo htmlspecialchars($parentId); ?>" data-is-parent="1" data-parent-id="<?php echo htmlspecialchars($parentId); ?>">
                                    <?php echo htmlspecialchars($parent['name'] ?? ''); ?> (Tümü)
                                </option>
                                <?php foreach ($childCategories[$parentId] as $child): ?>
                                    <option value="<?php echo htmlspecialchars($child['category_id'] ?? ''); ?>" data-is-parent="0" data-parent-id="<?php echo htmlspecialchars($parentId); ?>">
                                        &nbsp;&nbsp;<?php echo htmlspecialchars($child['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php else: ?>
                            <option value="<?php echo htmlspecialchars($parentId); ?>" data-is-parent="1" data-parent-id="<?php echo htmlspecialchars($parentId); ?>">
                                <?php echo htmlspecialchars($parent['name'] ?? ''); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="q-menu-filter-field">
                <select id="filter-status"
                        class="q-input">
                    <option value="all">Tüm Durumlar</option>
                    <option value="available">Mevcut</option>
                    <option value="unavailable">Tükenen</option>
                </select>
            </div>

            <!-- Stock Filter -->
            <div class="q-menu-filter-field">
                <select id="filter-stock"
                        class="q-input">
                    <option value="all">Tüm Stoklar</option>
                    <option value="in_stock">Stokta Var</option>
                    <option value="out_of_stock">Stokta Yok</option>
                </select>
            </div>

            <!-- Clear Filters -->
            <button id="btn-clear-filters"
                    class="q-btn q-btn--ghost q-btn--sm q-menu-filter-clear">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Temizle
            </button>
        </div>

        <div class="q-toolbar">
            <div id="active-filters" class="flex flex-wrap gap-2 hidden">
                <!-- Active filter tags will be added here -->
            </div>
            <div id="results-count" class="q-hint text-sm">
                <span class="font-medium"><?php echo count($menu_items); ?></span> ürün
            </div>
        </div>
    </div>

    <!-- Menu items (responsive list — all breakpoints) -->
    <div class="q-menu-items-list" id="menu-items-list">
        <!-- Items rendered by menu.js -->
    </div>

    <!-- Pagination -->
    <div id="pagination-container" class="q-card q-card--pad flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="q-hint text-sm">Sayfa başına</span>
            <div class="relative">
                <select id="items-per-page"
                        class="q-input" style="width:auto;padding-right:2rem;">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-1 q-pagination" id="pagination-controls">
            <!-- Pagination buttons will be generated here -->
        </div>

        <div class="q-hint text-sm" id="pagination-info">
            <!-- Page info will be shown here -->
        </div>
    </div>

    <!-- Modal -->
    <div id="menu-modal" class="q-modal-backdrop q-menu-modal hidden">
        <div class="q-modal-backdrop__scrim" id="modal-backdrop"></div>
        <div class="q-modal q-modal--wide q-menu-modal__panel">
            <div class="q-menu-modal__header">
                <div class="q-modal__header">
                    <h2 class="q-modal__title">
                        <span id="modal-title"><?php echo t('menu.addNew'); ?></span>
                    </h2>
                    <button id="btn-modal-close-x" type="button" class="q-btn q-btn--ghost q-btn--icon" aria-label="Kapat">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="q-menu-modal__tabs q-tab-row--card" role="tablist" id="menu-form-tabs">
                    <button type="button" data-menu-tab="basic" class="menu-form-tab q-tab selected" aria-selected="true">Temel</button>
                    <button type="button" data-menu-tab="stock" class="menu-form-tab q-tab" aria-selected="false">Stok</button>
                    <button type="button" data-menu-tab="detail" class="menu-form-tab q-tab" aria-selected="false">Detay</button>
                    <button type="button" data-menu-tab="i18n" class="menu-form-tab q-tab" aria-selected="false">Çoklu Dil</button>
                </div>
            </div>
                <form id="menu-form" method="POST" action="#" class="q-menu-modal__body q-stack q-stack--md" novalidate>
                <input type="hidden" id="edit-id" name="id">
                <?php echo csrf_field(); ?>
                <!-- Multilingual Support -->
                <div id="multilingual-section" class="space-y-4 hidden" data-menu-pane="basic i18n">
                    <div class="q-card q-card--pad q-toolbar" style="background:var(--color-surface-muted);">
                        <span class="q-label" style="margin:0;"><?php echo t('menu.multilingual'); ?></span>
                        <div class="q-tab-row--card">
                            <?php foreach ($supportedLanguages as $lang): ?>
                                <button type="button" 
                                        id="lang-tab-<?php echo $lang; ?>"
                                        class="lang-tab-btn <?php echo $lang === $defaultLanguage ? 'active' : ''; ?>"
                                        data-lang="<?php echo $lang; ?>">
                                    <?php echo strtoupper($lang); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php foreach ($supportedLanguages as $lang): ?>
                        <div id="lang-content-<?php echo $lang; ?>" class="lang-content space-y-4 <?php echo $lang === $defaultLanguage ? '' : 'hidden'; ?>" data-lang="<?php echo $lang; ?>">
                            <div>
                                <label class="q-label">
                                    <?php echo t('menu.name'); ?> (<?php echo strtoupper($lang); ?>)
                                </label>
                                <input type="text"
                                       id="form-name-<?php echo $lang; ?>"
                                       name="translations[<?php echo $lang; ?>][name]"
                                       <?php echo $lang === $defaultLanguage ? 'required' : ''; ?>
                                       data-lang="<?php echo $lang; ?>"
                                       data-field="name"
                                       data-auto-translate="true"
                                       class="q-input"
                                       placeholder="<?php echo t('menu.namePlaceholder'); ?>"/>
                            </div>

                            <div>
                                <label class="q-label">
                                    <?php echo t('menu.description'); ?> (<?php echo strtoupper($lang); ?>)
                                </label>
                                <textarea id="form-description-<?php echo $lang; ?>"
                                          name="translations[<?php echo $lang; ?>][description]"
                                          rows="3"
                                          data-lang="<?php echo $lang; ?>"
                                          data-field="description"
                                          data-auto-translate="true"
                                          class="q-input" style="resize:none;"
                                          placeholder="<?php echo t('menu.descriptionPlaceholder'); ?>"></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="items-form-fields">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" data-menu-pane="basic">
                        <div>
                            <label class="q-label"><?php echo t('menu.price'); ?></label>
                            <div class="relative">
                                <input aria-required="true" type="number" id="form-price" name="price" step="any" required
                                       class="q-input"
                                       placeholder="0.00"/>
                                <span class="q-hint text-sm" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);">₺</span>
                            </div>
                        </div>
                        <div>
                            <label class="q-label"><?php echo t('menu.category'); ?></label>
                            <div class="relative">
                                <select id="form-category" name="category_id" required
                                        class="q-input">
                                <option value=""><?php echo t('menu.selectCategory'); ?></option>
                                <?php 
                                $parentCats = [];
                                $childCats = [];
                                foreach ($categories as $cat) {
                                    if (empty($cat['parent_id'])) {
                                        $parentCats[] = $cat;
                                    } else {
                                        $pId = $cat['parent_id'];
                                        if (!isset($childCats[$pId])) $childCats[$pId] = [];
                                        $childCats[$pId][] = $cat;
                                    }
                                }
                                
                                foreach ($parentCats as $parent): 
                                    $pId = $parent['category_id'] ?? '';
                                    $hasKids = isset($childCats[$pId]) && !empty($childCats[$pId]);
                                ?>
                                    <?php if ($hasKids): ?>
                                        <optgroup label="<?php echo htmlspecialchars($parent['name'] ?? ''); ?>">
                                            <option value="<?php echo htmlspecialchars($pId); ?>"
                                                    data-default-production-point="<?php echo htmlspecialchars($parent['default_production_point'] ?? ''); ?>"
                                                    data-requires-kitchen="<?php echo !empty($parent['requires_kitchen']) && $parent['requires_kitchen'] == 1 ? '1' : '0'; ?>">
                                                <?php echo htmlspecialchars($parent['name'] ?? ''); ?> (Tümü)
                                            </option>
                                            <?php foreach ($childCats[$pId] as $child): ?>
                                                <option value="<?php echo htmlspecialchars($child['category_id'] ?? ''); ?>"
                                                        data-default-production-point="<?php echo htmlspecialchars($child['default_production_point'] ?? ''); ?>"
                                                        data-requires-kitchen="<?php echo !empty($child['requires_kitchen']) && $child['requires_kitchen'] == 1 ? '1' : '0'; ?>">
                                                    &nbsp;&nbsp;<?php echo htmlspecialchars($child['name'] ?? ''); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php else: ?>
                                        <option value="<?php echo htmlspecialchars($pId); ?>"
                                                data-default-production-point="<?php echo htmlspecialchars($parent['default_production_point'] ?? ''); ?>"
                                                data-requires-kitchen="<?php echo !empty($parent['requires_kitchen']) && $parent['requires_kitchen'] == 1 ? '1' : '0'; ?>">
                                            <?php echo htmlspecialchars($parent['name'] ?? ''); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            </div>
                        </div>
                    </div>

                    <!-- Availability & service options -->
                    <div class="q-card q-card--pad q-menu-toggle-card" style="background:var(--color-surface-muted);" data-menu-pane="basic">
                        <label class="q-menu-toggle-row">
                            <input type="checkbox" id="form-is-available" name="is_available" value="1" checked>
                            <span class="q-label" style="margin:0;">Satışta / müşteriye görünür</span>
                        </label>
                        <p class="q-hint text-xs" style="margin:0.5rem 0 0 2rem;">Kapalıysa QR menü ve POS listesinde görünmez.</p>
                    </div>

                    <!-- Stock Tracking -->
                    <div class="q-card q-card--pad" style="background:var(--color-surface-muted);" data-menu-pane="stock">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="form-track-stock" name="track_stock" value="1"
                                   />
                            <span class="q-label" style="margin:0;">Stok takibi yap</span>
                        </label>

                        <div id="stock-quantity-section" class="hidden mt-4 space-y-4">
                            <div class="space-y-2">
                                <label class="q-label">Stok miktarı</label>
                                <input type="number" id="form-stock-quantity" name="stock_quantity" step="1" min="0" value="0"
                                       class="q-input"
                                       placeholder="0"/>
                                <p class="q-hint text-xs">Sipariş verildiğinde veya fireye atıldığında otomatik düşer.</p>
                            </div>
                            <div class="space-y-2">
                                <label class="q-label">Düşük stok eşiği (opsiyonel)</label>
                                <input type="number" id="form-low-stock-threshold" name="low_stock_threshold" step="1" min="0"
                                       class="q-input"
                                       placeholder="Örn: 5"/>
                                <p class="q-hint text-xs">Bu değerin altına inince uyarı listesinde görünür.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Preparation Screen -->
                    <div id="form-production-point-container" class="space-y-2" data-menu-pane="detail">
                        <label class="q-label">Hazırlık Ekranı</label>
                        <div class="relative">
                            <select id="form-preparation-screen" name="preparation_screen_id"
                                    class="q-input">
                                <option value="">Seçiniz...</option>
                                <?php foreach ($preparation_screens as $screen): 
                                    $categoryIds = $screen['category_ids'] ?? [];
                                    $categoryIdsJson = htmlspecialchars(json_encode($categoryIds));
                                ?>
                                    <option value="<?php echo htmlspecialchars($screen['screen_id'] ?? ''); ?>"
                                            data-category-ids="<?php echo $categoryIdsJson; ?>">
                                        <?php echo htmlspecialchars($screen['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="q-hint text-xs">Siparişin gönderileceği ekran</p>
                    </div>

                    <div class="q-card q-card--pad q-menu-toggle-card" style="background:var(--color-surface-muted);" data-menu-pane="detail">
                        <label class="q-menu-toggle-row">
                            <input type="checkbox" id="form-is-direct-service" name="is_direct_service" value="1">
                            <span class="q-label" style="margin:0;">Doğrudan servis (mutfak ekranına gitmez)</span>
                        </label>
                    </div>

                    <!-- Variants -->
                    <div class="q-card q-card--pad" style="background:var(--color-surface-muted);" data-menu-pane="detail">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="form-has-variants" name="has_variants" value="1"
                                   />
                            <span class="q-label" style="margin:0;">Varyantları var</span>
                        </label>

                        <div id="variants-section" class="hidden mt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="q-label" style="margin:0;">Varyantlar</span>
                                <button type="button" id="btn-add-variant" class="q-btn q-btn--ghost q-btn--sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Ekle
                                </button>
                            </div>
                            <div id="variants-list" class="space-y-2">
                                <!-- Variants will be added here -->
                            </div>
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="space-y-3" data-menu-pane="basic">
                        <label class="q-label"><?php echo t('menu.imageUrl'); ?></label>

                        <div class="q-tab-row--card">
                            <button type="button" id="image-url-toggle" class="q-tab selected flex-1">
                                URL
                            </button>
                            <button type="button" id="image-file-toggle" class="q-tab flex-1">
                                Dosya Yükle
                            </button>
                        </div>

                        <div id="image-url-input">
                            <input type="url" id="form-image-url" name="image_url"
                                   placeholder="https://..."
                                   class="q-input"/>
                        </div>

                        <div id="image-file-input" class="hidden">
                            <input type="file" id="form-image-file" name="image_file" accept="image/*" class="hidden"/>
                            <label for="form-image-file" class="q-card q-card--pad flex flex-col items-center justify-center w-full h-32 cursor-pointer" style="border:2px dashed var(--color-border-1);">
                                <svg class="w-8 h-8 q-hint mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span id="image-file-label" class="q-hint text-sm">Dosya seç veya sürükle</span>
                            </label>
                            <div id="image-upload-progress" class="hidden mt-2">
                                <div class="w-full rounded-full h-1.5" style="background:var(--color-surface-muted);">
                                    <div id="image-upload-progress-bar" class="h-1.5 rounded-full transition-all" style="background:var(--color-brand-accent);width: 0%"></div>
                                </div>
                                <p id="image-upload-status" class="q-hint text-xs mt-1.5 text-center">Yükleniyor...</p>
                            </div>
                        </div>

                        <div id="image-preview-container" class="hidden">
                            <img id="image-preview" src="" alt="Preview" class="w-full h-48 object-cover rounded-xl"/>
                            <button type="button" id="btn-clear-image-preview" class="mt-2 w-full py-2 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all">
                                Kaldır
                            </button>
                        </div>
                    </div>

                    <!-- Ingredients Accordion -->
                    <div class="q-card overflow-hidden" style="padding:0;" data-menu-pane="detail">
                        <button type="button" id="ingredients-accordion-toggle" class="q-btn q-btn--ghost w-full" style="justify-content:space-between;border-radius:0;">
                            <span class="q-label" style="margin:0;">Malzemeler (Çıkarılabilir)</span>
                            <svg id="ingredients-chevron" class="w-4 h-4 q-hint transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="ingredients-accordion-content" class="hidden q-stack q-stack--sm q-card--pad">
                            <div class="flex gap-2">
                                <input type="text" id="new-ingredient" placeholder="Örn: Domates"
                                       class="q-input flex-1"/>
                                <button type="button" id="btn-add-ingredient" class="q-btn q-btn--primary q-btn--icon">
                                    <?php echo icon_plus(['class' => 'w-4 h-4']); ?>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2" id="ingredients-list">
                                <!-- Ingredients will be added here -->
                            </div>
                        </div>
                    </div>

                    <!-- Extras Accordion -->
                    <div class="q-card overflow-hidden" style="padding:0;" data-menu-pane="detail">
                        <button type="button" id="extras-accordion-toggle" class="q-btn q-btn--ghost w-full" style="justify-content:space-between;border-radius:0;">
                            <span class="q-label" style="margin:0;">Ekstralar (Ücretli)</span>
                            <svg id="extras-chevron" class="w-4 h-4 q-hint transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="extras-accordion-content" class="hidden q-stack q-stack--sm q-card--pad">
                            <div class="flex gap-2">
                                <input type="text" id="new-extra-name" placeholder="Adı"
                                       class="q-input flex-1"/>
                                <input type="number" id="new-extra-price" placeholder="₺" step="any"
                                       class="q-input w-full sm:w-auto"/>
                                <button type="button" id="btn-add-extra" class="q-btn q-btn--primary q-btn--icon">
                                    <?php echo icon_plus(['class' => 'w-4 h-4']); ?>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2" id="extras-list">
                                <!-- Extras will be added here -->
                            </div>
                        </div>
                    </div>
                </div>

                </form>
            <div class="q-menu-modal__footer q-modal__footer q-toolbar">
                <button type="button" id="btn-modal-cancel" class="q-btn q-btn--ghost flex-1"><?php echo t('common.cancel'); ?></button>
                <button type="submit" form="menu-form" class="q-btn q-btn--primary flex-1"><?php echo t('common.save'); ?></button>
            </div>
        </div>
    </div>

    <!-- Menu Extraction Modal -->
    <div id="menu-extraction-modal" class="q-modal-backdrop hidden">
        <div class="q-modal-backdrop__scrim" id="extraction-modal-backdrop"></div>
        <div class="q-modal q-modal--wide">
            <div class="q-modal__header">
                <h2 class="q-modal__title">Fotoğraftan Menü Yükle</h2>
                <button id="btn-extraction-modal-close" type="button" class="q-btn q-btn--ghost q-btn--icon">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="q-modal__body q-stack q-stack--md">
                <!-- Step 1: Image Upload -->
                <div id="extraction-step-upload" class="space-y-5">
                    <p class="q-hint text-sm">
                        Menü fotoğraflarınızı yükleyin (maks. 5 adet). Sistem ürünleri otomatik çıkaracaktır.
                    </p>
                    
                    <!-- Image Upload Area -->
                    <div class="q-card q-card--pad flex flex-col items-center justify-center p-10 cursor-pointer" style="border:2px dashed var(--color-border-1);" id="extraction-upload-area">
                        <input type="file" id="extraction-image-input" accept="image/jpeg,image/jpg,image/png,image/webp" multiple class="hidden">
                        <label for="extraction-image-input" class="cursor-pointer flex flex-col items-center text-center">
                            <div class="q-empty__icon mb-4">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--color-text-muted);">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <span class="q-label" style="margin:0;">Fotoğraf seç veya sürükle</span>
                            <span class="mt-1 q-hint text-xs">JPEG, PNG, WebP · Maks 10MB</span>
                        </label>
                    </div>
                    
                    <!-- Selected Images Preview -->
                    <div id="extraction-images-preview-container" class="hidden space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="q-label" style="margin:0;">
                                Seçilen (<span id="selected-images-count" class="font-semibold">0</span>/5)
                            </span>
                            <button type="button" id="btn-clear-all-images" class="q-btn q-btn--ghost q-btn--sm" style="color:var(--color-status-danger);">
                                Tümünü Temizle
                            </button>
                        </div>
                        <div id="extraction-images-preview" class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                            <!-- Selected images will be rendered here -->
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Loading -->
                <div id="extraction-step-loading" class="hidden flex flex-col items-center justify-center py-16">
                    <div class="q-spinner q-spinner--lg"></div>
                    <p class="mt-4 text-sm font-medium">Menü analiz ediliyor...</p>
                    <p class="mt-1 q-hint text-xs">Bu birkaç saniye sürebilir</p>
                </div>
                
                <!-- Step 3: Review/Edit Extracted Items -->
                <div id="extraction-step-review" class="hidden space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="q-label" style="margin:0;">
                            <span id="extraction-items-count" class="font-semibold">0</span> ürün bulundu
                        </span>
                        <button id="btn-remove-all-items" class="q-btn q-btn--ghost q-btn--sm" style="color:var(--color-status-danger);">
                            Tümünü Kaldır
                        </button>
                    </div>
                    
                    <div id="extracted-items-list" class="space-y-2 max-h-80 overflow-y-auto">
                        <!-- Extracted items will be rendered here -->
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="q-modal__footer q-toolbar">
                <button type="button" id="btn-extraction-cancel" class="q-btn q-btn--ghost flex-1">İptal</button>
                <button type="button" id="btn-extract-menu-modal" class="hidden q-btn q-btn--primary flex-1">Menüyü Çıkar</button>
                <button type="button" id="btn-save-extracted-items" class="hidden q-btn q-btn--primary flex-1">Onayla ve Ekle</button>
            </div>
        </div>
    </div>

  </div><!-- /q-container -->
</div><!-- /q-page -->

<script>
// --- Menu Form Tab Switcher (progressive disclosure) --------------------
// Keeps the add/edit modal's form readable by splitting fields into
// Temel / Stok / Detay / Çoklu Dil panes. Multilingual-section spans both
// Temel and i18n tabs; we hide/show language sub-panes accordingly.
(function() {
    function activateMenuFormTab(tabId) {
        const panes = document.querySelectorAll('[data-menu-pane]');
        panes.forEach(pane => {
            const assigned = (pane.getAttribute('data-menu-pane') || '').split(/\s+/);
            pane.style.display = assigned.includes(tabId) ? '' : 'none';
        });

        // Style active tab button.
        document.querySelectorAll('.menu-form-tab').forEach(btn => {
            const active = btn.getAttribute('data-menu-tab') === tabId;
            btn.classList.toggle('selected', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        // On Temel tab, force the multilingual section to show only the
        // default language sub-pane. On i18n tab, reveal the language
        // switcher so the user can edit translations in every language.
        const mlSection = document.getElementById('multilingual-section');
        if (mlSection) {
            const langSwitcher = mlSection.querySelector('.q-tab-row--card');
            const allLangContents = mlSection.querySelectorAll('.lang-content');
            const defaultLang = '<?php echo $defaultLanguage; ?>';

            if (tabId === 'basic') {
                if (langSwitcher) langSwitcher.style.display = 'none';
                allLangContents.forEach(el => {
                    const isDefault = el.getAttribute('data-lang') === defaultLang;
                    el.classList.toggle('hidden', !isDefault);
                });
            } else if (tabId === 'i18n') {
                if (langSwitcher) langSwitcher.style.display = '';
                // Let the lang-tab-btn handlers control which sub-pane is
                // visible; show the default one by default.
                allLangContents.forEach(el => {
                    const isDefault = el.getAttribute('data-lang') === defaultLang;
                    el.classList.toggle('hidden', !isDefault);
                });
            }
        }
    }

    // Reset to Temel whenever the modal opens — observe class changes on
    // #menu-modal so we do not need to patch the openModal method.
    function observeModal() {
        const modal = document.getElementById('menu-modal');
        if (!modal) return;
        const observer = new MutationObserver(() => {
            if (!modal.classList.contains('hidden')) {
                activateMenuFormTab('basic');
            }
        });
        observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
    }

    document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.menu-form-tab');
        if (!btn) return;
        ev.preventDefault();
        activateMenuFormTab(btn.getAttribute('data-menu-tab'));
    });

    // If an invalid field is nested inside a hidden tab, the browser cannot
    // focus it and shows the ambiguous "An invalid form control ... is not
    // focusable" warning. Jump to the owning tab before validation fires.
    document.addEventListener('invalid', (ev) => {
        const field = ev.target;
        if (!field || !field.closest) return;
        const pane = field.closest('[data-menu-pane]');
        if (!pane) return;
        const panes = (pane.getAttribute('data-menu-pane') || '').split(/\s+/);
        const target = panes.includes('basic') ? 'basic' : panes[0];
        if (target) activateMenuFormTab(target);
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            observeModal();
            activateMenuFormTab('basic');
        });
    } else {
        observeModal();
        activateMenuFormTab('basic');
    }

    // Expose for debugging / manual triggers.
    window.__activateMenuFormTab = activateMenuFormTab;
})();

// Wait for menu.js to load before initializing
(function() {
    const script = document.createElement('script');
    script.src = '<?php echo BASE_URL; ?>/assets/js/admin/menu.js';
    script.onload = function() {
        // MenuPage should be available now
        if (typeof MenuPage === 'undefined') {
            console.error('MenuPage module not found after script load');
            return;
        }
        
        // Initialize MenuPage
        initializeMenuPage();
        
        // Add direct event listeners for modal close buttons (backup)
        const closeXBtn = document.getElementById('btn-modal-close-x');
        const cancelBtn = document.getElementById('btn-modal-cancel');
        const modalBackdrop = document.getElementById('modal-backdrop');
        
        if (closeXBtn) {
            closeXBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof MenuPage !== 'undefined' && MenuPage.closeModal) {
                    MenuPage.closeModal();
                }
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof MenuPage !== 'undefined' && MenuPage.closeModal) {
                    MenuPage.closeModal();
                }
            });
        }
        
        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', function(e) {
                if (e.target === modalBackdrop) {
                    if (typeof MenuPage !== 'undefined' && MenuPage.closeModal) {
                        MenuPage.closeModal();
                    }
                }
            });
        }
    };
    script.onerror = function() {
        console.error('Failed to load menu.js');
    };
    document.head.appendChild(script);
})();

const baseUrl = <?php echo json_encode($baseUrl ?? BASE_URL ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const defaultLanguage = <?php echo json_encode($defaultLanguage ?? 'tr', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

// Stock tracking checkbox handler
document.addEventListener('DOMContentLoaded', function() {
    const trackStockCheckbox = document.getElementById('form-track-stock');
    const stockQuantitySection = document.getElementById('stock-quantity-section');
    
    if (trackStockCheckbox && stockQuantitySection) {
        // Handle checkbox change
        trackStockCheckbox.addEventListener('change', function() {
            if (this.checked) {
                stockQuantitySection.classList.remove('hidden');
                document.getElementById('form-stock-quantity').required = true;
            } else {
                stockQuantitySection.classList.add('hidden');
                document.getElementById('form-stock-quantity').required = false;
                document.getElementById('form-stock-quantity').value = '0';
            }
        });
        
        // Initialize state on page load
        if (trackStockCheckbox.checked) {
            stockQuantitySection.classList.remove('hidden');
            document.getElementById('form-stock-quantity').required = true;
        }
    }
    
    // Category selection handler for preparation screen visibility and filtering
    const categorySelect = document.getElementById('form-category');
    const preparationScreenContainer = document.getElementById('form-production-point-container');
    const preparationScreenSelect = document.getElementById('form-preparation-screen');
    
    if (categorySelect && preparationScreenContainer && preparationScreenSelect) {
        function handleCategoryChange() {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            let requiresService = true;

            if (!selectedOption || selectedOption.value === '') {
                // No category selected - hide preparation screen
                preparationScreenContainer.style.display = 'none';
                preparationScreenSelect.value = '';
                preparationScreenSelect.required = false;
                return;
            } else {
                const defaultProductionPoint = selectedOption.getAttribute('data-default-production-point') || '';
                const requiresKitchen = selectedOption.getAttribute('data-requires-kitchen') || '0';
                // Servise gerek yok: default_production_point = 'NONE' veya requires_kitchen = '0'
                requiresService = defaultProductionPoint !== 'NONE' && requiresKitchen !== '0';
            }

            // If category doesn't require service, hide preparation screen
            if (!requiresService) {
                preparationScreenContainer.style.display = 'none';
                preparationScreenSelect.value = '';
                preparationScreenSelect.required = false;
                return;
            }

            // Category requires service - show preparation screen picker
            preparationScreenContainer.style.display = 'block';
            preparationScreenSelect.required = true;

            // Show all preparation screens
            const allOptions = preparationScreenSelect.querySelectorAll('option');
            allOptions.forEach(option => {
                if (option.value === '') {
                    option.style.display = '';
                    return;
                }
                option.style.display = '';
            });

            // Auto-select preparation screen based on category name/type
            const selectedCategoryId = selectedOption.value;
            const categoryText = selectedOption.textContent.toLowerCase();
            let autoSelected = false;
            
            // Keywords that indicate Nargile (hookah/drinks) screen
            const nargileKeywords = ['nargile', 'hookah', 'içecek', 'icecek', 'drink', 
                                     'kahve', 'coffee', 'çay', 'cay', 'tea', 
                                     'soğuk', 'soguk', 'sıcak', 'sicak', 'bitki'];
            
            // Check if category is for Nargile screen
            const isNargileCategory = nargileKeywords.some(keyword => categoryText.includes(keyword));
            
            // Try to find a screen assigned to this category first
            allOptions.forEach(option => {
                if (option.value === '' || autoSelected) return;
                
                const categoryIdsJson = option.getAttribute('data-category-ids');
                if (categoryIdsJson) {
                    try {
                        const categoryIds = JSON.parse(categoryIdsJson);
                        if (Array.isArray(categoryIds) && categoryIds.includes(selectedCategoryId)) {
                            preparationScreenSelect.value = option.value;
                            autoSelected = true;
                        }
                    } catch (e) {
                        console.error('Error parsing category IDs:', e);
                    }
                }
            });

            // If no category-assigned screen found, auto-select based on category type
            if (!autoSelected && !preparationScreenSelect.value) {
                if (isNargileCategory) {
                    // Find Nargile screen
                    const nargileOption = Array.from(preparationScreenSelect.options).find(opt => {
                        if (!opt.value) return false;
                        const optText = opt.textContent.toLowerCase();
                        return optText.includes('nargile') || optText.includes('hookah');
                    });
                    
                    if (nargileOption) {
                        preparationScreenSelect.value = nargileOption.value;
                        autoSelected = true;
                    }
                }
                
                // If still not selected, default to Mutfak (kitchen)
                if (!autoSelected) {
                    const mutfakOption = Array.from(preparationScreenSelect.options).find(opt => {
                        if (!opt.value) return false;
                        const optText = opt.textContent.toLowerCase();
                        return optText.includes('mutfak') || optText.includes('kitchen');
                    });
                    
                    if (mutfakOption) {
                        preparationScreenSelect.value = mutfakOption.value;
                    } else {
                        // Fallback to first available screen
                        const firstActive = Array.from(preparationScreenSelect.options).find(opt => opt.value);
                        if (firstActive) {
                            preparationScreenSelect.value = firstActive.value;
                        }
                    }
                }
            }
        }
        
        // Add event listener
        categorySelect.addEventListener('change', handleCategoryChange);
        
        // Initialize on page load
        handleCategoryChange();
    }
});

function initializeMenuPage() {
    // Initialize MenuPage with configuration
    if (typeof MenuPage !== 'undefined') {
        MenuPage.init({
            baseUrl: baseUrl,
            adminPrefix: <?php echo json_encode($adminPrefix); ?>,
            apiPrefix: <?php echo json_encode($apiPrefix); ?>,
            menuItems: <?php echo json_encode($menu_items ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            categories: <?php echo json_encode($categories ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            supportedLanguages: <?php echo json_encode($supportedLanguages ?? ['tr', 'en'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
            defaultLanguage: defaultLanguage,
            permissions: {
                canCreate: <?php echo hasPermissionForRole('menu.create') ? 'true' : 'false'; ?>,
                canEdit: <?php echo hasPermissionForRole('menu.edit') ? 'true' : 'false'; ?>,
                canDelete: <?php echo hasPermissionForRole('menu.delete') ? 'true' : 'false'; ?>,
                canManageCategories: <?php echo hasPermissionForRole('menu.categories') ? 'true' : 'false'; ?>
            },
            translations: {
                newItem: <?php echo json_encode(t('menu.newItem'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                edit: <?php echo json_encode(t('menu.edit'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            }
        });
    } else {
        console.error('MenuPage module not loaded. Please check if menu.js is loaded correctly.');
    }
}

<?php if ($is_super_admin ?? false): ?>
// Super Admin: Load BusinessSelector and handle business selection
(function() {
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
            // Business ID is already in the URL — the PHP controller has already
            // swapped TenantContext and server-rendered the grid for this tenant.
            // We just need to resolve the display name and switch the view;
            // NEVER wipe the server-rendered grid with a spinner (there is no
            // reliable client-side updateData path and it would leave the page
            // stuck on "Menüler yükleniyor..." forever).
            const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
            fetch(`${BusinessSelector.config.baseUrl}${apiPrefix}/businesses`)
                .then(response => response.json())
                .then(data => {
                    const businesses = Array.isArray(data) ? data : (data.businesses || data.data || []);
                    const business = businesses.find(b => (b.business_id || b.id) === businessIdFromUrl);

                    let businessName = 'İşletme';
                    if (business) {
                        businessName = business.company_name || business.business_name || business.name || '';
                        if (!businessName || !businessName.trim()) {
                            const ownerName = (business.owner_name || business.owner || '').trim();
                            const email     = (business.email || business.business_email || '').trim();
                            businessName = ownerName || (email ? email.split('@')[0] : 'İşletme');
                        }
                        businessName = businessName.trim();
                    }

                    sessionStorage.setItem('selected_business_id', businessIdFromUrl);
                    sessionStorage.setItem('selected_business_name', businessName);
                    window.currentBusinessId = businessIdFromUrl;

                    // Flip to the menu management view without touching the grid.
                    BusinessSelector.showContentView(
                        'business-selection-view',
                        'menu-management-view',
                        businessName
                    );
                })
                .catch(error => {
                    console.error('Error resolving business display name:', error);
                    // Still reveal the menu view so the server-rendered items are visible.
                    BusinessSelector.showContentView(
                        'business-selection-view',
                        'menu-management-view',
                        'İşletme'
                    );
                });
        } else {
            // No business_id in URL — show the picker. A selection triggers a
            // full navigation so MenuController re-runs with the right tenant
            // and the view is server-rendered with that business's data.
            BusinessSelector.loadBusinesses().then(() => {
                BusinessSelector.renderBusinessGrid('business-grid', function(businessId, businessName) {
                    sessionStorage.setItem('selected_business_id', businessId);
                    sessionStorage.setItem('selected_business_name', businessName);
                    const url = new URL(window.location.href);
                    url.searchParams.set('business_id', businessId);
                    window.location.href = url.toString();
                });
            });
        }
    };
    document.head.appendChild(bsScript);
})();

// Global function to go back to business selection
function backToBusinessSelection() {
    BusinessSelector.showSelectionView('business-selection-view', 'menu-management-view');
    // Clear menu items
    if (window.MenuPage && typeof window.MenuPage.clearData === 'function') {
        window.MenuPage.clearData();
    }
    
    // Remove business_id from URL
    const url = new URL(window.location.href);
    url.searchParams.delete('business_id');
    window.history.pushState({}, '', url.toString());
    
    // Clear session storage
    sessionStorage.removeItem('selected_business_id');
    sessionStorage.removeItem('selected_business_name');
    window.currentBusinessId = null;
}

// Load menu items for selected business
function loadBusinessMenuItems(businessId, businessName) {
    // Show loading state
    const menuList = document.getElementById('menu-items-list');
    if (menuList) {
        menuList.innerHTML = '<div class="q-menu-items-empty"><div class="q-spinner q-spinner--lg" style="margin:0 auto;"></div><p class="q-hint mt-4">Menüler yükleniyor...</p></div>';
    }
    
    // Fetch menu items for this business
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    fetch(`${baseUrl}${apiPrefix}/businesses/${businessId}/menu`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.menu_items) {
                // Update MenuPage with new data
                if (window.MenuPage && typeof window.MenuPage.updateData === 'function') {
                    window.MenuPage.updateData({
                        menuItems: data.menu_items
                    });
                } else {
                    // Fallback: reload page with business context
                    console.log('MenuPage updateData not available, menu items:', data.menu_items);
                }
                
                // Show menu management view
                BusinessSelector.showContentView('business-selection-view', 'menu-management-view', businessName);
            } else {
                console.error('Failed to load menu items:', data);
                if (window.NotificationManager) {
                    window.NotificationManager.error('Menüler yüklenirken hata oluştu');
                }
            }
        })
        .catch(error => {
            console.error('Error loading menu items:', error);
            if (window.NotificationManager) {
                window.NotificationManager.error('Menüler yüklenirken hata oluştu');
            }
        });
}
<?php endif; ?>
</script>
