<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Repository for `stock_units`. Units are either global (is_global=1,
 * tenant_id NULL) or tenant-specific. Lookups return both sets for the
 * current tenant so UIs can show a unified dropdown.
 */
class StockUnitRepository extends BaseRepository
{
    protected $table = 'stock_units';
    protected $primaryKey = 'unit_id';

    public function __construct($database)
    {
        parent::__construct($database);
    }

    /**
     * Global + tenant units, tenant units take precedence if codes clash.
     * @return array<int, array<string, mixed>>
     */
    public function listForTenant(?string $tenantId): array
    {
        if ($tenantId === null || $tenantId === '') {
            $sql = "SELECT * FROM {$this->table} WHERE is_global = 1 ORDER BY sort_order ASC, code ASC";
            return $this->fetchAll($sql, []);
        }
        $sql = "SELECT * FROM {$this->table} WHERE is_global = 1 OR tenant_id = :tid ORDER BY is_global DESC, sort_order ASC, code ASC";
        return $this->fetchAll($sql, ['tid' => $tenantId]);
    }

    public function findByCode(string $code, ?string $tenantId): ?array
    {
        if ($tenantId === null) {
            $sql = "SELECT * FROM {$this->table} WHERE code = :c AND is_global = 1 LIMIT 1";
            return $this->fetchOne($sql, ['c' => $code]);
        }
        $sql = "SELECT * FROM {$this->table} WHERE code = :c AND (tenant_id = :tid OR is_global = 1) ORDER BY is_global ASC LIMIT 1";
        return $this->fetchOne($sql, ['c' => $code, 'tid' => $tenantId]);
    }

    public function createUnit(array $data): string|false
    {
        $id = $data['unit_id'] ?? ('unit_' . bin2hex(random_bytes(8)));
        $data['unit_id'] = $id;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        return parent::create($data) ? $id : false;
    }
}
