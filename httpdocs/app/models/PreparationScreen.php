<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class PreparationScreen extends \App\Core\Model {
    protected $table = 'preparation_screens';
    
    public function getAll() {
        return $this->query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }
    
    public function getById($screenId) {
        return $this->query()
            ->where('screen_id', $screenId)
            ->first();
    }
    
    public function getBySlug($slug) {
        return $this->query()
            ->where('slug', $slug)
            ->first();
    }
    
    public function getActive() {
        return $this->query()
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }
    
    public function updateScreen($screenId, $data) {
        return $this->query()
            ->where('screen_id', $screenId)
            ->update($data);
    }
    
    public function deleteScreen($screenId) {
        return $this->query()
            ->where('screen_id', $screenId)
            ->delete();
    }
}

