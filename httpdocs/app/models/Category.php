<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Category extends \App\Core\Model {
    protected $table = 'categories';
    
    public function getAll() {
        return $this->query()
            ->orderBy('name')
            ->get();
    }
    
    public function getById($categoryId) {
        return $this->query()
            ->where('category_id', $categoryId)
            ->first();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    public function updateCategory($categoryId, $data) {
        return $this->query()
            ->where('category_id', $categoryId)
            ->update($data);
    }

    public function deleteCategory($categoryId) {
        return $this->query()
            ->where('category_id', $categoryId)
            ->delete();
    }
    
    public function getWithKitchenRequirement() {
        return $this->query()
            ->where('requires_kitchen', 1)
            ->orderBy('name')
            ->get();
    }
    
    public function getWithoutKitchenRequirement() {
        return $this->query()
            ->where('requires_kitchen', 0)
            ->orderBy('name')
            ->get();
    }
}