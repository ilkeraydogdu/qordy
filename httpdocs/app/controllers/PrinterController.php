<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class PrinterController extends \App\Core\Controller {
    protected $printerService;
    protected $zonePrinterMappingService;
    
    public function __construct() {
        parent::__construct();
        $this->printerService = \App\Core\DependencyFactory::getPrinterService();
        $this->zonePrinterMappingService = \App\Core\DependencyFactory::getZonePrinterMappingService();
    }
    
    public function index() {
        // Redirect to bridge-setup page - all printer management is done there now
        header('Location: ' . BASE_URL . '/business/printers/bridge-setup');
        exit;
    }
    
    public function register() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.create')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $printerName = sanitizeInput($input['printer_name'] ?? '');
            $printerSerial = sanitizeInput($input['printer_serial'] ?? '');
            $printerLocation = sanitizeInput($input['printer_location'] ?? '');
            $port = sanitizeInput($input['port'] ?? '');
            
            if (empty($printerName) || empty($printerSerial) || empty($printerLocation)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            // Check if serial already exists
            $existingPrinter = $this->printerService->getPrinterBySerial($printerSerial);
            if ($existingPrinter) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.duplicate_entry', [], 400);
                return;
            }
            
            // CRITICAL: Ensure business_id is set for tenant isolation
            $tenantId = \App\Core\TenantContext::getId();
            if (!$tenantId && !$this->isSuperAdmin()) {
                \App\Core\Logger::error('PrinterController::register - No tenant context', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'Tenant context required', [], 400);
                return;
            }
            
            $printerData = [
                'printer_name' => $printerName,
                'printer_serial' => $printerSerial,
                'printer_location' => $printerLocation,
                'port' => $port,
                'printer_model' => 'XP-Q805K',
                'connection_type' => 'USB'
            ];
            
            // Add tenant_id for tenant isolation (printers table uses tenant_id column)
            if ($tenantId) {
                $printerData['tenant_id'] = $tenantId;
                $printerData['business_id'] = $tenantId; // legacy, stripped if column missing
            }
            
            $result = $this->printerService->registerPrinter($printerData);
            
            if ($result) {
                $this->apiResponse(['success' => true, 'printer_id' => $result]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.save_failed', [], 500);
            }
        }
    }
    
    public function testConnection() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Use printers.manage permission instead of printers.test (which doesn't exist)
        if (!$this->checkPermissionOrFail('printers.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $printerSerial = sanitizeInput($input['printer_serial'] ?? '');
            
            if (empty($printerSerial)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            // CRITICAL: Verify printer belongs to current tenant
            $printer = $this->printerService->getPrinterBySerial($printerSerial);
            if ($printer && !$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $printerBusinessId = $printer['tenant_id'] ?? null;
                
                if (!$tenantId || $printerBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('PrinterController::testConnection - Printer tenant isolation violation', [
                        'printer_serial' => $printerSerial,
                        'printer_business_id' => $printerBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            $result = $this->printerService->testPrinterConnection($printerSerial);
            
            $this->apiResponse([
                'success' => $result,
                'message' => $result ? 'Bağlantı başarılı' : 'Bağlantı başarısız'
            ]);
        }
    }
    
    public function update() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.edit')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $printerId = $input['printer_id'] ?? '';
            $printerName = sanitizeInput($input['printer_name'] ?? '');
            $screenId = $input['screen_id'] ?? '';
            $screenIds = isset($input['screen_ids']) ? $input['screen_ids'] : ($screenId ? [$screenId] : []);
            
            // SIMPLIFIED: Only require printer name
            if (empty($printerId) || empty($printerName)) {
                $this->apiResponse(['success' => false, 'error' => 'Yazıcı ID ve adı gereklidir'], 400);
                return;
            }
            
            // Screen ID optional - can be empty to unassign
            
            // CRITICAL: Verify tenant isolation before update
            $printer = $this->printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            $tenantId = \App\Core\TenantContext::getId();
            if (!$this->isSuperAdmin()) {
                $printerBusinessId = $printer['tenant_id'] ?? null;
                
                if (!$tenantId || $printerBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('PrinterController::update - Tenant isolation violation', [
                        'printer_id' => $printerId,
                        'printer_business_id' => $printerBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            // Update printer basic info - SIMPLIFIED: Only name
            $printerData = [
                'printer_name' => $printerName
            ];
            
            $result = $this->printerService->updatePrinter($printerId, $printerData);
            
            if (!$result) {
                $this->apiResponse(['success' => false, 'error' => 'Yazıcı güncellenemedi'], 500);
                return;
            }
            
            // Update screen assignments if provided
            if (is_array($screenIds) && count($screenIds) > 0) {
                try {
                    $preparationScreenPrinterService = \App\Core\DependencyFactory::getPreparationScreenPrinterService();
                    
                    // Remove all existing assignments
                    $preparationScreenPrinterService->removeAllPrinterAssignments($printerId);
                    
                    // Add new assignments
                    $assignedCount = 0;
                    foreach ($screenIds as $screenId) {
                        if (!empty($screenId)) {
                            $assigned = $preparationScreenPrinterService->assignPrinterToScreen($printerId, $screenId);
                            if ($assigned) {
                                $assignedCount++;
                            }
                        }
                    }
                    
                    $this->apiResponse([
                        'success' => true,
                        'message' => 'Yazıcı güncellendi',
                        'screens_assigned' => $assignedCount
                    ]);
                } catch (\Exception $e) {
                    \App\Core\Logger::error('PrinterController::update - Screen assignment error: ' . $e->getMessage());
                    $this->apiResponse([
                        'success' => true,
                        'message' => 'Yazıcı adı güncellendi ama ekran ataması yapılamadı',
                        'warning' => $e->getMessage()
                    ]);
                }
            } else {
                $this->apiResponse(['success' => true, 'message' => 'Yazıcı güncellendi']);
            }
        }
    }
    
    public function delete() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.delete')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $printerId = $input['printer_id'] ?? $queryParams['id'] ?? '';
            
            if (empty($printerId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            // CRITICAL: Verify tenant isolation before deletion
            $printer = $this->printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $printerBusinessId = $printer['tenant_id'] ?? null;
                
                if (!$tenantId || $printerBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('PrinterController::delete - Tenant isolation violation', [
                        'printer_id' => $printerId,
                        'printer_business_id' => $printerBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            $result = $this->printerService->deletePrinter($printerId);
            
            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
            }
        }
    }
    
    public function getAll() {
        if (!$this->checkPermissionOrFail('printers.view')) {
            return;
        }
        
        $printers = $this->printerService->getAllPrinters();
        $this->apiResponse($printers);
    }
    
    /**
     * Get printer by ID
     * GET /api/qodmin/printer/{id}
     */
    public function getPrinter($printerId = null) {
        if (!$this->checkPermissionOrFail('printers.view')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $printerId = $printerId ?? $queryParams['id'] ?? $queryParams['printer_id'] ?? '';
        
        if (empty($printerId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $printer = $this->printerService->getPrinterById($printerId);
            if ($printer) {
                $this->apiResponse(['success' => true, 'printer' => $printer]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            }
        } catch (\Exception $e) {
            error_log("PrinterController::getPrinter - Error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.load_failed', [], 500);
        }
    }
    
    /**
     * Get preparation screens assigned to printer
     * GET /api/business/printer/{id}/screens
     */
    public function getPrinterScreens($printerId = null) {
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.view')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $printerId = $printerId ?? $queryParams['id'] ?? $queryParams['printer_id'] ?? '';
        
        if (empty($printerId)) {
            $this->apiResponse(['success' => false, 'error' => 'Printer ID required'], 400);
            return;
        }
        
        try {
            // Verify printer exists and belongs to tenant
            $printer = $this->printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->apiResponse(['success' => false, 'error' => 'Printer not found'], 404);
                return;
            }
            
            $tenantId = \App\Core\TenantContext::getId();
            if (!$this->isSuperAdmin() && $printer['tenant_id'] !== $tenantId) {
                $this->apiResponse(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }
            
            // Get assigned screens
            $prepScreenPrinterService = \App\Core\DependencyFactory::getService('PreparationScreenPrinterService');
            $screens = $prepScreenPrinterService->getScreensByPrinter($printerId);
            
            $this->apiResponse([
                'success' => true,
                'screens' => $screens
            ]);
            
        } catch (\Exception $e) {
            \App\Core\Logger::error('PrinterController::getPrinterScreens - Error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'Failed to load screens'], 500);
        }
    }
    
    /**
     * Test print - Send test receipt to printer
     * POST /api/business/printer/test-print
     */
    public function testPrint() {
        $this->ensureTenantContext();
        
        // Use printers.manage permission instead of printers.test (which doesn't exist)
        if (!$this->checkPermissionOrFail('printers.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $printerId = $input['printer_id'] ?? '';
        
        if (empty($printerId)) {
            $this->apiResponse(['success' => false, 'error' => 'Printer ID required'], 400);
            return;
        }
        
        try {
            // Verify printer exists and belongs to tenant
            $printer = $this->printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->apiResponse(['success' => false, 'error' => 'Printer not found'], 404);
                return;
            }
            
            $tenantId = \App\Core\TenantContext::getId();
            if (!$this->isSuperAdmin() && $printer['tenant_id'] !== $tenantId) {
                $this->apiResponse(['success' => false, 'error' => 'Unauthorized'], 403);
                return;
            }
            
            // Get business info for test receipt
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getCustomerById($tenantId);
            
            $businessName = $customer['company_name'] ?? 'Test İşletme';
            
            // Create test receipt data
            $testReceiptData = [
                'type' => 'TEST',
                'printer_id' => $printerId,
                'business_name' => $businessName,
                'printer_name' => $printer['printer_name'] ?? 'Yazıcı',
                'printer_serial' => $printer['printer_serial'] ?? '',
                'test_time' => date('d.m.Y H:i:s')
            ];
            
            // Send to print queue
            $receiptService = \App\Core\DependencyFactory::getReceiptService();
            $result = $receiptService->addTestPrintToQueue($printerId, $testReceiptData);
            
            if ($result) {
                $this->apiResponse([
                    'success' => true,
                    'message' => 'Test yazdırma kuyruğa eklendi'
                ]);
            } else {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Test yazdırma kuyruğa eklenemedi'
                ], 500);
            }
            
        } catch (\Exception $e) {
            \App\Core\Logger::error('PrinterController::testPrint - Error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'Test yazdırma başarısız'], 500);
        }
    }

    public function getByLocation() {
        if (!$this->checkPermissionOrFail('printers.view')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $location = $queryParams['location'] ?? '';
        if (empty($location)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $printers = $this->printerService->getPrintersByLocation($location);
        $this->apiResponse($printers);
    }
    
    /**
     * Bridge setup page - shows configuration code/QR for Windows app
     * IMPORTANT: This page should ONLY be accessible from main domain (qordy.com/qodmin/*)
     * NOT from subdomains (subdomains are for staff login and bridge connections only)
     */
    public function bridgeSetup() {
        // AUTH REQUIRED
        if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
            header('Location: /login');
            exit;
        }
        
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Tenant-aware: super admin can view any business via ?business_id=XXX
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $queryBusinessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin && $queryBusinessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($queryBusinessId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                }
            } catch (\Exception $e) {
                error_log("bridgeSetup: Error setting tenant context: " . $e->getMessage());
            }
        }
        
        if (!$isSuperAdmin || $queryBusinessId) {
            $this->ensureTenantContext();
        }
        
        // Canonical tenant id (session + TenantContext), resolved centrally.
        $businessId = \App\Core\TenantResolver::resolve();
        
        if (!$businessId && isset($_SESSION['user_id'])) {
            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                $stmt = $db->prepare("SELECT tenant_id AS business_id FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($user && !empty($user['tenant_id'])) {
                    $businessId = $user['tenant_id'];
                    $_SESSION['business_id'] = $businessId;
                }
            } catch (\Exception $e) {
                error_log("bridgeSetup: Error fetching business_id: " . $e->getMessage());
            }
        }
        
        // Get existing config code
        $existingToken = null;
        if ($businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getCustomerById($businessId);
                if ($customer && !empty($customer['config_code'])) {
                    $existingToken = $customer['config_code'];
                }
            } catch (\Exception $e) {
                error_log("Bridge Setup - Error getting config code: " . $e->getMessage());
            }
        }
        
        // Get business info
        $businessName = '';
        
        // Set API base URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'qordy.com';
        $apiBaseUrl = $protocol . '://' . $host;
        
        if ($businessId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getCustomerById($businessId);
                if ($customer) {
                    $businessName = $customer['company_name'] ?? '';
                    if (empty($businessName)) {
                        $businessName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                    }
                }
            } catch (\Exception $e) {
                error_log("PrinterController::bridgeSetup - Error getting business info: " . $e->getMessage());
            }
        }
        
        $data = [
            'api_base_url' => $apiBaseUrl,
            'existing_token' => $existingToken,
            'token_expires' => null,
            'business_name' => $businessName,
            'business_id' => $businessId,
            'is_super_admin' => $isSuperAdmin
        ];
        
        $this->view('admin/bridge_setup', $data);
    }
    
    /**
     * Get zones assigned to a printer
     * GET /api/qodmin/printer/zones/:printer_id
     */
    public function getZones() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.view')) {
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $printerId = $queryParams['printer_id'] ?? '';
        
        if (empty($printerId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        // CRITICAL: Verify printer belongs to current tenant
        $printer = $this->printerService->getPrinterById($printerId);
        if ($printer && !$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $printerBusinessId = $printer['tenant_id'] ?? null;
            
            if (!$tenantId || $printerBusinessId !== $tenantId) {
                \App\Core\Logger::warning('PrinterController::getZones - Printer tenant isolation violation', [
                    'printer_id' => $printerId,
                    'printer_business_id' => $printerBusinessId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        try {
            $zones = $this->zonePrinterMappingService->getZonesByPrinter($printerId);
            $this->apiResponse(['success' => true, 'zones' => $zones]);
        } catch (\Exception $e) {
            error_log("PrinterController::getZones - Error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.load_failed', [], 500);
        }
    }
    
    /**
     * Assign printer to zone
     * POST /api/qodmin/printer/assign-zone
     */
    public function assignToZone() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.edit')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $printerId = $input['printer_id'] ?? '';
            $zoneId = $input['zone_id'] ?? '';
            $priority = intval($input['priority'] ?? 1);
            
            if (empty($printerId) || empty($zoneId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            // CRITICAL: Verify printer and zone belong to current tenant
            $printer = $this->printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Yazıcı'], 404);
                return;
            }
            
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $zone = $zoneService->getZoneById($zoneId);
            if (!$zone) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Bölge'], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $printerBusinessId = $printer['tenant_id'] ?? null;
                $zoneBusinessId = $zone['tenant_id'] ?? null;
                
                if (!$tenantId || $printerBusinessId !== $tenantId || $zoneBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('PrinterController::assignToZone - Tenant isolation violation', [
                        'printer_id' => $printerId,
                        'zone_id' => $zoneId,
                        'printer_business_id' => $printerBusinessId,
                        'zone_business_id' => $zoneBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
                
                $businessId = $tenantId;
            } else {
                $businessId = $_SESSION['business_id'] ?? $printer['tenant_id'] ?? null;
            }
            
            if (empty($businessId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            try {
                $result = $this->zonePrinterMappingService->assign($printerId, $zoneId, $businessId, $priority);
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.save_failed', [], 500);
                }
            } catch (\Exception $e) {
                error_log("PrinterController::assignToZone - Error: " . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.save_failed', [], 500);
            }
        }
    }
    
    /**
     * Remove printer from zone
     * POST /api/qodmin/printer/remove-zone
     */
    public function removeFromZone() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.edit')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $printerId = $input['printer_id'] ?? '';
            $zoneId = $input['zone_id'] ?? '';
            
            if (empty($printerId) || empty($zoneId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            // CRITICAL: Verify printer and zone belong to current tenant
            $printer = $this->printerService->getPrinterById($printerId);
            if (!$printer) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Yazıcı'], 404);
                return;
            }
            
            $zoneService = \App\Core\DependencyFactory::getZoneService();
            $zone = $zoneService->getZoneById($zoneId);
            if (!$zone) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Bölge'], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $printerBusinessId = $printer['tenant_id'] ?? null;
                $zoneBusinessId = $zone['tenant_id'] ?? null;
                
                if (!$tenantId || $printerBusinessId !== $tenantId || $zoneBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('PrinterController::removeFromZone - Tenant isolation violation', [
                        'printer_id' => $printerId,
                        'zone_id' => $zoneId,
                        'printer_business_id' => $printerBusinessId,
                        'zone_business_id' => $zoneBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            try {
                $result = $this->zonePrinterMappingService->remove($printerId, $zoneId);
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
                }
            } catch (\Exception $e) {
                error_log("PrinterController::removeFromZone - Error: " . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
            }
        }
    }
    
    /**
     * Get bridges for current business
     * GET /api/business/printer/bridges
     */
    public function getBridges() {
        // NO AUTH CHECK - Business ID'yi al
        $businessId = $_GET['business_id'] ?? \App\Core\TenantResolver::resolve() ?? ($_SESSION['user_id'] ?? null);
        
        if (!$businessId) {
            // Business ID yoksa boş array döndür
            $this->apiResponse([
                'success' => true,
                'data' => [],
                'message' => 'No business ID found in session'
            ]);
            return;
        }
        
        try {
            // Use PrinterBridgeService to get bridges
            $printerBridgeService = \App\Core\DependencyFactory::getPrinterBridgeService();
            $bridges = $printerBridgeService->getBridgesByBusiness($businessId);
            
            $this->apiResponse([
                'success' => true,
                'data' => $bridges ?: []
            ]);
        } catch (\Exception $e) {
            error_log("PrinterController::getBridges - Error: " . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'message' => 'Error fetching bridges',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get printers for a specific bridge
     * GET /api/business/printer/bridge/{bridgeId}/printers
     */
    public function getBridgePrinters($bridgeId = null) {
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.view')) {
            return;
        }
        
        // Get bridge ID from route parameter or query
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $bridgeId = $bridgeId ?? $queryParams['bridge_id'] ?? '';
        
        if (empty($bridgeId)) {
            $this->apiResponse(['success' => false, 'error' => 'Bridge ID required'], 400);
            return;
        }
        
        try {
            // Get printers from bridge
            $printers = $this->printerService->getPrintersByBridge($bridgeId);
            
            // Enrich with preparation screen assignments
            $preparationScreenPrinterService = \App\Core\DependencyFactory::getPreparationScreenPrinterService();
            
            foreach ($printers as &$printer) {
                $printerId = $printer['printer_id'] ?? '';
                if ($printerId) {
                    // Get assigned screens for this printer
                    $assignments = $preparationScreenPrinterService->getScreensForPrinter($printerId);
                    $printer['assigned_screens'] = $assignments;
                    
                    // Get first assigned screen name for display
                    if (!empty($assignments)) {
                        $printer['assigned_screen'] = $assignments[0]['screen_name'] ?? '';
                        $printer['assigned_screen_id'] = $assignments[0]['screen_id'] ?? '';
                    }
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'data' => $printers
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('PrinterController::getBridgePrinters - Error: ' . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'error' => 'Failed to load printers'
            ], 500);
        }
    }
    
    /**
     * Create a new printer bridge
     * POST /api/qodmin/printer/bridge/create
     */
    public function createBridge() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.create')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $bridgeName = trim(sanitizeInput($input['bridge_name'] ?? ''));
        
        // Validate bridge name
        if (empty($bridgeName)) {
            $this->toastNotificationService->sendApiResponse('error', 'Köprü adı zorunludur', [], 400);
            return;
        }
        
        if (strlen($bridgeName) < 3 || strlen($bridgeName) > 100) {
            $this->toastNotificationService->sendApiResponse('error', 'Köprü adı 3-100 karakter arasında olmalıdır', [], 400);
            return;
        }
        
        $businessId = \App\Core\TenantContext::getId() ?? $_SESSION['business_id'] ?? null;
        if (!$businessId) {
            \App\Core\Logger::error('PrinterController::createBridge - No business ID found', [
                'session_business_id' => $_SESSION['business_id'] ?? null,
                'tenant_id' => \App\Core\TenantContext::getId() ?? null
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'İşletme ID bulunamadı', [], 400);
            return;
        }
        
        try {
            // Ensure table exists
            $printerBridgeService = \App\Core\DependencyFactory::getPrinterBridgeService();
            $printerBridgeService->ensureTableExists();
            
            // Check maximum bridge limit (5 per business)
            $existingBridges = $printerBridgeService->getBridgesByBusiness($businessId);
            
            if (count($existingBridges) >= 5) {
                $this->toastNotificationService->sendApiResponse('error', 'Maksimum 5 köprü oluşturabilirsiniz', [], 400);
                return;
            }
            
            // Generate unique API key
            $apiKey = bin2hex(random_bytes(32)); // 64 char hex
            
            // Prepare bridge data
            require_once __DIR__ . '/../helpers/functions.php';
            $bridgeData = [
                'bridge_name' => $bridgeName,
                'api_key' => $apiKey
            ];
            
            // Register bridge using service
            $result = $printerBridgeService->registerBridge($bridgeData, $businessId);
            
            if ($result && is_array($result)) {
                $this->apiResponse([
                    'success' => true,
                    'message' => 'Köprü başarıyla oluşturuldu',
                    'data' => $result
                ]);
            } else {
                \App\Core\Logger::error('PrinterController::createBridge - registerBridge returned false or null', [
                    'bridge_name' => $bridgeName,
                    'business_id' => $businessId,
                    'result' => $result
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'Köprü oluşturulamadı. Lütfen tekrar deneyin.', [], 500);
            }
        } catch (\PDOException $e) {
            \App\Core\Logger::error('PrinterController::createBridge - PDOException: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'Veritabanı hatası: ' . $e->getMessage(), [], 500);
        } catch (\Exception $e) {
            \App\Core\Logger::error('PrinterController::createBridge - Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'Köprü oluşturulurken hata oluştu: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Update printer bridge name
     * PUT /api/qodmin/printer/bridge/update
     */
    public function updateBridge() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.edit')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $bridgeId = sanitizeInput($input['bridge_id'] ?? '');
            $bridgeName = trim(sanitizeInput($input['bridge_name'] ?? ''));
            
            // Validate inputs
            if (empty($bridgeId) || empty($bridgeName)) {
                $this->toastNotificationService->sendApiResponse('error', 'Gerekli alanlar eksik', [], 400);
                return;
            }
            
            if (strlen($bridgeName) < 3 || strlen($bridgeName) > 100) {
                $this->toastNotificationService->sendApiResponse('error', 'Köprü adı 3-100 karakter arasında olmalıdır', [], 400);
                return;
            }
            
            $businessId = $_SESSION['business_id'] ?? null;
            if (!$businessId) {
                $this->toastNotificationService->sendApiResponse('error', 'İşletme ID bulunamadı', [], 400);
                return;
            }
            
            try {
                $printerBridgeService = \App\Core\DependencyFactory::getPrinterBridgeService();
                
                // Get bridge and verify tenant isolation
                $bridge = $printerBridgeService->findById($bridgeId);
                if (!$bridge) {
                    $this->toastNotificationService->sendApiResponse('error', 'Köprü bulunamadı', [], 404);
                    return;
                }
                
                // Check tenant isolation (unless super admin)
                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $bridgeBusinessId = $bridge['tenant_id'] ?? null;
                    
                    if (!$tenantId || $bridgeBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('PrinterController::updateBridge - Tenant isolation violation', [
                            'bridge_id' => $bridgeId,
                            'bridge_business_id' => $bridgeBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'Yetkiniz yok', [], 403);
                        return;
                    }
                }
                
                // Update bridge name
                $result = $printerBridgeService->update($bridgeId, [
                    'bridge_name' => $bridgeName
                ]);
                
                if ($result) {
                    $this->apiResponse([
                        'success' => true,
                        'message' => 'Köprü adı güncellendi'
                    ]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'Güncelleme başarısız', [], 500);
                }
            } catch (\Exception $e) {
                error_log("PrinterController::updateBridge - Error: " . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'Güncelleme sırasında hata oluştu', [], 500);
            }
        }
    }
    
    /**
     * Delete printer bridge
     * DELETE /api/qodmin/printer/bridge/delete
     */
    public function deleteBridge() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.delete')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input === null) {
                $input = $_POST;
            }
            
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $bridgeId = $input['bridge_id'] ?? $queryParams['id'] ?? $queryParams['bridge_id'] ?? '';
            
            if (empty($bridgeId)) {
                $this->toastNotificationService->sendApiResponse('error', 'Köprü ID gerekli', [], 400);
                return;
            }
            
            $businessId = $_SESSION['business_id'] ?? null;
            if (!$businessId) {
                $this->toastNotificationService->sendApiResponse('error', 'İşletme ID bulunamadı', [], 400);
                return;
            }
            
            try {
                $printerBridgeService = \App\Core\DependencyFactory::getPrinterBridgeService();
                
                // Get bridge and verify tenant isolation
                $bridge = $printerBridgeService->findById($bridgeId);
                if (!$bridge) {
                    $this->toastNotificationService->sendApiResponse('error', 'Köprü bulunamadı', [], 404);
                    return;
                }
                
                // Check tenant isolation (unless super admin)
                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $bridgeBusinessId = $bridge['tenant_id'] ?? null;
                    
                    if (!$tenantId || $bridgeBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('PrinterController::deleteBridge - Tenant isolation violation', [
                            'bridge_id' => $bridgeId,
                            'bridge_business_id' => $bridgeBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'Yetkiniz yok', [], 403);
                        return;
                    }
                }
                
                // Delete bridge
                $result = $printerBridgeService->delete($bridgeId);
                
                if ($result) {
                    $this->apiResponse([
                        'success' => true,
                        'message' => 'Köprü silindi'
                    ]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'Silme başarısız', [], 500);
                }
            } catch (\Exception $e) {
                error_log("PrinterController::deleteBridge - Error: " . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'Silme sırasında hata oluştu', [], 500);
            }
        }
    }
    
    /**
     * Generate config code for printer bridge
     * POST /api/business/config-code/generate
     */
    public function generateConfigCode() {
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        
        try {
            $businessId = \App\Core\TenantResolver::resolve();
            
            if (!$businessId) {
                $this->apiResponse(['success' => false, 'error' => 'Business ID not found'], 400);
                return;
            }
            
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $result = $customerService->generateConfigCode($businessId);
            
            if ($result['success']) {
                $this->apiResponse([
                    'success' => true,
                    'config_code' => $result['config_code'],
                    'message' => 'Config code generated successfully'
                ]);
            } else {
                $this->apiResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to generate config code'
                ], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('PrinterController::generateConfigCode - Error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'Failed to generate config code'], 500);
        }
    }
    
    /**
     * Get current config code
     * GET /api/business/config-code
     */
    public function getConfigCode() {
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.view')) {
            return;
        }
        
        try {
            $businessId = \App\Core\TenantResolver::resolve();
            
            if (!$businessId) {
                $this->apiResponse(['success' => false, 'error' => 'Business ID not found'], 400);
                return;
            }
            
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $result = $customerService->getConfigCode($businessId);
            
            if ($result['success']) {
                $this->apiResponse([
                    'success' => true,
                    'config_code' => $result['config_code'],
                    'has_config_code' => !empty($result['config_code'])
                ]);
            } else {
                $this->apiResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to get config code'
                ], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('PrinterController::getConfigCode - Error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'Failed to get config code'], 500);
        }
    }
    
    /**
     * Regenerate config code (delete old and create new)
     * POST /api/business/config-code/regenerate
     */
    public function regenerateConfigCode() {
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('printers.manage')) {
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }
        
        try {
            $businessId = \App\Core\TenantResolver::resolve();
            
            if (!$businessId) {
                $this->apiResponse(['success' => false, 'error' => 'Business ID not found'], 400);
                return;
            }
            
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            
            // Regenerate is same as generate (it will overwrite existing)
            $result = $customerService->generateConfigCode($businessId);
            
            if ($result['success']) {
                $this->apiResponse([
                    'success' => true,
                    'config_code' => $result['config_code'],
                    'message' => 'Config code regenerated successfully'
                ]);
            } else {
                $this->apiResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to regenerate config code'
                ], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('PrinterController::regenerateConfigCode - Error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'Failed to regenerate config code'], 500);
        }
    }
}

