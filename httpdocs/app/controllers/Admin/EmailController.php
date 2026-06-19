<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class EmailController extends Controller {
    
    public function testEmail() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $email = $requestData['email'] ?? '';
        if (empty($email)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $emailService = \App\Core\DependencyFactory::getEmailService();
            $result = $emailService->testEmail($email);
            $this->apiResponse($result);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Test email error: ' . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'message' => 'Test emaili gönderilirken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getEmailStatus() {
        $this->requirePermission('settings.view');
        
        try {
            $emailService = \App\Core\DependencyFactory::getEmailService();
            $status = $emailService->getStatus();
            $this->apiResponse($status);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Email status error: ' . $e->getMessage());
            $this->apiResponse([
                'error' => 'Email durumu alınırken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
}

