<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PhpErrorLogRepository;

/**
 * PHP Error Log Service
 * Business logic for PHP error logging
 * 
 * @package App\Services
 */
class PhpErrorLogService extends BaseService {
    
    public function __construct(PhpErrorLogRepository $errorLogRepository) {
        parent::__construct($errorLogRepository);
    }
    
    /**
     * Log a PHP error
     * @param array $errorData Error data
     * @return bool|string Error log ID on success, false on failure
     */
    public function logError(array $errorData) {
        $logData = [
            'level' => $errorData['level'] ?? 'ERROR',
            'message' => $errorData['message'] ?? 'Unknown error',
            'file' => $errorData['file'] ?? null,
            'line' => $errorData['line'] ?? null,
            'trace' => $errorData['trace'] ?? $errorData['stack'] ?? null,
            'context' => !empty($errorData['context']) ? json_encode($errorData['context']) : null,
            'request_uri' => $errorData['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? null),
            'request_method' => $errorData['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? null),
            'ip' => $errorData['ip'] ?? ($this->getClientIP() ?? null),
            'user_agent' => $errorData['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'user_id' => $errorData['user_id'] ?? ($_SESSION['user_id'] ?? null),
            'error_hash' => $errorData['error_hash'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        if (!empty($logData['error_hash'])) {
            return $this->repository->createOrUpdate($logData);
        }

        return $this->repository->create($logData);
    }
    
    /**
     * Log an exception
     * @param \Throwable $exception Exception object
     * @param array $context Additional context
     * @return bool|string Error log ID on success, false on failure
     */
    public function logException(\Throwable $exception, array $context = []) {
        return $this->logError([
            'level' => 'ERROR',
            'message' => get_class($exception) . ': ' . $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context
        ]);
    }
    
    /**
     * Get error logs with pagination
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @param array $filters Filters
     * @return array ['logs' => array, 'total' => int, 'page' => int, 'per_page' => int, 'total_pages' => int]
     */
    public function getErrorLogs(int $page = 1, int $perPage = 50, array $filters = []): array {
        try {
            $offset = ($page - 1) * $perPage;
            $logs = $this->repository->getErrorLogs($perPage, $offset, $filters);
            $total = $this->repository->countErrorLogs($filters);
            $totalPages = ceil($total / $perPage);

            return [
                'logs' => $logs,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages
            ];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return empty result set
                return [
                    'logs' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => 0
                ];
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
            return $this->repository->getErrorStatistics();
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
     * @param int $id Error log ID
     * @param string $resolvedBy User ID who resolved
     * @return bool Success
     */
    public function resolveError(int $id, string $resolvedBy): bool {
        return $this->repository->resolveError($id, $resolvedBy);
    }
    
    /**
     * Delete error log by ID
     * @param int $id Error log ID
     * @return bool Success
     */
    public function deleteErrorLog(int $id): bool {
        return $this->repository->delete($id);
    }
    
    /**
     * Delete old error logs
     * @param int $days Days to keep (default 30)
     * @return int Number of deleted records
     */
    public function cleanupOldLogs(int $days = 30): int {
        return $this->repository->deleteOldLogs($days);
    }

    /**
     * Smart cleanup: removes noise, old resolved, and applies retention policy
     */
    public function smartCleanup(): array {
        return $this->repository->smartCleanup();
    }

    /**
     * Delete all error logs
     * WARNING: This will delete ALL error logs. Use with caution!
     * @return int Number of deleted records
     */
    public function deleteAll(): int {
        return $this->repository->deleteAll();
    }

    /**
     * Get errors by file
     * @param string $file File path
     * @param int $limit Limit
     * @return array Errors
     */
    public function getErrorsByFile(string $file, int $limit = 50): array {
        try {
            return $this->repository->getErrorsByFile($file, $limit);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return empty array
                return [];
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
            return $this->repository->getRecentErrorsByLevel($level, $limit);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02') {
                // Table doesn't exist, return empty array
                return [];
            }
            throw $e; // Re-throw other exceptions
        }
    }

    /**
     * Get client IP address (handles proxies)
     * @return string IP address
     */
    private function getClientIP(): string {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

