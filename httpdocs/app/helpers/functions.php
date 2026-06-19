<?php
// Helper functions for the application

// Load ViewHelper for XSS protection
require_once __DIR__ . '/../core/ViewHelper.php';

/**
 * Escape HTML to prevent XSS (uses ViewHelper)
 * @param string|null $text Text to escape
 * @return string Escaped text
 */
if (!function_exists('escape')) {
    function escape(?string $text): string {
        return \App\Core\ViewHelper::escape($text);
    }
}

/**
 * Safe HTML escape (alias for escape) - handles null values
 * @param string|null $text Text to escape
 * @return string Escaped text
 */
if (!function_exists('safeHtml')) {
    function safeHtml(?string $text): string {
        return escape($text);
    }
}

/**
 * Escape HTML attribute value (uses ViewHelper)
 * @param string|null $text Text to escape
 * @return string Escaped text
 */
if (!function_exists('escapeAttr')) {
    function escapeAttr(?string $text): string {
        return \App\Core\ViewHelper::escapeAttr($text);
    }
}

/**
 * Escape JavaScript string (uses ViewHelper)
 * @param string|null $text Text to escape
 * @return string Escaped text for use in JavaScript
 */
if (!function_exists('escapeJs')) {
    function escapeJs(?string $text): string {
        return \App\Core\ViewHelper::escapeJs($text);
    }
}

/**
 * Sanitize input to prevent XSS (alias for escape)
 * @param string|null $input Input to sanitize
 * @return string Sanitized input
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput(?string $input): string {
        return escape($input);
    }
}

/**
 * Generate unique ID with optional prefix
 * @param string $prefix Prefix for the ID (e.g., 'rec', 'pq', 'lt')
 * @return string Generated ID
 */
if (!function_exists('generateId')) {
    function generateId(string $prefix = ''): string {
        require_once __DIR__ . '/../core/Helpers/IdGeneratorHelper.php';
        return \App\Core\Helpers\IdGeneratorHelper::generateId($prefix, 13);
    }
}


if (!function_exists('normalizeRole')) {
    /**
     * Normalize role to standard English code
     * Handles Turkish role names and converts them to English codes
     * 
     * @param string $role Raw role from database or input
     * @return string Normalized role code (MANAGER, WAITER, etc.)
     */
    function normalizeRole(string $role): string {
        require_once __DIR__ . '/../services/RoleMapper.php';
        $roleMapper = \App\Services\RoleMapper::getInstance();
        return $roleMapper->normalizeRole($role);
    }
}

if (!function_exists('generateRestaurantStructuredData')) {
    function generateRestaurantStructuredData() {
        $seoService = getSEOService();
        return $seoService->generateStructuredData('restaurant', []);
    }
}

/**
 * Get AssetManager instance
 * @return \App\Services\AssetManager
 */
if (!function_exists('getAssetManager')) {
    function getAssetManager() {
        require_once __DIR__ . '/../services/AssetManager.php';
        return \App\Services\AssetManager::getInstance();
    }
}

/**
 * Get AppConfig instance
 * @return \App\Services\AppConfig
 */
if (!function_exists('getAppConfig')) {
    function getAppConfig() {
        require_once __DIR__ . '/../services/AppConfig.php';
        return \App\Services\AppConfig::getInstance();
    }
}

/**
 * Get ThemeService instance
 * @return \App\Services\ThemeService
 */
if (!function_exists('getThemeService')) {
    function getThemeService() {
        require_once __DIR__ . '/../services/ThemeService.php';
        return \App\Services\ThemeService::getInstance();
    }
}

/**
 * Get asset URL
 * @param string $path Asset path
 * @return string Asset URL
 */
if (!function_exists('asset')) {
    function asset($path) {
        return getAssetManager()->getAssetUrl($path);
    }
}

/**
 * Get CDN URL
 * @param string $name CDN name
 * @return string CDN URL
 */
if (!function_exists('cdn')) {
    function cdn($name) {
        return getAssetManager()->getCdnUrl($name);
    }
}

/**
 * Get config value
 * @param string $key Config key
 * @param mixed $default Default value
 * @return mixed
 */
if (!function_exists('config')) {
    function config($key, $default = null) {
        return getAppConfig()->get($key, $default);
    }
}

/**
 * Generate SEO-friendly slug from Turkish text
 * @param string $text Text to convert to slug
 * @return string SEO-friendly slug
 */
