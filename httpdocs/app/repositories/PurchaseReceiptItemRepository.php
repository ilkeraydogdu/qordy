<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Tenant-scoped repository for `purchase_receipt_items` — the per-line
 * detail rows attached to a purchase receipt. Each row also powers FIFO
 * batch consumption via `qty_remaining` so waste records can target a
 * specific lot.
 */
class PurchaseReceiptItemRepository extends BaseRepository
{
    protected $table = 'purchase_receipt_items';
    protected $primaryKey = 'item_id';

    public function __construct($database)
    {
        parent::__construct($database);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByReceipt(string $receiptId): array
    {
        $sql = "SELECT pri.*, i.name AS ingredient_name
                FROM {$this->table} pri
                LEFT JOIN ingredients i ON i.ingredient_id = pri.ingredient_id
                WHERE pri.receipt_id = :rid";
        $params = ['rid' => $receiptId];
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $sql .= ' AND ' . $this->tenantWhereForAlias('pri', $tenantFilter['where']);
            $params = array_merge($params, $tenantFilter['params']);
        }
        $sql .= " ORDER BY pri.created_at ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Active lots (qty_remaining > 0) for a given ingredient, FIFO ordered.
     * Used when the waste form asks which batch was wasted.
     * @return array<int, array<string, mixed>>
     */
    public function activeLotsForIngredient(string $ingredientId): array
    {
        $sql = "SELECT pri.*, pr.supplier_id, pr.invoice_no, pr.received_at
                FROM {$this->table} pri
                JOIN purchase_receipts pr ON pr.receipt_id = pri.receipt_id
                WHERE pri.ingredient_id = :iid AND pri.qty_remaining > 0";
        $params = ['iid' => $ingredientId];
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $sql .= ' AND ' . $this->tenantWhereForAlias('pri', $tenantFilter['where']);
            $params = array_merge($params, $tenantFilter['params']);
        }
        $sql .= " ORDER BY pri.created_at ASC";
        return $this->fetchAll($sql, $params);
    }

    public function createItem(array $data): string|false
    {
        $id = $data['item_id'] ?? ('pri_' . bin2hex(random_bytes(10)));
        $data['item_id'] = $id;
        $data['qty_remaining'] = $data['qty_remaining'] ?? $data['qty'] ?? 0;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        return parent::create($data) ? $id : false;
    }

    /**
     * Atomically decrement qty_remaining (used by FIFO consumption from
     * waste / outgoing movements). Returns the actually-decremented amount.
     */
    public function consumeLot(string $itemId, float $amount): float
    {
        $sql = "UPDATE {$this->table}
                SET qty_remaining = GREATEST(0, qty_remaining - :amt)
                WHERE {$this->primaryKey} = :id";
        $params = ['amt' => $amount, 'id' => $itemId];
        $sql = $this->addTenantToWhere($sql, $params);
        $this->execute($sql, $params);
        return $amount;
    }
}
