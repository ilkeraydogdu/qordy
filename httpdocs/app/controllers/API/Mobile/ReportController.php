<?php
/**
 * Mobile API Report Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 4 Report method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class ReportController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function getManagerAnalytics(): void { $this->delegate->getManagerAnalytics(); }
 public function getZReport(): void { $this->delegate->getZReport(); }
 public function printZReport(): void { $this->delegate->printZReport(); }
 public function getReportsMobile(): void { $this->delegate->getReportsMobile(); }
}
