<?php
/**
 * Public API Auth Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, APIController'daki 1 Auth method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API;
require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../APIController.php';
use App\Core\Controller;
use App\Controllers\APIController;

class AuthController extends Controller
{
 private APIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new APIController();
 }

 public function authenticate(): void { $this->delegate->authenticate(); }
}
