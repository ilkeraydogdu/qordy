<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class TrialController extends Controller {
    
    protected $trialService;
    
    public function __construct() {
        parent::__construct();
        $this->trialService = \App\Core\DependencyFactory::getTrialService();
        if (!function_exists('getAdminUrl')) {
            require_once __DIR__ . '/../../helpers/url_helper.php';
        }
    }
    
    public function settings() {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = \App\Core\RequestParser::getRequestData();
            
            $settings = [
                'trial_enabled' => isset($data['trial_enabled']) ? 1 : 0,
                'trial_duration_days' => max(1, intval($data['trial_duration_days'] ?? 14)),
                'trial_package_id' => trim($data['trial_package_id'] ?? ''),
                'trial_max_products' => max(1, intval($data['trial_max_products'] ?? 10)),
                'trial_max_tables' => max(1, intval($data['trial_max_tables'] ?? 5)),
                'trial_max_staff' => max(1, intval($data['trial_max_staff'] ?? 2)),
                'trial_max_categories' => max(1, intval($data['trial_max_categories'] ?? 3)),
            ];
            
            $result = $this->trialService->updateTrialSettings($settings);
            
            if ($result) {
                $this->toastNotificationService->setFlash('success', 'Trial ayarları başarıyla güncellendi.');
            } else {
                $this->toastNotificationService->setFlash('error', 'Ayarlar güncellenirken hata oluştu.');
            }
            
            header('Location: ' . getAdminUrl('trial-settings'));
            exit;
        }
        
        $trialSettings = $this->trialService->getTrialSettings();
        $stats = $this->trialService->getTrialStats();
        
        $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
        $allPackages = $packageRepo->getAll();
        $packages = is_array($allPackages) ? array_filter($allPackages, function($p) {
            return !empty($p['is_active']);
        }) : [];
        
        $this->render('admin/trial_settings', [
            'trialSettings' => $trialSettings,
            'stats' => $stats,
            'packages' => array_values($packages),
            'title' => 'Trial Yönetimi',
            'is_super_admin' => true,
        ]);
    }
    
    public function users() {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $filter = $_GET['filter'] ?? 'all';
        $page = max(1, intval($_GET['page'] ?? 1));
        
        $result = $this->trialService->getTrialUsers($filter, $page);
        $stats = $this->trialService->getTrialStats();
        
        $this->render('admin/trial_users', [
            'users' => $result['users'],
            'total' => $result['total'],
            'page' => $result['page'],
            'totalPages' => $result['total_pages'],
            'filter' => $filter,
            'stats' => $stats,
            'title' => 'Trial Kullanıcıları',
            'is_super_admin' => true,
        ]);
    }
    
    public function extendTrial() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            \App\Core\ApiResponseHelper::error('Yetkisiz', 403);
            return;
        }
        
        $data = \App\Core\RequestParser::getRequestData();
        $subscriptionId = $data['subscription_id'] ?? '';
        $extraDays = max(1, intval($data['extra_days'] ?? 7));
        
        if (empty($subscriptionId)) {
            \App\Core\ApiResponseHelper::error('Subscription ID gerekli', 400);
            return;
        }
        
        $result = $this->trialService->extendTrial($subscriptionId, $extraDays);
        
        if ($result['success']) {
            \App\Core\ApiResponseHelper::success(['new_end_date' => $result['new_end_date']], 'Trial süresi uzatıldı');
        } else {
            \App\Core\ApiResponseHelper::error($result['error'] ?? 'Hata oluştu', 400);
        }
    }
    
    public function cancelTrial() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            \App\Core\ApiResponseHelper::error('Yetkisiz', 403);
            return;
        }
        
        $data = \App\Core\RequestParser::getRequestData();
        $subscriptionId = $data['subscription_id'] ?? '';
        
        if (empty($subscriptionId)) {
            \App\Core\ApiResponseHelper::error('Subscription ID gerekli', 400);
            return;
        }
        
        $result = $this->trialService->expireTrial($subscriptionId);
        
        if ($result) {
            \App\Core\ApiResponseHelper::success([], 'Trial iptal edildi');
        } else {
            \App\Core\ApiResponseHelper::error('Hata oluştu', 400);
        }
    }
}
