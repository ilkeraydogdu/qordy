<?php
namespace App\Services;

use App\Core\TenantContext;

/**
 * Supplier performance analytics.
 *
 * Combines data from `purchase_receipts`, `purchase_receipt_items` and
 * `waste_records` to compute per-supplier KPIs:
 *   - purchased quantity & total purchase cost
 *   - wasted quantity & total waste cost
 *   - waste ratio (wasted / purchased)
 *   - on-time score placeholder (expiry-vs-waste dates)
 *
 * All queries are tenant-scoped via `TenantContext::getId()` so a business
 * only sees its own suppliers, and SuperAdmin screens can pin a specific
 * tenant before calling.
 */
class SupplierAnalyticsService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Return a leaderboard of suppliers ordered by waste ratio (worst first).
     *
     * @param array{start?:string,end?:string,limit?:int} $opts
     * @return array<int, array<string, mixed>>
     */
    public function leaderboard(array $opts = []): array
    {
        $tenantId = TenantContext::getId();
        $start = $opts['start'] ?? date('Y-m-d', strtotime('-90 days'));
        $end   = $opts['end']   ?? date('Y-m-d');
        $limit = max(1, (int)($opts['limit'] ?? 25));

        // We duplicate the start/end bindings per subquery because PDO (with
        // emulated prepares disabled) does not allow reusing a named
        // placeholder. Same for tenant filters below.
        $params = [
            ':start_p' => $start,
            ':end_p'   => $end . ' 23:59:59',
            ':start_w' => $start,
            ':end_w'   => $end . ' 23:59:59',
        ];
        $tenantFilterPri = '';
        $tenantFilterW = '';
        $tenantFilterS = '';
        if ($tenantId) {
            $params[':tid_p'] = $tenantId;
            $params[':tid_w'] = $tenantId;
            $params[':tid_s'] = $tenantId;
            $tenantFilterPri = ' AND pri.tenant_id = :tid_p';
            $tenantFilterW   = ' AND w.tenant_id = :tid_w';
            $tenantFilterS   = ' AND s.tenant_id = :tid_s';
        }

        $sql = "
            SELECT
                s.supplier_id,
                s.name AS supplier_name,
                COALESCE(p.purchased_qty, 0)  AS purchased_qty,
                COALESCE(p.purchased_cost, 0) AS purchased_cost,
                COALESCE(w.wasted_qty, 0)     AS wasted_qty,
                COALESCE(w.wasted_cost, 0)    AS wasted_cost,
                CASE
                    WHEN COALESCE(p.purchased_qty, 0) > 0
                    THEN ROUND((COALESCE(w.wasted_qty, 0) / p.purchased_qty) * 100, 2)
                    ELSE 0
                END AS waste_ratio_pct,
                COALESCE(p.batches, 0)        AS batches
            FROM suppliers s
            LEFT JOIN (
                SELECT pr.supplier_id,
                       SUM(pri.qty)                 AS purchased_qty,
                       SUM(pri.qty * pri.unit_cost) AS purchased_cost,
                       COUNT(pri.item_id)           AS batches
                FROM purchase_receipt_items pri
                JOIN purchase_receipts pr ON pr.receipt_id = pri.receipt_id
                WHERE pr.received_at BETWEEN :start_p AND :end_p
                  {$tenantFilterPri}
                GROUP BY pr.supplier_id
            ) p ON p.supplier_id = s.supplier_id
            LEFT JOIN (
                SELECT supplier_id,
                       SUM(amount)     AS wasted_qty,
                       SUM(COALESCE(total_cost, 0)) AS wasted_cost
                FROM waste_records w
                WHERE supplier_id IS NOT NULL
                  AND date BETWEEN :start_w AND :end_w
                  {$tenantFilterW}
                GROUP BY supplier_id
            ) w ON w.supplier_id = s.supplier_id
            WHERE 1=1 {$tenantFilterS}
              AND (COALESCE(p.purchased_qty, 0) > 0 OR COALESCE(w.wasted_qty, 0) > 0)
            ORDER BY waste_ratio_pct DESC, wasted_cost DESC
            LIMIT {$limit}
        ";

        return $this->runQuery($sql, $params);
    }

    /**
     * Detail view for one supplier: per-ingredient purchased/wasted breakdown
     * so ops can identify exactly which products from that vendor are lossy.
     *
     * @return array{
     *   supplier: array<string, mixed>|null,
     *   items: array<int, array<string, mixed>>,
     *   totals: array<string, float>
     * }
     */
    public function supplierDetail(string $supplierId, array $opts = []): array
    {
        $tenantId = TenantContext::getId();
        $start = $opts['start'] ?? date('Y-m-d', strtotime('-90 days'));
        $end   = $opts['end']   ?? date('Y-m-d');

        // Lookup supplier header (single tenant filter is fine here).
        $supplierSqlTenant = $tenantId ? ' AND tenant_id = :tid' : '';
        $supplierParams = [':sid' => $supplierId];
        if ($tenantId) { $supplierParams[':tid'] = $tenantId; }
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE supplier_id = :sid{$supplierSqlTenant} LIMIT 1");
        $stmt->execute($supplierParams);
        $supplier = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        // Duplicated params because PDO (non-emulated) does not allow
        // reusing the same named placeholder across the two subqueries.
        $params = [
            ':sid_p'   => $supplierId,
            ':sid_w'   => $supplierId,
            ':start_p' => $start,
            ':end_p'   => $end . ' 23:59:59',
            ':start_w' => $start,
            ':end_w'   => $end . ' 23:59:59',
        ];
        if ($tenantId) {
            $params[':tid_p'] = $tenantId;
            $params[':tid_w'] = $tenantId;
        }

        $sql = "
            SELECT
                i.ingredient_id,
                i.name AS ingredient_name,
                i.unit,
                COALESCE(p.purchased_qty, 0)  AS purchased_qty,
                COALESCE(p.purchased_cost, 0) AS purchased_cost,
                COALESCE(w.wasted_qty, 0)     AS wasted_qty,
                COALESCE(w.wasted_cost, 0)    AS wasted_cost,
                CASE
                    WHEN COALESCE(p.purchased_qty, 0) > 0
                    THEN ROUND((COALESCE(w.wasted_qty, 0) / p.purchased_qty) * 100, 2)
                    ELSE 0
                END AS waste_ratio_pct
            FROM ingredients i
            LEFT JOIN (
                SELECT pri.ingredient_id,
                       SUM(pri.qty) AS purchased_qty,
                       SUM(pri.qty * pri.unit_cost) AS purchased_cost
                FROM purchase_receipt_items pri
                JOIN purchase_receipts pr ON pr.receipt_id = pri.receipt_id
                WHERE pr.supplier_id = :sid_p
                  AND pr.received_at BETWEEN :start_p AND :end_p
                  " . ($tenantId ? 'AND pri.tenant_id = :tid_p' : '') . "
                GROUP BY pri.ingredient_id
            ) p ON p.ingredient_id = i.ingredient_id
            LEFT JOIN (
                SELECT ingredient_id,
                       SUM(amount) AS wasted_qty,
                       SUM(COALESCE(total_cost, 0)) AS wasted_cost
                FROM waste_records
                WHERE supplier_id = :sid_w
                  AND date BETWEEN :start_w AND :end_w
                  " . ($tenantId ? 'AND tenant_id = :tid_w' : '') . "
                GROUP BY ingredient_id
            ) w ON w.ingredient_id = i.ingredient_id
            WHERE (COALESCE(p.purchased_qty, 0) > 0 OR COALESCE(w.wasted_qty, 0) > 0)
            ORDER BY waste_ratio_pct DESC, wasted_cost DESC
        ";

        $items = $this->runQuery($sql, $params);

        $totals = [
            'purchased_qty'  => 0.0,
            'purchased_cost' => 0.0,
            'wasted_qty'     => 0.0,
            'wasted_cost'    => 0.0,
        ];
        foreach ($items as $it) {
            $totals['purchased_qty']  += (float)($it['purchased_qty'] ?? 0);
            $totals['purchased_cost'] += (float)($it['purchased_cost'] ?? 0);
            $totals['wasted_qty']     += (float)($it['wasted_qty'] ?? 0);
            $totals['wasted_cost']    += (float)($it['wasted_cost'] ?? 0);
        }

        return [
            'supplier' => $supplier,
            'items'    => $items,
            'totals'   => $totals,
        ];
    }

    /**
     * Time-series helper for charts: waste cost per day for a supplier (or
     * all suppliers if $supplierId is null).
     *
     * @return array<int, array{date:string, wasted_cost:float}>
     */
    public function wasteTrend(?string $supplierId, array $opts = []): array
    {
        $tenantId = TenantContext::getId();
        $start = $opts['start'] ?? date('Y-m-d', strtotime('-30 days'));
        $end   = $opts['end']   ?? date('Y-m-d');
        $params = [
            ':start' => $start,
            ':end'   => $end . ' 23:59:59',
        ];
        $where = 'w.date BETWEEN :start AND :end';
        if ($supplierId) {
            $where .= ' AND w.supplier_id = :sid';
            $params[':sid'] = $supplierId;
        }
        if ($tenantId) {
            $where .= ' AND w.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql = "
            SELECT DATE(w.date) AS date,
                   SUM(COALESCE(w.total_cost, 0)) AS wasted_cost,
                   SUM(w.amount) AS wasted_qty
            FROM waste_records w
            WHERE {$where}
            GROUP BY DATE(w.date)
            ORDER BY DATE(w.date) ASC
        ";
        return $this->runQuery($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function runQuery(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
