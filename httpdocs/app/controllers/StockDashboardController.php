<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Dedicated landing surface for the STOCK_MANAGER role (Phase 2).
 *
 * STOCK_MANAGER users log in through the same screen as other staff but
 * we don't want them to see the full BUSINESS_OWNER dashboard (upgrade
 * panels, waiter/kitchen widgets, etc). This controller renders a
 * focused dashboard that surfaces only stock-related KPIs and quick
 * links to the Phase 2 stock tools.
 */
class StockDashboardController extends \App\Core\Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requireLogin();
        // Allow anyone with at least stock.view (BUSINESS_OWNER, BUSINESS_MANAGER,
        // STOCK_MANAGER) to see the dashboard; the sidebar links respect
        // individual permissions so feature gating still applies.
        if (!$this->hasPermission('stock.view')) {
            \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 403);
            return;
        }

        $summary = $this->buildSummary();

        $this->view('admin/stock-dashboard', [
            'summary'       => $summary,
            'is_super_admin'=> $this->isSuperAdmin(),
        ]);
    }

    /**
     * Aggregate quick KPIs: total ingredients, low-stock count, today's
     * waste cost and last 7 days purchase cost. Keeps queries scoped to
     * the current tenant via TenantContext.
     *
     * @return array<string, mixed>
     */
    private function buildSummary(): array
    {
        $db = \App\Core\DependencyFactory::getDatabase();
        $tenantId = \App\Core\TenantContext::getId();

        $scope = '';
        $params = [];
        if ($tenantId) {
            $scope = ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }

        $fetch = function (string $sql, array $p) use ($db) {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute($p);
                return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                return [];
            }
        };

        $ingredients = $fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN current_stock <= min_threshold THEN 1 ELSE 0 END) AS low
             FROM ingredients WHERE 1=1{$scope}",
            $params
        );

        $todayWaste = $fetch(
            "SELECT SUM(COALESCE(total_cost,0)) AS total, COUNT(*) AS cnt
             FROM waste_records
             WHERE DATE(date) = CURDATE(){$scope}",
            $params
        );

        $weekPurchase = $fetch(
            "SELECT SUM(pri.qty * pri.unit_cost) AS total, COUNT(DISTINCT pr.receipt_id) AS cnt
             FROM purchase_receipt_items pri
             JOIN purchase_receipts pr ON pr.receipt_id = pri.receipt_id
             WHERE pr.received_at >= (CURDATE() - INTERVAL 7 DAY)"
             . ($tenantId ? ' AND pri.tenant_id = :tid' : ''),
            $tenantId ? [':tid' => $tenantId] : []
        );

        $disabled = $fetch(
            "SELECT COUNT(*) AS cnt FROM ingredients
             WHERE is_auto_disabled = 1{$scope}",
            $params
        );

        return [
            'ingredients_total'   => (int)($ingredients['total'] ?? 0),
            'ingredients_low'     => (int)($ingredients['low']   ?? 0),
            'ingredients_blocked' => (int)($disabled['cnt']      ?? 0),
            'waste_today_cost'    => (float)($todayWaste['total'] ?? 0),
            'waste_today_count'   => (int)($todayWaste['cnt']     ?? 0),
            'purchase_week_cost'  => (float)($weekPurchase['total'] ?? 0),
            'purchase_week_count' => (int)($weekPurchase['cnt']     ?? 0),
        ];
    }
}
