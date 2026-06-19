<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
// Traits are already included in Controller base class

use App\Core\Controller;

class ErrorLogController extends Controller {
    // Traits are already included in Controller base class
    
    protected $javascriptErrorLogService;
    protected $unifiedErrorLogService;
    
    public function __construct() {
        parent::__construct();
        $this->javascriptErrorLogService = \App\Core\DependencyFactory::getJavaScriptErrorLogService();
        $this->unifiedErrorLogService = \App\Core\DependencyFactory::getUnifiedErrorLogService();
    }
    
    public function errorLogs() {
        // Super Admin bypass
        if (!$this->isSuperAdmin()) {
            $this->requirePermission('settings.view');
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = 50;
        $filters = [
            'source' => $queryParams['source'] ?? 'all',
            'type' => $queryParams['type'] ?? '',
            'level' => $queryParams['level'] ?? '',
            'date_from' => $queryParams['date_from'] ?? '',
            'date_to' => $queryParams['date_to'] ?? '',
            'user_id' => $queryParams['user_id'] ?? '',
            'resolved' => isset($queryParams['resolved']) && $queryParams['resolved'] !== '' ? (bool)$queryParams['resolved'] : null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== '' && $value !== null;
        });
        
        $result = $this->unifiedErrorLogService->getAllErrorLogs($page, $perPage, $filters);
        $statistics = $this->unifiedErrorLogService->getUnifiedStatistics();
        
        $data = [
            'title' => 'Hata Logları',
            'error_logs' => $result['logs'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages']
            ],
            'filters' => [
                'source' => $queryParams['source'] ?? 'all',
                'type' => $queryParams['type'] ?? '',
                'level' => $queryParams['level'] ?? '',
                'date_from' => $queryParams['date_from'] ?? '',
                'date_to' => $queryParams['date_to'] ?? '',
                'user_id' => $queryParams['user_id'] ?? '',
                'resolved' => isset($queryParams['resolved']) ? (bool)$queryParams['resolved'] : null
            ],
            'statistics' => $statistics
        ];
        
        $this->view('admin/error_logs', $data);
    }

 /**
 * Hata Yakalama Merkezi — analytics view with unified stats
 * (extracted from AdminController::errorAnalytics, Q4 2026 refactor)
 */
 public function errorAnalytics(): void
 {
 $this->requirePermission('settings.view');

 $queryParams = \App\Core\RequestParser::getQueryParams();
 $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
 $perPage = 50;
 $filters = [
 'source' => $queryParams['source'] ?? 'all',
 'type' => $queryParams['type'] ?? '',
 'level' => $queryParams['level'] ?? '',
 'date_from' => $queryParams['date_from'] ?? '',
 'date_to' => $queryParams['date_to'] ?? '',
 'user_id' => $queryParams['user_id'] ?? '',
 'resolved' => isset($queryParams['resolved']) && $queryParams['resolved'] !== '' ? (bool)$queryParams['resolved'] : null
 ];

 $filters = array_filter($filters, fn($value) => $value !== '' && $value !== null);
 $result = $this->unifiedErrorLogService->getAllErrorLogs($page, $perPage, $filters);
 $statistics = $this->unifiedErrorLogService->getUnifiedStatistics();

 $data = [
 'title' => 'Hata Yakalama Merkezi',
 'error_logs' => $result['logs'],
 'pagination' => [
 'total' => $result['total'],
 'page' => $result['page'],
 'per_page' => $result['per_page'],
 'total_pages' => $result['total_pages']
 ],
 'filters' => $filters,
 'statistics' => $statistics
 ];

 $this->view('admin/error_analytics', $data);
 }
}
