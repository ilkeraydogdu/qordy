<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\WasteRecordRepository;

/**
 * Waste Record Service
 * Handles waste record-related business logic
 * 
 * @package App\Services
 */
class WasteRecordService extends BaseService {

    /**
     * Constructor
     * @param WasteRecordRepository $repository Waste record repository instance
     */
    public function __construct(WasteRecordRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Get all waste records
     * @return array All waste records
     */
    public function getAll(): array {
        return $this->repository->getAll();
    }

    /**
     * Return waste records for a specific tenant/business, intended for
     * SuperAdmin cross-tenant views. Internally this temporarily pins
     * TenantContext so the repository's built-in tenant filter applies,
     * instead of duplicating SQL elsewhere.
     *
     * @param string $tenantId Target tenant/business id.
     * @return array Waste records for that tenant (empty if none).
     */
    public function getAllForTenant(string $tenantId): array {
        if ($tenantId === '') {
            return [];
        }

        $previous = null;
        if (class_exists('\App\Core\TenantContext')) {
            $previous = \App\Core\TenantContext::get();
            \App\Core\TenantContext::set(['tenant_id' => $tenantId, 'customer_id' => $tenantId]);
        }

        try {
            return $this->repository->getAll();
        } finally {
            if (class_exists('\App\Core\TenantContext')) {
                if ($previous) {
                    \App\Core\TenantContext::set($previous);
                } else {
                    \App\Core\TenantContext::clear();
                }
            }
        }
    }
    
    /**
     * Get waste record by ID
     * @param string $wasteId Waste record ID
     * @return array|null Waste record data or null
     */
    public function getById(string $wasteId): ?array {
        return $this->repository->getById($wasteId);
    }

    /**
     * Create a new waste record AND decrement the associated stock in the
     * same transaction. Either an `ingredient_id` or a `menu_item_id` must
     * be supplied (or both if the business tracks both levels). On success,
     * a `stock_movements` row of type WASTE is also created and linked
     * through `waste_records.stock_movement_id` so we can prevent
     * double-decrement later.
     *
     * @param array $data Waste record data
     * @return bool|string Waste record ID on success, false on failure
     */
    public function createWasteRecord(array $data) {
        if (empty($data['waste_id'])) {
            $data['waste_id'] = generateId('w');
        }

        // Normalize input: ignore empty strings so DB NULLs are preserved.
        foreach ([
            'ingredient_id', 'menu_item_id', 'unit',
            'supplier_id', 'purchase_item_id', 'expense_id',
            'reason_detail', 'location_id',
        ] as $nullableKey) {
            if (isset($data[$nullableKey]) && $data[$nullableKey] === '') {
                $data[$nullableKey] = null;
            }
        }

        $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
        if ($amount <= 0) {
            return false;
        }

        // Ensure tenant_id present so tenant isolation holds.
        if (empty($data['tenant_id'])) {
            $tenantId = null;
            if (class_exists('\App\Core\TenantResolver')) {
                $tenantId = \App\Core\TenantResolver::resolve();
            }
            if ($tenantId) {
                $data['tenant_id'] = $tenantId;
            }
        }

        $menuItemId = $data['menu_item_id'] ?? null;
        $ingredientId = $data['ingredient_id'] ?? null;

        // Phase 2 — enrich via purchase_item (batch lookup) and cost fallback.
        $this->enrichWithBatchAndCost($data, $ingredientId, $menuItemId, $amount);

        // JSON fields — encode if arrays came in.
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = json_encode($data['images'], JSON_UNESCAPED_UNICODE);
        }

        if (!$menuItemId && !$ingredientId) {
            // Nothing to link the waste to; legacy fall-through (just insert).
            return $this->repository->create($data);
        }

        $db = $this->repository->getDbConnection();
        $alreadyInTxn = $db->inTransaction();

        if (!$alreadyInTxn) {
            $db->beginTransaction();
        }

        try {
            $stockMovementId = $this->recordWasteMovement([
                'menu_item_id'     => $menuItemId,
                'ingredient_id'    => $ingredientId,
                'amount'           => $amount,
                'unit'             => $data['unit'] ?? null,
                'reason'           => $data['reason'] ?? 'OTHER',
                'tenant_id'        => $data['tenant_id'] ?? null,
                'waste_id'         => $data['waste_id'],
                'purchase_item_id' => $data['purchase_item_id'] ?? null,
                'unit_cost'        => $data['unit_cost'] ?? null,
                'total_cost'       => $data['total_cost'] ?? null,
            ]);

            if ($stockMovementId) {
                $data['stock_movement_id'] = $stockMovementId;
            }

            $result = $this->repository->create($data);
            if (!$result) {
                throw new \RuntimeException('Failed to persist waste record.');
            }

            // Consume the batch lot if one was selected.
            if (!empty($data['purchase_item_id'])) {
                try {
                    \App\Core\DependencyFactory::getPurchaseReceiptItemRepository()
                        ->consumeLot((string)$data['purchase_item_id'], (float)$amount);
                } catch (\Throwable $e) {
                    // Non-fatal; log and continue.
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('WasteRecord: lot consumption failed', [
                            'error' => $e->getMessage(),
                            'purchase_item_id' => $data['purchase_item_id'],
                        ]);
                    }
                }
            }

            // Mirror the waste cost into expenses with source_type=WASTE so
            // the P&L view includes it automatically. See FinanceService.
            if (!empty($data['total_cost']) && (float)$data['total_cost'] > 0) {
                try {
                    $expenseId = \App\Core\DependencyFactory::getFinanceService()
                        ->createExpenseFromWaste((string)$data['waste_id'], [
                            'tenant_id'    => $data['tenant_id'] ?? null,
                            'supplier_id'  => $data['supplier_id'] ?? null,
                            'amount'       => (float)$data['total_cost'],
                            'reason'       => $data['reason'] ?? 'OTHER',
                            'reason_detail'=> $data['reason_detail'] ?? null,
                            'waste_date'   => $data['waste_date'] ?? date('Y-m-d H:i:s'),
                        ]);
                    if ($expenseId) {
                        $this->repository->update((string)$data['waste_id'], ['expense_id' => $expenseId]);
                    }
                } catch (\Throwable $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('WasteRecord: expense link failed', [
                            'error'    => $e->getMessage(),
                            'waste_id' => $data['waste_id'],
                        ]);
                    }
                }
            }

