<?php
/**
 * Mobile API Table Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 19 Table method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class TableController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function getTables(): void { $this->delegate->getTables(); }
 public function getZoneTables(): void { $this->delegate->getZoneTables(); }
 public function getTableDetails(): void { $this->delegate->getTableDetails(); }
 public function moveTable(): void { $this->delegate->moveTable(); }
 public function removeItemFromOrder(): void { $this->delegate->removeItemFromOrder(); }
 public function getTableOrdersMobile(): void { $this->delegate->getTableOrdersMobile(); }
 public function clearTableOrders(): void { $this->delegate->clearTableOrders(); }
 public function getZonesList(): void { $this->delegate->getZonesList(); }
 public function createZone(): void { $this->delegate->createZone(); }
 public function updateZone(): void { $this->delegate->updateZone(); }
 public function deleteZone(): void { $this->delegate->deleteZone(); }
 public function createTableMobile(): void { $this->delegate->createTableMobile(); }
 public function updateTableMobile(): void { $this->delegate->updateTableMobile(); }
 public function deleteTableMobile(): void { $this->delegate->deleteTableMobile(); }
 public function addStockMovement(): void { $this->delegate->addStockMovement(); }
 public function removeStockMovement(): void { $this->delegate->removeStockMovement(); }
 public function adjustStockMovement(): void { $this->delegate->adjustStockMovement(); }
 public function deleteStockMovement(): void { $this->delegate->deleteStockMovement(); }
 public function getTableHistoryMobile(): void { $this->delegate->getTableHistoryMobile(); }
}
