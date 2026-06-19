<?php
namespace App\Controllers\Customer;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class PackageController extends Controller {
    
    protected $packageService;
    protected $subscriptionService;
    protected $paymentService;
    protected $customerService;
    
    public function __construct() {
        parent::__construct();
        $this->packageService = \App\Core\DependencyFactory::getPackageService();
        $this->subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
        $this->paymentService = \App\Core\DependencyFactory::getPaymentService();
        $this->customerService = \App\Core\DependencyFactory::getCustomerService();
    }

    /**
     * Kayıt sonrası atanan BUSINESS_OWNER dahil — paket sayfası için izinli roller
     * (BusinessAdminController ile uyumlu).
     *
     * @return list<string>
     */
    private function packagePurchaserRoleCodes(): array {
        return [
            'BUSINESS_OWNER', 'BUSINESS_MANAGER', 'BUSINESS_ADMIN', 'MANAGER', 'TRIAL',
            'ROLE_BUSINESS_OWNER', 'ROLE_BUSINESS_MANAGER', 'ROLE_BUSINESS_ADMIN', 'ROLE_MANAGER', 'ROLE_TRIAL',
        ];
    }

    /**
     * Paket listesi / satın alma: yönetici rolü veya (NavigationPermission) packages.purchase izni.
     */
    private function userCanAccessPackagePurchaseArea(): bool {
        if ($this->auth->hasAnyRole($this->packagePurchaserRoleCodes())) {
            return true;
        }
        if ($this->auth->hasPermission('packages.purchase')) {
            return true;
        }
        return false;
    }
    
    public function index() {
        // Eski ve e-posta/banner linkleri /customer/packages kullanabiliyor; listeye yönlendir (ana sayfaya değil)
        header('Location: ' . BASE_URL . '/customer/packages/list');
        exit;
    }
    
