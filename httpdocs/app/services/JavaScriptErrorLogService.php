<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\JavaScriptErrorLogRepository;

/**
 * JavaScript Error Log Service
 * Business logic for JavaScript error logging
 * 
 * @package App\Services
 */
class JavaScriptErrorLogService extends BaseService {
    
    public function __construct(JavaScriptErrorLogRepository $errorLogRepository) {
        parent::__construct($errorLogRepository);
    }
    
    /**
     * Log a JavaScript error
     * @param array $errorData Error data
     * @return bool|string Error log ID on success, false on failure
     */
    public function logError(array $errorData) {
        $logData = [
            'message' => $errorData['message'] ?? 'Unknown error',
            'filename' => $errorData['filename'] ?? 'unknown',
            'lineno' => $errorData['lineno'] ?? 0,
            'colno' => $errorData['colno'] ?? 0,
            'stack' => $errorData['stack'] ?? $errorData['error']->stack ?? '',
            'type' => $errorData['type'] ?? 'javascript_error',
            'url' => $errorData['url'] ?? 'unknown',
            'user_agent' => $errorData['userAgent'] ?? $errorData['user_agent'] ?? 'unknown',
            'user_id' => $errorData['userId'] ?? $errorData['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->repository->create($logData);
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
            if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
                // Table or column doesn't exist, return empty result set
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
            if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
                // Table or column doesn't exist, return empty array
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
     * Delete all error logs
     * WARNING: This will delete ALL error logs. Use with caution!
     * @return int Number of deleted records
     */
    public function deleteAll(): int {
        return $this->repository->deleteAll();
    }
}

