<?php
/**
 * Mobile API Staff Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 6 Staff method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class StaffController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function staffLogin(): void { $this->delegate->staffLogin(); }
 public function staffDashboard(): void { $this->delegate->staffDashboard(); }
 public function getStaffList(): void { $this->delegate->getStaffList(); }
 public function createStaff(): void { $this->delegate->createStaff(); }
 public function updateStaff(): void { $this->delegate->updateStaff(); }
 public function deleteStaffMobile(): void { $this->delegate->deleteStaffMobile(); }
}
