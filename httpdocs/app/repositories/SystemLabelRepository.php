<?php
namespace App\Repositories;

require_once __DIR__ . '/../core/BaseRepository.php';

/**
 * SystemLabel Repository - MVC/OOP Dynamic Translation System
 * Handles all database operations for system labels/translations
 */
class SystemLabelRepository extends \App\Core\BaseRepository {
    protected $table = 'system_labels';
    protected $primaryKey = 'label_id';
    
    /**
     * Get label by type and key
     * @param string $type
     * @param string $key
     * @return array|null
     */
    public function getByTypeAndKey($type, $key) {
        $sql = "SELECT * FROM {$this->table} WHERE label_type = :type AND label_key = :key LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $type, 'key' => $key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get all labels by type
     * @param string $type
     * @return array
     */
    public function getByType($type) {
        $sql = "SELECT * FROM {$this->table} WHERE label_type = :type ORDER BY label_key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $type]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get label value for specific language
     * @param string $type
     * @param string $key
     * @param string $lang
     * @return string
     */
    public function getValue($type, $key, $lang = 'tr') {
        $label = $this->getByTypeAndKey($type, $key);
        if (!$label) {
            // Return null instead of key to avoid showing English keys
            return null;
        }
        
        $valueField = $lang === 'en' ? 'label_value_en' : 'label_value_tr';
        $value = $label[$valueField] ?? $label['label_value_tr'] ?? null;
        
        // If value is empty, try other language as fallback
        if (empty($value)) {
            $value = $lang === 'en' ? ($label['label_value_tr'] ?? null) : ($label['label_value_en'] ?? null);
        }
        
        // Return null if still empty
        return !empty($value) ? $value : null;
    }
    
    /**
     * Create or update label
     * @param string $type
     * @param string $key
     * @param string $valueTr
     * @param string $valueEn
     * @param string|null $color
     * @return bool
     */
    public function upsert($type, $key, $valueTr, $valueEn = '', $color = null) {
        $labelId = $type . '_' . $key;
        $existing = $this->getByTypeAndKey($type, $key);
        
        $data = [
            'label_id' => $labelId,
            'label_type' => $type,
            'label_key' => $key,
            'label_value_tr' => $valueTr,
            'label_value_en' => $valueEn ?: $valueTr,
            'color' => $color
        ];
        
        if ($existing) {
            // Update existing
            $sql = "UPDATE {$this->table} SET 
                    label_value_tr = :value_tr, 
                    label_value_en = :value_en,
                    color = :color
                    WHERE label_type = :type AND label_key = :key";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'value_tr' => $valueTr,
                'value_en' => $valueEn ?: $valueTr,
                'color' => $color,
                'type' => $type,
                'key' => $key
            ]);
        } else {
            // Insert new
            $sql = "INSERT INTO {$this->table} 
                    (label_id, label_type, label_key, label_value_tr, label_value_en, color) 
                    VALUES (:label_id, :type, :key, :value_tr, :value_en, :color)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'label_id' => $labelId,
                'type' => $type,
                'key' => $key,
                'value_tr' => $valueTr,
                'value_en' => $valueEn ?: $valueTr,
                'color' => $color
            ]);
        }
    }
    
    /**
     * Bulk insert/update labels
     * @param array $labels Array of ['type' => string, 'key' => string, 'tr' => string, 'en' => string, 'color' => string|null]
     * @return int Number of affected rows
     */
    public function bulkUpsert($labels) {
        $count = 0;
        foreach ($labels as $label) {
            if ($this->upsert(
                $label['type'],
                $label['key'],
                $label['tr'],
                $label['en'] ?? '',
                $label['color'] ?? null
            )) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Get all labels as nested array
     * @param string $lang
     * @return array
     */
    public function getAllAsArray($lang = 'tr') {
        $sql = "SELECT label_type, label_key, 
                " . ($lang === 'en' ? 'label_value_en' : 'label_value_tr') . " as value,
                color
                FROM {$this->table} 
                ORDER BY label_type, label_key";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $array = [];
        foreach ($results as $row) {
            $type = $row['label_type'];
            $key = $row['label_key'];
            $value = !empty($row['value']) ? $row['value'] : '';
            // Only add if value is not empty
            if (!empty($value)) {
                $array[$type][$key] = $value;
            }
        }
        
        return $array;
    }
    
    /**
     * Check if label exists
     * @param string $type
     * @param string $key
     * @return bool
     */
    public function exists($type, $key) {
        $label = $this->getByTypeAndKey($type, $key);
        return $label !== null;
    }
    
    /**
     * Get language usage statistics
     * @return array Statistics with counts for Turkish, English, and both
     */
    public function getLanguageStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_labels,
                    SUM(CASE WHEN label_value_tr IS NOT NULL AND label_value_tr != '' THEN 1 ELSE 0 END) as tr_count,
                    SUM(CASE WHEN label_value_en IS NOT NULL AND label_value_en != '' THEN 1 ELSE 0 END) as en_count,
                    SUM(CASE WHEN (label_value_tr IS NOT NULL AND label_value_tr != '') AND (label_value_en IS NOT NULL AND label_value_en != '') THEN 1 ELSE 0 END) as both_count,
                    SUM(CASE WHEN (label_value_tr IS NOT NULL AND label_value_tr != '') AND (label_value_en IS NULL OR label_value_en = '') THEN 1 ELSE 0 END) as tr_only_count,
                    SUM(CASE WHEN (label_value_en IS NOT NULL AND label_value_en != '') AND (label_value_tr IS NULL OR label_value_tr = '') THEN 1 ELSE 0 END) as en_only_count
                FROM {$this->table}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            return [
                'total' => 0,
                'tr_count' => 0,
                'en_count' => 0,
                'both_count' => 0,
                'tr_only_count' => 0,
                'en_only_count' => 0
            ];
        }
        
        return [
            'total' => (int)($result['total_labels'] ?? 0),
            'tr_count' => (int)($result['tr_count'] ?? 0),
            'en_count' => (int)($result['en_count'] ?? 0),
            'both_count' => (int)($result['both_count'] ?? 0),
            'tr_only_count' => (int)($result['tr_only_count'] ?? 0),
            'en_only_count' => (int)($result['en_only_count'] ?? 0)
        ];
    }
}

