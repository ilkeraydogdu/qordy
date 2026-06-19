<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class POSDeviceController extends Controller {
    
    public function posDevices() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
            header('Location: ' . BASE_URL . '/admin');
            exit;
        }

        $posDeviceRepository = \App\Core\DependencyFactory::getPOSDeviceRepository();
        $devices = $posDeviceRepository->getAll();

        $data = [
            'devices' => $devices,
            'is_super_admin' => $this->isSuperAdmin()
        ];

        $this->view('admin/pos-devices', $data);
    }

    public function updatePOSDevice() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $deviceId = $requestData['device_id'] ?? '';
        $isEnabled = isset($requestData['is_enabled']) ? (bool)$requestData['is_enabled'] : null;

        if (empty($deviceId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        // CRITICAL: Verify device belongs to current tenant
        // pos_devices table uses tenant_id column (business_id is kept for backward-compat)
        $posDeviceRepository = \App\Core\DependencyFactory::getPOSDeviceRepository();
        $device = $posDeviceRepository->findById($deviceId);
        if ($device && !$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $deviceTenantId = $device['tenant_id'] ?? $device['business_id'] ?? null;

            if ($deviceTenantId !== null && (string)$deviceTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('Admin/POSDeviceController::updatePOSDevice - Tenant isolation violation', [
                    'device_id' => $deviceId,
                    'device_tenant_id' => $deviceTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        if ($isEnabled !== null) {
            $result = $posDeviceRepository->updateStatus($deviceId, $isEnabled);
        } else {
            $updateData = [];
            if (isset($requestData['serial_port'])) $updateData['serial_port'] = $requestData['serial_port'];
            if (isset($requestData['network_host'])) $updateData['network_host'] = $requestData['network_host'];
            if (isset($requestData['network_port'])) $updateData['network_port'] = intval($requestData['network_port']);
            if (isset($requestData['api_endpoint'])) $updateData['api_endpoint'] = $requestData['api_endpoint'];
            if (isset($requestData['api_key'])) $updateData['api_key'] = $requestData['api_key'];
            
            $result = $posDeviceRepository->update($deviceId, $updateData);
        }

        if ($result) {
            $posDeviceService = \App\Core\DependencyFactory::getPOSDeviceService();
            $posDeviceService->reloadDevices();
            
            $this->apiResponse(['success' => true]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }

    public function testPOSDevice() {
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $deviceId = $requestData['device_id'] ?? '';

        if (empty($deviceId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $posDeviceService = \App\Core\DependencyFactory::getPOSDeviceService();
        $result = $posDeviceService->testDevice($deviceId);

        $this->apiResponse($result);
    }
    
    public function addPOSDevice() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        if (!$this->hasPermission('settings.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        $deviceName = sanitizeInput($requestData['device_name'] ?? '');
        $deviceType = sanitizeInput($requestData['device_type'] ?? 'POS');
        $connectionType = sanitizeInput($requestData['connection_type'] ?? 'serial');
        
        if (empty($deviceName)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $allowedConnectionTypes = ['serial', 'network', 'api'];
        if (!in_array($connectionType, $allowedConnectionTypes)) {
            $this->toastNotificationService->sendApiResponse('error', 'Geçersiz bağlantı tipi', [], 400);
            return;
        }
        
        // CRITICAL: Ensure business_id is set for tenant isolation
        $tenantId = \App\Core\TenantContext::getId();
        if (!$tenantId && !$this->isSuperAdmin()) {
            \App\Core\Logger::error('Admin/POSDeviceController::addPOSDevice - No tenant context', [
                'user_id' => $_SESSION['user_id'] ?? 'unknown'
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'Tenant context required', [], 400);
            return;
        }
        
        $deviceData = [
            'device_name' => $deviceName,
            'device_type' => $deviceType,
            'connection_type' => $connectionType,
            'is_enabled' => 1
        ];
        
        // Add tenant_id for tenant isolation (pos_devices uses tenant_id column)
        if ($tenantId) {
            $deviceData['tenant_id'] = $tenantId;
            $deviceData['business_id'] = $tenantId;
        }
        
        if ($connectionType === 'serial') {
            $deviceData['serial_port'] = sanitizeInput($requestData['serial_port'] ?? '');
        } elseif ($connectionType === 'network') {
            $deviceData['network_host'] = sanitizeInput($requestData['network_host'] ?? '');
            $deviceData['network_port'] = intval($requestData['network_port'] ?? 9100);
        } elseif ($connectionType === 'api') {
            $deviceData['api_endpoint'] = sanitizeInput($requestData['api_endpoint'] ?? '');
            $deviceData['api_key'] = sanitizeInput($requestData['api_key'] ?? '');
        }
        
        $posDeviceService = \App\Core\DependencyFactory::getPOSDeviceService();
        $deviceId = $posDeviceService->addDevice($deviceData);
        
        if ($deviceId) {
            $this->apiResponse([
                'success' => true,
                'device_id' => $deviceId
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    public function addPrinterPermissions() {
        try {
            $allPermissions = [
                'printers.view',
                'printers.create',
                'printers.edit',
                'printers.delete',
                'printers.test',
                'receipts.print',
                'orders.print',
                'kitchen.print',
                'bar.print'
            ];
            
            $permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
            $roleService = \App\Core\DependencyFactory::getRoleService();
            
            $addedCount = 0;
            $assignedCount = 0;
            
            foreach ($allPermissions as $permissionKey) {
                $permission = $permissionModel->getByKey($permissionKey);
                
                if (!$permission) {
                    $permissionData = [
                        'permission_key' => $permissionKey,
                        'permission_name' => ucwords(str_replace('.', ' ', $permissionKey)),
                        'description' => 'Printer and receipt printing permissions',
                        'category' => 'printers'
                    ];
                    
                    $permissionModel->create($permissionData);
                    $addedCount++;
                }
                
                $permission = $permissionModel->getByKey($permissionKey);
                if ($permission) {
                    $managerRole = $roleService->getByRoleCode('MANAGER');
                    if ($managerRole) {
                        $roleService->assignPermission($managerRole['role_id'], $permissionKey);
                        $assignedCount++;
                    }
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'added' => $addedCount,
                'assigned' => $assignedCount
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Add printer permissions error: ' . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

