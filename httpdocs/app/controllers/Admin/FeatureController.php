<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class FeatureController extends Controller {
    
    protected $featureService;
    
    public function __construct() {
        parent::__construct();
        $this->featureService = \App\Core\DependencyFactory::getFeatureService();
    }
    
    public function features() {
        $this->requirePermission('settings.edit');
        
        $features = $this->featureService->getAll();
        
        $data = [
            'features' => $features
        ];
        
        $this->view('admin/features', $data);
    }
    
    public function toggleFeature() {
        $this->requirePermission('settings.edit');
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $featureKey = $requestData['feature_key'] ?? '';
        $enabled = isset($requestData['enabled']) ? (bool)$requestData['enabled'] : false;
        
        if (empty($featureKey)) {
            $this->apiResponse(['success' => false, 'error' => 'Feature key gerekli'], 400);
            return;
        }
        
        $result = $this->featureService->updateStatus($featureKey, $enabled);
        
        if ($result) {
            $this->apiResponse(['success' => true]);
        } else {
            $this->apiResponse(['success' => false, 'error' => 'Güncelleme başarısız'], 500);
        }
    }
}
