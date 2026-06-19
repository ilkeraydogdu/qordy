<?php
/**
 * Mobile API Notification Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 7 Notification method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class NotificationController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function validateManagerEmail(): void { $this->delegate->validateManagerEmail(); }
 public function getNotifications(): void { $this->delegate->getNotifications(); }
 public function markNotificationRead(): void { $this->delegate->markNotificationRead(); }
 public function markAllNotificationsRead(): void { $this->delegate->markAllNotificationsRead(); }
 public function registerPushToken(): void { $this->delegate->registerPushToken(); }
 public function sendRegisterEmailCode(): void { $this->delegate->sendRegisterEmailCode(); }
 public function verifyRegisterEmail(): void { $this->delegate->verifyRegisterEmail(); }
}
