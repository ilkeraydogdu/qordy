<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class ReceiptTemplateDesignController extends Controller {
    protected $designService;
    
    public function __construct() {
        parent::__construct();
        $this->designService = \App\Core\DependencyFactory::getReceiptTemplateDesignService();
    }
    
    public function getLayout() {
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $callerBusinessId = \App\Core\TenantContext::getId();
        $requestedBusinessId = $queryParams['business_id'] ?? null;
        
        // SECURITY: Only super-admins may fetch a layout by arbitrary business_id.
        // Regular business users are always pinned to their own tenant context,
        // preventing IDOR against /qodmin/receipt-templates/layout?business_id=...
        if (!empty($requestedBusinessId) && $requestedBusinessId !== $callerBusinessId && !$this->isSuperAdmin()) {
            \App\Core\Logger::warning('ReceiptTemplateDesignController::getLayout - Cross-tenant access blocked', [
                'caller_business_id' => $callerBusinessId,
                'requested_business_id' => $requestedBusinessId,
            ]);
            $this->apiResponse(['success' => false, 'error' => 'Unauthorized'], 403);
            return;
        }
        
        $businessId = !empty($requestedBusinessId) ? $requestedBusinessId : $callerBusinessId;
        
        try {
            $layout = $this->designService->getLayoutByBusinessId($businessId);
            $this->apiResponse(['success' => true, 'layout' => $layout]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('getLayout error', ['error' => $e->getMessage()]);
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function saveLayout() {
        $this->ensureTenantContext();
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $businessId = \App\Core\TenantContext::getId();
        
        try {
            $layoutId = $requestData['layout_id'] ?? null;
            $layoutData = [
                // receipt_template_layouts uses tenant_id column
                'tenant_id' => $businessId,
                'business_id' => $businessId,
                'layout_name' => $requestData['layout_name'] ?? 'Custom Layout',
                'layout_data' => $requestData['layout_data'] ?? [],
                'social_media' => $requestData['social_media'] ?? [],
                'icon_positions' => $requestData['icon_positions'] ?? [],
                'is_default' => $requestData['is_default'] ?? 0,
                'is_active' => $requestData['is_active'] ?? 1,
            ];
            
            if (!empty($requestData['template_id'])) {
                $layoutData['template_id'] = $requestData['template_id'];
            }
            
            if ($layoutId) {
                $result = $this->designService->updateLayout($layoutId, $layoutData);
                if (is_array($result) && isset($result['success']) && !$result['success']) {
                    $this->apiResponse(['success' => false, 'errors' => $result['errors']], 400);
                    return;
                }
                $this->apiResponse(['success' => true, 'layout_id' => $layoutId, 'message' => 'Layout güncellendi']);
            } else {
                $result = $this->designService->createLayout($layoutData);
                if (is_array($result) && isset($result['success']) && !$result['success']) {
                    $this->apiResponse(['success' => false, 'errors' => $result['errors']], 400);
                    return;
                }
                $this->apiResponse(['success' => true, 'layout_id' => $result, 'message' => 'Layout oluşturuldu']);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('saveLayout error', ['error' => $e->getMessage()]);
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function preview(string $layoutId = '') {
        $this->ensureTenantContext();
        
        if (empty($layoutId)) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $layoutId = $queryParams['layout_id'] ?? '';
        }
        
        try {
            $layout = $this->designService->getLayoutById($layoutId);
            if (!$layout) {
                $this->apiResponse(['success' => false, 'error' => 'Layout bulunamadı'], 404);
                return;
            }
            // SECURITY: verify the layout belongs to the caller's tenant
            if (!$this->isSuperAdmin()) {
                $callerBusinessId = \App\Core\TenantContext::getId();
                // receipt_template_layouts uses tenant_id column — prefer that, then legacy business_id
                $layoutTenantId = $layout['tenant_id'] ?? $layout['business_id'] ?? null;
                if (!empty($layoutTenantId) && (string)$layoutTenantId !== (string)$callerBusinessId) {
                    \App\Core\Logger::warning('ReceiptTemplateDesignController::preview - Cross-tenant access blocked', [
                        'caller_business_id' => $callerBusinessId,
                        'layout_tenant_id' => $layoutTenantId,
                        'layout_id' => $layoutId,
                    ]);
                    $this->apiResponse(['success' => false, 'error' => 'Layout bulunamadı'], 404);
                    return;
                }
            }
            $this->apiResponse(['success' => true, 'layout' => $layout]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function listLayouts() {
        $this->ensureTenantContext();
        
        $businessId = \App\Core\TenantContext::getId();
        
        try {
            $layouts = $this->designService->getAllLayouts($businessId);
            $this->apiResponse(['success' => true, 'layouts' => $layouts]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteLayout(string $layoutId = '') {
        $this->ensureTenantContext();
        
        if (empty($layoutId)) {
            $requestData = \App\Core\RequestParser::getRequestData();
            $layoutId = $requestData['layout_id'] ?? '';
        }
        
        if (empty($layoutId)) {
            $this->apiResponse(['success' => false, 'error' => 'Layout ID gerekli'], 400);
            return;
        }
        
        // SECURITY: verify the layout belongs to the caller's tenant before deleting
        if (!$this->assertLayoutBelongsToCaller($layoutId)) {
            return;
        }
        
        try {
            $result = $this->designService->deleteLayout($layoutId);
            $this->apiResponse(['success' => $result, 'message' => $result ? 'Layout silindi' : 'Silme başarısız']);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function setDefault(string $layoutId = '') {
        $this->ensureTenantContext();
        
        if (empty($layoutId)) {
            $requestData = \App\Core\RequestParser::getRequestData();
            $layoutId = $requestData['layout_id'] ?? '';
        }
        
        $businessId = \App\Core\TenantContext::getId();
        
        // SECURITY: verify the layout belongs to the caller's tenant
        if (!$this->assertLayoutBelongsToCaller($layoutId)) {
            return;
        }
        
        try {
            $result = $this->designService->setAsDefault($layoutId, $businessId);
            $this->apiResponse(['success' => $result, 'message' => $result ? 'Varsayılan layout güncellendi' : 'Güncelleme başarısız']);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Verifies that $layoutId belongs to the current tenant. Emits a 403/404
     * response and returns false if it does not. Super-admins bypass the check.
     */
    private function assertLayoutBelongsToCaller(string $layoutId): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }
        try {
            $layout = $this->designService->getLayoutById($layoutId);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'error' => 'Layout bulunamadı'], 404);
            return false;
        }
        if (!$layout) {
            $this->apiResponse(['success' => false, 'error' => 'Layout bulunamadı'], 404);
            return false;
        }
        $callerBusinessId = \App\Core\TenantContext::getId();
        // receipt_template_layouts uses tenant_id column — prefer that, then legacy business_id
        $layoutTenantId = $layout['tenant_id'] ?? $layout['business_id'] ?? null;
        if (!empty($layoutTenantId) && (string)$layoutTenantId !== (string)$callerBusinessId) {
            \App\Core\Logger::warning('ReceiptTemplateDesignController - Cross-tenant access blocked', [
                'caller_business_id' => $callerBusinessId,
                'layout_tenant_id' => $layoutTenantId,
                'layout_id' => $layoutId,
            ]);
            $this->apiResponse(['success' => false, 'error' => 'Unauthorized'], 403);
            return false;
        }
        return true;
    }
}
