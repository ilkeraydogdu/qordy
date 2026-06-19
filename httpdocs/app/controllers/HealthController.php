<?php

declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

use App\Core\Controller;
use App\Config\Database;

/**
 * Health Check Endpoint
 * GET /health - Returns system health status
 *
 * Returns JSON: {status: ok|degraded|down, checks: {...}}
 */
class HealthController extends Controller
{
 /**
 * GET /health
 */
 public function check(): void
 {
 $checks = [
 'database' => $this->checkDatabase(),
 'cache' => $this->checkCache(),
 'disk' => $this->checkDisk(),
 'memory' => $this->checkMemory(),
 ];

 $allOk = !in_array('down', array_column($checks, 'status'), true);
 $anyDown = in_array('down', array_column($checks, 'status'), true);

 $status = $anyDown ? 'down' : ($allOk ? 'ok' : 'degraded');
 $httpCode = $anyDown ? 503 : 200;

 header('Content-Type: application/json');
 http_response_code($httpCode);

 echo json_encode([
 'status' => $status,
 'timestamp' => date('c'),
 'version' => '1.0.0',
 'checks' => $checks,
 'php_version' => PHP_VERSION,
 'memory_usage' => memory_get_usage(true),
 ], JSON_PRETTY_PRINT);

 exit;
 }

 /**
 * Check database connection
 */
 private function checkDatabase(): array
 {
 try {
 $db = Database::getInstance();
 $pdo = $db->getConnection();
 $start = microtime(true);
 $stmt = $pdo->query('SELECT 1');
 $stmt->fetch();
 $latency = (microtime(true) - $start) * 1000;

 return [
 'status' => $latency < 100 ? 'ok' : 'degraded',
 'latency_ms' => round($latency, 2),
 'message' => 'Database responsive'
 ];
 } catch (\Exception $e) {
 return [
 'status' => 'down',
 'error' => $e->getMessage()
 ];
 }
 }

 /**
 * Check cache
 */
 private function checkCache(): array
 {
 try {
 $key = 'health_check_' . uniqid();
 \App\Core\Cache::set($key, 'ok', 10);
 $value = \App\Core\Cache::get($key);
 \App\Core\Cache::delete($key);

 return [
 'status' => $value === 'ok' ? 'ok' : 'degraded',
 'message' => 'Cache operational'
 ];
 } catch (\Exception $e) {
 return [
 'status' => 'down',
 'error' => $e->getMessage()
 ];
 }
 }

 /**
 * Check disk space
 */
 private function checkDisk(): array
 {
 $free = disk_free_space('/');
 $total = disk_total_space('/');
 $usedPercent = (($total - $free) / $total) * 100;

 $status = match (true) {
 $usedPercent > 90 => 'down',
 $usedPercent > 75 => 'degraded',
 default => 'ok'
 };

 return [
 'status' => $status,
 'used_percent' => round($usedPercent, 2),
 'free_bytes' => $free,
 'total_bytes' => $total
 ];
 }

 /**
 * Check memory
 */
 private function checkMemory(): array
 {
 $used = memory_get_usage(true);
 $peak = memory_get_peak_usage(true);
 $limit = $this->getMemoryLimit();
 $usedPercent = ($used / $limit) * 100;

 $status = match (true) {
 $usedPercent > 90 => 'down',
 $usedPercent > 70 => 'degraded',
 default => 'ok'
 };

 return [
 'status' => $status,
 'used_bytes' => $used,
 'peak_bytes' => $peak,
 'limit_bytes' => $limit,
 'used_percent' => round($usedPercent, 2)
 ];
 }

 /**
 * Get PHP memory_limit in bytes
 */
 private function getMemoryLimit(): int
 {
 $limit = ini_get('memory_limit');
 if ($limit === '-1') {
 return PHP_INT_MAX;
 }
 $unit = strtolower(substr($limit, -1));
 $value = (int)$limit;
 return match ($unit) {
 'g' => $value * 1024 * 1024 * 1024,
 'm' => $value * 1024 * 1024,
 'k' => $value * 1024,
 default => $value
 };
 }
}