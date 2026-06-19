<?php
/**
 * Navigation Renderer Helper
 * Centralized functions for rendering navigation dropdowns
 *
 * v2.4 — Defensive: tüm callable parametreler null-safe (kökten çözüm:
 * "Value of type null is not callable" hatası bir daha oluşamaz)
 */

if (!function_exists('renderNavigationDropdown')) {
 /**
 * Render a navigation dropdown (desktop or mobile)
 *
 * @param array $items Navigation items
 * @param string $dropdownId Unique ID for the dropdown
 * @param string $label Translation key for dropdown label
 * @param string $icon Icon name for dropdown
 * @param callable $checkActive Function to check if item is active
 * @param callable $checkGroupActive Function to check if group is active
 * @param array $options Additional options (sortOrder, filterCallback, mainItemId, isMobile, etc.)
 * @return string Rendered HTML
 */
 function renderNavigationDropdown($items, $dropdownId, $label, $icon, $checkActive, $checkGroupActive, $options = []) {
 if (empty($items)) {
 return '';
 }


 $isMobile = $options['isMobile'] ?? false;
 $sortOrder = $options['sortOrder'] ?? [];
 $filterCallback = $options['filterCallback'] ?? null;
 $mainItemId = $options['mainItemId'] ?? null;
 $processChildren = $options['processChildren'] ?? false;

 // Filter items if callback provided
 $filteredItems = $items;
 if ($filterCallback && is_callable($filterCallback)) {
 $filteredItems = array_filter($filteredItems, $filterCallback);
 }

 // Process children if needed (for operations group)
 if ($processChildren) {
 $processedItems = [];
 $menuItem = array_filter($filteredItems, function($item) { return ($item['id'] ?? '') === 'MENU'; });
 $menuItem = !empty($menuItem) ? reset($menuItem) : null;

  if ($menuItem && !empty($menuItem['children']) && is_array($menuItem['children'])) {
 foreach ($menuItem['children'] as $child) {
 if (($child['id'] ?? '') === 'MENU_ITEMS') {
 $processedItems[] = $child;
 }
 }
 }

 foreach ($filteredItems as $item) {
 $itemId = $item['id'] ?? '';
 if ($itemId === 'MENU_ITEMS') {
 $alreadyExists = false;
 foreach ($processedItems as $existing) {
 if (($existing['id'] ?? '') === 'MENU_ITEMS') {
 $alreadyExists = true;
 break;
 }
 }
 if ($alreadyExists) continue;
 }
 $processedItems[] = $item;
 }
 $filteredItems = $processedItems;
 }

 // Sort items
 if (!empty($sortOrder)) {
 usort($filteredItems, function($a, $b) use ($sortOrder) {
 $idA = $a['id'] ?? '';
 $idB = $b['id'] ?? '';
 $orderA = $sortOrder[$idA] ?? (isset($a['display_order']) ? (int)$a['display_order'] + 100 : 999);
 $orderB = $sortOrder[$idB] ?? (isset($b['display_order']) ? (int)$b['display_order'] + 100 : 999);
 return $orderA <=> $orderB;
 });
 }

 $hasSubItems = !empty($filteredItems);
 $totalItems = count($filteredItems);
 $groupActive = $checkGroupActive($filteredItems);

 // Get main item if specified
 $mainItem = null;
 // First check if mainItem is passed directly in options
 if (isset($options['mainItem']) && is_array($options['mainItem'])) {
 $mainItem = $options['mainItem'];
 // If mainItem is provided, also filter it out from filteredItems to prevent duplicate
 if ($mainItemId) {
 $filteredItems = array_filter($filteredItems, function($item) use ($mainItemId) {
 return ($item['id'] ?? '') !== $mainItemId;
 });
 } else {
 // If no mainItemId but mainItem has id, use that
 $mainItemIdFromItem = $mainItem['id'] ?? '';
 if ($mainItemIdFromItem) {
 $filteredItems = array_filter($filteredItems, function($item) use ($mainItemIdFromItem) {
 return ($item['id'] ?? '') !== $mainItemIdFromItem;
 });
 }
 }
 } elseif ($mainItemId) {
 // Try to find main item in filteredItems
 $mainItems = array_filter($filteredItems, function($item) use ($mainItemId) {
 return ($item['id'] ?? '') === $mainItemId;
 });
 $mainItem = !empty($mainItems) ? reset($mainItems) : null;
 if ($mainItem) {
 $filteredItems = array_filter($filteredItems, function($item) use ($mainItemId) {
 return ($item['id'] ?? '') !== $mainItemId;
 });
 }
 }

 // If only one item, render as standalone link
 if ($totalItems === 1 && !$mainItem) {
 $singleItem = reset($filteredItems);
 return renderNavigationLink($singleItem, $checkActive, $isMobile);
 }

 // Render dropdown
 $mobileSuffix = $isMobile ? '-mobile' : '';
 $mobileClasses = $isMobile ? 'gap-3 sm:gap-4 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl text-xs sm:text-sm' : 'gap-2.5 lg:gap-3 xl:gap-3.5 px-4 lg:px-5 xl:px-6 py-3 lg:py-3.5 xl:py-4 rounded-xl lg:rounded-2xl xl:rounded-[24px] text-sm lg:text-base xl:text-lg';
 $iconSize = $isMobile ? 'w-4 h-4 sm:w-5 sm:h-5' : 'w-4 h-4 lg:w-5 lg:h-5 xl:w-5 xl:h-5';
 $chevronSize = $isMobile ? 'w-4 h-4' : 'w-3 h-3 lg:w-3.5 lg:h-3.5 xl:w-4 xl:h-4';
 $subItemClasses = $isMobile ? 'px-4 py-2.5 rounded-lg text-xs sm:text-sm border-l-4' : 'px-4 py-2 text-xs lg:text-sm rounded-lg mx-1 border-l-2';

 $translatedLabel = t($label, ucfirst(str_replace(['navigation.', 'nav.'], '', $label)));

 // CRITICAL: Türkçe fallback labels — çeviri sisteminde yoksa
 // İngilizce yerine doğru Türkçe label göster. Görseldeki
 // "Ekranlar / İşlemler / Finans / Analizler / Ayarlar" yapısı.
 $turkishFallbacks = [
 'navigation.dashboard' => 'Özet',
 'navigation.super_admin_dashboard' => 'Süper Admin Özeti',
 'navigation.screens' => 'Ekranlar',
 'navigation.operations' => 'İşlemler',
 'navigation.finance' => 'Finans',
 'navigation.analytics' => 'Analizler',
 'navigation.hr' => 'İnsan Kaynakları',
 'navigation.settings' => 'Ayarlar',
 'navigation.sass_management' => 'SaaS Yönetimi',
 ];
 if (isset($turkishFallbacks[$label])) {
 $translatedLabel = t($label, $turkishFallbacks[$label]);
 }

 ob_start();
 ?>
 <div class="relative dropdown-group" data-dropdown="<?php echo htmlspecialchars($dropdownId); ?>">
 <button type="button" onclick="<?php echo $isMobile ? 'toggleMobileDropdown' : 'toggleDropdown'; ?>('<?php echo htmlspecialchars($dropdownId); ?>')"
 class="w-full flex items-center <?php echo $mobileClasses; ?> font-black transition-all cursor-pointer
 <?php echo $groupActive ? 'bg-slate-900 text-white ' . ($isMobile ? 'shadow-lg' : 'shadow-2xl scale-[1.02]') : 'text-slate-500 hover:bg-slate-50'; ?>">
 <?php if ($mainItem): ?>
 <div class="flex items-center gap-2 lg:gap-2.5 xl:gap-3 flex-1">
 <?php
 $mainItemIcon = $mainItem['icon'] ?? $icon ?? '';
 if (empty($mainItemIcon) || $mainItemIcon === 'null' || $mainItemIcon === 'NULL') {
 $mainItemIcon = '';
 }
 echo getIcon($mainItemIcon ?: 'Circle', $iconSize . ' ' . ($groupActive ? 'text-orange-500' : 'text-slate-400'));
 ?>
 <span class="flex-1 text-left <?php echo $isMobile ? 'truncate' : ''; ?>"><?php echo htmlspecialchars($mainItem['label'] ?? $translatedLabel); ?></span>
 </div>
 <?php else: ?>
 <div class="flex items-center gap-2 lg:gap-2.5 xl:gap-3 flex-1">
 <?php
 $fallbackIcon = $icon ?? '';
 if (empty($fallbackIcon) || $fallbackIcon === 'null' || $fallbackIcon === 'NULL') {
 $fallbackIcon = '';
  }
 echo getIcon($fallbackIcon ?: 'Circle', $iconSize . ' ' . ($groupActive ? 'text-orange-500' : 'text-slate-400'));
 ?>
 <span class="flex-1 text-left <?php echo $isMobile ? 'truncate' : ''; ?>"><?php echo htmlspecialchars($translatedLabel); ?></span>
  </div>
 <?php endif; ?>
 <?php if ($hasSubItems): ?>
 <svg id="chevron-<?php echo htmlspecialchars($dropdownId . $mobileSuffix); ?>"
 class="<?php echo $chevronSize; ?> transition-transform duration-300 <?php echo $isMobile ? '' : 'pointer-events-none'; ?> flex-shrink-0 <?php echo $groupActive ? 'rotate-180' : ''; ?>"
 fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
 </svg>
 <?php endif; ?>
 </button>
 <?php if ($hasSubItems): ?>
 <div id="dropdown-<?php echo htmlspecialchars($dropdownId . $mobileSuffix); ?>"
 class="<?php echo $isMobile ? 'hidden pl-4 space-y-1 bg-slate-50 rounded-lg py-2 mt-2 border border-slate-200' : 'overflow-hidden transition-all duration-300 ease-in-out'; ?>"
 <?php if (!$isMobile): ?>style="max-height: <?php echo $groupActive ? '1000px' : '0'; ?>" data-initial-state="<?php echo $groupActive ? 'open' : 'closed'; ?>"<?php endif; ?>>
 <?php if (!$isMobile): ?>
 <div class="mt-2 bg-slate-50/80 rounded-xl border border-slate-200 py-1.5 ml-3">
 <?php endif; ?>
 <?php if ($mainItem): ?>
 <?php
 $mainItemUrl = $options['mainItemUrl'] ?? ($mainItem['url'] ?? '');
 $mainActive = $checkActive($mainItemUrl, $mainItem['id'] ?? '');
 ?>
 <a href="<?php echo htmlspecialchars($mainItemUrl); ?>"
 <?php if ($isMobile): ?>onclick="if(document.getElementById('mobile-nav-overlay')) { document.getElementById('mobile-nav-overlay').classList.add('hidden'); }"<?php endif; ?>
 class="block <?php echo $subItemClasses; ?> font-black transition-all duration-150 <?php echo $mainActive ? 'bg-slate-100 text-slate-900 border-orange-500' : 'text-slate-600 hover:bg-slate-100 border-transparent'; ?>">
 <?php
 $mainItemSubIcon = $mainItem['icon'] ?? $icon ?? '';
 if (empty($mainItemSubIcon) || $mainItemSubIcon === 'null' || $mainItemSubIcon === 'NULL') {
 $mainItemSubIcon = '';
 }
 echo getIcon($mainItemSubIcon ?: 'Circle', 'w-4 h-4 inline mr-3 text-slate-500 ' . ($mainActive ? 'text-orange-500' : ''));
 ?>
 <?php echo htmlspecialchars($mainItem['label'] ?? $translatedLabel); ?>
 </a>
 <?php if (!empty($filteredItems)): ?>
 <div class="border-t border-slate-200 my-1 <?php echo $isMobile ? '' : 'mx-2'; ?>"></div>
 <?php endif; ?>
 <?php endif; ?>
 <?php foreach ($filteredItems as $subItem):
 $subItemId = $subItem['id'] ?? '';
 $subItemUrl = $subItem['url'] ?? '';
 $subActive = $checkActive($subItemUrl, $subItemId);

 if (!empty($subItemUrl) && strpos($subItemUrl, '/') !== 0) {
 $subItemUrl = '/' . $subItemUrl;
 }

 $subItemIcon = $subItem['icon'] ?? '';
 if (empty($subItemIcon) || $subItemIcon === 'null' || $subItemIcon === 'NULL') {
 $subItemIcon = '';
 }

 // Use iconFallback if icon is still empty
 $iconFallback = $options['iconFallback'] ?? [];
 if (empty($subItemIcon) && !empty($iconFallback[$subItemId])) {
 $subItemIcon = $iconFallback[$subItemId];
  }

 $labelKey = 'navigation.' . strtolower($subItemId);
 $translatedLabel = t($labelKey, $subItem['label'] ?? '');
 ?>
 <a href="<?php echo BASE_URL . htmlspecialchars($subItemUrl); ?>"
 <?php if ($isMobile): ?>onclick="if(document.getElementById('mobile-nav-overlay')) { document.getElementById('mobile-nav-overlay').classList.add('hidden'); }"<?php endif; ?>
 class="block <?php echo $subItemClasses; ?> font-black transition-all duration-150 <?php echo $subActive ? 'bg-slate-100 text-slate-900 border-orange-500' : ($isMobile ? 'text-slate-600 hover:bg-slate-100 border-transparent' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 border-transparent'); ?>">
 <?php echo getIcon($subItemIcon ?: 'Circle', 'w-4 h-4 inline mr-2.5 text-slate-500 ' . ($subActive ? 'text-orange-500' : '')); ?>
 <?php echo htmlspecialchars($translatedLabel); ?>
 </a>
 <?php endforeach; ?>
 <?php if (!$isMobile): ?>
 </div>
 <?php endif; ?>
 </div>
 <?php endif; ?>
 </div>
 <?php
 return ob_get_clean();
 }
}

