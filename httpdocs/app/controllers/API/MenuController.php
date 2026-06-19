<?php
/**
 * Public API Menu Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, APIController'daki 10 Menu method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API;
require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../APIController.php';
use App\Core\Controller;
use App\Controllers\APIController;

class MenuController extends Controller
{
 private APIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new APIController();
 }

 public function getMenu(): void { $this->delegate->getMenu(); }
 public function getIngredients(): void { $this->delegate->getIngredients(); }
 public function getTopSellingItems(): void { $this->delegate->getTopSellingItems(); }
 public function getCategorySales(): void { $this->delegate->getCategorySales(); }
 public function searchMenuItems(): void { $this->delegate->searchMenuItems(); }
 public function getLowStockIngredients(): void { $this->delegate->getLowStockIngredients(); }
 public function getOutOfStockMenuItems(): void { $this->delegate->getOutOfStockMenuItems(); }
 public function getMenuAdvanced(): void { $this->delegate->getMenuAdvanced(); }
 public function getMenuItem(): void { $this->delegate->getMenuItem(); }
 public function getCategory(): void { $this->delegate->getCategory(); }
}
