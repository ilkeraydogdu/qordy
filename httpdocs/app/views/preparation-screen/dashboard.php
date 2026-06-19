<?php
/**
 * Preparation Screen Dashboard View
 * Based on kitchen dashboard template with dynamic color themes per screen
 */

require_once __DIR__ . '/../../helpers/translations.php';
require_once __DIR__ . '/../partials/icons.php';
require_once __DIR__ . '/../../helpers/toast.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

// Get FormattingService for duration formatting
$formattingService = \App\Core\DependencyFactory::getFormattingService();

// Helper function for duration if not available
if (!function_exists('getDuration')) {
    function getDuration($startTime = null) {
        if (!$startTime) return '';
        $timestamp = is_numeric($startTime) ? intval($startTime) : strtotime($startTime);
        if ($timestamp === false || $timestamp <= 0) return '';
        $diff = floor((time() - $timestamp) / 60);
        if ($diff < 0) return '0 dk';
        if ($diff < 60) return $diff . ' dk';
        $h = floor($diff / 60);
        $m = $diff % 60;
        return $h . 's ' . $m . 'dk';
    }
}

// Get screen and orders data from controller
$screen = $screen ?? null;
$activeOrders = $active_orders ?? [];

if (!$screen) {
    header('Location: ' . BASE_URL . '/unauthorized');
    exit;
}

// Get status constants from controller
$inactiveStatuses = $inactive_statuses ?? [
    defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED',
    defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED'
];
$activeStatuses = $active_statuses ?? [
    defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING',
    defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING',
    defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY'
];

// Get polling interval from config (default 2 seconds for faster updates)
$pollingInterval = defined('KITCHEN_POLLING_INTERVAL') ? KITCHEN_POLLING_INTERVAL : 2000;

// Define status constants early
$statusPending = defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING';
$statusPreparing = defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING';
$statusReady = defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY';

$baseUrl = BASE_URL;
$screenSlug = $screen['slug'] ?? '';
$screenName = $screen['name'] ?? 'Hazırlık Ekranı';

// Dynamic color theme based on screen slug
// Each screen gets a different color but same design
$colorThemes = [
    'nargile' => ['primary' => 'purple', 'primaryHex' => 'rgb(168, 85, 247)', 'primaryRgba' => 'rgba(168, 85, 247, 0.15)', 'primaryText' => 'text-purple-400', 'primaryBg' => 'bg-purple-500/10', 'primaryBorder' => 'border-purple-500', 'primaryButton' => 'bg-purple-600'],
    'cayci' => ['primary' => 'blue', 'primaryHex' => 'rgb(59, 130, 246)', 'primaryRgba' => 'rgba(59, 130, 246, 0.15)', 'primaryText' => 'text-blue-400', 'primaryBg' => 'bg-blue-500/10', 'primaryBorder' => 'border-blue-500', 'primaryButton' => 'bg-blue-600'],
    'bar' => ['primary' => 'amber', 'primaryHex' => 'rgb(245, 158, 11)', 'primaryRgba' => 'rgba(245, 158, 11, 0.15)', 'primaryText' => 'text-amber-400', 'primaryBg' => 'bg-amber-500/10', 'primaryBorder' => 'border-amber-500', 'primaryButton' => 'bg-amber-600'],
    'mutfak' => ['primary' => 'orange', 'primaryHex' => 'rgb(249, 115, 22)', 'primaryRgba' => 'rgba(249, 115, 22, 0.15)', 'primaryText' => 'text-orange-400', 'primaryBg' => 'bg-orange-500/10', 'primaryBorder' => 'border-orange-500', 'primaryButton' => 'bg-orange-600'],
];

// Get theme for this screen — Warm Ember default (amber); slug overrides for multi-station color coding
$brandAmberTheme = ['primary' => 'amber', 'primaryHex' => 'rgb(245, 158, 11)', 'primaryRgba' => 'rgba(245, 158, 11, 0.15)', 'primaryText' => 'text-amber-400', 'primaryBg' => 'bg-amber-500/10', 'primaryBorder' => 'border-amber-500', 'primaryButton' => 'bg-amber-600'];
$theme = $colorThemes[strtolower($screenSlug)] ?? $brandAmberTheme;
$primaryColor = $theme['primary'];
$primaryText = $theme['primaryText'];
$primaryBg = $theme['primaryBg'];
$primaryBorder = $theme['primaryBorder'];
$primaryButton = $theme['primaryButton'];
$primaryRgba = $theme['primaryRgba'];
?>