if (!function_exists('renderNavigationLink')) {
 /**
 * Render a standalone navigation link
 */
 function renderNavigationLink($item, $checkActive, $isMobile = false) {

 $itemUrl = $item['url'] ?? '';
 $itemId = $item['id'] ?? '';
 $isActive = $checkActive($itemUrl, $itemId);
 $url = BASE_URL . $itemUrl;

 $mobileClasses = $isMobile ? 'gap-3 sm:gap-4 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl text-xs sm:text-sm' : 'gap-2.5 lg:gap-3 xl:gap-3.5 px-4 lg:px-5 xl:px-6 py-3 lg:py-3.5 xl:py-4 rounded-xl lg:rounded-2xl xl:rounded-[24px] text-sm lg:text-base xl:text-lg';
 $iconSize = $isMobile ? 'w-4 h-4 sm:w-5 sm:h-5' : 'w-5 h-5 lg:w-6 lg:h-6 xl:w-6 xl:h-6';

 ob_start();
 ?>
 <a href="<?php echo htmlspecialchars($url); ?>"
 data-nav-id="<?php echo htmlspecialchars($itemId); ?>"
 <?php if ($isMobile): ?>onclick="if(document.getElementById('mobile-nav-overlay')) { document.getElementById('mobile-nav-overlay').classList.add('hidden'); }"<?php endif; ?>
 class="w-full flex items-center <?php echo $mobileClasses; ?> font-black transition-all relative
 <?php echo $isActive ? 'bg-slate-900 text-white shadow-2xl scale-[1.02]' : 'text-slate-500 hover:bg-slate-50'; ?>">
 <?php
 $itemIcon = $item['icon'] ?? '';
 if (empty($itemIcon) || $itemIcon === 'null' || $itemIcon === 'NULL') {
 $itemIcon = '';
 }
 echo getIcon($itemIcon ?: 'Circle', $iconSize . ' ' . ($isActive ? 'text-orange-500' : 'text-slate-400'));
 ?>
 <?php
 $labelKey = 'navigation.' . strtolower($itemId);
 $translatedLabel = t($labelKey, $item['label'] ?? '');
 ?>
 <span class="<?php echo $isMobile ? 'truncate' : ''; ?>"><?php echo htmlspecialchars($translatedLabel); ?></span>
 </a>
 <?php
 return ob_get_clean();
 }
}

