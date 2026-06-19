<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * System Constant Model
 * Handles system constants data operations
 */
class SystemConstant extends \App\Core\Model {
    protected $table = 'system_constants';
    
    /**
     * Get constant by type and key
     */
    public function getByTypeAndKey(string $type, string $key) {
        return $this->query()
            ->where('constant_type', $type)
            ->where('constant_key', $key)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get all constants by type
     */
    public function getByType(string $type): array {
        try {
            // Try with display_order first
            return $this->query()
                ->where('constant_type', $type)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get();
        } catch (\Exception $e) {
            // Fallback if display_order column doesn't exist
            return $this->query()
                ->where('constant_type', $type)
                ->where('is_active', true)
                ->orderBy('constant_key')
                ->get();
        }
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
}

