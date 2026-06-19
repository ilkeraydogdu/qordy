<?php
/**
 * Mobile API Settings Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 7 Settings method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class SettingsController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function getBusinessSettings(): void { $this->delegate->getBusinessSettings(); }
 public function updateBusinessSettings(): void { $this->delegate->updateBusinessSettings(); }
 public function getQueueMobile(): void { $this->delegate->getQueueMobile(); }
 public function getQueueSettingsMobile(): void { $this->delegate->getQueueSettingsMobile(); }
 public function updateQueueSettingsMobile(): void { $this->delegate->updateQueueSettingsMobile(); }
 public function callNextQueueTicketMobile(): void { $this->delegate->callNextQueueTicketMobile(); }
 public function updateQueueTicketStatusMobile(): void { $this->delegate->updateQueueTicketStatusMobile(); }
}