if (!function_exists('renderNavigationMenu')) {
 /**
 * Render navigation menu for both desktop and mobile
 * Centralizes the rendering logic to avoid duplicates and
 * ensures desktop and mobile see EXACTLY the same items.
 *
 * @param string $mode 'desktop' or 'mobile'
 * @param array $standaloneItems Items to render as standalone links
 * @param array $groupedItems Items to render as dropdowns (keyed by group)
 * @param callable $checkActive Function to check if item is active
 * @param callable $checkGroupActive Function to check if group is active
 * @param array $roleFlags ['isSuperAdminRole' => bool, 'isBusinessManagerRole' => bool, 'hasActiveSubscription' => bool]
 * @return string Rendered HTML
 */
 function renderNavigationMenu($mode, $standaloneItems, $groupedItems, $checkActive, $checkGroupActive, $roleFlags = []) {

 $isMobile = ($mode === 'mobile');
 $isSuperAdminRole = $roleFlags['isSuperAdminRole'] ?? false;
 $isBusinessManagerRole = $roleFlags['isBusinessManagerRole'] ?? false;
 $hasActiveSubscription = $roleFlags['hasActiveSubscription'] ?? true;

 // Determine which standalone items are allowed
 $allowedStandalone = $isSuperAdminRole
 ? ['SUPER_ADMIN_DASHBOARD']
 : ['DASHBOARD'];

 // CRITICAL: Parent dropdown IDs are NEVER standalone items.
 // They are rendered as dropdown headers below.
 $parentDropdownIds = [
 'SCREENS', 'OPERATIONS', 'FINANCE', 'FINANCE_MAIN',
 'ANALYTICS', 'HR', 'SETTINGS', 'SAAS_MANAGEMENT'
 ];

 // Filter standalone items
 $filteredStandaloneItems = [];
 foreach ($standaloneItems as $item) {
 $itemId = $item['id'] ?? '';
 if (empty($itemId)) continue;
 if (!in_array($itemId, $allowedStandalone, true)) continue;
 if (in_array($itemId, $parentDropdownIds, true)) continue;
 $filteredStandaloneItems[] = $item;
 }

 $output = '';

 // Subscription CTA (no active subscription)
 if ($isBusinessManagerRole && !$isSuperAdminRole && !$hasActiveSubscription) {
 if ($isMobile) {
 $output .= '<a href="' . htmlspecialchars(BASE_URL . '/customer/packages/list') . '" '
 . "onclick=\"if(document.getElementById('mobile-nav-overlay')) { document.getElementById('mobile-nav-overlay').classList.add('hidden'); }\" "
 . 'class="w-full flex items-center gap-3 sm:gap-4 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-black text-xs sm:text-sm transition-all text-indigo-600 hover:bg-indigo-50 border-2 border-indigo-200">'
 . getIcon('ShoppingCart', 'w-4 h-4 sm:w-5 sm:h-5 text-indigo-500')
 . '<span class="truncate">Paket Satın Al</span></a>';
 } else {
 $output .= '<a href="' . htmlspecialchars(BASE_URL . '/customer/packages/list') . '" '
 . 'class="w-full flex items-center gap-2.5 lg:gap-3 xl:gap-3.5 px-4 lg:px-5 xl:px-6 py-3 lg:py-3.5 xl:py-4 rounded-xl lg:rounded-2xl xl:rounded-[24px] font-black text-sm lg:text-base xl:text-lg transition-all text-indigo-600 hover:bg-indigo-50 border-2 border-indigo-200">'
 . getIcon('ShoppingCart', 'w-5 h-5 lg:w-6 lg:h-6 xl:w-6 xl:h-6 text-indigo-500')
 . 'Paket Satın Al</a>';
 }
 return $output; // No other items when no subscription
 }

 // Render standalone items using the same logic for both modes
 foreach ($filteredStandaloneItems as $item) {
 $itemId = $item['id'] ?? '';
 $itemUrl = $item['url'] ?? '';
 $isActive = $checkActive($itemUrl, $itemId);

 $itemIcon = $item['icon'] ?? '';
 if (empty($itemIcon) || $itemIcon === 'null' || $itemIcon === 'NULL') {
 $itemIcon = '';
 }

  $labelKey = 'navigation.' . strtolower($itemId);
 $translatedLabel = t($labelKey, $item['label'] ?? '');
 // Turkce fallback for standalone items (e.g. DASHBOARD -> Ozet)
 $linkFallbacks = [
 'dashboard' => 'Ozet',
 'super_admin_dashboard' => 'Super Admin Ozeti',
 ];
 if (isset($linkFallbacks[strtolower($itemId)]) && $translatedLabel === ($item['label'] ?? '')) {
 $translatedLabel = $linkFallbacks[strtolower($itemId)];
 }

 if ($isMobile) {
 $iconSize = 'w-4 h-4 sm:w-5 sm:h-5';
 $classes = 'w-full flex items-center gap-3 sm:gap-4 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-black text-xs sm:text-sm transition-all';
 $iconColor = $isActive ? 'text-orange-500' : 'text-slate-400';
 $activeClass = $isActive ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-500';

 $output .= '<a href="' . htmlspecialchars(BASE_URL . $itemUrl) . '" '
 . "onclick=\"if(document.getElementById('mobile-nav-overlay')) { document.getElementById('mobile-nav-overlay').classList.add('hidden'); }\" "
 . 'class="' . $classes . ' ' . $activeClass . '">'
 . getIcon($itemIcon ?: 'Circle', $iconSize . ' ' . $iconColor)
 . '<span class="truncate">' . htmlspecialchars($translatedLabel) . '</span></a>';
 } else {
 $iconSize = 'w-5 h-5 lg:w-6 lg:h-6 xl:w-6 xl:h-6';
 $classes = 'w-full flex items-center gap-2.5 lg:gap-3 xl:gap-3.5 px-4 lg:px-5 xl:px-6 py-3 lg:py-3.5 xl:py-4 rounded-xl lg:rounded-2xl xl:rounded-[24px] font-black text-sm lg:text-base xl:text-lg transition-all';
 $iconColor = $isActive ? 'text-orange-500' : 'text-slate-400';
 $activeClass = $isActive ? 'bg-slate-900 text-white shadow-2xl scale-[1.02]' : 'text-slate-500 hover:bg-slate-50';

 $output .= '<a href="' . htmlspecialchars(BASE_URL . $itemUrl) . '" '
 . 'class="' . $classes . ' ' . $activeClass . '">'
 . getIcon($itemIcon ?: 'Circle', $iconSize . ' ' . $iconColor)
 . htmlspecialchars($translatedLabel) . '</a>';
 }
 }

 // Render dropdown groups using a single unified renderer.
 // The dropdown groups and their items are EXACTLY the same
 // for desktop and mobile - just the visual style differs.
 $dropdownGroups = [
 'screens' => ['label' => 'navigation.screens', 'icon' => 'Monitor', 'items' => $groupedItems['screens'] ?? []],
 'operations' => ['label' => 'navigation.operations', 'icon' => 'Settings', 'items' => $groupedItems['operations'] ?? []],
 'finance' => ['label' => 'navigation.finance', 'icon' => 'Wallet', 'items' => $groupedItems['finance'] ?? []],
 'analytics' => ['label' => 'navigation.analytics', 'icon' => 'BarChart', 'items' => $groupedItems['analytics'] ?? []],
 'hr' => ['label' => 'navigation.hr', 'icon' => 'Users', 'items' => $groupedItems['hr'] ?? []],
 'settings' => ['label' => 'navigation.settings', 'icon' => 'SettingsSliders','items' => $groupedItems['settings'] ?? []],
 ];

 foreach ($dropdownGroups as $groupKey => $groupData) {
 if (empty($groupData['items'])) continue;

 $output .= renderNavigationDropdown(
  $groupData['items'],
 $groupKey,
 $groupData['label'],
 $groupData['icon'],
 $checkActive,
 $checkGroupActive,
 [
 'isMobile' => $isMobile,
 'sortOrder' => _getGroupSortOrder($groupKey),
 'iconFallback' => _getGroupIconFallback($groupKey),
 ]
 );
 }

 // Render superadmin-only items (if applicable)
 if ($isSuperAdminRole && !empty($groupedItems['superadmin'])) {
 foreach ($groupedItems['superadmin'] as $superAdminItem) {
 $itemId = $superAdminItem['id'] ?? '';
 $children = $superAdminItem['children'] ?? [];
 if ($itemId === 'SAAS_MANAGEMENT' && !empty($children)) {
 $output .= renderNavigationDropdown(
 $children,
 strtolower($itemId),
 $superAdminItem['label'] ?? '',
 $superAdminItem['icon'] ?? 'Settings',
 $checkActive,
 $checkGroupActive,
 [
 'isMobile' => $isMobile,
 'iconFallback' => ['BANK_TRANSFERS' => 'Wallet', 'BANK_ACCOUNTS' => 'Building'],
 ]
 );
 }
 }
 }

 return $output;
 }
}

