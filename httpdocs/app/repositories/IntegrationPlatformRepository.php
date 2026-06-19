<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class IntegrationPlatformRepository extends BaseRepository {
    protected $table = 'integration_platforms';
    protected $primaryKey = 'platform_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY name ASC";
        return $this->fetchAll($sql);
    }

    public function getById(string $platformId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $platformId]);
    }

    public function getActivePlatforms(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC";
        return $this->fetchAll($sql);
    }

    public function getInactivePlatforms(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 0 ORDER BY name ASC";
        return $this->fetchAll($sql);
    }

    public function activate(string $platformId): bool {
        $sql = "UPDATE {$this->table} SET is_active = 1 WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, ['id' => $platformId]);
    }

    public function deactivate(string $platformId): bool {
        $sql = "UPDATE {$this->table} SET is_active = 0 WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, ['id' => $platformId]);
    }

    public function updateSyncInfo(string $platformId, int $orderCount): bool {
        $sql = "UPDATE {$this->table} SET last_sync = :last_sync, daily_order_count = :order_count WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, [
            'id' => $platformId,
            'last_sync' => date('Y-m-d H:i:s'),
            'order_count' => $orderCount
        ]);
    }

    public function getByName(string $name): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE name = :name LIMIT 1";
        return $this->fetchOne($sql, ['name' => $name]);
    }
}

