<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\TenantResolver;

class ReportsRepository extends BaseRepository {
    protected $table = 'orders';
    protected $primaryKey = 'order_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    private function ordersTenantClause(string $alias = ''): array
    {
        $tenantId = TenantResolver::resolve();
        if (!$tenantId) {
            return ['sql' => 'AND 1=0', 'params' => []];
        }
        $col = $this->detectTenantColumn();
        if (!$col) {
            return ['sql' => 'AND 1=0', 'params' => []];
        }
        $prefix = $alias ? "{$alias}." : '';
        return [
            'sql'    => "AND {$prefix}{$col} = :_tenant_id",
            'params' => ['_tenant_id' => $tenantId],
        ];
    }

    public function getSalesReport(string $startDate, string $endDate, ?string $tableId = null): array {
        $t = $this->ordersTenantClause();
        $params = array_merge(['start_date' => $startDate, 'end_date' => $endDate], $t['params']);
        $extra = '';
        if ($tableId) {
            $extra = 'AND table_id = :table_id';
            $params['table_id'] = $tableId;
        }
        $sql = "SELECT
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value,
                    SUM(CASE WHEN status = 'SERVED' THEN 1 ELSE 0 END) as completed_orders,
                    MIN(created_at) as first_order_time,
                    MAX(created_at) as last_order_time
                FROM {$this->table}
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    {$t['sql']} {$extra}";
        return $this->fetchOne($sql, $params) ?: [
            'total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0,
            'completed_orders' => 0, 'first_order_time' => null, 'last_order_time' => null
        ];
    }

    public function getTableReport(string $tableId, string $startDate, string $endDate): array {
        $t = $this->ordersTenantClause('o');
        $params = array_merge([
            'table_id' => $tableId, 'start_date' => $startDate, 'end_date' => $endDate
        ], $t['params']);
        $sql = "SELECT
                    t.table_id, t.name as table_name, t.zone,
                    COUNT(DISTINCT o.order_id) as total_orders,
                    SUM(o.total_amount) as total_revenue,
                    AVG(o.total_amount) as avg_order_value,
                    SUM(CASE WHEN o.status = 'SERVED' THEN 1 ELSE 0 END) as completed_orders,
                    MIN(o.created_at) as first_order_time,
                    MAX(o.created_at) as last_order_time,
                    CASE WHEN MIN(o.created_at) IS NOT NULL AND MAX(o.created_at) IS NOT NULL
                         THEN TIMESTAMPDIFF(MINUTE, MIN(o.created_at), MAX(o.created_at)) ELSE 0
                    END as total_usage_minutes,
                    COUNT(DISTINCT DATE(o.created_at)) as active_days
                FROM tables t
                LEFT JOIN orders o ON t.table_id = o.table_id
                    AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                    AND o.status != 'CANCELLED'
                    AND (o.is_paid = 1 OR o.status = 'SERVED')
                    {$t['sql']}
                WHERE t.table_id = :table_id
                GROUP BY t.table_id, t.name, t.zone";
        return $this->fetchOne($sql, $params) ?: [
            'table_id' => $tableId, 'table_name' => '', 'zone' => '',
            'total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0,
            'completed_orders' => 0, 'first_order_time' => null, 'last_order_time' => null,
            'total_usage_minutes' => 0, 'active_days' => 0
        ];
    }

    public function getAllTablesReport(string $startDate, string $endDate): array {
        $t = $this->ordersTenantClause('o');
        $params = array_merge(['start_date' => $startDate, 'end_date' => $endDate], $t['params']);
        $sql = "SELECT
                    t.table_id, t.name as table_name, t.zone,
                    COUNT(DISTINCT o.order_id) as total_orders,
                    SUM(o.total_amount) as total_revenue,
                    AVG(o.total_amount) as avg_order_value,
                    SUM(CASE WHEN o.status = 'SERVED' THEN 1 ELSE 0 END) as completed_orders,
                    MIN(o.created_at) as first_order_time,
                    MAX(o.created_at) as last_order_time,
                    CASE WHEN MIN(o.created_at) IS NOT NULL AND MAX(o.created_at) IS NOT NULL
                         THEN TIMESTAMPDIFF(MINUTE, MIN(o.created_at), MAX(o.created_at)) ELSE 0
                    END as total_usage_minutes,
                    COUNT(DISTINCT DATE(o.created_at)) as active_days
                FROM tables t
                INNER JOIN orders o ON t.table_id = o.table_id
                    AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                    AND o.status != 'CANCELLED'
                    AND (o.is_paid = 1 OR o.status = 'SERVED')
                    {$t['sql']}
                GROUP BY t.table_id, t.name, t.zone
                HAVING COUNT(DISTINCT o.order_id) > 0
                ORDER BY total_revenue DESC, t.zone, t.name";
        return $this->fetchAll($sql, $params);
    }