<div class="q-prep-live-dashboard p-3 sm:p-4 md:p-5 h-full overflow-y-auto bg-[#0a0f1e] text-white animate-slide-up font-['Plus_Jakarta_Sans'] overflow-x-hidden w-full max-w-full q-biz-ops-scroll" style="min-width: 0;">
    <header class="flex flex-col gap-2 sm:gap-3 mb-3 sm:mb-5">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 sm:gap-3">
            <div class="flex flex-col min-w-0">
                <h1 class="text-base sm:text-lg md:text-xl font-black tracking-tight flex items-center gap-2">
                    <span class="<?php echo $primaryText; ?> font-mono uppercase tracking-widest truncate"><?php echo htmlspecialchars($screenName); ?></span>
                </h1>
                <?php
                $slugLower = strtolower($screenSlug);
                $isNargileOrBar = (strpos($slugLower, 'nargile') !== false || strpos($slugLower, 'bar') !== false || strpos($slugLower, 'cayci') !== false || strpos($slugLower, 'içecek') !== false);
                if ($isNargileOrBar): ?>
                <p class="text-slate-400 font-semibold mt-1 text-[10px] sm:text-xs md:text-sm lg:text-base"><?php echo t('preparation_screens.not_kitchen_hint', 'Bu ekran mutfaktan ayrıdır — Sadece bu ekrana atanmış siparişler burada görünür.'); ?></p>
                <?php else: ?>
                <p class="text-slate-500 font-bold mt-1 text-[10px] sm:text-xs md:text-sm lg:text-base"><?php echo t('preparation_screens.dynamic_screen', 'Dinamik hazırlık ekranı'); ?></p>
                <?php endif; ?>
            </div>
            <div class="flex gap-2 shrink-0">
                <div class="px-3 py-2 bg-white/10 rounded-lg border border-white/20 font-bold text-xs uppercase tracking-wide text-center text-white">
                    <span class="active-orders-count <?php echo $primaryText; ?>"><?php echo count($activeOrders); ?></span>
                    <span class="text-slate-300 ml-1"><?php echo t('kitchen.activeOrders', 'Aktif'); ?></span>
                </div>
                <button onclick="toggleFullscreen()" class="btn-touch px-3 sm:px-4 py-2.5 sm:py-3 bg-white/5 rounded-lg sm:rounded-xl border border-white/10 hover:bg-white/10 transition-colors" title="<?php echo t('titles.fullscreen', 'Tam Ekran'); ?>">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                    </svg>
                </button>
                <a href="<?php echo BASE_URL; ?>/logout" class="btn-touch px-3 sm:px-4 py-2.5 sm:py-3 bg-red-500/10 hover:bg-red-500/20 rounded-lg sm:rounded-xl border border-red-500/20 hover:border-red-500/30 transition-colors flex items-center gap-2" title="<?php echo t('common.logout', 'Çıkış Yap'); ?>">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="text-red-400 text-xs sm:text-sm font-bold hidden sm:inline"><?php echo t('common.logout', 'Çıkış'); ?></span>
                </a>
            </div>
        </div>
        
        <!-- Filters and Search -->
        <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
            <div class="flex-1 relative">
                <input type="text" id="preparation-search" placeholder="<?php echo t('kitchen.searchPlaceholder', 'Masa veya sipariş ara...'); ?>" 
                       class="form-input-responsive w-full px-4 py-3 bg-white/5 border border-white/10 rounded-lg sm:rounded-xl text-white placeholder-slate-500 focus:outline-none focus:<?php echo $primaryBorder; ?> transition-colors"
                       onkeyup="filterOrders()">
                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            <select id="preparation-status-filter" onchange="filterOrders()" 
                    class="form-input-responsive filter-button-responsive px-4 py-3 bg-white/5 border border-white/10 rounded-lg sm:rounded-xl text-white focus:outline-none focus:<?php echo $primaryBorder; ?> transition-colors w-full sm:w-auto">
                <option value="all"><?php echo t('kitchen.allStatuses', 'Tüm Durumlar'); ?></option>
                <option value="<?php echo htmlspecialchars($statusPending ?? 'PENDING'); ?>"><?php echo t('kitchen.pending', 'Beklemede'); ?></option>
                <option value="<?php echo htmlspecialchars($statusPreparing ?? 'PREPARING'); ?>"><?php echo t('kitchen.preparing', 'Hazırlanıyor'); ?></option>
                <option value="<?php echo htmlspecialchars($statusReady ?? 'READY'); ?>"><?php echo t('kitchen.ready', 'Hazır'); ?></option>
            </select>
        </div>
    </header>
    
    <?php if (empty($activeOrders)): ?>
        <div class="h-[60vh] flex flex-col items-center justify-center">
            <div class="p-8 rounded-3xl bg-slate-800/30 border border-slate-700/50">
                <?php echo icon_utensils(['class' => 'w-16 h-16 sm:w-20 sm:h-20 mb-4 text-slate-600']); ?>
                <h2 class="text-lg sm:text-xl font-semibold text-slate-400 text-center"><?php echo t('kitchen.kitchenQuiet', 'Mutfak Sessiz'); ?></h2>
                <p class="text-sm text-slate-500 mt-2 text-center">Yeni siparişler burada görünecek</p>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
            <?php foreach ($activeOrders as $order): 
                $orderId = $order['order_id'] ?? '';
                $tableName = $order['table_name'] ?? '';
                if (empty($tableName)) {
                    $tableName = t('table.default', t('table', 'Masa'));
                }
                $status = $order['status'] ?? (defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING');
                $createdAtStr = $order['created_at'] ?? null;
                $createdAt = is_numeric($createdAtStr) ? intval($createdAtStr) : strtotime($createdAtStr ?: 'now');
                
                $orderItems = [];
                if (isset($order['items']) && is_array($order['items'])) {
                    $orderItems = $order['items'];
                }
                
                $isPreparing = $status === $statusPreparing;
                $isReady = $status === $statusReady;
                $isPending = $status === $statusPending;
                
                // Calculate wait time for urgency indicator
                $waitMinutes = floor((time() - $createdAt) / 60);
                $urgencyClass = $waitMinutes > 15 ? 'text-red-400' : ($waitMinutes > 8 ? 'text-amber-400' : 'text-emerald-400');
                
                // Dynamic border class based on theme and status
                $cardBorderClass = $isPreparing ? $primaryBorder . '/60 ring-2 ring-' . $primaryColor . '-500/20' : 
                    ($isReady ? 'border-emerald-500/60 ring-2 ring-emerald-500/20' : 'border-slate-700/50 hover:border-slate-600/50');
            ?>
                <div class="preparation-order-card group bg-gradient-to-b from-slate-800/90 to-slate-900/90 backdrop-blur-sm rounded-2xl border transition-all duration-500 flex flex-col shadow-xl hover:shadow-2xl <?php echo $cardBorderClass; ?>" 
                     data-order-id="<?php echo htmlspecialchars($orderId); ?>" data-status="<?php echo htmlspecialchars($status); ?>" 
                     data-table-name="<?php echo htmlspecialchars(strtolower($tableName)); ?>" 
                     data-order-number="<?php echo htmlspecialchars($orderId); ?>" style="max-height: 85vh;">
                    
                    <!-- Header -->
                    <div class="p-4 sm:p-5 flex items-start justify-between gap-3 border-b border-slate-700/30 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2.5 mb-1.5 flex-wrap">
                                <h3 class="font-bold text-lg sm:text-xl text-white"><?php echo htmlspecialchars($tableName); ?></h3>
                                <?php if (!empty($order['order_source'])): 
                                    $sourceConfig = [
                                        'POS' => ['bg' => 'bg-blue-500/15', 'text' => 'text-blue-400', 'border' => 'border-blue-500/30'],
                                        'QR' => ['bg' => 'bg-violet-500/15', 'text' => 'text-violet-400', 'border' => 'border-violet-500/30'],
                                        'PHONE' => ['bg' => 'bg-pink-500/15', 'text' => 'text-pink-400', 'border' => 'border-pink-500/30']
                                    ];
                                    $src = $sourceConfig[$order['order_source']] ?? ['bg' => 'bg-slate-500/15', 'text' => 'text-slate-400', 'border' => 'border-slate-500/30'];
                                ?>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide <?php echo $src['bg'] . ' ' . $src['text'] . ' border ' . $src['border']; ?>">
                                        <?php echo htmlspecialchars($order['order_source']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                <span class="font-mono">#<?php echo htmlspecialchars(substr($orderId, -8)); ?></span>
                                <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                                <span><?php echo date('H:i', $createdAt); ?></span>
                            </div>
                        </div>
                        
                        <!-- Time Badge -->
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-950/60 border border-slate-700/50">
                                <svg class="w-3.5 h-3.5 <?php echo $urgencyClass; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs font-semibold <?php echo $urgencyClass; ?>"><?php echo getDuration($createdAt); ?></span>
                            </div>
                            <?php 
                            $statusLabels = [
                                $statusPending => ['label' => 'Bekliyor', 'class' => 'bg-amber-500/15 text-amber-400 border-amber-500/30'],
                                $statusPreparing => ['label' => 'Hazırlanıyor', 'class' => $primaryBg . ' ' . $primaryText . ' ' . $primaryBorder . '/30'],
                                $statusReady => ['label' => 'Hazır', 'class' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30']
                            ];
                            $statusInfo = $statusLabels[$status] ?? $statusLabels[$statusPending];
                            ?>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide border <?php echo $statusInfo['class']; ?>">
                                <?php echo $statusInfo['label']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['customer_note'])): ?>
                    <!-- Customer Note -->
                    <div class="px-4 sm:px-5 py-3 bg-amber-500/5 border-b border-amber-500/20">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                            <p class="text-xs text-amber-300/90 leading-relaxed"><?php echo htmlspecialchars($order['customer_note']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Items List -->
                    <div class="flex-1 overflow-y-auto min-h-0 divide-y divide-slate-700/30">
                        <?php if (empty($orderItems)): ?>
                            <div class="p-4 text-center text-slate-500 text-sm"><?php echo t('kitchen.loadingDetails', 'Yükleniyor...'); ?></div>
                        <?php else: ?>
                            <?php foreach ($orderItems as $item): 
                                $quantity = $item['quantity'] ?? 1;
                                $itemName = $item['name'] ?? $item['item_name'] ?? $item['menu_item_name'] ?? t('kitchen.product', 'Ürün');
                                $variantName = $item['variant_name'] ?? null;
                                
                                $excludedIngredientsRaw = $item['excluded_ingredients'] ?? '[]';
                                $excludedIngredients = is_string($excludedIngredientsRaw) ? json_decode($excludedIngredientsRaw, true) : $excludedIngredientsRaw;
                                $excludedIngredients = is_array($excludedIngredients) ? $excludedIngredients : [];
                                
                                $selectedExtrasRaw = $item['selected_extras'] ?? '[]';
                                $selectedExtras = is_string($selectedExtrasRaw) ? json_decode($selectedExtrasRaw, true) : $selectedExtrasRaw;
                                $selectedExtras = is_array($selectedExtras) ? $selectedExtras : [];
                                
                                $itemNote = $item['note'] ?? '';
                                $prepTime = $item['preparation_time'] ?? $item['prep_time'] ?? null;
                                $cookingTime = $item['cooking_time'] ?? null;
                                $serveTime = $item['serve_time'] ?? null;
                                $totalTime = ($prepTime || $cookingTime || $serveTime) ? (($prepTime ?? 0) + ($cookingTime ?? 0) + ($serveTime ?? 0)) : null;
                            ?>
                                <div class="p-4 sm:p-5 hover:bg-slate-800/30 transition-colors">
                                    <div class="flex gap-3">
                                        <!-- Quantity Badge -->
                                        <div class="shrink-0">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg <?php echo $primaryBg; ?> <?php echo $primaryText; ?> font-bold text-sm border <?php echo $primaryBorder; ?>/30">
                                                <?php echo $quantity; ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Item Details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2 mb-1">
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-semibold text-white text-sm sm:text-base leading-tight"><?php echo htmlspecialchars($itemName); ?></h4>
                                                    <?php if (!empty($variantName)): ?>
                                                        <span class="inline-block mt-1 px-2 py-0.5 rounded bg-blue-500/15 text-blue-400 text-[10px] font-medium border border-blue-500/20">
                                                            <?php echo htmlspecialchars($variantName); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($totalTime): ?>
                                                    <span class="shrink-0 px-2 py-1 rounded-md bg-slate-700/50 text-slate-300 text-[10px] font-medium">
                                                        <?php echo $totalTime; ?> dk
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($excludedIngredients) || !empty($selectedExtras)): ?>
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                <?php foreach ($excludedIngredients as $ex): 
                                                    $ingName = is_array($ex) ? ($ex['name'] ?? $ex['ingredient_name'] ?? '') : $ex;
                                                    if (!empty($ingName)):
                                                ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-500/10 text-red-400 text-[10px] font-medium border border-red-500/20">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                        <?php echo htmlspecialchars($ingName); ?>
                                                    </span>
                                                <?php endif; endforeach; ?>
                                                
                                                <?php foreach ($selectedExtras as $ext): 
                                                    $extName = is_array($ext) ? ($ext['name'] ?? '') : $ext;
                                                    if (!empty($extName)):
                                                ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-400 text-[10px] font-medium border border-emerald-500/20">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                        </svg>
                                                        <?php echo htmlspecialchars($extName); ?>
                                                    </span>
                                                <?php endif; endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($itemNote)): ?>
                                            <div class="mt-2 px-2.5 py-1.5 rounded-md bg-amber-500/10 border border-amber-500/20">
                                                <p class="text-[11px] text-amber-300/90 leading-relaxed">
                                                    <span class="font-semibold">Not:</span> <?php echo htmlspecialchars($itemNote); ?>
                                                </p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Button -->
                    <div class="p-4 sm:p-5 bg-slate-900/50 border-t border-slate-700/30 flex-shrink-0">
                        <?php if ($status === $statusPending): ?>
                            <button onclick="updateOrderStatus('<?php echo htmlspecialchars($orderId); ?>', '<?php echo htmlspecialchars($statusPreparing); ?>', this)" 
                                    class="w-full py-3.5 sm:py-4 rounded-xl font-semibold text-sm sm:text-base <?php echo $primaryButton; ?> hover:opacity-90 text-white shadow-lg transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]">
                                <?php echo icon_clock(['class' => 'w-5 h-5']); ?>
                                <span><?php echo t('kitchen.prepare', 'Hazırla'); ?></span>
                            </button>
                        <?php elseif ($status === $statusPreparing): ?>
                            <button onclick="markOrderAsServed('<?php echo htmlspecialchars($orderId); ?>', this)" 
                                    class="w-full py-3.5 sm:py-4 rounded-xl font-semibold text-sm sm:text-base bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white shadow-lg shadow-emerald-500/25 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]">
                                <?php echo icon_check(['class' => 'w-5 h-5']); ?>
                                <span><?php echo t('kitchen.serve', 'Servis Et'); ?></span>
                            </button>
                        <?php else: ?>
                            <button onclick="markOrderAsServed('<?php echo htmlspecialchars($orderId); ?>', this)" 
                                    class="w-full py-3.5 sm:py-4 rounded-xl font-semibold text-sm sm:text-base bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/25 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]">
                                <?php echo icon_check(['class' => 'w-5 h-5']); ?>
                                <span><?php echo t('kitchen.ready', 'Hazır'); ?> — <?php echo t('kitchen.serve', 'Servis Et'); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php echo getToastScript(); ?>