if (!function_exists('_getGroupSortOrder')) {
 /**
 * Get sort order for a navigation group
 */
 function _getGroupSortOrder($groupKey) {
 $orders = [
 'screens' => ['POS' => 1, 'WAITER' => 2, 'KITCHEN' => 3, 'PREPARATION_SCREENS' => 4],
 'operations' => ['CATEGORIES' => 1, 'MENU' => 2, 'MENU_ITEMS' => 2, 'TABLES' => 3, 'ORDERS' => 4, 'RESERVATIONS' => 5, 'ORDER_APPROVALS' => 6, 'ORDER_APPROVAL_HISTORY' => 7],
 'settings' => ['STAFF' => 1, 'BUSINESS_SETTINGS' => 2, 'SETTINGS' => 3, 'PRINTERS' => 4, 'ROLES' => 5, 'SYSTEM_SETTINGS' => 6, 'PAYMENT_GATEWAYS' => 7, 'PAYMENT_GATEWAYS_BUSINESS' => 7, 'TRIAL_MANAGEMENT' => 8, 'PAYMENT_LINKS' => 9, 'LEGAL_PAGES' => 10],
 'analytics' => ['ERROR_LOGS' => 1, 'SYSTEM_LOGS' => 2, 'ANALYTICS' => 3, 'GENERAL_ANALYTICS' => 3, 'REPORTS' => 4, 'PRODUCT_SALES' => 5, 'ERROR_ANALYTICS' => 6],
 'hr' => ['HR_SHIFTS' => 1, 'HR_LEAVES' => 2, 'HR_GUEST_STAFF' => 3],
 'finance' => ['FINANCE_INVOICES' => 1, 'FINANCE_EXPENSES' => 2, 'FINANCE_INVENTORY' => 3, 'FINANCE_PURCHASES' => 4, 'FINANCE_STOCK_CATEGORIES' => 5, 'FINANCE_LOW_STOCK' => 6, 'FINANCE_SUPPLIERS' => 7, 'FINANCE_SUPPLIER_PERFORMANCE' => 8, 'FINANCE_WASTE' => 9],
 ];
 return $orders[$groupKey] ?? [];
 }
}

if (!function_exists('_getGroupIconFallback')) {
 /**
 * Get icon fallback for a navigation group
 */
 function _getGroupIconFallback($groupKey) {
 $fallbacks = [
 'operations' => ['MENU_ITEMS' => 'FileText', 'MENU' => 'FileText', 'CATEGORIES' => 'Folder', 'ORDER_APPROVALS' => 'CheckCircle', 'ORDER_APPROVAL_HISTORY' => 'Clock'],
 'hr' => ['HR_SHIFTS' => 'Calendar', 'HR_LEAVES' => 'CheckCircle', 'HR_GUEST_STAFF' => 'User'],
 'settings' => ['BUSINESS_SETTINGS' => 'Settings', 'FEATURE_FLAGS' => 'ToggleRight', 'SETTINGS' => 'Settings', 'PRINTERS' => 'Printer', 'STAFF' => 'Users', 'ROLES' => 'Shield'],
 ];
 return $fallbacks[$groupKey] ?? [];
 }
}

if (!function_exists('_resolveBusinessPanelNavIcon')) {
 /**
 * Resolve icon for compact business panel nav link (DB field → group fallback → infer).
 */
 function _resolveBusinessPanelNavIcon(array $item): string {
 $id = strtoupper($item['id'] ?? '');
 $icon = $item['icon'] ?? '';
 if ($icon === 'null' || $icon === 'NULL') {
 $icon = '';
 }
 if (!empty($icon)) {
 return $icon;
 }

 $fallbackMaps = [
 _getGroupIconFallback('operations'),
 _getGroupIconFallback('hr'),
 _getGroupIconFallback('settings'),
 ];
 foreach ($fallbackMaps as $map) {
 if (!empty($map[$id])) {
 return $map[$id];
 }
 }

 $inferred = [
 'DASHBOARD' => 'LayoutDashboard',
 'SUPER_ADMIN_DASHBOARD' => 'LayoutDashboard',
 'POS' => 'CreditCard',
 'WAITER' => 'User',
 'KITCHEN' => 'ChefHat',
 'PREPARATION_SCREENS' => 'Monitor',
 'ORDERS' => 'ShoppingCart',
 'TABLES' => 'Grid',
 'MENU_ITEMS' => 'FileText',
 'MENU' => 'FileText',
 'CATEGORIES' => 'Folder',
 'RESERVATIONS' => 'Calendar',
 'QUEUE' => 'Clock',
 'ORDER_APPROVALS' => 'CheckCircle',
 'ORDER_APPROVAL_HISTORY' => 'History',
 'RECEIPTS' => 'Receipt',
 'FINANCE_OVERVIEW' => 'Wallet',
 'FINANCE_INCOME' => 'TrendingUp',
 'FINANCE_EXPENSES' => 'TrendingDown',
 'FINANCE_INVENTORY' => 'Package',
 'FINANCE_STOCK_CATEGORIES' => 'FolderTree',
 'FINANCE_PURCHASES' => 'Truck',
 'FINANCE_SUPPLIERS' => 'Truck',
 'FINANCE_WASTE' => 'Trash2',
 'FINANCE_SUPPLIER_PERFORMANCE' => 'BarChart',
 'STAFF' => 'Users',
 'HR_SHIFTS' => 'CalendarClock',
 'HR_LEAVES' => 'CalendarCheck',
 'HR_GUEST_STAFF' => 'UserPlus',
 'GENERAL_ANALYTICS' => 'BarChart',
 'AI_SUGGESTIONS' => 'Sparkles',
 'PRODUCT_SALES' => 'TrendingUp',
 'REPORTS' => 'FileText',
 'BUSINESS_SETTINGS' => 'Store',
 'PRINTERS' => 'Printer',
 'PAYMENT_GATEWAYS_BUSINESS' => 'CreditCard',
 'FEATURE_FLAGS' => 'ToggleRight',
 'SETTINGS' => 'Settings',
 'ROLES' => 'Shield',
 'BANK_TRANSFERS' => 'Wallet',
 'BANK_ACCOUNTS' => 'Building',
 'SAAS_MANAGEMENT' => 'Building2',
 ];
 return $inferred[$id] ?? 'Circle';
 }
}

