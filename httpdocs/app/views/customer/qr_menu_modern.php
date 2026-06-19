<?php
/**
 * Modern QR Menu - Q-System Edition
 * Standalone customer menu view, mobile-first, large touch targets.
 *
 * Refactored: hardcoded #FF6B35 → CSS var --color-brand-accent
 * raw Tailwind palette → q-system classes, hardcoded gradients → q-bg-brand-primary.
 */
if (!isset($table) || !is_array($table)) {
 require_once __DIR__ . '/../../helpers/functions.php';
 require_once __DIR__ . '/../../helpers/translations.php';
 $translationService = getTranslationService();
 echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . t('errors.error', 'Hata') . '</title></head><body><h1>' . t('errors.tableNotFound', 'Masa bulunamadı') . '</h1><p>' . t('errors.scanQRAgain', 'Lütfen QR kodu tekrar okutun.') . '</p></body></html>';
 exit;
}
?>
<!DOCTYPE html>
<html lang="<?php
 require_once __DIR__ . '/../../helpers/functions.php';
 require_once __DIR__ . '/../../core/HelperLoader.php';
 \App\Core\HelperLoader::ensureLoaded();
 $translationService = getTranslationService();
 echo $translationService->getCurrentLanguage();
?>">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
 <meta name="theme-color" content="#FF6B35">
 <meta name="apple-mobile-web-app-capable" content="yes">
 <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
 <?php
 require_once __DIR__ . '/../../core/Security/CSRFManager.php';
 $csrfToken = \App\Core\Security\CSRFManager::generateToken();
 ?>
 <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
 <script>
 window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
 </script>
 <title><?php echo getAppConfig()->getAppName(); ?> - Modern Menü</title>

 <!-- Q-System assets -->
 <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/tokens.css">
 <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-components.css">

 <!-- Icons Helper -->
 <?php require_once __DIR__ . '/../partials/icons.php'; ?>

 <style>
 /* Q-System: animations & helpers */
 .q-no-scrollbar::-webkit-scrollbar { display: none; }
 .q-no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

 @keyframes qFadeIn {
 from { opacity: 0; transform: translateY(10px); }
 to { opacity: 1; transform: translateY(0); }
 }
 @keyframes qSlideUp {
 from { transform: translateY(100%); }
 to { transform: translateY(0); }
 }
 @keyframes qPulse {
 0%, 100% { transform: scale(1); }
 50% { transform: scale(1.05); }
 }
 .q-anim-fade-in { animation: qFadeIn 0.3s ease-out; }
 .q-anim-slide-up { animation: qSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
 .q-anim-pulse { animation: qPulse 2s infinite; }

 /* q-system touch feedback */
 .q-btn-touch:active { transform: scale(0.96); }
 .q-product-card:active { transform: scale(0.98); }

 /* Brand gradient (uses --color-brand-accent) */
 .q-bg-brand-gradient { background: linear-gradient(135deg, var(--color-brand-accent) 0%, #FF8E53 100%); }

 /* Soft tinted text/border for selected menu state */
 .q-brand-border { border-color: var(--color-brand-accent) !important; background: var(--color-amber-soft); }
 .q-danger-border { border-color: var(--color-status-danger) !important; color: var(--color-status-danger); background: #fee2e2; }
 </style>
</head>
<body class="q-page">

 <a href="#main-content" class="skip-link">Menüye git</a>

 <!-- Header -->
 <header class="q-card q-card--glass q-menu-header">
 <div class="q-page-header">
 <div>
 <?php
 $displayBusinessName = '';
 try {
 $tenantId = \App\Core\TenantContext::getId();
 if ($tenantId) {
 $customerService = \App\Core\DependencyFactory::getCustomerService();
 $customer = $customerService->getById($tenantId);
 if ($customer) {
 $displayBusinessName = !empty($customer['company_name'])
 ? $customer['company_name']
 : (!empty($customer['business_name']) ? $customer['business_name'] : '');
 }
 }
 } catch (\Exception $e) { /* silent */ }
 if (empty($displayBusinessName)) {
 $displayBusinessName = $settings['business_name'] ?? $settings['restaurant_name'] ?? getAppConfig()->getAppName();
 }
 ?>
 <?php if (!empty($logo_url)): ?>
 <img src="<?php echo htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8'); ?>"
 alt="<?php echo htmlspecialchars($displayBusinessName, ENT_QUOTES, 'UTF-8'); ?>"
 class="q-card__logo">
 <?php endif; ?>
 <p class="q-page-header__eyebrow"><?php echo htmlspecialchars($table['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="q-anim-pulse">•</span></p>
 <h1 class="q-page-header__title"><?php echo htmlspecialchars($displayBusinessName ?: ($table['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
 </div>
 <div class="q-page-header__actions">
 <button type="button" onclick="callWaiter()" class="q-icon-btn" aria-label="Garson çağır" title="Garson Çağır">👋</button>
 <button type="button" onclick="requestBill()" class="q-icon-btn" aria-label="Hesap iste" title="Hesap İste">💳</button>
 <button type="button" onclick="openCart()" class="q-icon-btn q-bg-brand-gradient" aria-label="Sepeti aç" title="Sepet">
 🛒
 <span id="cart-badge" class="q-cart-badge q-anim-pulse q-hidden">0</span>
 </button>
 </div>
 </div>
 </header>

 <!-- Main Content -->
 <main id="main-content" class="q-container q-stack q-stack--lg">

 <!-- Categories -->
 <section aria-label="Kategoriler">
 <h2 class="q-card__title q-mb-3">Kategoriler</h2>
 <div class="q-no-scrollbar q-category-row">
 <?php
 $categoryMap = [];
 foreach ($categories ?? [] as $category) {
 $categoryMap[$category['category_id']] = $category;
 }
 $parentToChildren = [];
 $childrenToParent = [];
 foreach ($categories ?? [] as $category) {
 if (!empty($category['parent_id'])) {
 if (!isset($parentToChildren[$category['parent_id']])) {
 $parentToChildren[$category['parent_id']] = [];
 }
 $parentToChildren[$category['parent_id']][] = $category;
 $childrenToParent[$category['category_id']] = $category['parent_id'];
 }
 }
 $parentCategories = [];
 $childCategories = [];
 foreach ($categories ?? [] as $category) {
 if (empty($category['parent_id'])) {
 $parentCategories[] = $category;
 } else {
 if (isset($categoryMap[$category['parent_id']])) {
 $childCategories[] = $category;
 }
 }
 }
 $displayCategories = [];
 foreach ($parentCategories as $parent) {
 $displayCategories[] = $parent;
 if (isset($parentToChildren[$parent['category_id']])) {
 foreach ($parentToChildren[$parent['category_id']] as $child) {
 $displayCategories[] = $child;
 }
 }
 }
 $firstCategoryId = !empty($displayCategories) ? $displayCategories[0]['category_id'] : '';
 ?>
 <button type="button" onclick="filterCategory('all')" class="q-chip q-chip--active" data-category="all" aria-label="Tüm kategoriler">🎯 Tümü</button>
 <?php foreach ($displayCategories as $category):
 $isChild = isset($childrenToParent[$category['category_id']]);
 ?>
 <button type="button" onclick="filterCategory('<?php echo $category['category_id']; ?>')"
 class="q-chip <?php echo $isChild ? 'q-chip--child' : ''; ?>"
 data-category="<?php echo $category['category_id']; ?>"
 data-is-child="<?php echo $isChild ? '1' : '0'; ?>"
 data-parent-id="<?php echo $isChild ? $childrenToParent[$category['category_id']] : ''; ?>"
 aria-label="Kategori: <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>">
 <?php if ($isChild): ?>
 <span class="q-hint">└</span>
 <?php endif; ?>
 <?php echo htmlspecialchars($category['name']); ?>
 </button>
 <?php endforeach; ?>
 </div>
 </section>

 <!-- Products Grid -->
 <div id="products-container">
 <?php
 $itemsByCategory = [];
 foreach ($menu_items as $item):
 if (!$item['is_available']) continue;
 $catId = $item['category_id'] ?? 'other';
 if (!isset($itemsByCategory[$catId])) {
 $itemsByCategory[$catId] = [];
 }
 $itemsByCategory[$catId][] = $item;
 endforeach;

 foreach ($displayCategories as $category):
 $categoryItems = $itemsByCategory[$category['category_id']] ?? [];
 if (empty($categoryItems)) continue;
 $isChild = isset($childrenToParent[$category['category_id']]);
 $parentInfo = $isChild && isset($categoryMap[$childrenToParent[$category['category_id']]])
 ? $categoryMap[$childrenToParent[$category['category_id']]]
 : null;
 ?>
 <section class="q-category-section" data-category="<?php echo $category['category_id']; ?>" data-parent-id="<?php echo $isChild ? $childrenToParent[$category['category_id']] : ''; ?>" aria-label="<?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>">
 <h3 class="q-card__title q-mb-3">
 <?php if ($isChild && $parentInfo): ?>
 <span class="q-hint"><?php echo htmlspecialchars($parentInfo['name']); ?> › </span>
 <?php endif; ?>
 <?php echo htmlspecialchars($category['name']); ?>
 <span class="q-hint">(<?php echo count($categoryItems); ?>)</span>
 </h3>

 <div class="q-grid q-grid--4">
 <?php foreach ($categoryItems as $item): ?>
 <article onclick='openProductModal(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES); ?>)' class="q-card q-card--pad q-card--hover q-product-card q-anim-fade-in" tabindex="0" role="button" aria-label="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
 <!-- Product Image -->
 <div class="q-product-image">
 <?php if (!empty($item['image_url'])): ?>
 <img src="<?php echo htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" class="q-product-image__img">
 <?php else: ?>
 <div class="q-product-image__placeholder">🍽️</div>
 <?php endif; ?>
 <div class="q-product-image__add">
 <span class="q-text-add">+</span>
 </div>
 </div>

 <!-- Product Info -->
 <div class="q-product-info">
 <h4 class="q-product-name"><?php echo htmlspecialchars($item['name']); ?></h4>
 <?php if (!empty($item['description'])): ?>
 <p class="q-product-desc"><?php echo htmlspecialchars($item['description']); ?></p>
 <?php endif; ?>

 <div class="q-toolbar q-toolbar--between q-mt-2">
 <span class="q-text-metric q-text-brand"><?php echo formatCurrency($item['price']); ?></span>
 <?php if (!empty($item['variants']) && count($item['variants']) > 0): ?>
 <span class="q-badge q-badge--neutral">Seçenekli</span>
 <?php endif; ?>
 </div>
 </div>
 </article>
 <?php endforeach; ?>
 </div>
 </section>
 <?php endforeach; ?>
 </div>

 </main>

 <!-- Floating Cart Button -->
 <div id="floating-cart" class="q-cart-floating q-hidden" aria-hidden="true">
 <button type="button" onclick="openCart()" class="q-btn q-btn--primary q-btn--block q-btn--lg q-bg-brand-gradient" aria-label="Sepeti aç">
 <div class="q-toolbar q-toolbar--between q-w-full">
 <div class="q-toolbar">
 <div class="q-cart-floating__icon">🛒</div>
 <div>
 <p class="q-cart-floating__label">Sepetiniz</p>
 <p class="q-cart-floating__total" id="floating-cart-total">0 ₺</p>
 </div>
 </div>
 <div class="q-toolbar">
 <span class="q-cart-floating__items" id="floating-cart-items">0 Ürün</span>
 <span class="q-text-xl">→</span>
 </div>
 </div>
 </button>
 </div>

 <!-- Product Modal -->
 <div id="product-modal" class="q-modal-backdrop q-hidden" role="dialog" aria-modal="true" aria-label="Ürün detayı">
 <div class="q-modal-sheet" onclick="closeProductModal()" aria-hidden="true"></div>
 <div class="q-modal-bottom">
 <div class="q-modal-handle"></div>
 <div id="modal-header" class="q-modal-header">
 <!-- Populated by JavaScript -->
 </div>

 <div id="modal-content" class="q-modal-body">
 <!-- Populated by JavaScript -->
 </div>

 <div class="q-modal-footer">
 <button type="button" onclick="addToCartFromModal()" class="q-btn q-btn--primary q-btn--block q-btn--lg q-bg-brand-gradient" aria-label="Sepete ekle">
 <div class="q-toolbar q-toolbar--between q-w-full">
 <span>Sepete Ekle</span>
 <span id="modal-total-price">0 ₺</span>
 </div>
 </button>
 </div>
 </div>
 </div>

 <!-- Cart Modal -->
 <div id="cart-modal" class="q-modal-backdrop q-hidden" role="dialog" aria-modal="true" aria-label="Sepetim">
 <div class="q-modal-sheet" onclick="closeCart()" aria-hidden="true"></div>
 <div class="q-modal-bottom">
 <div class="q-modal-handle"></div>
 <div class="q-modal-header q-toolbar q-toolbar--between">
 <h3 class="q-card__title">Sepetim</h3>
 <button type="button" onclick="closeCart()" class="q-icon-btn" aria-label="Sepeti kapat">✕</button>
 </div>

 <div id="cart-items" class="q-modal-body">
 <div class="q-empty q-empty--inline">
 <div class="q-empty__icon-wrapper">🛒</div>
 <p class="q-empty__title">Sepetiniz boş</p>
 </div>
 </div>

 <div class="q-modal-footer">
 <div class="q-field">
 <label class="q-label" for="order-note">Sipariş Notu</label>
 <textarea id="order-note" class="q-input" rows="3" placeholder="Örn: Soğansız, az pişmiş..."></textarea>
 </div>

 <div class="q-toolbar q-toolbar--between q-mb-3">
 <span class="q-text-label">Toplam</span>
 <span class="q-text-metric" id="cart-total">0 ₺</span>
 </div>

 <button type="button" onclick="placeOrder()" id="place-order-btn" disabled class="q-btn q-btn--primary q-btn--block q-btn--lg q-bg-brand-gradient" aria-label="Siparişi gönder">
 Siparişi Gönder
 </button>
 </div>
 </div>
 </div>

 <script>
 const TABLE_ID = '<?php echo $table['table_id'] ?? ''; ?>';
 const BASE_URL = '<?php echo BASE_URL; ?>';
 let cart = [];
 let currentProduct = null;
 let selectedVariant = null;
 let selectedExtras = [];
 let excludedIngredients = [];
 let quantity = 1;

 document.addEventListener('DOMContentLoaded', function() {
 loadCart();
 updateCart();
 });

 const categoryHierarchy = <?php
 $hierarchy = [];
 foreach ($categories ?? [] as $cat) {
 $hierarchy[$cat['category_id']] = [
 'name' => $cat['name'],
 'parent_id' => $cat['parent_id'] ?? null,
 'category_id' => $cat['category_id']
 ];
 }
 echo json_encode($hierarchy, JSON_UNESCAPED_UNICODE);
 ?>;

 const parentToChildren = {};
 Object.values(categoryHierarchy).forEach(cat => {
 if (cat.parent_id) {
 if (!parentToChildren[cat.parent_id]) {
 parentToChildren[cat.parent_id] = [];
 }
 parentToChildren[cat.parent_id].push(cat.category_id);
 }
 });

 function filterCategory(categoryId) {
 const chips = document.querySelectorAll('.q-chip');
 const sections = document.querySelectorAll('.q-category-section');

 chips.forEach(chip => {
 if (chip.dataset.category === categoryId) {
 chip.classList.add('q-chip--active');
 } else {
 chip.classList.remove('q-chip--active');
 }
 });

 if (categoryId === 'all') {
 sections.forEach(section => section.style.display = 'block');
 } else {
 const categoryBtn = document.querySelector(`.q-chip[data-category="${categoryId}"]`);
 const isParent = categoryBtn && categoryBtn.dataset.isChild === '0';

 sections.forEach(section => {
 const sectionCategoryId = section.dataset.category;
 if (sectionCategoryId === categoryId) {
 section.style.display = 'block';
 } else if (isParent) {
 const sectionBtn = document.querySelector(`.q-chip[data-category="${sectionCategoryId}"]`);
 if (sectionBtn && sectionBtn.dataset.parentId === categoryId) {
 section.style.display = 'block';
 } else {
 section.style.display = 'none';
 }
 } else {
 section.style.display = 'none';
 }
 });
 }
 }

 function openProductModal(product) {
 currentProduct = product;
 selectedVariant = null;
 selectedExtras = [];
 excludedIngredients = [];
 quantity = 1;

 const modal = document.getElementById('product-modal');
 const header = document.getElementById('modal-header');
 const content = document.getElementById('modal-content');

 const variants = product.variants || [];
 const ingredients = Array.isArray(product.ingredients) ? product.ingredients : (product.ingredients ? JSON.parse(product.ingredients) : []);
 const extras = Array.isArray(product.available_extras) ? product.available_extras : (product.available_extras ? JSON.parse(product.available_extras) : []);

 if (variants.length > 0) {
 selectedVariant = variants.find(v => v.is_default == 1) || variants[0];
 }

 header.innerHTML = `
 <div class="q-toolbar q-toolbar--start">
 ${product.image_url ? `
 <img src="${product.image_url}" alt="${product.name}" class="q-product-modal-img">
 ` : `
 <div class="q-product-modal-placeholder">🍽️</div>
 `}
 <div>
 <h3 class="q-card__title">${product.name}</h3>
 ${product.description ? `<p class="q-hint">${product.description}</p>` : ''}
 <p class="q-text-metric q-text-brand q-mt-2">${formatCurrency(product.price)}</p>
 </div>
 </div>
 `;

 let contentHtml = '';

 if (variants.length > 0) {
 contentHtml += `
 <div class="q-stack">
 <h4 class="q-text-label">Seçenekler *</h4>
 <div class="q-stack q-stack--sm">
 ${variants.map(v => {
 const isSelected = selectedVariant && selectedVariant.variant_id === v.variant_id;
 const variantPrice = parseFloat(product.price) + parseFloat(v.price_modifier || 0);
 return `
 <button type="button" onclick="selectVariant('${v.variant_id}')" class="q-modal-option ${isSelected ? 'q-brand-border' : ''}" data-variant="${v.variant_id}" aria-pressed="${isSelected}">
 <div class="q-toolbar">
 <div class="q-radio ${isSelected ? 'q-radio--selected' : ''}" aria-hidden="true">${isSelected ? '<span class="q-radio__dot"></span>' : ''}</div>
 <span class="q-text-label">${v.name}</span>
 </div>
 <span class="q-text-metric q-text-brand">${formatCurrency(variantPrice)}</span>
 </button>
 `;
 }).join('')}
 </div>
 </div>
 `;
 }

 if (ingredients.length > 0) {
 contentHtml += `
 <div class="q-stack">
 <h4 class="q-text-label">Çıkarılacak Malzemeler</h4>
 <p class="q-hint">İstemediğiniz malzemeleri seçin</p>
 <div class="q-chip-row">
 ${ingredients.map(ing => `
 <button type="button" onclick="toggleIngredient('${ing}')" class="q-chip q-chip--toggle" data-ingredient="${ing}" aria-pressed="false">${ing}</button>
 `).join('')}
 </div>
 </div>
 `;
 }

 if (extras.length > 0) {
 contentHtml += `
 <div class="q-stack">
 <h4 class="q-text-label">Ekstralar</h4>
 <div class="q-stack q-stack--sm">
 ${extras.map(ext => {
 const extName = ext.name || ext;
 const extPrice = ext.price || 0;
 return `
 <button type="button" onclick="toggleExtra('${extName}', ${extPrice})" class="q-modal-option" data-extra="${extName}" aria-pressed="false">
 <div class="q-toolbar">
 <div class="q-checkbox" data-checkbox aria-hidden="true"><span class="q-checkbox__mark q-hidden">✓</span></div>
 <span class="q-text-label">${extName}</span>
 </div>
 <span class="q-text-metric q-text-brand">+${formatCurrency(extPrice)}</span>
 </button>
 `;
 }).join('')}
 </div>
 </div>
 `;
 }

 contentHtml += `
 <div class="q-stack">
 <h4 class="q-text-label">Adet</h4>
 <div class="q-toolbar q-toolbar--center q-gap-3">
 <button type="button" onclick="changeQuantity(-1)" class="q-icon-btn q-icon-btn--lg q-bg-brand-gradient" aria-label="Azalt">−</button>
 <span class="q-text-metric" id="modal-quantity">1</span>
 <button type="button" onclick="changeQuantity(1)" class="q-icon-btn q-icon-btn--lg q-bg-brand-gradient" aria-label="Arttır">+</button>
 </div>
 </div>
 `;

 content.innerHTML = contentHtml;
 updateModalTotal();
 modal.classList.remove('q-hidden');
 }

 function closeProductModal() {
 document.getElementById('product-modal').classList.add('q-hidden');
 }

 function selectVariant(variantId) {
 if (!currentProduct || !currentProduct.variants) return;
 selectedVariant = currentProduct.variants.find(v => v.variant_id === variantId);

 document.querySelectorAll('[data-variant]').forEach(btn => {
 const isSelected = btn.dataset.variant === variantId;
 btn.classList.toggle('q-brand-border', isSelected);
 btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
 const radio = btn.querySelector('.q-radio');
 if (radio) {
 radio.classList.toggle('q-radio--selected', isSelected);
 radio.innerHTML = isSelected ? '<span class="q-radio__dot"></span>' : '';
 }
 });

 updateModalTotal();
 }

 function toggleIngredient(ingredient) {
 const index = excludedIngredients.indexOf(ingredient);
 if (index > -1) {
 excludedIngredients.splice(index, 1);
 } else {
 excludedIngredients.push(ingredient);
 }

 const btn = document.querySelector(`[data-ingredient="${ingredient}"]`);
 if (btn) {
 const isExcluded = excludedIngredients.includes(ingredient);
 btn.classList.toggle('q-danger-border', isExcluded);
 btn.classList.toggle('q-chip--excluded', isExcluded);
 btn.setAttribute('aria-pressed', isExcluded ? 'true' : 'false');
 }
 }

 function toggleExtra(extraName, extraPrice) {
 const index = selectedExtras.findIndex(e => e.name === extraName);
 if (index > -1) {
 selectedExtras.splice(index, 1);
 } else {
 selectedExtras.push({ name: extraName, price: extraPrice });
 }

 const btn = document.querySelector(`[data-extra="${extraName}"]`);
 if (btn) {
 const checkbox = btn.querySelector('[data-checkbox]');
 const checkmark = checkbox ? checkbox.querySelector('span') : null;
 const isSelected = selectedExtras.some(e => e.name === extraName);
 btn.classList.toggle('q-brand-border', isSelected);
 btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
 if (checkbox) {
 checkbox.classList.toggle('q-checkbox--checked', isSelected);
 }
 if (checkmark) {
 checkmark.classList.toggle('q-hidden', !isSelected);
 }
 }

 updateModalTotal();
 }

 function changeQuantity(delta) {
 quantity = Math.max(1, quantity + delta);
 document.getElementById('modal-quantity').textContent = quantity;
 updateModalTotal();
 }

 function updateModalTotal() {
 if (!currentProduct) return;

 let basePrice = parseFloat(currentProduct.price);
 if (selectedVariant) {
 basePrice += parseFloat(selectedVariant.price_modifier || 0);
 }

 let extrasPrice = selectedExtras.reduce((sum, ext) => sum + parseFloat(ext.price || 0), 0);
 let total = (basePrice + extrasPrice) * quantity;

 document.getElementById('modal-total-price').textContent = formatCurrency(total);
 }

 function addToCartFromModal() {
 if (!currentProduct) return;

 const cartItem = {
 menu_item_id: currentProduct.menu_item_id,
 name: currentProduct.name,
 price: parseFloat(currentProduct.price),
 quantity: quantity,
 variant: selectedVariant,
 extras: selectedExtras,
 excluded_ingredients: excludedIngredients,
 image_url: currentProduct.image_url
 };

 cart.push(cartItem);
 saveCart();
 updateCart();
 closeProductModal();

 if (window.NotificationManager) { window.NotificationManager.success('Ürün sepete eklendi!'); }
 }

 function loadCart() {
 const saved = localStorage.getItem('cart_' + TABLE_ID);
 if (saved) {
 cart = JSON.parse(saved);
 }
 }

 function saveCart() {
 localStorage.setItem('cart_' + TABLE_ID, JSON.stringify(cart));
 }

 function updateCart() {
 const badge = document.getElementById('cart-badge');
 const floatingCart = document.getElementById('floating-cart');
 const floatingTotal = document.getElementById('floating-cart-total');
 const floatingItems = document.getElementById('floating-cart-items');
 const cartTotal = document.getElementById('cart-total');
 const placeOrderBtn = document.getElementById('place-order-btn');

 const itemCount = cart.reduce((sum, item) => sum + item.quantity, 0);
 const total = cart.reduce((sum, item) => {
 let itemPrice = item.price;
 if (item.variant) itemPrice += parseFloat(item.variant.price_modifier || 0);
 if (item.extras) itemPrice += item.extras.reduce((s, e) => s + parseFloat(e.price || 0), 0);
 return sum + (itemPrice * item.quantity);
 }, 0);

 if (itemCount > 0) {
 badge.textContent = itemCount;
 badge.classList.remove('q-hidden');
 floatingCart.classList.remove('q-hidden');
 floatingTotal.textContent = formatCurrency(total);
 floatingItems.textContent = itemCount + ' Ürün';
 placeOrderBtn.disabled = false;
 } else {
 badge.classList.add('q-hidden');
 floatingCart.classList.add('q-hidden');
 placeOrderBtn.disabled = true;
 }

 if (cartTotal) {
 cartTotal.textContent = formatCurrency(total);
 }

 renderCart();
 }

 function renderCart() {
 const container = document.getElementById('cart-items');
 if (cart.length === 0) {
 container.innerHTML = `
 <div class="q-empty q-empty--inline">
 <div class="q-empty__icon-wrapper">🛒</div>
 <p class="q-empty__title">Sepetiniz boş</p>
 </div>
 `;
 return;
 }

 container.innerHTML = cart.map((item, index) => {
 let itemPrice = item.price;
 if (item.variant) itemPrice += parseFloat(item.variant.price_modifier || 0);
 if (item.extras) itemPrice += item.extras.reduce((s, e) => s + parseFloat(e.price || 0), 0);
 const totalPrice = itemPrice * item.quantity;

 return `
 <article class="q-card q-card--pad q-card--hover">
 <div class="q-toolbar q-toolbar--start">
 ${item.image_url ? `
 <img src="${item.image_url}" alt="${item.name}" class="q-cart-item-img">
 ` : `
 <div class="q-cart-item-placeholder">🍽️</div>
 `}
 <div class="q-flex-1">
 <h4 class="q-text-label">${item.name}</h4>
 ${item.variant ? `<p class="q-hint">${item.variant.name}</p>` : ''}
 ${item.extras && item.extras.length > 0 ? `<p class="q-hint">+${item.extras.map(e => e.name).join(', ')}</p>` : ''}
 ${item.excluded_ingredients && item.excluded_ingredients.length > 0 ? `<p class="q-text-status-danger">-${item.excluded_ingredients.join(', ')}</p>` : ''}
 <div class="q-toolbar q-toolbar--between q-mt-2">
 <div class="q-toolbar">
 <button type="button" onclick="updateCartQuantity(${index}, -1)" class="q-icon-btn q-icon-btn--sm" aria-label="Azalt">−</button>
 <span class="q-text-label">${item.quantity}</span>
 <button type="button" onclick="updateCartQuantity(${index}, 1)" class="q-icon-btn q-icon-btn--sm" aria-label="Arttır">+</button>
 </div>
 <span class="q-text-metric q-text-brand">${formatCurrency(totalPrice)}</span>
 </div>
 </div>
 <button type="button" onclick="removeFromCart(${index})" class="q-icon-btn" aria-label="Sepetten çıkar">🗑️</button>
 </div>
 </article>
 `;
 }).join('');
 }

 function updateCartQuantity(index, delta) {
 cart[index].quantity = Math.max(1, cart[index].quantity + delta);
 saveCart();
 updateCart();
 }

 function removeFromCart(index) {
 cart.splice(index, 1);
 saveCart();
 updateCart();
 }

 function openCart() {
 document.getElementById('cart-modal').classList.remove('q-hidden');
 }

 function closeCart() {
 document.getElementById('cart-modal').classList.add('q-hidden');
 }

 function getCSRFToken() {
 const metaTag = document.querySelector('meta[name="csrf-token"]');
 if (metaTag && metaTag.content) {
 return metaTag.content;
 }
 if (typeof window !== 'undefined' && window.CSRF_TOKEN) {
 return window.CSRF_TOKEN;
 }
 console.error('CSRF token not found');
 return null;
 }

 let isSubmittingOrder = false;
 let isCallingWaiter = false;
 let isRequestingBill = false;

 async function placeOrder() {
 if (cart.length === 0) return;

 if (isSubmittingOrder) {
 if (window.NotificationManager) { window.NotificationManager.warning('⏳ Sipariş gönderiliyor, lütfen bekleyin...'); }
 return;
 }

 const csrfToken = getCSRFToken();
 if (!csrfToken) {
 if (window.NotificationManager) { window.NotificationManager.error('❌ Güvenlik hatası: Token bulunamadı. Lütfen sayfayı yenileyin.'); }
 return;
 }

 isSubmittingOrder = true;

 const note = document.getElementById('order-note').value;
 const btn = document.getElementById('place-order-btn');
 const originalText = btn.textContent;

 btn.disabled = true;
 btn.textContent = 'Gönderiliyor...';
 btn.classList.add('q-btn--loading');

 const cartBackup = [...cart];

 cart = [];
 saveCart();
 updateCart();

 try {
 const response = await fetch(BASE_URL + '/api/place-order', {
 method: 'POST',
 headers: {
 'Content-Type': 'application/json',
 'X-CSRF-Token': csrfToken,
 'Accept': 'application/json'
 },
 credentials: 'same-origin',
 body: JSON.stringify({
 table_id: TABLE_ID,
 items: cartBackup,
 customer_note: note
 })
 });

 const result = await response.json();

 if (result.success) {
 closeCart();
 if (window.NotificationManager) { window.NotificationManager.success('✅ Siparişiniz alındı!'); }
 } else {
 cart = cartBackup;
 saveCart();
 updateCart();
 if (window.NotificationManager) { window.NotificationManager.error('❌ Hata: ' + (result.message || result.error || 'Sipariş gönderilemedi')); }
 }
 } catch (error) {
 console.error('Order error:', error);
 cart = cartBackup;
 saveCart();
 updateCart();
 if (window.NotificationManager) { window.NotificationManager.error('❌ Bağlantı hatası: ' + (error.message || 'Bilinmeyen hata')); }
 } finally {
 isSubmittingOrder = false;
 btn.disabled = cart.length === 0;
 btn.textContent = originalText;
 btn.classList.remove('q-btn--loading');
 }
 }

 async function callWaiter() {
 if (isCallingWaiter) {
 if (window.NotificationManager) { window.NotificationManager.warning('⏳ Garson çağrılıyor, lütfen bekleyin...'); }
 return;
 }

 const csrfToken = getCSRFToken();
 if (!csrfToken) {
 if (window.NotificationManager) { window.NotificationManager.error('❌ Güvenlik hatası: Token bulunamadı. Lütfen sayfayı yenileyin.'); }
 return;
 }

 isCallingWaiter = true;
 if (window.NotificationManager) { window.NotificationManager.info('👋 Garson çağrılıyor...'); }

 try {
 const response = await fetch(BASE_URL + '/api/call-waiter', {
 method: 'POST',
 headers: {
 'Content-Type': 'application/json',
 'X-CSRF-Token': csrfToken,
 'Accept': 'application/json'
 },
 credentials: 'same-origin',
 body: JSON.stringify({
 table_id: TABLE_ID,
 type: 'CALL_WAITER'
 })
 });
 const result = await response.json();
 if (window.NotificationManager) { result.success ? window.NotificationManager.success('✅ Garson çağrıldı!') : window.NotificationManager.error('❌ Hata: ' + (result.error || result.message || 'İşlem başarısız')); }
 } catch (error) {
 console.error('Call waiter error:', error);
 if (window.NotificationManager) { window.NotificationManager.error('❌ Bağlantı hatası: ' + (error.message || 'Bilinmeyen hata')); }
 } finally {
 setTimeout(() => { isCallingWaiter = false; }, 3000);
 }
 }

 async function requestBill() {
 if (isRequestingBill) {
 if (window.NotificationManager) { window.NotificationManager.warning('⏳ Hesap isteniyor, lütfen bekleyin...'); }
 return;
 }

 const csrfToken = getCSRFToken();
 if (!csrfToken) {
 if (window.NotificationManager) { window.NotificationManager.error('❌ Güvenlik hatası: Token bulunamadı. Lütfen sayfayı yenileyin.'); }
 return;
 }

 isRequestingBill = true;
 if (window.NotificationManager) { window.NotificationManager.info('💳 Hesap isteniyor...'); }

 try {
 const response = await fetch(BASE_URL + '/api/request-bill', {
 method: 'POST',
 headers: {
 'Content-Type': 'application/json',
 'X-CSRF-Token': csrfToken,
 'Accept': 'application/json'
 },
 credentials: 'same-origin',
 body: JSON.stringify({ table_id: TABLE_ID })
 });
 const result = await response.json();
 if (window.NotificationManager) { result.success ? window.NotificationManager.success('✅ Hesap istendi!') : window.NotificationManager.error('❌ Hata: ' + (result.error || result.message || 'İşlem başarısız')); }
 } catch (error) {
 console.error('Request bill error:', error);
 if (window.NotificationManager) { window.NotificationManager.error('❌ Bağlantı hatası: ' + (error.message || 'Bilinmeyen hata')); }
 } finally {
 setTimeout(() => { isRequestingBill = false; }, 5000);
 }
 }

 function formatCurrency(amount) {
 return parseFloat(amount).toFixed(2) + ' ₺';
 }
 </script>
</body>
</html>
