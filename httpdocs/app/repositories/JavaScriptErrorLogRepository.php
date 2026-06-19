<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * JavaScript Error Log Repository
 * Handles database operations for JavaScript error logs
 * 
 * @package App\Repositories
 */
class JavaScriptErrorLogRepository extends BaseRepository {
    protected $table = 'javascript_error_logs';
    protected $primaryKey = 'id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get error logs with pagination
     * @param int $limit Limit
     * @param int $offset Offset
     * @param array $filters Filters (type, date_from, date_to, user_id, resolved)
     * @return array Error logs
     */
    public function getErrorLogs(int $limit = 100, int $offset = 0, array $filters = [], bool $retryWithoutResolved = false): array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['type'])) {
                $sql .= " AND type = :type";
                $params['type'] = $filters['type'];
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

            // Only add resolved filter if column exists (skip if retrying without resolved)
            if (isset($filters['resolved']) && !$retryWithoutResolved) {
                if ($filters['resolved']) {
                    $sql .= " AND resolved_at IS NOT NULL";
                } else {
                    $sql .= " AND resolved_at IS NULL";
                }
            }

            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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
                // Table doesn't exist, return empty array
                return [];
            }
            if ($e->getCode() === '42S22') {
                // Column doesn't exist (likely resolved_at), retry without resolved filter
                if (isset($filters['resolved']) && !$retryWithoutResolved) {
                    unset($filters['resolved']);
                    return $this->getErrorLogs($limit, $offset, $filters, true);
                }
                return [];
            }
            throw $e; // Re-throw other exceptions
        }
    }

    /**
     * Count error logs with filters
     * @param array $filters Filters
     * @return int Count
     */
    public function countErrorLogs(array $filters = [], bool $retryWithoutResolved = false): int {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['type'])) {
                $sql .= " AND type = :type";
                $params['type'] = $filters['type'];
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

            // Only add resolved filter if column exists (skip if retrying without resolved)
            if (isset($filters['resolved']) && !$retryWithoutResolved) {
                if ($filters['resolved']) {
                    $sql .= " AND resolved_at IS NOT NULL";
                } else {
                    $sql .= " AND resolved_at IS NULL";
                }
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return 0
                return 0;
            }
            if ($e->getCode() === '42S22') {
                // Column doesn't exist (likely resolved_at), retry without resolved filter
                if (isset($filters['resolved']) && !$retryWithoutResolved) {
                    unset($filters['resolved']);
                    return $this->countErrorLogs($filters, true);
                }
                return 0;
            }
            throw $e; // Re-throw other exceptions
        }
    }

    /**
     * Get error statistics
     * @return array Statistics
     */
    public function getErrorStatistics(): array {
        try {
            $sql = "SELECT 
                        type,
                        COUNT(*) as count,
                        MAX(created_at) as last_occurrence
                    FROM {$this->table}
                    GROUP BY type
                    ORDER BY count DESC";
            
            return $this->fetchAll($sql);
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
            if ($e->getCode() === '42S22') {
                // Column doesn't exist (resolved_at/resolved_by), return false (feature not supported for this table)
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
}