if (!function_exists('_businessPanelSectionHasActive')) {
 /**
 * @param array<int, array> $items
 */
 function _businessPanelSectionHasActive(array $items, callable $checkActive): bool {
 foreach ($items as $item) {
 $id = $item['id'] ?? '';
 $url = $item['url'] ?? '';
 if ($url && strpos($url, '/') !== 0) {
 $url = '/' . $url;
 }
 if ($checkActive($url, $id)) {
 return true;
 }
 }
 return false;
 }
}

if (!function_exists('buildBusinessPanelSections')) {
 /**
 * Build accordion sidebar sections for business/qodmin panel surfaces.
 *
 * @param array<string, mixed> $roleFlags isSuperAdminRole, isManagerRole, isBusinessManagerRole
 * @return array<int, array{label?: string|null, key?: string, icon?: string, items: array<int, array>}>
 */
 function buildBusinessPanelSections(array $standaloneItems, array $groupedItems, array $roleFlags = []): array {
 $isSuperAdminRole = !empty($roleFlags['isSuperAdminRole']);
 $isManagerRole = !empty($roleFlags['isManagerRole']);
 $isBusinessManagerRole = !empty($roleFlags['isBusinessManagerRole']);

 $flattenItems = function (array $items, array $excludeIds = []) use (&$flattenItems): array {
 $out = [];
 foreach ($items as $item) {
 $id = $item['id'] ?? '';
 if (in_array($id, $excludeIds, true)) {
 continue;
 }
 if (!empty($item['children'])) {
 foreach ($item['children'] as $ch) {
 if (!in_array($ch['id'] ?? '', $excludeIds, true)) {
 $out[] = $ch;
 }
 }
 } else {
 $out[] = $item;
 }
 }
 return $out;
 };

 $screensFlat = $flattenItems($groupedItems['screens'] ?? [], ['SCREENS']);
 $operationsFlat = $flattenItems($groupedItems['operations'] ?? [], ['OPERATIONS']);
 $financeFlat = $flattenItems($groupedItems['finance'] ?? [], ['FINANCE']);
 $hrFlat = array_merge(
 $flattenItems($groupedItems['hr'] ?? [], ['HR']),
 array_filter(
 $flattenItems($groupedItems['settings'] ?? [], ['SETTINGS']),
 fn($i) => in_array($i['id'] ?? '', ['STAFF', 'ROLES'], true)
 )
 );
 $analyticsFlat = $flattenItems($groupedItems['analytics'] ?? [], ['ANALYTICS']);
 $hasAiSuggestionsNav = false;
 foreach ($analyticsFlat as $_aiNavProbe) {
 if (($_aiNavProbe['id'] ?? '') === 'AI_SUGGESTIONS') {
 $hasAiSuggestionsNav = true;
 break;
 }
 }
 if (!$hasAiSuggestionsNav && function_exists('hasPermissionForRole') && hasPermissionForRole('dashboard.analytics')) {
 $analyticsFlat[] = [
 'id' => 'AI_SUGGESTIONS',
 'nav_key' => 'AI_SUGGESTIONS',
 'label_tr' => 'AI Önerileri',
 'label' => 'AI Önerileri',
 'icon' => 'Sparkles',
 'url' => '/business/ai-onerileri',
 'permission_key' => 'dashboard.analytics',
 'display_order' => 12,
 ];
 }
 $settingsFlat = array_values(array_filter(
 $flattenItems($groupedItems['settings'] ?? [], ['SETTINGS']),
 function ($i) use ($isSuperAdminRole, $isManagerRole, $isBusinessManagerRole) {
 $itemId = $i['id'] ?? '';
 if (in_array($itemId, ['STAFF', 'ROLES'], true)) {
 return false;
 }
 if (in_array($itemId, ['FEATURE_FLAGS', 'FEATURE_FLAGS_BUSINESS'], true)) {
 return false;
 }
 if (in_array($itemId, ['SYSTEM_SETTINGS', 'PAYMENT_GATEWAYS'], true)) {
 return (bool) $isSuperAdminRole;
 }
 if ($itemId === 'PRINTERS') {
 return $isManagerRole || $isSuperAdminRole;
 }
 if ($itemId === 'BUSINESS_SETTINGS') {
 return $isManagerRole || $isBusinessManagerRole;
 }
 if ($itemId === 'PAYMENT_GATEWAYS_BUSINESS') {
 return $isManagerRole && !$isSuperAdminRole;
 }
 return true;
 }
 ));

 $dashboardNavItems = [];
 $allowedStandaloneNav = $isSuperAdminRole ? ['SUPER_ADMIN_DASHBOARD'] : ['DASHBOARD'];
 foreach ($standaloneItems as $navItem) {
 $navId = $navItem['id'] ?? '';
 if (in_array($navId, $allowedStandaloneNav, true)) {
 $dashboardNavItems[] = $navItem;
 }
 }

 $panelSections = [];
 if (!empty($dashboardNavItems)) {
 $panelSections[] = ['items' => $dashboardNavItems];
 }
 if (!empty($screensFlat)) {
 $panelSections[] = ['label' => 'Ekranlar', 'key' => 'screens', 'icon' => 'Monitor', 'items' => $screensFlat];
 }
 if (!empty($operationsFlat)) {
 $panelSections[] = ['label' => 'Operasyon', 'key' => 'operations', 'icon' => 'Settings', 'items' => $operationsFlat];
 }
 if (!empty($financeFlat)) {
 $panelSections[] = ['label' => 'Finans', 'key' => 'finance', 'icon' => 'Wallet', 'items' => $financeFlat];
 }
 if (!empty($hrFlat)) {
 $panelSections[] = ['label' => 'Personel', 'key' => 'hr', 'icon' => 'Users', 'items' => $hrFlat];
 }
 if (!empty($analyticsFlat)) {
 $panelSections[] = ['label' => 'Raporlar', 'key' => 'analytics', 'icon' => 'BarChart', 'items' => $analyticsFlat];
 }
 if (!empty($settingsFlat)) {
 $panelSections[] = ['label' => 'Ayarlar', 'key' => 'settings', 'icon' => 'SettingsSliders', 'items' => $settingsFlat];
 }
 if ($isSuperAdminRole && !empty($groupedItems['superadmin'])) {
 foreach ($groupedItems['superadmin'] as $superAdminItem) {
 if (($superAdminItem['id'] ?? '') === 'SAAS_MANAGEMENT' && !empty($superAdminItem['children'])) {
 $panelSections[] = ['label' => 'SaaS', 'key' => 'saas', 'icon' => 'Building2', 'items' => $superAdminItem['children']];
 }
 }
 }

 return $panelSections;
 }
}

