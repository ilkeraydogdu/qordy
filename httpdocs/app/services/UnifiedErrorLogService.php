<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PhpErrorLogRepository;
use App\Repositories\JavaScriptErrorLogRepository;

/**
 * Unified Error Log Service
 * Manages both PHP and JavaScript errors in a unified way
 * 
 * @package App\Services
 */
class UnifiedErrorLogService {
    private $phpErrorLogService;
    private $javascriptErrorLogService;
    
    public function __construct(
        PhpErrorLogService $phpErrorLogService,
        JavaScriptErrorLogService $javascriptErrorLogService
    ) {
        $this->phpErrorLogService = $phpErrorLogService;
        $this->javascriptErrorLogService = $javascriptErrorLogService;
    }
    
    /**
     * Get all error logs (PHP + JavaScript) with pagination
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @param array $filters Filters (type, level, date_from, date_to, user_id, resolved, source)
     * @return array ['logs' => array, 'total' => int, 'page' => int, 'per_page' => int, 'total_pages' => int]
     */
    public function getAllErrorLogs(int $page = 1, int $perPage = 50, array $filters = []): array {
        $source = $filters['source'] ?? 'all'; // 'php', 'javascript', 'all'
        
        $allLogs = [];
        $total = 0;
        
        // Get PHP errors
        if ($source === 'all' || $source === 'php') {
            try {
                $phpFilters = $this->convertFiltersForPhp($filters);
                $phpResult = $this->phpErrorLogService->getErrorLogs($page, $perPage, $phpFilters);
                if (is_array($phpResult) && isset($phpResult['logs'])) {
                    foreach ($phpResult['logs'] as $log) {
                        $log['source'] = 'php';
                        $log['error_type'] = $log['level'] ?? 'ERROR';
                        $allLogs[] = $log;
                    }
                    if ($source === 'php') {
                        $total = $phpResult['total'] ?? 0;
                    }
                }
            } catch (\PDOException $e) {
                // If PHP error service fails due to missing table/column, continue with empty PHP logs
                if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
                    if ($source === 'php') {
                        $total = 0;
                    }
                } else {
                    // Re-throw other database exceptions
                    throw $e;
                }
            } catch (\Exception $e) {
                // If PHP error service fails for other reasons, log and continue
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("PHP Error Log Service Exception: " . $e->getMessage());
                } else {
                    error_log("PHP Error Log Service Exception: " . $e->getMessage());
                }
                if ($source === 'php') {
                    $total = 0;
                }
            }
        }
        
        // Get JavaScript errors
        if ($source === 'all' || $source === 'javascript') {
            try {
                $jsFilters = $this->convertFiltersForJs($filters);
                $jsResult = $this->javascriptErrorLogService->getErrorLogs($page, $perPage, $jsFilters);
                if (is_array($jsResult) && isset($jsResult['logs'])) {
                    foreach ($jsResult['logs'] as $log) {
                        $log['source'] = 'javascript';
                        $log['error_type'] = $log['type'] ?? 'javascript_error';
                        $allLogs[] = $log;
                    }
                    if ($source === 'javascript') {
                        $total = $jsResult['total'] ?? 0;
                    }
                }
            } catch (\PDOException $e) {
                // If JavaScript error service fails due to missing table/column, continue with empty JS logs
                if ($e->getCode() === '42S02' || $e->getCode() === '42S22') {
                    if ($source === 'javascript') {
                        $total = 0;
                    }
                } else {
                    // Re-throw other database exceptions
                    throw $e;
                }
            } catch (\Exception $e) {
                // If JavaScript error service fails for other reasons, log and continue
                error_log("JavaScript Error Log Service Exception: " . $e->getMessage());
                if ($source === 'javascript') {
                    $total = 0;
                }
            }
        }
        
        // If getting all, merge and sort
        if ($source === 'all') {
            // Sort by created_at descending
            usort($allLogs, function($a, $b) {
                $timeA = strtotime($a['created_at'] ?? '1970-01-01');
                $timeB = strtotime($b['created_at'] ?? '1970-01-01');
                return $timeB <=> $timeA;
            });
            
            // Apply pagination
            $total = count($allLogs);
            $offset = ($page - 1) * $perPage;
            $allLogs = array_slice($allLogs, $offset, $perPage);
        }
        
        $totalPages = ceil($total / $perPage);
        
        return [
            'logs' => $allLogs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ];
    }
    
    /**
     * Get unified error statistics
     * @return array Statistics
     */
    public function getUnifiedStatistics(): array {
        $unifiedStats = [
            'php' => [],
            'javascript' => [],
            'total' => [
                'php' => 0,
                'javascript' => 0,
                'all' => 0
            ]
        ];
        
        try {
            $phpStats = $this->phpErrorLogService->getErrorStatistics();
            // Process PHP stats
            if (is_array($phpStats)) {
                foreach ($phpStats as $stat) {
                    $unifiedStats['php'][] = [
                        'type' => $stat['level'] ?? 'UNKNOWN',
                        'count' => (int)($stat['count'] ?? 0),
                        'last_occurrence' => $stat['last_occurrence'] ?? null
                    ];
                    $unifiedStats['total']['php'] += (int)($stat['count'] ?? 0);
                }
            }
        } catch (\PDOException $e) {
            // If PHP error service fails due to missing table/column, continue with empty PHP stats
            if ($e->getCode() !== '42S02' && $e->getCode() !== '42S22') {
                // Re-throw other database exceptions
                throw $e;
            }
        } catch (\Exception $e) {
            // If PHP error service fails for other reasons, log and continue
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PHP Error Statistics Exception: " . $e->getMessage());
            } else {
                error_log("PHP Error Statistics Exception: " . $e->getMessage());
            }
        }
        
        try {
            $jsStats = $this->javascriptErrorLogService->getErrorStatistics();
            // Process JavaScript stats
            if (is_array($jsStats)) {
                foreach ($jsStats as $stat) {
                    $unifiedStats['javascript'][] = [
                        'type' => $stat['type'] ?? 'UNKNOWN',
                        'count' => (int)($stat['count'] ?? 0),
                        'last_occurrence' => $stat['last_occurrence'] ?? null
                    ];
                    $unifiedStats['total']['javascript'] += (int)($stat['count'] ?? 0);
                }
            }
        } catch (\PDOException $e) {
            // If JavaScript error service fails due to missing table/column, continue with empty JS stats
            if ($e->getCode() !== '42S02' && $e->getCode() !== '42S22') {
                // Re-throw other database exceptions
                throw $e;
            }
        } catch (\Exception $e) {
            // If JavaScript error service fails for other reasons, log and continue
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("JavaScript Error Statistics Exception: " . $e->getMessage());
            } else {
                error_log("JavaScript Error Statistics Exception: " . $e->getMessage());
            }
        }
        
        $unifiedStats['total']['all'] = $unifiedStats['total']['php'] + $unifiedStats['total']['javascript'];
        
        return $unifiedStats;
    }
    
    /**
     * Get error trends (daily counts for last N days)
     * @param int $days Number of days
     * @return array Trends
     */
    public function getErrorTrends(int $days = 30): array {
        // This would require additional repository methods
        // For now, return basic structure
        return [
            'php' => [],
            'javascript' => [],
            'dates' => []
        ];
    }
    
    /**
     * Get most problematic files
     * @param int $limit Limit
     * @return array Files with error counts
     */
    public function getMostProblematicFiles(int $limit = 10): array {
        // This would require additional repository methods
        // For now, return basic structure
        return [];
    }
    
    /**
     * Resolve error
     * @param string $source Error source ('php' or 'javascript')
     * @param int $id Error ID
     * @param string $resolvedBy User ID who resolved
     * @return bool Success
     */
    public function resolveError(string $source, int $id, string $resolvedBy): bool {
        if ($source === 'php') {
            return $this->phpErrorLogService->resolveError($id, $resolvedBy);
        } elseif ($source === 'javascript') {
            return $this->javascriptErrorLogService->resolveError($id, $resolvedBy);
        }
        return false;
    }
    
    /**
     * Delete error log
     * @param string $source Error source ('php' or 'javascript')
     * @param int $id Error ID
     * @return bool Success
     */
    public function deleteErrorLog(string $source, int $id): bool {
        if ($source === 'php') {
            return $this->phpErrorLogService->deleteErrorLog($id);
        } elseif ($source === 'javascript') {
            return $this->javascriptErrorLogService->deleteErrorLog($id);
        }
        return false;
    }
    
    /**
     * Cleanup old error logs
     * @param int $days Days to keep (default 30)
     * @return array ['php' => int, 'javascript' => int]
     */
    public function cleanupOldLogs(int $days = 30): array {
        return [
            'php' => $this->phpErrorLogService->cleanupOldLogs($days),
            'javascript' => $this->javascriptErrorLogService->cleanupOldLogs($days)
        ];
    }
    
    /**
     * Resolve multiple errors by their IDs
     * @param array $errorIds Array of error data [['source' => 'php'|'javascript', 'id' => int], ...]
     * @param string $resolvedBy User ID who resolved
     * @return int Number of resolved errors
     */
    public function resolveErrors(array $errorIds, string $resolvedBy = 'system'): int {
        $count = 0;
        foreach ($errorIds as $error) {
            $source = $error['source'] ?? '';
            $id = $error['id'] ?? 0;
            
            if ($id > 0 && in_array($source, ['php', 'javascript'])) {
                if ($this->resolveError($source, $id, $resolvedBy)) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    /**
     * Delete all resolved errors
     * @return int Number of deleted errors
     */
    public function deleteResolvedErrors(): int {
        $phpDeleted = 0;
        $jsDeleted = 0;
        
        // Get resolved PHP errors and delete them
        $phpResolved = $this->phpErrorLogService->getErrorLogs(1, 10000, ['resolved' => true]);
        foreach ($phpResolved['logs'] as $log) {
            if ($this->phpErrorLogService->deleteErrorLog($log['id'])) {
                $phpDeleted++;
            }
        }
        
        // Get resolved JavaScript errors and delete them
        $jsResolved = $this->javascriptErrorLogService->getErrorLogs(1, 10000, ['resolved' => true]);
        foreach ($jsResolved['logs'] as $log) {
            if ($this->javascriptErrorLogService->deleteErrorLog($log['id'])) {
                $jsDeleted++;
            }
        }
        
        return $phpDeleted + $jsDeleted;
    }

    /**
     * Delete all errors (both PHP and JavaScript)
     * WARNING: This will delete ALL error logs. Use with caution!
     * @return int Number of deleted errors
     */
    public function deleteAllErrors(): int {
        $phpDeleted = 0;
        $jsDeleted = 0;
        
        try {
            $phpDeleted = $this->phpErrorLogService->deleteAll();
        } catch (\Exception $e) {
            // If PHP error service fails, continue with JavaScript errors
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Error deleting PHP errors: " . $e->getMessage());
            }
        }
        
        try {
            $jsDeleted = $this->javascriptErrorLogService->deleteAll();
        } catch (\Exception $e) {
            // If JavaScript error service fails, log but continue
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Error deleting JavaScript errors: " . $e->getMessage());
            }
        }
        
        return $phpDeleted + $jsDeleted;
    }
    
    /**
     * Convert unified filters to PHP error log filters
     * @param array $filters Unified filters
     * @return array PHP filters
     */
    private function convertFiltersForPhp(array $filters): array {
        $phpFilters = [];
        
        if (!empty($filters['level'])) {
            $phpFilters['level'] = $filters['level'];
        } elseif (!empty($filters['type'])) {
            $phpFilters['level'] = $filters['type'];
        }
        
        if (!empty($filters['date_from'])) {
            $phpFilters['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $phpFilters['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['user_id'])) {
            $phpFilters['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['resolved'])) {
            $phpFilters['resolved'] = $filters['resolved'];
        }
        
        if (!empty($filters['file'])) {
            $phpFilters['file'] = $filters['file'];
        }

        if (!empty($filters['search'])) {
            $phpFilters['search'] = $filters['search'];
        }

        if (!empty($filters['min_occurrences'])) {
            $phpFilters['min_occurrences'] = $filters['min_occurrences'];
        }
        
        return $phpFilters;
    }
    
    /**
     * Convert unified filters to JavaScript error log filters
     * @param array $filters Unified filters
     * @return array JavaScript filters
     */
    private function convertFiltersForJs(array $filters): array {
        $jsFilters = [];
        
        if (!empty($filters['type'])) {
            $jsFilters['type'] = $filters['type'];
        }
        
        if (!empty($filters['date_from'])) {
            $jsFilters['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $jsFilters['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['user_id'])) {
            $jsFilters['user_id'] = $filters['user_id'];
        }
        
        if (isset($filters['resolved'])) {
            $jsFilters['resolved'] = $filters['resolved'];
        }
        
        return $jsFilters;
    }
}

