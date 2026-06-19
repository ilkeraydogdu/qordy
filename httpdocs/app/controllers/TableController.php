<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
use App\Core\Helpers\ConstantsHelper;

class TableController extends \App\Core\Controller {
    protected $tableService;
    protected $orderService;
    protected $zoneService;
    
    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->orderService = \App\Core\DependencyFactory::getOrderService();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
    }
    
    public function index() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('tables.view');
        
        $data = [
            'tables' => $this->tableService->getAllTables(),
            'active_tables' => $this->tableService->getActiveTables(),
            'zones' => $this->zoneService->getAllZones()
        ];
        
        $this->view('admin/tables', $data);
    }
    
    public function add() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('tables.manage');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            // Validate request data using ValidationService
            $validationResult = $this->validateRequestData($requestData, 'table');
            
            if (!$validationResult['valid']) {
                $firstError = reset($validationResult['errors']);
                $errorMsg = is_array($firstError) ? reset($firstError) : $firstError;
                $this->toastNotificationService->setFlash('error', 'notifications.warning.missing_fields');
                header('Location: ' . BASE_URL . '/admin/tables/add');
                exit;
            }
            
            $validatedData = $validationResult['data'];
            
            require_once __DIR__ . '/../helpers/functions.php';
            $tableId = generateId('table');
            
            // CRITICAL: Ensure business_id is set for tenant isolation
            $tenantId = \App\Core\TenantContext::getId();
            if (!$tenantId && !$this->isSuperAdmin()) {
                \App\Core\Logger::error('TableController::add - No tenant context', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                $this->toastNotificationService->setFlash('error', 'Tenant context required');
                header('Location: ' . BASE_URL . '/admin/tables/add');
                exit;
            }
            
            $tableData = [
                'table_id' => $tableId,
                'name' => $validatedData['name'] ?? '',
                'zone_id' => $requestData['zone_id'] ?? '', // zone_id might not be in validation rules
                'floor' => $requestData['floor'] ?? '',
                'section' => $requestData['section'] ?? '',
                'capacity' => intval($validatedData['capacity'] ?? 4),
                'status' => 'FREE'
            ];
            
            // Add tenant_id for tenant isolation (tables table uses tenant_id column)
            if ($tenantId) {
                $tableData['tenant_id'] = $tenantId;
                // Keep business_id too for callers that may read it; BaseRepository strips unknown columns
                $tableData['business_id'] = $tenantId;
            }
            
            // Additional validation
            if (empty($tableData['name']) || empty($tableData['zone_id'])) {
                $this->toastNotificationService->setFlash('error', 'notifications.warning.missing_fields');
                header('Location: ' . BASE_URL . '/admin/tables/add');
                exit;
            }
            
            // CRITICAL: Verify zone belongs to current tenant
            if (!empty($tableData['zone_id'])) {
                try {
                    $zoneService = \App\Core\DependencyFactory::getZoneService();
                    $zone = $zoneService->getZoneById($tableData['zone_id']);
                    if (!$zone) {
                        $this->toastNotificationService->setFlash('error', 'Zone not found');
                        header('Location: ' . BASE_URL . '/admin/tables/add');
                        exit;
                    }
                    
                    // Check tenant isolation (unless super admin)
                    if (!$this->isSuperAdmin() && $tenantId) {
                        $zoneBusinessId = $zone['tenant_id'] ?? null;
                        if ($zoneBusinessId !== $tenantId) {
                            \App\Core\Logger::warning('TableController::add - Zone tenant isolation violation', [
                                'zone_id' => $tableData['zone_id'],
                                'zone_business_id' => $zoneBusinessId,
                                'tenant_id' => $tenantId
                            ]);
                            $this->toastNotificationService->setFlash('error', 'Unauthorized zone');
                            header('Location: ' . BASE_URL . '/admin/tables/add');
                            exit;
                        }
                    }
                    
                    if (!empty($zone['name'])) {
                        $tableData['zone'] = $zone['name'];
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("TableController: Error fetching zone for table: " . $e->getMessage());
                }
            }
            
            // URL and QR code are auto-generated in createTable
            $result = $this->tableService->createTable($tableData);
            
            if ($result) {
                $this->toastNotificationService->setFlash('success', 'notifications.success.table_created');
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.create_failed');
            }
            
            header('Location: ' . BASE_URL . '/admin/tables');
            exit;
        }
        
        $data = [
            'zones' => $this->zoneService->getAllZones()
        ];
        
        $this->view('admin/add_table', $data);
    }
    
    public function updateStatus() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.manage')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $tableId = $requestData['table_id'] ?? '';
            $status = $requestData['status'] ?? '';
            
            $validStatuses = ['FREE', 'OCCUPIED', 'PAYMENT_PENDING', 'DIRTY', 'RESERVED'];
            
            if (!empty($tableId) && in_array($status, $validStatuses)) {
                // CRITICAL: Verify tenant isolation before update
                $table = $this->tableService->getTableById($tableId);
                if (!$table) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
                    return;
                }
                
                // Check tenant isolation (unless super admin)
                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $tableBusinessId = $table['tenant_id'] ?? null;
                    
                    if (!$tenantId || $tableBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('TableController::updateStatus - Tenant isolation violation', [
                            'table_id' => $tableId,
                            'table_business_id' => $tableBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
                
                $result = $this->tableService->updateTableStatus($tableId, $status);
                
                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function delete() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        
        if (empty($tableId)) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
            header('Location: ' . BASE_URL . '/admin/tables');
            exit;
        }
        
        // CRITICAL: Verify tenant isolation before deletion
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.table_not_found');
            header('Location: ' . BASE_URL . '/admin/tables');
            exit;
        }
        
        // Check tenant isolation (unless super admin)
        // If table belongs to current tenant, allow deletion (business owner can delete their own tables)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $tableBusinessId = $table['tenant_id'] ?? $table['tenant_id'] ?? null;

            // If tenant matches, allow deletion (business owner deleting their own table)
            if ($tenantId && $tableBusinessId === $tenantId) {
                // Table belongs to current tenant - allow deletion
                // Permission check is bypassed for business owners deleting their own tables
            } else {
                // Table doesn't belong to current tenant - check permission
                if (!$this->hasPermission('tables.manage')) {
                    \App\Core\Logger::warning('TableController::delete - Permission denied', [
                        'table_id' => $tableId,
                        'table_business_id' => $tableBusinessId,
                        'table_tenant_id' => $table['tenant_id'] ?? null,
                        'tenant_id' => $tenantId,
                        'has_permission' => false
                    ]);
                    $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                    header('Location: ' . BASE_URL . '/admin/tables');
                    exit;
                }

                // Even with permission, don't allow deleting other tenant's tables
                if (!$tenantId || $tableBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('TableController::delete - Tenant isolation violation', [
                        'table_id' => $tableId,
                        'table_business_id' => $tableBusinessId,
                        'table_tenant_id' => $table['tenant_id'] ?? null,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                    header('Location: ' . BASE_URL . '/admin/tables');
                    exit;
                }
            }
        } else {
            // Super admin - still check permission
            if (!$this->hasPermission('tables.manage')) {
                $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                header('Location: ' . BASE_URL . '/admin/tables');
                exit;
            }
        }

        // Check for active orders (excludes SERVED and CANCELLED)
        $activeOrders = $this->orderService->getActiveOrdersByTable($tableId);

        if (count($activeOrders) > 0) {
            $this->toastNotificationService->setFlash('error', 'notifications.warning.confirm_action');
            header('Location: ' . BASE_URL . '/admin/tables');
            exit;
        }

        $result = $this->tableService->deleteTable($tableId);

        if ($result) {
            $this->toastNotificationService->setFlash('success', 'notifications.success.table_deleted');
        } else {
            $this->toastNotificationService->setFlash('error', 'notifications.error.delete_failed');
        }

        header('Location: ' . BASE_URL . '/admin/tables');
        exit;
    }
    
    public function transfer() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.transfer')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $fromTableId = $requestData['from_table_id'] ?? '';
            $toTableId = $requestData['to_table_id'] ?? '';
            
            if (!empty($fromTableId) && !empty($toTableId) && $fromTableId !== $toTableId) {
                // CRITICAL: Verify tenant isolation for both tables
                $fromTable = $this->tableService->getTableById($fromTableId);
                $toTable = $this->tableService->getTableById($toTableId);
                
                if (!$fromTable || !$toTable) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
                    return;
                }
                
                // Check tenant isolation (unless super admin)
                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $fromTableBusinessId = $fromTable['tenant_id'] ?? null;
                    $toTableBusinessId = $toTable['tenant_id'] ?? null;
                    
                    if (!$tenantId || $fromTableBusinessId !== $tenantId || $toTableBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('TableController::transfer - Tenant isolation violation', [
                            'from_table_id' => $fromTableId,
                            'to_table_id' => $toTableId,
                            'from_table_business_id' => $fromTableBusinessId,
                            'to_table_business_id' => $toTableBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
                
                $orders = $this->orderService->getOrdersByTable($fromTableId);
                
                foreach ($orders as $order) {
                    $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
                    $this->orderService->updateOrderStatus($order['order_id'], $pendingStatus); // Reset status
                    $this->orderService->update($order['order_id'], [
                        'table_id' => $toTableId,
                        'table_name' => $toTable['name'] ?? ''
                    ]);
                }
                
                // Talepleri yeni masaya taşı: garson çağrısı, hesap, iptal talepleri
                $notificationService = \App\Core\DependencyFactory::getNotificationService();
                $notificationService->updateTableId($fromTableId, $toTableId);
                
                // Azaltma/iptal onay taleplerindeki masa bilgisini güncelle
                $orderIds = array_column($orders, 'order_id');
                $approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
                $approvalService->updateTableForOrders($orderIds, $toTableId, $toTable['name'] ?? '');
                
                $this->tableService->updateTableStatus($fromTableId, 'FREE');
                $this->tableService->updateTableStatus($toTableId, 'OCCUPIED');
                
                $this->apiResponse(['success' => true]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }
    
    public function getTables() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $tables = $this->tableService->getAllTables();
        $this->apiResponse($tables);
    }
    
    public function generateQRCode() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // QR kod görüntüleme için tables.view yeterli (tables.manage gerekmez)
        if (!$this->hasPermission('tables.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        
        if (!empty($tableId)) {
            // CRITICAL: Verify tenant isolation
            $table = $this->tableService->getTableById($tableId);
            if (!$table) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $tableBusinessId = $table['tenant_id'] ?? null;
                
                if (!$tenantId || $tableBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('TableController::generateQRCode - Tenant isolation violation', [
                        'table_id' => $tableId,
                        'table_business_id' => $tableBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            $qrCodeUrl = $this->tableService->generateQRCodeForTable($tableId);
            
            if ($qrCodeUrl) {
                // Also return the table URL (SEO-friendly) for display
                $tableUrl = $table['url'] ?? $this->tableService->generateTableUrl($tableId, true);
                
                $this->apiResponse([
                    'success' => true, 
                    'qr_code_url' => $qrCodeUrl,
                    'table_url' => $tableUrl
                ]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
    }
    
    public function getTable($tableId = null) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('tables.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $tableId ?? $queryParams['id'] ?? '';
        if (empty($tableId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_not_found', [], 404);
            return;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $tableBusinessId = $table['tenant_id'] ?? null;
            
            if (!$tenantId || $tableBusinessId !== $tenantId) {
                \App\Core\Logger::warning('TableController::getTable - Tenant isolation violation', [
                    'table_id' => $tableId,
                    'table_business_id' => $tableBusinessId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        $this->apiResponse($table);
    }
    
}
