<?php
// Modern QR Menu - Getir/Trendyol Yemek Style
// Standalone customer menu view

if (!isset($table) || !is_array($table)) {
    require_once __DIR__ . '/../../helpers/functions.php';
    require_once __DIR__ . '/../../helpers/translations.php';
    $translationService = getTranslationService();
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Hata</title></head><body><h1>Masa bulunamadı</h1><p>Lütfen QR kodu tekrar okutun.</p></body></html>';
    exit;
}

require_once __DIR__ . '/../../helpers/functions.php';
require_once __DIR__ . '/../../core/HelperLoader.php';
\App\Core\HelperLoader::ensureLoaded();
$translationService = getTranslationService();

$isMenuOnly = ($qr_menu_status ?? 'active') === 'menu_only';

// Get business name - prioritize from database
$displayBusinessName = $business_name ?? '';
if (empty($displayBusinessName)) {
    // Try to get from tenant context (database)
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
    } catch (\Exception $e) {
        // Silent fail - continue with fallback
    }
    
    // Fallback to settings
    if (empty($displayBusinessName)) {
        $displayBusinessName = $settings['business_name'] ?? $settings['restaurant_name'] ?? '';
    }
    
    // Last resort: use app name (but this should rarely happen)
    if (empty($displayBusinessName)) {
        $displayBusinessName = getAppConfig()->getAppName();
    }
}

// Logo URL setup
$host = $_SERVER['HTTP_HOST'] ?? '';
$isSubdomain = !in_array($host, ['qordy.com', 'www.qordy.com']);
$publicPrefix = $isSubdomain ? '' : '/public';
$headerLogoUrl = BASE_URL . $publicPrefix . '/assets/images/logo.png';
if (isset($logo_url) && !empty($logo_url)) {
    $logoUrl = trim($logo_url);
    if (preg_match('/^https?:\/\//', $logoUrl)) {
        $headerLogoUrl = $logoUrl;
    } elseif (strpos($logoUrl, '/') === 0) {
        $headerLogoUrl = BASE_URL . $publicPrefix . $logoUrl;
    } else {
        $headerLogoUrl = BASE_URL . $publicPrefix . '/' . ltrim($logoUrl, '/');
    }
}

// Build category data
$categoryMap = [];
$parentToChildren = [];
$childrenToParent = [];
$displayCategories = [];

if (!empty($categories) && is_array($categories)) {
    foreach ($categories as $category) {
        if (empty($category['category_id'])) continue;
        $categoryMap[$category['category_id']] = $category;
        $parentId = $category['parent_id'] ?? null;
        if ($parentId) {
            if (!isset($parentToChildren[$parentId])) {
                $parentToChildren[$parentId] = [];
            }
            $parentToChildren[$parentId][] = $category['category_id'];
            $childrenToParent[$category['category_id']] = $parentId;
        }
    }
    
    foreach ($categories as $category) {
        if (empty($category['category_id'])) continue;
        if (empty($category['parent_id'])) {
            $displayCategories[] = $category;
            if (isset($parentToChildren[$category['category_id']])) {
                foreach ($parentToChildren[$category['category_id']] as $childId) {
                    if (isset($categoryMap[$childId])) {
                        $displayCategories[] = $categoryMap[$childId];
                    }
                }
            }
        }
    }
}

// Group items by category
$itemsByCategory = [];
if (!empty($menu_items) && is_array($menu_items)) {
    foreach ($menu_items as $item) {
        if (empty($item['is_available'])) continue;
        $catId = $item['category_id'] ?? 'other';
        if (!isset($itemsByCategory[$catId])) {
            $itemsByCategory[$catId] = [];
        }
        $itemsByCategory[$catId][] = $item;
    }
}

$categoryItemCounts = [];
foreach ($itemsByCategory as $catId => $items) {
    $categoryItemCounts[$catId] = count($items);
}

