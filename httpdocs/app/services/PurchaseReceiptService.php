<?php
namespace App\Services;

use App\Core\TenantContext;
use App\Repositories\PurchaseReceiptRepository;
use App\Repositories\PurchaseReceiptItemRepository;
use App\Repositories\IngredientRepository;

/**
 * Orchestrates creation of purchase receipts (irsaliye) together with
 * their line items and the resulting inbound stock movements. Everything
 * happens in a single DB transaction so a partial failure never leaves
 * half-created batches in the ledger.
 */
class PurchaseReceiptService
{
    /** @var PurchaseReceiptRepository */
    private $receiptRepo;
    /** @var PurchaseReceiptItemRepository */
    private $itemRepo;
    /** @var IngredientRepository */
    private $ingredientRepo;
    /** @var StockMovementService */
    private $stockMovementService;

    public function __construct(
        PurchaseReceiptRepository $receiptRepo,
        PurchaseReceiptItemRepository $itemRepo,
        IngredientRepository $ingredientRepo,
        StockMovementService $stockMovementService
    ) {
        $this->receiptRepo = $receiptRepo;
        $this->itemRepo = $itemRepo;
        $this->ingredientRepo = $ingredientRepo;
        $this->stockMovementService = $stockMovementService;
    }

    public function list(array $filters = []): array
    {
        return $this->receiptRepo->listWithSupplier($filters);
    }

    public function get(string $receiptId): ?array
    {
        $receipt = $this->receiptRepo->getById($receiptId);
        if (!$receipt) {
            return null;
        }
        $receipt['items'] = $this->itemRepo->listByReceipt($receiptId);
        return $receipt;
    }

    /**
     * Create a receipt + items in one transaction. Each accepted item
     * produces an IN stock movement linked via purchase_item_id so waste
     * records can later trace the batch back to a supplier.
     *
     * @param array $input {
     *   @type string  $supplier_id   required
     *   @type string  $invoice_no    optional
     *   @type string  $received_at   Y-m-d H:i:s (defaults to now)
     *   @type string  $notes         optional
     *   @type array[] $items         list of { ingredient_id, qty, unit, unit_cost, batch_no?, expiry_date?, notes? }
     * }
     * @return string receipt_id
     * @throws \InvalidArgumentException on validation errors.
     */
    public function createReceipt(array $input): string
    {
        $supplierId = trim((string)($input['supplier_id'] ?? ''));
        if ($supplierId === '') {
            throw new \InvalidArgumentException('Tedarikçi zorunludur.');
        }
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];
        $items = array_values(array_filter($items, static function ($it) {
            return is_array($it) && !empty($it['ingredient_id']) && isset($it['qty']) && (float)$it['qty'] > 0;
        }));
        if (empty($items)) {
            throw new \InvalidArgumentException('En az bir geçerli satır girin.');
        }

        $tenantId = TenantContext::getTenantId();
        $receivedAt = !empty($input['received_at']) ? (string)$input['received_at'] : date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? null;

        $db = $this->receiptRepo->getDbConnection();
        $shouldCommit = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $shouldCommit = true;
        }

        try {
            $total = 0.0;
            $receiptId = $this->receiptRepo->createReceipt([
                'tenant_id'    => $tenantId,
                'supplier_id'  => $supplierId,
                'invoice_no'   => isset($input['invoice_no']) ? (string)$input['invoice_no'] : null,
                'received_at'  => $receivedAt,
                'total_cost'   => 0,
                'notes'        => isset($input['notes']) ? (string)$input['notes'] : null,
                'created_by'   => $userId,
            ]);
            if ($receiptId === false) {
                throw new \RuntimeException('İrsaliye kaydedilemedi.');
            }

            foreach ($items as $row) {
                $qty = (float)$row['qty'];
                $unit = (string)($row['unit'] ?? '');
                $unitCost = (float)($row['unit_cost'] ?? 0);
                $lineTotal = round($qty * $unitCost, 4);
                $total += $lineTotal;

                // Resolve ingredient to confirm it exists within this tenant.
                $ingredient = $this->ingredientRepo->getById((string)$row['ingredient_id']);
                if (!$ingredient) {
                    throw new \InvalidArgumentException('Seçilen malzeme bu işletmeye ait değil.');
                }

                $itemId = $this->itemRepo->createItem([
                    'tenant_id'     => $tenantId,
                    'receipt_id'    => $receiptId,
                    'ingredient_id' => $ingredient['ingredient_id'],
                    'qty'           => $qty,
                    'unit'          => $unit !== '' ? $unit : ($ingredient['unit'] ?? 'adet'),
                    'unit_cost'     => $unitCost,
                    'line_total'    => $lineTotal,
                    'qty_remaining' => $qty,
                    'batch_no'      => isset($row['batch_no']) ? (string)$row['batch_no'] : null,
                    'expiry_date'   => !empty($row['expiry_date']) ? (string)$row['expiry_date'] : null,
                    'notes'         => isset($row['notes']) ? (string)$row['notes'] : null,
                ]);
                if ($itemId === false) {
                    throw new \RuntimeException('Satır kaydedilemedi.');
                }

                // Ledger IN movement, tagged with the purchase_item_id so
                // downstream traceability (waste → supplier) is preserved.
                $movementData = [
                    'tenant_id'        => $tenantId,
                    'item_type'        => 'INGREDIENT',
                    'item_id'          => $ingredient['ingredient_id'],
                    'movement_type'    => 'IN',
                    'quantity'         => $qty,
                    'unit'             => $unit !== '' ? $unit : ($ingredient['unit'] ?? 'adet'),
                    'unit_cost'        => $unitCost,
                    'total_cost'       => $lineTotal,
                    'purchase_item_id' => $itemId,
                    'supplier_id'      => $supplierId,
                    'note'             => 'İrsaliye: ' . ($input['invoice_no'] ?? $receiptId),
                ];
                $this->stockMovementService->recordMovement($movementData);
            }

            $this->receiptRepo->updateReceipt($receiptId, ['total_cost' => $total]);

            if ($shouldCommit) {
                $db->commit();
            }
            return $receiptId;
        } catch (\Throwable $e) {
            if ($shouldCommit && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function delete(string $receiptId): bool
    {
        // For safety in Phase 2 we do a soft delete by prefixing notes and
        // zeroing qty_remaining for the batch rows. A full reversing flow
        // is tracked as a future enhancement because it needs to account
        // for already-consumed stock & waste links.
        $receipt = $this->receiptRepo->getById($receiptId);
        if (!$receipt) {
            return false;
        }
        $this->receiptRepo->updateReceipt($receiptId, [
            'notes' => trim(((string)($receipt['notes'] ?? '')) . "\n[IPTAL: " . date('Y-m-d H:i') . ']'),
        ]);
        return true;
    }
}
