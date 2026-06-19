<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Stock Categories + Units API. All endpoints are tenant-scoped via the
 * shared StockController::applyTenantContext helper (inherited from the
 * base Controller). Super admins can optionally pass ?business_id to
 * read/write into a specific tenant.
 */
class StockCategoryController extends \App\Core\Controller
{
    private $categoryService;
    private $unitService;

    public function __construct()
    {
        parent::__construct();
        $this->categoryService = \App\Core\DependencyFactory::getStockCategoryService();
        $this->unitService     = \App\Core\DependencyFactory::getStockUnitService();
    }

    /** @return void */
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
                    // fall through — empty result is safer than crashing.
                }
            }
            return;
        }
        parent::ensureTenantContext();
    }

    /** @return void */
    private function ensurePermission(string $perm): void
    {
        if (!$this->hasPermission($perm)
            && !$this->hasPermission('stock.view')
            && !$this->hasPermission('finance.view')) {
            \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401);
        }
    }

    /**
     * Render the stock categories + units admin page.
     */
    public function index(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.view');
        $this->view('admin/stock-categories', [
            'is_super_admin' => $this->isSuperAdmin(),
        ]);
    }

    // ---- categories -------------------------------------------------------

    public function listCategories(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.view');
        $qp = \App\Core\RequestParser::getQueryParams();
        $includeInactive = !empty($qp['include_inactive']);
        $items = $this->categoryService->getList($includeInactive);
        $this->apiResponse(['success' => true, 'data' => $items]);
    }

    public function categoryTree(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.view');
        $qp = \App\Core\RequestParser::getQueryParams();
        $includeInactive = !empty($qp['include_inactive']);
        $tree = $this->categoryService->getTree($includeInactive);
        $this->apiResponse(['success' => true, 'data' => $tree]);
    }

    public function createCategory(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.category.manage');
        $input = \App\Core\RequestParser::getJsonBody();
        try {
            $id = $this->categoryService->create(is_array($input) ? $input : []);
            $created = $this->categoryService->get($id);
            $this->apiResponse(['success' => true, 'data' => $created]);
        } catch (\InvalidArgumentException $e) {
            \App\Core\ResponseHandler::error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Throwable $e) {
            \App\Core\ResponseHandler::error('Kategori oluşturulamadı: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    public function updateCategory(string $id): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.category.manage');
        $input = \App\Core\RequestParser::getJsonBody();
        try {
            if (!is_array($input)) {
                $input = [];
            }
            if (array_key_exists('parent_id', $input)) {
                $newParent = $input['parent_id'] !== '' ? (string)$input['parent_id'] : null;
                $this->categoryService->moveNode($id, $newParent);
                unset($input['parent_id']);
            }
            if (!empty($input)) {
                $this->categoryService->update($id, $input);
            }
            $this->apiResponse(['success' => true, 'data' => $this->categoryService->get($id)]);
        } catch (\InvalidArgumentException $e) {
            \App\Core\ResponseHandler::error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Throwable $e) {
            \App\Core\ResponseHandler::error('Kategori güncellenemedi: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    public function deleteCategory(string $id): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.category.manage');
        try {
            $ok = $this->categoryService->delete($id);
            $this->apiResponse(['success' => $ok]);
        } catch (\InvalidArgumentException $e) {
            \App\Core\ResponseHandler::error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Throwable $e) {
            \App\Core\ResponseHandler::error('Kategori silinemedi: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    // ---- units ------------------------------------------------------------

    public function listUnits(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.view');
        $this->apiResponse(['success' => true, 'data' => $this->unitService->list()]);
    }

    public function createUnit(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.category.manage');
        $input = \App\Core\RequestParser::getJsonBody();
        try {
            $id = $this->unitService->create(is_array($input) ? $input : []);
            $this->apiResponse(['success' => true, 'data' => ['unit_id' => $id]]);
        } catch (\InvalidArgumentException $e) {
            \App\Core\ResponseHandler::error($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (\Throwable $e) {
            \App\Core\ResponseHandler::error('Birim oluşturulamadı: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }

    public function deleteUnit(string $id): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.category.manage');
        try {
            $ok = $this->unitService->delete($id);
            $this->apiResponse(['success' => $ok]);
        } catch (\Throwable $e) {
            \App\Core\ResponseHandler::error('Birim silinemedi: ' . $e->getMessage(), 'SERVER_ERROR', 500);
        }
    }
}
