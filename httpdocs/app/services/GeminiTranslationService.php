<?php
/**
 * GeminiTranslationService (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu service, GeminiService'deki 4 method'u organize eder.
 * Tam implementasyon Q2 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);

namespace App\Services;

require_once __DIR__ . '/GeminiService.php';

class GeminiTranslationService extends GeminiService {
 protected GeminiService $delegate;

 public function __construct() {
 parent::__construct();
 $this->delegate = new GeminiService();
 }

 public function translateMenuItem() { return $this->delegate->translateMenuItem(...func_get_args()); }
 public function translateText() { return $this->delegate->translateText(...func_get_args()); }
 public function translateIngredients() { return $this->delegate->translateIngredients(...func_get_args()); }
 public function translateExtras() { return $this->delegate->translateExtras(...func_get_args()); }

}