if (!function_exists('renderBusinessPanelNavLink')) {
 /**
 * Compact nav row — icon + 11px label (marketing #panel style).
 */
 function renderBusinessPanelNavLink(array $item, callable $checkActive, array $options = []): string {
 $id = $item['id'] ?? '';
 $url = $item['url'] ?? '';
 if ($url && strpos($url, '/') !== 0) {
 $url = '/' . $url;
 }
 $isActive = $checkActive($url, $id);
 $labelKey = 'navigation.' . strtolower($id);
 $label = function_exists('t') ? t($labelKey, $item['label'] ?? $id) : ($item['label'] ?? $id);
 $shortLabels = [
 'DASHBOARD' => 'Ana Sayfa',
 'SUPER_ADMIN_DASHBOARD' => 'Ana Sayfa',
 'PREPARATION_SCREENS' => 'Hazırlık',
 'GENERAL_ANALYTICS' => 'Analiz',
 'PRODUCT_SALES' => 'Ürün Satış',
 'ORDER_APPROVAL_HISTORY' => 'Onay Geçmişi',
 'FINANCE_SUPPLIER_PERFORMANCE' => 'Tedarikçi Perf.',
 'PAYMENT_GATEWAYS_BUSINESS' => 'Ödeme',
 'BUSINESS_SETTINGS' => 'İşletme',
 ];
 if (isset($shortLabels[$id])) {
 $label = $shortLabels[$id];
 }
 $activeClass = $isActive ? ' is-active' : '';
 $isMobile = !empty($options['isMobile']);
 $mobileClose = $isMobile
 ? ' onclick="if(document.getElementById(\'mobile-nav-overlay\')) { document.getElementById(\'mobile-nav-overlay\').classList.add(\'hidden\'); }"'
 : '';
 $itemIcon = _resolveBusinessPanelNavIcon($item);
 $iconHtml = '';
 if (function_exists('getIcon')) {
  $iconInner = getIcon($itemIcon, 'q-panel-nav-icon');
 if ($iconInner !== '') {
 $iconHtml = $iconInner;
 }
 }
 return '<a href="' . htmlspecialchars(BASE_URL . $url, ENT_QUOTES, 'UTF-8') . '"'
 . $mobileClose
 . ' class="q-panel-nav-link' . $activeClass . '" data-nav-id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
 . $iconHtml
 . '<span class="q-panel-nav-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
 . '</a>';
 }
}

if (!function_exists('renderBusinessPanelSidebarAsideClass')) {
 /**
 * Shared aside classes for desktop sidebar and mobile drawer.
 */
 function renderBusinessPanelSidebarAsideClass(bool $isDrawer = false): string {
 if ($isDrawer) {
 return 'q-biz-sidebar q-biz-sidebar--drawer relative w-72 sm:w-80 h-full flex flex-col animate-slide-right shadow-2xl safe-area-left';
 }
 return 'q-biz-sidebar hidden lg:flex flex-col shrink-0 h-full border-r border-slate-200 bg-white';
 }
}

if (!function_exists('renderBusinessPanelSidebarHeader')) {
 /**
 * Mobile drawer header — Qordy home link (desktop uses top bar instead).
 */
 function renderBusinessPanelSidebarHeader(string $homeUrl, string $logoUrl): string {
 $homeEsc = htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8');
 $logoEsc = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
 return '<div class="q-biz-sidebar__header shrink-0">'
 . '<a href="' . $homeEsc . '" class="q-panel-home" aria-label="Qordy — Kontrol paneli">'
 . '<img src="' . $logoEsc . '" alt="Qordy" class="q-panel-home__logo" decoding="async">'
 . '</a></div>';
 }
}

if (!function_exists('renderBusinessPanelSidebarNavOpenTag')) {
 /**
 * Opening <nav> tag shared by #main-navigation and #mobile-navigation.
 */
 function renderBusinessPanelSidebarNavOpenTag(string $navId, bool $isMobile = false): string {
 $menuLabel = function_exists('t') ? t('common.menu', 'Menü') : 'Menü';
 $menuEsc = htmlspecialchars($menuLabel, ENT_QUOTES, 'UTF-8');
 $navEsc = htmlspecialchars($navId, ENT_QUOTES, 'UTF-8');
 $loaded = ($navId === 'main-navigation') ? ' data-navigation-rendering="true"' : '';
 return '<nav class="q-biz-sidebar__nav min-h-0 overflow-y-auto overflow-x-hidden"'
 . ' id="' . $navEsc . '"'
 . ' data-navigation-loaded="false"' . $loaded
 . ' aria-label="' . $menuEsc . '">';
 }
}

if (!function_exists('renderBusinessPanelSubscriptionCta')) {
 /**
 * No-subscription CTA link — same markup for desktop sidebar and mobile drawer.
 */
 function renderBusinessPanelSubscriptionCta(bool $isMobile = false): string {
 $close = $isMobile
 ? ' onclick="if(document.getElementById(\'mobile-nav-overlay\')) { document.getElementById(\'mobile-nav-overlay\').classList.add(\'hidden\'); }"'
 : '';
 $icon = function_exists('getIcon') ? getIcon('ShoppingCart', 'q-panel-nav-icon') : '';
 return '<a href="' . htmlspecialchars(BASE_URL . '/customer/packages/list', ENT_QUOTES, 'UTF-8') . '"'
 . $close
 . ' class="q-panel-nav-link q-panel-nav-link--cta">'
 . $icon
 . '<span class="q-panel-nav-label">Paket Satın Al</span></a>';
 }
}

if (!function_exists('renderBusinessPanelNavContent')) {
 /**
 * Panel nav body — subscription gate or accordion sections (desktop + mobile).
 *
 * @param array<int, array<string, mixed>> $panelSections
 * @param array<string, mixed> $roleFlags isSuperAdminRole, isBusinessManagerRole, hasActiveSubscription
 */
 function renderBusinessPanelNavContent(
 array $panelSections,
 callable $checkActive,
 array $roleFlags = [],
 array $options = []
 ): string {
 $isMobile = !empty($options['isMobile']);
 $isSuperAdminRole = !empty($roleFlags['isSuperAdminRole']);
 $isBusinessManagerRole = !empty($roleFlags['isBusinessManagerRole']);
 $hasActiveSubscription = $roleFlags['hasActiveSubscription'] ?? true;

 if ($isBusinessManagerRole && !$isSuperAdminRole && !$hasActiveSubscription) {
 return renderBusinessPanelSubscriptionCta($isMobile);
 }

 return renderBusinessPanelNav($panelSections, $checkActive, $options);
 }
}

if (!function_exists('renderBusinessPanelSidebarFooter')) {
 /**
 * Pofuduk partner footer — shared by desktop sidebar and mobile drawer.
 */
 function renderBusinessPanelSidebarFooter(): string {
 return '<div class="q-biz-sidebar__footer shrink-0">'
 . '<a href="https://pofudukdijital.com/" target="_blank" rel="noopener noreferrer" class="q-biz-sidebar__partner-logo">'
 . '<img src="https://pofudukdijital.com/wp-content/uploads/2023/11/logo1.svg" alt="Pofuduk Dijital" loading="lazy" />'
 . '</a></div>';
 }
}

if (!function_exists('renderBusinessPanelSidebarActions')) {
 /**
 * Language selector + logout for panel sidebar mobile drawer.
 */
 function renderBusinessPanelSidebarActions($translationService): string {
 if (!$translationService || !method_exists($translationService, 'getAvailableLanguages')) {
 return '';
 }

 $availableLanguages = $translationService->getAvailableLanguages();
 $currentLang = method_exists($translationService, 'getCurrentLanguage')
 ? $translationService->getCurrentLanguage()
 : 'tr';
 $logoutLabel = function_exists('t') ? t('common.logout', 'Çıkış Yap') : 'Çıkış Yap';
 $logoutUrl = htmlspecialchars(BASE_URL . '/logout', ENT_QUOTES, 'UTF-8');

 $langHtml = '';
 if (is_array($availableLanguages) && count($availableLanguages) > 1) {
 $langItems = '';
 foreach ($availableLanguages as $langCode => $langInfo) {
 $isCurrent = ($currentLang === $langCode);
 $cls = $isCurrent ? 'q-panel-lang__btn is-active' : 'q-panel-lang__btn';
 $flag = htmlspecialchars((string) ($langInfo['flag'] ?? ''), ENT_QUOTES, 'UTF-8');
 $name = htmlspecialchars((string) ($langInfo['name'] ?? strtoupper((string) $langCode)), ENT_QUOTES, 'UTF-8');
 $langItems .= '<button type="button" onclick="changeLanguage(\'' . htmlspecialchars((string) $langCode, ENT_QUOTES, 'UTF-8') . '\')" class="' . $cls . '">' . $flag . ' ' . $name . '</button>';
 }
 $langHtml = '<div class="q-panel-lang q-biz-sidebar__lang" role="group" aria-label="Dil seçimi">' . $langItems . '</div>';
 }

 return '<div class="q-biz-sidebar__actions shrink-0">'
 . $langHtml
 . '<a href="' . $logoutUrl . '" class="q-biz-sidebar__logout q-panel-icon-btn q-panel-icon-btn--danger" aria-label="' . htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8') . '">'
 . '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>'
 . '<span class="q-biz-sidebar__logout-label">' . htmlspecialchars($logoutLabel, ENT_QUOTES, 'UTF-8') . '</span>'
 . '</a></div>';
 }
}

