<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Order Repository
 * Handles database operations for orders
 * 
 * @package App\Repositories
 */
class OrderRepository extends BaseRepository {
    protected $table = 'orders';
    protected $primaryKey = 'order_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Check if a column exists in a table
     * @param string $columnName Column name to check
     * @param string $tableName Table name (default: 'tables')
     * @return bool True if column exists, false otherwise
     */
    private function hasTableColumn(string $columnName, string $tableName = 'tables'): bool {
        return \App\Core\DbSchema::hasColumn($tableName, $columnName);
    }

    /**
     * Get all orders with proper ordering and zone/table information
     * @param array $criteria Optional criteria for filtering
     * @return array All orders sorted by created_at DESC with zone and table info
     */
    public function findAll(array $criteria = []): array {
        // Check if columns exist for backward compatibility
        $hasZoneId = $this->hasTableColumn('zone_id');
        $hasFloor = $this->hasTableColumn('floor');
        $hasSection = $this->hasTableColumn('section');
        $hasZoneFloor = $this->hasTableColumn('floor', 'zones');
        
        // Build SELECT clause based on column existence
        if ($hasZoneId) {
            $zoneField = 't.zone_id';
            $zoneJoin = 'LEFT JOIN zones z ON t.zone_id = z.zone_id';
        } else {
            $zoneField = 't.zone as zone_id';
            $zoneJoin = 'LEFT JOIN zones z ON t.zone = z.name';
        }
        
        // Build table fields conditionally
        $tableFields = ['t.name as table_name_from_db', $zoneField];
        if ($hasFloor) {
            $tableFields[] = 't.floor as table_floor';
        } else {
            $tableFields[] = 'NULL as table_floor';
        }
        if ($hasSection) {
            $tableFields[] = 't.section as table_section';
        } else {
            $tableFields[] = 'NULL as table_section';
        }
        
        // Build zone fields conditionally
        $zoneFields = ['z.name as zone_name'];
        if ($hasZoneFloor) {
            $zoneFields[] = 'z.floor as zone_floor';
        } else {
            $zoneFields[] = 'NULL as zone_floor';
        }
        $zoneFields[] = 'z.description as zone_description';
        
        $sql = "SELECT 
                    o.*,
                    " . implode(",\n                    ", $tableFields) . ",
                    " . implode(",\n                    ", $zoneFields) . "
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.table_id
                {$zoneJoin}";
        $params = [];
        
        // Get tenant_id for filtering
        // Super Admin için tenant_id yoksa tüm siparişleri göster
        $tenantId = $this->getTenantId();
        if ($tenantId && !isset($criteria['tenant_id'])) {
            // Check if user is Super Admin - if so, don't filter by tenant_id
            $isSuperAdmin = false;
            if (class_exists('\App\Core\SessionManager')) {
                $isSuperAdmin = \App\Core\SessionManager::get('is_super_admin') === true;
                if (!$isSuperAdmin) {
                    $role = \App\Core\SessionManager::get('role') ?? '';
                    $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
                    $isSuperAdmin = ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'QODMIN');
                }
            }
            
            // Only apply tenant filter if not Super Admin
            if (!$isSuperAdmin) {
                $criteria['tenant_id'] = $tenantId;
            }
        }
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $field => $value) {
                // Sanitize field names to prevent SQL injection
                if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
                    // Check if field is from orders table or joined tables
                    if (in_array($field, ['order_id', 'table_id', 'status', 'created_at', 'total_amount', 'tenant_id'])) {
                        $conditions[] = "o.{$field} = :{$field}";
                    } else {
                        $conditions[] = "{$field} = :{$field}";
                    }
                    $params[$field] = $value;
                }
            }
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
        } else {
            // If no criteria but tenant_id exists and not Super Admin, add tenant filter
            $tenantId = $this->getTenantId();
            if ($tenantId) {
                // Check if user is Super Admin - if so, don't filter by tenant_id
                $isSuperAdmin = false;
                if (class_exists('\App\Core\SessionManager')) {
                    $isSuperAdmin = \App\Core\SessionManager::get('is_super_admin') === true;
                    if (!$isSuperAdmin) {
                        $role = \App\Core\SessionManager::get('role') ?? '';
                        $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
                        $isSuperAdmin = ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'QODMIN');
                    }
                }
                
                // Only apply tenant filter if not Super Admin
                if (!$isSuperAdmin) {
                    // Use getTenantFilter() which supports both business_id and tenant_id
                    $filter = $this->getTenantFilter();
                    if (!empty($filter['where'])) {
                        $sql .= " WHERE " . $this->tenantWhereForAlias('o', $filter['where']);
                        $params = array_merge($params, $filter['params']);
                    }
                }
            }
        }
        
        // Build ORDER BY clause conditionally
        $orderByParts = [];
        if ($hasZoneFloor) {
            $orderByParts[] = 'z.floor ASC';
        }
        $orderByParts[] = 'z.name ASC';
        if ($hasFloor) {
            $orderByParts[] = 't.floor ASC';
        }
        if ($hasSection) {
            $orderByParts[] = 't.section ASC';
        }
        $orderByParts[] = 't.name ASC';
        $orderByParts[] = 'o.created_at DESC';
        
        $sql .= " ORDER BY " . implode(', ', $orderByParts);
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get orders by table ID
     * @param string $tableId Table ID
     * @return array Orders
     */
    public function getByTableId(string $tableId): array {
        // CRITICAL: Get tenant_id from table for tenant isolation
        // This ensures QR menu orders are filtered by table's tenant_id
        try {
            $tableStmt = $this->db->prepare("SELECT tenant_id FROM tables WHERE table_id = :table_id LIMIT 1");
            $tableStmt->execute(['table_id' => $tableId]);
            $table = $tableStmt->fetch(\PDO::FETCH_ASSOC);
            
            $tableTenantId = $table['tenant_id'] ?? null;
        } catch (\Exception $e) {
            $tableTenantId = null;
        }
        
        $params = ['table_id' => $tableId];
        $whereConditions = ['o.table_id = :table_id'];
        
        // If table has tenant_id, filter orders by it
        if ($tableTenantId) {
            $whereConditions[] = "o.tenant_id = :table_tenant_id";
            $params['table_tenant_id'] = $tableTenantId;
        } else {
            // Fallback: Use session/context tenant filter if table doesn't have tenant_id
            $filter = $this->getTenantFilter();
            if (!empty($filter['where'])) {
                $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
                $params = array_merge($params, $filter['params']);
            }
        }
        
        $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at DESC";
        
        // CRITICAL: Bypass cache for real-time order updates
        // Orders need to be fetched fresh every time for customer QR menu
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get active orders by table ID (excludes SERVED and CANCELLED)
     * @param string $tableId Table ID
     * @return array Active orders
     */
    public function getActiveOrdersByTable(string $tableId): array {
        // Use constants if available, otherwise fallback to defaults
        $statusServed = defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED';
        $statusCancelled = defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED';
        
        // CRITICAL: Get tenant_id from table for tenant isolation
        try {
            $tableStmt = $this->db->prepare("SELECT tenant_id FROM tables WHERE table_id = :table_id LIMIT 1");
            $tableStmt->execute(['table_id' => $tableId]);
            $table = $tableStmt->fetch(\PDO::FETCH_ASSOC);
            
            $tableTenantId = $table['tenant_id'] ?? null;
        } catch (\Exception $e) {
            $tableTenantId = null;
        }
        
        $params = [
            'table_id' => $tableId,
            'served' => $statusServed,
            'cancelled' => $statusCancelled
        ];
        $whereConditions = [
            'o.table_id = :table_id',
            'o.status != :served',
            'o.status != :cancelled'
        ];
        
        // If table has tenant_id, filter orders by it
        if ($tableTenantId) {
            $whereConditions[] = "o.tenant_id = :table_tenant_id";
            $params['table_tenant_id'] = $tableTenantId;
        } else {
            // Fallback: Use session/context tenant filter if table doesn't have tenant_id
            $filter = $this->getTenantFilter();
            if (!empty($filter['where'])) {
                $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
                $params = array_merge($params, $filter['params']);
            }
        }
        
        $sql = "SELECT o.* FROM {$this->table} o 
                WHERE " . implode(" AND ", $whereConditions) . "
                ORDER BY o.created_at DESC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get active orders by multiple table IDs (batch operation for performance)
     * Excludes SERVED and CANCELLED orders
     * @param array $tableIds Array of table IDs
     * @return array Orders grouped by table_id
     */
    public function getActiveOrdersByTableIds(array $tableIds): array {
        if (empty($tableIds)) {
            return [];
        }
        
        $statusServed = defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED';
        $statusCancelled = defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED';
        
        // Build placeholders for IN clause
        $placeholders = [];
        $params = [
            'served' => $statusServed,
            'cancelled' => $statusCancelled
        ];
        
        foreach ($tableIds as $index => $tableId) {
            $key = "table_id_{$index}";
            $placeholders[] = ":{$key}";
            $params[$key] = $tableId;
        }
        
        $whereConditions = [
            "o.table_id IN (" . implode(", ", $placeholders) . ")",
            "o.status != :served",
            "o.status != :cancelled"
        ];
        
        // Add tenant filtering
        $filter = $this->getTenantFilter();
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT o.* FROM {$this->table} o 
                WHERE " . implode(" AND ", $whereConditions) . "
                ORDER BY o.table_id ASC, o.created_at DESC";
        
        $orders = $this->fetchAll($sql, $params);
        
        // Group orders by table_id
        $grouped = [];
        foreach ($orders as $order) {
            $tableId = $order['table_id'] ?? '';
            if (!isset($grouped[$tableId])) {
                $grouped[$tableId] = [];
            }
            $grouped[$tableId][] = $order;
        }
        
        return $grouped;
    }

    /**
     * Get recent orders with limit (performance optimized)
     * @param int $limit Number of recent orders to return
     * @return array Recent orders sorted by created_at DESC
     */
    public function getRecent(int $limit = 10, bool $todayOnly = true, bool $excludeCancelled = true): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['limit' => $limit];
        $whereConditions = [];

        // Today filter: use the business calendar day (overnight businesses supported).
        if ($todayOnly) {
            try {
                $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                $range = $settingsService->getBusinessDateRange();
                if (!empty($range['start_datetime']) && !empty($range['end_datetime'])) {
                    $whereConditions[] = 'o.created_at >= :recent_start';
                    $whereConditions[] = 'o.created_at <= :recent_end';
                    $params['recent_start'] = $range['start_datetime'];
                    $params['recent_end'] = $range['end_datetime'];
                } else {
                    $whereConditions[] = 'DATE(o.created_at) = CURDATE()';
                }
            } catch (\Exception $e) {
                $whereConditions[] = 'DATE(o.created_at) = CURDATE()';
            }
        }

        // Drop cancelled orders so they never surface on the dashboard
        if ($excludeCancelled) {
            $cancelledStatus = defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED';
            $whereConditions[] = 'o.status != :recent_cancelled';
            $params['recent_cancelled'] = $cancelledStatus;
        }

        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        // Sub-selects: enrich rows with first item name, item count, and staff name
        // so the view can render "Müşteri" / "Ürün" without N+1 queries.
        $sql = "SELECT o.*,
                (SELECT mi.name FROM order_items oi
                 JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                 WHERE oi.order_id = o.order_id
                 ORDER BY oi.order_item_id ASC LIMIT 1) AS first_item_name,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count,
                (SELECT u.name FROM users u WHERE u.user_id = o.created_by LIMIT 1) AS staff_name
                FROM {$this->table} o
                {$whereClause}
                ORDER BY o.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === 'limit') {
                $stmt->bindValue(':limit', $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $key, $value);
            }
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get orders by status
     * @param string $status Order status
     * @return array Orders
     */
    public function getByStatus(string $status): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['status' => $status];
        $whereConditions = ['o.status = :status'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at DESC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get delivery orders by status (only orders where is_delivery = 1)
     * @param string $status Order status
     * @return array Delivery orders
     */
    public function getDeliveryOrdersByStatus(string $status): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = ['status' => $status];
        $whereConditions = [];
        
        $hasIsDelivery = $this->hasTableColumn('is_delivery', 'orders');
        $hasDeliveryAddress = $this->hasTableColumn('delivery_address', 'orders');
        
        try {
            if ($hasIsDelivery) {
                // Primary method: Use is_delivery column
                $whereConditions = ['o.status = :status', "(o.is_delivery = 1 OR o.is_delivery = '1' OR o.is_delivery = true)"];
                if (!empty($filter['where'])) {
                    $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
                    $params = array_merge($params, $filter['params']);
                }
                $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at ASC";
                $result = $this->fetchAll($sql, $params);
                if (!empty($result)) {
                    return $result;
                }
            }
            
            // Fallback: If is_delivery column doesn't exist or returns empty, use delivery_address
            if ($hasDeliveryAddress) {
                $whereConditions = ['o.status = :status', "o.delivery_address IS NOT NULL AND o.delivery_address != ''"];
                if (!empty($filter['where'])) {
                    $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
                    $params = array_merge($params, $filter['params']);
                }
                $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at ASC";
                $result = $this->fetchAll($sql, $params);
                if (!empty($result)) {
                    return $result;
                }
            }
            
            // Last fallback: If no delivery-specific columns, check if table_id is NULL (delivery orders usually don't have table_id)
            $whereConditions = ['o.status = :status', "(o.table_id IS NULL OR o.table_id = '')"];
            if (!empty($filter['where'])) {
                $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
                $params = array_merge($params, $filter['params']);
            }
            $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at ASC";
            return $this->fetchAll($sql, $params) ?: [];
            
        } catch (\Exception $e) {
            error_log('OrderRepository::getDeliveryOrdersByStatus error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return [];
        }
    }
    
    /**
     * Get last order by prefix (for order ID generation)
     * @param string $prefix Order ID prefix (e.g., 'cd')
     * @return array|null Last order with this prefix or null
     */
    public function getLastOrderByPrefix(string $prefix): ?array {
        $prefixLen = strlen($prefix) + 1;
        $sql = "SELECT * FROM {$this->table} 
                WHERE order_id LIKE :prefix_pattern 
                AND order_id REGEXP :regex_pattern
                ORDER BY CAST(SUBSTRING(order_id, {$prefixLen}) AS UNSIGNED) DESC 
                LIMIT 1";
        $prefixPattern = $prefix . '%';
        $regexPattern = '^' . preg_quote($prefix) . '[0-9]+$';
        $result = $this->fetchAll($sql, ['prefix_pattern' => $prefixPattern, 'regex_pattern' => $regexPattern]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get active orders (PENDING, PREPARING, READY)
     * @return array Active orders
     */
    public function getActiveOrders(): array {
        // Use constants if available, otherwise fallback to defaults
        $statusPending = defined('ORDER_STATUS_PENDING') ? ORDER_STATUS_PENDING : 'PENDING';
        $statusPreparing = defined('ORDER_STATUS_PREPARING') ? ORDER_STATUS_PREPARING : 'PREPARING';
        $statusReady = defined('ORDER_STATUS_READY') ? ORDER_STATUS_READY : 'READY';
        
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = [
            'pending' => $statusPending,
            'preparing' => $statusPreparing,
            'ready' => $statusReady
        ];
        $whereConditions = ['o.status IN (:pending, :preparing, :ready)'];
        $whereConditions[] = 'o.created_at > DATE_SUB(NOW(), INTERVAL 8 HOUR)';
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get tenant ID from session or TenantContext
     * @return string|null
     */
    private function getTenantId(): ?string {
        // Try session first
        $tenantId = \App\Core\TenantResolver::resolve();
        
        // If not in session, try TenantContext (for API requests)
        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not available, continue without tenant_id
            }
        }
        
        return $tenantId;
    }
    
    // addTenantToWhere() method removed - using BaseRepository's smart tenant filter instead
    // BaseRepository's version supports both business_id and tenant_id automatically

    /**
     * Get orders by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Orders
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        $whereConditions = ['DATE(o.created_at) BETWEEN :start_date AND :end_date'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at DESC";
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get orders by datetime range (for overnight working hours support)
     * @param string $startDatetime Start datetime (Y-m-d H:i:s)
     * @param string $endDatetime End datetime (Y-m-d H:i:s)
     * @return array Orders
     */
    public function getByDatetimeRange(string $startDatetime, string $endDatetime): array {
        $filter = $this->getTenantFilter();
        $params = [
            'start_dt' => $startDatetime,
            'end_dt' => $endDatetime
        ];
        $whereConditions = ['o.created_at >= :start_dt', 'o.created_at <= :end_dt'];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT o.* FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions) . " ORDER BY o.created_at DESC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get total amount by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return float Total amount
     */
    public function getTotalAmountByDateRange(string $startDate, string $endDate): float {
        // Use constant for cancelled status (dynamic, not hardcoded)
        $statusCancelled = defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED';
        $statusServed = defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED';
        
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cancelled' => $statusCancelled,
            'served' => $statusServed
        ];
        $whereConditions = [
            'DATE(o.created_at) BETWEEN :start_date AND :end_date',
            'o.status != :cancelled',
            '(o.is_paid = 1 OR o.status = :served)'
        ];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT SUM(o.total_amount) as total FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }
    
    /**
     * Get total amount by datetime range (for overnight working hours support)
     * Uses exact datetime comparison instead of DATE() for overnight businesses
     * @param string $startDatetime Start datetime (Y-m-d H:i:s)
     * @param string $endDatetime End datetime (Y-m-d H:i:s)
     * @return float Total amount
     */
    public function getTotalAmountByDatetimeRange(string $startDatetime, string $endDatetime): float {
        $statusCancelled = defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED';
        $statusServed = defined('ORDER_STATUS_SERVED') ? ORDER_STATUS_SERVED : 'SERVED';
        
        $filter = $this->getTenantFilter();
        $params = [
            'start_dt' => $startDatetime,
            'end_dt' => $endDatetime,
            'cancelled' => $statusCancelled,
            'served' => $statusServed
        ];
        $whereConditions = [
            'o.created_at >= :start_dt',
            'o.created_at <= :end_dt',
            'o.status != :cancelled',
            '(o.is_paid = 1 OR o.status = :served)'
        ];
        
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        $sql = "SELECT SUM(o.total_amount) as total FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get ACTUAL (collected) revenue by datetime range — paid orders only.
     *
     * "Actual" = money that has actually been collected, i.e. orders flagged
     * is_paid = 1. Unpaid-but-open orders are intentionally excluded; their
     * value belongs in the estimated figure below.
     *
     * @param string $startDatetime Start datetime (Y-m-d H:i:s)
     * @param string $endDatetime End datetime (Y-m-d H:i:s)
     * @return float Collected revenue
     */
    public function getActualRevenueByDatetimeRange(string $startDatetime, string $endDatetime): float {
        $filter = $this->getTenantFilter();
        $params = [
            'start_dt' => $startDatetime,
            'end_dt' => $endDatetime
        ];
        $whereConditions = [
            'o.created_at >= :start_dt',
            'o.created_at <= :end_dt',
            'o.is_paid = 1'
        ];

        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }

        $sql = "SELECT SUM(o.total_amount) as total FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }

    /**
     * Get ESTIMATED (projected) revenue by datetime range — all non-cancelled
     * orders, paid or not.
     *
     * "Estimated" = the full value of every live order (collected + still
     * awaiting payment). Always >= the actual/collected figure above.
     *
     * @param string $startDatetime Start datetime (Y-m-d H:i:s)
     * @param string $endDatetime End datetime (Y-m-d H:i:s)
     * @return float Estimated revenue (includes pending payments)
     */
    public function getEstimatedRevenueByDatetimeRange(string $startDatetime, string $endDatetime): float {
        $statusCancelled = defined('ORDER_STATUS_CANCELLED') ? ORDER_STATUS_CANCELLED : 'CANCELLED';

        $filter = $this->getTenantFilter();
        $params = [
            'start_dt' => $startDatetime,
            'end_dt' => $endDatetime,
            'cancelled' => $statusCancelled
        ];
        $whereConditions = [
            'o.created_at >= :start_dt',
            'o.created_at <= :end_dt',
            'o.status != :cancelled'
        ];

        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }

        $sql = "SELECT SUM(o.total_amount) as total FROM {$this->table} o WHERE " . implode(" AND ", $whereConditions);
        $result = $this->fetchOne($sql, $params);
        return (float)($result['total'] ?? 0);
    }

    /**
     * Load order items relationship (for eager loading)
     * @param array $orders Orders data
     * @return array Orders with items loaded
     */
    protected function loadRelationForMany(array $orders, string $relation): array {
        if ($relation === 'items') {
            return $this->loadItemsForOrders($orders);
        }
        return parent::loadRelationForMany($orders, $relation);
    }
    
    /**
     * Load items for multiple orders (batch loading to prevent N+1)
     * @param array $orders Orders data
     * @return array Orders with items
     */
    private function loadItemsForOrders(array $orders): array {
        if (empty($orders)) {
            return $orders;
        }
        
        $orderIds = array_column($orders, 'order_id');
        $ordersWithItems = $this->getOrdersWithItems($orderIds);
        
        // Merge items into orders
        $ordersById = [];
        foreach ($orders as $order) {
            $ordersById[$order['order_id']] = $order;
            $ordersById[$order['order_id']]['items'] = [];
        }
        
        foreach ($ordersWithItems as $order) {
            if (isset($ordersById[$order['order_id']])) {
                if (!isset($ordersById[$order['order_id']]['items'])) {
                    $ordersById[$order['order_id']]['items'] = [];
                }
                if (isset($order['order_item_id'])) {
                    $ordersById[$order['order_id']]['items'][] = [
                        'order_item_id' => $order['order_item_id'],
                        'menu_item_id' => $order['menu_item_id'],
                        'quantity' => $order['item_quantity'],
                        'price' => $order['item_price'],
                        'note' => $order['item_note'],
                        'item_name' => $order['item_name'],
                        'item_image' => $order['item_image'],
                        'preparation_time' => $order['preparation_time'] ?? null,
                        'cooking_time' => $order['cooking_time'] ?? null,
                        'serve_time' => $order['serve_time'] ?? null
                    ];
                }
            }
        }
        
        return array_values($ordersById);
    }
    
    /**
     * Get orders with their items in a single query (optimized to prevent N+1)
     * @param array $orderIds Array of order IDs
     * @return array Orders with items grouped
     */
    public function getOrdersWithItems(array $orderIds): array {
        if (empty($orderIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        // Schema probe via central DbSchema cache — shared across the request.
        $columnCache = [
            'excluded_ingredients' => \App\Core\DbSchema::hasColumn('order_items', 'excluded_ingredients'),
            'selected_extras'      => \App\Core\DbSchema::hasColumn('order_items', 'selected_extras'),
            'preparation_time'     => \App\Core\DbSchema::hasColumn('menu_items', 'preparation_time'),
            'cooking_time'         => \App\Core\DbSchema::hasColumn('menu_items', 'cooking_time'),
            'serve_time'           => \App\Core\DbSchema::hasColumn('menu_items', 'serve_time'),
        ];

        $excludedIngredientsField = $columnCache['excluded_ingredients'] 
            ? "COALESCE(oi.excluded_ingredients, '[]') as excluded_ingredients" 
            : "'[]' as excluded_ingredients";
        $selectedExtrasField = $columnCache['selected_extras'] 
            ? "COALESCE(oi.selected_extras, '[]') as selected_extras" 
            : "'[]' as selected_extras";
        
        // Build time fields conditionally
        $timeFields = '';
        if ($columnCache['preparation_time'] || $columnCache['cooking_time'] || $columnCache['serve_time']) {
            $timeFieldsArray = [];
            if ($columnCache['preparation_time']) {
                $timeFieldsArray[] = 'mi.preparation_time';
            }
            if ($columnCache['cooking_time']) {
                $timeFieldsArray[] = 'mi.cooking_time';
            }
            if ($columnCache['serve_time']) {
                $timeFieldsArray[] = 'mi.serve_time';
            }
            if (!empty($timeFieldsArray)) {
                $timeFields = ',' . implode(',', $timeFieldsArray);
            }
        }
        
        $sql = "SELECT 
                    o.*,
                    oi.order_item_id,
                    oi.menu_item_id,
                    oi.variant_id,
                    oi.quantity as item_quantity,
                    oi.price as item_price,
                    oi.note as item_note,
                    COALESCE(oi.preparation_status, 'PENDING') as preparation_status,
                    {$excludedIngredientsField},
                    {$selectedExtrasField},
                    mi.name as item_name,
                    mi.image_url as item_image,
                    pv.name as variant_name,
                    pv.price_modifier as variant_price_modifier{$timeFields}
                FROM {$this->table} o
                LEFT JOIN order_items oi ON o.order_id = oi.order_id
                LEFT JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                LEFT JOIN product_variants pv ON oi.variant_id = pv.variant_id
                WHERE o.order_id IN ({$placeholders})
                ORDER BY o.created_at ASC, oi.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($orderIds);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Group items by order
        $orders = [];
        foreach ($results as $row) {
            $orderId = $row['order_id'];
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'order_id' => $row['order_id'],
                    'table_id' => $row['table_id'],
                    'table_name' => $row['table_name'],
                    'status' => $row['status'],
                    'total_amount' => $row['total_amount'],
                    'customer_note' => $row['customer_note'],
                    'created_at' => $row['created_at'],
                    'items' => []
                ];
            }
            
            if ($row['order_item_id']) {
                $orders[$orderId]['items'][] = [
                    'order_item_id' => $row['order_item_id'],
                    'menu_item_id' => $row['menu_item_id'],
                    'variant_id' => $row['variant_id'] ?? null,
                    'variant_name' => $row['variant_name'] ?? null,
                    'variant_price_modifier' => $row['variant_price_modifier'] ?? null,
                    'quantity' => $row['item_quantity'],
                    'price' => $row['item_price'],
                    'note' => $row['item_note'],
                    'preparation_status' => $row['preparation_status'] ?? 'PENDING',
                    'name' => $row['item_name'],
                    'item_name' => $row['item_name'], // Keep for backward compatibility
                    'menu_item_name' => $row['item_name'], // Keep for backward compatibility
                    'item_image' => $row['item_image'],
                    'excluded_ingredients' => $row['excluded_ingredients'] ?? '[]',
                    'selected_extras' => $row['selected_extras'] ?? '[]'
                ];
            }
        }
        
        return array_values($orders);
    }

    /**
     * Get orders grouped by table sessions
     * Groups orders into customer sessions based on payment status
     * A session ends when an order has is_paid=1 or status='SERVED'
     * 
     * @param string $tableId Table ID (optional, if provided returns sessions for that table only)
     * @return array Orders grouped by table and sessions
     */
    public function getOrdersGroupedByTableSessions(?string $tableId = null): array {
        // Check if columns exist for backward compatibility
        $hasZoneId = $this->hasTableColumn('zone_id');
        $hasFloor = $this->hasTableColumn('floor');
        $hasSection = $this->hasTableColumn('section');
        $hasZoneFloor = $this->hasTableColumn('floor', 'zones');
        
        // Build SELECT clause based on column existence
        if ($hasZoneId) {
            $zoneField = 't.zone_id';
            $zoneJoin = 'LEFT JOIN zones z ON t.zone_id = z.zone_id';
        } else {
            $zoneField = 't.zone as zone_id';
            $zoneJoin = 'LEFT JOIN zones z ON t.zone = z.name';
        }
        
        // Build table fields conditionally
        $tableFields = ['t.name as table_name_from_db', $zoneField];
        if ($hasFloor) {
            $tableFields[] = 't.floor as table_floor';
        } else {
            $tableFields[] = 'NULL as table_floor';
        }
        if ($hasSection) {
            $tableFields[] = 't.section as table_section';
        } else {
            $tableFields[] = 'NULL as table_section';
        }
        
        // Build zone fields conditionally
        $zoneFields = ['z.name as zone_name'];
        if ($hasZoneFloor) {
            $zoneFields[] = 'z.floor as zone_floor';
        } else {
            $zoneFields[] = 'NULL as zone_floor';
        }
        $zoneFields[] = 'z.description as zone_description';
        
        $sql = "SELECT 
                    o.*,
                    " . implode(",\n                    ", $tableFields) . ",
                    " . implode(",\n                    ", $zoneFields) . "
                FROM {$this->table} o
                LEFT JOIN tables t ON o.table_id = t.table_id
                {$zoneJoin}";
        $params = [];
        
        $whereConditions = [];
        if ($tableId) {
            $whereConditions[] = "o.table_id = :table_id";
            $params['table_id'] = $tableId;
        }
        
        // CRITICAL: Add tenant filtering
        $filter = $this->getTenantFilter();
        if (!empty($filter['where'])) {
            $whereConditions[] = $this->tenantWhereForAlias('o', $filter['where']);
            $params = array_merge($params, $filter['params']);
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY o.table_id ASC, o.created_at ASC";
        
        $orders = $this->fetchAll($sql, $params);
        
        // Group orders by table and sessions
        $grouped = [];
        
        foreach ($orders as $order) {
            $tableIdKey = $order['table_id'] ?? 'unknown';
            
            // Initialize table group if not exists
            if (!isset($grouped[$tableIdKey])) {
                $grouped[$tableIdKey] = [
                    'table_id' => $order['table_id'],
                    'table_name' => $order['table_name'] ?? $order['table_name_from_db'] ?? 'Bilinmiyor',
                    'table_floor' => $order['table_floor'] ?? null,
                    'table_section' => $order['table_section'] ?? null,
                    'zone_id' => $order['zone_id'] ?? null,
                    'zone_name' => $order['zone_name'] ?? 'Bölgesiz',
                    'zone_floor' => $order['zone_floor'] ?? null,
                    'zone_description' => $order['zone_description'] ?? null,
                    'sessions' => [],
                    'total_sessions' => 0,
                    'total_orders' => 0,
                    'total_amount' => 0
                ];
            }
            
            // Determine if this order ends a session
            $isPaid = !empty($order['is_paid']) && $order['is_paid'] != '0';
            $isServed = !empty($order['status']) && strtoupper($order['status']) === 'SERVED';
            $endsSession = $isPaid || $isServed;
            
            // Get current sessions for this table
            $sessions = &$grouped[$tableIdKey]['sessions'];
            
            // Check if we need to start a new session
            $startNewSession = false;
            if (empty($sessions)) {
                $startNewSession = true;
            } else {
                $lastSession = end($sessions);
                $startNewSession = !empty($lastSession['is_ended']);
            }
            
            // If no sessions or last session is ended, start a new session
            if ($startNewSession) {
                $sessionId = count($sessions) + 1;
                $sessions[] = [
                    'session_id' => $tableIdKey . '_session_' . $sessionId,
                    'session_number' => $sessionId,
                    'start_time' => $order['created_at'],
                    'end_time' => null,
                    'is_ended' => false,
                    'orders' => [],
                    'total_amount' => 0,
                    'order_count' => 0
                ];
            }
            
            // Add order to current (last) session
            $currentSession = &$sessions[count($sessions) - 1];
            $currentSession['orders'][] = $order;
            $currentSession['total_amount'] += floatval($order['total_amount'] ?? 0);
            $currentSession['order_count']++;
            
            // If this order ends the session, mark it
            if ($endsSession) {
                $currentSession['end_time'] = $order['updated_at'] ?? $order['created_at'];
                $currentSession['is_ended'] = true;
                $currentSession['payment_status'] = $isPaid ? 'paid' : 'served';
            }
            
            // Update table totals
            $grouped[$tableIdKey]['total_orders']++;
            $grouped[$tableIdKey]['total_amount'] += floatval($order['total_amount'] ?? 0);
        }
        
        // Calculate total sessions and finalize session data
        foreach ($grouped as &$tableGroup) {
            $tableGroup['total_sessions'] = count($tableGroup['sessions']);
            
            // For sessions that aren't ended, set end_time to last order time
            foreach ($tableGroup['sessions'] as &$session) {
                if (!$session['is_ended'] && !empty($session['orders'])) {
                    $lastOrder = end($session['orders']);
                    $session['end_time'] = $lastOrder['updated_at'] ?? $lastOrder['created_at'];
                }
            }
        }
        
        return array_values($grouped);
    }

    /**
     * Get table order sessions for a specific table
     * 
     * @param string $tableId Table ID
     * @return array|null Table sessions data or null if table not found
     */
    public function getTableOrderSessions(string $tableId): ?array {
        $result = $this->getOrdersGroupedByTableSessions($tableId);
        return !empty($result) ? $result[0] : null;
    }
}