$firstCategoryId = !empty($displayCategories) ? $displayCategories[0]['category_id'] : '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, viewport-fit=cover">
    <meta name="theme-color" content="#5D3EBC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <?php
    // CSRF Token for JavaScript
    require_once __DIR__ . '/../../core/Security/CSRFManager.php';
    $csrfToken = \App\Core\Security\CSRFManager::generateToken();
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <script>
        window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <title><?php echo htmlspecialchars($displayBusinessName); ?> - Menü</title>
    
    <style>
        /* ========================================
           GETIR / TRENDYOL YEMEK STYLE
           Modern Food Delivery App UI
           ======================================== */
        
        :root {
            --primary: #5D3EBC;
            --primary-light: #7849F7;
            --secondary: #FFD300;
            --success: #00C48C;
            --danger: #FF4757;
            --bg: #FAFAFA;
            --white: #FFFFFF;
            --text-primary: #191919;
            --text-secondary: #697488;
            --text-muted: #9CA4AB;
            --border: #E8E8E8;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 4px 12px rgba(0,0,0,0.15);
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            height: 100%;
            width: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }
        
        .app {
            display: flex;
            flex-direction: column;
            height: 100dvh;
            max-width: 100%;
            background: var(--bg);
        }
        
        /* Header */
        .header {
            background: var(--white);
            padding: 12px 16px;
            padding-top: max(12px, env(safe-area-inset-top));
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }
        
        .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: contain;
            background: var(--bg);
            border: 1px solid var(--border);
        }
        
        .brand-info h1 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        
        .brand-info .table {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .brand-info .table::before {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--success);
            border-radius: 50%;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        
        .action-btn:active {
            transform: scale(0.92);
            background: var(--border);
        }
        
        .action-btn svg {
            width: 20px;
            height: 20px;
            color: var(--text-primary);
        }
        
        /* Categories */
        .categories {
            background: var(--white);
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        
        .categories-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 0 16px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        .categories-scroll::-webkit-scrollbar {
            display: none;
        }
        
        .cat-chip {
            flex-shrink: 0;
            padding: 10px 18px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 600;
            background: var(--bg);
            color: var(--text-secondary);
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .cat-chip.active {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(93, 62, 188, 0.3);
        }
        
        .cat-chip:active {
            transform: scale(0.95);
        }
        
        /* Content */
        .content {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 100px;
        }
        
        .content::-webkit-scrollbar {
            display: none;
        }
        
        /* Section */
        .section {
            padding: 20px 16px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title .count {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
        }
        
        /* Products Grid */
        .products {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        @media (min-width: 480px) {
            .products {
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
            }
        }
        
        /* Product Card */
        .product {
            background: var(--white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .product:active {
            transform: scale(0.97);
        }
        
        .product-img {
            position: relative;
            aspect-ratio: 1;
            background: linear-gradient(135deg, #f5f5f5 0%, #ebebeb 100%);
        }
        
        .product-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-img .no-img {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            opacity: 0.3;
        }
        
        .product-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .product-badge.hot {
            background: var(--danger);
            color: white;
        }
        
        .product-badge.new {
            background: var(--success);
            color: white;
        }
        
        .add-btn {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(93, 62, 188, 0.4);
            transition: all 0.15s;
        }
        
        .add-btn:active {
            transform: scale(0.85);
        }
        
        .add-btn svg {
            width: 20px;
            height: 20px;
        }
        
        .product-body {
            padding: 12px;
        }
        
        .product-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.3;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-price {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Floating Cart */
        .float-cart {
            position: fixed;
            bottom: 16px;
            left: 16px;
            right: 16px;
            background: var(--primary);
            color: white;
            padding: 16px 20px;
            border-radius: var(--radius-lg);
            display: none;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 24px rgba(93, 62, 188, 0.45);
            z-index: 1000;
            cursor: pointer;
            transition: all 0.2s;
            padding-bottom: max(16px, env(safe-area-inset-bottom));
        }
        
        .float-cart.show {
            display: flex;
        }
        
        .float-cart:active {
            transform: scale(0.98);
        }
        
        .float-cart-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .cart-icon-wrap {
            position: relative;
        }
        
        .cart-icon-wrap svg {
            width: 24px;
            height: 24px;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--secondary);
            color: var(--text-primary);
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
        }
        
        .float-cart-text {
            font-size: 15px;
            font-weight: 600;
        }
        
        .float-cart-total {
            font-size: 17px;
            font-weight: 700;
        }
        
        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-sheet {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--white);
            border-radius: 20px 20px 0 0;
            z-index: 2001;
            transform: translateY(100%);
            transition: transform 0.3s ease-out;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        .modal-overlay.open .modal-sheet {
            transform: translateY(0);
        }
        
        .modal-handle {
            width: 40px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            margin: 12px auto;
            flex-shrink: 0;
        }
        
        .modal-header {
            padding: 0 16px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 700;
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
        }
        
        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        .modal-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
            padding-bottom: max(16px, env(safe-area-inset-bottom));
            flex-shrink: 0;
        }
        
        /* Cart Item */
        .cart-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-editable {
            cursor: pointer;
        }
        .cart-item-editable:active {
            background: rgba(0,0,0,0.03);
        }
        
        .cart-item-img {
            width: 64px;
            height: 64px;
            border-radius: 10px;
            overflow: hidden;
            background: var(--bg);
            flex-shrink: 0;
        }
        
        .cart-item-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .cart-item-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .cart-item-price {
            font-size: 15px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .qty-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .qty-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
        }
        
        .qty-btn:active {
            background: var(--bg);
        }
        
        .qty-val {
            font-size: 15px;
            font-weight: 600;
            min-width: 24px;
            text-align: center;
        }
        
        /* Primary Button */
        .btn-primary {
            width: 100%;
            padding: 16px;
            border-radius: var(--radius);
            background: var(--primary);
            color: white;
            font-size: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-primary:active:not(:disabled) {
            transform: scale(0.98);
        }
        
        /* Empty State */
        .empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .empty h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .empty p {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--text-primary);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            z-index: 3000;
            opacity: 0;
            transition: all 0.3s;
        }
        
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        
        /* Product Detail Modal */
        .product-detail-img {
            width: 100%;
            aspect-ratio: 1;
            background: var(--bg);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 16px;
        }
        
        .product-detail-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-detail-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .product-detail-desc {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 16px;
        }
        
        .product-detail-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .detail-qty {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-qty .qty-btn {
            width: 44px;
            height: 44px;
            font-size: 20px;
        }
        
        .detail-qty .qty-val {
            font-size: 20px;
            min-width: 40px;
        }
        
        /* Product Customization Styles */
        .customization-section {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        .customization-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .customization-title .icon {
            font-size: 16px;
        }
        
        /* Variant Buttons */
        .variant-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .variant-btn {
            padding: 10px 16px;
            border-radius: 10px;
            border: 2px solid var(--border);
            background: var(--white);
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        
        .variant-btn.active {
            border-color: var(--primary);
            background: rgba(93, 62, 188, 0.08);
            color: var(--primary);
        }
        
        .variant-btn:active {
            transform: scale(0.95);
        }
        
        .variant-price {
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .variant-btn.active .variant-price {
            color: var(--primary);
        }
        
        /* Ingredient Chips (for removal) */
        .ingredient-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .ingredient-chip {
            padding: 8px 14px;
            border-radius: 20px;
            border: 1.5px solid var(--border);
            background: var(--white);
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .ingredient-chip.removed {
            border-color: var(--danger);
            background: rgba(255, 71, 87, 0.08);
            color: var(--danger);
            text-decoration: line-through;
        }
        
        .ingredient-chip .remove-icon {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
        }
        
        .ingredient-chip.removed .remove-icon {
            background: var(--danger);
            color: white;
        }
        
        /* Extra Items */
        .extra-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .extra-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: var(--white);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .extra-item.selected {
            border-color: var(--success);
            background: rgba(0, 196, 140, 0.08);
        }
        
        .extra-item-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .extra-checkbox {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .extra-item.selected .extra-checkbox {
            border-color: var(--success);
            background: var(--success);
            color: white;
        }
        
        .extra-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .extra-price {
            font-size: 14px;
            font-weight: 700;
            color: var(--success);
        }
        
        /* Note Input */
        .note-input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: var(--bg);
            font-size: 14px;
            color: var(--text-primary);
            resize: none;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        .note-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
        }
        
        .note-input::placeholder {
            color: var(--text-muted);
        }
        
        /* Bottom Tabs */
        .bottom-tabs {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--white);
            border-top: 1px solid var(--border);
            display: flex;
            z-index: 900;
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        .tab-btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 0;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.2s;
            gap: 4px;
        }
        
        .tab-btn.active {
            color: var(--primary);
        }
        
        .tab-btn svg {
            width: 24px;
            height: 24px;
        }
        
        .tab-btn span {
            font-size: 11px;
            font-weight: 600;
        }
        
        .tab-badge {
            position: absolute;
            top: 4px;
            right: 50%;
            transform: translateX(16px);
            background: var(--danger);
            color: white;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }
        
        /* Tab Content */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Orders Section */
        .orders-section {
            padding: 16px;
            padding-bottom: 80px;
        }
        
        .order-card {
            background: var(--white);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
            transition: transform 0.2s ease;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .order-id {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .order-time {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        
        .order-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .order-status-pill.pending { background: #fef3c7; color: #92400e; }
        .order-status-pill.preparing { background: #dbeafe; color: #1e40af; }
        .order-status-pill.ready { background: #d1fae5; color: #065f46; }
        
        .order-id {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .order-time {
            font-size: 12px;
            color: var(--text-muted);
        }
        
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .order-status.pending {
            background: #FFF3CD;
            color: #856404;
        }
        
        .order-status.preparing {
            background: #CCE5FF;
            color: #004085;
        }
        
        .order-status.ready {
            background: #D4EDDA;
            color: #155724;
        }
        
        .order-status.completed {
            background: #E2E3E5;
            color: #383D41;
        }
        
        .order-status.cancelled {
            background: #F8D7DA;
            color: #721C24;
        }
        
        .order-groups {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .order-group {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-secondary, #fafafa);
        }
        .order-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: var(--bg-secondary, #f1f5f9);
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }
        .order-group-name {
            font-weight: 700;
            color: var(--text-primary);
        }
        .group-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .group-status.pending {
            background: #FFF3CD;
            color: #856404;
        }
        .group-status.preparing {
            background: #CCE5FF;
            color: #004085;
        }
        .group-status.ready {
            background: #D4EDDA;
            color: #155724;
        }
        
        .order-items {
            border-top: 1px solid var(--border);
            padding-top: 12px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            font-size: 14px;
            gap: 12px;
        }
        
        .order-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .order-item-name {
            color: var(--text-primary);
        }
        
        .order-item-qty {
            color: var(--text-muted);
            font-weight: 500;
            white-space: nowrap;
        }
        
        .order-item-details {
            margin-top: 4px;
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .order-item-note {
            color: #b45309;
            font-weight: 600;
        }
        
        .order-item-custom {
            color: var(--text-muted);
        }
        
        .cart-order-note {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 56px;
        }
        .cart-order-note:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .order-note {
            margin-top: 8px;
            padding: 8px 10px;
            background: #fff7ed;
            color: #92400e;
            border-radius: 8px;
            font-size: 12px;
        }
        
        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            margin-top: 12px;
            border-top: 1px solid var(--border);
        }
        
        .order-total-label {
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .order-total-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Progress Tracker - çizgi ikonların altında, ikonların üzerine taşmıyor */
        .progress-tracker {
            display: flex;
            justify-content: space-between;
            margin: 16px 0;
            position: relative;
        }
        
        /* Arka plan çizgisi: ikonların hemen altında (ikon 32px + 4px boşluk) */
        .progress-tracker::before {
            content: '';
            position: absolute;
            top: 38px;
            left: 12%;
            right: 12%;
            height: 3px;
            background: var(--border);
            z-index: 0;
        }
        
        /* Doluluk çizgisi: aynı hizada, ikonların üzerine gelmez */
        .progress-tracker::after {
            content: '';
            position: absolute;
            top: 38px;
            left: 12%;
            width: calc(var(--progress-percent, 0%) * 0.76);
            height: 3px;
            background: linear-gradient(to right, var(--success), var(--primary));
            z-index: 1;
            transition: width 0.5s ease;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .progress-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
            box-sizing: border-box;
        }
        
        .progress-step.active .progress-icon {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: scale(1.15);
            box-shadow: 0 2px 8px rgba(93, 62, 188, 0.3);
        }
        
        .progress-step.completed .progress-icon {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        
        .progress-label {
            font-size: 10px;
            color: var(--text-muted);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .progress-step.active .progress-label {
            color: var(--primary);
            font-weight: 700;
            font-size: 11px;
        }
        
        .progress-step.completed .progress-label {
            color: var(--success);
            font-weight: 600;
        }
        
        .progress-tracker .progress-step.completed ~ .progress-step .progress-icon {
            background: var(--bg);
            border-color: var(--border);
        }
        
        .progress-tracker .progress-step.active ~ .progress-step .progress-icon {
            background: var(--bg);
            border-color: var(--border);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
    </style>
</head>
<body>
<div class="app">
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="brand">
                <img src="<?php echo htmlspecialchars($headerLogoUrl); ?>" 
                     alt="<?php echo htmlspecialchars($displayBusinessName); ?>" 
                     class="brand-logo"
                     onerror="this.style.display='none'">
                <div class="brand-info">
                    <h1><?php echo htmlspecialchars($displayBusinessName); ?></h1>
                    <span class="table"><?php echo htmlspecialchars($table['name']); ?></span>
                </div>
            </div>
            <div class="header-actions">
                <?php if (!empty($wifi_show_to_customer) && !empty($wifi_name)): ?>
                <button class="action-btn" onclick="showWifiInfo()" title="WiFi">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/>
                    </svg>
                </button>
                <?php endif; ?>
                <?php if (!$isMenuOnly): ?>
                <button class="action-btn" onclick="callWaiter()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </button>
                <button class="action-btn" onclick="requestBill()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- Categories -->
    <?php if (!empty($displayCategories)): ?>
    <div class="categories">
        <div class="categories-scroll" id="cat-scroll">
            <?php foreach ($displayCategories as $i => $cat): ?>
            <button class="cat-chip <?php echo $i === 0 ? 'active' : ''; ?>" 
                    data-cat="<?php echo $cat['category_id']; ?>"
                    onclick="selectCat('<?php echo $cat['category_id']; ?>')">
                <?php echo htmlspecialchars($cat['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tab: Menu Content -->
    <main class="content tab-content active" id="tab-menu">
        <?php if (empty($displayCategories) && empty($menu_items)): ?>
        <div class="empty">
            <div class="empty-icon">🍽️</div>
            <h3>Henüz ürün yok</h3>
            <p>Menüye yakında ürünler eklenecek</p>
        </div>
        <?php else: ?>
        <?php foreach ($displayCategories as $cat):
            $catId = $cat['category_id'];
            $items = $itemsByCategory[$catId] ?? [];
            
            if (empty($items) && isset($parentToChildren[$catId])) {
                foreach ($parentToChildren[$catId] as $childId) {
                    if (isset($itemsByCategory[$childId])) {
                        $items = array_merge($items, $itemsByCategory[$childId]);
                    }
                }
            }
            
            if (empty($items)) continue;
        ?>
        <section class="section" data-cat="<?php echo $catId; ?>" style="<?php echo $catId !== $firstCategoryId ? 'display:none;' : ''; ?>">
            <div class="section-title">
                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                <span class="count"><?php echo count($items); ?> ürün</span>
            </div>
            <div class="products">
                <?php foreach ($items as $item): ?>
                <div class="product" onclick="openDetail(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                    <div class="product-img">
                        <?php if (!empty($item['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             loading="lazy"
                             onerror="this.outerHTML='<div class=\'no-img\'>🍽️</div>'">
                        <?php else: ?>
                        <div class="no-img">🍽️</div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['is_popular'])): ?>
                        <span class="product-badge hot">Popüler</span>
                        <?php elseif (!empty($item['is_new'])): ?>
                        <span class="product-badge new">Yeni</span>
                        <?php endif; ?>
                        
                        <button class="add-btn" onclick="event.stopPropagation(); openDetail(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v12m6-6H6"/>
                            </svg>
                        </button>
                    </div>
                    <div class="product-body">
                        <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <?php if (!empty($item['description'])): ?>
                        <div class="product-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                        <?php endif; ?>
                        <div class="product-price"><?php echo formatCurrency($item['price']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>
    
    <?php if (!$isMenuOnly): ?>
    <!-- Tab: Orders Content -->
    <div class="content tab-content" id="tab-orders">
        <div class="orders-section" id="orders-container">
            <div class="empty">
                <div class="empty-icon">📋</div>
                <h3>Siparişler yükleniyor...</h3>
                <p>Lütfen bekleyin</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($isMenuOnly): ?>
    <!-- Menu Only Mode Banner -->
    <div style="position:fixed;bottom:0;left:0;right:0;z-index:100;background:linear-gradient(135deg,#fef3c7,#fde68a);padding:14px 20px;text-align:center;border-top:2px solid #f59e0b;box-shadow:0 -4px 16px rgba(0,0,0,0.1);">
        <div style="font-size:13px;font-weight:700;color:#92400e;">📋 Bu menü yalnızca görüntüleme amaçlıdır</div>
        <div style="font-size:11px;color:#a16207;margin-top:2px;">Sipariş verme şu anda aktif değildir</div>
    </div>
    <?php else: ?>
    <!-- Bottom Tabs -->
    <div class="bottom-tabs">
        <button class="tab-btn active" onclick="switchTab('menu')" id="btn-tab-menu">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <span>Menü</span>
        </button>
        <button class="tab-btn" onclick="openCart()" style="position:relative;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span>Sepet</span>
            <span class="tab-badge" id="tab-cart-badge" style="display:none;">0</span>
        </button>
        <button class="tab-btn" onclick="switchTab('orders')" id="btn-tab-orders" style="position:relative;">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span>Siparişlerim</span>
            <span class="tab-badge" id="tab-orders-badge" style="display:none;">0</span>
        </button>
    </div>
    <?php endif; ?>
</div>

<?php if (!$isMenuOnly): ?>
<!-- Cart Modal -->
<div class="modal-overlay" id="cart-modal" onclick="if(event.target===this)closeCart()">
    <div class="modal-sheet">
        <div class="modal-handle"></div>
        <div class="modal-header">
            <span class="modal-title">Sepetim</span>
            <button class="modal-close" onclick="closeCart()">✕</button>
        </div>
        <div class="modal-body" id="cart-items">
            <div class="empty">
                <div class="empty-icon">🛒</div>
                <h3>Sepetiniz boş</h3>
                <p>Lezzetli ürünlerimizi keşfedin</p>
            </div>
        </div>
        <div class="modal-footer">
            <div class="cart-order-note-wrap" style="margin-bottom:12px;">
                <label for="order-note" style="display:block;font-size:13px;color:var(--text-secondary);margin-bottom:6px;">Sipariş notu (isteğe bağlı)</label>
                <textarea id="order-note" class="cart-order-note" rows="2" placeholder="Örn: Çocuk için bıçak vermeyin, servis saatini geciktirin..."></textarea>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <span style="font-size:14px;color:var(--text-secondary);">Toplam</span>
                <span style="font-size:20px;font-weight:700;color:var(--primary);" id="cart-total-modal">₺0</span>
            </div>
            <button class="btn-primary" id="order-btn" onclick="placeOrder()" disabled>Sipariş Ver</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Product Detail Modal -->
<div class="modal-overlay" id="detail-modal" onclick="if(event.target===this)closeDetail()">
    <div class="modal-sheet">
        <div class="modal-handle"></div>
        <div class="modal-header">
            <span class="modal-title">Ürün Detayı</span>
            <button class="modal-close" onclick="closeDetail()">✕</button>
        </div>
        <div class="modal-body" id="detail-content"></div>
        <?php if (!$isMenuOnly): ?>
        <div class="modal-footer">
            <button class="btn-primary" id="detail-add-btn" onclick="addFromDetail()">Sepete Ekle</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="<?php echo BASE_URL; ?>/assets/js/utils.js"></script>
<script>
(function() {
    'use strict';
    
    // Menü ürünlerini ID ile bulmak için (sepette düzenleme)
    window.__menuItemsById = <?php
    $menuItemsById = [];
    if (!empty($menu_items) && is_array($menu_items)) {
        foreach ($menu_items as $it) {
            if (!empty($it['menu_item_id'])) {
                $menuItemsById[$it['menu_item_id']] = $it;
            }
        }
    }
    echo json_encode($menuItemsById);
    ?>;
    
    const isMenuOnly = <?php echo $isMenuOnly ? 'true' : 'false'; ?>;
    let cart = <?php echo json_encode($_SESSION['cart'] ?? []); ?>;
    const tableId = '<?php echo $table['table_id'] ?? ''; ?>';
    const baseUrl = '<?php echo BASE_URL; ?>';
    let currentItem = null;
    let detailQty = 1;
    let currentTab = 'menu';
    let orders = [];
    let ordersLoaded = false;
    let isSubmittingOrder = false; // Flag to prevent duplicate orders
    let isCallingWaiter = false; // Flag to prevent duplicate waiter calls
    let isRequestingBill = false; // Flag to prevent duplicate bill requests
    let sessionActive = true;
    let sessionCheckInterval = null;
    const presenceEnabled = <?php echo json_encode(!empty($features['customer_presence_tracking'])); ?>;
    const geoFence = <?php echo json_encode($geo_fence ?? ['enabled' => false]); ?>;
    let locationConsented = localStorage.getItem('qordy_location_consent_' + tableId) === '1';
    let locationGranted = false;
    let geoVerified = false;
    
    // ============================================================
    // GEO-FENCE: Block remote users from accessing the menu
    // ============================================================
    
    // Calculate distance between two lat/lng points (Haversine formula, returns meters)
    function haversineDistance(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }
    
    // Show "too far" blocking screen
    function showTooFarScreen(distance) {
        const existing = document.getElementById('geo-blocked-overlay');
        if (existing) return;
        
        const distKm = (distance / 1000).toFixed(1);
        const overlay = document.createElement('div');
        overlay.id = 'geo-blocked-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:#fff;z-index:99999;display:flex;align-items:center;justify-content:center;padding:24px;';
        overlay.innerHTML = `
            <div style="text-align:center;max-width:360px;">
                <div style="width:80px;height:80px;background:linear-gradient(135deg,#ef4444,#dc2626);border-radius:24px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                    <svg style="width:40px;height:40px;color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h2 style="font-size:22px;font-weight:800;color:#1e293b;margin-bottom:8px;">Erişim Engellendi</h2>
                <p style="font-size:15px;color:#64748b;margin-bottom:16px;line-height:1.6;">
                    Bu menüye yalnızca işletme yakınından erişilebilir.
                </p>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:12px;margin-bottom:24px;">
                    <p style="font-size:13px;color:#dc2626;font-weight:600;">
                        Konumunuz işletmeden yaklaşık <strong>${distKm} km</strong> uzaklıkta.
                    </p>
                </div>
                <p style="font-size:13px;color:#94a3b8;line-height:1.5;">
                    Sipariş vermek için lütfen işletmeye gidin ve masadaki QR kodu okutun.
                </p>
                <button onclick="retryGeoCheck()" style="margin-top:20px;padding:14px 32px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;">
                    Tekrar Dene
                </button>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    // Show location permission required screen
    function showLocationRequiredScreen() {
        const existing = document.getElementById('geo-blocked-overlay');
        if (existing) return;
        
        const overlay = document.createElement('div');
        overlay.id = 'geo-blocked-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:#fff;z-index:99999;display:flex;align-items:center;justify-content:center;padding:24px;';
        overlay.innerHTML = `
            <div style="text-align:center;max-width:360px;">
                <div style="width:80px;height:80px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:24px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                    <svg style="width:40px;height:40px;color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h2 style="font-size:22px;font-weight:800;color:#1e293b;margin-bottom:8px;">Konum İzni Gerekli</h2>
                <p style="font-size:15px;color:#64748b;margin-bottom:16px;line-height:1.6;">
                    Menüyü kullanabilmek için konum izni vermeniz gerekmektedir. Bu sayede işletme yakınında olduğunuz doğrulanır.
                </p>
                <button onclick="retryGeoCheck()" style="padding:14px 32px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;">
                    Konum İzni Ver
                </button>
                <p style="font-size:11px;color:#94a3b8;margin-top:16px;line-height:1.5;">
                    Tarayıcınız konum izni isteyecektir. Lütfen "İzin Ver" seçeneğini seçin.
                </p>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    // Retry geo check
    window.retryGeoCheck = function() {
        const overlay = document.getElementById('geo-blocked-overlay');
        if (overlay) overlay.remove();
        performGeoFenceCheck();
    };
    
    // Main geo-fence check
    function performGeoFenceCheck() {
        if (!geoFence.enabled || geoFence.allow_remote) {
            geoVerified = true;
            return;
        }
        
        if (!navigator.geolocation) {
            showLocationRequiredScreen();
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                locationGranted = true;
                const dist = haversineDistance(pos.coords.latitude, pos.coords.longitude, geoFence.lat, geoFence.lng);
                
                if (dist > geoFence.radius) {
                    showTooFarScreen(dist);
                } else {
                    geoVerified = true;
                    const overlay = document.getElementById('geo-blocked-overlay');
                    if (overlay) overlay.remove();
                    
                    sendLocationToServer(pos.coords.latitude, pos.coords.longitude);
                    locationConsented = true;
                    localStorage.setItem('qordy_location_consent_' + tableId, '1');
                }
            },
            function(err) {
                if (err.code === 1) {
                    showLocationRequiredScreen();
                } else {
                    // Timeout or unavailable - allow access (fail-open for GPS issues)
                    geoVerified = true;
                }
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
        );
    }
    
    // ============================================================
    // SESSION MANAGEMENT: Presence tracking + inactivity check
    // ============================================================
    
    // Show first-visit consent dialog for location permission (only when geo-fence is off but presence tracking is on)
    function showPresenceConsentDialog() {
        if (!presenceEnabled || locationConsented || geoFence.enabled) return;
        
        const dialog = document.createElement('div');
        dialog.id = 'presence-consent-dialog';
        dialog.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);z-index:99998;display:flex;align-items:flex-end;justify-content:center;padding:0;animation:fadeIn .2s ease;';
        dialog.innerHTML = `
            <div style="background:#fff;border-radius:24px 24px 0 0;padding:28px 24px env(safe-area-inset-bottom,20px);max-width:440px;width:100%;animation:slideUp .3s ease;max-height:85vh;overflow-y:auto;">
                <div style="width:40px;height:4px;background:#e2e8f0;border-radius:2px;margin:0 auto 20px;"></div>
                
                <div style="text-align:center;margin-bottom:20px;">
                    <div style="width:56px;height:56px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                        <svg style="width:28px;height:28px;color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <h3 style="font-size:19px;font-weight:800;color:#1e293b;margin-bottom:6px;">Daha iyi bir deneyim</h3>
                    <p style="font-size:14px;color:#64748b;line-height:1.6;">
                        Siparişlerinizi hizli ve guvenli bir sekilde isleme alabilmemiz icin konum bilginize ihtiyacimiz var.
                    </p>
                </div>
                
                <div style="background:#f8fafc;border-radius:16px;padding:16px;margin-bottom:16px;">
                    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">
                        <div style="width:32px;height:32px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg style="width:16px;height:16px;color:#3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#1e293b;">Konum Bilgisi</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">Masanizda oldugunuzu dogrulamak icin kullanilir</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:flex-start;gap:12px;">
                        <div style="width:32px;height:32px;background:#dcfce7;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg style="width:16px;height:16px;color:#16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#1e293b;">IP Adresi</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">Oturumunuzu guvenli tutmak icin kullanilir</div>
                        </div>
                    </div>
                </div>
                
                <p style="font-size:11px;color:#94a3b8;text-align:center;margin-bottom:20px;line-height:1.5;">
                    Bu veriler yalnizca oturumunuz boyunca kullanilir ve sonrasinda silinir. 
                    <a href="#" onclick="event.preventDefault();showPrivacyDetails();" style="color:#6366f1;text-decoration:underline;">Detayli bilgi</a>
                </p>
                
                <div style="display:flex;gap:10px;">
                    <button onclick="acceptPresenceConsent()" style="flex:2;padding:14px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;">
                        Izin Ver
                    </button>
                    <button onclick="skipPresenceConsent()" style="flex:1;padding:14px;background:#f1f5f9;color:#64748b;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;">
                        Simdilik Degil
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(dialog);
    }
    
    // Privacy details popup
    window.showPrivacyDetails = function() {
        const existing = document.getElementById('privacy-details-modal');
        if (existing) existing.remove();
        
        const modal = document.createElement('div');
        modal.id = 'privacy-details-modal';
        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';
        modal.innerHTML = `
            <div style="background:#fff;border-radius:20px;padding:28px;max-width:400px;width:100%;max-height:80vh;overflow-y:auto;">
                <h3 style="font-size:18px;font-weight:800;color:#1e293b;margin-bottom:16px;">Veri Kullanimî Hakkinda</h3>
                
                <div style="font-size:13px;color:#475569;line-height:1.7;">
                    <p style="margin-bottom:12px;"><strong>Neden konum bilgisi istiyoruz?</strong></p>
                    <p style="margin-bottom:12px;">Konum bilginiz, masanizda oldugunuzu dogrulamak icin kullanilir. Bu sayede:</p>
                    <ul style="margin-bottom:16px;padding-left:20px;">
                        <li style="margin-bottom:6px;">Siparisleriniz dogru masaya iletilir</li>
                        <li style="margin-bottom:6px;">Oturumunuz guvenli kalir</li>
                        <li style="margin-bottom:6px;">Odeme sonrasi oturumunuz otomatik sonlanir</li>
                    </ul>
                    
                    <p style="margin-bottom:12px;"><strong>Neden IP adresi kullanilir?</strong></p>
                    <p style="margin-bottom:16px;">IP adresiniz, oturumunuzu kimlik dogrulama amacli korumak icin kullanilir. Baska birinin sizin oturumunuza erisimini engeller.</p>
                    
                    <p style="margin-bottom:12px;"><strong>Verileriniz ne kadar saklanir?</strong></p>
                    <p style="margin-bottom:16px;">Toplanan veriler yalnizca aktif oturumunuz suresince gecerlidir. Odeme yapildiktan ve masadan kalktiktan sonra tum oturum verileri otomatik olarak temizlenir.</p>
                    
                    <p style="margin-bottom:12px;"><strong>Izin vermek zorunlu mu?</strong></p>
                    <p>Hayir. Konum izni vermeden de siparis verebilirsiniz. Ancak konum izni vermeniz, size daha guvenli ve hizli bir deneyim sunar.</p>
                </div>
                
                <button onclick="document.getElementById('privacy-details-modal').remove()" style="width:100%;padding:14px;background:#f1f5f9;border:none;border-radius:12px;font-size:14px;font-weight:700;color:#475569;cursor:pointer;margin-top:20px;">
                    Anladim, Kapat
                </button>
            </div>
        `;
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
    };
    
    // User accepted location consent
    window.acceptPresenceConsent = function() {
        locationConsented = true;
        localStorage.setItem('qordy_location_consent_' + tableId, '1');
        
        const dialog = document.getElementById('presence-consent-dialog');
        if (dialog) dialog.remove();
        
        // Now request actual geolocation permission from browser
        requestLocationPermission();
    };
    
    // User skipped consent
    window.skipPresenceConsent = function() {
        const dialog = document.getElementById('presence-consent-dialog');
        if (dialog) dialog.remove();
        // Don't set consent flag - will ask again next visit
    };
    
    // Request browser geolocation permission
    function requestLocationPermission() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                locationGranted = true;
                sendLocationToServer(pos.coords.latitude, pos.coords.longitude);
            },
            function() {
                locationGranted = false;
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );
    }
    
    // Send location data to server
    function sendLocationToServer(lat, lng) {
        fetch(baseUrl + '/api/session/update-location', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
            credentials: 'same-origin',
            body: JSON.stringify({ latitude: lat, longitude: lng })
        }).catch(() => {});
    }
    
    // Periodic location tracking (only if consented and granted)
    function trackLocation() {
        if (!presenceEnabled || !locationConsented || !navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                locationGranted = true;
                sendLocationToServer(pos.coords.latitude, pos.coords.longitude);
            },
            function() {},
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
        );
    }
    
    // Check session activity status
    function checkSessionStatus() {
        if (!sessionActive) return;
        
        fetch(baseUrl + '/api/session/check', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            
            if (data.session_active) return; // All good
            
            // SESSION_ENDED = payment processed, full block
            if (data.reason === 'SESSION_ENDED') {
                showSessionEndedScreen();
                return;
            }
            
            // Don't interrupt if user has modal open
            const detailOpen = document.getElementById('detail-modal')?.classList.contains('open');
            const cartOpen = document.getElementById('cart-modal')?.classList.contains('open');
            if (detailOpen || cartOpen) return;
            
            // INACTIVITY_TIMEOUT = long idle, show continue option
            if (data.reason === 'INACTIVITY_TIMEOUT') {
                showSessionExpiredModal(false);
                return;
            }
            
            // NO_SESSION or SESSION_NOT_FOUND = session lost, try silent recovery
            if (data.reason === 'NO_SESSION' || data.reason === 'SESSION_NOT_FOUND') {
                // If cart has items, don't immediately block - user may still be browsing
                if (cart.length > 0) return;
                showSessionExpiredModal(true);
            }
        })
        .catch(() => {});
    }
    
    // Show session expired modal with continue option
    function showSessionExpiredModal(forceRescan) {
        if (!sessionActive) return;
        // Remove existing modals first
        const existing = document.getElementById('session-expired-modal');
        if (existing) existing.remove();
        
        sessionActive = false;
        
        const modal = document.createElement('div');
        modal.id = 'session-expired-modal';
        modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';
        
        const content = forceRescan 
            ? `<div style="background:#fff;border-radius:20px;padding:32px;max-width:360px;width:100%;text-align:center;">
                <div style="font-size:48px;margin-bottom:16px;">📱</div>
                <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;color:#1a1a2e;">Bağlantı Koptu</h3>
                <p style="font-size:14px;color:#666;margin-bottom:24px;">Oturumunuz bulunamadı. Sayfayı yenileyerek devam edebilirsiniz.</p>
                <button onclick="location.reload()" style="width:100%;padding:14px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Sayfayı Yenile</button>
               </div>`
            : `<div style="background:#fff;border-radius:20px;padding:32px;max-width:360px;width:100%;text-align:center;">
                <div style="font-size:48px;margin-bottom:16px;">⏰</div>
                <h3 style="font-size:18px;font-weight:700;margin-bottom:8px;color:#1a1a2e;">Hâlâ burada mısınız?</h3>
                <p style="font-size:14px;color:#666;margin-bottom:24px;">Uzun süredir işlem yapılmadı. Devam etmek ister misiniz?</p>
                <div style="display:flex;gap:12px;">
                    <button onclick="continueSession()" style="flex:1;padding:14px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Evet, Devam Et</button>
                    <button onclick="location.reload()" style="flex:1;padding:14px;background:#f1f5f9;color:#475569;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">Yenile</button>
                </div>
               </div>`;
        
        modal.innerHTML = content;
        document.body.appendChild(modal);
    }
    
    // Show payment completed modal (for orders tab inline)
    function showPaymentCompletedModal() {
        showSessionEndedScreen();
    }
    
    // Full-screen session ended overlay: blocks ALL interaction after payment
    function showSessionEndedScreen() {
        if (document.getElementById('session-ended-overlay')) return;
        
        // Stop all polling
        sessionActive = false;
        if (sessionCheckInterval) {
            clearInterval(sessionCheckInterval);
            sessionCheckInterval = null;
        }
        
        // Clear cart
        cart = [];
        try { updateCart(); saveCart(); } catch(e) {}
        
        const overlay = document.createElement('div');
        overlay.id = 'session-ended-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:#f8fafc;z-index:999999;display:flex;align-items:center;justify-content:center;padding:20px;';
        overlay.innerHTML = `
            <div style="text-align:center;max-width:380px;width:100%;">
                <div style="width:80px;height:80px;background:linear-gradient(135deg,#10b981,#059669);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                    <svg style="width:40px;height:40px;color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h2 style="font-size:24px;font-weight:900;color:#1e293b;margin-bottom:8px;">Ödemeniz Alındı</h2>
                <p style="font-size:16px;color:#64748b;margin-bottom:8px;">Teşekkür ederiz, afiyet olsun!</p>
                <p style="font-size:14px;color:#94a3b8;margin-bottom:32px;">Oturumunuz sonlandırılmıştır.</p>
                <div style="background:white;border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:24px;">
                    <div style="font-size:13px;color:#94a3b8;margin-bottom:4px;">Tekrar sipariş vermek için</div>
                    <div style="font-size:15px;font-weight:700;color:#1e293b;">Masadaki QR kodu tekrar okutun</div>
                </div>
                <button onclick="location.reload()" style="padding:14px 32px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;">
                    Sayfayı Yenile
                </button>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    // Continue session after inactivity warning
    window.continueSession = function() {
        const modal = document.getElementById('session-expired-modal');
        if (modal) modal.remove();
        sessionActive = true;
        
        fetch(baseUrl + '/api/session/continue', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                toast('Oturum devam ediyor');
            }
        })
        .catch(() => {});
    };
    
    // Presence tracking: detect user interaction and keep session alive
    let lastInteractionTime = Date.now();
    
    function onUserInteraction() {
        lastInteractionTime = Date.now();
    }
    
    // Passive listeners for any user activity
    ['touchstart', 'scroll', 'click', 'keydown'].forEach(evt => {
        document.addEventListener(evt, onUserInteraction, { passive: true });
    });
    
    // Start session monitoring (after 1.5 seconds to let page load)
    setTimeout(() => {
        // 1. Geo-fence check (blocks remote users entirely)
        if (geoFence.enabled && !geoFence.allow_remote) {
            performGeoFenceCheck();
        } else if (presenceEnabled && !locationConsented) {
            // 2. Consent dialog (only if geo-fence is off but presence tracking is on)
            showPresenceConsentDialog();
        } else if (presenceEnabled && locationConsented) {
            requestLocationPermission();
        }
        
        // Track location every 2 minutes (only if consented)
        setInterval(trackLocation, 120000);
        
        // Check session every 60 seconds
        sessionCheckInterval = setInterval(checkSessionStatus, 60000);
    }, 1500);
    
    // Customization state
    let selectedVariant = null;
    let removedIngredients = [];
    let selectedExtras = [];
    let itemNote = '';
    let editingCartIndex = null; // Sepette düzenleme: hangi kalem düzenleniyor
    
    // Format currency
    function formatPrice(price) {
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(price);
    }
    
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    function parseJsonList(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value;
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (!trimmed) return [];
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) return parsed;
                if (typeof parsed === 'string' && parsed) return [parsed];
            } catch (e) {
                // Ignore JSON parse errors
            }
            return [trimmed];
        }
        return [];
    }
    
    function normalizeIngredientList(value) {
        const list = parseJsonList(value);
        return list.map((entry) => {
            if (entry && typeof entry === 'object') {
                return entry.name || entry.ingredient_name || entry.label || '';
            }
            return String(entry);
        }).filter(Boolean);
    }
    
    // Show toast
    function toast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2000);
    }
    
    // Update cart UI
    function updateCart() {
        if (isMenuOnly) return;
        const count = cart.reduce((s, i) => s + i.quantity, 0);
        const total = cart.reduce((s, i) => s + (i.price * i.quantity), 0);
        
        const cartTotalEl = document.getElementById('cart-total-modal');
        const orderBtnEl = document.getElementById('order-btn');
        if (cartTotalEl) cartTotalEl.textContent = formatPrice(total);
        if (orderBtnEl) orderBtnEl.disabled = count === 0;
        
        // Update tab badge
        const tabBadge = document.getElementById('tab-cart-badge');
        if (tabBadge) {
            if (count > 0) {
                tabBadge.textContent = count;
                tabBadge.style.display = 'flex';
            } else {
                tabBadge.style.display = 'none';
            }
        }
        
        // Render cart items
        const container = document.getElementById('cart-items');
        if (!container) return;
        if (count === 0) {
            container.innerHTML = '<div class="empty"><div class="empty-icon">🛒</div><h3>Sepetiniz boş</h3><p>Lezzetli ürünlerimizi keşfedin</p></div>';
        } else {
            container.innerHTML = cart.map((item, idx) => {
                let customDetails = [];
                if (item.variant && item.variant.name) {
                    customDetails.push(`<span style="color:var(--primary);font-weight:600;">${escapeHtml(item.variant.name)}</span>`);
                }
                if (item.removed_ingredients && item.removed_ingredients.length > 0) {
                    customDetails.push(`<span style="color:var(--danger);">Çıkarılan: ${escapeHtml(item.removed_ingredients.join(', '))}</span>`);
                }
                if (item.selected_extras && item.selected_extras.length > 0) {
                    const extraNames = item.selected_extras.map(e => e.name).join(', ');
                    customDetails.push(`<span style="color:var(--success);">Ekstra: ${escapeHtml(extraNames)}</span>`);
                }
                if (item.note) {
                    customDetails.push(`<span style="color:#b45309;">Not: ${escapeHtml(item.note)}</span>`);
                }
                
                return `
                <div class="cart-item cart-item-editable" onclick="editCartItem(${idx})" title="Düzenlemek için tıklayın">
                    <div class="cart-item-img">
                        ${item.image_url ? `<img src="${escapeHtml(item.image_url)}" alt="">` : '<div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;">🍽️</div>'}
                    </div>
                    <div class="cart-item-info">
                        <div class="cart-item-name">${escapeHtml(item.name)}</div>
                        ${customDetails.length > 0 ? `<div style="font-size:11px;line-height:1.4;margin-bottom:4px;">${customDetails.join('<br>')}</div>` : ''}
                        <div class="cart-item-price">${formatPrice(item.price * item.quantity)}</div>
                        <div class="qty-controls" onclick="event.stopPropagation();">
                            <button class="qty-btn" type="button" onclick="event.stopPropagation(); changeQty(${idx}, -1)">−</button>
                            <span class="qty-val">${item.quantity}</span>
                            <button class="qty-btn" type="button" onclick="event.stopPropagation(); changeQty(${idx}, 1)">+</button>
                        </div>
                    </div>
                </div>
            `}).join('');
        }
    }
    
    // Change quantity
    window.changeQty = function(idx, delta) {
        cart[idx].quantity += delta;
        if (cart[idx].quantity <= 0) {
            cart.splice(idx, 1);
        }
        updateCart();
        saveCart();
    };
    
    // Add item to cart
    window.addItem = function(item) {
        const existing = cart.find(i => i.menu_item_id === item.menu_item_id);
        if (existing) {
            existing.quantity++;
        } else {
            cart.push({
                menu_item_id: item.menu_item_id,
                name: item.name,
                price: parseFloat(item.price),
                image_url: item.image_url || '',
                quantity: 1
            });
        }
        updateCart();
        saveCart();
        toast('Sepete eklendi');
    };
    
    // Save cart to session
    function saveCart() {
        fetch(baseUrl + '/api/cart/sync', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart, table_id: tableId })
        }).catch(() => {});
    }
    
    // Select category
    window.selectCat = function(catId) {
        document.querySelectorAll('.cat-chip').forEach(c => {
            c.classList.toggle('active', c.dataset.cat === catId);
        });
        // Only target sections within the menu tab
        document.querySelectorAll('#tab-menu .section').forEach(s => {
            s.style.display = s.dataset.cat === catId ? '' : 'none';
        });
        
        const activeChip = document.querySelector(`.cat-chip[data-cat="${catId}"]`);
        if (activeChip) {
            activeChip.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    };
    
    // Open cart modal
    window.openCart = function() {
        if (isMenuOnly) { toast('Bu menü yalnızca görüntüleme amaçlıdır'); return; }
        document.getElementById('cart-modal').classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    
    // Close cart modal
    window.closeCart = function() {
        document.getElementById('cart-modal').classList.remove('open');
        document.body.style.overflow = '';
    };
    
    // Sepette ürüne tıklayınca düzenleme (varyant, malzeme çıkar, ekstra, not)
    window.editCartItem = function(idx) {
        const cartItem = cart[idx];
        if (!cartItem) return;
        const fullItem = window.__menuItemsById && window.__menuItemsById[cartItem.menu_item_id];
        if (!fullItem) {
            toast('Bu ürün artık menüde görüntülenemiyor.');
            return;
        }
        closeCart();
        openDetail(fullItem, cartItem, idx);
    };
    
    // Open product detail (optionally prefill for cart edit: openDetail(item, prefillCartItem, editIndex))
    window.openDetail = function(item, prefillCartItem, editIndex) {
        currentItem = item;
        editingCartIndex = editIndex !== undefined ? editIndex : null;
        
        if (prefillCartItem) {
            detailQty = prefillCartItem.quantity || 1;
            selectedVariant = prefillCartItem.variant || null;
            removedIngredients = Array.isArray(prefillCartItem.removed_ingredients) ? [...prefillCartItem.removed_ingredients] : [];
            selectedExtras = [];
            itemNote = (prefillCartItem.note || '').trim();
            // selected_extras: array of {name, price}; match to extras by name
            if (Array.isArray(prefillCartItem.selected_extras) && prefillCartItem.selected_extras.length > 0) {
                // Will fill after parsing extras below
            }
        } else {
            detailQty = 1;
            selectedVariant = null;
            removedIngredients = [];
            selectedExtras = [];
            itemNote = '';
        }
        
        // Parse variants
        let variants = [];
        if (item.variants) {
            variants = Array.isArray(item.variants) ? item.variants : [];
        }
        
        // Parse ingredients
        let ingredients = [];
        if (item.ingredients) {
            if (typeof item.ingredients === 'string') {
                try {
                    ingredients = JSON.parse(item.ingredients);
                } catch(e) {
                    ingredients = item.ingredients.split(',').map(i => i.trim()).filter(Boolean);
                }
            } else if (Array.isArray(item.ingredients)) {
                ingredients = item.ingredients;
            }
        }
        
        // Parse extras
        let extras = [];
        if (item.extras || item.available_extras) {
            const extrasData = item.extras || item.available_extras;
            if (typeof extrasData === 'string') {
                try {
                    extras = JSON.parse(extrasData);
                } catch(e) {
                    extras = [];
                }
            } else if (Array.isArray(extrasData)) {
                extras = extrasData;
            }
        }
        
        // Set default variant if none prefilled
        if (variants.length > 0 && !prefillCartItem) {
            const defaultVariant = variants.find(v => v.is_default == 1) || variants[0];
            selectedVariant = defaultVariant;
        }
        
        // Prefill selected extras by name match
        if (prefillCartItem && Array.isArray(prefillCartItem.selected_extras) && prefillCartItem.selected_extras.length > 0 && extras.length > 0) {
            selectedExtras = [];
            prefillCartItem.selected_extras.forEach(se => {
                const name = typeof se === 'object' ? (se.name || '') : String(se);
                const idx = extras.findIndex(e => (typeof e === 'object' ? (e.name || '') : e) === name);
                if (idx >= 0) selectedExtras.push(idx);
            });
        }
        
        const content = document.getElementById('detail-content');
        let html = `
            <div class="product-detail-img">
                ${item.image_url ? `<img src="${escapeHtml(item.image_url)}" alt="">` : '<div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:64px;">🍽️</div>'}
            </div>
            <div class="product-detail-name">${escapeHtml(item.name)}</div>
            ${item.description ? `<div class="product-detail-desc">${escapeHtml(item.description)}</div>` : ''}
            <div class="product-detail-price" id="detail-price">${formatPrice(getItemPrice())}</div>
        `;
        
        // Variants Section
        if (variants.length > 0) {
            html += `
                <div class="customization-section">
                    <div class="customization-title">
                        <span class="icon">📏</span>
                        Boyut Seçin
                    </div>
                    <div class="variant-options" id="variant-options">
                        ${variants.map((v, idx) => {
                            const isActive = selectedVariant && selectedVariant.variant_id === v.variant_id;
                            const priceModifier = parseFloat(v.price_modifier || 0);
                            const priceText = priceModifier > 0 ? `+${formatPrice(priceModifier)}` : (priceModifier < 0 ? formatPrice(priceModifier) : '');
                            return `
                                <button class="variant-btn ${isActive ? 'active' : ''}" onclick="selectVariant(${idx})">
                                    <span>${escapeHtml(v.name)}</span>
                                    ${priceText ? `<span class="variant-price">${priceText}</span>` : ''}
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        // Ingredients Section (for removal)
        if (ingredients.length > 0) {
            html += `
                <div class="customization-section">
                    <div class="customization-title">
                        <span class="icon">🥗</span>
                        Malzemeler <span style="font-weight:400;color:var(--text-muted);font-size:12px;">(çıkarmak için tıklayın)</span>
                    </div>
                    <div class="ingredient-chips" id="ingredient-chips">
                        ${ingredients.map((ing, idx) => {
                            const ingName = typeof ing === 'object' ? (ing.name || ing.ingredient_name || '') : ing;
                            const isRemoved = removedIngredients.indexOf(ingName) >= 0;
                            return `
                                <button class="ingredient-chip ${isRemoved ? 'removed' : ''}" onclick="toggleIngredient(${idx})" data-ingredient="${escapeHtml(ingName)}">
                                    <span>${escapeHtml(ingName)}</span>
                                    <span class="remove-icon">✕</span>
                                </button>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        // Extras Section
        if (extras.length > 0) {
            html += `
                <div class="customization-section">
                    <div class="customization-title">
                        <span class="icon">➕</span>
                        Ekstralar
                    </div>
                    <div class="extra-items" id="extra-items">
                        ${extras.map((extra, idx) => {
                            const extraName = typeof extra === 'object' ? (extra.name || '') : extra;
                            const extraPrice = typeof extra === 'object' ? parseFloat(extra.price || 0) : 0;
                            const isSelected = selectedExtras.indexOf(idx) >= 0;
                            return `
                                <div class="extra-item ${isSelected ? 'selected' : ''}" onclick="toggleExtra(${idx})">
                                    <div class="extra-item-left">
                                        <div class="extra-checkbox">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                        </div>
                                        <span class="extra-name">${escapeHtml(extraName)}</span>
                                    </div>
                                    ${extraPrice > 0 ? `<span class="extra-price">+${formatPrice(extraPrice)}</span>` : ''}
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        // Note Section
        html += `
            <div class="customization-section" style="border-bottom:none;margin-bottom:0;">
                <div class="customization-title">
                    <span class="icon">📝</span>
                    Sipariş Notu
                </div>
                <textarea class="note-input" id="item-note" rows="2" placeholder="Örn: Az pişmiş olsun, sosu ayrı gelsin..." oninput="itemNote = this.value"></textarea>
            </div>
        `;
        
        // Quantity Section
        html += `
            <div class="detail-qty">
                <button class="qty-btn" onclick="detailQtyChange(-1)">−</button>
                <span class="qty-val" id="detail-qty">${detailQty}</span>
                <button class="qty-btn" onclick="detailQtyChange(1)">+</button>
            </div>
        `;
        
        content.innerHTML = html;
        
        // Store parsed data for later use
        currentItem._parsedVariants = variants;
        currentItem._parsedIngredients = ingredients;
        currentItem._parsedExtras = extras;
        
        // Prefill note textarea (edit mode)
        const noteEl = document.getElementById('item-note');
        if (noteEl) noteEl.value = itemNote;
        
        updateDetailBtn();
        document.getElementById('detail-modal').classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    
    // Get current item price including variant modifier and extras
    function getItemPrice() {
        if (!currentItem) return 0;
        
        let price = parseFloat(currentItem.price || 0);
        
        // Add variant price modifier
        if (selectedVariant) {
            price += parseFloat(selectedVariant.price_modifier || 0);
        }
        
        // Add extras prices
        if (selectedExtras.length > 0 && currentItem._parsedExtras) {
            selectedExtras.forEach(idx => {
                const extra = currentItem._parsedExtras[idx];
                if (extra && typeof extra === 'object') {
                    price += parseFloat(extra.price || 0);
                }
            });
        }
        
        return price;
    }
    
    // Select variant
    window.selectVariant = function(idx) {
        if (!currentItem || !currentItem._parsedVariants) return;
        
        selectedVariant = currentItem._parsedVariants[idx];
        
        // Update UI
        document.querySelectorAll('.variant-btn').forEach((btn, i) => {
            btn.classList.toggle('active', i === idx);
        });
        
        // Update price display
        document.getElementById('detail-price').textContent = formatPrice(getItemPrice());
        updateDetailBtn();
    };
    
    // Toggle ingredient removal
    window.toggleIngredient = function(idx) {
        if (!currentItem || !currentItem._parsedIngredients) return;
        
        const ingName = currentItem._parsedIngredients[idx];
        const actualName = typeof ingName === 'object' ? (ingName.name || ingName.ingredient_name || '') : ingName;
        
        const existingIdx = removedIngredients.indexOf(actualName);
        if (existingIdx > -1) {
            removedIngredients.splice(existingIdx, 1);
        } else {
            removedIngredients.push(actualName);
        }
        
        // Update UI
        const chips = document.querySelectorAll('.ingredient-chip');
        chips[idx].classList.toggle('removed', removedIngredients.includes(actualName));
    };
    
    // Toggle extra selection
    window.toggleExtra = function(idx) {
        if (!currentItem || !currentItem._parsedExtras) return;
        
        const existingIdx = selectedExtras.indexOf(idx);
        if (existingIdx > -1) {
            selectedExtras.splice(existingIdx, 1);
        } else {
            selectedExtras.push(idx);
        }
        
        // Update UI
        const items = document.querySelectorAll('.extra-item');
        items[idx].classList.toggle('selected', selectedExtras.includes(idx));
        
        // Update price display
        document.getElementById('detail-price').textContent = formatPrice(getItemPrice());
        updateDetailBtn();
    };
    
    // Close detail modal
    window.closeDetail = function() {
        document.getElementById('detail-modal').classList.remove('open');
        document.body.style.overflow = '';
        currentItem = null;
        editingCartIndex = null;
    };
    
    // Detail qty change
    window.detailQtyChange = function(delta) {
        detailQty = Math.max(1, detailQty + delta);
        document.getElementById('detail-qty').textContent = detailQty;
        updateDetailBtn();
    };
    
    function updateDetailBtn() {
        const btn = document.getElementById('detail-add-btn');
        const totalPrice = getItemPrice() * detailQty;
        btn.textContent = editingCartIndex !== null
            ? `Güncelle - ${formatPrice(totalPrice)}`
            : `Sepete Ekle - ${formatPrice(totalPrice)}`;
    }
    
    // Generate unique cart item key based on customizations
    function getCartItemKey(item, variant, removedIngs, extras) {
        let key = item.menu_item_id;
        if (variant) key += '_v' + variant.variant_id;
        if (removedIngs.length > 0) key += '_r' + removedIngs.sort().join(',');
        if (extras.length > 0) key += '_e' + extras.sort().join(',');
        return key;
    }
    
    // Add from detail
    window.addFromDetail = function() {
        if (isMenuOnly) { toast('Bu menü yalnızca görüntüleme amaçlıdır'); return; }
        if (!currentItem) return;
        
        const unitPrice = getItemPrice();
        const cartKey = getCartItemKey(currentItem, selectedVariant, removedIngredients, selectedExtras);
        
        // Build customization display text
        let customizationParts = [];
        if (selectedVariant) {
            customizationParts.push(selectedVariant.name);
        }
        if (removedIngredients.length > 0) {
            customizationParts.push('Çıkarılan: ' + removedIngredients.join(', '));
        }
        if (selectedExtras.length > 0 && currentItem._parsedExtras) {
            const extraNames = selectedExtras.map(idx => {
                const extra = currentItem._parsedExtras[idx];
                return typeof extra === 'object' ? extra.name : extra;
            });
            customizationParts.push('Ekstra: ' + extraNames.join(', '));
        }
        
        // Get extra details for order
        const extraDetails = selectedExtras.map(idx => {
            const extra = currentItem._parsedExtras[idx];
            return {
                name: typeof extra === 'object' ? extra.name : extra,
                price: typeof extra === 'object' ? parseFloat(extra.price || 0) : 0
            };
        });
        
        const newCartItem = {
            _cartKey: cartKey,
            menu_item_id: currentItem.menu_item_id,
            name: currentItem.name,
            price: unitPrice,
            base_price: parseFloat(currentItem.price),
            image_url: currentItem.image_url || '',
            quantity: detailQty,
            variant: selectedVariant ? {
                variant_id: selectedVariant.variant_id,
                name: selectedVariant.name,
                price_modifier: parseFloat(selectedVariant.price_modifier || 0)
            } : null,
            removed_ingredients: [...removedIngredients],
            selected_extras: extraDetails,
            note: itemNote.trim(),
            customization_display: customizationParts.join(' | ')
        };
        
        if (editingCartIndex !== null) {
            cart[editingCartIndex] = newCartItem;
            editingCartIndex = null;
            toast('Ürün güncellendi');
        } else {
            const existing = cart.find(i => i._cartKey === cartKey);
            if (existing) {
                existing.quantity += detailQty;
            } else {
                cart.push(newCartItem);
            }
            toast('Sepete eklendi');
        }
        
        updateCart();
        saveCart();
        closeDetail();
    };
    
    // Get CSRF token helper function
    function getCSRFToken() {
        // Try meta tag first
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag && metaTag.content) {
            return metaTag.content;
        }
        // Try window variable
        if (typeof window !== 'undefined' && window.CSRF_TOKEN) {
            return window.CSRF_TOKEN;
        }
        console.error('CSRF token not found');
        return null;
    }
    
    // Place order - with duplicate submission prevention
    window.placeOrder = function() {
        if (isMenuOnly) { toast('Sipariş verme şu anda aktif değildir'); return; }
        if (cart.length === 0) return;
        
        // CRITICAL: Prevent duplicate submission
        if (isSubmittingOrder) {
            toast('Sipariş gönderiliyor, lütfen bekleyin...');
            return;
        }
        
        const csrfToken = getCSRFToken();
        if (!csrfToken) {
            toast('Güvenlik hatası: Token bulunamadı. Lütfen sayfayı yenileyin.');
            return;
        }
        
        // Set flag and disable button IMMEDIATELY
        isSubmittingOrder = true;
        const orderBtn = document.getElementById('order-btn');
        const originalBtnText = orderBtn.textContent;
        orderBtn.disabled = true;
        orderBtn.textContent = 'Gönderiliyor...';
        orderBtn.style.opacity = '0.7';
        
        // Save cart items before clearing (for potential retry)
        const cartBackup = [...cart];
        
        const items = cart.map(i => ({
            menu_item_id: i.menu_item_id,
            quantity: i.quantity,
            unit_price: i.price,
            variant_id: i.variant ? i.variant.variant_id : null,
            variant_name: i.variant ? i.variant.name : null,
            excluded_ingredients: i.removed_ingredients || [],
            selected_extras: i.selected_extras || [],
            note: i.note || ''
        }));
        
        // Clear cart OPTIMISTICALLY to prevent duplicate on page refresh
        cart = [];
        updateCart();
        saveCart(); // CRITICAL: Sync empty cart to server immediately
        
        fetch(baseUrl + '/api/place-order', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                table_id: tableId,
                items: items,
                customer_note: (document.getElementById('order-note') && document.getElementById('order-note').value) ? document.getElementById('order-note').value.trim() : '',
                customer_session_id: '<?php echo $_SESSION['customer_session_id'] ?? ''; ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeCart();
                toast(data.duplicate ? 'Sipariş zaten alındı' : '✅ Siparişiniz alındı!');
                // Refresh orders and switch to orders tab
                ordersLoaded = false;
                loadOrders();
                setTimeout(() => switchTab('orders'), 500);
            } else {
                // Restore cart on error
                cart = cartBackup;
                updateCart();
                saveCart(); // Sync restored cart to server
                toast(data.error || data.message || 'Sipariş gönderilemedi');
            }
        })
        .catch((error) => {
            console.error('Order error:', error);
            // Restore cart on error
            cart = cartBackup;
            updateCart();
            saveCart(); // Sync restored cart to server
            toast('Bağlantı hatası: ' + (error.message || 'Bilinmeyen hata'));
        })
        .finally(() => {
            // Reset submission flag and button state
            isSubmittingOrder = false;
            orderBtn.disabled = cart.length === 0;
            orderBtn.textContent = originalBtnText;
            orderBtn.style.opacity = '';
        });
    };
    
    // WiFi info modal
    window.showWifiInfo = function() {
        // Remove existing modal if any
        document.getElementById('wifi-modal')?.remove();
        
        const wifiName = <?php echo json_encode($wifi_name ?? ''); ?>;
        const wifiPass = <?php echo json_encode($wifi_password ?? ''); ?>;
        
        const modal = document.createElement('div');
        modal.id = 'wifi-modal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);animation:fadeIn .2s ease';
        modal.innerHTML = `
            <div style="background:white;border-radius:24px;padding:32px;max-width:360px;width:100%;text-align:center;box-shadow:0 25px 50px rgba(0,0,0,0.15);animation:slideUp .3s ease">
                <div style="width:64px;height:64px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
                    <svg style="width:32px;height:32px;color:white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                </div>
                <h3 style="font-size:20px;font-weight:900;color:#1e293b;margin-bottom:4px">WiFi</h3>
                <p style="font-size:13px;color:#94a3b8;margin-bottom:24px">Baglanti bilgileri</p>
                <div style="background:#f8fafc;border-radius:16px;padding:16px;margin-bottom:12px;text-align:left">
                    <div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Ag Adi</div>
                    <div style="font-size:18px;font-weight:800;color:#1e293b">${wifiName || '-'}</div>
                </div>
                <div style="background:#f8fafc;border-radius:16px;padding:16px;margin-bottom:20px;text-align:left">
                    <div style="font-size:10px;font-weight:800;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Sifre</div>
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div style="font-size:18px;font-weight:800;color:#1e293b;font-family:monospace;letter-spacing:1px" id="wifi-pass-text">${wifiPass || '-'}</div>
                        ${wifiPass ? '<button onclick="copyWifiPass()" style="background:#f97316;color:white;border:none;padding:8px 16px;border-radius:10px;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap" id="wifi-copy-btn">Kopyala</button>' : ''}
                    </div>
                </div>
                <button onclick="document.getElementById(\'wifi-modal\').remove()" style="width:100%;padding:14px;background:#f1f5f9;border:none;border-radius:14px;font-size:14px;font-weight:800;color:#64748b;cursor:pointer">Kapat</button>
            </div>
        `;
        document.body.appendChild(modal);
        modal.addEventListener('click', function(e) { if (e.target === modal) modal.remove(); });
    };
    
    window.copyWifiPass = function() {
        const pass = <?php echo json_encode($wifi_password ?? ''); ?>;
        if (navigator.clipboard && pass) {
            navigator.clipboard.writeText(pass).then(() => {
                const btn = document.getElementById('wifi-copy-btn');
                if (btn) { btn.textContent = 'Kopyalandi!'; btn.style.background = '#10b981'; setTimeout(() => { btn.textContent = 'Kopyala'; btn.style.background = '#f97316'; }, 2000); }
            }).catch(() => {
                // Fallback
                const ta = document.createElement('textarea');
                ta.value = pass;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                const btn = document.getElementById('wifi-copy-btn');
                if (btn) { btn.textContent = 'Kopyalandi!'; btn.style.background = '#10b981'; setTimeout(() => { btn.textContent = 'Kopyala'; btn.style.background = '#f97316'; }, 2000); }
            });
        }
    };
    
    // Call waiter - with duplicate submission prevention
    window.callWaiter = function() {
        if (isMenuOnly) { toast('Bu fonksiyon şu anda aktif değildir'); return; }
        // Prevent duplicate submissions
        if (isCallingWaiter) {
            toast('Garson çağrılıyor, lütfen bekleyin...');
            return;
        }
        
        const csrfToken = getCSRFToken();
        if (!csrfToken) {
            toast('Güvenlik hatası: Token bulunamadı. Lütfen sayfayı yenileyin.');
            return;
        }
        
        isCallingWaiter = true;
        toast('Garson çağrılıyor...');
        
        fetch(baseUrl + '/api/call-waiter', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                table_id: tableId,
                type: 'CALL_WAITER'
            })
        })
        .then(r => r.json())
        .then(data => {
            toast(data.success ? '✅ Garson çağrıldı!' : (data.error || data.message || 'Hata oluştu'));
        })
        .catch((error) => {
            console.error('Call waiter error:', error);
            toast('❌ Bağlantı hatası: ' + (error.message || 'Bilinmeyen hata'));
        })
        .finally(() => {
            // Reset flag after 3 seconds to allow calling again (prevent rapid clicks)
            setTimeout(() => { isCallingWaiter = false; }, 3000);
        });
    };
    
    // Request bill - with duplicate submission prevention and order validation
    window.requestBill = function() {
        if (isMenuOnly) { toast('Bu fonksiyon şu anda aktif değildir'); return; }
        if (isRequestingBill) {
            toast('Hesap isteniyor, lütfen bekleyin...');
            return;
        }
        
        // Client-side validation: check if there are any orders
        const hasUnpaidOrders = orders.some(o => !(Number(o.is_paid) === 1 || o.is_paid === true));
        if (!hasUnpaidOrders) {
            toast('Aktif siparişiniz bulunmamaktadır. Önce sipariş verin.');
            return;
        }
        
        const csrfToken = getCSRFToken();
        if (!csrfToken) {
            toast('Güvenlik hatası: Token bulunamadı. Lütfen sayfayı yenileyin.');
            return;
        }
        
        isRequestingBill = true;
        toast('Hesap isteniyor...');
        
        fetch(baseUrl + '/api/request-bill', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                table_id: tableId
            })
        })
        .then(r => r.json())
        .then(data => {
            toast(data.success ? '✅ Hesap istendi!' : (data.error || data.message || 'Hata oluştu'));
        })
        .catch((error) => {
            console.error('Request bill error:', error);
            toast('❌ Bağlantı hatası: ' + (error.message || 'Bilinmeyen hata'));
        })
        .finally(() => {
            setTimeout(() => { isRequestingBill = false; }, 5000);
        });
    };
    
    // Tab switching
    window.switchTab = function(tab) {
        if (isMenuOnly && (tab === 'orders' || tab === 'cart')) return;
        currentTab = tab;
        
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        const tabBtn = document.getElementById('btn-tab-' + tab);
        if (tabBtn) tabBtn.classList.add('active');
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        const tabContent = document.getElementById('tab-' + tab);
        if (tabContent) tabContent.classList.add('active');
        
        // Show/hide categories bar
        const categoriesBar = document.querySelector('.categories');
        if (categoriesBar) {
            categoriesBar.style.display = tab === 'menu' ? '' : 'none';
        }
        
        // Load orders if switching to orders tab
        if (tab === 'orders' && !ordersLoaded) {
            loadOrders();
        }
    };
    
    // Load orders
    function loadOrders(showLoading = true) {
        if (isMenuOnly) return;
        const container = document.getElementById('orders-container');
        if (!container) return;
        if (showLoading) {
            container.innerHTML = '<div class="empty"><div class="empty-icon">⏳</div><h3>Yükleniyor...</h3></div>';
        }
        
        fetch(baseUrl + '/api/orders?table_id=' + tableId)
        .then(r => r.json())
        .then(data => {
            ordersLoaded = true;
            if (data.success) {
                orders = data.orders || [];
                renderOrders();
                updateOrdersBadge();
            } else {
                if (showLoading) {
                    const errorMessage = data && data.error ? escapeHtml(data.error) : '';
                    if (errorMessage) {
                        container.innerHTML = `<div class="empty"><div class="empty-icon">⚠️</div><h3>Oturum Hatası</h3><p>${errorMessage}</p></div>`;
                    } else {
                        container.innerHTML = '<div class="empty"><div class="empty-icon">📋</div><h3>Henüz sipariş yok</h3><p>Menüden sipariş vererek başlayın</p></div>';
                    }
                }
            }
        })
        .catch(() => {
            if (showLoading) {
                container.innerHTML = '<div class="empty"><div class="empty-icon">⚠️</div><h3>Yüklenemedi</h3><p>Lütfen tekrar deneyin</p></div>';
            }
        });
    }
    
    // Grup durumu: kalemlerin preparation_status'una göre Bekliyor / Hazırlanıyor / Hazır
    function getGroupStatus(items) {
        if (!items || items.length === 0) return 'pending';
        const statuses = items.map(i => (i.preparation_status || 'PENDING').toUpperCase());
        if (statuses.every(s => s === 'READY' || s === 'SERVED')) return 'ready';
        if (statuses.some(s => s === 'PREPARING')) return 'preparing';
        return 'pending';
    }
    
    // Sipariş kalemlerini hazırlık ekranına göre grupla (Mutfak, Bar, Nargile vb.)
    function groupItemsByScreen(items) {
        const groups = {};
        (items || []).forEach(item => {
            const sid = item.screen_id || 'kitchen_main';
            const sname = item.screen_name || 'Mutfak';
            if (!groups[sid]) groups[sid] = { screen_id: sid, screen_name: sname, items: [] };
            groups[sid].items.push(item);
        });
        return Object.values(groups);
    }
    
    // Render orders: improved consolidated view with progress tracking
    function renderOrders() {
        const container = document.getElementById('orders-container');
        const isPaid = (o) => Number(o.is_paid) === 1 || o.is_paid === true;
        const displayOrders = orders.filter(o => !isPaid(o));
        
        if (displayOrders.length === 0) {
            if (orders.length > 0) {
                showPaymentCompletedModal();
            } else {
                container.innerHTML = `
                    <div class="empty" style="padding:40px 20px;">
                        <div class="empty-icon" style="font-size:48px;">📋</div>
                        <h3 style="font-size:16px;font-weight:600;margin:12px 0 6px;color:var(--text-primary);">Henüz sipariş yok</h3>
                        <p style="font-size:13px;color:var(--text-muted);">Menüden sipariş vererek başlayın</p>
                        <button onclick="switchTab('menu')" style="margin-top:16px;padding:10px 24px;background:var(--primary,#6366f1);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">Menüye Git</button>
                    </div>`;
            }
            return;
        }
        
        const groupStatusLabels = { 'pending': 'Bekliyor', 'preparing': 'Hazırlanıyor', 'ready': 'Hazır' };
        const groupStatusIcons = { 'pending': '⏳', 'preparing': '🔥', 'ready': '✅' };
        const groupStatusColors = { 'pending': '#f59e0b', 'preparing': '#3b82f6', 'ready': '#10b981' };
        
        container.innerHTML = displayOrders.map(order => {
            const items = order.items || [];
            const totalFromItems = items.reduce((sum, item) => {
                const price = parseFloat(item.price || item.menu_item_price || 0);
                const qty = parseInt(item.quantity || 1);
                return sum + (price * qty);
            }, 0);
            const total = totalFromItems > 0 ? totalFromItems : parseFloat(order.total_amount || order.total || 0);
            const orderTime = order.created_at ? new Date(order.created_at).toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'}) : '';
            const orderId = order.order_id || order.id || 'N/A';
            let groups = groupItemsByScreen(items);
            if (groups.length === 0 && items.length > 0) {
                groups = [{ screen_id: 'kitchen_main', screen_name: 'Siparişler', items: items }];
            }
            groups = groups.map(grp => ({
                ...grp,
                items: (window.Utils && window.Utils.groupOrderItemsForDisplay ? window.Utils.groupOrderItemsForDisplay(grp.items) : grp.items)
            }));
            const allReady = groups.length > 0 && groups.every(g => getGroupStatus(g.items) === 'ready');
            const anyPreparing = groups.some(g => getGroupStatus(g.items) === 'preparing');
            
            // Overall status for progress bar
            const overallStatus = allReady ? 'ready' : (anyPreparing ? 'preparing' : 'pending');
            const overallLabel = groupStatusLabels[overallStatus];
            const overallColor = groupStatusColors[overallStatus];
            
            function renderItem(item) {
                const itemName = item.item_name || item.menu_item_name || item.name || 'Ürün';
                const itemPrice = parseFloat(item.price || item.menu_item_price || 0);
                const itemQty = parseInt(item.quantity || 1);
                const itemNote = item.note || '';
                const customizationsDisplay = item.customizations_display || '';
                const removedIngredients = normalizeIngredientList(item.excluded_ingredients);
                const extraIngredients = normalizeIngredientList(item.selected_extras);
                const itemPrepStatus = (item.preparation_status || 'PENDING').toLowerCase();
                const statusDot = itemPrepStatus === 'ready' || itemPrepStatus === 'served' ? '✅' : (itemPrepStatus === 'preparing' ? '🔥' : '⏳');
                
                const details = [];
                if (item.variant_name) details.push(`<span style="color:var(--primary);font-weight:500;">${escapeHtml(item.variant_name)}</span>`);
                if (itemNote) details.push(`<span style="color:#b45309;">📝 ${escapeHtml(itemNote)}</span>`);
                if (customizationsDisplay) details.push(`<span>${escapeHtml(customizationsDisplay)}</span>`);
                if (removedIngredients.length > 0) details.push(`<span style="color:#dc2626;">✕ ${escapeHtml(removedIngredients.join(', '))}</span>`);
                if (extraIngredients.length > 0) details.push(`<span style="color:#059669;">+ ${escapeHtml(extraIngredients.join(', '))}</span>`);
                const detailsHtml = details.length > 0 ? `<div style="margin-top:3px;font-size:11px;display:flex;flex-wrap:wrap;gap:6px;">${details.join('')}</div>` : '';
                
                return `
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:10px 14px;gap:10px;border-bottom:1px solid rgba(0,0,0,0.04);">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:12px;">${statusDot}</span>
                            <span style="font-size:14px;font-weight:500;color:var(--text-primary);">${escapeHtml(itemName)}</span>
                        </div>
                        ${detailsHtml}
                    </div>
                    <div style="text-align:right;white-space:nowrap;">
                        <div style="font-size:13px;color:var(--text-muted);">x${itemQty}</div>
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);">${formatPrice(itemPrice * itemQty)}</div>
                    </div>
                </div>`;
            }
            
            // Progress bar
            const progressSteps = ['Alındı', 'Hazırlanıyor', 'Hazır'];
            const activeStep = overallStatus === 'ready' ? 3 : (overallStatus === 'preparing' ? 2 : 1);
            const progressHtml = `
                <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 0 8px;margin:0 4px;">
                    ${progressSteps.map((step, i) => {
                        const stepNum = i + 1;
                        const isActive = stepNum <= activeStep;
                        const isCurrent = stepNum === activeStep;
                        const color = isActive ? overallColor : '#e2e8f0';
                        const textColor = isActive ? overallColor : '#94a3b8';
                        return `
                            <div style="display:flex;flex-direction:column;align-items:center;flex:1;position:relative;">
                                <div style="width:28px;height:28px;border-radius:50%;background:${isActive ? color : '#f1f5f9'};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:${isActive ? '#fff' : '#94a3b8'};${isCurrent ? 'box-shadow:0 0 0 4px ' + color + '33;' : ''}transition:all 0.3s;">
                                    ${stepNum === 3 && isActive ? '✓' : stepNum}
                                </div>
                                <span style="font-size:11px;margin-top:4px;color:${textColor};font-weight:${isCurrent ? '600' : '400'};">${step}</span>
                                ${i < 2 ? `<div style="position:absolute;top:14px;left:calc(50% + 18px);width:calc(100% - 36px);height:3px;background:${stepNum < activeStep ? overallColor : '#e2e8f0'};border-radius:2px;transition:background 0.3s;"></div>` : ''}
                            </div>`;
                    }).join('')}
                </div>`;
            
            return `
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-id">Sipariş #${escapeHtml(orderId)}</div>
                            <div class="order-time">${orderTime || ''}</div>
                        </div>
                        <span class="order-status-pill ${overallStatus}">${groupStatusIcons[overallStatus]} ${overallLabel}</span>
                    </div>
                    
                    ${progressHtml}
                    
                    ${allReady ? `
                    <div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-radius:12px;padding:14px 16px;margin:12px 0;text-align:center;">
                        <div style="font-weight:700;color:#059669;font-size:15px;">✅ Siparişiniz hazır!</div>
                        <div style="font-size:12px;color:#047857;margin-top:4px;">Garson tarafından servis edilecektir.</div>
                    </div>
                    ` : ''}
                    
                    ${order.customer_note ? `<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#92400e;">📝 ${escapeHtml(order.customer_note)}</div>` : ''}
                    
                    <div class="order-groups">
                        ${groups.map(grp => {
                            const gStatus = getGroupStatus(grp.items);
                            const label = groupStatusLabels[gStatus] || 'Bekliyor';
                            const icon = groupStatusIcons[gStatus] || '⏳';
                            const gColor = groupStatusColors[gStatus] || '#f59e0b';
                            return `
                            <div class="order-group">
                                <div class="order-group-header">
                                    <span class="order-group-name">${escapeHtml(grp.screen_name)}</span>
                                    <span class="group-status ${gStatus}" title="${label}" style="background:${gColor}15;color:${gColor};border:1px solid ${gColor}30;">${icon} ${label}</span>
                                </div>
                                <div style="padding:0;">
                                    ${grp.items.map(renderItem).join('')}
                                </div>
                            </div>`;
                        }).join('')}
                    </div>
                    
                    <div class="order-total">
                        <span class="order-total-label">Toplam</span>
                        <span class="order-total-value">${formatPrice(total)}</span>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    // Update orders badge (ödeme yapılmamış sipariş sayısı)
    function updateOrdersBadge() {
        if (isMenuOnly) return;
        const isPaid = (o) => Number(o.is_paid) === 1 || o.is_paid === true;
        const unpaidCount = orders.filter(o => !isPaid(o)).length;
        const badge = document.getElementById('tab-orders-badge');
        if (!badge) return;
        if (unpaidCount > 0) {
            badge.textContent = unpaidCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    // Auto refresh orders every 5 seconds (anlık durum güncellemesi için)
    setInterval(() => {
        if (isSubmittingOrder) return;
        
        if (currentTab === 'orders') {
            fetch(baseUrl + '/api/orders?table_id=' + tableId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.orders) {
                    orders = data.orders;
                    renderOrders();
                    updateOrdersBadge();
                }
            })
            .catch(() => {});
        } else {
            fetch(baseUrl + '/api/orders?table_id=' + tableId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.orders) {
                    orders = data.orders;
                    updateOrdersBadge();
                }
            })
            .catch(() => {});
        }
    }, 5000); // 5 saniye - hazırlık ekranlarından gelen güncellemeler daha hızlı yansır
    
    // Init
    updateCart();
    
    // Load orders initially to show badge
    if (!isMenuOnly) {
        setTimeout(() => {
            fetch(baseUrl + '/api/orders?table_id=' + tableId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.orders) {
                    orders = data.orders;
                    updateOrdersBadge();
                }
            })
            .catch(() => {});
        }, 1000);
    }
})();
</script>

<?php require_once __DIR__ . '/../components/notification.php'; ?>
<?php toast_scripts(); ?>
<?php display_queued_toasts(); ?>

</body>
</html>