if (!function_exists('renderBusinessPanelNav')) {
 /**
 * Accordion panel sidebar — collapsible sections + compact icon links.
 *
 * @param array<int, array{label?: string|null, key?: string, icon?: string, items: array<int, array>}> $sections
 */
 function renderBusinessPanelNav(array $sections, callable $checkActive, array $options = []): string {
 $html = '';
 foreach ($sections as $section) {
 $items = $section['items'] ?? [];
 if (empty($items)) {
 continue;
 }
 $sectionLabel = $section['label'] ?? null;
 if (!$sectionLabel) {
 foreach ($items as $item) {
 $html .= renderBusinessPanelNavLink($item, $checkActive, $options);
 }
 continue;
 }

        $sectionKey = $section['key'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $sectionLabel));
        $hasActive = _businessPanelSectionHasActive($items, $checkActive);
        $openClass = $hasActive ? ' is-open' : '';
        $expanded = $hasActive ? 'true' : 'false';
        $sectionIcon = $section['icon'] ?? '';

        $html .= '<div class="q-panel-nav-group' . $openClass . '"'
            . ' data-section-id="' . htmlspecialchars($sectionKey, ENT_QUOTES, 'UTF-8') . '"'
            . ($hasActive ? ' data-has-active="1"' : '')
            . '>';
        $html .= '<button type="button" class="q-panel-nav-group__toggle"'
            . ' aria-expanded="' . $expanded . '"'
            . ' aria-controls="q-panel-nav-group-' . htmlspecialchars($sectionKey, ENT_QUOTES, 'UTF-8') . '">';
        $iconHtml = '';
        if ($sectionIcon && function_exists('getIcon')) {
            $iconHtml = getIcon($sectionIcon, 'q-panel-nav-icon q-panel-nav-group__icon flex-shrink-0');
        }
        $html .= '<span class="q-panel-nav-group__title-wrap">'
            . $iconHtml
            . '<span class="q-panel-nav-group__label">' . htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
        $html .= '<svg class="q-panel-nav-group__chevron" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $html .= '</button>';
 $html .= '<div class="q-panel-nav-group__items" id="q-panel-nav-group-' . htmlspecialchars($sectionKey, ENT_QUOTES, 'UTF-8') . '">';
 foreach ($items as $item) {
 $html .= renderBusinessPanelNavLink($item, $checkActive, $options);
 }
 $html .= '</div></div>';
 }
 return $html;
 }
}

if (!function_exists('getDashboardRangeLabels')) {
 /**
 * Human-readable labels for dashboard date-range keys.
 *
 * @return array<string, string>
 */
 function getDashboardRangeLabels(): array {
 return [
 'today' => 'Bugün',
 'week' => 'Bu Hafta',
 'month' => 'Bu Ay',
 '3months' => 'Son 3 Ay',
 '6months' => 'Son 6 Ay',
 '9months' => 'Son 9 Ay',
 'year' => 'Bu Yıl',
 'custom' => 'Özel Aralık',
 ];
 }
}

if (!function_exists('getDashboardCardRangeKeys')) {
 /**
 * Per-card mini filter keys (subset of dashboard ranges).
 *
 * @return list<string>
 */
 function getDashboardCardRangeKeys(): array {
 return ['today', 'week', 'month', '3months'];
 }
}

if (!function_exists('renderDashboardCardRangeFilter')) {
 /**
 * Compact pill row for per-widget date range inside a panel card header.
 */
 function renderDashboardCardRangeFilter(string $widgetId, string $active = 'today'): string {
 $labels = getDashboardRangeLabels();
 $keys = getDashboardCardRangeKeys();
 $activeInKeys = ($active !== '' && in_array($active, $keys, true));
 $html = '<div class="q-panel-card__ranges" data-card-range-filter data-widget="'
 . htmlspecialchars($widgetId, ENT_QUOTES, 'UTF-8')
 . '" role="group" aria-label="Tarih aralığı">';
 foreach ($keys as $key) {
 $isActive = $activeInKeys && ($key === $active);
 $html .= sprintf(
 '<button type="button" class="q-panel-card__range%s" data-range="%s" aria-pressed="%s">%s</button>',
 $isActive ? ' q-panel-card__range--active' : '',
 htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
 $isActive ? 'true' : 'false',
 htmlspecialchars($labels[$key] ?? $key, ENT_QUOTES, 'UTF-8')
 );
 }
 $html .= '</div>';
 return $html;
 }
}

if (!function_exists('resolveDashboardRangeKey')) {
 /**
 * Resolve active dashboard range from path, query, or session.
 */
 function resolveDashboardRangeKey(string $uri): string {
 $allowed = array_keys(getDashboardRangeLabels());
 if (preg_match('~^/business/dashboard/([^/?#]+)~', $uri, $m)) {
 $fromPath = $m[1];
 if (in_array($fromPath, $allowed, true)) {
 return $fromPath;
 }
 }
 $fromGet = $_GET['range'] ?? null;
 if ($fromGet !== null && in_array($fromGet, $allowed, true)) {
 return $fromGet;
 }
 $fromSession = $_SESSION['dashboard_range'] ?? 'today';
 return in_array($fromSession, $allowed, true) ? $fromSession : 'today';
 }
}

if (!function_exists('resolveBusinessPanelLogoUrl')) {
 /**
 * Resolve absolute business logo URL from customers row (logo_url, then logo_path).
 */
 function resolveBusinessPanelLogoUrl(?array $customer): string {
 if (!$customer) {
 return '';
 }

 $url = trim((string) ($customer['logo_url'] ?? ''));
 if ($url !== '') {
 if (preg_match('#^https?://#i', $url)) {
 return $url;
 }
 return rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
 }

 $path = trim((string) ($customer['logo_path'] ?? ''));
 if ($path === '') {
 return '';
 }
 if (preg_match('#^https?://#i', $path)) {
 return $path;
 }

 return BASE_URL . $path;
 }
}

if (!function_exists('businessPanelAvatarInitials')) {
 /**
 * Derive up to two initials from a business display name.
 */
 function businessPanelAvatarInitials(string $name): string {
 $parts = preg_split('/\s+/u', trim($name));
 $initials = '';
 if (is_array($parts)) {
 foreach ($parts as $part) {
 if ($part === '') {
 continue;
 }
 $initials .= mb_strtoupper(mb_substr($part, 0, 1));
 if (mb_strlen($initials) >= 2) {
 break;
 }
 }
 }
 return $initials !== '' ? $initials : 'Q';
 }
}

require_once __DIR__ . '/brand.php';

