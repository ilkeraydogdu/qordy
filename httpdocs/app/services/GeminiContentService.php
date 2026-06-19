<?php
/**
 * GeminiContentService (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu service, GeminiService'deki 7 method'u organize eder.
 * Tam implementasyon Q2 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);

namespace App\Services;

require_once __DIR__ . '/GeminiService.php';

class GeminiContentService extends GeminiService {
 protected GeminiService $delegate;

 public function __construct() {
 parent::__construct();
 $this->delegate = new GeminiService();
 }

 public function generateMenuDescription() { return $this->delegate->generateMenuDescription(...func_get_args()); }
 public function generatePackageDescription() { return $this->delegate->generatePackageDescription(...func_get_args()); }
 public function improveText() { return $this->delegate->improveText(...func_get_args()); }
 public function generateSEOContent() { return $this->delegate->generateSEOContent(...func_get_args()); }
 public function improveImagePrompt() { return $this->delegate->improveImagePrompt(...func_get_args()); }
 public function generateCustomerRecommendations() { return $this->delegate->generateCustomerRecommendations(...func_get_args()); }
 public function generateReceiptTemplate() { return $this->delegate->generateReceiptTemplate(...func_get_args()); }

}
