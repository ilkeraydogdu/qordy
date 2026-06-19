<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class TablesController extends Controller {
    protected $tableService;
    protected $zoneService;
    
    public function __construct() {
        parent::__construct();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
        $this->zoneService = \App\Core\DependencyFactory::getZoneService();
    }
    
    public function tables() {
        // NO AUTH CHECK
        
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // İşletme ID'sini query parametresinden al (super admin için)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin && $businessId) {
            // Tenant context'i işletme ID'sine göre set et
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to set tenant context from business_id', [
                        'business_id' => $businessId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        $tables = $this->tableService->getAllTables();
        $zones = $this->tableService->getAllZones();
        
        $data = [
            'tables' => $tables ?: [],
            'zones' => $zones ?: [],
            'is_super_admin' => $isSuperAdmin
        ];
        
        $this->view('admin/tables', $data);
    }
    
    public function zones() {
        // NO AUTH CHECK
        
        $zones = $this->zoneService->getZonesWithTableCount();
        
        $data = [
            'zones' => $zones ?: []
        ];
        
        $this->view('admin/zones', $data);
    }
    
    public function tableHistory() {
        // CRITICAL: Ensure tenant context is set
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
        
        $this->requirePermission('table.history');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $tableId = $queryParams['id'] ?? '';
        if (empty($tableId)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/table-history\/([^\/\?]+)/', $path, $matches)) {
                $tableId = $matches[1];
            }
        }
        
        if (empty($tableId)) {
            $errorMessage = $this->toastNotificationService->translate('notifications.error.invalid_data');
            $this->view('admin/error', ['message' => $errorMessage]);
            return;
        }
        
        $table = $this->tableService->getTableById($tableId);
        if (!$table) {
            $errorMessage = $this->toastNotificationService->translate('notifications.error.table_not_found');
            $this->view('admin/error', ['message' => $errorMessage]);
            return;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $tableTenantId = $table['tenant_id'] ?? $table['business_id'] ?? null;

            if (!$tenantId || (string)$tableTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('Admin/TablesController::tableHistory - Tenant isolation violation', [
                    'table_id' => $tableId,
                    'table_tenant_id' => $tableTenantId,
                    'tenant_id' => $tenantId
                ]);
                $errorMessage = $this->toastNotificationService->translate('notifications.error.unauthorized');
                $this->view('admin/error', ['message' => $errorMessage]);
                return;
            }
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $startDate = $queryParams['start_date'] ?? date('Y-m-01');
        $endDate = $queryParams['end_date'] ?? date('Y-m-d');
        
        $archivedSessionService = \App\Core\DependencyFactory::getArchivedSessionService();
        $sessions = $archivedSessionService->getByDateRange($startDate . ' 00:00:00', $endDate . ' 23:59:59');
        
        $tableSessions = array_filter($sessions, function($session) use ($tableId) {
            return ($session['table_id'] ?? '') === $tableId;
        });
        
        $data = [
            'table_id' => $tableId,
            'table_name' => $table['name'] ?? 'Masa',
            'sessions' => array_values($tableSessions),
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        $this->view('admin/table_history', $data);
    }
}