    public function getEmployeePerformanceReport(string $startDate, string $endDate): array {
        $tenantId = TenantResolver::resolve();
        if (!$tenantId) {
            return [];
        }
        try {
            $constantsService = \App\Core\DependencyFactory::getConstantsService();
            $allRoleCodes = $constantsService->getRoleCodes();
            $roleCodes = array_values(array_filter($allRoleCodes, fn($r) => $r !== 'CUSTOMER'));
        } catch (\Exception $e) {
            $roleCodes = ['MANAGER', 'WAITER', 'CASHIER', 'ADMIN'];
        }
        $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
        $roleStmt = $this->db->prepare("SELECT role_id FROM roles WHERE role_code IN ({$placeholders}) AND is_active = 1");
        $roleStmt->execute($roleCodes);
        $roleIds = $roleStmt->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($roleIds)) {
            return [];
        }
        $rolePh = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = "SELECT
                    u.user_id, u.name,
                    COUNT(DISTINCT o.order_id) as orders_handled,
                    SUM(o.total_amount) as total_sales,
                    AVG(o.total_amount) as avg_order_value,
                    SUM(CASE WHEN o.status = 'SERVED' THEN 1 ELSE 0 END) as completed_orders,
                    MIN(o.created_at) as first_order_time,
                    MAX(o.created_at) as last_order_time
                FROM users u
                LEFT JOIN orders o ON u.user_id = o.created_by
                    AND DATE(o.created_at) BETWEEN ? AND ?
                    AND o.status != 'CANCELLED'
                    AND (o.is_paid = 1 OR o.status = 'SERVED')
                WHERE u.role_id IN ({$rolePh})
                    AND u.tenant_id = ?
                GROUP BY u.user_id, u.name
                HAVING orders_handled > 0
                ORDER BY total_sales DESC, orders_handled DESC";
        $params = array_merge([$startDate, $endDate], $roleIds, [$tenantId]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCustomerReport(string $startDate, string $endDate): array {
        $t = $this->ordersTenantClause();
        $params = array_merge(['start_date' => $startDate, 'end_date' => $endDate], $t['params']);
        $sql = "SELECT
                    COUNT(DISTINCT table_id) as unique_customers,
                    COUNT(*) as total_visits,
                    AVG(total_amount) as avg_spent,
                    SUM(total_amount) as total_revenue,
                    MIN(total_amount) as min_order_value,
                    MAX(total_amount) as max_order_value
                FROM {$this->table}
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    AND table_id IS NOT NULL
                    {$t['sql']}";
        return $this->fetchOne($sql, $params) ?: [
            'unique_customers' => 0, 'total_visits' => 0, 'avg_spent' => 0,
            'total_revenue' => 0, 'min_order_value' => 0, 'max_order_value' => 0
        ];
    }

    public function getCategoryRevenueReport(string $startDate, string $endDate): array {
        $t = $this->ordersTenantClause('o');
        $params = array_merge(['start_date' => $startDate, 'end_date' => $endDate], $t['params']);
        $sql = "SELECT
                    c.category_id, c.name as category_name,
                    COUNT(DISTINCT o.order_id) as order_count,
                    SUM(oi.price * oi.quantity) as revenue,
                    SUM(oi.quantity) as items_sold,
                    AVG(oi.price * oi.quantity) as avg_order_item_value
                FROM categories c
                LEFT JOIN menu_items mi ON c.category_id = mi.category_id
                LEFT JOIN order_items oi ON mi.menu_item_id = oi.menu_item_id
                LEFT JOIN orders o ON oi.order_id = o.order_id
                    AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                    AND o.status != 'CANCELLED'
                    AND (o.is_paid = 1 OR o.status = 'SERVED')
                    {$t['sql']}
                GROUP BY c.category_id, c.name
                HAVING revenue > 0 OR order_count > 0
                ORDER BY revenue DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getHourlySalesReport(string $startDate, string $endDate): array {
        $t = $this->ordersTenantClause();
        $params = array_merge(['start_date' => $startDate, 'end_date' => $endDate], $t['params']);
        $sql = "SELECT
                    HOUR(created_at) as hour,
                    COUNT(*) as order_count,
                    SUM(total_amount) as revenue,
                    AVG(total_amount) as avg_order_value
                FROM {$this->table}
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    {$t['sql']}
                GROUP BY HOUR(created_at)
                ORDER BY hour";
        return $this->fetchAll($sql, $params);
    }

    public function getDailyRevenueChart(string $startDate, string $endDate): array {
        $t = $this->ordersTenantClause();
        $params = array_merge(['start_date' => $startDate, 'end_date' => $endDate], $t['params']);
        $sql = "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as order_count,
                    SUM(total_amount) as revenue,
                    AVG(total_amount) as avg_order_value
                FROM {$this->table}
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                    {$t['sql']}
                GROUP BY DATE(created_at)
                ORDER BY date";
        return $this->fetchAll($sql, $params);
    }

    public function getTopSellingItems(string $startDate, string $endDate, int $limit = 10): array {
        $tenantId = TenantResolver::resolve();
        if (!$tenantId) {
            return [];
        }
        $col = $this->detectTenantColumn() ?? 'tenant_id';
        $sql = "SELECT
                    mi.menu_item_id, mi.name, mi.price,
                    c.name as category_name,
                    SUM(oi.quantity) as total_quantity,
                    COUNT(DISTINCT o.order_id) as order_count,
                    SUM(oi.price * oi.quantity) as revenue
                FROM menu_items mi
                LEFT JOIN categories c ON mi.category_id = c.category_id
                LEFT JOIN order_items oi ON mi.menu_item_id = oi.menu_item_id
                LEFT JOIN orders o ON oi.order_id = o.order_id
                    AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                    AND o.status != 'CANCELLED'
                    AND (o.is_paid = 1 OR o.status = 'SERVED')
                    AND o.{$col} = :_tenant_id
                GROUP BY mi.menu_item_id, mi.name, mi.price, c.name
                HAVING total_quantity > 0
                ORDER BY total_quantity DESC, revenue DESC
                LIMIT :limit";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':start_date', $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(':_tenant_id', $tenantId, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTableOrders(string $tableId, string $startDate, string $endDate): array {
        $t = $this->ordersTenantClause('o');
        $params = array_merge([
            'table_id' => $tableId, 'start_date' => $startDate, 'end_date' => $endDate
        ], $t['params']);
        $sql = "SELECT
                    o.order_id, o.table_id, o.table_name, o.status,
                    o.total_amount, o.created_at, o.updated_at,
                    o.customer_note, o.order_source, o.is_paid,
                    COUNT(oi.order_item_id) as item_count,
                    SUM(oi.quantity) as total_items
                FROM orders o
                LEFT JOIN order_items oi ON o.order_id = oi.order_id
                WHERE o.table_id = :table_id
                    AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                    AND o.status != 'CANCELLED'
                    {$t['sql']}
                GROUP BY o.order_id, o.table_id, o.table_name, o.status,
                         o.total_amount, o.created_at, o.updated_at,
                         o.customer_note, o.order_source, o.is_paid
                ORDER BY o.created_at DESC";
        return $this->fetchAll($sql, $params);
    }

    public function getTableOrderItems(string $orderId): array {
        $t = $this->ordersTenantClause('o');
        $params = array_merge(['order_id' => $orderId], $t['params']);
        $sql = "SELECT
                    oi.order_item_id, oi.order_id, oi.menu_item_id,
                    oi.quantity, oi.price, oi.note,
                    mi.name as menu_item_name, mi.image_url,
                    c.name as category_name
                FROM order_items oi
                LEFT JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                LEFT JOIN categories c ON mi.category_id = c.category_id
                INNER JOIN orders o ON oi.order_id = o.order_id {$t['sql']}
                WHERE oi.order_id = :order_id
                ORDER BY oi.created_at ASC";
        return $this->fetchAll($sql, $params);
    }
}
