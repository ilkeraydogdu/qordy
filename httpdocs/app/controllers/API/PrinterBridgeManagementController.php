<?php
namespace App\Controllers\API;

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * Printer Bridge Management API
 * Web panelinden köprü yönetimi için endpoint'ler
 */
class PrinterBridgeManagementController extends Controller {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = DependencyFactory::getDatabase();
    }
    
    /**
     * List bridges for current business
     * GET /api/business/printer-bridges
     */
    public function index() {
        $businessId = $this->resolveBusinessId();
        
        if (!$businessId) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized - Please login'], 401);
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    bridge_id,
                    bridge_name,
                    config_code,
                    status,
                    last_seen,
                    last_heartbeat,
                    version,
                    os_info,
                    created_at
                FROM printer_bridges
                WHERE tenant_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$businessId]);
            $bridges = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->jsonResponse([
                'success' => true,
                'bridges' => $bridges
            ]);
            
        } catch (\Exception $e) {
            Logger::error('List bridges error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * Create new bridge with config code
     * POST /api/business/printer-bridges/create
     */
    public function create() {
        $businessId = $this->resolveBusinessId();
        
        if (!$businessId) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized - Please login'], 401);
            return;
        }
        
        $data = $this->getJsonInput();
        $bridgeName = $data['bridge_name'] ?? null;
        
        if (!$bridgeName || strlen(trim($bridgeName)) < 3) {
            $this->jsonResponse(['success' => false, 'error' => 'Bridge name required (min 3 chars)'], 400);
            return;
        }
        
        $bridgeName = htmlspecialchars(substr(trim($bridgeName), 0, 200), ENT_QUOTES, 'UTF-8');
        
        try {
            // Generate unique IDs
            $bridgeId = 'bridge_' . uniqid() . '_' . time();
            $apiKey = hash('sha256', $bridgeId . time() . random_bytes(32));
            $configCode = hash('sha256', $businessId . $bridgeId . time() . random_bytes(32));
            
            // Insert bridge
            $stmt = $this->db->prepare("
                INSERT INTO printer_bridges 
                (bridge_id, api_key, config_code, tenant_id, bridge_name, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'OFFLINE', NOW(), NOW())
            ");
            $stmt->execute([$bridgeId, $apiKey, $configCode, $businessId, $bridgeName]);
            
            Logger::info('Bridge created', [
                'bridge_id' => $bridgeId,
                'bridge_name' => $bridgeName,
                'business_id' => $businessId
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'bridge_id' => $bridgeId,
                'config_code' => $configCode,
                'message' => 'Bridge created successfully'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Create bridge error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * Delete bridge
     * DELETE /api/business/printer-bridges/{id}
     */
    public function delete($bridgeId = null) {
        if (!$bridgeId) {
            $bridgeId = $_GET['id'] ?? null;
        }
        
        $businessId = $this->resolveBusinessId();
        
        if (!$businessId) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized - Please login'], 401);
            return;
        }
        
        if (!$bridgeId) {
            $this->jsonResponse(['success' => false, 'error' => 'Bridge ID required'], 400);
            return;
        }
        
        try {
            // Delete only if belongs to this business
            $stmt = $this->db->prepare("
                DELETE FROM printer_bridges
                WHERE bridge_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$bridgeId, $businessId]);
            
            $rowsAffected = $stmt->rowCount();
            
            if ($rowsAffected === 0) {
                $this->jsonResponse(['success' => false, 'error' => 'Bridge not found'], 404);
                return;
            }
            
            // Also delete associated printers
            $stmt = $this->db->prepare("
                DELETE FROM printers
                WHERE bridge_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$bridgeId, $businessId]);
            
            Logger::info('Bridge deleted', [
                'bridge_id' => $bridgeId,
                'business_id' => $businessId
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Bridge deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Delete bridge error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    /**
     * Resolve business_id: tenant merkezi TenantResolver üzerinden gelir,
     * super admin ayrıca ?business_id=... / selected_business_id ile override edebilir.
     */
    private function resolveBusinessId(): ?string {
        // Super admin: query/session override'a izin ver.
        if (method_exists($this, 'isSuperAdmin') && $this->isSuperAdmin()) {
            $override = $_GET['business_id']
                ?? ($_SESSION['selected_business_id'] ?? null);
            if (!empty($override)) {
                return (string) $override;
            }
        }

        // Merkezi çözüm: Session (business_id/customer_id) -> TenantContext.
        $tenantId = \App\Core\TenantResolver::resolve();
        if ($tenantId) {
            return (string) $tenantId;
        }

        // Son çare: user_id -> users.tenant_id (merkezi çözüm yapamadığı nadir
        // durumlar için, örn. eski session şemasında business_id yoksa).
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            try {
                $stmt = $this->db->prepare("SELECT tenant_id FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($user && !empty($user['tenant_id'])) {
                    return (string) $user['tenant_id'];
                }
            } catch (\Exception $e) {
                Logger::error('PrinterBridgeManagementController: resolveBusinessId users lookup failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
    
    private function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    protected function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
