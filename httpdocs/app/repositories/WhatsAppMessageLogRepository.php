<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class WhatsAppMessageLogRepository extends BaseRepository {
    protected $table = 'whatsapp_message_logs';
    protected $primaryKey = 'id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getDailyStats(?string $businessId = null, ?string $date = null): array {
        $date = $date ?: date('Y-m-d');
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN message_type = 'otp' THEN 1 ELSE 0 END) as otp_count,
                    SUM(CASE WHEN message_type = 'test' THEN 1 ELSE 0 END) as test_count,
                    SUM(CASE WHEN message_type = 'template' THEN 1 ELSE 0 END) as template_count,
                    SUM(CASE WHEN message_type = 'text' THEN 1 ELSE 0 END) as text_count,
                    SUM(CASE WHEN message_type = 'marketing' THEN 1 ELSE 0 END) as marketing_count,
                    AVG(api_response_time_ms) as avg_response_time,
                    MAX(api_response_time_ms) as max_response_time,
                    MIN(created_at) as first_message_at,
                    MAX(created_at) as last_message_at
                FROM {$this->table} 
                WHERE DATE(created_at) = :date";
        $params = ['date' => $date];

        if ($businessId) {
            $sql .= " AND tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        return $this->fetchOne($sql, $params) ?: [
            'total' => 0, 'sent' => 0, 'delivered' => 0, 'read_count' => 0,
            'failed' => 0, 'pending' => 0, 'otp_count' => 0, 'test_count' => 0,
            'template_count' => 0, 'text_count' => 0, 'marketing_count' => 0,
            'avg_response_time' => 0, 'max_response_time' => 0,
            'first_message_at' => null, 'last_message_at' => null
        ];
    }

    public function getWeeklyStats(?string $businessId = null): array {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$this->table}
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $params = [];

        if ($businessId) {
            $sql .= " AND tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        $sql .= " GROUP BY DATE(created_at) ORDER BY date ASC";
        return $this->fetchAll($sql, $params);
    }

    public function getMonthlyStats(?string $businessId = null): array {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$this->table}
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $params = [];

        if ($businessId) {
            $sql .= " AND tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        $sql .= " GROUP BY DATE(created_at) ORDER BY date ASC";
        return $this->fetchAll($sql, $params);
    }

    public function getMonthlyTotal(?string $businessId = null): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}
                WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $params = [];

        if ($businessId) {
            $sql .= " AND tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        $result = $this->fetchOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    public function getRecentMessages(?string $businessId = null, int $limit = 20, int $offset = 0, array $filters = []): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        $conditions = [];

        if ($businessId) {
            $conditions[] = "tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        if (!empty($filters['phone'])) {
            $conditions[] = "recipient_phone LIKE :phone";
            $params['phone'] = '%' . $filters['phone'] . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = "status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['message_type'])) {
            $conditions[] = "message_type = :mtype";
            $params['mtype'] = $filters['message_type'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = "created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = "created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(message_content LIKE :search OR error_message LIKE :search2 OR template_name LIKE :search3 OR recipient_phone LIKE :search4)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params['search'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
            $params['search4'] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY created_at DESC LIMIT :lim OFFSET :off";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotalCount(?string $businessId = null, array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        $conditions = [];

        if ($businessId) {
            $conditions[] = "tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        if (!empty($filters['phone'])) {
            $conditions[] = "recipient_phone LIKE :phone";
            $params['phone'] = '%' . $filters['phone'] . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = "status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['message_type'])) {
            $conditions[] = "message_type = :mtype";
            $params['mtype'] = $filters['message_type'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = "created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = "created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(message_content LIKE :search OR error_message LIKE :search2 OR template_name LIKE :search3 OR recipient_phone LIKE :search4)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params['search'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
            $params['search4'] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $result = $this->fetchOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    public function getHourlyDistribution(?string $businessId = null, ?string $date = null): array {
        $date = $date ?: date('Y-m-d');
        $sql = "SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE DATE(created_at) = :date";
        $params = ['date' => $date];

        if ($businessId) {
            $sql .= " AND tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        $sql .= " GROUP BY HOUR(created_at) ORDER BY hour ASC";
        return $this->fetchAll($sql, $params);
    }

    public function getTopRecipients(?string $businessId = null, int $limit = 10): array {
        $sql = "SELECT 
                    recipient_phone,
                    COUNT(*) as message_count,
                    MAX(created_at) as last_sent_at,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM {$this->table}";
        $params = [];

        if ($businessId) {
            $sql .= " WHERE tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        $sql .= " GROUP BY recipient_phone ORDER BY message_count DESC LIMIT " . (int)$limit;
        return $this->fetchAll($sql, $params);
    }

    public function getSuccessRate(?string $businessId = null, int $days = 30): array {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$this->table}
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)";
        $params = ['days' => $days];

        if ($businessId) {
            $sql .= " AND tenant_id = :bid";
            $params['bid'] = $businessId;
        }

        return $this->fetchOne($sql, $params) ?: ['total' => 0, 'success' => 0, 'failed' => 0];
    }

    public function updateMessageStatus(string $metaMessageId, string $status): bool {
        $sql = "UPDATE {$this->table} SET status = :status WHERE meta_message_id = :mid";
        return $this->execute($sql, ['status' => $status, 'mid' => $metaMessageId]);
    }
}
