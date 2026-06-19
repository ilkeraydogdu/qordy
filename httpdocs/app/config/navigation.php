<?php
/**
 * Navigation Configuration
 * Loads navigation items ONLY from database (NO FALLBACK)
 */

// Load navigation items from database ONLY
$items = [];

try {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    
    // Use DependencyFactory for DI
    $navModel = new \App\Models\NavigationItem(\App\Core\DependencyFactory::getDatabase());
    
    // Load navigation items from database
    $dbItems = $navModel->getNavigationAsArray();
    
    if (!empty($dbItems) && is_array($dbItems)) {
        // Remove STOCK menu item if it exists (duplicate of INVENTORY)
        $items = array_filter($dbItems, function($item) {
            return ($item['id'] ?? '') !== 'STOCK';
        });
        
        // Remove duplicate SETTINGS items - keep only the first one
        $settingsFound = false;
        $items = array_filter($items, function($item) use (&$settingsFound) {
            if (($item['id'] ?? '') === 'SETTINGS') {
                if ($settingsFound) {
                    return false; // Skip duplicate SETTINGS
                }
                $settingsFound = true;
            }
            return true;
        });
        
        // Remove MIGRATIONS navigation item (migrations are now automatic)
        $items = array_filter($items, function($item) {
            return ($item['id'] ?? '') !== 'MIGRATIONS';
        });
        
        // Remove GENERAL_SETTINGS (duplicate of SYSTEM_SETTINGS)
        $items = array_filter($items, function($item) {
            return ($item['id'] ?? '') !== 'GENERAL_SETTINGS';
        });
        
        // Remove FEATURE_FLAGS and FEATURE_FLAGS_BUSINESS (not needed)
        $items = array_filter($items, function($item) {
            return !in_array($item['id'] ?? '', ['FEATURE_FLAGS', 'FEATURE_FLAGS_BUSINESS']);
        });
        
        // Remove SHIFTS navigation item (shift management removed)
        $items = array_filter($items, function($item) {
            return ($item['id'] ?? '') !== 'SHIFTS';
        });

        // Remove FINANCE_MAIN (duplicate of FINANCE that routed back to the
        // same /finance dashboard inside the Finance dropdown).
        $filterOutFinanceMain = function(array $list) use (&$filterOutFinanceMain): array {
            $clean = [];
            foreach ($list as $item) {
                if (($item['id'] ?? '') === 'FINANCE_MAIN') {
                    continue;
                }
                if (!empty($item['children']) && is_array($item['children'])) {
                    $item['children'] = array_values($filterOutFinanceMain($item['children']));
                }
                $clean[] = $item;
            }
            return $clean;
        };
        $items = array_values($filterOutFinanceMain($items));
        
        // BUSINESS_SETTINGS (İşletme Ayarları) artık kaldırılmıyor - işletme yöneticisi Ayarlar altında görür
        
        // Clean up unwanted items from database if they exist
        try {
            $itemsToRemove = ['STOCK', 'MIGRATIONS', 'GENERAL_SETTINGS', 'FEATURE_FLAGS', 'FEATURE_FLAGS_BUSINESS', 'FINANCE_MAIN'];
            foreach ($itemsToRemove as $itemKey) {
                $navItem = $navModel->getByKey($itemKey);
                if ($navItem) {
                    $navId = $navItem['nav_id'] ?? null;
                    if ($navId) {
                        $navModel->removeRolesForNav($navId);
                        $navModel->query()->where('nav_id', $navId)->delete();
                        error_log("Removed {$itemKey} navigation item from database");
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    } else {
        error_log("WARNING: No navigation items found in database! Please run navigation seed script.");
    }
    
} catch (\Exception $e) {
    error_log('Navigation config error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
}

// Return items (empty array if database is not accessible)
return $items;
