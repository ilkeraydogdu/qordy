<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * FinanceCategoryRepository
 *
 * Tenant-scoped CRUD for `finance_categories`. Backs both supplier and
 * expense category pickers via a single `type` discriminator.
 */
class FinanceCategoryRepository extends BaseRepository {
    protected $table = 'finance_categories';
    protected $primaryKey = 'category_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * List all non-archived categories for the current tenant, optionally
     * filtered by type (SUPPLIER | EXPENSE).
     */
    public function listByType(?string $type = null): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_archived = 0";
        $params = [];

        if ($type !== null && $type !== '') {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }

        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY sort_order ASC, label ASC";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Count how many supplier/expense rows reference this category label so
     * the UI can warn before deleting.
     */
    public function usageCount(string $type, string $label): int {
        $table = $type === 'SUPPLIER' ? 'suppliers' : 'expenses';
        $sql = "SELECT COUNT(*) AS c FROM {$table} WHERE category = :label";
        $params = ['label' => $label];
        $sql = $this->addTenantToWhere($sql, $params);
        $rows = $this->fetchAll($sql, $params);
        return (int)($rows[0]['c'] ?? 0);
    }

    public function findByLabel(string $type, string $label): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE type = :type AND label = :label";
        $params = ['type' => $type, 'label' => $label];
        $sql = $this->addTenantToWhere($sql, $params);
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    /**
     * Rename a category label and optionally keep supplier/expense rows in
     * sync so historical data doesn't lose its grouping.
     */
    public function renameAndPropagate(string $categoryId, string $newLabel, bool $propagate = true): bool {
        $current = $this->getById($categoryId);
        if (!$current) return false;

        $ok = $this->update($categoryId, ['label' => $newLabel]);
        if (!$ok || !$propagate) return $ok;

        $type = $current['type'] ?? null;
        $oldLabel = $current['label'] ?? null;
        if (!$type || !$oldLabel || $oldLabel === $newLabel) return true;

        $targetTable = $type === 'SUPPLIER' ? 'suppliers' : 'expenses';
        $sql = "UPDATE {$targetTable} SET category = :new_label WHERE category = :old_label";
        $params = ['new_label' => $newLabel, 'old_label' => $oldLabel];
        $filter = $this->getTenantFilter();
        if (!empty($filter['where'])) {
            $sql .= " AND " . $filter['where'];
            $params = array_merge($params, $filter['params']);
        }
        $this->execute($sql, $params);
        return true;
    }
}
