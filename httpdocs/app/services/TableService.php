<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\TableRepository;

class TableService extends BaseService {
    
    public function __construct(TableRepository $tableRepository) {
        parent::__construct($tableRepository);
    }
    
    /**
     * Get all tables (with Redis cache for performance)
     * @return array All tables
     */
    public function getAllTables(): array {
        $tenantId = \App\Core\TenantContext::getId() ?? 'guest';
        $cacheKey = "tables:all:{$tenantId}";
        
        try {
            $cache = \App\Core\DependencyFactory::getCacheService();
            
            // Try to get from cache (5 seconds TTL for real-time updates)
            $tables = $cache->get($cacheKey);
            if ($tables !== null) {
                return $tables;
            }
            
            // Cache miss - get from database
            $tables = $this->repository->findAll();
            
            // Store in cache for 5 seconds (optimized for real-time table status updates)
            $cache->set($cacheKey, $tables, 5);
            
            return $tables;
        } catch (\Exception $e) {
            // If cache fails, return from database directly
            return $this->repository->findAll();
        }
    }
    
    /**
     * Get table by ID
     * @param string $tableId Table ID
     * @return array|null Table data or null
     */
    public function getTableById(string $tableId): ?array {
        return $this->repository->findById($tableId);
    }

    /**
     * Get table by ID without tenant filter (for bootstrap when setting tenant from table)
     * Use only when TenantContext is not set - security: caller must verify user has access
     * @param string $tableId Table ID
     * @return array|null Table data or null
     */
    public function getTableByIdUnscoped(string $tableId): ?array {
        return $this->repository->findByIdUnscoped($tableId);
    }
    
    /**
     * Get tables by status
     * @param string $status Table status
     * @return array Tables
     */
    public function getTablesByStatus(string $status): array {
        return $this->repository->getByStatus($status);
    }
    
    /**
     * Get tables by zone (backward compatibility)
     * @param string $zone Zone name
     * @return array Tables
     */
    public function getTablesByZone(string $zone): array {
        return $this->repository->getByZone($zone);
    }
    
    /**
     * Get tables by zone_id
     * @param string $zoneId Zone ID
     * @return array Tables
     */
    public function getTablesByZoneId(string $zoneId): array {
        return $this->repository->getByZoneId($zoneId);
    }
    
    /**
     * Get tables by floor
     * @param string $floor Floor name
     * @return array Tables
     */
    public function getTablesByFloor(string $floor): array {
        return $this->repository->getByFloor($floor);
    }
    
    /**
     * Get tables by section
     * @param string $section Section name
     * @return array Tables
     */
    public function getTablesBySection(string $section): array {
        return $this->repository->getBySection($section);
    }
    
    /**
     * Get available tables
     * @return array Available tables
     */
    public function getAvailableTables(): array {
        return $this->repository->getAvailableTables();
    }
    
    /**
     * Get occupied tables
     * @return array Occupied tables
     */
    public function getOccupiedTables(): array {
        return $this->repository->getOccupiedTables();
    }
    
    /**
     * Update table status
     * @param string $tableId Table ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateTableStatus(string $tableId, string $status): bool {
        $validStatuses = ['FREE', 'CUSTOMER_SEATED', 'OCCUPIED', 'PAYMENT_PENDING', 'DIRTY', 'RESERVED'];

        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        // Invalidate cache after status update
        $this->invalidateTablesCache();
        
        $result = $this->repository->updateStatus($tableId, $status);
        
        if ($result) {
            // Broadcast table status update via WebSocket
            try {
                require_once __DIR__ . '/WebSocketBroadcaster.php';
                $table = $this->repository->findById($tableId);
                if ($table) {
                    \App\Services\WebSocketBroadcaster::broadcastTable('updated', $table);
                }
            } catch (\Exception $e) {
                \App\Core\Logger::warning("WebSocket broadcast failed", ['error' => $e->getMessage()]);
            }
        }
        
        return $result;
    }
    
    /**
     * Generate table URL (SEO-friendly)
     * Uses centralized UrlService for consistency
     * @param string $tableId Table ID
     * @param bool $useSeoUrl Whether to use SEO-friendly URL (default: true)
     * @param array|null $tableData Optional table data to use instead of fetching from DB
     * @return string Table URL
     */
    public function generateTableUrl(string $tableId, bool $useSeoUrl = true, ?array $tableData = null): string {
        $urlService = \App\Core\DependencyFactory::getUrlService();
        return $urlService->generateTableUrl($tableId, $useSeoUrl, $tableData);
    }
    
