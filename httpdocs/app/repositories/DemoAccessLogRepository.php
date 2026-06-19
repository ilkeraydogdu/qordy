<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class DemoAccessLogRepository extends BaseRepository {
    protected $table = 'demo_access_log';
    protected $primaryKey = 'id';

    public function log(
        string $customerId,
        ?string $userId,
        string $ip,
        string $userAgent,
        string $method,
        string $uri,
        string $eventType = 'login'
    ): bool {
        try {
            if (strlen($uri) > 500) {
                $uri = substr($uri, 0, 497) . '...';
            }
            if (strlen($userAgent) > 500) {
                $userAgent = substr($userAgent, 0, 497) . '...';
            }
            $sql = "INSERT INTO {$this->table}
                (customer_id, user_id, ip, user_agent, request_method, request_uri, event_type)
                VALUES (:customer_id, :user_id, :ip, :user_agent, :request_method, :request_uri, :event_type)";
            return $this->execute($sql, [
                'customer_id' => $customerId,
                'user_id' => $userId,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'request_method' => $method,
                'request_uri' => $uri,
                'event_type' => $eventType,
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('DemoAccessLogRepository::log failed', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
}
