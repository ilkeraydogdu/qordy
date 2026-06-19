<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class LogoController extends Controller {
    protected $settingsService;
    
    public function __construct() {
        parent::__construct();
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
    }
    
    public function uploadLogo() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['logo'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $file = $_FILES['logo'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
            return;
        }
        
        if ($file['size'] > $maxSize) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_too_large', [], 400);
            return;
        }
        
        $uploadDir = __DIR__ . '/../../public/assets/images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $logoUrl = BASE_URL . '/assets/images/' . $filename;
            $this->settingsService->setSetting('logo_url', $logoUrl);
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.logo_uploaded', ['url' => $logoUrl], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.logo_upload_failed', [], 500);
        }
    }
    
    public function uploadFavicon() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['favicon'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $file = $_FILES['favicon'];
        $allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];
        $maxSize = 1 * 1024 * 1024; // 1MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
            return;
        }
        
        if ($file['size'] > $maxSize) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_too_large', [], 400);
            return;
        }
        
        $uploadDir = __DIR__ . '/../../public/assets/images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'favicon.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $faviconUrl = BASE_URL . '/assets/images/' . $filename;
            $this->settingsService->setSetting('favicon_url', $faviconUrl);
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.favicon_uploaded', ['url' => $faviconUrl], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.favicon_upload_failed', [], 500);
        }
    }
}

