<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class AccountController extends Controller {
    
    public function index() {
        // Require login and BUSINESS_MANAGER role
        $this->requireLogin();
        
        $userId = $_SESSION['user_id'] ?? null;
        $customerId = $_SESSION['customer_id'] ?? null;
        
        if (!$userId) {
            $this->toastNotificationService->setFlash('error', 'Kullanıcı bilgileri bulunamadı');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
        
        // Get user data
        $userService = \App\Core\DependencyFactory::getUserService();
        $user = $userService->findByUserId($userId);
        
        if (!$user) {
            $this->toastNotificationService->setFlash('error', 'Kullanıcı bulunamadı');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
        
        // Get customer data
        $customer = null;
        if ($customerId) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getCustomerById($customerId);
            } catch (\Exception $e) {
                // Customer not found
            }
        }
        
        $data = [
            'user' => $user,
            'customer' => $customer,
            'page' => 'account'
        ];
        
        $this->view('customer/account', $data);
    }
    
    public function update() {
        try {
            // Require login
            $this->requireLogin();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: ' . BASE_URL . '/business/account');
                exit;
            }
            
            // Validate CSRF token
            $csrfToken = $_POST['csrf_token'] ?? '';
            require_once __DIR__ . '/../../core/Security/CSRFManager.php';
            if (!\App\Core\Security\CSRFManager::validateToken($csrfToken)) {
                $this->toastNotificationService->sendApiResponse('error', 'Geçersiz istek', [], 403);
                return;
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            $customerId = $_SESSION['customer_id'] ?? null;
            
            if (!$userId || !$customerId) {
                $this->toastNotificationService->setFlash('error', 'Kullanıcı bilgileri bulunamadı');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Get customer service with error handling
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to get CustomerService', [
                        'error' => $e->getMessage(),
                        'user_id' => $userId
                    ]);
                }
                $this->toastNotificationService->setFlash('error', 'Sistem hatası oluştu. Lütfen tekrar deneyin.');
                header('Location: ' . BASE_URL . '/business/account');
                exit;
            }
            
            // Update customer data
            $updateData = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
            ];
            
            // Update email if changed
            if (!empty($_POST['email'])) {
                try {
                    $newEmail = trim($_POST['email']);
                    $customer = $customerService->getCustomerById($customerId);
                    if ($customer && $customer['email'] !== $newEmail) {
                        // Check if email already exists
                        $existing = $customerService->findByEmail($newEmail);
                        if ($existing && $existing['customer_id'] !== $customerId) {
                            $this->toastNotificationService->setFlash('error', 'Bu email adresi başka bir kullanıcı tarafından kullanılıyor');
                            header('Location: ' . BASE_URL . '/business/account');
                            exit;
                        }
                        $updateData['email'] = $newEmail;
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to check email', [
                            'error' => $e->getMessage(),
                            'user_id' => $userId
                        ]);
                    }
                    // Continue without email update if check fails
                }
            }
            
            try {
                $customerService->updateCustomer($customerId, $updateData);
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to update customer', [
                        'error' => $e->getMessage(),
                        'user_id' => $userId,
                        'customer_id' => $customerId
                    ]);
                }
                throw $e; // Re-throw to be caught by outer catch
            }
            
            // Update session data
            if (!empty($updateData['first_name'])) {
                $_SESSION['first_name'] = $updateData['first_name'];
            }
            if (!empty($updateData['last_name'])) {
                $_SESSION['last_name'] = $updateData['last_name'];
            }
            
            // Update password if provided
            if (!empty($_POST['new_password'])) {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if ($newPassword !== $confirmPassword) {
                    $this->toastNotificationService->setFlash('error', 'Yeni şifreler eşleşmiyor');
                    header('Location: ' . BASE_URL . '/business/account');
                    exit;
                }
                
                // Verify current password
                try {
                    $userService = \App\Core\DependencyFactory::getUserService();
                    $user = $userService->findByUserId($userId);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to get UserService', [
                            'error' => $e->getMessage(),
                            'user_id' => $userId
                        ]);
                    }
                    $this->toastNotificationService->setFlash('error', 'Kullanıcı bilgileri alınamadı');
                    header('Location: ' . BASE_URL . '/business/account');
                    exit;
                }
                
                // Check password - try both 'password' and 'pin' fields
                $currentPasswordHash = $user['password'] ?? $user['pin'] ?? '';
                $passwordValid = false;
                
                if ($currentPasswordHash) {
                    // Try password_verify first (for hashed passwords)
                    if (password_verify($currentPassword, $currentPasswordHash)) {
                        $passwordValid = true;
                    } else {
                        // Try direct comparison (for encrypted PINs)
                        require_once __DIR__ . '/../../helpers/EncryptionHelper.php';
                        try {
                            $decryptedPin = \App\Helpers\EncryptionHelper::decrypt($currentPasswordHash);
                            if ($decryptedPin === $currentPassword) {
                                $passwordValid = true;
                            }
                        } catch (\Exception $e) {
                            // Decryption failed, password is invalid
                        }
                    }
                }
                
                if ($user && $passwordValid) {
                    try {
                        $userService->updatePassword($userId, $newPassword);
                        
                        // Clear force_password_change flag from session
                        \App\Core\SessionManager::remove('force_password_change');
                        
                        $this->toastNotificationService->setFlash('success', 'Şifreniz başarıyla değiştirildi. Artık sistemi kullanabilirsiniz.');
                    } catch (\Exception $e) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Failed to update password', [
                                'error' => $e->getMessage(),
                                'user_id' => $userId
                            ]);
                        }
                        $this->toastNotificationService->setFlash('error', 'Şifre güncellenirken bir hata oluştu');
                        header('Location: ' . BASE_URL . '/business/account');
                        exit;
                    }
                } else {
                    $this->toastNotificationService->setFlash('error', 'Mevcut şifre hatalı');
                    header('Location: ' . BASE_URL . '/business/account');
                    exit;
                }
            }
            
            $this->toastNotificationService->setFlash('success', 'Hesap bilgileriniz başarıyla güncellendi');
            header('Location: ' . BASE_URL . '/business/account');
            exit;
            
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Account update error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'user_id' => $userId ?? null
                ]);
            }
            $this->toastNotificationService->setFlash('error', 'Hesap güncellenirken bir hata oluştu');
            header('Location: ' . BASE_URL . '/business/account');
            exit;
        }
    }
}
