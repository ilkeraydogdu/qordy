<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class TwoFactorController extends Controller {
    
    public function enable2FA() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $method = $requestData['method'] ?? '';
        $secretCode = $requestData['secret_code'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($method) || empty($secretCode) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            $result = $twoFactorAuthService->enable2FA($userId, $method, $secretCode);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Enable 2FA error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => '2FA aktifleştirilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function disable2FA() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $method = $requestData['method'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($method) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            $result = $twoFactorAuthService->disable2FA($userId, $method);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Disable 2FA error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => '2FA kapatılırken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function send2FACode() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $method = $requestData['method'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($method) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            $result = $twoFactorAuthService->sendVerificationCode($userId, $method);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Send 2FA code error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => 'Kod gönderilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function verify2FA() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $method = $requestData['method'] ?? '';
        $code = $requestData['code'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';
        
        if (empty($method) || empty($code) || empty($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
            // Note: signature is (userId, code, method) — see TwoFactorAuthService::verifyCode.
            $result = $twoFactorAuthService->verifyCode($userId, $code, $method);
            
            if ($result['success']) {
                $this->apiResponse($result);
            } else {
                $this->apiResponse($result, 400);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Verify 2FA error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => 'Kod doğrulanırken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
}