    /**
     * Generate QR code URL
     * @param string $tableUrl Table URL
     * @return string QR code URL
     */
    public function generateQRCodeUrl(string $tableUrl): string {
        // Yerel QR endpoint'imiz (endroid/qr-code ile üretim).
        return rtrim(BASE_URL, '/') . '/qr?size=500&data=' . urlencode($tableUrl);
    }
    
    /**
     * Create a new table
     * @param array $tableData Table data
     * @return bool|string Table ID on success, false on failure
     */
    public function createTable(array $tableData) {
        require_once __DIR__ . '/../helpers/functions.php';
        
        if (empty($tableData['table_id'])) {
            $tableData['table_id'] = generateId('table');
        }
        
        if (empty($tableData['name'])) {
            return false;
        }
        
        // Remove hardcoded defaults - get from zone if zone_id provided
        $defaults = [
            'capacity' => 4,
            'status' => 'FREE'
        ];
        
        // If zone_id is provided, get zone info for zone name (backward compatibility)
        // This ensures zone name is available for URL generation
        // Validate zone_id is not empty string (not just !empty() check)
        $zoneId = trim($tableData['zone_id'] ?? '');
        if (!empty($zoneId) && $zoneId !== '' && empty($tableData['zone'])) {
            try {
                $zoneService = \App\Core\DependencyFactory::getZoneService();
                $zone = $zoneService->getZoneById($zoneId);
                if ($zone && !empty($zone['name'])) {
                    $tableData['zone'] = $zone['name'];
                    error_log("TableService: Successfully retrieved zone name '{$zone['name']}' for zone_id '{$zoneId}'");
                } else {
                    error_log("TableService: Zone not found or has no name for zone_id '{$zoneId}'");
                }
            } catch (\Exception $e) {
                error_log("TableService: Error getting zone for zone_id '{$zoneId}': " . $e->getMessage());
            }
        } elseif (empty($tableData['zone']) && !empty($zoneId)) {
            error_log("TableService: Warning - zone_id '{$zoneId}' provided but zone name not set and lookup failed");
        }
        
        $tableData = array_merge($defaults, $tableData);
        
        // Generate unique slug if not provided
        if (empty($tableData['unique_slug'])) {
            $tableData['unique_slug'] = generateUniqueTableSlug();
        }
        
        // Auto-generate SEO-friendly URL and QR code
        // Pass tableData to generateTableUrl so it can use zone info without DB query
        // IMPORTANT: tableData must have zone and name for SEO URL generation
        // Always regenerate URL if it's empty OR if it's in old format (/t/tableId)
        // Check for old format in both relative (/t/tableId) and absolute (https://domain/t/tableId) formats
        $currentUrl = $tableData['url'] ?? '';
        $isOldFormat = !empty($currentUrl) && (
            strpos($currentUrl, '/t/') !== false || 
            preg_match('#/t/[a-zA-Z0-9]+$#', $currentUrl)
        );
        
        // Always generate new SEO URL for new tables (empty URL) or if old format detected
        if (empty($tableData['url']) || $isOldFormat) {
            $oldUrl = $tableData['url'] ?? 'none';
            $tableData['url'] = $this->generateTableUrl($tableData['table_id'], true, $tableData);
            error_log("TableService: Regenerated URL for tableId '{$tableData['table_id']}': old='{$oldUrl}', new='{$tableData['url']}'");
        } else {
            error_log("TableService: Keeping existing URL for tableId '{$tableData['table_id']}': '{$tableData['url']}'");
        }
        
        // Always regenerate QR code if URL was regenerated or if QR code is missing/old format
        $currentQrUrl = $tableData['qr_code_url'] ?? '';
        $qrIsOldFormat = !empty($currentQrUrl) && strpos($currentQrUrl, '/t/') !== false;
        
        if (empty($tableData['qr_code_url']) || $isOldFormat || $qrIsOldFormat) {
            $tableData['qr_code_url'] = $this->generateQRCodeUrl($tableData['url']);
            error_log("TableService: Generated new QR code URL for tableId '{$tableData['table_id']}': {$tableData['qr_code_url']}");
        }
        
        // Log final tableData before insert to debug
        error_log("TableService: Creating table with URL: '{$tableData['url']}' and QR: '{$tableData['qr_code_url']}' for tableId '{$tableData['table_id']}'");
        
        $result = $this->repository->create($tableData);
        
        if ($result) {
            error_log("TableService: Successfully created table '{$tableData['table_id']}' with URL: '{$tableData['url']}'");
            // Invalidate cache after creation
            $this->invalidateTablesCache();
            return $tableData['table_id'];
        } else {
            error_log("TableService: Failed to create table '{$tableData['table_id']}'");
        }
        
        return false;
    }
    
