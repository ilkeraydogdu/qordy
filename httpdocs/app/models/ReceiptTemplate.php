<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class ReceiptTemplate extends \App\Core\Model {
    protected $table = 'receipt_templates';
    
    public function getAll() {
        return $this->query()
            ->orderBy('is_default', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getById($templateId) {
        return $this->query()
            ->where('template_id', $templateId)
            ->first();
    }
    
    public function getDefault() {
        return $this->query()
            ->where('is_default', 1)
            ->first();
    }
    
    public function create($data) {
        if (!isset($data['template_id'])) {
            $data['template_id'] = generateId('rt');
        }
        return $this->query()->insert($data);
    }
    
    public function updateTemplate($templateId, $data) {
        return $this->query()
            ->where('template_id', $templateId)
            ->update($data);
    }
    
    public function deleteTemplate($templateId) {
        return $this->query()
            ->where('template_id', $templateId)
            ->delete();
    }
    
    public function setAsDefault($templateId) {
        // First, unset all defaults
        $this->query()
            ->where('is_default', 1)
            ->update(['is_default' => 0]);
        
        // Then set the new default
        return $this->query()
            ->where('template_id', $templateId)
            ->update(['is_default' => 1]);
    }
}

