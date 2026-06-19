<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * PHP Error Log Repository
 * Handles database operations for PHP error logs
 * 
 * @package App\Repositories
 */
class PhpErrorLogRepository extends BaseRepository {
    protected $table = 'php_error_logs';
    protected $primaryKey = 'id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Create or update (dedup) an error log entry.
     * If error_hash exists, increments occurrence_count and updates last_occurred_at.
     */
    public function createOrUpdate(array $data) {
        $hash = $data['error_hash'] ?? null;
        if (!$hash) {
            return parent::create($data);
        }

        try {
            $sql = "INSERT INTO {$this->table} 
                    (level, message, file, line, trace, context, request_uri, request_method, ip, user_agent, user_id, error_hash, occurrence_count, last_occurred_at, created_at)
                    VALUES (:level, :message, :file, :line, :trace, :context, :request_uri, :request_method, :ip, :user_agent, :user_id, :error_hash, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        occurrence_count = occurrence_count + 1,
                        last_occurred_at = NOW(),
                        ip = VALUES(ip),
                        user_agent = VALUES(user_agent),
                        user_id = VALUES(user_id),
                        request_uri = VALUES(request_uri)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'level' => $data['level'] ?? 'ERROR',
                'message' => $data['message'] ?? '',
                'file' => $data['file'] ?? null,
                'line' => $data['line'] ?? null,
                'trace' => $data['trace'] ?? null,
                'context' => $data['context'] ?? null,
                'request_uri' => $data['request_uri'] ?? null,
                'request_method' => $data['request_method'] ?? null,
                'ip' => $data['ip'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'error_hash' => $hash
            ]);
            return $this->db->lastInsertId() ?: true;
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                return false;
            }
            return parent::create($data);
        }
    }

    /**
     * Delete noise/suppressed entries matching known patterns
     */
    public function deleteNoiseEntries(): int {
        $noisePatterns = [
            'Route not found: %/ws%',
            '404 Error: Route not found: %/ws%',
            '%wp-admin%',
            '%wp-login%',
            '%wordpress%',
            '%wp-includes%',
            '%wp-content%',
            '%xmlrpc%',
            '%phpmyadmin%',
            '%adminer%',
            'Authorization: Package permission denied%',
            'QR access denied - tenant mismatch%',
            'Authorization: User not logged in for permission check%',
            'ReceiptService::generateReceipt - No order items found%',
            'ReceiptService::generateReceipt duplicate receipt_number retry%',
            'CSRF token validation failed%',
            '%169.254.169.254%',
            '%.well-known/assetlinks%',
            '%.well-known/apple-app-site%',
            '%Route not found: GET /favicon.ico%',
            '%Route not found: GET /robots.txt%',
            '%Route not found: GET /sitemap%',
            'BaseRepository::create - Removed non-existent columns%',
            'UserRepository::findByPin - No matching user found%',
            'Authentication failed: Invalid PIN%',
            'PIN login failed - authentication returned false%',
            '%generatePreparationReceipt - Dedup check failed%',
        ];

        $total = 0;
        foreach ($noisePatterns as $pattern) {
            try {
                $sql = "DELETE FROM {$this->table} WHERE message LIKE :pattern";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['pattern' => $pattern]);
                $total += $stmt->rowCount();
            } catch (\PDOException $e) {
                continue;
            }
        }
        return $total;
    }

    /**
     * Smart cleanup: retention policy + noise removal
     * - Resolved errors: keep 7 days
     * - Unresolved errors: keep 30 days  
     * - Noise: delete immediately
     * - Duplicates with occurrence_count > 100 and resolved: delete
     */
    public function smartCleanup(): array {
        $result = ['noise' => 0, 'old_resolved' => 0, 'old_unresolved' => 0, 'total' => 0];

        try {
            $result['noise'] = $this->deleteNoiseEntries();

            $sql = "DELETE FROM {$this->table} WHERE resolved_at IS NOT NULL AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result['old_resolved'] = $stmt->rowCount();

            $sql = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result['old_unresolved'] = $stmt->rowCount();

            $result['total'] = $result['noise'] + $result['old_resolved'] + $result['old_unresolved'];
        } catch (\PDOException $e) {
            // silently fail
        }

        return $result;
    }

    /**
     * Get error logs with pagination (excludes suppressed by default)
     * @param int $limit Limit
     * @param int $offset Offset
     * @param array $filters Filters (level, date_from, date_to, user_id, resolved)
     * @return array Error logs
     */
    public function getErrorLogs(int $limit = 100, int $offset = 0, array $filters = []): array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE is_suppressed = 0";
            $params = [];

            if (!empty($filters['level'])) {
                $sql .= " AND level = :level";
                $params['level'] = $filters['level'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND created_at >= :date_from";
                $params['date_from'] = $filters['date_from'] . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND created_at <= :date_to";
                $params['date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['user_id'])) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $filters['user_id'];
            }

            if (isset($filters['resolved'])) {
                if ($filters['resolved']) {
                    $sql .= " AND resolved_at IS NOT NULL";
                } else {
                    $sql .= " AND resolved_at IS NULL";
                }
            }

            if (!empty($filters['file'])) {
                $sql .= " AND file LIKE :file";
                $params['file'] = '%' . $filters['file'] . '%';
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (message LIKE :search OR file LIKE :search2)";
                $params['search'] = '%' . $filters['search'] . '%';
                $params['search2'] = '%' . $filters['search'] . '%';
            }

            if (!empty($filters['min_occurrences'])) {
                $sql .= " AND occurrence_count >= :min_occ";
                $params['min_occ'] = (int)$filters['min_occurrences'];
            }

            $sql .= " ORDER BY last_occurred_at DESC, created_at DESC LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Count error logs with filters
     * @param array $filters Filters
     * @return int Count
     */
    public function countErrorLogs(array $filters = []): int {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE is_suppressed = 0";
            $params = [];

            if (!empty($filters['level'])) {
                $sql .= " AND level = :level";
                $params['level'] = $filters['level'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND created_at >= :date_from";
                $params['date_from'] = $filters['date_from'] . ' 00:00:00';
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND created_at <= :date_to";
                $params['date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['user_id'])) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $filters['user_id'];
            }

            if (isset($filters['resolved'])) {
                if ($filters['resolved']) {
                    $sql .= " AND resolved_at IS NOT NULL";
                } else {
                    $sql .= " AND resolved_at IS NULL";
                }
            }

            if (!empty($filters['file'])) {
                $sql .= " AND file LIKE :file";
                $params['file'] = '%' . $filters['file'] . '%';
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (message LIKE :search OR file LIKE :search2)";
                $params['search'] = '%' . $filters['search'] . '%';
                $params['search2'] = '%' . $filters['search'] . '%';
            }

            if (!empty($filters['min_occurrences'])) {
                $sql .= " AND occurrence_count >= :min_occ";
                $params['min_occ'] = (int)$filters['min_occurrences'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * Get error statistics
     * @return array Statistics
     */
    public function getErrorStatistics(): array {
        try {
            $sql = "SELECT 
                        level,
                        COUNT(*) as count,
                        SUM(occurrence_count) as total_occurrences,
                        MAX(COALESCE(last_occurred_at, created_at)) as last_occurrence
                    FROM {$this->table}
                    WHERE is_suppressed = 0
                    GROUP BY level
                    ORDER BY count DESC";
            
            return $this->fetchAll($sql);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Get errors by file
     * @param string $file File path
     * @param int $limit Limit
     * @return array Errors
     */
    public function getErrorsByFile(string $file, int $limit = 50): array {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE file = :file 
                    ORDER BY created_at DESC 
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':file', $file, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return empty array
                return [];
            }
            throw $e; // Re-throw other exceptions
        }
    }

    /**
     * Resolve error
     * @param int $id Error ID
     * @param string $resolvedBy User ID who resolved
     * @return bool Success
     */
    public function resolveError(int $id, string $resolvedBy): bool {
        try {
            $sql = "UPDATE {$this->table} 
                    SET resolved_at = NOW(), resolved_by = :resolved_by 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'id' => $id,
                'resolved_by' => $resolvedBy
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return false
                return false;
            }
            throw $e; // Re-throw other exceptions
        }
    }

    /**
     * Delete old error logs (older than specified days)
     * @param int $days Days to keep
     * @return int Number of deleted records
     */
    public function deleteOldLogs(int $days = 30): int {
        try {
            $sql = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return 0
                return 0;
            }
            throw $e; // Re-throw other exceptions
        }
    }

    /**
     * Delete all error logs
     * WARNING: This will delete ALL error logs. Use with caution!
     * @return int Number of deleted records
     */
    public function deleteAll(): int {
        try {
            $sql = "DELETE FROM {$this->table}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return 0
                return 0;
            }
            throw $e; // Re-throw other exceptions
        }
    }

    /**
     * Get recent errors by level
     * @param string $level Error level
     * @param int $limit Limit
     * @return array Errors
     */
    public function getRecentErrorsByLevel(string $level, int $limit = 10): array {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE level = :level 
                    ORDER BY created_at DESC 
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':level', $level, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return empty array
                return [];
            }
            throw $e; // Re-throw other exceptions
        }
    }
}