if (!function_exists('generateSlug')) {
    /**
     * Generate SEO-friendly slug from text
     * Centralized slug generation - used by MenuItemTranslationRepository and other services
     * @param string $text Text to convert to slug
     * @return string SEO-friendly slug
     */
    function generateSlug(string $text): string {
        // Convert Turkish characters to English equivalents (same logic as MenuItemTranslationRepository)
        $turkish = ['ş', 'Ş', 'ı', 'İ', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'];
        $english = ['s', 'S', 'i', 'I', 'g', 'G', 'u', 'U', 'o', 'O', 'c', 'C'];
        $text = str_replace($turkish, $english, $text);
        
        // Convert to lowercase
        $slug = strtolower($text);
        
        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        return $slug;
    }
}

/**
 * Generate unique random slug for tables
 * Creates a cryptographically secure random 8-character alphanumeric slug
 * Checks database for uniqueness and retries if collision occurs
 * @param \PDO|null $db Optional database connection (if not provided, will create one)
 * @param int $maxRetries Maximum number of retry attempts (default: 10)
 * @return string Unique random slug
 */
if (!function_exists('generateUniqueTableSlug')) {
    function generateUniqueTableSlug(?\PDO $db = null, int $maxRetries = 10): string {
        // Create database connection if not provided
        if ($db === null) {
            try {
                require_once __DIR__ . '/../config/database.php';
                $database = new \App\Config\Database();
                $db = $database->connect();
            } catch (\Exception $e) {
                error_log("generateUniqueTableSlug: Failed to connect to database: " . $e->getMessage());
                // Fallback: generate without uniqueness check (should not happen in production)
                return bin2hex(random_bytes(4)); // 8 characters
            }
        }
        
        $attempts = 0;
        while ($attempts < $maxRetries) {
            // Generate cryptographically secure random slug (8 characters)
            // Using lowercase letters and numbers for URL-friendly format
            $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $slug = '';
            $max = strlen($characters) - 1;
            
            // Use random_bytes for cryptographically secure randomness
            for ($i = 0; $i < 8; $i++) {
                $randomByte = random_int(0, $max);
                $slug .= $characters[$randomByte];
            }
            
            // Check if slug already exists in database
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM tables WHERE unique_slug = :slug");
                $stmt->execute(['slug' => $slug]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($result && $result['count'] == 0) {
                    // Slug is unique, return it
                    return $slug;
                }
            } catch (\PDOException $e) {
                // If column doesn't exist yet (during migration), return slug anyway
                if (strpos($e->getMessage(), "unknown column 'unique_slug'") !== false) {
                    return $slug;
                }
                error_log("generateUniqueTableSlug: Database error: " . $e->getMessage());
            }
            
            $attempts++;
        }
        
        // If all retries failed, generate a longer slug with timestamp
        // This should be extremely rare
        $timestamp = substr(base_convert(time(), 10, 36), -4); // Last 4 chars of timestamp in base36
        $random = bin2hex(random_bytes(2)); // 4 random hex chars
        return substr($timestamp . $random, 0, 8);
    }
}

/**
 * Get theme color
 * @param string $name Color name
 * @param mixed $default Default value
 * @return string Color value
 */
if (!function_exists('themeColor')) {
    function themeColor($name, $default = null) {
        return getThemeService()->getThemeColor($name, $default);
    }
}

/**
 * Get NotificationService instance
 * @return \App\Services\NotificationService
 */
if (!function_exists('getNotificationService')) {
    function getNotificationService() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        return \App\Core\DependencyFactory::getNotificationService();
    }
}

/**
 * Get ToastNotificationService instance
 * @return \App\Services\ToastNotificationService
 */
if (!function_exists('getToastNotificationService')) {
    function getToastNotificationService() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        return \App\Core\DependencyFactory::getToastNotificationService();
    }
}

/**
 * Get SEOService instance
 * @return \App\Services\SEOService
 */
if (!function_exists('getSEOService')) {
    function getSEOService() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        return \App\Core\DependencyFactory::getSEOService();
    }
}

/**
 * Get SEOContentService instance
 * @return \App\Services\SEOContentService
 */
if (!function_exists('getSEOContentService')) {
    function getSEOContentService() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        return \App\Core\DependencyFactory::getSEOContentService();
    }
}

/**
 * Get FilterService instance
 * @return \App\Services\FilterService
 */
if (!function_exists('getFilterService')) {
    function getFilterService() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        return \App\Core\DependencyFactory::getFilterService();
    }
}

/**
 * Get SearchService instance
 * @return \App\Services\SearchService
 */
if (!function_exists('getSearchService')) {
    function getSearchService() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        return \App\Core\DependencyFactory::getSearchService();
    }
}

/**
 * Format currency
 * @param float|int|string $amount Amount to format
 * @return string Formatted currency string
 */
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount = 0) {
        if (is_nan($amount) || $amount === null || $amount === '') return '0 ₺';
        try {
            return number_format((float)$amount, 2, ',', '.') . ' ₺';
        } catch (\Exception $e) {
            return $amount . ' ₺';
        }
    }
}

/**
 * Format date
 * @param string|int|null $date Date string or timestamp
 * @param string $format Date format (default: d.m.Y)
 * @return string Formatted date
 */
if (!function_exists('formatDate')) {
    function formatDate($date, string $format = 'd.m.Y') {
        if (empty($date)) {
            return '-';
        }
        try {
            $timestamp = is_numeric($date) ? intval($date) : strtotime($date);
            if ($timestamp === false) {
                return '-';
            }
            return date($format, $timestamp);
        } catch (\Exception $e) {
            return '-';
        }
    }
}

