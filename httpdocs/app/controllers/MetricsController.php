<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\Cache;
use App\Core\CacheManager;

/**
 * Prometheus Metrics Endpoint
 * Exposed at /metrics
 */
class MetricsController extends Controller
{
 /**
 * GET /metrics
 * Returns Prometheus-formatted metrics
 */
 public function index(): void
 {
 $metrics = $this->collectMetrics();

 header('Content-Type: text/plain; version=0.0.4');
 echo $this->formatPrometheus($metrics);
 exit;
 }

 /**
 * Collect all application metrics
 */
 private function collectMetrics(): array
 {
 $cacheStats = Cache::getStats();

 return [
 'qordy_info' => [
 'type' => 'gauge',
 'value' => 1,
 'labels' => ['version' => '1.0.0', 'env' => ($_ENV['APP_ENV'] ?? 'production')]
 ],
 'qordy_cache_files_total' => [
 'type' => 'gauge',
 'value' => $cacheStats['total_files'] ?? 0
 ],
 'qordy_cache_size_bytes' => [
 'type' => 'gauge',
 'value' => $cacheStats['total_size'] ?? 0
 ],
 'qordy_cache_hits_total' => [
 'type' => 'counter',
 'value' => $cacheStats['hits'] ?? 0
 ],
 'qordy_cache_misses_total' => [
 'type' => 'counter',
 'value' => $cacheStats['misses'] ?? 0
 ],
 'qordy_php_memory_usage_bytes' => [
 'type' => 'gauge',
 'value' => memory_get_usage(true)
 ],
 'qordy_php_memory_peak_bytes' => [
 'type' => 'gauge',
 'value' => memory_get_peak_usage(true)
 ],
 'qordy_php_uptime_seconds' => [
 'type' => 'gauge',
 'value' => time() - ($_SERVER['REQUEST_TIME'] ?? time())
 ],
 'qordy_db_connections_active' => [
 'type' => 'gauge',
 'value' => $this->getDbConnections()
 ],
 ];
 }

 /**
 * Get active DB connection count (approximate)
 */
 private function getDbConnections(): int
 {
 try {
 $db = \App\Config\Database::getInstance();
 $pdo = $db->getConnection();
 $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
 $result = $stmt->fetch();
 return (int)($result['Value'] ?? 0);
 } catch (\Exception $e) {
 return 0;
 }
 }

 /**
 * Format metrics as Prometheus text
 */
 private function formatPrometheus(array $metrics): string
 {
 $output = "# HELP qordy_info Qordy application info\n";
 $output .= "# TYPE qordy_info gauge\n";

 foreach ($metrics as $name => $data) {
 $type = $data['type'];
 $value = $data['value'];
 $labels = $data['labels'] ?? [];

 $labelStr = '';
 if (!empty($labels)) {
 $pairs = [];
 foreach ($labels as $k => $v) {
 $pairs[] = sprintf('%s="%s"', $k, addslashes((string)$v));
 }
 $labelStr = '{' . implode(',', $pairs) . '}';
 }

 $output .= sprintf("%s%s %s\n", $name, $labelStr, $value);
 }

 return $output;
 }
}