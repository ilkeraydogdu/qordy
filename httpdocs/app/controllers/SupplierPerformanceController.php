<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Supplier performance analytics (tedarikçi performans) controller.
 *
 * Exposes JSON endpoints for the leaderboard/detail/trend queries in
 * {@see \App\Services\SupplierAnalyticsService} plus a plain HTML index
 * that renders the dashboard view. Same tenant-context pattern used by
 * other Phase 2 controllers: super-admin may pass ?business_id= to pin
 * a specific tenant, everyone else is pinned by their session.
 */
class SupplierPerformanceController extends \App\Core\Controller
{
    /** @var \App\Services\SupplierAnalyticsService */
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = \App\Core\DependencyFactory::getSupplierAnalyticsService();
    }

    protected function applyTenantContext(): void
    {
        if ($this->isSuperAdmin()) {
            $qp = \App\Core\RequestParser::getQueryParams();
            $requested = $qp['business_id'] ?? $qp['tenant_id'] ?? null;
            if ($requested) {
                try {
                    $cs = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $cs->getById($requested);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        $_SESSION['business_id'] = $requested;
                        $_SESSION['customer_id'] = $requested;
                        return;
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
            return;
        }
        parent::ensureTenantContext();
    }

    private function ensurePermission(): void
    {
        if (!$this->hasPermission('supplier.performance.view')
            && !$this->hasPermission('finance.view')
            && !$this->hasPermission('stock.view')) {
            \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401);
        }
    }

    public function index(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission();
        $this->view('admin/supplier-performance', [
            'is_super_admin' => $this->isSuperAdmin(),
        ]);
    }

    public function leaderboard(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission();
        $qp = \App\Core\RequestParser::getQueryParams();
        $rows = $this->service->leaderboard([
            'start' => $qp['start'] ?? null,
            'end'   => $qp['end']   ?? null,
            'limit' => isset($qp['limit']) ? (int)$qp['limit'] : 25,
        ]);
        $this->apiResponse(['success' => true, 'data' => $rows]);
    }

    public function detail(string $supplierId): void
    {
        $this->applyTenantContext();
        $this->ensurePermission();
        $qp = \App\Core\RequestParser::getQueryParams();
        $data = $this->service->supplierDetail($supplierId, [
            'start' => $qp['start'] ?? null,
            'end'   => $qp['end']   ?? null,
        ]);
        $this->apiResponse(['success' => true, 'data' => $data]);
    }

    public function trend(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission();
        $qp = \App\Core\RequestParser::getQueryParams();
        $supplierId = isset($qp['supplier_id']) && $qp['supplier_id'] !== '' ? (string)$qp['supplier_id'] : null;
        $rows = $this->service->wasteTrend($supplierId, [
            'start' => $qp['start'] ?? null,
            'end'   => $qp['end']   ?? null,
        ]);
        $this->apiResponse(['success' => true, 'data' => $rows]);
    }
}