    /**
     * List all available packages for purchase
     * Shows packages from database and allows user to select and purchase
     */
    public function listPackages() {
        try {
            $this->requireLogin();
            
            if (!$this->userCanAccessPackagePurchaseArea()) {
                $this->toastNotificationService->setFlash('error', 'Paketleri görüntülemek için işletme yöneticisi rolüne sahip olmanız gerekmektedir.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            $customerId = $_SESSION['customer_id'] ?? null;
            $hasPendingBankTransfer = false;
            if ($customerId) {
                $bankTransferService = \App\Core\DependencyFactory::getBankTransferService();
                $pendingList = $bankTransferService->getTransfersByCustomerId($customerId, 'pending');
                if (!empty($pendingList)) {
                    $hasPendingBankTransfer = true;
                    $this->toastNotificationService->setFlash('info', 'Bekleyen bir ödeme kaydınız var. O tamamlanana kadar yeni plan satın alınamaz.');
                }
            }
            
            // Get active packages
            $packages = $this->packageService->getActivePackages();
            
            // Ensure packages is an array
            if (!is_array($packages)) {
                $packages = [];
            }
            
            // Process packages: calculate discounts and format features
            foreach ($packages as &$package) {
                // Check if package_id exists
                $packageId = $package['package_id'] ?? $package['id'] ?? null;
                if (empty($packageId)) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("PackageController::listPackages - Package missing package_id", [
                            'package_name' => $package['name'] ?? 'Unknown',
                            'package_keys' => array_keys($package)
                        ]);
                    }
                    continue;
                }
                
                // Calculate discounts
                $package['monthly_discount'] = $this->packageService->calculateDiscount($package, 'monthly');
                $package['yearly_discount'] = $this->packageService->calculateDiscount($package, 'yearly');
                
                // Get discounted prices
                $package['discounted_price_monthly'] = $this->packageService->getDiscountedPrice($package, 'monthly');
                $package['discounted_price_yearly'] = $this->packageService->getDiscountedPrice($package, 'yearly');
                
                // Format features for display
                $package['features_array'] = $this->packageService->formatFeaturesForDisplay($package['features'] ?? null);
            }
            unset($package); // Break reference
            
            // Filter out packages without package_id
            $packages = array_filter($packages, function($pkg) {
                $packageId = $pkg['package_id'] ?? $pkg['id'] ?? null;
                return !empty($packageId);
            });
            
            // Reset array keys
            $packages = array_values($packages);

            // Single-package shortcut: if the tenant only has one active
            // package on offer, there is nothing meaningful to "choose"
            // from. Skip the list screen and send the user straight to
            // the purchase handler (which creates a yearly subscription
            // and forwards to /customer/payment). We still keep the list
            // view for the multi-package case so pricing can be compared.
            if (!$hasPendingBankTransfer && count($packages) === 1) {
                $onlyPackageId = $packages[0]['package_id'] ?? ($packages[0]['id'] ?? null);
                if (!empty($onlyPackageId)) {
                    header('Location: ' . BASE_URL . '/customer/packages/' . urlencode($onlyPackageId) . '/purchase?pricing_type=yearly');
                    exit;
                }
            }

            // Get current subscription if exists
            $customerId = $_SESSION['customer_id'] ?? null;
            $currentSubscription = null;
            if ($customerId) {
                try {
                    $currentSubscription = $this->subscriptionService->getCustomerSubscription($customerId);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("PackageController::listPackages - Error getting subscription", [
                            'error' => $e->getMessage(),
                            'customer_id' => $customerId
                        ]);
                    }
                }
            }
            
            // Render view
            $this->view('customer/package_list', [
                'packages' => $packages,
                'current_subscription' => $currentSubscription,
                'customer_id' => $customerId,
                'has_pending_bank_transfer' => $hasPendingBankTransfer,
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PackageController::listPackages - Error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            $this->toastNotificationService->setFlash('error', 'Paketler yüklenirken bir hata oluştu.');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }
    
    public function purchase($packageId) {
        try {
            $this->requireLogin();
            
            if (!$this->userCanAccessPackagePurchaseArea()) {
                $currentRole = $this->getCurrentRole();
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("PackageController::purchase - Access denied", [
                        'current_role' => $currentRole,
                        'package_id' => $packageId,
                        'user_id' => $_SESSION['user_id'] ?? null
                    ]);
                }
                $this->toastNotificationService->setFlash('error', 'Paket satın almak için işletme yöneticisi rolüne sahip olmanız gerekmektedir.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Support both GET and POST requests
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $pricingType = $_GET['pricing_type'] ?? 'yearly';
            } else {
                $requestData = \App\Core\RequestParser::getRequestData();
                $pricingType = $requestData['pricing_type'] ?? 'yearly';
            }
            // Sadece yıllık satış: aylık/tek seferlik gelenleri yıllığa çevir
            if (!in_array($pricingType, ['yearly'])) {
                $pricingType = 'yearly';
            }
            
            // Get customer
            $userId = $_SESSION['user_id'] ?? null;
            $userEmail = $_SESSION['username'] ?? '';
            $customer = null;
            
            if (!empty($userEmail)) {
                try {
                    $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                    $customer = $customerRepo->findByEmail($userEmail);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error("PackageController::purchase - Customer lookup error", [
                            'error' => $e->getMessage(),
                            'email' => $userEmail
                        ]);
                    }
                    $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı.');
                    header('Location: ' . BASE_URL . '/business/dashboard');
                    exit;
                }
            }
            
            if (!$customer || empty($customer['customer_id'])) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("PackageController::purchase - Customer not found", [
                        'user_email' => $userEmail,
                        'user_id' => $userId,
                        'package_id' => $packageId
                    ]);
                }
                $this->toastNotificationService->setFlash('error', 'Müşteri bilgileriniz bulunamadı. Lütfen giriş yapıp tekrar deneyin.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            $customerId = $customer['customer_id'];
            $bankTransferService = \App\Core\DependencyFactory::getBankTransferService();
            $pendingList = $bankTransferService->getTransfersByCustomerId($customerId, 'pending');
            if (!empty($pendingList)) {
                $this->toastNotificationService->setFlash('info', 'Bekleyen bir ödeme kaydınız var. O tamamlanana kadar yeni plan satın alınamaz.');
                header('Location: ' . BASE_URL . '/customer/packages/list');
                exit;
            }
            
            // Validate package ID
            if (empty($packageId)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("PackageController::purchase - Empty package_id", [
                        'customer_id' => $customer['customer_id'] ?? null,
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'pricing_type' => $pricingType
                    ]);
                }
                $this->toastNotificationService->setFlash('error', 'Paket seçimi geçersiz. Lütfen tekrar deneyin.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Verify package exists
            $package = $this->packageService->getPackageById($packageId);
            if (!$package) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("PackageController::purchase - Package not found", [
                        'package_id' => $packageId,
                        'customer_id' => $customer['customer_id'] ?? null,
                        'user_id' => $_SESSION['user_id'] ?? null
                    ]);
                }
                $this->toastNotificationService->setFlash('error', 'Seçilen paket bulunamadı. Lütfen geçerli bir paket seçin.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Create subscription
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("PackageController::purchase - Creating subscription", [
                    'package_id' => $packageId,
                    'package_name' => $package['name'] ?? 'Unknown',
                    'customer_id' => $customer['customer_id'],
                    'pricing_type' => $pricingType
                ]);
            }
            
            $result = $this->subscriptionService->createSubscription(
                $customer['customer_id'],
                $packageId,
                $pricingType
            );
            
            if (!$result['success']) {
                $errorMessage = $result['error'] ?? 'Abonelik oluşturulurken bir hata oluştu.';
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("PackageController::purchase - Subscription creation failed", [
                        'package_id' => $packageId,
                        'customer_id' => $customer['customer_id'],
                        'pricing_type' => $pricingType,
                        'error' => $errorMessage,
                        'result' => $result
                    ]);
                }
                $this->toastNotificationService->setFlash('error', $errorMessage);
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            if (empty($result['subscription_id'])) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("PackageController::purchase - Subscription created but no subscription_id returned", [
                        'package_id' => $packageId,
                        'customer_id' => $customer['customer_id'],
                        'pricing_type' => $pricingType,
                        'result' => $result
                    ]);
                }
                $this->toastNotificationService->setFlash('error', 'Abonelik oluşturuldu ancak ödeme sayfasına yönlendirilemedi. Lütfen destek ekibiyle iletişime geçin.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Log successful subscription creation
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("PackageController::purchase - Subscription created successfully", [
                    'subscription_id' => $result['subscription_id'],
                    'package_id' => $packageId,
                    'customer_id' => $customer['customer_id'],
                    'pricing_type' => $pricingType
                ]);
            }
            
            // Redirect to payment page
            header('Location: ' . BASE_URL . '/customer/payment?subscription_id=' . $result['subscription_id']);
            exit;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("PackageController::purchase - Exception", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'package_id' => $packageId ?? null,
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'customer_id' => $_SESSION['customer_id'] ?? null,
                    'pricing_type' => $_GET['pricing_type'] ?? 'unknown'
                ]);
            }
            
