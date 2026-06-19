<?php
namespace App\Services;

/**
 * URL Service
 * Centralized service for generating URLs (table URLs, QR code URLs)
 * Replaces global helper functions with OOP approach
 */
class UrlService {
    /**
     * Generate table URL (SEO-friendly) with subdomain support
     * CRITICAL: Each business must use its own subdomain for table URLs
     * @param string $tableId Table ID
     * @param bool $useSeoUrl Whether to use SEO-friendly URL (default: true)
     * @param array|null $tableData Optional table data to use instead of fetching from DB
     * @return string Table URL with subdomain
     */
    public function generateTableUrl(string $tableId, bool $useSeoUrl = true, ?array $tableData = null): string {
        // CRITICAL: Get business subdomain for tenant isolation
        $subdomain = null;
        $tenantId = null;
        
        // Try to get tenant ID from context
        if (class_exists('\App\Core\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
                $tenant = \App\Core\TenantContext::get();
                if ($tenant && !empty($tenant['subdomain'])) {
                    $subdomain = $tenant['subdomain'];
                }
            } catch (\Exception $e) {
                // TenantContext not set, continue
            }
        }
        
        // If subdomain not in context, try to get from table's business_id
        if (!$subdomain) {
            // Use provided table data or fetch from database
            if ($tableData !== null && is_array($tableData) && count($tableData) > 0) {
                $table = $tableData;
            } else {
                try {
                    $tableService = \App\Core\DependencyFactory::getTableService();
                    $table = $tableService->getTableById($tableId);
                } catch (\Exception $e) {
                    error_log("UrlService: Error getting table for tableId '{$tableId}': " . $e->getMessage());
                    // Fallback to old format without subdomain
                    try {
                        $baseUrl = \App\Services\BaseUrlService::getBaseUrl();
                    } catch (\Exception $e) {
                        $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost';
                    }
                    return $baseUrl . '/t/' . $tableId;
                }
            }
            
            if ($table && is_array($table)) {
                $tableBusinessId = $table['tenant_id'] ?? null;
                if ($tableBusinessId) {
                    try {
                        $customerService = \App\Core\DependencyFactory::getCustomerService();
                        $customer = $customerService->getById($tableBusinessId);
                        if ($customer && !empty($customer['subdomain'])) {
                            $subdomain = $customer['subdomain'];
                        }
                    } catch (\Exception $e) {
                        error_log("UrlService: Error getting customer subdomain: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Build base URL with subdomain
        $baseUrl = $this->getBaseUrlWithSubdomain($subdomain);
        
        // If SEO URL is disabled, use old format for backward compatibility
        if (!$useSeoUrl) {
            return $baseUrl . '/t/' . $tableId;
        }
        
        // Use provided table data or fetch from database
        if ($tableData !== null && is_array($tableData) && count($tableData) > 0) {
            $table = $tableData;
            error_log("UrlService: Using provided tableData for tableId '{$tableId}'");
        } else {
            // Fetch from database if tableData not provided
            try {
                $tableService = \App\Core\DependencyFactory::getTableService();
                $table = $tableService->getTableById($tableId);
                error_log("UrlService: Fetched table from database for tableId '{$tableId}'");
            } catch (\Exception $e) {
                error_log("UrlService: Error getting table for tableId '{$tableId}': " . $e->getMessage());
                return $baseUrl . '/t/' . $tableId;
            }
        }
        
        if (!$table || !is_array($table)) {
            error_log("UrlService: Table data is invalid for tableId '{$tableId}', falling back to old format");
            return $baseUrl . '/t/' . $tableId;
        }
        
        // Check if table name exists - if not, fall back to old format
        if (empty($table['name'])) {
            error_log("UrlService: Table name is missing for tableId '{$tableId}', falling back to old format");
            return $baseUrl . '/t/' . $tableId;
        }
        
        // Get zone information - prioritize zone name from tableData if available
        $zoneName = '';
        
        // First try to get zone name from table data (if provided)
        if (!empty($table['zone']) && is_string($table['zone'])) {
            $zoneName = $table['zone'];
            error_log("UrlService: Using zone name from table data: '{$zoneName}'");
        } elseif (!empty($table['zone_id'])) {
            // If zone name not in table data, fetch from zone service
            try {
                $zoneService = \App\Core\DependencyFactory::getZoneService();
                $zone = $zoneService->getZoneById($table['zone_id']);
                if ($zone && !empty($zone['name'])) {
                    $zoneName = $zone['name'];
                    error_log("UrlService: Fetched zone name from service: '{$zoneName}' for zone_id '{$table['zone_id']}'");
                } else {
                    error_log("UrlService: Zone not found or has no name for zone_id '{$table['zone_id']}'");
                }
            } catch (\Exception $e) {
                error_log("UrlService: Error getting zone for zone_id '{$table['zone_id']}': " . $e->getMessage());
            }
        } else {
            error_log("UrlService: No zone_id or zone name provided for tableId '{$tableId}', using default 'masa'");
        }
        
        // Generate slugs
        require_once __DIR__ . '/../helpers/functions.php';
        $zoneSlug = !empty($zoneName) ? generateSlug($zoneName) : 'masa';
        
        // Use unique_slug if available, otherwise fallback to table name slug (backward compatibility)
        if (!empty($table['unique_slug'])) {
            $tableSlug = $table['unique_slug'];
            error_log("UrlService: Using unique_slug '{$tableSlug}' for tableId '{$tableId}'");
        } else {
            // Fallback to table name slug for backward compatibility
            $tableSlug = generateSlug($table['name']);
            error_log("UrlService: Using table name slug '{$tableSlug}' for tableId '{$tableId}' (unique_slug not available)");
        }
        
        // Always generate SEO-friendly URL if table name exists
        // Only fall back to old format if table name is missing (checked above)
        $seoUrl = $baseUrl . '/masa/' . $zoneSlug . '/' . $tableSlug;
        error_log("UrlService: Generated SEO URL with subdomain '{$subdomain}' for tableId '{$tableId}': {$seoUrl}");
        
        return $seoUrl;
    }
    
    /**
     * Get base URL with subdomain
     * @param string|null $subdomain Subdomain (e.g., 'restaurant1')
     * @return string Base URL with subdomain (e.g., 'https://restaurant1.qordy.com')
     */
    private function getBaseUrlWithSubdomain(?string $subdomain): string {
        try {
            // Get main domain from BaseUrlService
            $mainDomain = \App\Services\BaseUrlService::getDomain();
        } catch (\Exception $e) {
            // Fallback to constant if service is not available
            $mainDomain = defined('BASE_URL') ? parse_url(BASE_URL, PHP_URL_HOST) : 'qordy.com';
        }
        
        // If subdomain is provided, prepend it
        if ($subdomain) {
            // Remove protocol if present
            $mainDomain = str_replace(['http://', 'https://'], '', $mainDomain);
            // Build subdomain URL
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            return $protocol . '://' . $subdomain . '.' . $mainDomain;
        }
        
        // No subdomain, use main domain
        try {
            return \App\Services\BaseUrlService::getBaseUrl();
        } catch (\Exception $e) {
            return defined('BASE_URL') ? BASE_URL : 'http://localhost';
        }
    }
    
    /**
     * Generate QR code image URL from table URL
     * @param string $tableUrl Table URL
     * @return string QR code image URL
     */
    public function generateQRCodeImage(string $tableUrl): string {
        // Yerel QR endpoint'imiz (endroid/qr-code ile üretim).
        return rtrim(BASE_URL, '/') . '/qr?size=500&data=' . urlencode($tableUrl);
    }

    /**
     * Resolve the platform apex/base domain (e.g. "qordy.com").
     *
     * Priority:
     *   1. .env BASE_DOMAIN  (authoritative; survives main + subdomain contexts)
     *   2. Strip the first label from HTTP_HOST when it has 3+ parts and the
     *      first part is NOT a reserved/system subdomain
     *   3. Raw HTTP_HOST
     *   4. Hard fallback "qordy.com"
     */
    public function getApexDomain(): string {
        $env = trim((string) ($_ENV['BASE_DOMAIN'] ?? getenv('BASE_DOMAIN') ?: ''));
        if ($env !== '') {
            return strtolower($env);
        }

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $host = strtolower(explode(':', $host)[0]);
        $parts = $host !== '' ? explode('.', $host) : [];

        if (count($parts) >= 3) {
            $first = $parts[0];
            $systemSubs = ['www', 'admin', 'api', 'qodmin', 'business'];
            if (!in_array($first, $systemSubs, true)) {
                return implode('.', array_slice($parts, 1));
            }
            return implode('.', array_slice($parts, 1));
        }

        return $host !== '' ? $host : 'qordy.com';
    }

    /**
     * Build a fully-qualified URL for a given tenant, always targeting the
     * tenant's own subdomain on the platform apex.
     *
     *   buildTenantUrl('CUST_...', '/q')
     *     → "https://caddecafe.qordy.com/q"
     *
     * Falls back to the apex domain when the tenant has no subdomain recorded.
     */
    public function buildTenantUrl(string $tenantId, string $path = '/'): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $apex = $this->getApexDomain();
        $subdomain = null;

        try {
            if (class_exists('\App\Core\TenantContext')) {
                $ctx = \App\Core\TenantContext::get();
                $ctxId = \App\Core\TenantContext::getId();
                if (is_array($ctx) && (string) ($ctxId ?? '') === $tenantId) {
                    $subdomain = $ctx['subdomain'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if (!$subdomain) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($tenantId);
                $subdomain = $customer['subdomain'] ?? null;
            } catch (\Throwable $e) {
                $subdomain = null;
            }
        }

        $path = '/' . ltrim((string) $path, '/');
        if ($subdomain) {
            $subdomain = strtolower(preg_replace('/[^a-z0-9\-]/i', '', (string) $subdomain));
        }
        $host = $subdomain ? $subdomain . '.' . $apex : $apex;
        return $protocol . '://' . $host . $path;
    }
}

