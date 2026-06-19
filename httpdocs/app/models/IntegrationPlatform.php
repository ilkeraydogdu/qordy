<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class IntegrationPlatform extends \App\Core\Model {
    protected $table = 'integration_platforms';
    
    public function getAll() {
        return $this->query()
            ->from('integration_platforms')
            ->orderBy('name')
            ->get();
    }
    
    public function getById($platformId) {
        return $this->query()
            ->from('integration_platforms')
            ->where('platform_id', $platformId)
            ->first();
    }
    
    public function create($data) {
        return $this->query()
            ->from('integration_platforms')
            ->insert($data);
    }

    public function updatePlatform($platformId, $data) {
        return $this->query()
            ->from('integration_platforms')
            ->where('platform_id', $platformId)
            ->update($data);
    }

    public function deletePlatform($platformId) {
        return $this->query()
            ->from('integration_platforms')
            ->where('platform_id', $platformId)
            ->delete();
    }
    
    public function getActivePlatforms() {
        return $this->query()
            ->from('integration_platforms')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();
    }
    
    public function getInactivePlatforms() {
        return $this->query()
            ->from('integration_platforms')
            ->where('is_active', 0)
            ->orderBy('name')
            ->get();
    }
    
    public function activate($platformId) {
        return $this->query()
            ->from('integration_platforms')
            ->where('platform_id', $platformId)
            ->update(['is_active' => 1]);
    }
    
    public function deactivate($platformId) {
        return $this->query()
            ->from('integration_platforms')
            ->where('platform_id', $platformId)
            ->update(['is_active' => 0]);
    }
    
    public function updateSyncInfo($platformId, $orderCount) {
        return $this->query()
            ->from('integration_platforms')
            ->where('platform_id', $platformId)
            ->update([
                'last_sync' => date('Y-m-d H:i:s'),
                'daily_order_count' => $orderCount
            ]);
    }
    
    public function getByName($name) {
        return $this->query()
            ->from('integration_platforms')
            ->where('name', $name)
            ->first();
    }
}