<?php
/**
 * Public API Resource Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, APIController'daki 31 Resource method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API;
require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../APIController.php';
use App\Core\Controller;
use App\Controllers\APIController;

class ResourceController extends Controller
{
 private APIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new APIController();
 }

 public function getSuppliers(): void { $this->delegate->getSuppliers(); }
 public function getExpenses(): void { $this->delegate->getExpenses(); }
 public function getWasteRecords(): void { $this->delegate->getWasteRecords(); }
 public function getReservations(): void { $this->delegate->getReservations(); }
 public function getInvoices(): void { $this->delegate->getInvoices(); }
 public function getPaymentTransactions(): void { $this->delegate->getPaymentTransactions(); }
 public function getIntegrationPlatforms(): void { $this->delegate->getIntegrationPlatforms(); }
 public function getDailyRevenue(): void { $this->delegate->getDailyRevenue(); }
 public function getHourlyBusy(): void { $this->delegate->getHourlyBusy(); }
 public function getNetProfit(): void { $this->delegate->getNetProfit(); }
 public function processPayment(): void { $this->delegate->processPayment(); }
 public function addExpense(): void { $this->delegate->addExpense(); }
 public function deleteExpense(): void { $this->delegate->deleteExpense(); }
 public function addWaste(): void { $this->delegate->addWaste(); }
 public function deleteWaste(): void { $this->delegate->deleteWaste(); }
 public function addReservation(): void { $this->delegate->addReservation(); }
 public function sendReservationReminder(): void { $this->delegate->sendReservationReminder(); }
 public function deleteReservation(): void { $this->delegate->deleteReservation(); }
 public function addSupplier(): void { $this->delegate->addSupplier(); }
 public function updateSupplier(): void { $this->delegate->updateSupplier(); }
 public function deleteSupplier(): void { $this->delegate->deleteSupplier(); }
 public function addInvoice(): void { $this->delegate->addInvoice(); }
 public function payInvoice(): void { $this->delegate->payInvoice(); }
 public function changeLanguage(): void { $this->delegate->changeLanguage(); }
 public function translate(): void { $this->delegate->translate(); }
 public function resolveErrors(): void { $this->delegate->resolveErrors(); }
 public function deleteResolvedErrors(): void { $this->delegate->deleteResolvedErrors(); }
 public function deleteAllErrors(): void { $this->delegate->deleteAllErrors(); }
 public function smartCleanup(): void { $this->delegate->smartCleanup(); }
 public function generateContactCaptcha(): void { $this->delegate->generateContactCaptcha(); }
 public function submitContactForm(): void { $this->delegate->submitContactForm(); }
}
