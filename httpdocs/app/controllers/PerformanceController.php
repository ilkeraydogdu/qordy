<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Core\DependencyFactory;
use App\Services\PerformanceMonitor;
use App\Services\QueryProfiler;

/**
 * Performance Monitoring Controller
 * Provides performance metrics, cache stats, and query profiling
 */
class PerformanceController extends BaseController {
    
    /**
     * Display performance dashboard
     * Only accessible to admin users
     */
    public function dashboard() {
        // Check admin permission
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo "Access denied. Admin privileges required.";
            return;
        }
        
        $cacheStats = $this->getCacheStats();
        $queryStats = $this->getQueryStats();
        $systemInfo = $this->getSystemInfo();
        
        $this->render('admin/performance_dashboard', [
            'cache' => $cacheStats,
            'queries' => $queryStats,
            'system' => $systemInfo,
            'page_title' => 'Performance Dashboard'
        ]);
    }
    
    /**
     * Get cache statistics
     * @return array
     */
    public function getCacheStats(): array {
        try {
            $cacheService = DependencyFactory::getCacheService();
            
            // Check if using Redis
            $config = require __DIR__ . '/../config/cache.php';
            $driver = $config['driver'] ?? 'file';
            
            $stats = [
                'driver' => $driver,
                'status' => 'operational'
            ];
            
            if ($driver === 'redis') {
                // Redis-specific stats
                $redis = new \Redis();
                $connected = $redis->connect(
                    $config['redis']['host'],
                    $config['redis']['port'],
                    $config['redis']['timeout']
                );
                
                if ($connected) {
                    $redis->select($config['redis']['database']);
                    $info = $redis->info('stats');
                    $memory = $redis->info('memory');
                    
                    $stats['connection'] = 'connected';
                    $stats['keys'] = $redis->dbSize();
                    $stats['used_memory'] = $this->formatBytes($memory['used_memory'] ?? 0);
                    $stats['used_memory_peak'] = $this->formatBytes($memory['used_memory_peak'] ?? 0);
                    $stats['keyspace_hits'] = $info['keyspace_hits'] ?? 0;
                    $stats['keyspace_misses'] = $info['keyspace_misses'] ?? 0;
                    $stats['hit_rate'] = $this->calculateHitRate($info);
                    $stats['total_commands'] = $info['total_commands_processed'] ?? 0;
                    $stats['connected_clients'] = $info['connected_clients'] ?? 0;
                    
                    $redis->close();
                } else {
                    $stats['connection'] = 'failed';
                    $stats['status'] = 'error';
                }
            } else {
                // File cache stats
                if (method_exists($cacheService, 'getStats')) {
                    $fileStats = $cacheService->getStats();
                    $stats = array_merge($stats, $fileStats);
                }
            }
            
            return $stats;
        } catch (\Exception $e) {
            return [
                'driver' => 'unknown',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get query statistics
     * @return array
     */
    public function getQueryStats(): array {
        if (class_exists('\App\Services\QueryProfiler')) {
            return QueryProfiler::getQueryStats();
        }
        
        if (class_exists('\App\Services\PerformanceMonitor')) {
            return PerformanceMonitor::getQueryStats();
        }
        
        return [
            'total_queries' => 0,
            'total_time' => 0,
            'average_time' => 0,
            'slow_queries' => 0
        ];
    }
    
    /**
     * Get system information
     * @return array
     */
    public function getSystemInfo(): array {
        return [
            'php_version' => PHP_VERSION,
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'opcache_enabled' => function_exists('opcache_get_status') ? 'Yes' : 'No',
            'redis_extension' => extension_loaded('redis') ? 'Loaded' : 'Not loaded',
            'pdo_drivers' => implode(', ', \PDO::getAvailableDrivers())
        ];
    }
    
    /**
     * API endpoint for cache stats (JSON)
     */
    public function apiCacheStats() {
        // Check admin permission
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        header('Content-Type: application/json');
        echo json_encode($this->getCacheStats());
    }
    
    /**
     * API endpoint for query stats (JSON)
     */
    public function apiQueryStats() {
        // Check admin permission
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        header('Content-Type: application/json');
        echo json_encode($this->getQueryStats());
    }
    
    /**
     * Get slow queries
     */
    public function slowQueries() {
        // Check admin permission
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $threshold = $_GET['threshold'] ?? 1.0;
        
        if (class_exists('\App\Services\PerformanceMonitor')) {
            $slowQueries = PerformanceMonitor::getSlowQueries((float)$threshold);
        } else {
            $slowQueries = [];
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'threshold' => $threshold,
            'count' => count($slowQueries),
            'queries' => $slowQueries
        ]);
    }
    
    /**
     * Clear cache (admin only)
     */
public function clearCache() {
        // Check admin permission
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        try {
            $cacheService = DependencyFactory::getCacheService();
            $result = $cacheService->clear();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Cache cleared successfully' : 'Failed to clear cache'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Clear all active sessions (admin only)
     * Clears all PIN sessions, IP mappings, and user PIN mappings from Redis
     */
    public function clearActiveSessions() {
        // Check admin permission
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        header('Content-Type: application/json');
        
        try {
            $cacheConfig = require __DIR__ . '/../config/cache.php';
            
            if ($cacheConfig['driver'] !== 'redis') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Redis is not configured. Cannot clear session mappings.'
                ]);
                return;
            }
            
            if (!extension_loaded('redis') && !class_exists('Redis')) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Redis extension is not loaded.'
                ]);
                return;
            }
            
            $redis = new \Redis();
            $host = $cacheConfig['redis']['host'] ?? '127.0.0.1';
            $port = $cacheConfig['redis']['port'] ?? 6379;
            $password = $cacheConfig['redis']['password'] ?? null;
            $timeout = $cacheConfig['redis']['timeout'] ?? 2.5;
            
            if (!$redis->connect($host, $port, $timeout)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to connect to Redis.'
                ]);
                return;
            }
            
            if ($password !== null) {
                if (!$redis->auth($password)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to authenticate with Redis.'
                    ]);
                    return;
                }
            }
            
            // Use rate limit database (database 2) for PIN sessions
            $database = isset($_ENV['REDIS_RATELIMIT_DATABASE']) ? (int)$_ENV['REDIS_RATELIMIT_DATABASE'] : 2;
            $redis->select($database);
            
            $results = [
                'pin_sessions' => 0,
                'user_pin_mappings' => 0,
                'ip_user_mappings' => 0,
                'user_ip_mappings' => 0,
                'session_invalidations' => 0
            ];
            
            // Clear PIN session mappings
            $pinKeys = $redis->keys('pin_session:*');
            $results['pin_sessions'] = count($pinKeys);
            foreach ($pinKeys as $key) {
                $redis->del($key);
            }
            
            // Clear user PIN mappings
            $userPinKeys = $redis->keys('user_pin_mapping:*');
            $results['user_pin_mappings'] = count($userPinKeys);
            foreach ($userPinKeys as $key) {
                $redis->del($key);
            }
            
            // Clear IP user mappings
            $ipUserKeys = $redis->keys('ip_user_mapping:*');
            $results['ip_user_mappings'] = count($ipUserKeys);
            foreach ($ipUserKeys as $key) {
                $redis->del($key);
            }
            
            // Clear user IP mappings
            $userIpKeys = $redis->keys('user_ip_mapping:*');
            $results['user_ip_mappings'] = count($userIpKeys);
            foreach ($userIpKeys as $key) {
                $redis->del($key);
            }
            
            // Clear session invalidation keys
            $sessionInvalidKeys = $redis->keys('session_invalidated:*');
            $results['session_invalidations'] = count($sessionInvalidKeys);
            foreach ($sessionInvalidKeys as $key) {
                $redis->del($key);
            }
            
            $totalCleared = array_sum($results);
            
            echo json_encode([
                'success' => true,
                'message' => "Cleared {$totalCleared} active session mapping(s)",
                'results' => $results,
                'total' => $totalCleared
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Test cache performance
     */
    public function testCachePerformance() {
        // Check admin permission
        if (!$this->isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $iterations = $_GET['iterations'] ?? 100;
        $cacheService = DependencyFactory::getCacheService();
        
        // Test write performance
        $startWrite = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $cacheService->set("perf_test_$i", "value_$i", 3600);
        }
        $writeTime = microtime(true) - $startWrite;
        
        // Test read performance
        $startRead = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $cacheService->get("perf_test_$i");
        }
        $readTime = microtime(true) - $startRead;
        
        // Clean up
        for ($i = 0; $i < $iterations; $i++) {
            $cacheService->delete("perf_test_$i");
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'iterations' => $iterations,
            'write_total_ms' => round($writeTime * 1000, 2),
            'read_total_ms' => round($readTime * 1000, 2),
            'write_avg_ms' => round($writeTime / $iterations * 1000, 2),
            'read_avg_ms' => round($readTime / $iterations * 1000, 2),
            'total_time_ms' => round(($writeTime + $readTime) * 1000, 2)
        ]);
    }
    
    /**
     * Check if current user is admin
     * @return bool
     */
    private function isAdmin(): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Projede rol kodları büyük harfli (SUPER_ADMIN, QODMIN, ROLE_*)
        // formatta tutulur; `admin`/`super_admin` eşlemesi tutarsızdı.
        // Normalize ederek kontrol et.
        $role = $_SESSION['role'] ?? '';
        if (is_string($role) && $role !== '') {
            $normalized = strtoupper($role);
            if (in_array($normalized, ['SUPER_ADMIN', 'QODMIN', 'ROLE_SUPER_ADMIN', 'ROLE_QODMIN'], true)) {
                return true;
            }
        }

        // Authorization::hasPermission bir INSTANCE metodudur, static değil.
        // Önceki hali PHP 8'de fatal oluyordu; getInstance() ile doğru kullanım.
        if (class_exists('\App\Core\Authorization')) {
            try {
                $auth = \App\Core\Authorization::getInstance();
                if (method_exists($auth, 'isSuperAdmin') && $auth->isSuperAdmin()) {
                    return true;
                }
                if (method_exists($auth, 'hasPermission')) {
                    return (bool) $auth->hasPermission('view_performance_metrics');
                }
            } catch (\Throwable $e) {
                // Auth kararını fail-close yap — sorun varsa erişime izin verme.
                return false;
            }
        }

        return false;
    }
    
    /**
     * Calculate cache hit rate
     * @param array $info Redis info stats
     * @return float Hit rate percentage
     */
    private function calculateHitRate(array $info): float {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($hits / $total) * 100, 2);
    }
    
    /**
     * Format bytes to human-readable format
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
