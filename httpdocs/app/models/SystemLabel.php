<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class SystemLabel extends \App\Core\Model {
    protected $table = 'system_labels';
    
    public function getByType($type) {
        return $this->query()
            ->where('label_type', $type)
            ->orderBy('label_key')
            ->get();
    }
    
    public function getLabel($type, $key) {
        return $this->query()
            ->where('label_type', $type)
            ->where('label_key', $key)
            ->first();
    }
    
    public function getLabelValue($type, $key, $lang = 'tr') {
        $label = $this->getLabel($type, $key);
        if (!$label) {
            return $key;
        }
        
        $valueField = $lang === 'en' ? 'label_value_en' : 'label_value_tr';
        return $label[$valueField] ?? $label['label_value_tr'] ?? $key;
    }
    
    public function getLabelColor($type, $key) {
        $label = $this->getLabel($type, $key);
        return $label ? ($label['color'] ?? null) : null;
    }
    
    public function getAll() {
        return $this->query()
            ->orderBy('label_type')
            ->orderBy('label_key')
            ->get();
    }
    
    public function create($data) {
        if (!isset($data['label_id'])) {
            $data['label_id'] = $data['label_type'] . '_' . $data['label_key'];
        }
        
        return $this->query()
            ->insert($data);
    }
    
    public function updateLabel($labelId, $data) {
        return $this->query()
            ->where('label_id', $labelId)
            ->update($data);
    }
    
    public function deleteLabel($labelId) {
        return $this->query()
            ->where('label_id', $labelId)
            ->delete();
    }
    
    /**
     * Get all labels as associative array for quick lookup
     * Returns: ['type' => ['key' => 'value']]
     */
    public function getLabelsAsArray($lang = 'tr') {
        $labels = $this->getAll();
        $result = [];
        
        foreach ($labels as $label) {
            $type = $label['label_type'];
            $key = $label['label_key'];
            $valueField = $lang === 'en' ? 'label_value_en' : 'label_value_tr';
            $result[$type][$key] = $label[$valueField] ?? $label['label_value_tr'] ?? $key;
        }
        
        return $result;
    }
    
    /**
     * Get all labels with colors as associative array
     * Returns: ['type' => ['key' => ['value' => '...', 'color' => '...']]]
     */
    public function getLabelsWithColors($lang = 'tr') {
        $labels = $this->getAll();
        $result = [];
        
        foreach ($labels as $label) {
            $type = $label['label_type'];
            $key = $label['label_key'];
            $valueField = $lang === 'en' ? 'label_value_en' : 'label_value_tr';
            
            $result[$type][$key] = [
                'value' => $label[$valueField] ?? $label['label_value_tr'] ?? $key,
                'color' => $label['color'] ?? null
            ];
        }
        
        return $result;
    }
}

