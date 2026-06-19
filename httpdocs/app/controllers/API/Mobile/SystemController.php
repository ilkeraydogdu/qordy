<?php
/**
 * Mobile API System Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 7 System method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class SystemController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function getKitchenOrders(): void { $this->delegate->getKitchenOrders(); }
 public function updateKitchenStatus(): void { $this->delegate->updateKitchenStatus(); }
 public function getPreparationOrders(): void { $this->delegate->getPreparationOrders(); }
 public function updatePreparationStatus(): void { $this->delegate->updatePreparationStatus(); }
 public function transferToCashier(): void { $this->delegate->transferToCashier(); }
 public function getSubscriptionStatus(): void { $this->delegate->getSubscriptionStatus(); }
 public function subscriptionHistory(): void { $this->delegate->subscriptionHistory(); }
}