if (!function_exists('renderBusinessPanelTopbar')) {
 /**
 * Marketing #panel top bar for unified business shell.
 */
 function renderBusinessPanelTopbar(string $context, string $businessName, string $rangeLabel = '', array $opts = []): string {
 $contextEsc = htmlspecialchars($context, ENT_QUOTES, 'UTF-8');
 $bizEsc = htmlspecialchars($businessName, ENT_QUOTES, 'UTF-8');
 $rangeEsc = htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8');
 $businessNumber = trim((string) ($opts['businessNumber'] ?? ''));
 $businessNumberEsc = htmlspecialchars($businessNumber, ENT_QUOTES, 'UTF-8');
 $homeUrl = htmlspecialchars($opts['homeUrl'] ?? (BASE_URL . '/business/dashboard'), ENT_QUOTES, 'UTF-8');
 $brandLogoUrl = trim((string) ($opts['brandLogoUrl'] ?? ''));
 if ($brandLogoUrl === '') {
 $brandLogoUrl = resolveQordyCorporateLogoUrl();
 }
 $brandLogoUrlEsc = htmlspecialchars($brandLogoUrl, ENT_QUOTES, 'UTF-8');
 $profileUrl = htmlspecialchars($opts['profileUrl'] ?? (BASE_URL . '/business/profile'), ENT_QUOTES, 'UTF-8');
 $logoutUrl = htmlspecialchars($opts['logoutUrl'] ?? (BASE_URL . '/logout'), ENT_QUOTES, 'UTF-8');
 $logoUrl = trim((string) ($opts['logoUrl'] ?? ''));
 $currentRange = htmlspecialchars((string) ($opts['currentRange'] ?? ''), ENT_QUOTES, 'UTF-8');
 $showRangeChip = !empty($opts['showRangeChip']) && $rangeLabel !== '';
 $avatarInitials = businessPanelAvatarInitials($businessName);
 $initialsEsc = htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8');

 if ($logoUrl !== '') {
 $logoUrlEsc = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
 $avatarClass = 'q-panel-avatar q-panel-avatar--logo';
 $avatarInner = '<img src="' . $logoUrlEsc . '" alt="' . $bizEsc . '" class="q-panel-avatar__img" loading="lazy" decoding="async"'
 . ' onerror="this.style.display=\'none\';var s=this.nextElementSibling;if(s)s.hidden=false;">'
 . '<span class="q-panel-avatar__initials" hidden aria-hidden="true">' . $initialsEsc . '</span>';
 } else {
 $avatarClass = 'q-panel-avatar q-panel-avatar--initials';
 $avatarInner = '<span class="q-panel-avatar__initials" aria-hidden="true">' . $initialsEsc . '</span>';
 }

 $rangeChip = '';
 if ($showRangeChip) {
 $rangeChip = '<button type="button" class="q-panel-chip q-panel-chip--range" data-panel-range-chip data-range="' . $currentRange . '"'
 . ' aria-label="Tarih aralığı: ' . $rangeEsc . ', canlı saat" title="Tarih aralığını değiştir">'
 . '<span class="q-panel-chip__live-dot" aria-hidden="true"></span>'
 . '<svg class="q-panel-chip__icon" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
 . '<span class="q-panel-chip__datetime" data-live-datetime aria-hidden="true"></span>'
 . '</button>';
 }

 // Dil seçici (opsiyonel, default null → render edilmez)
 $langSelector = '';
 if (!empty($opts['langCurrent']) && !empty($opts['langOptions'])) {
 $langItems = '';
 foreach ($opts['langOptions'] as $code => $label) {
 $isCurrent = $code === $opts['langCurrent'];
 $cls = $isCurrent ? 'q-panel-lang__btn is-active' : 'q-panel-lang__btn';
 $langItems .= '<button type="button" data-lang="' . htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '">' . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8') . '</button>';
 }
 $langSelector = '<div class="q-panel-lang" role="group" aria-label="Dil seçimi">' . $langItems . '</div>';
 }
 $menuLabel = function_exists('t') ? t('common.menu', 'Menü') : 'Menü';
 $menuLabelEsc = htmlspecialchars($menuLabel, ENT_QUOTES, 'UTF-8');

 $businessCodeChip = '';
 if ($businessNumber !== '' && $businessNumber !== '000000') {
 $businessCodeChip = '<span class="q-panel-chip q-panel-chip--code" title="Personel uygulaması giriş kodu — bu kod personeliniz mobil girişinde kullanılır"><span class="q-panel-chip__code-label">Kod</span><span class="q-panel-chip__code-sep" aria-hidden="true">:</span><span class="q-panel-chip__code-value">' . $businessNumberEsc . '</span></span>';
 }

 // Standart Görünüm & Tema Ekranı butonları (topbar'ın sağ tarafı)
 $topbarUtilityButtons = <<<HTMLUTIL
<button type="button" class="q-panel-icon-btn q-panel-icon-btn--utility" data-topbar-action="reset-layout" aria-label="Standart Görünüm" title="Standart Görünüm — paneli varsayılan düzenine sıfırla">
 <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
</button>
<button type="button" class="q-panel-icon-btn q-panel-icon-btn--utility q-panel-theme-toggle" data-topbar-action="toggle-theme" aria-label="Tema Ekranı" title="Tema Ekranı — karanlık / aydınlık mod" aria-pressed="false">
 <svg class="q-panel-icon-sun" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path></svg>
 <svg class="q-panel-icon-moon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
</button>
HTMLUTIL;

 return <<<HTML
<div class="q-biz-topbar q-panel-topbar" role="banner">
  <button type="button" class="q-biz-topbar__menu-btn q-panel-icon-btn lg:hidden" id="mobile-nav-toggle-button" data-mobile-nav-toggle="true" aria-label="{$menuLabelEsc}" title="{$menuLabelEsc}" aria-expanded="false" aria-controls="mobile-nav-overlay">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
  </button>
  <a href="{$homeUrl}" class="q-panel-home" aria-label="Qordy — Kontrol paneli">
    <img src="{$brandLogoUrlEsc}" alt="Qordy" class="q-panel-home__logo" decoding="async">
  </a>
  <span class="q-panel-context">{$contextEsc}</span>
  <div class="q-panel-topbar__actions">
    {$rangeChip}
    {$businessCodeChip}
    <span class="q-panel-chip q-panel-chip--business" title="İşletme">{$bizEsc}</span>
    {$langSelector}
    <a href="{$profileUrl}" class="{$avatarClass}" aria-label="İşletme profili" title="İşletme profili">{$avatarInner}</a>
    <a href="{$logoutUrl}" class="q-panel-icon-btn q-panel-icon-btn--danger" aria-label="Çıkış Yap" title="Çıkış Yap">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
    </a>
  </div>
</div>
HTML;
 }
}

if (!function_exists('renderStaffBusinessCodeBar')) {
  /**
   * Personel (garson/mutfak/kasa) ekranları için ince işletme kodu şeridi.
   */
  function renderStaffBusinessCodeBar(string $businessNumber): string {
    $code = trim($businessNumber);
    if ($code === '' || $code === '000000') {
      return '';
    }
    $codeEsc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<div class="q-staff-code-bar" role="status" aria-label="İşletme kodu {$codeEsc}">
  <div class="q-staff-code-bar__info">
    <span>İşletme kodu:</span>
    <span class="q-staff-code-bar__value">{$codeEsc}</span>
  </div>
  <div class="q-staff-code-bar__actions">
    <button type="button" class="q-staff-code-bar__btn" id="staffLayoutReset" title="Standart Görünüm">
      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
      <span>Standart Görünüm</span>
    </button>
    <button type="button" class="q-staff-code-bar__btn" id="staffFullscreenToggle" title="Tam Ekran">
      <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
      <span>Tam Ekran</span>
    </button>
  </div>
</div>
<script>
(function() {
  document.getElementById('staffFullscreenToggle')?.addEventListener('click', function() {
    if (typeof window.toggleFullscreen === 'function') {
      window.toggleFullscreen();
    } else {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
          console.error('Fullscreen request failed:', err);
        });
      } else {
        document.exitFullscreen();
      }
    }
  });

  document.getElementById('staffLayoutReset')?.addEventListener('click', function() {
    if (typeof window.toggleZoneView === 'function') {
      const zoneView = document.getElementById('zone-grouped-view');
      if (zoneView && !zoneView.classList.contains('hidden')) {
        window.toggleZoneView();
      }
    } else {
      const cleanUrl = window.location.origin + window.location.pathname;
      window.location.href = cleanUrl;
    }
  });
})();
</script>
HTML;
  }
}


