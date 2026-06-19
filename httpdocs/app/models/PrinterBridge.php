<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class PrinterBridge extends \App\Core\Model {
    protected $table = 'printer_bridges';
    
    public function getById(string $bridgeId): ?array {
        return $this->query()
            ->where('bridge_id', $bridgeId)
            ->first();
    }
    
    public function getByBusiness(string $businessId): array {
        return $this->query()
            ->where('tenant_id', $businessId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getByApiKey(string $apiKey): ?array {
        return $this->query()
            ->where('api_key', $apiKey)
            ->first();
    }
    
    public function getOnline(): array {
        return $this->query()
            ->where('status', 'ONLINE')
            ->where('last_heartbeat', '>=', date('Y-m-d H:i:s', strtotime('-2 minutes')))
            ->get();
    }
    
    public function createBridge(array $data): bool {
        if (!isset($data['bridge_id'])) {
            require_once __DIR__ . '/../helpers/functions.php';
            $data['bridge_id'] = generateId('pb');
        }
        return $this->query()->insert($data);
    }
    
    public function updateHeartbeat(string $bridgeId): bool {
        return $this->query()
            ->where('bridge_id', $bridgeId)
            ->update([
                'last_heartbeat' => date('Y-m-d H:i:s'),
                'status' => 'ONLINE',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }
    
    public function updateStatus(string $bridgeId, string $status): bool {
        return $this->query()
            ->where('bridge_id', $bridgeId)
            ->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }
}