    /**
     * Update table
     * @param string $tableId Table ID
     * @param array $tableData Table data to update
     * @return bool Success
     */
    public function updateTable(string $tableId, array $tableData): bool {
        require_once __DIR__ . '/../helpers/functions.php';
        
        // Generate unique slug if not provided and table doesn't have one
        if (empty($tableData['unique_slug'])) {
            $existingTable = $this->getTableById($tableId);
            if (!$existingTable || empty($existingTable['unique_slug'])) {
                $tableData['unique_slug'] = generateUniqueTableSlug();
            }
        }
        
        // Old domain to check for (can be configured or detected)
        $oldDomains = ['caddecafe.pfdk.me', 'pfdk.me'];
        
        // If table_id is being changed, regenerate SEO-friendly URL and QR code
        if (isset($tableData['table_id']) && $tableData['table_id'] !== $tableId) {
            $tableData['url'] = $this->generateTableUrl($tableData['table_id'], true);
            $tableData['qr_code_url'] = $this->generateQRCodeUrl($tableData['url']);
        } else {
            // Get current table to check URL format
            $table = $this->getTableById($tableId);
            
            // Check if URL contains old domain and needs update
            $needsDomainUpdate = false;
            if (!empty($tableData['url'])) {
                foreach ($oldDomains as $oldDomain) {
                    if (strpos($tableData['url'], $oldDomain) !== false) {
                        $needsDomainUpdate = true;
                        break;
                    }
                }
            } elseif ($table && !empty($table['url'])) {
                foreach ($oldDomains as $oldDomain) {
                    if (strpos($table['url'], $oldDomain) !== false) {
                        $needsDomainUpdate = true;
                        break;
                    }
                }
            }
            
            // If URL is not being explicitly set, check if we need to update it
            if (!isset($tableData['url'])) {
                if ($table && empty($table['url'])) {
                    // URL doesn't exist, generate SEO-friendly URL
                    $tableData['url'] = $this->generateTableUrl($tableId, true);
                    $tableData['qr_code_url'] = $this->generateQRCodeUrl($tableData['url']);
                } elseif ($table && !empty($table['url']) && (strpos($table['url'], '/t/') !== false || $needsDomainUpdate)) {
                    // URL exists but is old format (/t/tableId) or contains old domain, update to SEO-friendly format
                    $tableData['url'] = $this->generateTableUrl($tableId, true);
                    $tableData['qr_code_url'] = $this->generateQRCodeUrl($tableData['url']);
                }
            } elseif (!empty($tableData['url']) && (strpos($tableData['url'], '/t/') !== false || $needsDomainUpdate)) {
                // If explicitly setting old format URL or old domain, convert to SEO-friendly with current domain
                if ($needsDomainUpdate) {
                    // Replace old domain with current domain
                    try {
                        $baseUrlService = \App\Services\BaseUrlService::class;
                        foreach ($oldDomains as $oldDomain) {
                            if (strpos($tableData['url'], $oldDomain) !== false) {
                                $currentDomain = \App\Services\BaseUrlService::getDomain();
                                $tableData['url'] = str_replace($oldDomain, $currentDomain, $tableData['url']);
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // If service not available, regenerate URL
                        $tableData['url'] = $this->generateTableUrl($tableId, true);
                    }
                } else {
                    // Old format, convert to SEO-friendly
                    $tableData['url'] = $this->generateTableUrl($tableId, true);
                }
                $tableData['qr_code_url'] = $this->generateQRCodeUrl($tableData['url']);
            }
            
            // Also check QR code URL for old domain
            if (!empty($tableData['qr_code_url'])) {
                foreach ($oldDomains as $oldDomain) {
                    if (strpos($tableData['qr_code_url'], $oldDomain) !== false) {
                        // QR code contains old domain, regenerate it
                        if (empty($tableData['url'])) {
                            $tableData['url'] = $this->generateTableUrl($tableId, true);
                        }
                        $tableData['qr_code_url'] = $this->generateQRCodeUrl($tableData['url']);
                        break;
                    }
                }
            }
        }
        
        $result = $this->repository->update($tableId, $tableData);
        
        // Invalidate cache after update
        if ($result) {
            $this->invalidateTablesCache();
        }
        
        return $result;
    }
    
    /**
     * Delete table
     * @param string $tableId Table ID
     * @return bool Success
     */
    public function deleteTable(string $tableId): bool {
        $result = $this->repository->delete($tableId);
        
        // Invalidate cache after deletion
        if ($result) {
            $this->invalidateTablesCache();
        }
        
        return $result;
    }
    
    /**
     * Invalidate tables cache
     * @return void
     */
    private function invalidateTablesCache(): void {
        try {
            $tenantId = \App\Core\TenantContext::getId() ?? 'guest';
            $cacheKey = "tables:all:{$tenantId}";
            $cache = \App\Core\DependencyFactory::getCacheService();
            $cache->delete($cacheKey);
        } catch (\Exception $e) {
            // Ignore cache errors
        }
    }
    
    /**
     * Get all zones from database (not hardcoded)
     * @return array Zone names
     */
    public function getAllZones(): array {
        try {
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $zones = $zoneService->getAllZones();
            return array_column($zones, 'name');
        } catch (\Exception $e) {
            error_log("TableService::getAllZones error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get occupied count
     * @return int Number of occupied tables
     */
    public function getOccupiedCount(): int {
        // Get all tables and filter by OCCUPIED status
        // This ensures tenant isolation through getAllTables()
        $allTables = $this->getAllTables();
        $occupiedCount = 0;
        
        foreach ($allTables as $table) {
            if (($table['status'] ?? 'FREE') === 'OCCUPIED') {
                $occupiedCount++;
            }
        }
        
        return $occupiedCount;
    }
    
    /**
     * Get active tables (occupied or payment pending)
     * @return array Active tables
     */
    public function getActiveTables(): array {
        $db = $this->repository->getDbConnection();
        $tableName = $this->repository->getTableName();
        
        // CRITICAL: Add tenant isolation
        $tenantId = \App\Core\TenantContext::getId();
        if (!$tenantId) {
            return [];
        }
        
        // Smart column detection for tenant_id vs business_id
        $tenantColumn = $this->repository->getTenantColumnName();
        
        // Order by zone (if exists) and name
        $sql = "SELECT * FROM {$tableName} 
                WHERE status IN ('OCCUPIED', 'PAYMENT_PENDING') 
                AND {$tenantColumn} = :tenant_id
                ORDER BY zone, name";
        $stmt = $db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate QR code for a table
     * Always uses dynamic BASE_URL to ensure QR codes are up-to-date
     * @param string $tableId Table ID
     * @return string|false QR code URL or false on failure
     */
    public function generateQRCodeForTable(string $tableId): string|false {
        $table = $this->getTableById($tableId);
        if (!$table) {
            return false;
        }
        
        // Always generate fresh SEO-friendly URL using current BASE_URL (dynamic)
        $tableUrl = $this->generateTableUrl($tableId, true);
        $qrCodeUrl = $this->generateQRCodeUrl($tableUrl);
        
        // Update table with fresh URL and QR code
        $this->updateTable($tableId, [
            'url' => $tableUrl,
            'qr_code_url' => $qrCodeUrl
        ]);
        
        return $qrCodeUrl;
    }
    

    /**
     * Get available tables for a specific date and time
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param int $minCapacity Minimum capacity required
     * @return array Available tables
     */
    public function getAvailableTablesForDateTime(string $date, string $time, int $minCapacity = 0): array {
        // Get reservation service to check conflicts
        $reservationService = \App\Core\DependencyFactory::getReservationService();
        
        // Get all tables
        $allTables = $this->getAllTables();
        
        // Get reserved table IDs for this date/time
        $reservedTableIds = $reservationService->getReservedTableIds($date, $time);
        
        // Filter available tables
        $availableTables = [];
        foreach ($allTables as $table) {
            $tableId = $table['table_id'] ?? '';
            $capacity = intval($table['capacity'] ?? 0);
            $status = $table['status'] ?? 'FREE';
            
            // Skip if table is reserved for this date/time
            if (in_array($tableId, $reservedTableIds)) {
                continue;
            }
            
            // Skip if table is occupied or payment pending (unless it's the same date/time)
            if (in_array($status, ['OCCUPIED', 'PAYMENT_PENDING'])) {
                continue;
            }
            
            // Check capacity requirement
            if ($minCapacity > 0 && $capacity < $minCapacity) {
                continue;
            }
            
            $availableTables[] = $table;
        }
        
        return $availableTables;
    }

    /**
     * Get tables grouped by zone
     * @return array Tables grouped by zone
     */
    public function getTablesGroupedByZone(): array {
        $allTables = $this->getAllTables();
        $grouped = [];
        
        // PERFORMANCE OPTIMIZATION: Load all zones once instead of N+1 queries
        $zonesById = [];
        try {
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $allZones = $zoneService->getAllZones();
            foreach ($allZones as $zone) {
                $zoneId = $zone['zone_id'] ?? null;
                if ($zoneId) {
                    $zonesById[$zoneId] = $zone;
                }
            }
        } catch (\Exception $e) {
            // If zone service fails, continue without zone cache
        }
        
        foreach ($allTables as $table) {
            $zoneId = $table['zone_id'] ?? null;
            $zoneName = 'Diğer';
            
            if ($zoneId && isset($zonesById[$zoneId])) {
                // PERFORMANCE OPTIMIZATION: Use cached zone data instead of individual query
                $zone = $zonesById[$zoneId];
                $zoneName = $zone['name'] ?? 'Diğer';
            } elseif ($zoneId) {
                // Fallback: Try individual query only if not in cache (shouldn't happen normally)
                try {
                    $zoneService = \App\Core\DependencyFactory::getZoneService();
                    $zone = $zoneService->getZoneById($zoneId);
                    if ($zone) {
                        $zoneName = $zone['name'] ?? 'Diğer';
                    }
                } catch (\Exception $e) {
                    // If zone service fails, fallback to zone name
                    $zoneName = $table['zone'] ?? 'Diğer';
                }
            } else {
                // Fallback to zone name for backward compatibility
                $zoneName = $table['zone'] ?? 'Diğer';
            }
            
            if (!isset($grouped[$zoneName])) {
                $grouped[$zoneName] = [];
            }
            $grouped[$zoneName][] = $table;
        }
        
        // Sort tables within each zone by name (natural sort: bahçe1, bahçe2, bahçe10)
        foreach ($grouped as $zone => $tables) {
            usort($grouped[$zone], function($a, $b) {
                $nameA = $a['name'] ?? '';
                $nameB = $b['name'] ?? '';
                // Natural sort: extract numbers and compare
                // Use MySQL's natural sort equivalent: name + 0, name
                // For PHP, we'll use a simple natural sort
                return strnatcasecmp($nameA, $nameB);
            });
        }
        
        return $grouped;
    }
    
    /**
     * Get zones with tables and statistics
     * @return array Zones with table counts and statistics
     */
    public function getZonesWithTables(): array {
        $zones = [];
        $zoneService = \App\Core\DependencyFactory::getZoneService();
        $allZones = $zoneService->getAllZones();
        $tablesGrouped = $this->getTablesGroupedByZone();
        
        foreach ($allZones as $zone) {
            $zoneName = $zone['name'] ?? '';
            $tables = $tablesGrouped[$zoneName] ?? [];
            
            $occupiedCount = 0;
            $freeCount = 0;
            $paymentPendingCount = 0;
            
            foreach ($tables as $table) {
                $status = $table['status'] ?? 'FREE';
                if ($status === 'OCCUPIED') {
                    $occupiedCount++;
                } elseif ($status === 'PAYMENT_PENDING') {
                    $paymentPendingCount++;
                } else {
                    $freeCount++;
                }
            }
            
            $zones[] = [
                'zone_id' => $zone['zone_id'] ?? '',
                'name' => $zoneName,
                'description' => $zone['description'] ?? '',
                'floor' => $zone['floor'] ?? '',
                'tables' => $tables,
                'total_count' => count($tables),
                'occupied_count' => $occupiedCount,
                'free_count' => $freeCount,
                'payment_pending_count' => $paymentPendingCount
            ];
        }
        
        // Add zones that have tables but are not in zone table (backward compatibility)
        foreach ($tablesGrouped as $zoneName => $tables) {
            $found = false;
            foreach ($zones as $zone) {
                if ($zone['name'] === $zoneName) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $occupiedCount = 0;
                $freeCount = 0;
                $paymentPendingCount = 0;
                
                foreach ($tables as $table) {
                    $status = $table['status'] ?? 'FREE';
                    if ($status === 'OCCUPIED') {
                        $occupiedCount++;
                    } elseif ($status === 'PAYMENT_PENDING') {
                        $paymentPendingCount++;
                    } else {
                        $freeCount++;
                    }
                }
                
                $zones[] = [
                    'zone_id' => null,
                    'name' => $zoneName,
                    'description' => '',
                    'floor' => '',
                    'tables' => $tables,
                    'total_count' => count($tables),
                    'occupied_count' => $occupiedCount,
                    'free_count' => $freeCount,
                    'payment_pending_count' => $paymentPendingCount
                ];
            }
        }
        
        return $zones;
    }
}