/**
 * Group order items for display - aynı ürün (aynı özelleştirme) tek satırda birleştirilir
 * Farklı ekstra/çıkarılan malzeme/not = ayrı satır
 *
 * @param array $items Order items array
 * @return array Grouped items with summed quantity
 */
/**
 * Get Turkish label for order status
 * @param string $status Order status (PENDING, PREPARING, READY, SERVED, CANCELLED, REFUNDED, etc.)
 * @return string Turkish label
 */
if (!function_exists('getOrderStatusLabel')) {
    function getOrderStatusLabel(?string $status = ''): string {
        $labels = [
            'PENDING' => 'Beklemede',
            'PREPARING' => 'Hazırlanıyor',
            'READY' => 'Hazır',
            'SERVED' => 'Tamamlandı',
            'CANCELLED' => 'İptal',
            'REFUNDED' => 'İade',
            'PAYMENT_PENDING' => 'Ödeme Bekliyor',
        ];
        $s = strtoupper(trim($status));
        return $labels[$s] ?? $status ?: 'Beklemede';
    }
}

if (!function_exists('groupOrderItemsForDisplay')) {
    function groupOrderItemsForDisplay(array $items): array {
        if (empty($items)) {
            return [];
        }
        $groups = [];
        foreach ($items as $item) {
            $menuItemId = $item['menu_item_id'] ?? '';
            $variantId = $item['variant_id'] ?? '';
            $extras = $item['selected_extras'] ?? [];
            $extrasArr = is_array($extras) ? $extras : (is_string($extras) ? (json_decode($extras, true) ?: []) : []);
            $extrasNames = array_map(function ($e) {
                return is_array($e) ? ($e['extra_id'] ?? $e['name'] ?? '') : $e;
            }, $extrasArr);
            sort($extrasNames);
            $extrasKey = implode('|', $extrasNames);
            $excluded = $item['excluded_ingredients'] ?? [];
            $excludedArr = is_array($excluded) ? $excluded : (is_string($excluded) ? (json_decode($excluded, true) ?: []) : []);
            $excludedNames = array_map(function ($e) {
                return is_array($e) ? ($e['ingredient_name'] ?? $e['name'] ?? '') : $e;
            }, $excludedArr);
            sort($excludedNames);
            $excludedKey = implode('|', $excludedNames);
            $note = trim($item['note'] ?? $item['notes'] ?? $item['item_note'] ?? '');
            $key = $menuItemId . "\0" . $variantId . "\0" . $extrasKey . "\0" . $excludedKey . "\0" . $note;
            if (!isset($groups[$key])) {
                $groups[$key] = $item;
                $groups[$key]['quantity'] = 0;
            }
            $groups[$key]['quantity'] += (int)($item['quantity'] ?? 1);
        }
        return array_values($groups);
    }
}

if (!function_exists('isHttps')) {
    /**
     * Check if request is secure (HTTPS)
     * Handles reverse proxies
     * @return bool True if HTTPS
     */
    function isHttps(): bool {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );
    }
}

if (!function_exists('getProtocol')) {
    /**
     * Get request protocol (http or https)
     * @return string
     */
    function getProtocol(): string {
        return isHttps() ? 'https' : 'http';
    }
}

/**
 * Normalize REQUEST_URI to an app-relative path (strips base dir, /public, /qordy, locale prefix).
 */
if (!function_exists('normalizeAppRequestPath')) {
    function normalizeAppRequestPath(?string $requestUri = null): string {
        $uri = $requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            $path = '/';
        }

        if (preg_match('#^/(tr|en)(/|$)#', $path)) {
            $path = preg_replace('#^/(tr|en)#', '', $path);
            if ($path === '' || $path === null) {
                $path = '/';
            }
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        if (strpos($scriptDir, '/public') !== false) {
            $scriptDir = str_replace('/public', '', $scriptDir);
        }
        if ($scriptDir !== '/' && $scriptDir !== '.' && $scriptDir !== '' && strpos($path, $scriptDir) === 0) {
            $path = substr($path, strlen($scriptDir));
        }
        if (strpos($path, '/qordy') === 0) {
            $path = substr($path, strlen('/qordy'));
        }
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path === '//') {
            $path = '/';
        }

        return $path;
    }
}

/**
 * Whether the current route/view needs business-theme.css (panel + ops surfaces).
 */
if (!function_exists('routeNeedsBusinessTheme')) {
    function routeNeedsBusinessTheme(?string $path = null, ?string $view = null): bool {
        $path = $path ?? normalizeAppRequestPath();

        if (strpos($path, '/business/') === 0
            || strpos($path, '/qodmin/') === 0
            || $path === '/qodmin') {
            return true;
        }

        if (preg_match('#^/(?:pos|waiter|kitchen|preparation-screen|cashier)(?:/|$)#', $path)) {
            return true;
        }

        if ($view !== null && $view !== ''
            && preg_match('#^(?:waiter|kitchen|pos|cashier|preparation-screen)/#', $view)) {
            return true;
        }

        return false;
    }
}