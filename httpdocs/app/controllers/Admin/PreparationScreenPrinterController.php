<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

class PreparationScreenPrinterController extends \App\Core\Controller {
    protected $preparationScreenPrinterService;
    protected $preparationScreenService;
    protected $printerService;
    
    public function __construct() {
        parent::__construct();
        
        try {
            $this->preparationScreenPrinterService = \App\Core\DependencyFactory::getPreparationScreenPrinterService();
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterController: Failed to load service: " . $e->getMessage());
            $this->preparationScreenPrinterService = null;
        }
        
        try {
            $this->preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterController: Failed to load preparation screen service: " . $e->getMessage());
            $this->preparationScreenService = null;
        }
        
        try {
            $this->printerService = \App\Core\DependencyFactory::getPrinterService();
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterController: Failed to load printer service: " . $e->getMessage());
            $this->printerService = null;
        }
    }
    
    /**
     * Get printers assigned to a screen
     * GET /api/qodmin/preparation-screens/{screenId}/printers
     */
    public function index($screenId = null) {
        $this->requirePermission('preparation-screens.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $screenId = $screenId ?? $queryParams['screen_id'] ?? '';
        
        if (empty($screenId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        if (!$this->preparationScreenPrinterService) {
            $this->toastNotificationService->sendApiResponse('error', 'Service kullanılamıyor.', [], 503);
            return;
        }
        
        $businessId = $_SESSION['business_id'] ?? null;
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'İşletme bilgisi bulunamadı.', [], 400);
            return;
        }
        
        try {
            $printers = $this->preparationScreenPrinterService->getPrintersByScreen($screenId, $businessId);
            $this->apiResponse(['success' => true, 'printers' => $printers]);
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterController::index - Error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Yazıcılar yüklenemedi', [], 500);
        }
    }
    
    /**
     * Assign printer to screen
     * POST /api/qodmin/preparation-screens/{screenId}/assign-printer
     */
    public function assign($screenId = null) {
        $this->requirePermission('preparation-screens.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $screenId = $screenId ?? $queryParams['screen_id'] ?? $input['screen_id'] ?? '';
        $printerId = $input['printer_id'] ?? '';
        $priority = isset($input['priority']) ? intval($input['priority']) : 1;
        
        if (empty($screenId) || empty($printerId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        if (!$this->preparationScreenPrinterService) {
            $this->toastNotificationService->sendApiResponse('error', 'Service kullanılamıyor.', [], 503);
            return;
        }
        
        $businessId = $_SESSION['business_id'] ?? null;
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'İşletme bilgisi bulunamadı.', [], 400);
            return;
        }
        
        try {
            $result = $this->preparationScreenPrinterService->assignPrinter($screenId, $printerId, $businessId, $priority);
            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'Yazıcı atanamadı', [], 500);
            }
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterController::assign - Error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Yazıcı atanamadı: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Remove printer from screen
     * POST /api/qodmin/preparation-screens/{screenId}/remove-printer
     */
    public function remove($screenId = null) {
        $this->requirePermission('preparation-screens.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $screenId = $screenId ?? $queryParams['screen_id'] ?? $input['screen_id'] ?? '';
        $printerId = $input['printer_id'] ?? '';
        
        if (empty($screenId) || empty($printerId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        if (!$this->preparationScreenPrinterService) {
            $this->toastNotificationService->sendApiResponse('error', 'Service kullanılamıyor.', [], 503);
            return;
        }
        
        $businessId = $_SESSION['business_id'] ?? null;
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'İşletme bilgisi bulunamadı.', [], 400);
            return;
        }
        
        try {
            $result = $this->preparationScreenPrinterService->removePrinter($screenId, $printerId, $businessId);
            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'Yazıcı kaldırılamadı', [], 500);
            }
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterController::remove - Error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Yazıcı kaldırılamadı: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Update priority
     * POST /api/qodmin/preparation-screens/{screenId}/update-priority
     */
    public function updatePriority($screenId = null) {
        $this->requirePermission('preparation-screens.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $screenId = $screenId ?? $queryParams['screen_id'] ?? $input['screen_id'] ?? '';
        $printerId = $input['printer_id'] ?? '';
        $priority = isset($input['priority']) ? intval($input['priority']) : 1;
        
        if (empty($screenId) || empty($printerId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        if (!$this->preparationScreenPrinterService) {
            $this->toastNotificationService->sendApiResponse('error', 'Service kullanılamıyor.', [], 503);
            return;
        }
        
        $businessId = $_SESSION['business_id'] ?? null;
        if (empty($businessId)) {
            $this->toastNotificationService->sendApiResponse('error', 'İşletme bilgisi bulunamadı.', [], 400);
            return;
        }
        
        try {
            $result = $this->preparationScreenPrinterService->updatePriority($screenId, $printerId, $businessId, $priority);
            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'Öncelik güncellenemedi', [], 500);
            }
        } catch (\Exception $e) {
            error_log("PreparationScreenPrinterController::updatePriority - Error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'Öncelik güncellenemedi: ' . $e->getMessage(), [], 500);
        }
    }
}
