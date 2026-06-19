<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class ZoneController extends \App\Core\Controller {
    protected $zoneService;
    protected $zonePrinterMappingService;
    
    public function __construct() {
        parent::__construct();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
        $this->zonePrinterMappingService = \App\Core\DependencyFactory::getZonePrinterMappingService();
    }
    
    /**
     * Index page - show zones management view
     */
    public function index() {
        // CRITICAL: Ensure tenant context before permission check
        $this->ensureTenantContext();
        
        // CRITICAL: If tenant context is not set, try to set it from session
        if (!\App\Core\TenantContext::isSet()) {
            $customerId = \App\Core\TenantResolver::resolve();
            if ($customerId) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($customerId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from session', [
                            'customer_id' => $customerId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        $this->requirePermission('tables.view');
        
        $data = [
            'zones' => $this->zoneService->getZonesWithTableCount()
        ];
        
        $this->view('admin/zones', $data);
    }
    
    /**
     * Get all zones (API endpoint)
     * @return void
     */
    public function getZones() {
        // CRITICAL: Ensure tenant context is set before fetching zones
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $zones = $this->zoneService->getZonesWithTableCount();
        $this->apiResponse($zones);
    }
    
    /**
     * Get zone by ID (API endpoint)
     * @param string|null $zoneId Zone ID
     * @return void
     */
    public function getZone($zoneId = null) {
        // CRITICAL: Ensure tenant context is set before fetching zone
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $zoneId = $zoneId ?? $queryParams['id'] ?? '';
        if (empty($zoneId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $zone = $this->zoneService->getZoneById($zoneId);
        if ($zone) {
            // CRITICAL: Verify tenant isolation - ensure zone belongs to current tenant
            $isSuperAdmin = $this->isSuperAdmin();
            if (!$isSuperAdmin) {
                $tenantId = \App\Core\TenantContext::getId();
                $zoneBusinessId = $zone['business_id'] ?? $zone['tenant_id'] ?? null;
                
                // If zone has no tenant column, verify through tables
                if (!$zoneBusinessId) {
                    // Check if zone has any tables belonging to current tenant
                    $tableService = \App\Core\DependencyFactory::getTableService();
                    $tables = $tableService->getAllTables();
                    $hasTableInZone = false;
                    foreach ($tables as $table) {
                        if (($table['zone_id'] ?? null) === $zoneId) {
                            $hasTableInZone = true;
                            break;
                        }
                    }
                    if (!$hasTableInZone) {
                        \App\Core\Logger::warning('ZoneController::getZone - Tenant isolation violation (no tables in zone)', [
                            'zone_id' => $zoneId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                } elseif ($zoneBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('ZoneController::getZone - Tenant isolation violation', [
                        'zone_id' => $zoneId,
                        'zone_business_id' => $zoneBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            $this->apiResponse($zone);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
        }
    }
    
    /**
     * Create zone (API endpoint)
     * @return void
     */
    public function createZone() {
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Super admin için business_id query parametresinden tenant context set et
        if ($isSuperAdmin) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $businessId = $queryParams['business_id'] ?? null;
            
            if ($businessId) {
                // Tenant context'i işletme ID'sine göre set et
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from business_id in createZone', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // CRITICAL: Ensure tenant context is set (for non-super-admin users)
        $this->ensureTenantContext();
        
        try {
            if (!$this->hasPermission('tables.manage')) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }
            
            $name = sanitizeInput($data['name'] ?? '');
            $description = sanitizeInput($data['description'] ?? '');
            $floor = sanitizeInput($data['floor'] ?? '');
            
            if (empty($name)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            $zoneData = [
                'name' => $name,
                'description' => $description,
                'floor' => $floor
            ];
            
            $result = $this->zoneService->createZone($zoneData);
            
            if ($result) {
                $this->apiResponse(['success' => true, 'zone_id' => $result]);
            } else {
                // Check if zone already exists
                $existing = $this->zoneService->getZoneByName($name);
                if ($existing) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.duplicate_entry', [], 400);
                } else {
                    \App\Core\Logger::error('Zone creation failed without exception', [
                        'data' => $zoneData
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
                }
            }
        } catch (\PDOException $e) {
            \App\Core\Logger::error('PDO Error creating zone: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'errorInfo' => $e->errorInfo ?? [],
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? []
            ]);
            
            // Check for specific database errors
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            // Check for duplicate entry error (unique constraint violation)
            if ($errorCode == 23000 && (strpos($errorMessage, 'Duplicate entry') !== false || strpos($errorMessage, '1062') !== false)) {
                // Extract zone name from error message if possible
                $zoneName = $data['name'] ?? 'bu isim';
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.duplicate_entry', ['field' => 'Bölge adı', 'value' => $zoneName], 400);
            } elseif (strpos($errorMessage, "doesn't exist") !== false || strpos($errorMessage, "Unknown column") !== false) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.database_schema', [], 500);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Error creating zone: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? []
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    /**
     * Update zone (API endpoint)
     * @param string|null $zoneId Zone ID
     * @return void
     */
    public function updateZone($zoneId = null) {
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Super admin için business_id query parametresinden tenant context set et
        if ($isSuperAdmin) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $businessId = $queryParams['business_id'] ?? null;
            
            if ($businessId) {
                // Tenant context'i işletme ID'sine göre set et
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from business_id in updateZone', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // CRITICAL: Ensure tenant context is set (for non-super-admin users)
        $this->ensureTenantContext();
        
        try {
            if (!$this->hasPermission('tables.manage')) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
                return;
            }
            
            $queryParams = \App\Core\RequestParser::getQueryParams();
        $zoneId = $zoneId ?? $queryParams['id'] ?? '';
            if (empty($zoneId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            // CRITICAL: Verify tenant isolation before update
            $existingZone = $this->zoneService->getZoneById($zoneId);
            if (!$existingZone) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$isSuperAdmin) {
                $tenantId = \App\Core\TenantContext::getId();
                $zoneBusinessId = $existingZone['business_id'] ?? $existingZone['tenant_id'] ?? null;
                
                // If zone has no tenant column, verify through tables
                if (!$zoneBusinessId) {
                    $tableService = \App\Core\DependencyFactory::getTableService();
                    $tables = $tableService->getAllTables();
                    $hasTableInZone = false;
                    foreach ($tables as $table) {
                        if (($table['zone_id'] ?? null) === $zoneId) {
                            $hasTableInZone = true;
                            break;
                        }
                    }
                    if (!$hasTableInZone) {
                        \App\Core\Logger::warning('ZoneController::updateZone - Tenant isolation violation (no tables in zone)', [
                            'zone_id' => $zoneId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                } elseif ($zoneBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('ZoneController::updateZone - Tenant isolation violation', [
                        'zone_id' => $zoneId,
                        'zone_business_id' => $zoneBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }
            
            $name = sanitizeInput($data['name'] ?? '');
            $description = sanitizeInput($data['description'] ?? '');
            $floor = sanitizeInput($data['floor'] ?? '');
            
            if (empty($name)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            $zoneData = [
                'name' => $name,
                'description' => $description,
                'floor' => $floor
            ];
            
            $result = $this->zoneService->updateZone($zoneId, $zoneData);
            
            if ($result) {
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Error updating zone: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'zoneId' => $zoneId ?? null,
                'data' => $data ?? []
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    /**
     * Delete zone (API endpoint)
     * @param string|null $zoneId Zone ID
     * @return void
     */
    public function deleteZone($zoneId = null) {
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // Super admin için business_id query parametresinden tenant context set et
        if ($isSuperAdmin) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $businessId = $queryParams['business_id'] ?? null;
            
            if ($businessId) {
                // Tenant context'i işletme ID'sine göre set et
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from business_id in deleteZone', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // CRITICAL: Ensure tenant context is set (for non-super-admin users)
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.manage')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $zoneId = $zoneId ?? $queryParams['id'] ?? '';
        if (empty($zoneId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // CRITICAL: Verify tenant isolation before deletion
        $zone = $this->zoneService->getZoneById($zoneId);
        if (!$zone) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
            return;
        }
        
        // Check tenant isolation (unless super admin)
        if (!$isSuperAdmin) {
            $tenantId = \App\Core\TenantContext::getId();
            $zoneBusinessId = $zone['business_id'] ?? $zone['tenant_id'] ?? null;
            
            // If zone has no tenant column, verify through tables
            if (!$zoneBusinessId) {
                $tableService = \App\Core\DependencyFactory::getTableService();
                $tables = $tableService->getAllTables();
                $hasTableInZone = false;
                foreach ($tables as $table) {
                    if (($table['zone_id'] ?? null) === $zoneId) {
                        $hasTableInZone = true;
                        break;
                    }
                }
                if (!$hasTableInZone) {
                    \App\Core\Logger::warning('ZoneController::deleteZone - Tenant isolation violation (no tables in zone)', [
                        'zone_id' => $zoneId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            } elseif ($zoneBusinessId !== $tenantId) {
                \App\Core\Logger::warning('ZoneController::deleteZone - Tenant isolation violation', [
                    'zone_id' => $zoneId,
                    'zone_business_id' => $zoneBusinessId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        $result = $this->zoneService->deleteZone($zoneId);
        
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    /**
     * Get all floors (API endpoint)
     * @return void
     */
    public function getFloors() {
        // CRITICAL: Ensure tenant context is set before fetching floors
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $floors = $this->zoneService->getAllFloors();
        $this->apiResponse($floors);
    }
    
    /**
     * Get zones grouped by floor (API endpoint)
     * @return void
     */
    public function getZonesGroupedByFloor() {
        // CRITICAL: Ensure tenant context is set before fetching zones
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $grouped = $this->zoneService->getZonesGroupedByFloor();
        $this->apiResponse($grouped);
    }
    
    /**
     * Get printers assigned to a zone
     * GET /api/qodmin/zone/printers/:zone_id
     * @param string|null $zoneId Zone ID
     * @return void
     */
    public function getPrinters($zoneId = null) {
        if (!$this->hasPermission('printers.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $zoneId = $zoneId ?? $queryParams['zone_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($zoneId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        try {
            $printers = $this->zonePrinterMappingService->getPrintersByZone($zoneId);
            $this->apiResponse(['success' => true, 'printers' => $printers]);
        } catch (\Exception $e) {
            error_log("ZoneController::getPrinters - Error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.load_failed', [], 500);
        }
    }
}

