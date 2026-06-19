<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Per-item low-stock configuration: lets business owners choose what
 * happens when stock drops below threshold for each ingredient/menu item
 * (notify only, notify + disable, disable only, none) along with the
 * channels and recipients to use.
 *
 * This controller is tenant-scoped exactly like Phase 2 siblings: super
 * admins may pass ?business_id= to inspect/modify another tenant.
 */
class LowStockController extends \App\Core\Controller
{
    public function __construct()
    {
        parent::__construct();
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

    private function ensurePermission(string $perm): void
    {
        if (!$this->hasPermission($perm)
            && !$this->hasPermission('stock.view')) {
            \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401);
        }
    }

    /**
     * Render the admin view where users pick per-ingredient alert config.
     */
    public function index(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.low_stock.configure');
        $this->view('admin/low-stock-config', [
            'is_super_admin' => $this->isSuperAdmin(),
        ]);
    }

    /**
     * List ingredients with their current low-stock config fields.
     */
    public function list(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.low_stock.configure');
        $rows = \App\Core\DependencyFactory::getIngredientRepository()->getAll();

        $payload = array_map(static function ($row) {
            return [
                'ingredient_id'     => $row['ingredient_id'] ?? null,
                'name'              => $row['name'] ?? '',
                'unit'              => $row['unit'] ?? '',
                'current_stock'     => (float)($row['current_stock'] ?? 0),
                'min_threshold'     => (float)($row['min_threshold'] ?? 0),
                'low_stock_action'  => $row['low_stock_action']  ?? 'NOTIFY_ONLY',
                'notify_channels'   => $row['notify_channels']   ?? 'in_app',
                'notify_recipients' => $row['notify_recipients'] ?? null,
                'is_available'      => (int)($row['is_available'] ?? 1),
                'is_auto_disabled'  => (int)($row['is_auto_disabled'] ?? 0),
            ];
        }, $rows);

        $this->apiResponse(['success' => true, 'data' => $payload]);
    }

    /**
     * Update config for a single ingredient.
     */
    public function update(string $ingredientId): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.low_stock.configure');

        $body = \App\Core\RequestParser::getJsonBody();
        $updates = [];

        $allowedActions = ['NONE', 'NOTIFY_ONLY', 'NOTIFY_AND_DISABLE', 'DISABLE_ONLY'];
        if (isset($body['low_stock_action'])) {
            $a = strtoupper((string)$body['low_stock_action']);
            if (!in_array($a, $allowedActions, true)) {
                \App\Core\ResponseHandler::error('Geçersiz aksiyon', 'INVALID_ACTION', 400);
            }
            $updates['low_stock_action'] = $a;
        }

        if (isset($body['notify_channels'])) {
            $raw = $body['notify_channels'];
            if (is_array($raw)) $raw = implode(',', $raw);
            $clean = preg_replace('/[^a-z_,]/i', '', (string)$raw) ?: 'in_app';
            $updates['notify_channels'] = strtolower($clean);
        }

        if (array_key_exists('notify_recipients', $body)) {
            $r = $body['notify_recipients'];
            $updates['notify_recipients'] = $r === null
                ? null
                : json_encode(is_array($r) ? $r : [], JSON_UNESCAPED_UNICODE);
        }

        if (isset($body['min_threshold'])) {
            $updates['min_threshold'] = (float)$body['min_threshold'];
        }

        if (isset($body['is_available'])) {
            $updates['is_available'] = !empty($body['is_available']) ? 1 : 0;
            // Manual override clears the auto-disable flag so the dispatcher
            // can re-trigger later if stock drops again.
            if ($updates['is_available'] === 1) {
                $updates['is_auto_disabled'] = 0;
            }
        }

        if (empty($updates)) {
            $this->apiResponse(['success' => true, 'changed' => false]);
            return;
        }

        $ok = \App\Core\DependencyFactory::getIngredientRepository()->update($ingredientId, $updates);
        $this->apiResponse(['success' => (bool)$ok, 'changed' => (bool)$ok]);
    }

    /**
     * Manually trigger the dispatcher. Handy for "send me a test alert now"
     * buttons and admin verification.
     */
    public function triggerNow(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('stock.low_stock.configure');
        $summary = \App\Core\DependencyFactory::getLowStockDispatcher()->run();
        $this->apiResponse(['success' => true, 'data' => $summary]);
    }
}