            if (!$alreadyInTxn) {
                $db->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if (!$alreadyInTxn && $db->inTransaction()) {
                $db->rollBack();
            }
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('WasteRecordService::createWasteRecord failed', [
                    'error' => $e->getMessage(),
                    'menu_item_id' => $menuItemId,
                    'ingredient_id' => $ingredientId,
                ]);
            }
            return false;
        }
    }

    /**
     * Phase 2 enrichment: if a purchase_item_id was selected, pull its
     * supplier_id and unit_cost into the waste record so supplier
     * performance analytics have everything they need. Falls back to
     * ingredient.unit_cost / menu_item.cost when no batch is given.
     *
     * @param array $data (by reference) — mutated with inferred fields.
     */
    private function enrichWithBatchAndCost(array &$data, ?string $ingredientId, ?string $menuItemId, float $amount): void
    {
        $unitCost = isset($data['unit_cost']) ? (float)$data['unit_cost'] : 0.0;

        if (!empty($data['purchase_item_id'])) {
            try {
                $itemRepo = \App\Core\DependencyFactory::getPurchaseReceiptItemRepository();
                $lot = $itemRepo->findById((string)$data['purchase_item_id']);
                if ($lot) {
                    if (empty($data['supplier_id'])) {
                        // purchase_receipts carries supplier; fetch via receipt.
                        try {
                            $receipt = \App\Core\DependencyFactory::getPurchaseReceiptRepository()
                                ->getById((string)$lot['receipt_id']);
                            if ($receipt && !empty($receipt['supplier_id'])) {
                                $data['supplier_id'] = $receipt['supplier_id'];
                            }
                        } catch (\Throwable $e) { /* ignore */ }
                    }
                    if ($unitCost <= 0 && isset($lot['unit_cost'])) {
                        $unitCost = (float)$lot['unit_cost'];
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        if ($unitCost <= 0 && $ingredientId) {
            try {
                $ing = \App\Core\DependencyFactory::getIngredientRepository()->getById($ingredientId);
                if ($ing && !empty($ing['unit_cost'])) {
                    $unitCost = (float)$ing['unit_cost'];
                }
                if ($ing && empty($data['supplier_id']) && !empty($ing['supplier_id'])) {
                    $data['supplier_id'] = $ing['supplier_id'];
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        if ($unitCost <= 0 && $menuItemId) {
            try {
                $mi = \App\Core\DependencyFactory::getMenuItemRepository()->findById($menuItemId);
                if ($mi && !empty($mi['cost'])) {
                    $unitCost = (float)$mi['cost'];
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        if ($unitCost > 0) {
            $data['unit_cost'] = $unitCost;
            if (empty($data['total_cost'])) {
                $data['total_cost'] = round($unitCost * $amount, 4);
            }
        }
    }

    /**
     * Delegate to StockMovementService to record a WASTE movement and
     * decrement the owning item's stock. Returns the created movement id
     * (or null when no link could be made).
     *
     * @param array $payload
     * @return string|null
     */
    private function recordWasteMovement(array $payload): ?string
    {
        try {
            $stockService = \App\Core\DependencyFactory::getStockMovementService();
        } catch (\Throwable $e) {
            return null;
        }

        $common = [
            'movement_type' => 'WASTE',
            'quantity'     => $payload['amount'],
            'unit'         => $payload['unit'] ?? 'adet',
            'reference_type' => 'WASTE_RECORD',
            'reference_id'   => $payload['waste_id'],
            'notes'        => 'Fire kaydı: ' . ($payload['reason'] ?? 'OTHER'),
        ];
        if (!empty($payload['tenant_id'])) {
            $common['tenant_id'] = $payload['tenant_id'];
        }
        if (!empty($payload['purchase_item_id'])) {
            $common['purchase_item_id'] = $payload['purchase_item_id'];
        }
        if (isset($payload['unit_cost']) && $payload['unit_cost'] !== null) {
            $common['unit_cost'] = $payload['unit_cost'];
        }
        if (isset($payload['total_cost']) && $payload['total_cost'] !== null) {
            $common['total_cost'] = $payload['total_cost'];
        }

        if (!empty($payload['menu_item_id'])) {
            return $stockService->recordMovement(array_merge($common, [
                'item_type' => 'MENU_ITEM',
                'item_id'   => $payload['menu_item_id'],
            ])) ?: null;
        }

        if (!empty($payload['ingredient_id'])) {
            return $stockService->recordMovement(array_merge($common, [
                'item_type' => 'INGREDIENT',
                'item_id'   => $payload['ingredient_id'],
            ])) ?: null;
        }

        return null;
    }
    
    /**
     * Update waste record
     * @param string $wasteId Waste record ID
     * @param array $data Waste record data to update
     * @return bool Success
     */
    public function updateWasteRecord(string $wasteId, array $data): bool {
        return $this->repository->update($wasteId, $data);
    }
    
    /**
     * Delete waste record
     * @param string $wasteId Waste record ID
     * @return bool Success
     */
    public function deleteWasteRecord(string $wasteId): bool {
        return $this->repository->deleteWasteRecord($wasteId);
    }
    
    /**
     * Get waste records by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Waste records
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Get waste records by reason
     * @param string $reason Waste reason
     * @return array Waste records
     */
    public function getByReason(string $reason): array {
        return $this->repository->getByReason($reason);
    }
    
    /**
     * Get total waste amount by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return float Total waste amount
     */
    public function getTotalWasteByDateRange(string $startDate, string $endDate): float {
        return $this->repository->getTotalWasteByDateRange($startDate, $endDate);
    }
    
    /**
     * Get total waste amount by reason
     * @param string $reason Waste reason
     * @param string|null $startDate Optional start date (Y-m-d)
     * @param string|null $endDate Optional end date (Y-m-d)
     * @return float Total waste amount
     */
    public function getTotalWasteByReason(string $reason, ?string $startDate = null, ?string $endDate = null): float {
        return $this->repository->getTotalWasteByReason($reason, $startDate, $endDate);
    }
    
    /**
     * Get waste amount by ingredient
     * @param string $ingredientId Ingredient ID
     * @param string|null $startDate Optional start date (Y-m-d)
     * @param string|null $endDate Optional end date (Y-m-d)
     * @return float Total waste amount
     */
    public function getWasteByIngredient(string $ingredientId, ?string $startDate = null, ?string $endDate = null): float {
        return $this->repository->getWasteByIngredient($ingredientId, $startDate, $endDate);
    }
}

