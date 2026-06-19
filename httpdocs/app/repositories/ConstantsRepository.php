<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Constants Repository
 * Handles database operations for system constants
 */
class ConstantsRepository extends BaseRepository {
    protected $table = 'system_constants';
    protected $primaryKey = 'constant_id';
    
    /**
     * Get constant by type and key
     */
    public function getByTypeAndKey(string $type, string $key): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE constant_type = :type AND constant_key = :key AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':type' => $type, ':key' => $key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get all constants by type
     */
    public function getByType(string $type): array {
        // Check if display_order column exists
        try {
            $hasDisplayOrder = \App\Core\DbSchema::hasColumn($this->table, 'display_order');

            if ($hasDisplayOrder) {
                $sql = "SELECT * FROM {$this->table} WHERE constant_type = :type AND is_active = 1 ORDER BY display_order ASC";
            } else {
                $sql = "SELECT * FROM {$this->table} WHERE constant_type = :type AND is_active = 1 ORDER BY constant_key ASC";
            }
        } catch (\Exception $e) {
            // Fallback if check fails
            $sql = "SELECT * FROM {$this->table} WHERE constant_type = :type AND is_active = 1 ORDER BY constant_key ASC";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':type' => $type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get constant value by type and key
     */
    public function getValue(string $type, string $key): ?string {
        $constant = $this->getByTypeAndKey($type, $key);
        return $constant ? $constant['constant_value'] : null;
    }
    
    /**
     * Get constant label (localized)
     */
    public function getLabel(string $type, string $key, string $lang = 'tr'): ?string {
        $constant = $this->getByTypeAndKey($type, $key);
        if (!$constant) {
            return null;
        }
        
        $labelField = $lang === 'en' ? 'label_en' : 'label_tr';
        return $constant[$labelField] ?? $constant['label_tr'] ?? $constant['constant_key'];
    }
    
    /**
     * Get all constants as key-value array
     */
    public function getAsKeyValue(string $type): array {
        $constants = $this->getByType($type);
        $result = [];
        foreach ($constants as $constant) {
            $result[$constant['constant_key']] = $constant['constant_value'];
        }
        return $result;
    }
    
    /**
     * Get all constants as key-label array (localized)
     */
    public function getAsKeyLabel(string $type, string $lang = 'tr'): array {
        $constants = $this->getByType($type);
        $result = [];
        $labelField = $lang === 'en' ? 'label_en' : 'label_tr';
        
        foreach ($constants as $constant) {
            $result[$constant['constant_key']] = $constant[$labelField] ?? $constant['label_tr'] ?? $constant['constant_key'];
        }
        return $result;
    }
    
    /**
     * Get all constant types
     */
    public function getTypes(): array {
        $sql = "SELECT DISTINCT constant_type FROM {$this->table} WHERE is_active = 1 ORDER BY constant_type";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

