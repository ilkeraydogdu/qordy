<?php
/**
 * Mobile API Resource Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 83 Resource method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class ResourceController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function refreshToken(): void { $this->delegate->refreshToken(); }
 public function validateSubdomain(): void { $this->delegate->validateSubdomain(); }
 public function totpStatus(): void { $this->delegate->totpStatus(); }
 public function totpSetup(): void { $this->delegate->totpSetup(); }
 public function totpConfirm(): void { $this->delegate->totpConfirm(); }
 public function totpDisable(): void { $this->delegate->totpDisable(); }
 public function whatsappStatus(): void { $this->delegate->whatsappStatus(); }
 public function whatsappSetup(): void { $this->delegate->whatsappSetup(); }
 public function whatsappConfirm(): void { $this->delegate->whatsappConfirm(); }
 public function whatsappDisable(): void { $this->delegate->whatsappDisable(); }
 public function authMethodsStatus(): void { $this->delegate->authMethodsStatus(); }
 public function send2FAChallengeCode(): void { $this->delegate->send2FAChallengeCode(); }
 public function verify2FAChallenge(): void { $this->delegate->verify2FAChallenge(); }
 public function verifyTokenEndpoint(): void { $this->delegate->verifyTokenEndpoint(); }
 public function getOrders(): void { $this->delegate->getOrders(); }
 public function updateOrderStatus(): void { $this->delegate->updateOrderStatus(); }
 public function getReadyOrders(): void { $this->delegate->getReadyOrders(); }
 public function deliverOrder(): void { $this->delegate->deliverOrder(); }
 public function acceptOrder(): void { $this->delegate->acceptOrder(); }
 public function createMobileOrder(): void { $this->delegate->createMobileOrder(); }
 public function processPaymentMobile(): void { $this->delegate->processPaymentMobile(); }
 public function printAdisyonMobile(): void { $this->delegate->printAdisyonMobile(); }
 public function getActiveOrdersMobile(): void { $this->delegate->getActiveOrdersMobile(); }
 public function getCategories(): void { $this->delegate->getCategories(); }
 public function getRoles(): void { $this->delegate->getRoles(); }
 public function getReservationsList(): void { $this->delegate->getReservationsList(); }
 public function createReservation(): void { $this->delegate->createReservation(); }
 public function updateReservationMobile(): void { $this->delegate->updateReservationMobile(); }
 public function deleteReservation(): void { $this->delegate->deleteReservation(); }
 public function getExpenses(): void { $this->delegate->getExpenses(); }
 public function createExpense(): void { $this->delegate->createExpense(); }
 public function updateExpense(): void { $this->delegate->updateExpense(); }
 public function deleteExpense(): void { $this->delegate->deleteExpense(); }
 public function registerBusiness(): void { $this->delegate->registerBusiness(); }
 public function sendRegisterPhoneCode(): void { $this->delegate->sendRegisterPhoneCode(); }
 public function verifyRegisterPhone(): void { $this->delegate->verifyRegisterPhone(); }
 public function getPackagesList(): void { $this->delegate->getPackagesList(); }
 public function purchasePackage(): void { $this->delegate->purchasePackage(); }
 public function initiateIyzicoPayment(): void { $this->delegate->initiateIyzicoPayment(); }
 public function iyzicoPaymentStatus(): void { $this->delegate->iyzicoPaymentStatus(); }
 public function getAssignedOffer(): void { $this->delegate->getAssignedOffer(); }
 public function listCustomOffers(): void { $this->delegate->listCustomOffers(); }
 public function dismissCustomOffer(): void { $this->delegate->dismissCustomOffer(); }
 public function uploadPaymentReceipt(): void { $this->delegate->uploadPaymentReceipt(); }
 public function getPendingPayments(): void { $this->delegate->getPendingPayments(); }
 public function getStockList(): void { $this->delegate->getStockList(); }
 public function getReceiptsList(): void { $this->delegate->getReceiptsList(); }
 public function getOrderApprovalsPending(): void { $this->delegate->getOrderApprovalsPending(); }
 public function approveOrderRequest(): void { $this->delegate->approveOrderRequest(); }
 public function rejectOrderRequest(): void { $this->delegate->rejectOrderRequest(); }
 public function getPrinterBridges(): void { $this->delegate->getPrinterBridges(); }
 public function revealPrinterBridgeKey(): void { $this->delegate->revealPrinterBridgeKey(); }
 public function createPrinterBridge(): void { $this->delegate->createPrinterBridge(); }
 public function updatePrinterBridge(): void { $this->delegate->updatePrinterBridge(); }
 public function deletePrinterBridge(): void { $this->delegate->deletePrinterBridge(); }
 public function getPrintersForBridge(): void { $this->delegate->getPrintersForBridge(); }
 public function updatePrinterMobile(): void { $this->delegate->updatePrinterMobile(); }
 public function deletePrinterMobile(): void { $this->delegate->deletePrinterMobile(); }
 public function testPrinterMobile(): void { $this->delegate->testPrinterMobile(); }
 public function getPrepScreensForPrinterMobile(): void { $this->delegate->getPrepScreensForPrinterMobile(); }
 public function getReceiptTemplatesMobile(): void { $this->delegate->getReceiptTemplatesMobile(); }
 public function createReceiptTemplateMobile(): void { $this->delegate->createReceiptTemplateMobile(); }
 public function updateReceiptTemplateMobile(): void { $this->delegate->updateReceiptTemplateMobile(); }
 public function deleteReceiptTemplateMobile(): void { $this->delegate->deleteReceiptTemplateMobile(); }
 public function getRolesPermissionsMobile(): void { $this->delegate->getRolesPermissionsMobile(); }
 public function updateRolePermissionsMobile(): void { $this->delegate->updateRolePermissionsMobile(); }
 public function getOrderApprovalHistoryMobile(): void { $this->delegate->getOrderApprovalHistoryMobile(); }
 public function getInvoicesMobile(): void { $this->delegate->getInvoicesMobile(); }
 public function createInvoiceMobile(): void { $this->delegate->createInvoiceMobile(); }
 public function deleteInvoiceMobile(): void { $this->delegate->deleteInvoiceMobile(); }
 public function getSuppliersMobile(): void { $this->delegate->getSuppliersMobile(); }
 public function createSupplierMobile(): void { $this->delegate->createSupplierMobile(); }
 public function updateSupplierMobile(): void { $this->delegate->updateSupplierMobile(); }
 public function deleteSupplierMobile(): void { $this->delegate->deleteSupplierMobile(); }
 public function getWasteMobile(): void { $this->delegate->getWasteMobile(); }
 public function createWasteMobile(): void { $this->delegate->createWasteMobile(); }
 public function deleteWasteMobile(): void { $this->delegate->deleteWasteMobile(); }
 public function getPaymentGatewaysMobile(): void { $this->delegate->getPaymentGatewaysMobile(); }
 public function togglePaymentGatewayMobile(): void { $this->delegate->togglePaymentGatewayMobile(); }
 public function getPosDevicesMobile(): void { $this->delegate->getPosDevicesMobile(); }
 public function deletePosDeviceMobile(): void { $this->delegate->deletePosDeviceMobile(); }
 public function getFeatureFlagsMobile(): void { $this->delegate->getFeatureFlagsMobile(); }
 public function toggleFeatureFlagMobile(): void { $this->delegate->toggleFeatureFlagMobile(); }
}