<?php
// Get preparation screen translations for JavaScript
$preparationTranslations = [
    'ready' => t('kitchen.ready', 'Hazır'),
    'serve' => t('kitchen.serve', 'Servis Et'),
    'prepare' => t('kitchen.prepare', 'Hazırla'),
    'orderPreparing' => t('kitchen.orderPreparing', 'Sipariş hazırlanıyor'),
    'orderReady' => t('kitchen.orderReady', 'Sipariş hazır! Garson bilgilendirildi.')
];
?>
<script>
<?php
// Inline safe JSON helper
if (!function_exists('_safeJson')) {
    function _safeJson($data, $default = '[]') {
        if ($data === null) return $default;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }
        return $json;
    }
}
?>
const baseUrl = <?php echo _safeJson($baseUrl ?? '', '""'); ?>;
const screenSlug = <?php echo _safeJson($screenSlug ?? '', '""'); ?>;

if (typeof window.BASE_URL === 'undefined') {
    window.BASE_URL = baseUrl;
}
<?php
$bid = \App\Core\TenantResolver::resolve();
if (empty($bid) && class_exists('\\App\\Core\\TenantContext')) {
    $bid = \App\Core\TenantContext::getId();
}
?>
if (!window.BUSINESS_ID) {
    window.BUSINESS_ID = <?php echo json_encode((string)($bid ?? '')); ?>;
}
if (!window.WEBSOCKET_URL) {
    window.WEBSOCKET_URL = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/ws';
}

