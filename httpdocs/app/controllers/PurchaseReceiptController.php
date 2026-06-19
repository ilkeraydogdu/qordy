<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Purchase receipts (irsaliye / fatura) admin + API.
 *
 * Follows the same tenant-context pattern as StockController: super-admins
 * may pass ?business_id= to read/write another tenant, everyone else is
 * pinned to their session tenant by BaseRepository's tenant filter.
 */
class PurchaseReceiptController extends \App\Core\Controller
{
    /** @var \App\Services\PurchaseReceiptService */
    private $service;
    /** @var \App\Services\PurchaseReceiptItemRepository|null */
    private $itemRepo;

    public function __construct()
    {
        parent::__construct();
        $this->service  = \App\Core\DependencyFactory::getPurchaseReceiptService();
        $this->itemRepo = \App\Core\DependencyFactory::getPurchaseReceiptItemRepository();
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
                } catch (\Throwable $e) {
                    // swallow — empty list is safer than a crash.
                }
            }
            return;
        }
        parent::ensureTenantContext();
    }

    private function ensurePermission(string $perm): void
    {
        if (!$this->hasPermission($perm)
            && !$this->hasPermission('stock.view')
            && !$this->hasPermission('finance.view')) {
            \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401);
        }
    }

    public function index(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.receipt.view');
        $this->view('admin/purchase-receipts', [
            'is_super_admin' => $this->isSuperAdmin(),
        ]);
    }

    public function listReceipts(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.receipt.view');
        $qp = \App\Core\RequestParser::getQueryParams();
        $filters = [
            'supplier_id' => $qp['supplier_id'] ?? null,
            'date_from'   => $qp['date_from']   ?? null,
            'date_to'     => $qp['date_to']     ?? null,
        ];
        $rows = $this->service->list($filters);
        $this->apiResponse(['success' => true, 'data' => $rows]);
    }

    public function getReceipt(string $id): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.receipt.view');
        $row = $this->service->get($id);
        if (!$row) {
            \App\Core\ResponseHandler::error('İrsaliye bulunamadı', 'NOT_FOUND', 404);
            return;
        }
        $this->apiResponse(['success' => true, 'data' => $row]);
    }

    public function createReceipt(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.receipt.create');
        $input = \App\Core\RequestParser::getJsonBody();
        try {
            $id = $this->service->createReceipt(is_array($input) ? $input : []);
            $this->apiResponse(['success' => true, 'data' => $this->service->get($id)]);
        } catch (\InvalidArgumentException $e) {
            \App\Core\ResponseHandler::error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Throwable $e) {
            \App\Core\ResponseHandler::error('İrsaliye oluşturulamadı: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    public function deleteReceipt(string $id): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.receipt.delete');
        try {
            $ok = $this->service->delete($id);
            $this->apiResponse(['success' => $ok]);
        } catch (\Throwable $e) {
            \App\Core\ResponseHandler::error('İşlem başarısız: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    /**
     * List active lots (qty_remaining > 0) for an ingredient — consumed by
     * the waste form's batch selector.
     */
    public function activeLots(string $ingredientId): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.waste.view');
        $lots = $this->itemRepo->activeLotsForIngredient($ingredientId);
        $this->apiResponse(['success' => true, 'data' => $lots]);
    }

    /**
     * Lightweight suppliers lookup used by Phase 2 UIs (purchases form,
     * waste form, supplier performance screen). FinanceController only
     * renders the HTML page; this endpoint returns JSON.
     */
    public function lookupSuppliers(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.view');
        $rows = \App\Core\DependencyFactory::getFinanceService()->getAllSuppliers();
        $this->apiResponse(['success' => true, 'data' => $rows]);
    }

    /**
     * Lightweight ingredients lookup (id, name, unit, unit_cost, category_id).
     * Tenant-scoped via IngredientRepository::getAll.
     */
    public function lookupIngredients(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.view');
        $rows = \App\Core\DependencyFactory::getIngredientRepository()->getAll();
        $this->apiResponse(['success' => true, 'data' => $rows]);
    }
}
