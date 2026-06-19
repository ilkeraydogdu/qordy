<?php
namespace App\Controllers\API;

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Core\Logger;

/**
 * Regenerate config code for existing bridge
 */
class PrinterBridgeRegenerateController extends Controller {
    private $db;

    public function __construct() {
        parent::__construct();
        $this->db = DependencyFactory::getDatabase();
    }
    
    /**
     * Regenerate config code
     * POST /api/business/printer-bridges/{id}/regenerate
     */
    public function regenerate($bridgeId = null) {
        if (!$bridgeId) {
            $bridgeId = $_GET['id'] ?? null;
        }
        
        $businessId = $this->resolveBusinessId();
        
        if (!$businessId) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }
        
        if (!$bridgeId) {
            $this->jsonResponse(['success' => false, 'error' => 'Bridge ID required'], 400);
            return;
        }
        
        try {
            // Verify bridge belongs to business
            $stmt = $this->db->prepare("
                SELECT bridge_id, bridge_name 
                FROM printer_bridges 
                WHERE bridge_id = ? AND tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$bridgeId, $businessId]);
            $bridge = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bridge) {
                $this->jsonResponse(['success' => false, 'error' => 'Bridge not found'], 404);
                return;
            }
            
            // Generate new config code
            $newConfigCode = hash('sha256', $businessId . $bridgeId . time() . random_bytes(32));
            
            // Update bridge
            $stmt = $this->db->prepare("
                UPDATE printer_bridges 
                SET config_code = ?, updated_at = NOW()
                WHERE bridge_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$newConfigCode, $bridgeId, $businessId]);
            
            Logger::info('Config code regenerated', [
                'bridge_id' => $bridgeId,
                'bridge_name' => $bridge['bridge_name'],
                'business_id' => $businessId
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'config_code' => $newConfigCode,
                'message' => 'Config code regenerated successfully'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Regenerate config code error: ' . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        }
    }
    
    private function resolveBusinessId(): ?string {
        $isSuperAdmin = $this->isSuperAdmin();
        if ($isSuperAdmin) {
            $queryBusinessId = $_GET['business_id'] ?? $_SESSION['selected_business_id'] ?? null;
            if ($queryBusinessId) {
                return $queryBusinessId;
            }
        }
        
        if (isset($_SESSION['business_id'])) return $_SESSION['business_id'];
        if (isset($_SESSION['customer_id'])) return $_SESSION['customer_id'];
        
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            try {
                $stmt = $this->db->prepare("SELECT tenant_id FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($user && !empty($user['tenant_id'])) return $user['tenant_id'];
            } catch (\Exception $e) {
                error_log("Error fetching business_id: " . $e->getMessage());
            }
        }
        return null;
    }
    
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
