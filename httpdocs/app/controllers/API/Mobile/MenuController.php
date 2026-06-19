<?php
/**
 * Mobile API Menu Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 15 Menu method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class MenuController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function getMenu(): void { $this->delegate->getMenu(); }
 public function getMenuItemIngredients(): void { $this->delegate->getMenuItemIngredients(); }
 public function orderHasKitchenItems(): void { $this->delegate->orderHasKitchenItems(); }
 public function deleteOrderItem(): void { $this->delegate->deleteOrderItem(); }
 public function addItemToOrder(): void { $this->delegate->addItemToOrder(); }
 public function updateItemQuantity(): void { $this->delegate->updateItemQuantity(); }
 public function updateMenuItemAvailability(): void { $this->delegate->updateMenuItemAvailability(); }
 public function addMenuItem(): void { $this->delegate->addMenuItem(); }
 public function updateMenuItem(): void { $this->delegate->updateMenuItem(); }
 public function deleteMenuItem(): void { $this->delegate->deleteMenuItem(); }
 public function createCategory(): void { $this->delegate->createCategory(); }
 public function updateCategory(): void { $this->delegate->updateCategory(); }
 public function deleteCategoryMobile(): void { $this->delegate->deleteCategoryMobile(); }
 public function getProductSalesData(): void { $this->delegate->getProductSalesData(); }
 public function getAnalyticsByCategory(): void { $this->delegate->getAnalyticsByCategory(); }
}
