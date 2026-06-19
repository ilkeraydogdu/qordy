<?php
/**
 * Mobile API Error Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 3 Error method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class ErrorController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function managerLogin(): void { $this->delegate->managerLogin(); }
 public function logout(): void { $this->delegate->logout(); }
 public function getErrorLogsMobile(): void { $this->delegate->getErrorLogsMobile(); }
}
