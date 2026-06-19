<?php
namespace App\Services;

use App\Core\Logger;

/**
 * Query Profiler
 * Detects N+1 query problems and logs query performance
 */
class QueryProfiler {
    private static $queries = [];
    private static $startTime = null;
    private static $enabled = true;
    
    /**
     * Start profiling
     */
    public static function start(): void {
        if (!self::$enabled) {
            return;
        }
        
        self::$startTime = microtime(true);
        self::$queries = [];
    }
    
    /**
     * Log a query
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @param float $executionTime Execution time in seconds
     */
    public static function logQuery(string $sql, float $executionTime, array $params = []): void {
        if (!self::$enabled) {
            return;
        }
        
        self::$queries[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => $executionTime,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Detect N+1 query problems
     * @return array Detected N+1 problems
     */
    public static function detectNPlusOne(): array {
        if (empty(self::$queries)) {
            return [];
        }
        
        $problems = [];
        $queryPatterns = [];
        
        // Group queries by pattern
        foreach (self::$queries as $query) {
            $pattern = self::extractQueryPattern($query['sql']);
            if (!isset($queryPatterns[$pattern])) {
                $queryPatterns[$pattern] = [];
            }
            $queryPatterns[$pattern][] = $query;
        }
        
        // Detect patterns that appear many times (potential N+1)
        foreach ($queryPatterns as $pattern => $queries) {
            if (count($queries) > 10) { // Threshold for N+1 detection
                $problems[] = [
                    'pattern' => $pattern,
                    'count' => count($queries),
                    'total_time' => array_sum(array_column($queries, 'time')),
                    'avg_time' => array_sum(array_column($queries, 'time')) / count($queries),
                    'suggestion' => 'Consider using eager loading (with()) or batch loading to reduce queries'
                ];
            }
        }
        
        return $problems;
    }
    
    /**
     * Extract query pattern from SQL
     * @param string $sql SQL query
     * @return string Query pattern
     */
    private static function extractQueryPattern(string $sql): string {
        // Normalize SQL by removing values
        $pattern = preg_replace('/\?|:\w+/', '?', $sql);
        $pattern = preg_replace('/\d+/', '?', $pattern);
        $pattern = preg_replace('/\s+/', ' ', $pattern);
        return trim($pattern);
    }
    
    /**
     * Get query statistics
     * @return array Query statistics
     */
    public static function getStats(): array {
        if (empty(self::$queries)) {
            return [
                'total_queries' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'slow_queries' => []
            ];
        }
        
        $totalTime = array_sum(array_column(self::$queries, 'time'));
        $avgTime = $totalTime / count(self::$queries);
        
        // Find slow queries (> 100ms)
        $slowQueries = array_filter(self::$queries, function($query) {
            return $query['time'] > 0.1;
        });
        
        return [
            'total_queries' => count(self::$queries),
            'total_time' => $totalTime,
            'avg_time' => $avgTime,
            'slow_queries' => array_values($slowQueries),
            'n_plus_one' => self::detectNPlusOne()
        ];
    }
    
    /**
     * Log query statistics
     */
    public static function logStats(): void {
        if (!self::$enabled || empty(self::$queries)) {
            return;
        }
        
        $stats = self::getStats();
        
        Logger::info("Query Profiler Statistics", [
            'total_queries' => $stats['total_queries'],
            'total_time' => round($stats['total_time'], 4) . 's',
            'avg_time' => round($stats['avg_time'], 4) . 's',
            'slow_queries_count' => count($stats['slow_queries']),
            'n_plus_one_problems' => count($stats['n_plus_one'])
        ]);
        
        // Log N+1 problems
        if (!empty($stats['n_plus_one'])) {
            foreach ($stats['n_plus_one'] as $problem) {
                Logger::warning("N+1 Query Problem Detected", [
                    'pattern' => $problem['pattern'],
                    'count' => $problem['count'],
                    'total_time' => round($problem['total_time'], 4) . 's',
                    'suggestion' => $problem['suggestion']
                ]);
            }
        }
        
        // Log slow queries
        if (!empty($stats['slow_queries'])) {
            foreach ($stats['slow_queries'] as $query) {
                Logger::warning("Slow Query Detected", [
                    'sql' => $query['sql'],
                    'time' => round($query['time'], 4) . 's'
                ]);
            }
        }
    }
    
    /**
     * Enable/disable profiler
     * @param bool $enabled
     */
    public static function setEnabled(bool $enabled): void {
        self::$enabled = $enabled;
    }
    
    /**
     * Clear query log
     */
    public static function clear(): void {
        self::$queries = [];
        self::$startTime = null;
    }
}

