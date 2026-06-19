<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class ProfileController extends Controller {
    
    public function index() {
        // Check authentication using parent method
        $this->requireLogin();
        
        $userId = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['role'] ?? '';
        $dashboardUrl = ($role === 'SUPER_ADMIN' || $role === 'QODMIN') ? '/qodmin/dashboard' : '/business/dashboard';
        
        if (!$userId) {
            $this->toastNotificationService->setFlash('error', 'Kullanıcı bilgileri bulunamadı');
            header('Location: ' . BASE_URL . $dashboardUrl);
            exit;
        }
        
        // Get user data
        $userService = \App\Core\DependencyFactory::getUserService();
        $user = $userService->findByUserId($userId);
        
        if (!$user) {
            $this->toastNotificationService->setFlash('error', 'Kullanıcı bulunamadı');
            header('Location: ' . BASE_URL . $dashboardUrl);
            exit;
        }
        
        // Get customer data (if BUSINESS_MANAGER)
        $customer = null;
        $userEmail = $user['name'] ?? ''; // Email name field'ında saklanıyor
        
        if (!empty($userEmail)) {
            try {
                $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                $customer = $customerRepo->findByEmail($userEmail);
            } catch (\Exception $e) {
                // Customer bulunamadı - devam et
            }
        }
        
        $data = [
            'user' => $user,
            'customer' => $customer,
            'is_super_admin' => $this->isSuperAdmin()
        ];
        
        $this->view('admin/profile', $data);
    }
    
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/admin/profile');
            exit;
        }
        
        // Check authentication using parent method
        $this->requireLogin();
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->toastNotificationService->setFlash('error', 'Kullanıcı bilgileri bulunamadı');
            header('Location: ' . BASE_URL . '/admin/profile');
            exit;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        // Get user data
        $userService = \App\Core\DependencyFactory::getUserService();
        $user = $userService->findByUserId($userId);
        
        if (!$user) {
            $this->toastNotificationService->setFlash('error', 'Kullanıcı bulunamadı');
            header('Location: ' . BASE_URL . '/admin/profile');
            exit;
        }
        
        // Get customer data
        $userEmail = $user['name'] ?? '';
        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
        $customer = null;
        
        if (!empty($userEmail)) {
            try {
                $customer = $customerRepo->findByEmail($userEmail);
            } catch (\Exception $e) {
                // Customer bulunamadı
            }
        }
        
        // Update customer data if exists
        if ($customer) {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            
            $updateData = [];
            
            if (isset($requestData['first_name'])) {
                $updateData['first_name'] = trim($requestData['first_name']);
            }
            
            if (isset($requestData['last_name'])) {
                $updateData['last_name'] = trim($requestData['last_name']);
            }
            
            if (isset($requestData['phone'])) {
                $updateData['phone'] = trim($requestData['phone']);
            }
            
            if (isset($requestData['password']) && !empty($requestData['password'])) {
                $updateData['password'] = $requestData['password'];
            }
            
            if (!empty($updateData)) {
                $result = $customerService->updateProfile($customer['customer_id'], $updateData);
                
                if ($result['success']) {
                    // Update user name if first_name or last_name changed
                    if (isset($updateData['first_name']) || isset($updateData['last_name'])) {
                        $firstName = $updateData['first_name'] ?? $customer['first_name'] ?? '';
                        $lastName = $updateData['last_name'] ?? $customer['last_name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        
                        if (!empty($fullName)) {
                            // Update user name (but keep email in name field for login)
                            // Actually, we should keep email in name field, so don't update user name
                        }
                    }
                    
                    // Update password in users table if password changed
                    if (isset($updateData['password']) && !empty($updateData['password'])) {
                        $passwordHash = password_hash($updateData['password'], PASSWORD_DEFAULT);
                        $userService->update($userId, ['pin' => $passwordHash]);
                    }
                    
                    $this->toastNotificationService->setFlash('success', 'Profil başarıyla güncellendi');
                } else {
                    $this->toastNotificationService->setFlash('error', $result['error'] ?? 'Profil güncellenirken bir hata oluştu');
                }
            } else {
                $this->toastNotificationService->setFlash('info', 'Değişiklik yapılmadı');
            }
        } else {
            $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı');
        }
        
        header('Location: ' . BASE_URL . '/admin/profile');
        exit;
    }
}
