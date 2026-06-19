<?php
/**
 * Mobile API Auth Controller (Q4 2026 Refactor — DELEGATE WRAPPER)
 *
 * Bu controller, MobileAPIController'daki 22 Auth method'u organize eder.
 * Tam implementasyon Q1 2027'de yapılacak. Şimdilik delegate eder.
 */
declare(strict_types=1);
namespace App\Controllers\API\Mobile;
require_once __DIR__ . '/../../../../core/Controller.php';
require_once __DIR__ . '/../../MobileAPIController.php';
use App\Core\Controller;
use App\Controllers\API\MobileAPIController;

class AuthController extends Controller
{
 private MobileAPIController $delegate;

 public function __construct()
 {
 parent::__construct();
 $this->delegate = new MobileAPIController();
 }

 public function refreshToken(): void { $this->delegate->refreshToken(); }
 public function staffLogin(): void { $this->delegate->staffLogin(); }
 public function managerLogin(): void { $this->delegate->managerLogin(); }
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
 public function logout(): void { $this->delegate->logout(); }
 public function registerPushToken(): void { $this->delegate->registerPushToken(); }
 public function registerBusiness(): void { $this->delegate->registerBusiness(); }
 public function sendRegisterEmailCode(): void { $this->delegate->sendRegisterEmailCode(); }
 public function verifyRegisterEmail(): void { $this->delegate->verifyRegisterEmail(); }
 public function sendRegisterPhoneCode(): void { $this->delegate->sendRegisterPhoneCode(); }
 public function verifyRegisterPhone(): void { $this->delegate->verifyRegisterPhone(); }
}
