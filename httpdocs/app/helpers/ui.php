<?php
/**
 * UI Helper Functions
 */

/**
 * Generate CSRF token input field for forms
 * @return string HTML input field with CSRF token
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Escape HTML output to prevent XSS
 * @param string $value
 * @param int $flags
 * @return string
 */
function e($value, $flags = ENT_QUOTES | ENT_HTML5): string {
    if (is_null($value)) {
        return '';
    }
    return htmlspecialchars((string)$value, $flags, 'UTF-8');
}

/**
 * Escape JavaScript output to prevent XSS
 * @param string $value
 * @return string
 */
function e_js($value): string {
    if (is_null($value)) {
        return '';
    }
    return json_encode((string)$value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * UI Helper Functions
 * Helper functions for UI components like toast, modals, etc.
 */

// Include icons if not already included
// Check for any icon function to determine if icons.php is loaded
$anyIconFunctionExists = function_exists('icon_plus') || function_exists('icon_folder') || function_exists('icon_edit') || function_exists('icon_trash') || function_exists('icon_utensils');
if (!$anyIconFunctionExists) {
    require_once __DIR__ . '/../views/partials/icons.php';
} else {
    // Ensure all required icon functions exist, if not, load icons.php anyway
    if (!function_exists('icon_folder')) {
        require_once __DIR__ . '/../views/partials/icons.php';
    }
}

if (!function_exists('getIcon')) {
    /**
     * Get icon by name - maps React icon names to PHP icon functions
     * @param string $iconName - Icon name (e.g., 'ChefHat', 'LayoutGrid')
     * @param string $class - CSS classes for the icon
     * @return string - SVG icon HTML
     */
    function getIcon($iconName, $class = 'w-6 h-6') {
        // Map React icon names to PHP icon function names
        $iconMap = [
            'ChefHat'           => 'icon_chef_hat',
            'LayoutGrid'        => 'icon_layout_grid',
            'LayoutDashboard'   => 'icon_layout_dashboard',
            'CreditCard'        => 'icon_credit_card',
            'FileText'          => 'icon_file_text',
            'Calendar'          => 'icon_calendar',
            'CalendarDays'      => 'icon_calendar',       // DB: RESERVATIONS
            'CalendarClock'     => 'icon_calendar',       // DB: HR_SHIFTS
            'CalendarCheck'     => 'icon_check_circle',   // DB: HR_LEAVES
            'Wallet'            => 'icon_wallet',
            'UserCog'           => 'icon_user_cog',
            'UserPlus'          => 'icon_user',           // DB: HR_GUEST_STAFF
            'SettingsSliders'   => 'icon_settings_sliders',
            'Settings'          => 'icon_settings',
            'LogOut'            => 'icon_log_out',
            'Signal'            => 'icon_signal',
            'Bell'              => 'icon_bell',
            'Clock'             => 'icon_clock',
            'Utensils'          => 'icon_utensils',
            'Plus'              => 'icon_plus',
            'Minus'             => 'icon_minus',
            'Trash'             => 'icon_trash',
            'Trash2'            => 'icon_trash',           // DB: FINANCE_WASTE
            'Edit'              => 'icon_edit',
            'Check'             => 'icon_check',
            'X'                 => 'icon_x',
            'ArrowLeft'         => 'icon_arrow_left',
            'BarChart'          => 'icon_bar_chart',
            'BarChart2'         => 'icon_bar_chart',
            'BarChart3'         => 'icon_bar_chart',
            'Printer'           => 'icon_printer',
            'Receipt'           => 'icon_receipt',
            'TrendingDown'      => 'icon_trending_down',
            'TrendingUp'        => 'icon_trending_up',
            'Package'           => 'icon_package',
            'ShoppingCart'      => 'icon_shopping_cart',
            'Grid'              => 'icon_grid',
            'Grid3x3'           => 'icon_grid',            // DB: TABLES
            'Shield'            => 'icon_shield',
            'Circle'            => 'icon_circle',
            'User'              => 'icon_user',
            'Users'             => 'icon_users',
            'Monitor'           => 'icon_monitor',
            'AlertCircle'       => 'icon_alert_circle',
            'AlertTriangle'     => 'icon_alert_triangle',
            'CheckCircle'       => 'icon_check_circle',
            'Folder'            => 'icon_folder',
            'FolderTree'        => 'icon_folder',          // DB: FINANCE_STOCK_CATEGORIES
            'Building'          => 'icon_building',
            'Building2'         => 'icon_building2',
            'Store'             => 'icon_building',        // DB: BUSINESS_SETTINGS
            'Mail'              => 'icon_mail',
            'ToggleRight'       => 'icon_toggle_right',
            'RefreshCw'         => 'icon_refresh_cw',
            'FileEdit'          => 'icon_edit',
            'List'              => 'icon_file_text',
            'History'           => 'icon_clock',           // DB: ORDER_APPROVAL_HISTORY
            'Truck'             => 'icon_shopping_cart',   // DB: FINANCE_PURCHASES, FINANCE_SUPPLIERS
            'Sparkles'          => 'icon_sparkles',        // DB: AI_SUGGESTIONS
        ];
        
        $functionName = $iconMap[$iconName] ?? null;
        
        if ($functionName && function_exists($functionName)) {
            return $functionName(['class' => $class]);
        }
        
        // Fallback: try direct function name if icon name matches function pattern
        $directFunctionName = 'icon_' . strtolower(preg_replace('/([A-Z])/', '_$1', lcfirst($iconName)));
        if (function_exists($directFunctionName)) {
            return $directFunctionName(['class' => $class]);
        }
        
        // Return empty string if icon not found
        return '';
    }
}

if (!function_exists('renderToast')) {
    /**
     * Render toast notification container
     */
    function renderToast() {
        return '<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>';
    }
}

// showToast function moved to toast.php for centralized management
// This function is kept for backward compatibility but delegates to toast.php

if (!function_exists('getToasts')) {
    /**
     * Get and clear toast messages from session
     */
    function getToasts() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $toasts = $_SESSION['toasts'] ?? [];
        unset($_SESSION['toasts']);
        
        return $toasts;
    }
}

if (!function_exists('renderToastScript')) {
    /**
     * Render JavaScript to show toasts from session
     */
    function renderToastScript() {
        $toasts = getToasts();
        if (empty($toasts)) {
            return '';
        }
        
        $script = '<script>';
        foreach ($toasts as $toast) {
            $type = htmlspecialchars($toast['type'], ENT_QUOTES, 'UTF-8');
            $message = htmlspecialchars($toast['message'], ENT_QUOTES, 'UTF-8');
            $script .= "if (window.Toast) { window.Toast.show('{$message}', '{$type}'); }";
        }
        $script .= '</script>';
        
        return $script;
    }
}

if (!function_exists('buildUrl')) {
    /**
     * Build clean, SEO-friendly URL with optional query parameters
     * This function creates URLs that are safe, SEO-friendly, and follow the central routing structure
     * 
     * @param string $path Base path (e.g., '/qodmin/error-logs')
     * @param array $params Query parameters (will be properly encoded)
     * @param bool $removeEmpty Remove empty/null parameters
     * @return string Clean URL
     */
    function buildUrl(string $path, array $params = [], bool $removeEmpty = true): string {
        // Validate and sanitize path to prevent path traversal and injection
        $path = trim($path, '/');
        
        // Remove any dangerous characters and path traversal attempts
        $path = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $path);
        $path = str_replace(['..', '//'], '', $path);
        
        // Ensure path starts with /
        $path = '/' . ltrim($path, '/');
        
        // Filter out empty values if requested
        if ($removeEmpty) {
            $params = array_filter($params, function($value) {
                return $value !== '' && $value !== null && $value !== false;
            });
        }
        
        // Build query string
        $queryString = '';
        if (!empty($params)) {
            // Sort parameters for consistent URLs
            ksort($params);
            $queryParts = [];
            foreach ($params as $key => $value) {
                // Validate key to prevent injection (only alphanumeric, underscore, hyphen)
                if (preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
                    // Encode both key and value to prevent XSS and injection
                    $queryParts[] = urlencode($key) . '=' . urlencode((string)$value);
                }
            }
            if (!empty($queryParts)) {
                $queryString = '?' . implode('&', $queryParts);
            }
        }
        
        // Ensure BASE_URL is safe (should be defined in config)
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        if (empty($baseUrl)) {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                      '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        
        return rtrim($baseUrl, '/') . $path . $queryString;
    }
}

if (!function_exists('route')) {
    /**
     * Generate route URL (alias for buildUrl for consistency)
     * 
     * @param string $path Route path
     * @param array $params Route parameters
     * @return string Generated URL
     */
    function route(string $path, array $params = []): string {
        return buildUrl($path, $params);
    }
}