            // Show user-friendly error message
            $errorMessage = 'Satın alma işlemi sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin veya destek ekibiyle iletişime geçin.';
            $this->toastNotificationService->setFlash('error', $errorMessage);
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }
    
    public function processPayment() {
        $this->requireLogin();
        
        // GET: Show payment form
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $subscriptionId = $_GET['subscription_id'] ?? '';
            
            if (empty($subscriptionId)) {
                $this->toastNotificationService->setFlash('error', 'Abonelik ID gereklidir.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            // Get subscription with package
            require_once __DIR__ . '/../../repositories/SubscriptionRepository.php';
            $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();
            $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);
            
            if (!$subscription) {
                $this->toastNotificationService->setFlash('error', 'Abonelik bulunamadı.');
                header('Location: ' . BASE_URL . '/business/dashboard');
                exit;
            }
            
            $packageService = \App\Core\DependencyFactory::getPackageService();
            $package = $packageService->getPackageById($subscription['package_id']);
            
            $billingCycle = $subscription['billing_cycle'] ?? 'yearly';
            $amount = $packageService->getDiscountedPrice($package, $billingCycle);
            
            $data = [
                'subscription' => $subscription,
                'package' => $package,
                'amount' => $amount,
                'pay_result' => $_GET['pay_result'] ?? null,
                'pay_error' => isset($_GET['error']) ? (string) $_GET['error'] : null,
                'pay_transaction_id' => $_GET['transaction_id'] ?? null,
            ];
            
            $this->view('customer/payment', $data);
            return;
        }
        
        // POST: Process payment
        $requestData = \App\Core\RequestParser::getRequestData();
        $subscriptionId = $requestData['subscription_id'] ?? '';
        $paymentMethod = $requestData['payment_method'] ?? 'iyzico';
        if ($paymentMethod === 'manual') {
            $this->toastNotificationService->setFlash('error', 'Manuel ödeme artık kullanılmıyor. Lütfen online ödeme veya havale seçin.');
            header('Location: ' . BASE_URL . '/customer/payment?subscription_id=' . $subscriptionId);
            exit;
        }
        
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $bt = $settingsService->getSetting('payment_bank_transfer_enabled', '0');
        $bankTransferEnabled = ($bt === '1' || $bt === 1 || $bt === true
            || (is_string($bt) && strcasecmp($bt, 'true') === 0));
        if ($paymentMethod === 'bank_transfer' && !$bankTransferEnabled) {
            $this->toastNotificationService->setFlash('error', 'Havale ile ödeme şu an kapalıdır. Lütfen online ödeme seçin.');
            header('Location: ' . BASE_URL . '/customer/payment?subscription_id=' . $subscriptionId);
            exit;
        }
        
        if (empty($subscriptionId)) {
            $this->toastNotificationService->setFlash('error', 'Abonelik ID gereklidir.');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
        
        // Get subscription with package
        require_once __DIR__ . '/../../repositories/SubscriptionRepository.php';
        $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();
        $subscription = $subscriptionRepo->getSubscriptionWithPackage($subscriptionId);
        
        if (!$subscription) {
            $this->toastNotificationService->setFlash('error', 'Abonelik bulunamadı.');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
        
        // Get package to calculate amount
        $packageService = \App\Core\DependencyFactory::getPackageService();
        $package = $packageService->getPackageById($subscription['package_id']);
        
        if (!$package) {
            $this->toastNotificationService->setFlash('error', 'Paket bulunamadı.');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
        
        // Calculate amount from package based on billing cycle (indirimli fiyat)
        $billingCycle = $subscription['billing_cycle'] ?? 'yearly';
        $amount = $this->packageService->getDiscountedPrice($package, $billingCycle);
        
        // Handle bank transfer separately
        if ($paymentMethod === 'bank_transfer') {
            $this->handleBankTransferPayment($subscription, $package, $amount);
            return;
        }

        // iyzico payment - redirect to iyzico initiation via AJAX from payment view
        // The payment view will call /customer/payment/iyzico/initiate
        $this->toastNotificationService->setFlash('info', 'Lütfen iyzico ile ödeme yapın.');
        header('Location: ' . BASE_URL . '/customer/payment?subscription_id=' . $subscription['subscription_id']);
        exit;
    }
    
    /**
     * Handle bank transfer payment: generate unique code, show bank info,
     * accept receipt upload.
     */
    private function handleBankTransferPayment(array $subscription, array $package, float $amount): void {
        $customerId = $subscription['customer_id'] ?? $_SESSION['customer_id'] ?? '';
        $customerEmail = $_SESSION['email'] ?? '';
        if (empty($customerEmail)) {
            try {
                $customer = $this->customerService->getById($customerId);
                $customerEmail = $customer['email'] ?? 'user';
            } catch (\Exception $e) {
                $customerEmail = 'user';
            }
        }

        $bankTransferService = \App\Core\DependencyFactory::getBankTransferService();
        $uniqueCode = $bankTransferService->generateUniqueCode($customerEmail);

        $transferResult = $bankTransferService->createTransfer([
            'subscription_id' => $subscription['subscription_id'],
            'customer_id' => $customerId,
            'amount' => $amount,
            'unique_code' => $uniqueCode,
            'sender_name' => $_POST['sender_name'] ?? null,
            'status' => 'pending',
        ]);

        if (!$transferResult['success']) {
            $this->toastNotificationService->setFlash('error', 'Havale kaydı oluşturulamadı: ' . ($transferResult['error'] ?? ''));
            header('Location: ' . BASE_URL . '/customer/payment?subscription_id=' . $subscription['subscription_id']);
            exit;
        }

        $bankAccounts = $bankTransferService->getActiveBankAccounts();

        $this->view('customer/bank_transfer', [
            'subscription' => $subscription,
            'package' => $package,
            'amount' => $amount,
            'uniqueCode' => $uniqueCode,
            'transferId' => $transferResult['transfer_id'],
            'bankAccounts' => $bankAccounts,
        ]);
    }

    /**
     * Handle receipt upload for bank transfer (AJAX)
     */
    public function uploadReceipt() {
        header('Content-Type: application/json; charset=utf-8');
        $this->requireLogin();

        $transferId = $_POST['transfer_id'] ?? '';
        if (empty($transferId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transfer ID gereklidir.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dekont dosyası seçilmedi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $bankTransferService = \App\Core\DependencyFactory::getBankTransferService();

        // Also update sender info if provided
        $senderName = $_POST['sender_name'] ?? null;
        $senderIban = $_POST['sender_iban'] ?? null;
        if ($senderName || $senderIban) {
            $updateData = [];
            if ($senderName) $updateData['sender_name'] = $senderName;
            if ($senderIban) $updateData['sender_iban'] = $senderIban;
            $bankTransferService->getTransferById($transferId); // validate exists
            \App\Core\DependencyFactory::getBankTransferPaymentRepository()->update($transferId, $updateData);
        }

        $result = $bankTransferService->uploadReceipt($transferId, $_FILES['receipt']);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Dekont yüklendi. Ödemeniz onay aşamasındadır. Kısa süre içerisinde onay verilecektir.'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Yükleme başarısız.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    public function paymentCallback() {
        // Payment gateway callback handler
        $requestData = \App\Core\RequestParser::getRequestData();
        $transactionId = $requestData['transaction_id'] ?? '';
        $subscriptionId = $requestData['subscription_id'] ?? '';
        $status = $requestData['status'] ?? '';
        
        if (empty($transactionId) || empty($subscriptionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            exit;
        }
        
        // Update payment status
        require_once __DIR__ . '/../../repositories/SubscriptionPaymentRepository.php';
        $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
        $payment = $paymentRepo->getByGatewayTransactionId($transactionId);
        
        if ($payment && $status === 'completed') {
            $paymentRepo->update($payment['payment_id'], [
                'payment_status' => 'completed',
                'payment_date' => date('Y-m-d H:i:s')
            ]);
            
            // Activate subscription
            $this->subscriptionService->activateSubscription($subscriptionId);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    public function mySubscription() {
        $this->requireLogin();
        
        $userId = $_SESSION['user_id'] ?? null;
        $userEmail = $_SESSION['username'] ?? '';
        $customer = null;
        
        if (!empty($userEmail)) {
            try {
                $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                $customer = $customerRepo->findByEmail($userEmail);
            } catch (\Exception $e) {
                // Customer not found
            }
        }
        
        $activeSubscription = null;
        $allSubscriptions = [];
        
        if ($customer) {
            $activeSubscription = $this->subscriptionService->getCustomerSubscription($customer['customer_id']);
            $allSubscriptions = $this->subscriptionService->getCustomerSubscriptions($customer['customer_id']);
        }
        
        $data = [
            'activeSubscription' => $activeSubscription,
            'allSubscriptions' => $allSubscriptions,
            'customer' => $customer
        ];
        
        $this->view('customer/my_subscription', $data);
    }
    
    public function subscriptionDetail() {
        $this->requireLogin();
        
        $userEmail = $_SESSION['username'] ?? '';
        $customer = null;
        
        if (!empty($userEmail)) {
            try {
                $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                $customer = $customerRepo->findByEmail($userEmail);
            } catch (\Exception $e) {
                // Customer not found
            }
        }
        
        if (!$customer) {
            $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı.');
            header('Location: ' . BASE_URL . '/customer/packages/list');
            exit;
        }
        
        $activeSubscription = $this->subscriptionService->getCustomerSubscription($customer['customer_id']);
        
        if (!$activeSubscription) {
            $this->toastNotificationService->setFlash('info', 'Aktif aboneliğiniz bulunmamaktadır.');
            header('Location: ' . BASE_URL . '/customer/packages/list');
            exit;
        }
        
        // Get package details
        $package = $this->packageService->getPackageById($activeSubscription['package_id']);
        
        // Get subscription payments
        $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
        $payments = $paymentRepo->getBySubscriptionId($activeSubscription['subscription_id']);
        
        $data = [
            'subscription' => $activeSubscription,
            'package' => $package,
            'payments' => $payments,
            'customer' => $customer
        ];
        
        $this->view('customer/subscription_detail', $data);
    }
    
    public function paymentHistory() {
        try {
            $this->requireLogin();
            
            $userEmail = $_SESSION['username'] ?? '';
            $customer = null;
            
            if (!empty($userEmail)) {
                try {
                    $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                    $customer = $customerRepo->findByEmail($userEmail);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to get customer repository in paymentHistory', [
                            'error' => $e->getMessage(),
                            'email' => $userEmail
                        ]);
                    }
                    // Customer not found - will be handled below
                }
            }
            
            if (!$customer) {
                $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı.');
                header('Location: ' . BASE_URL . '/customer/packages/list');
                exit;
            }
            
            // Get all payments for customer with error handling
            $payments = [];
            try {
                $paymentRepo = \App\Core\DependencyFactory::getSubscriptionPaymentRepository();
                $payments = $paymentRepo->getByCustomerId($customer['customer_id']);
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to get payment history', [
                        'error' => $e->getMessage(),
                        'customer_id' => $customer['customer_id'] ?? null
                    ]);
                }
                // Continue with empty array - user will see no payments
                $payments = [];
            }
            
            // Ensure payments is an array
            if (!is_array($payments)) {
                $payments = [];
            }
            
            $data = [
                'payments' => $payments,
                'customer' => $customer
            ];
            
            $this->view('customer/payment_history', $data);
            
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('paymentHistory error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            $this->toastNotificationService->setFlash('error', 'Ödeme geçmişi yüklenirken bir hata oluştu.');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }
    
    public function savedCards() {
        try {
            $this->requireLogin();
            
            $userEmail = $_SESSION['username'] ?? '';
            $customer = null;
            
            if (!empty($userEmail)) {
                try {
                    $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                    $customer = $customerRepo->findByEmail($userEmail);
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to get customer repository', [
                            'error' => $e->getMessage(),
                            'email' => $userEmail
                        ]);
                    }
                    // Customer not found - will be handled below
                }
            }
            
            if (!$customer) {
                $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı.');
                header('Location: ' . BASE_URL . '/customer/packages/list');
                exit;
            }
            
            // Get saved cards with error handling
            $savedCards = [];
            try {
                if ($this->paymentService) {
                    $savedCards = $this->paymentService->getSavedCards($customer['customer_id']);
                } else {
                    // Fallback: try to get payment service directly
                    $paymentService = \App\Core\DependencyFactory::getPaymentService();
                    $savedCards = $paymentService->getSavedCards($customer['customer_id']);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to get saved cards', [
                        'error' => $e->getMessage(),
                        'customer_id' => $customer['customer_id'] ?? null
                    ]);
                }
                // Continue with empty array - user will see no saved cards
                $savedCards = [];
            }
            
            // Ensure savedCards is an array
            if (!is_array($savedCards)) {
                $savedCards = [];
            }
            
            $data = [
                'savedCards' => $savedCards,
                'customer' => $customer
            ];
            
            $this->view('customer/saved_cards', $data);
            
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('savedCards error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
            $this->toastNotificationService->setFlash('error', 'Kayıtlı kartlar yüklenirken bir hata oluştu.');
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }
    }
    
    public function cancelSubscription() {
        $this->requireLogin();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/customer/subscription');
            exit;
        }
        
        $userEmail = $_SESSION['username'] ?? '';
        $customer = null;
        
        if (!empty($userEmail)) {
            try {
                $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                $customer = $customerRepo->findByEmail($userEmail);
            } catch (\Exception $e) {
                // Customer not found
            }
        }
        
        if (!$customer) {
            $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri bulunamadı.');
            header('Location: ' . BASE_URL . '/customer/subscription');
            exit;
        }
        
        $activeSubscription = $this->subscriptionService->getCustomerSubscription($customer['customer_id']);
        
        if (!$activeSubscription) {
            $this->toastNotificationService->setFlash('error', 'Aktif aboneliğiniz bulunmamaktadır.');
            header('Location: ' . BASE_URL . '/customer/subscription');
            exit;
        }
        
        $result = $this->subscriptionService->cancelSubscription($activeSubscription['subscription_id']);
        
        if ($result['success']) {
            $this->toastNotificationService->setFlash('success', 'Aboneliğiniz iptal edildi.');
        } else {
            $this->toastNotificationService->setFlash('error', $result['error'] ?? 'Abonelik iptal edilemedi.');
        }
        
        header('Location: ' . BASE_URL . '/customer/subscription');
        exit;
    }
}
