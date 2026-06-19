<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class SubscriptionsController extends Controller {
    
    protected $subscriptionService;
    protected $customerService;
    protected $packageService;
    
    public function __construct() {
        parent::__construct();
        $this->subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
        $this->customerService = \App\Core\DependencyFactory::getCustomerService();
        $this->packageService = \App\Core\DependencyFactory::getPackageService();
    }
    
    /**
     * List all subscriptions
     */
    public function index() {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        
        if (!$isSuperAdmin) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();
            $subscriptions = $subscriptionRepo->getAll();
            $totalCollected = $subscriptionRepo->getTotalCompletedPaymentsAmount();
            
            $this->view('admin/subscriptions', [
                'subscriptions' => $subscriptions,
                'total_collected_try' => $totalCollected,
                'is_super_admin' => true
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SubscriptionsController::index error', [
                    'error' => $e->getMessage()
                ]);
            }
            
            $this->view('admin/subscriptions', [
                'subscriptions' => [],
                'total_collected_try' => 0,
                'is_super_admin' => true
            ]);
        }
    }
    
    /**
     * Show subscription details
     */
    public function show($subscriptionId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $subscription = $this->subscriptionService->getSubscriptionById($subscriptionId);
            
            if (!$subscription) {
                $this->toastNotificationService->setFlash('error', 'Abonelik bulunamadı');
                header('Location: ' . BASE_URL . '/qodmin/subscriptions');
                exit;
            }
            
            $customer = $this->customerService->getById($subscription['tenant_id'] ?? $subscription['business_id'] ?? $subscription['customer_id'] ?? '');
            $package = $this->packageService->getPackageById($subscription['package_id'] ?? '');
            $payments = \App\Core\DependencyFactory::getSubscriptionRepository()->getPaymentsForSubscription($subscriptionId);
            
            $this->view('admin/subscription_detail', [
                'subscription' => $subscription,
                'customer' => $customer,
                'package' => $package,
                'payments' => $payments
            ]);
        } catch (\Exception $e) {
            $this->toastNotificationService->setFlash('error', 'Abonelik yüklenirken bir hata oluştu');
            header('Location: ' . BASE_URL . '/qodmin/subscriptions');
            exit;
        }
    }
    
    /**
     * Activate subscription
     */
    public function activate($subscriptionId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        try {
            $result = $this->subscriptionService->activateSubscription($subscriptionId);
            
            if ($result['success']) {
                $this->toastNotificationService->setFlash('success', 'Abonelik başarıyla aktifleştirildi');
                return $this->jsonResponse(['success' => true, 'message' => 'Abonelik aktifleştirildi']);
            } else {
                return $this->jsonResponse(['success' => false, 'message' => $result['error'] ?? 'Aktifleştirme başarısız'], 500);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Cancel subscription
     */
    public function cancel($subscriptionId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        try {
            $result = $this->subscriptionService->cancelSubscription($subscriptionId);
            
            if ($result['success']) {
                $this->toastNotificationService->setFlash('success', 'Abonelik başarıyla iptal edildi');
                return $this->jsonResponse(['success' => true, 'message' => 'Abonelik iptal edildi']);
            } else {
                return $this->jsonResponse(['success' => false, 'message' => $result['error'] ?? 'İptal başarısız'], 500);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * View subscription (alias for show)
     * Note: Renamed from view() to avoid conflict with base Controller::view()
     */
    public function viewSubscription($subscriptionId) {
        $this->show($subscriptionId);
    }
    
    /**
     * Upgrade subscription
     */
    public function upgrade($subscriptionId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $newPackageId = $requestData['package_id'] ?? null;
        
        if (!$newPackageId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Package ID required'], 400);
        }
        
        try {
            $result = $this->subscriptionService->upgradeSubscription($subscriptionId, $newPackageId);
            
            if ($result['success']) {
                $this->toastNotificationService->setFlash('success', 'Abonelik başarıyla yükseltildi');
                return $this->jsonResponse(['success' => true, 'message' => 'Abonelik yükseltildi']);
            } else {
                return $this->jsonResponse(['success' => false, 'message' => $result['error'] ?? 'Yükseltme başarısız'], 500);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
