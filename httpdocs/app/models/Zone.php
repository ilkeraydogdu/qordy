<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Zone extends \App\Core\Model {
    protected $table = 'zones';
    protected $primaryKey = 'zone_id';
    
    public function getAll(): array {
        return $this->query()
            ->orderBy('name')
            ->get();
    }
    
    public function getById(string $zoneId) {
        return $this->query()
            ->where($this->primaryKey, $zoneId)
            ->first();
    }
    
    public function getByName(string $name) {
        return $this->query()
            ->where('name', $name)
            ->first();
    }
    
    public function create(array $data) {
        require_once __DIR__ . '/../helpers/functions.php';
        if (!isset($data['zone_id'])) {
            $data['zone_id'] = generateId('z');
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        return $this->query()->insert($data);
    }
    
    public function updateZone(string $zoneId, array $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->query()
            ->where($this->primaryKey, $zoneId)
            ->update($data);
    }
    
    public function deleteZone(string $zoneId) {
        return $this->query()
            ->where($this->primaryKey, $zoneId)
            ->delete();
    }
    
    /**
     * Get zones by floor
     * @param string $floor Floor name
     * @return array Zones
     */
    public function getByFloor(string $floor): array {
        return $this->query()
            ->where('floor', $floor)
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Get all unique floors
     * @return array Unique floor names
     */
    public function getAllFloors(): array {
        $results = $this->query()
            ->select(['floor'])
            ->whereNotNull('floor')
            ->where('floor', '!=', '')
            ->distinct()
            ->orderBy('floor')
            ->get();
        return array_column($results, 'floor');
    }
    
    /**
     * Get zones with table count
     * @return array Zones with table count
     */
    public function getWithTableCount(): array {
        $db = $this->getDbConnection();
        $sql = "SELECT z.*, 
                COUNT(t.table_id) as table_count 
                FROM {$this->table} z 
                LEFT JOIN tables t ON t.zone_id = z.zone_id 
                GROUP BY z.zone_id 
                ORDER BY z.floor, z.name";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get zones grouped by floor
     * @return array Zones grouped by floor
     */
    public function getGroupedByFloor(): array {
        $zones = $this->getAll();
        $grouped = [];
        
        foreach ($zones as $zone) {
            $floor = $zone['floor'] ?? 'Diğer';
            if (!isset($grouped[$floor])) {
                $grouped[$floor] = [];
            }
            $grouped[$floor][] = $zone;
        }
        
        return $grouped;
    }
}

