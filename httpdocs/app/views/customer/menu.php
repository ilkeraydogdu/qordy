<?php
$title = 'Menü - ' . getAppConfig()->getAppName();
require_once __DIR__ . '/../partials/icons.php';
include __DIR__ . '/../layouts/header.php';

// Group menu items by category
$menuByCategory = [];
foreach ($menu_items ?? [] as $item) {
    if (!$item['is_available']) continue;
    $catId = $item['category_id'] ?? '';
    if (!isset($menuByCategory[$catId])) {
        $menuByCategory[$catId] = [];
    }
    $menuByCategory[$catId][] = $item;
}

// Sort menu items A-Z within each category (Turkish character support)
foreach ($menuByCategory as $catId => &$items) {
    usort($items, function($a, $b) {
        $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
        $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
        return strcoll($nameA, $nameB);
    });
}
unset($items); // Unset reference to avoid issues

// Build category hierarchy for proper parent-child handling
$parentToChildren = [];
$childrenToParent = [];
$categoryMap = [];
foreach ($categories ?? [] as $category) {
    $catId = $category['category_id'];
    $parentId = $category['parent_id'] ?? null;
    $categoryMap[$catId] = $category;
    
    if ($parentId) {
        if (!isset($parentToChildren[$parentId])) {
            $parentToChildren[$parentId] = [];
        }
        $parentToChildren[$parentId][] = $catId;
        $childrenToParent[$catId] = $parentId;
    }
}

// Separate parent and child categories
$parentCategories = [];
$childCategories = [];
foreach ($categories ?? [] as $category) {
    if (empty($category['parent_id'])) {
        $parentCategories[] = $category;
    } else {
        // Only add child if parent is also in the list (has products)
        if (isset($categoryMap[$category['parent_id']])) {
            $childCategories[] = $category;
        }
    }
}

// Sort parent categories by display_order (with name as fallback)
usort($parentCategories, function($a, $b) {
    $orderA = $a['display_order'] ?? 9999;
    $orderB = $b['display_order'] ?? 9999;
    if ($orderA === $orderB) {
        $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
        $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
        return strcoll($nameA, $nameB);
    }
    return $orderA - $orderB;
});

// Sort: parents first (by display_order), then children right after their parent (by display_order)
$displayCategories = [];
foreach ($parentCategories as $parent) {
    $displayCategories[] = $parent;
    // Add children of this parent right after (sorted by display_order)
    if (isset($parentToChildren[$parent['category_id']])) {
        $children = [];
        foreach ($parentToChildren[$parent['category_id']] as $childId) {
            if (isset($categoryMap[$childId])) {
                $children[] = $categoryMap[$childId];
            }
        }
        // Sort children by display_order
        usort($children, function($a, $b) {
            $orderA = $a['display_order'] ?? 9999;
            $orderB = $b['display_order'] ?? 9999;
            if ($orderA === $orderB) {
                $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
                $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
                return strcoll($nameA, $nameB);
            }
            return $orderA - $orderB;
        });
        foreach ($children as $child) {
            $displayCategories[] = $child;
        }
    }
}

// Also add standalone child categories (parent not in list)
foreach ($categories ?? [] as $category) {
    if (!empty($category['parent_id']) && !isset($categoryMap[$category['parent_id']])) {
        // Parent doesn't have products, show child as standalone
        if (!in_array($category, $displayCategories)) {
            $displayCategories[] = $category;
        }
    }
}

// Get first category ID for default selection
$firstCategoryId = !empty($displayCategories) ? ($displayCategories[0]['category_id'] ?? '') : '';
?>

<?php 
$queryParams = \App\Core\RequestParser::getQueryParams();
$errorCode = $queryParams['error'] ?? '';

