<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Tenant-scoped repository for `purchase_receipts` (irsaliye/fatura başlık).
 * Line items live in PurchaseReceiptItemRepository so each aggregate has a
 * clean, narrow mapper.
 */
class PurchaseReceiptRepository extends BaseRepository
{
    protected $table = 'purchase_receipts';
    protected $primaryKey = 'receipt_id';

    public function __construct($database)
    {
        parent::__construct($database);
    }

    /**
     * Paginated list with supplier name joined. Tenant filter is applied
     * through addTenantToWhere so the "pr" alias needs an override in
     * that helper — we instead add the condition manually here because
     * BaseRepository's helper uses the bare table name.
     */
    public function listWithSupplier(array $filters = []): array
    {
        $sql = "SELECT pr.*, s.name AS supplier_name
                FROM {$this->table} pr
                LEFT JOIN suppliers s ON s.supplier_id = pr.supplier_id
                WHERE 1=1";
        $params = [];

        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $sql .= ' AND ' . $this->tenantWhereForAlias('pr', $tenantFilter['where']);
            $params = array_merge($params, $tenantFilter['params']);
        }
        if (!empty($filters['supplier_id'])) {
            $sql .= ' AND pr.supplier_id = :sid';
            $params['sid'] = $filters['supplier_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND pr.received_at >= :df';
            $params['df'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND pr.received_at <= :dt';
            $params['dt'] = $filters['date_to'];
        }
        $sql .= ' ORDER BY pr.received_at DESC, pr.created_at DESC LIMIT 500';
        return $this->fetchAll($sql, $params);
    }

    public function getById(string $receiptId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $receiptId];
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        return $this->fetchOne($sql, $params);
    }

    public function createReceipt(array $data): string|false
    {
        $id = $data['receipt_id'] ?? ('pr_' . bin2hex(random_bytes(10)));
        $data['receipt_id'] = $id;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        return parent::create($data) ? $id : false;
    }

    public function updateReceipt(string $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return parent::update($id, $data);
    }
}
