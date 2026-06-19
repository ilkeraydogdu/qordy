<?php
/**
 * Mobile API Order Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 26 Order method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class OrderController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 // 26 method delegation
 public function getOrders(): void { $this->delegate->getOrders(); }
 public function updateOrderStatus(): void { $this->delegate->updateOrderStatus(); }
 public function orderHasKitchenItems(): void { $this->delegate->orderHasKitchenItems(); }
 public function getKitchenOrders(): void { $this->delegate->getKitchenOrders(); }
 public function getPreparationOrders(): void { $this->delegate->getPreparationOrders(); }
 public function getReadyOrders(): void { $this->delegate->getReadyOrders(); }
 public function deliverOrder(): void { $this->delegate->deliverOrder(); }
 public function deleteOrderItem(): void { $this->delegate->deleteOrderItem(); }
 public function acceptOrder(): void { $this->delegate->acceptOrder(); }
 public function createMobileOrder(): void { $this->delegate->createMobileOrder(); }
 public function addItemToOrder(): void { $this->delegate->addItemToOrder(); }
 public function removeItemFromOrder(): void { $this->delegate->removeItemFromOrder(); }
 public function processPaymentMobile(): void { $this->delegate->processPaymentMobile(); }
 public function getActiveOrdersMobile(): void { $this->delegate->getActiveOrdersMobile(); }
 public function getTableOrdersMobile(): void { $this->delegate->getTableOrdersMobile(); }
 public function clearTableOrders(): void { $this->delegate->clearTableOrders(); }
 public function initiateIyzicoPayment(): void { $this->delegate->initiateIyzicoPayment(); }
 public function iyzicoPaymentStatus(): void { $this->delegate->iyzicoPaymentStatus(); }
 public function uploadPaymentReceipt(): void { $this->delegate->uploadPaymentReceipt(); }
 public function getPendingPayments(): void { $this->delegate->getPendingPayments(); }
 public function getOrderApprovalsPending(): void { $this->delegate->getOrderApprovalsPending(); }
 public function approveOrderRequest(): void { $this->delegate->approveOrderRequest(); }
 public function rejectOrderRequest(): void { $this->delegate->rejectOrderRequest(); }
 public function getOrderApprovalHistoryMobile(): void { $this->delegate->getOrderApprovalHistoryMobile(); }
 public function getPaymentGatewaysMobile(): void { $this->delegate->getPaymentGatewaysMobile(); }
 public function togglePaymentGatewayMobile(): void { $this->delegate->togglePaymentGatewayMobile(); }
}