// Handle critical errors with full page error display
if (in_array($errorCode, ['load_failed', 'table_not_found', 'access_denied', 'invalid_subdomain'])) {
    $errorMessages = [
        'load_failed' => [
            'title' => 'Sayfa Yüklenemedi',
            'message' => 'Menü yüklenirken bir sorun oluştu. Lütfen QR kodu tekrar okutun veya sayfayı yenileyin.',
            'icon' => '⚠️'
        ],
        'table_not_found' => [
            'title' => 'Masa Bulunamadı',
            'message' => 'Bu masa sistemde kayıtlı değil. Lütfen doğru QR kodu okuttuğunuzdan emin olun.',
            'icon' => '🔍'
        ],
        'access_denied' => [
            'title' => 'Erişim Engellendi',
            'message' => 'Bu menüye erişim izniniz yok. Lütfen doğru QR kodu kullandığınızdan emin olun.',
            'icon' => '🚫'
        ],
        'invalid_subdomain' => [
            'title' => 'İşletme Bulunamadı',
            'message' => 'Bu adres için kayıtlı bir işletme bulunamadı.',
            'icon' => '❓'
        ]
    ];
    
    $error = $errorMessages[$errorCode];
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($error['title']); ?> - <?php echo getAppConfig()->getAppName(); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-card {
                background: white;
                border-radius: 24px;
                padding: 48px 32px;
                max-width: 400px;
                width: 100%;
                text-align: center;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }
            .error-icon {
                font-size: 64px;
                margin-bottom: 24px;
            }
            .error-title {
                font-size: 24px;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 12px;
            }
            .error-message {
                font-size: 16px;
                color: #6b7280;
                line-height: 1.6;
                margin-bottom: 32px;
            }
            .retry-btn {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                font-weight: 600;
                padding: 14px 32px;
                border-radius: 12px;
                text-decoration: none;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .retry-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.5);
            }
            .help-text {
                margin-top: 24px;
                font-size: 14px;
                color: #9ca3af;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon"><?php echo $error['icon']; ?></div>
            <h1 class="error-title"><?php echo htmlspecialchars($error['title']); ?></h1>
            <p class="error-message"><?php echo htmlspecialchars($error['message']); ?></p>
            <a href="javascript:location.reload()" class="retry-btn">Tekrar Dene</a>
            <p class="help-text">Sorun devam ederse personelden yardım isteyebilirsiniz.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<div class="min-h-screen bg-slate-50 pb-24 w-full max-w-full overflow-x-hidden">
    <?php if ($errorCode === 'invalid_qr'): ?>
        <!-- Error Message -->
        <div class="bg-red-50 border-l-4 border-red-500 p-4 m-4 rounded-lg">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <?php echo icon_alert_triangle(['class' => 'w-6 h-6 text-red-500']); ?>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-bold text-red-800">Geçersiz QR Kod</h3>
                    <p class="text-sm text-red-700 mt-1">QR kodunuz geçersiz veya süresi dolmuş. Lütfen masanızdaki QR kodu tekrar okutun.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($table) && !empty($table)): ?>
        <!-- Table Header -->
        <div class="bg-primary-600 text-white p-4 sticky top-0 z-20">
            <div class="container mx-auto">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <?php if (!empty($business_logo_path)): ?>
                            <div class="w-12 h-12 flex items-center justify-center shrink-0 bg-white rounded-lg p-1">
                                <img src="<?php echo htmlspecialchars(BASE_URL . $business_logo_path, ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="İşletme Logosu" 
                                     class="w-full h-full object-contain"
                                     onerror="this.style.display='none';">
                            </div>
                        <?php endif; ?>
                        <div>
                            <h1 class="text-xl font-bold"><?php echo htmlspecialchars($table['name']); ?></h1>
                            <p class="text-primary-100 text-sm">Masa <?php echo htmlspecialchars($table['name']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="window.API?.callWaiter('<?php echo htmlspecialchars($table['table_id']); ?>', 'CALL_WAITER')" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-colors flex items-center space-x-2">
                            <?php echo icon_hand(['class' => 'w-5 h-5']); ?>
                            <span><?php echo t('waiter.call', 'Call Waiter'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Category Tabs -->
    <?php if (!empty($displayCategories)): ?>
        <div class="bg-white/80 backdrop-blur-md border-b sticky top-16 z-10 px-4 py-3">
            <div class="container mx-auto">
                <div class="flex gap-3 overflow-x-auto no-scrollbar" id="category-tabs">
                    <?php foreach ($displayCategories as $index => $category): 
                        $isChild = isset($childrenToParent[$category['category_id']]);
                    ?>
                        <button 
                            onclick="selectCategory('<?php echo htmlspecialchars($category['category_id']); ?>')"
                            class="category-tab px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest whitespace-nowrap transition-all flex items-center gap-2 <?php echo ($index === 0) ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-50 text-slate-400'; ?> <?php echo $isChild ? 'opacity-90 text-sm' : ''; ?>"
                            data-category="<?php echo htmlspecialchars($category['category_id']); ?>"
                            id="tab-<?php echo htmlspecialchars($category['category_id']); ?>"
                            data-is-child="<?php echo $isChild ? '1' : '0'; ?>"
                        >
                            <?php if ($isChild): ?>
                                <span class="text-[10px] opacity-60">└</span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($category['name']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Menu Items -->
    <div class="container mx-auto px-3 sm:px-4 md:px-6 py-4 sm:py-5 md:py-6 w-full max-w-full">
        <?php 
        // Build category info map
        $categoryInfoMap = [];
        foreach ($categories ?? [] as $category) {
            $categoryInfoMap[$category['category_id']] = [
                'name' => $category['name'],
                'parent_id' => $category['parent_id'] ?? null,
                'has_children' => isset($parentToChildren[$category['category_id']])
            ];
        }
        
        foreach ($displayCategories as $index => $displayCategory): 
            $displayCategoryId = $displayCategory['category_id'];
            
            // Get items for this specific category only (children have their own sections)
            $categoryItems = $menuByCategory[$displayCategoryId] ?? [];
            
            // Only show if there are items
            if (empty($categoryItems)) continue;
            
            $isChild = isset($childrenToParent[$displayCategoryId]);
            $parentInfo = $isChild && isset($categoryInfoMap[$childrenToParent[$displayCategoryId]]) 
                ? $categoryInfoMap[$childrenToParent[$displayCategoryId]] 
                : null;
        ?>
            <div 
                class="category-section space-y-3 sm:space-y-4 md:space-y-5 <?php echo ($index === 0) ? '' : 'hidden'; ?>" 
                id="section-<?php echo htmlspecialchars($displayCategoryId); ?>"
                data-category="<?php echo htmlspecialchars($displayCategoryId); ?>"
            >
                <?php if ($isChild && $parentInfo): ?>
                    <!-- Child Category Header -->
                    <div class="mb-4 mt-2">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-500 uppercase tracking-wide"><?php echo htmlspecialchars($parentInfo['name']); ?></span>
                            <span class="text-xs text-gray-400">›</span>
                            <h2 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($displayCategory['name']); ?></h2>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Parent Category Header -->
                    <div class="mb-4">
                        <h2 class="text-xl sm:text-2xl font-black text-gray-900 mb-2"><?php echo htmlspecialchars($displayCategory['name']); ?></h2>
                        <?php if (isset($parentToChildren[$displayCategoryId]) && count($parentToChildren[$displayCategoryId]) > 0): ?>
                            <p class="text-sm text-gray-500">
                                <?php echo count($parentToChildren[$displayCategoryId]); ?> alt kategori
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($categoryItems as $item): ?>
                    <div class="btn-touch bg-white rounded-xl sm:rounded-2xl shadow-soft overflow-hidden animate-slide-up">
                        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 p-3 sm:p-4 md:p-5">
                            <?php if (!empty($item['image_url'])): ?>
                                <img 
                                    src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                    alt="<?php echo htmlspecialchars($item['name']); ?>"
                                    class="w-24 h-24 rounded-xl object-cover flex-shrink-0"
                                >
                            <?php else: ?>
                                <div class="w-24 h-24 rounded-xl bg-gray-200 flex items-center justify-center flex-shrink-0">
                                    <?php echo icon_utensils(['class' => 'w-10 h-10 text-gray-400']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-lg text-gray-800 mb-1"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="text-sm text-gray-600 mb-2 line-clamp-2"><?php echo htmlspecialchars($item['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="flex items-center justify-between mt-3">
                                    <span class="text-xl font-black text-primary-600">
                                        <?php echo formatCurrency($item['price']); ?>
                                    </span>
                                    <button 
                                        onclick="openItemModal(<?php echo htmlspecialchars(json_encode($item)); ?>)"
                                        class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors"
                                    >
                                        Sepete Ekle
                                    </button>
                                </div>
                                
                                <?php if (isset($item['stock']) && $item['stock'] <= 0): ?>
                                    <div class="mt-2">
                                        <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded">TÜKENDİ</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Cart Floating Button -->
    <div id="cart-floating-btn" class="fixed bottom-6 right-6 z-30">
        <button 
            onclick="openCartModal()"
            class="bg-primary-600 hover:bg-primary-700 text-white rounded-full p-4 shadow-xl hover:shadow-2xl transition-all animate-bounce-soft"
        >
            <?php echo icon_receipt(['class' => 'w-6 h-6']); ?>
            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center" id="cart-badge">0</span>
        </button>
        <div class="mt-2 text-center">
            <span class="text-sm font-bold text-gray-800" id="cart-total-display">0 ₺</span>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div id="item-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-end">
    <div class="bg-white rounded-t-3xl w-full max-h-[90vh] overflow-y-auto animate-slide-up" id="item-modal-content">
        <!-- Content will be populated by JavaScript -->
    </div>
</div>

<!-- Cart Modal -->
<div id="cart-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-end">
    <div class="bg-white rounded-t-3xl w-full max-h-[90vh] overflow-y-auto animate-slide-up">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-black text-gray-800">Sepetim</h2>
                <button onclick="closeCartModal()" class="text-gray-500 hover:text-gray-700">
                    <?php echo icon_x(['class' => 'w-6 h-6']); ?>
                </button>
            </div>
            
            <div id="cart-items-container" class="space-y-3 mb-6">
                <!-- Cart items will be populated here -->
            </div>
            
            <div class="border-t pt-4 mb-6">
                <div class="flex justify-between items-center text-xl font-black">
                    <span>Toplam:</span>
                    <span class="text-primary-600" id="cart-modal-total">0 ₺</span>
                </div>
            </div>
            
            <textarea 
                id="order-note" 
                placeholder="Özel not (isteğe bağlı)"
                class="w-full p-3 border-2 border-gray-300 rounded-lg mb-4 focus:border-primary-500 outline-none"
                rows="3"
            ></textarea>
            
            <button 
                onclick="placeOrder()"
                class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 rounded-lg transition-colors"
                id="place-order-btn"
            >
                Siparişi Onayla
            </button>
        </div>
    </div>
</div>

<script>
// Initialize cart
document.addEventListener('DOMContentLoaded', function() {
    // Select first category by default
    <?php if ($firstCategoryId): ?>
        selectCategory('<?php echo htmlspecialchars($firstCategoryId); ?>');
    <?php endif; ?>
    
    // Update cart display
    updateCartDisplay();
    
    // Listen for cart updates
    window.addEventListener('cart-update', function() {
        updateCartDisplay();
    });
});

function selectCategory(categoryId) {
    // Update active tab
    document.querySelectorAll('.category-tab').forEach(tab => {
        if (tab.dataset.category === categoryId) {
            tab.classList.add('bg-slate-900', 'text-white', 'shadow-lg');
            tab.classList.remove('bg-slate-50', 'text-slate-400');
        } else {
            tab.classList.remove('bg-slate-900', 'text-white', 'shadow-lg');
            tab.classList.add('bg-slate-50', 'text-slate-400');
        }
    });
    
    // Show selected category section
    document.querySelectorAll('.category-section').forEach(section => {
        if (section.dataset.category === categoryId) {
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
        }
    });
}

function openItemModal(item) {
    const modal = document.getElementById('item-modal');
    const content = document.getElementById('item-modal-content');
    
    const imageUrl = item.image_url || '';
    const imageHtml = imageUrl 
        ? `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item.name)}" class="w-full h-64 object-cover">`
        : `<div class="w-full h-64 bg-gray-200 flex items-center justify-center">${iconUtensils()}</div>`;
    
    content.innerHTML = `
        <div class="p-6">
            <button onclick="closeItemModal()" class="absolute top-4 right-4 text-white bg-black/50 rounded-full p-2 hover:bg-black/70">
                ${iconX()}
            </button>
            ${imageHtml}
            <div class="mt-4">
                <h3 class="text-2xl font-black text-gray-800 mb-2">${escapeHtml(item.name)}</h3>
                ${item.description ? `<p class="text-gray-600 mb-4">${escapeHtml(item.description)}</p>` : ''}
                <div class="text-3xl font-black text-primary-600 mb-6">${formatCurrency(item.price)}</div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Adet</label>
                    <div class="flex items-center space-x-4">
                        <button onclick="changeQuantity(-1)" class="w-10 h-10 rounded-lg bg-gray-200 hover:bg-gray-300 flex items-center justify-center font-bold">-</button>
                        <span id="item-quantity" class="text-xl font-bold">1</span>
                        <button onclick="changeQuantity(1)" class="w-10 h-10 rounded-lg bg-gray-200 hover:bg-gray-300 flex items-center justify-center font-bold">+</button>
                    </div>
                </div>
                
                <textarea 
                    id="item-note" 
                    placeholder="Özel not (isteğe bağlı)"
                    class="w-full p-3 border-2 border-gray-300 rounded-lg mb-6 focus:border-primary-500 outline-none"
                    rows="3"
                ></textarea>
                
                <button 
                    onclick="addToCartFromModal()"
                    class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 rounded-lg transition-colors"
                >
                    Sepete Ekle
                </button>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    currentModalItem = item;
    currentModalQuantity = 1;
}

function closeItemModal() {
    document.getElementById('item-modal').classList.add('hidden');
    currentModalItem = null;
    currentModalQuantity = 1;
}

let currentModalItem = null;
let currentModalQuantity = 1;

function changeQuantity(delta) {
    currentModalQuantity = Math.max(1, currentModalQuantity + delta);
    document.getElementById('item-quantity').textContent = currentModalQuantity;
}

function addToCartFromModal() {
    if (!currentModalItem) return;
    
    const note = document.getElementById('item-note').value || '';
    
    // Add to cart using Cart.js
    if (window.Cart) {
        window.Cart.addItem(currentModalItem, currentModalQuantity, note, [], []);
        if (window.Toast) {
            window.Toast.success('Ürün sepete eklendi!');
        }
        closeItemModal();
    }
}

function openCartModal() {
    const modal = document.getElementById('cart-modal');
    updateCartModal();
    modal.classList.remove('hidden');
}

function closeCartModal() {
    document.getElementById('cart-modal').classList.add('hidden');
}

function updateCartModal() {
    const container = document.getElementById('cart-items-container');
    const totalEl = document.getElementById('cart-modal-total');
    
    if (!window.Cart) return;
    
    const items = window.Cart.getItems();
    const total = window.Cart.getTotal();
    
    if (items.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">Sepetiniz boş</p>';
        document.getElementById('place-order-btn').disabled = true;
    } else {
        container.innerHTML = items.map(item => `
            <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                <div class="flex-1">
                    <h4 class="font-bold text-gray-800">${escapeHtml(item.name)}</h4>
                    <p class="text-sm text-gray-600">${formatCurrency(item.price)} x ${item.quantity}</p>
                    ${item.note ? `<p class="text-xs text-gray-500 mt-1">Not: ${escapeHtml(item.note)}</p>` : ''}
                </div>
                <div class="text-right">
                    <div class="font-bold text-primary-600 mb-2">${formatCurrency(item.price * item.quantity)}</div>
                    <button onclick="removeFromCart('${item.id}')" class="text-red-500 hover:text-red-700 text-sm">
                        ${iconTrash()}
                    </button>
                </div>
            </div>
        `).join('');
        document.getElementById('place-order-btn').disabled = false;
    }
    
    totalEl.textContent = formatCurrency(total);
}

function removeFromCart(cartItemId) {
    if (window.Cart) {
        window.Cart.removeItem(cartItemId);
        updateCartModal();
        if (window.Toast) {
            window.Toast.info('Ürün sepetten çıkarıldı');
        }
    }
}

function updateCartDisplay() {
    if (!window.Cart) return;
    
    const badge = document.getElementById('cart-badge');
    const totalDisplay = document.getElementById('cart-total-display');
    
    const count = window.Cart.getItemCount();
    const total = window.Cart.getTotal();
    
    badge.textContent = count;
    totalDisplay.textContent = formatCurrency(total);
    
    // Hide badge if empty
    if (count === 0) {
        badge.style.display = 'none';
    } else {
        badge.style.display = 'flex';
    }
}

function placeOrder() {
    if (!window.Cart) return;
    
    const items = window.Cart.getItems();
    if (items.length === 0) {
        if (window.Toast) {
            window.Toast.warning('Sepetiniz boş!');
        }
        return;
    }
    
    const note = document.getElementById('order-note').value || '';
    const tableId = '<?php echo isset($table) ? htmlspecialchars($table['table_id']) : ''; ?>';
    
    if (!tableId && window.Toast) {
        window.Toast.error('Masa bilgisi bulunamadı!');
        return;
    }
    
    // Prepare order data
    const orderData = {
        table_id: tableId,
        items: items,
        customer_note: note
    };
    
    // Send order via API
    if (window.API) {
        window.API.placeOrder(orderData)
            .then(data => {
                if (data.success) {
                    if (window.Toast) {
                        window.Toast.success('Siparişiniz alındı!');
                    }
                    window.Cart.clear();
                    closeCartModal();
                    updateCartDisplay();
                    setTimeout(() => {
                        if (window.NotificationManager) {
                            window.NotificationManager.success('Siparişiniz başarıyla verildi. Teşekkür ederiz!');
                        }
                    }, 500);
                } else {
                    if (window.Toast) {
                        window.Toast.error(data.error || 'Sipariş verilirken bir hata oluştu');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (window.Toast) {
                    window.Toast.error('Bir hata oluştu, lütfen tekrar deneyin.');
                }
            });
    }
}

// Helper functions - Use Utils from utils.js (loaded globally)
// These are aliases for backward compatibility, but prefer using Utils.* directly
const formatCurrency = window.Utils?.formatCurrency || function(amount) {
    return new Intl.NumberFormat('tr-TR', { 
        style: 'currency', 
        currency: '<?php echo getAppConfig()->getCurrency(); ?>'
    }).format(amount);
};

const escapeHtml = window.Utils?.escapeHtml || function(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
};

// Icon functions - Use Utils or modules/icons.js
const iconX = function() {
    return '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
};

const iconUtensils = function() {
    return '<svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>';
};

const iconTrash = function() {
    return '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>';
};

// Close modals when clicking outside
document.getElementById('item-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeItemModal();
    }
});

document.getElementById('cart-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeCartModal();
    }
});
</script>

