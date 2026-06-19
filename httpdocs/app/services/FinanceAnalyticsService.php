<?php
namespace App\Services;

/**
 * FinanceAnalyticsService
 *
 * Cross-domain analytics that glue together the fire (waste), stok (stock),
 * tedarikçi (suppliers) and gider (expenses) sub-systems so the finance
 * dashboards and supplier detail pages can answer questions like:
 *   - This tenant's waste cost for the last 7 days, broken down by reason.
 *   - Supplier X's purchases, returns, waste and unpaid balance this month.
 *   - Current stock value and low-stock count for the dashboard header.
 *
 * All queries are tenant-scoped via TenantResolver, so super-admins must
 * call TenantContext::set() before invoking these methods.
 */
class FinanceAnalyticsService {
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    // -----------------------------------------------------------------
    // Time-window helpers
    // -----------------------------------------------------------------

    /**
     * Resolve a preset range label into [startDate, endDate] in Y-m-d format.
     * Unknown presets fall back to "today".
     */
    public static function resolveDateRange(string $preset, ?string $start = null, ?string $end = null): array {
        $today = date('Y-m-d');
        switch (strtolower($preset)) {
            case 'yesterday':
                $d = date('Y-m-d', strtotime('-1 day'));
                return [$d, $d];
            case 'week':
            case '7d':
                return [date('Y-m-d', strtotime('-6 days')), $today];
            case 'month':
                return [date('Y-m-01'), $today];
            case '30d':
                return [date('Y-m-d', strtotime('-29 days')), $today];
            case 'quarter':
            case '90d':
                return [date('Y-m-d', strtotime('-89 days')), $today];
            case 'year':
                return [date('Y-01-01'), $today];
            case 'custom':
                return [
                    $start ?: $today,
                    $end   ?: $today,
                ];
            case 'today':
            default:
                return [$today, $today];
        }
    }

    private function tenantWhere(string $alias = ''): array {
        $tenantId = \App\Core\TenantContext::getId();
        if (!$tenantId) {
            return ['', []];
        }
        $col = $alias !== '' ? "{$alias}.tenant_id" : 'tenant_id';
        return [" {$col} = :tenant_id ", ['tenant_id' => $tenantId]];
    }

    // -----------------------------------------------------------------
    // Stock analytics
    // -----------------------------------------------------------------

