<?php
/**
 * GeminiAnalyticsService (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu service, GeminiService'deki 1 method'u organize eder.
 * Tam implementasyon Q2 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);

namespace App\Services;

require_once __DIR__ . '/GeminiService.php';

class GeminiAnalyticsService extends GeminiService {
 protected GeminiService $delegate;

 public function __construct() {
 parent::__construct();
 $this->delegate = new GeminiService();
 }

 public function analyzeRestaurantPerformance() { return $this->delegate->analyzeRestaurantPerformance(...func_get_args()); }

}
