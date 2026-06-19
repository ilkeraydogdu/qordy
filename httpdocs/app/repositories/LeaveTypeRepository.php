<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Leave Type Repository
 * Handles database operations for leave types
 * 
 * @package App\Repositories
 */
class LeaveTypeRepository extends BaseRepository {
    protected $table = 'leave_types';
    protected $primaryKey = 'leave_type_id';

    /**
     * Find leave type by code
     * @param string $typeCode Type code
     * @return array|null Leave type data or null
     */
    public function findByCode($typeCode) {
        $sql = "SELECT * FROM {$this->table} WHERE type_code = :type_code LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type_code' => $typeCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get active leave types
     * @return array Active leave types
     */
    public function getActive() {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY type_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all leave types
     * @return array All leave types
     */
    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY type_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

