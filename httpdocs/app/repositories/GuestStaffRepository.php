<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

class GuestStaffRepository extends BaseRepository {
    protected $table = 'guest_staff';
    protected $primaryKey = 'guest_staff_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all active guest staff, scoped to the current tenant via the
     * parent BaseRepository filter. Phase 2 added tenant_id column — older
     * rows where tenant_id IS NULL are still returned for backwards-compat
     * until backfilled.
     * @return array
     */
    public function getActive(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= ' ORDER BY first_name, last_name';
        return $this->fetchAll($sql, $params);
    }

    /**
     * Find by phone within the current tenant scope.
     * @param string $phone
     * @return array|null
     */
    public function findByPhone(string $phone): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE phone = :phone";
        $params = ['phone' => $phone];
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= ' LIMIT 1';
        return $this->fetchOne($sql, $params);
    }

    /**
     * Search by name or phone within the current tenant scope.
     * @param string $search
     * @return array
     */
    public function search(string $search): array {
        $sql = "SELECT * FROM {$this->table}
                WHERE (first_name LIKE :search OR last_name LIKE :search OR phone LIKE :search OR tc_no LIKE :search)
                  AND is_active = 1";
        $params = ['search' => "%{$search}%"];
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= ' ORDER BY first_name, last_name';
        return $this->fetchAll($sql, $params);
    }
}