if (!window.realtimeService) {
    const script = document.createElement('script');
    script.src = baseUrl + '/assets/js/realtime.js';
    document.head.appendChild(script);
}

const pollingInterval = <?php echo _safeJson($pollingInterval ?? 5000, '5000'); ?>;
const orderStatuses = <?php echo _safeJson([
    'PENDING' => $statusPending ?? 'PENDING',
    'PREPARING' => $statusPreparing ?? 'PREPARING',
    'READY' => $statusReady ?? 'READY',
    'SERVED' => defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED',
    'CANCELLED' => defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED'
], '{}'); ?>;
const inactiveStatuses = <?php echo _safeJson($inactiveStatuses ?? [], '[]'); ?>;

// Preparation screen translations from PHP
window.preparationTranslations = <?php echo _safeJson($preparationTranslations ?? [], '{}'); ?>;

// Notification translations for NotificationManager
window.notificationTranslations = {
    success: <?php echo _safeJson(t('notifications.success', 'Başarılı'), '""'); ?>,
    error: <?php echo _safeJson(t('notifications.error', 'Hata'), '""'); ?>,
    warning: <?php echo _safeJson(t('notifications.warning', 'Uyarı'), '""'); ?>,
    info: <?php echo _safeJson(t('notifications.info', 'Bilgi'), '""'); ?>,
    confirm: <?php echo _safeJson(t('notifications.confirm', 'Onay'), '""'); ?>,
    yes: <?php echo _safeJson(t('notifications.yes', 'Evet'), '""'); ?>,
    no: <?php echo _safeJson(t('notifications.no', 'Hayır'), '""'); ?>,
    input: <?php echo _safeJson(t('notifications.input', 'Giriş'), '""'); ?>,
    ok: <?php echo _safeJson(t('notifications.ok', 'Tamam'), '""'); ?>,
    cancel: <?php echo _safeJson(t('notifications.cancel', 'İptal'), '""'); ?>
};

// Helper function for translations
function t(key) {
    if (typeof window.preparationTranslations !== 'undefined' && window.preparationTranslations[key]) {
        return window.preparationTranslations[key];
    }
    return key;
}

// Use NotificationManager for all notifications (same as kitchen)
// NotificationManager is loaded by admin_layout.php

// Check if order has pending notifications that need approval
function hasPendingNotificationForOrder(orderId) {
    if (!window.pendingNotifications || !orderId) return false;
    
    for (const [notifId, notifData] of window.pendingNotifications.entries()) {
        if (notifData.orderIds && notifData.orderIds.includes(orderId)) {
            return true;
        }
    }
    return false;
}

// Disable order button when notification is pending
function disableOrderButton(orderId) {
    if (!orderId) return;
    
    const buttons = document.querySelectorAll(`[data-order-id="${orderId}"] button`);
    buttons.forEach(button => {
        button.disabled = true;
        button.style.opacity = '0.5';
        button.style.cursor = 'not-allowed';
        button.title = 'Bildirimi onaylamanız gerekiyor';
    });
}

// Enable order button after notification is approved
function enableOrderButton(orderId) {
    if (!orderId) return;
    
    const buttons = document.querySelectorAll(`[data-order-id="${orderId}"] button`);
    buttons.forEach(button => {
        button.disabled = false;
        button.style.opacity = '1';
        button.style.cursor = 'pointer';
        button.title = '';
    });
}

// Approve preparation notification - called when user clicks "Bildirimi Onayla" button
window.approvePreparationNotification = async function(notificationId, orderIds, buttonElement) {
    if (!notificationId || !orderIds || orderIds.length === 0) return;
    
    try {
        // Remove notification element
        if (window.pendingNotifications && window.pendingNotifications.has(notificationId)) {
            const notifData = window.pendingNotifications.get(notificationId);
            if (notifData && notifData.element) {
                if (window.NotificationManager && window.NotificationManager.remove) {
                    window.NotificationManager.remove(notifData.element);
                }
            }
            window.pendingNotifications.delete(notificationId);
        }
        
        // Enable order buttons
        orderIds.forEach(orderId => {
            enableOrderButton(orderId);
        });
        
        // Show success message
        if (window.NotificationManager && window.NotificationManager.success) {
            window.NotificationManager.success('Bildirim onaylandı', 2000);
        }
    } catch (error) {
        console.error('Error approving notification:', error);
    }
};

