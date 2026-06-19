<?php
namespace App\Services;

use App\Core\Logger;

/**
 * Performance Monitoring Service
 * Tracks query execution times, API response times, and performance metrics
 */
class PerformanceMonitor {
    private static $queries = [];
    private static $startTime = null;
    private static $slowQueryThreshold = 1.0; // 1 second
    
    /**
     * Start performance monitoring
     */
    public static function start(): void {
        self::$startTime = microtime(true);
    }
    
    /**
     * Get elapsed time since start
     * @return float
     */
    public static function getElapsedTime(): float {
        if (self::$startTime === null) {
            return 0.0;
        }
        return microtime(true) - self::$startTime;
    }
    
    /**
     * Log query execution time
     * @param string $query
     * @param float $executionTime
     * @param array $params
     */
    public static function logQuery(string $query, float $executionTime, array $params = []): void {
        self::$queries[] = [
            'query' => $query,
            'time' => $executionTime,
            'params' => $params,
            'timestamp' => microtime(true)
        ];
        
        // Log slow queries
        if ($executionTime > self::$slowQueryThreshold) {
            Logger::warning("Slow query detected", [
                'query' => substr($query, 0, 200), // Truncate for logging
                'execution_time' => $executionTime,
                'threshold' => self::$slowQueryThreshold
            ]);
        }
    }
    
    /**
     * Get all logged queries
     * @return array
     */
    public static function getQueries(): array {
        return self::$queries;
    }
    
    /**
     * Get slow queries
     * @param float $threshold
     * @return array
     */
    public static function getSlowQueries(float $threshold = null): array {
        $threshold = $threshold ?? self::$slowQueryThreshold;
        return array_filter(self::$queries, function($query) use ($threshold) {
            return $query['time'] > $threshold;
        });
    }
    
    /**
     * Get query statistics
     * @return array
     */
    public static function getQueryStats(): array {
        if (empty(self::$queries)) {
            return [
                'total_queries' => 0,
                'total_time' => 0,
                'average_time' => 0,
                'slow_queries' => 0
            ];
        }
        
        $totalTime = array_sum(array_column(self::$queries, 'time'));
        $slowQueries = count(self::getSlowQueries());
        
        return [
            'total_queries' => count(self::$queries),
            'total_time' => $totalTime,
            'average_time' => $totalTime / count(self::$queries),
            'slow_queries' => $slowQueries,
            'slow_query_threshold' => self::$slowQueryThreshold
        ];
    }
    
    /**
     * Clear query log
     */
    public static function clearQueries(): void {
        self::$queries = [];
    }
    
    /**
     * Get performance metrics
     * @return array
     */
    public static function getMetrics(): array {
        $elapsed = self::getElapsedTime();
        $queryStats = self::getQueryStats();
        
        return [
            'request_time' => $elapsed,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'queries' => $queryStats
        ];
    }
    
    /**
     * Set slow query threshold
     * @param float $threshold
     */
    public static function setSlowQueryThreshold(float $threshold): void {
        self::$slowQueryThreshold = $threshold;
    }
}