    /**
     * Aggregate stock KPIs for the dashboard header:
     *   - ingredient/menu item counts
     *   - low-stock count (current_stock < min_threshold)
     *   - out-of-stock count (current_stock = 0)
     *   - total stock value = sum(current_stock * cost_per_unit) for ingredients
     */
    public function getStockOverview(): array {
        [$where, $params] = $this->tenantWhere();
        $whereClause = $where ? "WHERE {$where}" : '';

        $ingredientSql = "
            SELECT
                COUNT(*)                                               AS item_count,
                COALESCE(SUM(current_stock * IFNULL(cost_per_unit, 0)), 0) AS stock_value,
                SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END)    AS out_of_stock,
                SUM(CASE WHEN current_stock > 0 AND min_threshold > 0
                         AND current_stock < min_threshold
                         THEN 1 ELSE 0 END)                            AS low_stock
            FROM ingredients
            {$whereClause}
        ";
        $stmt = $this->db->prepare($ingredientSql);
        $stmt->execute($params);
        $ing = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $menuSql = "
            SELECT
                COUNT(*) AS item_count,
                SUM(CASE WHEN track_stock = 1 AND stock <= 0 THEN 1 ELSE 0 END)                      AS out_of_stock,
                SUM(CASE WHEN track_stock = 1 AND stock > 0 AND low_stock_threshold > 0
                         AND stock < low_stock_threshold THEN 1 ELSE 0 END)                          AS low_stock
            FROM menu_items
            {$whereClause}
        ";
        $stmt = $this->db->prepare($menuSql);
        $stmt->execute($params);
        $menu = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'ingredient_count'  => (int)($ing['item_count'] ?? 0),
            'menu_item_count'   => (int)($menu['item_count'] ?? 0),
            'stock_value'       => (float)($ing['stock_value'] ?? 0),
            'low_stock_count'   => (int)($ing['low_stock'] ?? 0) + (int)($menu['low_stock'] ?? 0),
            'out_of_stock_count'=> (int)($ing['out_of_stock'] ?? 0) + (int)($menu['out_of_stock'] ?? 0),
        ];
    }

    /**
     * Per-sub_type stock counts for the filter badges on the inventory page
     * (Malzeme / Hammadde / Mutfak Sarfı / Temizlik / Menü Ürünü / Diğer).
     */
    public function getStockTypeCounts(): array {
        [$where, $params] = $this->tenantWhere();
        $whereClause = $where ? "WHERE {$where}" : '';

        $sql = "
            SELECT COALESCE(item_type, 'OTHER') AS sub_type, COUNT(*) AS c
            FROM ingredients
            {$whereClause}
            GROUP BY COALESCE(item_type, 'OTHER')
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $counts = [
            'INGREDIENT'     => 0,
            'RAW_MATERIAL'   => 0,
            'KITCHEN_SUPPLY' => 0,
            'CLEANING'       => 0,
            'OTHER'          => 0,
            'MENU_ITEM'      => 0,
        ];
        foreach ($rows as $r) {
            $k = $r['sub_type'] ?? 'OTHER';
            $counts[$k] = ($counts[$k] ?? 0) + (int)$r['c'];
        }

        $menuSql = "SELECT COUNT(*) FROM menu_items {$whereClause}";
        $stmt = $this->db->prepare($menuSql);
        $stmt->execute($params);
        $counts['MENU_ITEM'] = (int)$stmt->fetchColumn();

        return $counts;
    }

    // -----------------------------------------------------------------
    // Waste (fire) analytics
    // -----------------------------------------------------------------

    public function getWasteOverview(string $start, string $end): array {
        [$where, $params] = $this->tenantWhere();
        $whereClause = $where ? "{$where} AND" : '';
        $params['start'] = $start . ' 00:00:00';
        $params['end']   = $end   . ' 23:59:59';

        $sql = "
            SELECT
                COUNT(*)                                     AS record_count,
                COALESCE(SUM(total_cost), 0)                 AS total_cost,
                COALESCE(SUM(amount), 0)                     AS total_amount
            FROM waste_records
            WHERE {$whereClause} date BETWEEN :start AND :end
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        return [
            'record_count' => (int)($row['record_count'] ?? 0),
            'total_cost'   => (float)($row['total_cost'] ?? 0),
            'total_amount' => (float)($row['total_amount'] ?? 0),
        ];
    }

    public function getWasteByReason(string $start, string $end): array {
        [$where, $params] = $this->tenantWhere();
        $whereClause = $where ? "{$where} AND" : '';
        $params['start'] = $start . ' 00:00:00';
        $params['end']   = $end   . ' 23:59:59';

        $sql = "
            SELECT COALESCE(reason, 'OTHER') AS reason,
                   COUNT(*)                  AS record_count,
                   COALESCE(SUM(total_cost), 0) AS total_cost
            FROM waste_records
            WHERE {$whereClause} date BETWEEN :start AND :end
            GROUP BY COALESCE(reason, 'OTHER')
            ORDER BY total_cost DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn($r) => [
            'reason'       => $r['reason'],
            'record_count' => (int)$r['record_count'],
            'total_cost'   => (float)$r['total_cost'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    public function getWasteTrend(int $days = 7): array {
        [$where, $params] = $this->tenantWhere();
        $whereClause = $where ? "{$where} AND" : '';
        $days = max(1, min(90, $days));

        $sql = "
            SELECT DATE(date)                 AS day,
                   COUNT(*)                   AS record_count,
                   COALESCE(SUM(total_cost), 0) AS total_cost
            FROM waste_records
            WHERE {$whereClause} date >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
            GROUP BY DATE(date)
            ORDER BY day ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn($r) => [
            'day'          => $r['day'],
            'record_count' => (int)$r['record_count'],
            'total_cost'   => (float)$r['total_cost'],
        ], $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
    }

    public function getTopWastedIngredients(string $start, string $end, int $limit = 10): array {
        [$where, $params] = $this->tenantWhere('w');
        $whereClause = $where ? "{$where} AND" : '';
        $params['start'] = $start . ' 00:00:00';
        $params['end']   = $end   . ' 23:59:59';
        $limit = max(1, min(50, $limit));

        $sql = "
            SELECT w.ingredient_id,
                   COALESCE(i.name, 'Bilinmeyen') AS name,
                   COUNT(*)                       AS record_count,
                   COALESCE(SUM(w.amount), 0)     AS total_amount,
                   COALESCE(SUM(w.total_cost), 0) AS total_cost
            FROM waste_records w
            LEFT JOIN ingredients i ON i.ingredient_id = w.ingredient_id
            WHERE {$whereClause} w.date BETWEEN :start AND :end
              AND w.ingredient_id IS NOT NULL
            GROUP BY w.ingredient_id, i.name
            ORDER BY total_cost DESC
            LIMIT {$limit}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // -----------------------------------------------------------------
    // Supplier analytics
    // -----------------------------------------------------------------

    public function getSupplierOverview(string $start, string $end): array {
        [$where, $params] = $this->tenantWhere();
        $whereClause = $where ? "WHERE {$where}" : '';

        $countSql = "SELECT COUNT(*) AS c, COALESCE(SUM(balance), 0) AS total_balance FROM suppliers {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $cnt = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $purchaseParams = $params;
        $purchaseParams['start'] = $start . ' 00:00:00';
        $purchaseParams['end']   = $end   . ' 23:59:59';
        $purchaseWhere = $where ? "{$where} AND" : '';
        $purchaseSql = "
            SELECT COUNT(*) AS receipt_count, COALESCE(SUM(total_cost), 0) AS total_purchase
            FROM purchase_receipts
            WHERE {$purchaseWhere} received_at BETWEEN :start AND :end
        ";
        $stmt = $this->db->prepare($purchaseSql);
        $stmt->execute($purchaseParams);
        $purchases = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Unpaid invoices.
        $invoiceWhere = $where ? "{$where} AND" : '';
        $invoiceSql = "
            SELECT COUNT(*) AS unpaid_count, COALESCE(SUM(amount), 0) AS unpaid_total
            FROM invoices
            WHERE {$invoiceWhere} is_paid = 0
        ";
        $stmt = $this->db->prepare($invoiceSql);
        $stmt->execute($params);
        $unpaid = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'supplier_count'   => (int)($cnt['c'] ?? 0),
            'supplier_balance' => (float)($cnt['total_balance'] ?? 0),
            'purchase_count'   => (int)($purchases['receipt_count'] ?? 0),
            'purchase_total'   => (float)($purchases['total_purchase'] ?? 0),
            'unpaid_invoices'  => (int)($unpaid['unpaid_count'] ?? 0),
            'unpaid_total'     => (float)($unpaid['unpaid_total'] ?? 0),
        ];
    }

    public function getTopSuppliers(string $start, string $end, int $limit = 10): array {
        [$where, $params] = $this->tenantWhere('pr');
        $whereClause = $where ? "{$where} AND" : '';
        $params['start'] = $start . ' 00:00:00';
        $params['end']   = $end   . ' 23:59:59';
        $limit = max(1, min(50, $limit));

        $sql = "
            SELECT pr.supplier_id,
                   COALESCE(s.name, 'Bilinmeyen') AS name,
                   COUNT(*)                       AS receipt_count,
                   COALESCE(SUM(pr.total_cost), 0) AS total_purchase
            FROM purchase_receipts pr
            LEFT JOIN suppliers s ON s.supplier_id = pr.supplier_id
            WHERE {$whereClause} pr.received_at BETWEEN :start AND :end
            GROUP BY pr.supplier_id, s.name
            ORDER BY total_purchase DESC
            LIMIT {$limit}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Full supplier drill-down: purchases, returns, waste, unpaid balance,
     * stocked items for a single supplier within the selected window.
     */
    public function getSupplierDetail(string $supplierId, string $start, string $end): array {
        [$where, $params] = $this->tenantWhere();
        $params['supplier_id'] = $supplierId;
        $params['start']       = $start . ' 00:00:00';
        $params['end']         = $end   . ' 23:59:59';

        $supplierSql = "SELECT * FROM suppliers WHERE supplier_id = :supplier_id" . ($where ? " AND {$where}" : '');
        $stmt = $this->db->prepare($supplierSql);
        $stmt->execute($params);
        $supplier = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$supplier) {
            return ['supplier' => null];
        }

        // Purchases within the date range.
        $purchaseSql = "
            SELECT COUNT(*) AS receipt_count,
                   COALESCE(SUM(total_cost), 0) AS total_purchase
            FROM purchase_receipts
            WHERE supplier_id = :supplier_id" . ($where ? " AND {$where}" : '') . "
              AND received_at BETWEEN :start AND :end
        ";
        $stmt = $this->db->prepare($purchaseSql);
        $stmt->execute($params);
        $purchases = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Returns from stock_movements with type=RETURN, linked to this
        // supplier via purchase_item_id → purchase_receipt_items → receipt.
        $returnSql = "
            SELECT COUNT(*) AS return_count,
                   COALESCE(SUM(sm.quantity * IFNULL(pri.unit_cost, 0)), 0) AS return_cost
            FROM stock_movements sm
            INNER JOIN purchase_receipt_items pri ON pri.item_id = sm.purchase_item_id
            INNER JOIN purchase_receipts pr ON pr.receipt_id = pri.receipt_id
            WHERE sm.movement_type = 'RETURN'
              AND pr.supplier_id = :supplier_id" . ($where ? " AND sm.{$where}" : '') . "
              AND sm.created_at BETWEEN :start AND :end
        ";
        $stmt = $this->db->prepare($returnSql);
        $stmt->execute($params);
        $returns = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Waste directly attributed to the supplier or to one of their
        // purchase items (so products that went to waste before they were
        // consumed are counted even if waste_records.supplier_id is null).
        $wasteSql = "
            SELECT COUNT(*) AS waste_count,
                   COALESCE(SUM(total_cost), 0) AS waste_cost,
                   COALESCE(SUM(amount), 0)     AS waste_amount
            FROM waste_records w
            WHERE (w.supplier_id = :supplier_id
                   OR w.purchase_item_id IN (
                       SELECT pri.item_id
                       FROM purchase_receipt_items pri
                       INNER JOIN purchase_receipts pr2 ON pr2.receipt_id = pri.receipt_id
                       WHERE pr2.supplier_id = :supplier_id
                   ))" . ($where ? " AND w.{$where}" : '') . "
              AND w.date BETWEEN :start AND :end
        ";
        $stmt = $this->db->prepare($wasteSql);
        $stmt->execute($params);
        $waste = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Ingredients this supplier provides plus current on-hand value.
        $invSql = "
            SELECT i.ingredient_id, i.name, i.unit, i.current_stock, i.cost_per_unit,
                   (i.current_stock * IFNULL(i.cost_per_unit, 0)) AS stock_value,
                   i.min_threshold
            FROM ingredients i
            WHERE i.supplier_id = :supplier_id" . ($where ? " AND i.{$where}" : '') . "
            ORDER BY i.name ASC
        ";
        $stmt = $this->db->prepare($invSql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Purchase receipts list (for the timeline / attachments UI).
        $recSql = "
            SELECT pr.receipt_id, pr.invoice_no, pr.received_at, pr.total_cost, pr.notes, pr.attachment_path,
                   (SELECT COUNT(*) FROM purchase_receipt_items pri WHERE pri.receipt_id = pr.receipt_id) AS item_count
            FROM purchase_receipts pr
            WHERE pr.supplier_id = :supplier_id" . ($where ? " AND pr.{$where}" : '') . "
              AND pr.received_at BETWEEN :start AND :end
            ORDER BY pr.received_at DESC
            LIMIT 100
        ";
        $stmt = $this->db->prepare($recSql);
        $stmt->execute($params);
        $receipts = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Unpaid invoices for this supplier.
        $invoiceSql = "
            SELECT COUNT(*) AS unpaid_count, COALESCE(SUM(amount), 0) AS unpaid_total
            FROM invoices
            WHERE supplier_id = :supplier_id AND is_paid = 0" . ($where ? " AND {$where}" : '') . "
        ";
        $stmt = $this->db->prepare($invoiceSql);
        $stmt->execute($params);
        $invoices = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'supplier' => $supplier,
            'kpis' => [
                'purchase_count'  => (int)($purchases['receipt_count'] ?? 0),
                'purchase_total'  => (float)($purchases['total_purchase'] ?? 0),
                'return_count'    => (int)($returns['return_count'] ?? 0),
                'return_cost'     => (float)($returns['return_cost'] ?? 0),
                'waste_count'     => (int)($waste['waste_count'] ?? 0),
                'waste_cost'      => (float)($waste['waste_cost'] ?? 0),
                'waste_amount'    => (float)($waste['waste_amount'] ?? 0),
                'unpaid_count'    => (int)($invoices['unpaid_count'] ?? 0),
                'unpaid_total'    => (float)($invoices['unpaid_total'] ?? 0),
                'items_count'     => count($items),
                'stock_value'     => array_sum(array_map(static fn($r) => (float)($r['stock_value'] ?? 0), $items)),
                'current_balance' => (float)($supplier['balance'] ?? 0),
            ],
            'items'    => $items,
            'receipts' => $receipts,
        ];
    }
}