// Mark order as SERVED and remove from screen
function markOrderAsServed(orderId, buttonElement) {
    if (!buttonElement) {
        buttonElement = document.querySelector(`[data-order-id="${orderId}"] button`);
    }
    
    const orderCard = buttonElement?.closest('[data-order-id]');
    if (!orderCard) {
        console.error('Order card not found:', orderId);
        return;
    }
    
    // Optimistic UI update - hide immediately
    orderCard.style.opacity = '0.5';
    orderCard.style.pointerEvents = 'none';
    buttonElement.disabled = true;
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    const servedStatus = orderStatuses.SERVED || 'SERVED';
    const updateStatusUrl = `${baseUrl}/preparation-screen/${screenSlug}/update-status`;
    
    fetch(updateStatusUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            order_id: orderId,
            status: servedStatus
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove order card from DOM immediately
            orderCard.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
            orderCard.style.opacity = '0';
            orderCard.style.transform = 'scale(0.95)';
            
            setTimeout(() => {
                orderCard.remove();
                // Update order count
                const countElement = document.querySelector('.active-orders-count');
                if (countElement) {
                    const currentCount = parseInt(countElement.textContent) || 0;
                    countElement.textContent = Math.max(0, currentCount - 1);
                }
            }, 300);
            
            if (window.NotificationManager && window.NotificationManager.success) {
                window.NotificationManager.success('Sipariş servis edildi', 2000);
            } else if (window.showToast) {
                window.showToast('Sipariş servis edildi', 'success');
            }
            
            // Refresh orders list after a short delay
            setTimeout(() => {
                loadOrders(true); // true = suppress notifications
            }, 500);
        } else {
            // Revert optimistic update
            orderCard.style.opacity = '1';
            orderCard.style.pointerEvents = '';
            buttonElement.disabled = false;
            
            const errorMsg = data.error || 'Sipariş durumu güncellenemedi';
            if (window.NotificationManager && window.NotificationManager.error) {
                window.NotificationManager.error('Hata: ' + errorMsg);
            } else if (window.showToast) {
                window.showToast(errorMsg, 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert optimistic update
        orderCard.style.opacity = '1';
        orderCard.style.pointerEvents = '';
        buttonElement.disabled = false;
        
        const errorMsg = 'Bir hata oluştu: ' + error.message;
        if (window.NotificationManager && window.NotificationManager.error) {
            window.NotificationManager.error(errorMsg);
        } else if (window.showToast) {
            window.showToast(errorMsg, 'error');
        }
    });
}

function updateOrderStatus(orderId, status, buttonElement) {
    if (!buttonElement) {
        buttonElement = document.querySelector(`[data-order-id="${orderId}"] button`);
    }
    
    // Check if there are pending notifications for this order
    if (hasPendingNotificationForOrder(orderId)) {
        // Find pending notification for this order
        let pendingNotifId = null;
        let pendingOrderIds = [];
        for (const [notifId, notifData] of window.pendingNotifications.entries()) {
            if (notifData.orderIds && notifData.orderIds.includes(orderId)) {
                pendingNotifId = notifId;
                pendingOrderIds = notifData.orderIds;
                break;
            }
        }
        
        // Auto-approve notification when user clicks order button (fallback behavior)
        if (pendingNotifId) {
            window.approvePreparationNotification(pendingNotifId, pendingOrderIds, null);
        } else {
            // Show warning that notification must be approved first
            window.NotificationManager.warning('Bu sipariş için bildirim onayı gerekiyor. Lütfen önce bildirimi onaylayın.');
            return;
        }
    }
    
    const orderCard = buttonElement?.closest('[data-order-id]');
    if (orderCard) {
        orderCard.setAttribute('data-status', status);
        buttonElement.disabled = true;
        buttonElement.style.opacity = '0.5';
    }
    
    const csrfToken = window.CSRF_TOKEN || '';
    const updateStatusUrl = `${baseUrl}/preparation-screen/${screenSlug}/update-status`;
    
    fetch(updateStatusUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            order_id: orderId,
            status: status
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
            const readyStatus = orderStatuses.READY || 'READY';
            
            if (status === readyStatus) {
                // Order is ready - notify waiter (notification is already sent by backend)
                if (window.showToast) {
                    window.showToast(<?php echo json_encode(t('kitchen.orderReady', 'Sipariş hazır! Garson bilgilendirildi.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'success');
                } else if (window.NotificationManager) {
                    window.NotificationManager.success(<?php echo _safeJson(t('kitchen.orderReady', 'Sipariş hazır! Garson bilgilendirildi.'), '""'); ?>);
                }
                // Play success sound only when order is ready
                playNotificationSound();
            } else if (status === preparingStatus) {
                // Order is being prepared - no sound notification, just visual feedback
                if (window.showToast) {
                    window.showToast('Sipariş hazırlanmaya başlandı', 'info');
                } else if (window.NotificationManager) {
                    window.NotificationManager.info('Sipariş hazırlanmaya başlandı');
                }
                // NO SOUND HERE - only visual feedback
            }
            
            setTimeout(() => {
                loadOrders(true);
            }, 1000);
        } else {
            if (orderCard) {
                const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
                const pendingStatus = orderStatuses.PENDING || 'PENDING';
                orderCard.setAttribute('data-status', status === preparingStatus ? pendingStatus : preparingStatus);
                buttonElement.disabled = false;
                buttonElement.style.opacity = '1';
            }
            const errorMsg = data.error || 'Sipariş durumu güncellenemedi';
            if (window.showToast) {
                window.showToast(errorMsg, 'error');
            } else if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + errorMsg);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (orderCard) {
            const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
            const pendingStatus = orderStatuses.PENDING || 'PENDING';
            orderCard.setAttribute('data-status', status === preparingStatus ? pendingStatus : preparingStatus);
            buttonElement.disabled = false;
            buttonElement.style.opacity = '1';
        }
        const errorMsg = 'Bir hata oluştu: ' + error.message;
        if (window.showToast) {
            window.showToast(errorMsg, 'error');
        } else if (window.NotificationManager) {
            window.NotificationManager.error(errorMsg);
        }
    });
}

function loadOrders(suppressNotifications = false) {
    if (rateLimitBlocked && Date.now() < rateLimitUntil) {
        return;
    }
    
    fetch(`${baseUrl}/preparation-screen/${screenSlug}/orders?status=all`)
        .then(response => {
            if (!response.ok) {
                if (response.status === 429) {
                    const retryAfter = 30;
                    rateLimitBlocked = true;
                    rateLimitUntil = Date.now() + (retryAfter * 1000);
                    if (refreshInterval) {
                        clearInterval(refreshInterval);
                        refreshInterval = null;
                    }
                    setTimeout(() => {
                        rateLimitBlocked = false;
                        rateLimitUntil = 0;
                        if (!isUsingWebSocket) {
                            startPolling();
                        }
                    }, retryAfter * 1000);
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            if (!suppressNotifications) {
                if (error.message && error.message.includes('Failed to fetch')) {
                    console.error('Error loading orders: Network error - check your connection');
                } else {
                    console.error('Error loading orders:', error);
                }
            }
            return [];
        })
        .then(orders => {
            if (!Array.isArray(orders)) {
                orders = [];
            }
            
            const activeOrders = orders.filter(order => 
                order && order.status && !inactiveStatuses.includes(order.status)
            );
            
            const pendingStatus = orderStatuses.PENDING || 'PENDING';
            const newPendingOrders = activeOrders.filter(order => order.status === pendingStatus);
            const newPendingOrderIds = new Set(newPendingOrders.map(order => order.order_id));
            const trulyNewOrders = Array.from(newPendingOrderIds).filter(id => !notifiedOrderIds.has(id));
            
            if (trulyNewOrders.length > 0 && !suppressNotifications) {
                trulyNewOrders.forEach(id => notifiedOrderIds.add(id));
                
                // New order arrived - play sound
                playNotificationSound();
                
                // Show notification with approval button
                const message = `${trulyNewOrders.length} yeni sipariş geldi!`;
                const duration = 0; // Don't auto-dismiss - wait for user approval
                
                if (window.NotificationManager && window.NotificationManager.show) {
                    const notificationElement = window.NotificationManager.show(
                        message,
                        'info',
                        duration,
                        {
                            action: `
                                <button class="approve-notification-btn w-full px-4 py-2 bg-orange-500 text-white rounded-xl font-black text-sm hover:bg-orange-600 transition-all" 
                                        data-order-ids="${trulyNewOrders.join(',')}">
                                    Bildirimi Onayla
                                </button>
                            `
                        }
                    );
                    
                    // Store notification element for later removal
                    if (notificationElement) {
                        if (!window.pendingNotifications) {
                            window.pendingNotifications = new Map();
                        }
                        const notificationId = 'preparation-new-orders-' + Date.now();
                        window.pendingNotifications.set(notificationId, {
                            element: notificationElement,
                            orderIds: trulyNewOrders
                        });
                        
                        // Add event listener to approval button
                        setTimeout(() => {
                            const approveBtn = notificationElement.querySelector('.approve-notification-btn');
                            if (approveBtn) {
                                approveBtn.addEventListener('click', function() {
                                    const orderIds = this.getAttribute('data-order-ids').split(',').filter(id => id);
                                    window.approvePreparationNotification(notificationId, orderIds, this);
                                });
                            }
                        }, 100);
                    }
                    
                    // Disable buttons for new orders
                    trulyNewOrders.forEach(orderId => {
                        disableOrderButton(orderId);
                    });
                } else {
                    // Fallback: Show simple notification and disable buttons
                    trulyNewOrders.forEach(orderId => {
                        disableOrderButton(orderId);
                    });
                    
                    if (window.showToast) {
                        window.showToast(message + ' (Sipariş butonuna tıklayarak onaylayın)', 'info');
                    } else if (window.NotificationManager) {
                        window.NotificationManager.info(message + ' (Sipariş butonuna tıklayarak onaylayın)');
                    }
                    
                    // Store notifications for approval when order button is clicked
                    if (!window.pendingNotifications) {
                        window.pendingNotifications = new Map();
                    }
                    const notificationId = 'preparation-new-orders-' + Date.now();
                    window.pendingNotifications.set(notificationId, {
                        element: null,
                        orderIds: trulyNewOrders
                    });
                }
            }
            
            const allActiveOrderIds = new Set(activeOrders.map(order => order.order_id));
            const currentPendingOrderIds = new Set(activeOrders
                .filter(order => order.status === (orderStatuses.PENDING || 'PENDING'))
                .map(order => order.order_id));
            
            notifiedOrderIds.forEach(id => {
                if (!allActiveOrderIds.has(id) || !currentPendingOrderIds.has(id)) {
                    notifiedOrderIds.delete(id);
                }
            });
            
            const countElement = document.querySelector('.active-orders-count');
            if (countElement) {
                countElement.textContent = activeOrders.length;
            }
            
            updateOrdersGrid(activeOrders);
        })
        .catch(error => {
            console.error('Error loading orders:', error);
        });
}

let refreshInterval = null;
let isUsingWebSocket = false;
let rateLimitBlocked = false;
let rateLimitUntil = 0;
let notifiedOrderIds = new Set(<?php 
$orderIds = is_array($activeOrders) ? array_column($activeOrders, 'order_id') : [];
$orderIds = array_filter($orderIds);
$jsonOrderIds = json_encode($orderIds ?: [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($jsonOrderIds === false || json_last_error() !== JSON_ERROR_NONE) {
    $jsonOrderIds = '[]';
}
echo $jsonOrderIds;
?>);

// Theme colors from PHP
const themeColors = <?php echo _safeJson($theme, '{}'); ?>;

function createOrderCard(order, container) {
    const oid = order.order_id || '';
    const status = order.status || 'PENDING';
    const pendingStatus = orderStatuses.PENDING || 'PENDING';
    const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
    const readyStatus = orderStatuses.READY || 'READY';
    const isPreparing = status === preparingStatus;
    const isReady = status === readyStatus;
    const tableName = order.table_name || order.tableName || 'Masa';
    const primaryBorder = themeColors.primaryBorder || 'border-orange-500';
    const primaryColor = themeColors.primary || 'orange';
    const primaryButton = themeColors.primaryButton || 'bg-orange-600';
    const borderCls = isPreparing ? primaryBorder + '/60 ring-2 ring-' + primaryColor + '-500/20' :
                      (isReady ? 'border-emerald-500/60 ring-2 ring-emerald-500/20' : 'border-slate-700/50');
    const createdAt = order.created_at ? new Date(order.created_at) : new Date();
    const timeStr = createdAt.toLocaleTimeString('tr-TR', {hour: '2-digit', minute: '2-digit'});
    const waitMin = Math.floor((Date.now() - createdAt.getTime()) / 60000);
    const urgClass = waitMin > 15 ? 'text-red-400' : (waitMin > 8 ? 'text-amber-400' : 'text-emerald-400');
    const si = {[pendingStatus]: {l:'Bekliyor',c:'bg-amber-500/15 text-amber-400 border-amber-500/30'},
                [preparingStatus]: {l:'Hazırlanıyor',c:(themeColors.primaryBg||'bg-orange-500/15')+' '+(themeColors.primaryText||'text-orange-400')+' '+primaryBorder+'/30'},
                [readyStatus]: {l:'Hazır',c:'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'}}[status] || {l:'Bekliyor',c:'bg-amber-500/15 text-amber-400 border-amber-500/30'};
    const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const escId = esc(oid).replace(/'/g, "\\'");

    let itemsHtml = '';
    const items = (window.Utils && window.Utils.groupOrderItemsForDisplay ? window.Utils.groupOrderItemsForDisplay(order.items || []) : (order.items || []));
    items.forEach(item => {
        const qty = item.quantity || 1;
        const name = item.name || item.item_name || item.menu_item_name || 'Ürün';
        itemsHtml += `<div class="p-4 hover:bg-slate-800/30"><div class="flex gap-3">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-orange-500/15 text-orange-400 font-bold text-sm border border-orange-500/30">${qty}</span>
            <div class="flex-1"><h4 class="font-semibold text-white text-sm">${esc(name)}</h4></div></div></div>`;
    });

    let btnHtml = '';
    const prepareText = window.preparationTranslations?.prepare || 'Hazırla';
    const serveText = window.preparationTranslations?.serve || 'Servis Et';
    if (status === pendingStatus) {
        btnHtml = `<button onclick="updateOrderStatus('${escId}','${esc(preparingStatus).replace(/'/g,"\\'")}',this)" class="w-full py-3.5 rounded-xl font-semibold text-sm ${primaryButton} hover:opacity-90 text-white shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50 active:scale-[0.98]"><span>${prepareText}</span></button>`;
    } else if (status === preparingStatus) {
        btnHtml = `<button onclick="markOrderAsServed('${escId}',this)" class="w-full py-3.5 rounded-xl font-semibold text-sm bg-gradient-to-r from-emerald-600 to-emerald-500 text-white shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50 active:scale-[0.98]"><span>${serveText}</span></button>`;
    }

    const card = document.createElement('div');
    card.className = `preparation-order-card group bg-gradient-to-b from-slate-800/90 to-slate-900/90 backdrop-blur-sm rounded-2xl border transition-all duration-500 flex flex-col shadow-xl hover:shadow-2xl ${borderCls}`;
    card.setAttribute('data-order-id', oid);
    card.setAttribute('data-status', status);
    card.setAttribute('data-table-name', tableName.toLowerCase());
    card.style.maxHeight = '85vh';
    card.innerHTML = `
        <div class="p-4 flex items-start justify-between gap-3 border-b border-slate-700/30 flex-shrink-0">
            <div class="flex-1 min-w-0"><h3 class="font-bold text-lg text-white">${esc(tableName)}</h3>
                <div class="flex items-center gap-2 text-xs text-slate-500"><span class="font-mono">#${esc(oid.slice(-8))}</span><span class="w-1 h-1 rounded-full bg-slate-600"></span><span>${timeStr}</span></div></div>
            <div class="flex flex-col items-end gap-1 shrink-0">
                <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-slate-950/60 border border-slate-700/50">
                    <span class="text-xs font-semibold ${urgClass}">${waitMin}dk</span></div>
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide border ${si.c}">${si.l}</span></div></div>
        <div class="flex-1 overflow-y-auto min-h-0 divide-y divide-slate-700/30">${itemsHtml || '<div class="p-4 text-center text-slate-500 text-sm">Yükleniyor...</div>'}</div>
        <div class="p-3 bg-slate-900/50 border-t border-slate-700/30 flex-shrink-0">${btnHtml}</div>`;
    container.appendChild(card);
}

function updateOrdersGrid(orders) {
    const container = document.querySelector('.grid');
    if (!container) return;
    
    const currentOrderIds = new Set(Array.from(document.querySelectorAll('[data-order-id]'))
        .map(el => el.getAttribute('data-order-id')));
    const newOrderIds = new Set(orders.map(o => o.order_id));
    
    currentOrderIds.forEach(orderId => {
        if (!newOrderIds.has(orderId)) {
            const orderCard = document.querySelector(`[data-order-id="${orderId}"]`);
            if (orderCard) {
                orderCard.remove();
            }
        }
    });
    
    let hasNewOrders = false;
    orders.forEach(order => {
        const existingCard = document.querySelector(`[data-order-id="${order.order_id}"]`);
        const status = order.status || 'PENDING';
        const pendingStatus = orderStatuses.PENDING || 'PENDING';
        const preparingStatus = orderStatuses.PREPARING || 'PREPARING';
        const readyStatus = orderStatuses.READY || 'READY';
        
        if (existingCard) {
            existingCard.setAttribute('data-status', status);
            const isPreparing = status === preparingStatus;
            const isReady = status === readyStatus;
            const primaryBorder = themeColors.primaryBorder || 'border-orange-500';
            const primaryColor = themeColors.primary || 'orange';
            
            // Update card classes with new minimal design
            existingCard.className = `preparation-order-card group bg-gradient-to-b from-slate-800/90 to-slate-900/90 backdrop-blur-sm rounded-2xl border transition-all duration-500 flex flex-col shadow-xl hover:shadow-2xl ${
                isPreparing ? primaryBorder + '/60 ring-2 ring-' + primaryColor + '-500/20' : 
                (isReady ? 'border-emerald-500/60 ring-2 ring-emerald-500/20' : 'border-slate-700/50 hover:border-slate-600/50')
            }`;
            existingCard.style.maxHeight = '85vh';
            
            // Update status badge
            const statusBadge = existingCard.querySelector('[class*="uppercase tracking-wide border"]');
            if (statusBadge) {
                const primaryBg = themeColors.primaryBg || 'bg-orange-500/15';
                const primaryText = themeColors.primaryText || 'text-orange-400';
                const statusLabels = {
                    [pendingStatus]: { label: 'Bekliyor', class: 'bg-amber-500/15 text-amber-400 border-amber-500/30' },
                    [preparingStatus]: { label: 'Hazırlanıyor', class: primaryBg + ' ' + primaryText + ' ' + primaryBorder + '/30' },
                    [readyStatus]: { label: 'Hazır', class: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' }
                };
                const statusInfo = statusLabels[status] || statusLabels[pendingStatus];
                statusBadge.className = `px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wide border ${statusInfo.class}`;
                statusBadge.textContent = statusInfo.label;
            }
            
            // Update button area based on status
            const buttonArea = existingCard.querySelector('.bg-slate-900\\/50.border-t');
            if (buttonArea) {
                const escapedOrderId = String(order.order_id || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
                const escapedPreparingStatus = String(preparingStatus || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
                const prepareText = window.preparationTranslations?.prepare || 'Hazırla';
                const serveText = window.preparationTranslations?.serve || 'Servis Et';
                const readyText = window.preparationTranslations?.ready || 'Hazır';
                const primaryButton = themeColors.primaryButton || 'bg-orange-600';
                
                if (status === pendingStatus) {
                    buttonArea.innerHTML = `
                        <button onclick="updateOrderStatus('${escapedOrderId}', '${escapedPreparingStatus}', this)" 
                                class="w-full py-3.5 sm:py-4 rounded-xl font-semibold text-sm sm:text-base ${primaryButton} hover:opacity-90 text-white shadow-lg transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${prepareText}</span>
                        </button>
                    `;
                } else if (status === preparingStatus) {
                    buttonArea.innerHTML = `
                        <button onclick="markOrderAsServed('${escapedOrderId}', this)" 
                                class="w-full py-3.5 sm:py-4 rounded-xl font-semibold text-sm sm:text-base bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-white shadow-lg shadow-emerald-500/25 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>${serveText}</span>
                        </button>
                    `;
                } else if (status === readyStatus) {
                    buttonArea.innerHTML = `
                        <button onclick="markOrderAsServed('${escapedOrderId}', this)" 
                                class="w-full py-3.5 sm:py-4 rounded-xl font-semibold text-sm sm:text-base bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/25 transition-all duration-300 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>${readyText} — ${serveText}</span>
                        </button>
                    `;
                }
            }
        } else {
            createOrderCard(order, container);
        }
    });
    
    const countElement = document.querySelector('.active-orders-count');
    if (countElement) {
        countElement.textContent = orders.length;
    }
}

function initRealtimeUpdates() {
    loadOrders(); // Initial load
    
    if (typeof window.realtimeService !== 'undefined' && window.realtimeService) {
        isUsingWebSocket = true;
        window.realtimeService.start('orders', (data) => {
            if (data && (data.type === 'ORDER_UPDATE' || data.type === 'order.updated' || data.type === 'ORDER_CREATED' || data.type === 'order.created')) {
                loadOrders();
            } else if (Array.isArray(data)) {
                updateOrdersGrid(data.filter(order => !inactiveStatuses.includes(order.status)));
            } else {
                loadOrders();
            }
        }, { interval: pollingInterval || 2000, useCustomLoader: true });
        
        window.realtimeService.onStatusChange((status) => {
            if (status === 'connected') {
                if (refreshInterval) { clearInterval(refreshInterval); refreshInterval = null; }
            } else {
                if (!refreshInterval && !document.hidden) startPolling();
            }
        });
        
        if (window.realtimeService.connectionStatus !== 'connected') {
            startPolling();
        }
        console.log('Preparation screen: WebSocket + polling fallback');
    } else {
        isUsingWebSocket = false;
        startPolling();
        console.log('Preparation screen: Polling only');
    }
}

function startPolling() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    refreshInterval = setInterval(() => {
        loadOrders();
    }, pollingInterval || 2000); // Reduced from 5s to 2s for faster updates
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initRealtimeUpdates();
    });
} else {
    initRealtimeUpdates();
}

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    } else {
        // When tab visible: poll if WS not connected (isUsingWebSocket but status may be disconnected)
        const wsConnected = window.realtimeService?.connectionStatus === 'connected';
        if (!wsConnected && !refreshInterval) {
            startPolling();
        }
    }
});

// Cleanup on page unload to prevent memory leaks
window.addEventListener('beforeunload', () => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
});

function filterOrders() {
    const searchTerm = (document.getElementById('preparation-search')?.value || '').toLowerCase();
    const statusFilter = document.getElementById('preparation-status-filter')?.value || 'all';
    const orderCards = document.querySelectorAll('.preparation-order-card');
    
    let visibleCount = 0;
    orderCards.forEach(card => {
        const tableName = (card.getAttribute('data-table-name') || '').toLowerCase();
        const orderNumber = (card.getAttribute('data-order-number') || '').toLowerCase();
        const status = card.getAttribute('data-status') || '';
        
        const matchesSearch = !searchTerm || tableName.includes(searchTerm) || orderNumber.includes(searchTerm);
        const matchesStatus = statusFilter === 'all' || status === statusFilter;
        
        if (matchesSearch && matchesStatus) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    const countElement = document.querySelector('.active-orders-count');
    if (countElement) {
        countElement.textContent = visibleCount;
    }
}

function toggleFullscreen() {
    const sidebars = document.querySelectorAll('[id*="sidebar"], [class*="sidebar"], [id*="menu"], [class*="menu"]');
    sidebars.forEach(sidebar => {
        if (sidebar && sidebar.style) {
            sidebar.style.display = 'none';
        }
    });
    
    // Check if fullscreen API is supported
    if (!document.documentElement.requestFullscreen && 
        !document.documentElement.webkitRequestFullscreen && 
        !document.documentElement.mozRequestFullScreen && 
        !document.documentElement.msRequestFullscreen) {
        // Fullscreen not supported
        return;
    }
    
    // Toggle fullscreen
    if (!document.fullscreenElement && 
        !document.webkitFullscreenElement && 
        !document.mozFullScreenElement && 
        !document.msFullscreenElement) {
        // Enter fullscreen
        const requestFullscreen = document.documentElement.requestFullscreen ||
                                  document.documentElement.webkitRequestFullscreen ||
                                  document.documentElement.mozRequestFullScreen ||
                                  document.documentElement.msRequestFullscreen;
        
        if (requestFullscreen) {
            requestFullscreen.call(document.documentElement).catch(err => {
                // Only log errors that aren't related to user gesture requirements
                const errorMessage = err.message || err.toString() || '';
                if (!errorMessage.toLowerCase().includes('permission') && 
                    !errorMessage.toLowerCase().includes('not allowed') &&
                    !errorMessage.toLowerCase().includes('user gesture')) {
                    console.error('Fullscreen error:', err);
                }
            });
        }
    } else {
        // Exit fullscreen
        const exitFullscreen = document.exitFullscreen ||
                               document.webkitExitFullscreen ||
                               document.mozCancelFullScreen ||
                               document.msExitFullscreen;
        
        if (exitFullscreen) {
            exitFullscreen.call(document).catch(err => {
                console.error('Exit fullscreen error:', err);
            });
        }
    }
}

document.addEventListener('fullscreenchange', function() {
    const sidebars = document.querySelectorAll('[id*="sidebar"], [class*="sidebar"], [id*="menu"], [class*="menu"]');
    if (document.fullscreenElement) {
        sidebars.forEach(sidebar => {
            if (sidebar && sidebar.style) {
                sidebar.style.display = 'none';
            }
        });
    }
});

function playNotificationSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
    } catch (e) {
        console.error('Sound error:', e);
    }
}
</script>
