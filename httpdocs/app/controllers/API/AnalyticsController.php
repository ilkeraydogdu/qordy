<?php
/**
 * Public API Analytics Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, APIController'daki 3 Analytics method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API;
require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../APIController.php';
use App\Core\Controller;
use App\Controllers\APIController;

class AnalyticsController extends Controller
{
 private APIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new APIController();
 }

 public function getAnalytics(): void { $this->delegate->getAnalytics(); }
 public function getAnalyticsAdvanced(): void { $this->delegate->getAnalyticsAdvanced(); }
 public function reportError(): void { $this->delegate->reportError(); }
}
