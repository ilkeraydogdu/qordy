<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class BusinessesController extends Controller {
    
    public function index() {
        // Require super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Get all customers with their subscriptions
            // getAllWithSubscriptions already filters out super admin users
            $customers = $customerService->getAllWithSubscriptions();

            // Business status artık customers tablosunda is_active olarak tutuluyor
            // businesses tablosu kaldırıldı, tüm bilgiler customers tablosunda
            foreach ($customers as &$customer) {
                // is_active değerini business_status olarak ekle (backward compatibility)
                $isActive = isset($customer['is_active']) ? (bool)$customer['is_active'] : false;
                $customer['business_status'] = $isActive ? 'active' : 'inactive';
                
                // Ensure subscription_id is set (may be null)
                if (!isset($customer['subscription_id'])) {
                    $customer['subscription_id'] = null;
                }
            }
            unset($customer); // Break reference
            
            // Apply filters if any (default to 'all' to show all businesses)
            $filter = $_GET['filter'] ?? 'all';
            $search = $_GET['search'] ?? '';
            
            // Apply search filter first (before status filtering)
            if ($search) {
                $searchLower = strtolower(trim($search));
                $customers = array_filter($customers, function($customer) use ($searchLower) {
                    return strpos(strtolower($customer['email'] ?? ''), $searchLower) !== false ||
                           strpos(strtolower($customer['first_name'] ?? ''), $searchLower) !== false ||
                           strpos(strtolower($customer['last_name'] ?? ''), $searchLower) !== false ||
                           strpos(strtolower($customer['company_name'] ?? ''), $searchLower) !== false ||
                           strpos(strtolower($customer['subdomain'] ?? ''), $searchLower) !== false;
                });
                // Re-index after filtering
                $customers = array_values($customers);
            }
            
            // Apply status filter
            if ($filter !== 'all') {
                $customers = array_filter($customers, function($customer) use ($filter) {
                    $hasSubscription = !empty($customer['subscription_id']);
                    $isActive = isset($customer['is_active']) ? (int)$customer['is_active'] : 1;
                    
                    if ($filter === 'active') {
                        return $isActive === 1 && $hasSubscription;
                    } elseif ($filter === 'pending') {
                        return $isActive === 1 && !$hasSubscription;
                    } elseif ($filter === 'inactive') {
                        return $isActive === 0;
                    }
                    
                    return true;
                });
                // Re-index after filtering
                $customers = array_values($customers);
                
            }
            
            $data = [
                'customers' => $customers,
                'filter' => $filter,
                'search' => $search,
                'page' => 'superadmin-businesses'
            ];
            
            $this->view('superadmin/businesses', $data);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin Businesses list error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            $data = [
                'customers' => [],
                'filter' => 'all',
                'search' => '',
                'page' => 'superadmin-businesses'
            ];
            
            $this->view('superadmin/businesses', $data);
        }
    }
    
    public function show($id) {
        // Require super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            
            $customer = $customerService->getCustomerById($id);
            if (!$customer) {
                $this->toastNotificationService->setFlash('error', 'Müşteri bulunamadı');
                header('Location: ' . BASE_URL . '/qodmin/businesses');
                exit;
            }
            
            $subscription = $subscriptionService->getCustomerSubscription($id);
            
            // Get subdomain URL
            $subdomainService = \App\Core\DependencyFactory::getSubdomainService();
            $subdomainUrl = $subdomainService->getSubdomainUrl($id);
            
            // Get more details (orders, stats, etc.)
            $totalOrders = 0;
            $totalRevenue = 0;
            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                
                // Try to get orders by customer_id (if column exists)
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE (tenant_id = :tid OR tenant_id = :bid) AND status != 'CANCELLED' AND (is_paid = 1 OR status = 'SERVED')");
                    $stmt->execute(['tid' => $id, 'bid' => $id]);
                    $stats = $stmt->fetch();
                    $totalOrders = (int)($stats['count'] ?? 0);
                    $totalRevenue = (float)($stats['revenue'] ?? 0);
                } catch (\Exception $e) {
                    // If customer_id column doesn't exist, try to get orders by user email
                    $userService = \App\Core\DependencyFactory::getUserService();
                    $user = $userService->findByEmail($customer['email']);
                    if ($user && isset($user['user_id'])) {
                        // Try to get orders by user_id or business_id
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE (tenant_id = :tid OR tenant_id = :bid) AND status != 'CANCELLED' AND (is_paid = 1 OR status = 'SERVED')");
                            $stmt->execute(['tid' => $id, 'bid' => $id]);
                            $stats = $stmt->fetch();
                            $totalOrders = (int)($stats['count'] ?? 0);
                            $totalRevenue = (float)($stats['revenue'] ?? 0);
                        } catch (\Exception $e2) {
                            // Ignore if columns don't exist
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
            
            // Additional stats for enhanced detail page
            $staffCount = 0;
            $staffList = [];
            $tableCount = 0;
            $occupiedTables = 0;
            $activeTables = 0;
            $menuItemCount = 0;
            $categoryCount = 0;
            $revenueToday = 0;
            $ordersToday = 0;
            $revenueMonth = 0;
            $ordersMonth = 0;
            $recentOrders = [];
            
            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                
                // Staff (users table: user_id, name, role, role_id, avatar - no email/is_active)
                try {
                    $stmt = $db->prepare("SELECT user_id, name, role, role_id, avatar, created_at FROM users WHERE tenant_id = :bid ORDER BY created_at DESC");
                    $stmt->execute(['bid' => $id]);
                    $staffList = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $staffCount = count($staffList);
                } catch (\Exception $e) {}
                
                // Tables
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='OCCUPIED' THEN 1 ELSE 0 END) as occupied, SUM(CASE WHEN status IN ('OCCUPIED','PAYMENT_PENDING') THEN 1 ELSE 0 END) as active FROM tables WHERE tenant_id = :tid");
                    $stmt->execute(['tid' => $id]);
                    $tableData = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $tableCount = (int)($tableData['total'] ?? 0);
                    $occupiedTables = (int)($tableData['occupied'] ?? 0);
                    $activeTables = (int)($tableData['active'] ?? 0);
                } catch (\Exception $e) {}
                
                // Menu items & categories
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM menu_items WHERE tenant_id = :tid");
                    $stmt->execute(['tid' => $id]);
                    $menuItemCount = (int)($stmt->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0);
                } catch (\Exception $e) {}
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM categories WHERE tenant_id = :tid");
                    $stmt->execute(['tid' => $id]);
                    $categoryCount = (int)($stmt->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0);
                } catch (\Exception $e) {}
                
                // Today's stats
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as rev FROM orders WHERE (tenant_id = :bid1 OR tenant_id = :bid2) AND DATE(created_at) = CURDATE() AND status != 'CANCELLED' AND (is_paid = 1 OR status = 'SERVED')");
                    $stmt->execute(['bid1' => $id, 'bid2' => $id]);
                    $todayData = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $ordersToday = (int)($todayData['cnt'] ?? 0);
                    $revenueToday = (float)($todayData['rev'] ?? 0);
                } catch (\Exception $e) {}
                
                // Month stats
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as rev FROM orders WHERE (tenant_id = :bid1 OR tenant_id = :bid2) AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m') AND status != 'CANCELLED' AND (is_paid = 1 OR status = 'SERVED')");
                    $stmt->execute(['bid1' => $id, 'bid2' => $id]);
                    $monthData = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $ordersMonth = (int)($monthData['cnt'] ?? 0);
                    $revenueMonth = (float)($monthData['rev'] ?? 0);
                } catch (\Exception $e) {}
                
                // Recent orders (last 10)
                try {
                    $stmt = $db->prepare("SELECT order_id, total_amount, status, is_paid, created_at FROM orders WHERE (tenant_id = :bid1 OR tenant_id = :bid2) ORDER BY created_at DESC LIMIT 10");
                    $stmt->execute(['bid1' => $id, 'bid2' => $id]);
                    $recentOrders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {}
                
            } catch (\Exception $e) {}
            
            $data = [
                'customer' => $customer,
                'subscription' => $subscription,
                'subdomain_url' => $subdomainUrl,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'staff_count' => $staffCount,
                'staff_list' => $staffList,
                'table_count' => $tableCount,
                'occupied_tables' => $occupiedTables,
                'active_tables' => $activeTables,
                'menu_item_count' => $menuItemCount,
                'category_count' => $categoryCount,
                'revenue_today' => $revenueToday,
                'orders_today' => $ordersToday,
                'revenue_month' => $revenueMonth,
                'orders_month' => $ordersMonth,
                'recent_orders' => $recentOrders,
                'page' => 'superadmin-business-detail'
            ];

            // ---- Trial & role info ----
            $trialData = [
                'is_trial'        => false,
                'trial_ends_at'   => null,
                'trial_end_ts'    => null,
                'days_left'       => null,
                'is_expired'      => false,
                'grace_days_left' => null,
                'fully_blocked'   => false,
            ];
            try {
                $_trialSub = null;
                if ($subscription && !empty($subscription['is_trial'])) {
                    $_trialSub = $subscription;
                } else {
                    // After the tenant-id canonicalization refactor the
                    // subscriptions table uses `tenant_id` (same value as the
                    // former `business_id`).
                    $_trialStmt = $db->prepare(
                        "SELECT * FROM subscriptions
                         WHERE tenant_id = :bid AND is_trial = 1
                         ORDER BY created_at DESC LIMIT 1"
                    );
                    $_trialStmt->execute(['bid' => $id]);
                    $_trialSub = $_trialStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                }
                if ($_trialSub) {
                    $_endsAt = $_trialSub['trial_ends_at'] ?? $_trialSub['trial_end'] ?? $_trialSub['current_period_end'] ?? null;
                    $_endTs  = $_endsAt ? strtotime($_endsAt) : null;
                    $_now    = time();
                    $_expired = $_endTs && $_endTs < $_now;
                    $_graceEndTs = $_endTs ? ($_endTs + 7 * 86400) : null;
                    $trialData = [
                        'is_trial'        => true,
                        'trial_ends_at'   => $_endsAt,
                        'trial_end_ts'    => $_endTs,
                        'days_left'       => $_endTs ? max(0, (int) ceil(($_endTs - $_now) / 86400)) : null,
                        'is_expired'      => $_expired,
                        'grace_days_left' => $_graceEndTs ? max(0, (int) ceil(($_graceEndTs - $_now) / 86400)) : null,
                        'fully_blocked'   => $_expired && $_graceEndTs && $_now > $_graceEndTs,
                        'subscription_id' => $_trialSub['subscription_id'] ?? null,
                    ];
                }
            } catch (\Exception $_te) {}

            // Owner user role info
            $ownerUser = null;
            $allRoles = [];
            try {
                $_ownerStmt = $db->prepare(
                    "SELECT u.user_id, u.name, u.role, u.role_id,
                            r.role_name, r.role_code
                     FROM users u
                     LEFT JOIN roles r ON u.role_id = r.role_id
                     WHERE u.tenant_id = :bid
                     ORDER BY u.created_at ASC LIMIT 1"
                );
                $_ownerStmt->execute(['bid' => $id]);
                $ownerUser = $_ownerStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

                // All available roles for the role-change dropdown
                $_rolesStmt = $db->query(
                    "SELECT role_id, role_name, role_code FROM roles
                     WHERE is_active = 1
                       AND role_code IN ('BUSINESS_MANAGER','TRIAL')
                     ORDER BY display_order"
                );
                $allRoles = $_rolesStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $_ue) {}

            $data['trial_data']  = $trialData;
            $data['owner_user']  = $ownerUser;
            $data['all_roles']   = $allRoles;

            $this->view('superadmin/business_detail', $data);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin Business detail error', [
                    'error' => $e->getMessage(),
                    'customer_id' => $id
                ]);
            }
            
            $this->toastNotificationService->setFlash('error', 'Müşteri bilgileri alınırken hata oluştu');
            header('Location: ' . BASE_URL . '/qodmin/businesses');
            exit;
        }
    }
    
    /**
     * Change the owner user's role for a business.
     * POST /api/qodmin/businesses/{id}/change-role
     * Body: { role_code: 'TRIAL' | 'BUSINESS_MANAGER' }
     */
    public function changeOwnerRole($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            $this->json(['success' => false, 'error' => 'Yetkisiz erişim'], 403);
            return;
        }

        $body = \App\Core\RequestParser::getRequestData();
        $roleCode = strtoupper(trim($body['role_code'] ?? ''));
        if (!in_array($roleCode, ['BUSINESS_MANAGER', 'TRIAL'], true)) {
            $this->json(['success' => false, 'error' => 'Geçersiz rol'], 400);
            return;
        }

        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $roleRow = $db->prepare("SELECT role_id FROM roles WHERE role_code = ? AND is_active = 1 LIMIT 1");
            $roleRow->execute([$roleCode]);
            $role = $roleRow->fetch(\PDO::FETCH_ASSOC);
            if (!$role) {
                $this->json(['success' => false, 'error' => 'Rol bulunamadı'], 404);
                return;
            }

            // CRITICAL: Only update the business OWNER user, never the
            // whole staff. The previous implementation ran
            //   UPDATE users SET role = ?, role_id = ? WHERE tenant_id = ?
            // which rewrote every staff member's role for the business —
            // that is what caused the "all personnel became BUSINESS_OWNER
            // / İşletme Sahibi and the staff list went empty" bug.
            require_once __DIR__ . '/../../services/BusinessOwnerResolver.php';
            $ownerResolver = new \App\Services\BusinessOwnerResolver($db);
            $ownerUserId = $ownerResolver->resolve((string)$id);

            if (empty($ownerUserId)) {
                $this->json(['success' => false, 'error' => 'Bu işletmeye bağlı sahip kullanıcı bulunamadı.'], 404);
                return;
            }

            try {
                $upd = $db->prepare("UPDATE users SET role = ?, role_id = ? WHERE user_id = ? LIMIT 1");
                $upd->execute([$roleCode, $role['role_id'], $ownerUserId]);
                $totalUpdated = $upd->rowCount();
            } catch (\Throwable $e) {
                \App\Core\Logger::error('changeOwnerRole: owner update failed', [
                    'owner_user_id' => $ownerUserId,
                    'business_id'   => $id,
                    'error'         => $e->getMessage(),
                ]);
                $this->json(['success' => false, 'error' => 'Rol güncellenirken bir hata oluştu.'], 500);
                return;
            }

            if ($totalUpdated === 0) {
                $this->json(['success' => false, 'error' => 'Sahip kullanıcı güncellenemedi.'], 404);
                return;
            }

            \App\Core\Logger::info('changeOwnerRole: owner role updated', [
                'business_id'   => $id,
                'owner_user_id' => $ownerUserId,
                'role_code'     => $roleCode,
            ]);

            $this->json([
                'success'       => true,
                'message'       => 'Rol güncellendi.',
                'rows_updated'  => $totalUpdated,
                'owner_user_id' => $ownerUserId,
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('changeOwnerRole: unexpected failure', [
                'business_id' => $id,
                'error'       => $e->getMessage(),
            ]);
            $this->json(['success' => false, 'error' => 'Rol güncellenirken bir hata oluştu.'], 500);
        }
    }

    /**
     * Start free trial for a business (super admin only)
     */
    public function startTrial($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            $this->json(['success' => false, 'error' => 'Yetkisiz erişim'], 403);
            return;
        }
        try {
            $trialService = \App\Core\DependencyFactory::getTrialService();
            $result = $trialService->createTrialSubscription((string)$id);
            // Service-level errors ("trial disabled", "already used") are
            // functional not exceptional — surface them as 400 so the UI
            // can show a toast instead of the generic 500 page.
            if (empty($result['success'])) {
                $this->json($result, 400);
                return;
            }
            $this->json($result);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('startTrial: failed', [
                'business_id' => $id,
                'error'       => $e->getMessage(),
            ]);
            $this->json(['success' => false, 'error' => 'Deneme süresi başlatılamadı.'], 500);
        }
    }

    /**
     * Activate subscription for a business (super admin only)
     */
    public function activateSubscription($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            $this->json(['success' => false, 'error' => 'Yetkisiz erişim'], 403);
            return;
        }
        try {
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $packageService = \App\Core\DependencyFactory::getPackageService();

            $packages = $packageService->getActivePackages();
            if (empty($packages)) {
                $this->json(['success' => false, 'error' => 'Aktif paket bulunamadı.'], 400);
                return;
            }

            // Prefer a package that actually has a yearly price > 0 —
            // picking `$packages[0]` blindly can land on a draft/zero-
            // priced plan and fail inside createSubscription with a
            // generic error. Fall back to the first package only if
            // nothing has an explicit yearly price.
            $package = null;
            foreach ($packages as $p) {
                if ((float)($p['price_yearly'] ?? 0) > 0) {
                    $package = $p;
                    break;
                }
            }
            if (!$package) {
                $package = $packages[0];
            }
            $packageId = $package['package_id'] ?? null;
            if (!$packageId) {
                $this->json(['success' => false, 'error' => 'Paket bilgisi eksik.'], 400);
                return;
            }

            $result = $subscriptionService->createSubscription((string)$id, (string)$packageId, 'yearly');
            if (!empty($result['success']) && !empty($result['subscription_id'])) {
                $subscriptionService->activateSubscription($result['subscription_id']);
                $this->json([
                    'success'         => true,
                    'message'         => 'Abonelik aktifleştirildi.',
                    'subscription_id' => $result['subscription_id'],
                    'package'         => $package['name'] ?? null,
                ]);
                return;
            }
            // Service-level failure → 400 with explicit message.
            $this->json([
                'success' => false,
                'error'   => $result['error'] ?? 'Abonelik oluşturulamadı.',
            ], 400);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('activateSubscription: failed', [
                'business_id' => $id,
                'error'       => $e->getMessage(),
            ]);
            $this->json(['success' => false, 'error' => 'Abonelik aktifleştirilemedi.'], 500);
        }
    }

    /**
     * Upload business logo (super admin only)
     */
    public function uploadLogo($id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            $this->toastNotificationService->sendApiResponse('error', 'Yetkisiz erişim', [], 403);
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['logo'])) {
            $this->toastNotificationService->sendApiResponse('error', 'Geçersiz istek veya logo dosyası eksik', [], 400);
            return;
        }
        $file = $_FILES['logo'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        if (!in_array($file['type'], $allowedTypes) || $file['size'] > $maxSize) {
            $this->toastNotificationService->sendApiResponse('error', 'Geçersiz dosya türü veya boyutu (max 5MB)', [], 400);
            return;
        }
        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getCustomerById($id);
            if (!$customer) {
                $this->toastNotificationService->sendApiResponse('error', 'İşletme bulunamadı', [], 404);
                return;
            }
            if (!function_exists('generateSlug')) {
                require_once __DIR__ . '/../../helpers/functions.php';
            }
            $companyName = trim($customer['company_name'] ?? $customer['business_name'] ?? '');
            $slug = $companyName !== '' ? generateSlug($companyName) : '';
            if ($slug === '') {
                $slug = 'isletme-' . preg_replace('/[^a-z0-9]/i', '-', $id);
                $slug = trim(preg_replace('/-+/', '-', $slug), '-');
            }
            $uploadDir = __DIR__ . '/../../../public/uploads/businesses/' . $id . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'png';
            $filename = $slug . '-logo.' . $ext;
            $filepath = $uploadDir . $filename;
            // Eski logo dosyasını sil (farklı dosya adıyla kayıtlıysa)
            $oldLogoPath = trim($customer['logo_path'] ?? '');
            if ($oldLogoPath !== '') {
                $oldFullPath = __DIR__ . '/../../../public' . (strpos($oldLogoPath, '/') === 0 ? $oldLogoPath : '/' . $oldLogoPath);
                if (file_exists($oldFullPath) && is_file($oldFullPath) && realpath($oldFullPath) !== realpath($filepath)) {
                    @unlink($oldFullPath);
                }
            }
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                @chmod($filepath, 0644);
                $logoPath = '/uploads/businesses/' . $id . '/' . $filename;
                $customerRepo = new \App\Repositories\CustomerRepository(\App\Core\DependencyFactory::getDatabase());
                $customerRepo->update($id, ['logo_path' => $logoPath]);
                $logoUrl = BASE_URL . $logoPath . '?t=' . time();
                $this->toastNotificationService->sendApiResponse('success', 'Logo başarıyla güncellendi', [], 200, ['url' => $logoUrl]);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'Logo yüklenirken hata oluştu', [], 500);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business logo upload error', ['error' => $e->getMessage(), 'customer_id' => $id]);
            }
            $this->toastNotificationService->sendApiResponse('error', 'Logo yüklenirken hata oluştu', [], 500);
        }
    }
    
    /**
     * WordPress-multisite tarzı "Login As" işleyişi.
     *
     * Qordy mimarisinde her müşteri kendi subdomain'inde yaşıyor (örn.
     * pofudukcafe.qordy.com). Oturum çerezleri güvenlik için per-host
     * izole tutuluyor — yani qordy.com üzerindeki süper admin session'ı
     * pofudukcafe.qordy.com'a otomatik taşınmıyor.
     *
     * Bu nedenle süper admin "Giriş Yap" tıkladığında:
     *   1. `admin_impersonation_tokens` tablosuna tek kullanımlık token
     *      düşüyoruz (2 dk geçerli).
     *   2. Kullanıcıyı müşterinin asıl subdomain'ine yönlendiriyoruz:
     *        https://<sub>.qordy.com/admin-handoff?token=...
     *   3. Subdomain tarafı `adminHandoff()` ile token'ı tüketip oturumu
     *      kuruyor ve layout'a "Qodmin'e dön" bilgisini basıyor.
     *
     * qordy.com üzerindeki super admin oturumu HİÇ değiştirilmiyor, o
     * yüzden müşteri panelinden geri dönerken sadece tarayıcıyı
     * qordy.com/qodmin/dashboard'a yönlendirmek yeterli.
     */
    public function loginAs($id) {
        $this->requireLogin();

        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        \App\Core\SessionManager::ensureSession();

        if (empty($id)) {
            $this->toastNotificationService->setFlash('error', 'Geçersiz işletme kimliği.');
            header('Location: ' . BASE_URL . '/qodmin/businesses');
            exit;
        }

        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getCustomerById($id);
        } catch (\Throwable $e) {
            $customer = null;
        }

        if (empty($customer)) {
            $this->toastNotificationService->setFlash('error', 'İşletme bulunamadı (ID: ' . htmlspecialchars($id) . ').');
            header('Location: ' . BASE_URL . '/qodmin/businesses');
            exit;
        }

        // Müşterinin user_id'sini bul (yoksa customer_id'yi kullan).
        $userId = $customer['customer_id'];
        $roleId = null;
        try {
            $userService = \App\Core\DependencyFactory::getUserService();
            $user = $userService->findByEmail($customer['email'] ?? '');
            if ($user && !empty($user['user_id'])) {
                $userId = $user['user_id'];
                $roleId = isset($user['role_id']) ? (int)$user['role_id'] : null;
            }
        } catch (\Throwable $e) {
            // user yoksa customer_id ile devam ederiz
        }

        if (!$roleId) {
            try {
                $roleService = \App\Core\DependencyFactory::getRoleService();
                foreach (['BUSINESS_OWNER', 'BUSINESS_MANAGER', 'MANAGER'] as $code) {
                    $role = $roleService->getByRoleCode($code);
                    if ($role && !empty($role['role_id'])) {
                        $roleId = (int)$role['role_id'];
                        break;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Tek kullanımlık token üret.
        require_once __DIR__ . '/../../services/ImpersonationService.php';
        $imp = new \App\Services\ImpersonationService();

        $superAdminUserId = $_SESSION['user_id'] ?? 'unknown';
        $superAdminEmail  = $_SESSION['username'] ?? $_SESSION['email'] ?? null;
        $returnUrl        = rtrim(BASE_URL, '/') . '/qodmin/dashboard';

        $token = $imp->mintToken(
            $customer['customer_id'],
            $userId,
            $roleId,
            (string)$superAdminUserId,
            $superAdminEmail,
            $returnUrl
        );

        if (!$token) {
            $this->toastNotificationService->setFlash('error', 'Müşteri hesabına geçiş token\'ı oluşturulamadı.');
            header('Location: ' . BASE_URL . '/qodmin/businesses');
            exit;
        }

        try {
            \App\Core\DependencyFactory::getActivityLogService()->log(
                'impersonation_start',
                $customer['customer_id'],
                $superAdminUserId,
                'customer',
                $id,
                [
                    'admin_impersonation' => true,
                    'target_email'        => $customer['email'] ?? '',
                    'target_user_id'      => $userId,
                    'mode'                => 'cross_subdomain_token',
                ]
            );
        } catch (\Throwable $e) {}

        // Hedef subdomain belirle.
        $subdomain = trim($customer['subdomain'] ?? '');
        $baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
        if ($subdomain !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
            $target = $scheme . '://' . $subdomain . '.' . $baseDomain . '/admin-handoff?token=' . urlencode($token);
        } else {
            // Subdomain yoksa (örn. eski kayıt) mevcut host üzerinde handoff.
            $target = rtrim(BASE_URL, '/') . '/admin-handoff?token=' . urlencode($token);
        }

        if (!empty($_SESSION) && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header('Location: ' . $target);
        exit;
    }

    /**
     * Subdomain tarafında token'ı tüketip müşteri oturumu kur.
     *
     * URL: https://<sub>.qordy.com/admin-handoff?token=...
     *
     * Başarılı olursa `/business/dashboard`'a yönlendirir.
     * admin_layout.php üst bilgi şeridini gösterebilsin diye oturuma
     * `superadmin_backup` + `logged_in_as` + `admin_return_url`
     * flag'lerini koyarız.
     */
    public function adminHandoff() {
        \App\Core\SessionManager::ensureSession();

        $token = $_GET['token'] ?? '';
        if (!is_string($token) || $token === '') {
            http_response_code(400);
            echo 'Geçersiz handoff isteği.';
            exit;
        }

        require_once __DIR__ . '/../../services/ImpersonationService.php';
        $imp  = new \App\Services\ImpersonationService();
        $data = $imp->consumeToken($token);
        if (!$data) {
            http_response_code(403);
            echo 'Handoff token geçersiz veya süresi dolmuş. Qodmin\'den tekrar deneyin.';
            exit;
        }

        $targetCustomerId = $data['target_customer_id'] ?? '';
        $targetUserId     = $data['target_user_id'] ?? null;

        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getCustomerById($targetCustomerId);
        } catch (\Throwable $e) {
            $customer = null;
        }

        if (empty($customer)) {
            http_response_code(404);
            echo 'Hedef müşteri bulunamadı.';
            exit;
        }

        // Yeni temiz bir session başlat (güvenlik için ID regenerate).
        $_SESSION = [];
        session_regenerate_id(true);

        // Resolve the real role code from the DB. The handoff previously
        // hardcoded 'BUSINESS_OWNER' which isn't a value in the `users.role`
        // enum — the logger reported -INVALID_ROLE, the admin_layout
        // `isBusinessManager` check failed (so the business logo and
        // branding header were skipped on the tenant subdomain), and any
        // downstream permission check that compares role codes broke.
        $impersonatedRole = 'BUSINESS_MANAGER';
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            if ($targetUserId) {
                $roleStmt = $db->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
                $roleStmt->execute([$targetUserId]);
                $roleRow = $roleStmt->fetch(\PDO::FETCH_ASSOC);
                if ($roleRow && !empty($roleRow['role'])) {
                    $impersonatedRole = (string)$roleRow['role'];
                }
            } else {
                $roleStmt = $db->prepare("SELECT role FROM users WHERE tenant_id = ? ORDER BY created_at ASC LIMIT 1");
                $roleStmt->execute([$customer['customer_id']]);
                $roleRow = $roleStmt->fetch(\PDO::FETCH_ASSOC);
                if ($roleRow && !empty($roleRow['role'])) {
                    $impersonatedRole = (string)$roleRow['role'];
                }
            }
        } catch (\Throwable $e) {
            // Fall back to BUSINESS_MANAGER.
        }

        \App\Core\SessionManager::set('user_id',        $targetUserId ?: $customer['customer_id']);
        \App\Core\SessionManager::set('customer_id',    $customer['customer_id']);
        \App\Core\SessionManager::set('business_id',    $customer['customer_id']);
        \App\Core\SessionManager::set('role',           $impersonatedRole);
        \App\Core\SessionManager::set('role_id',        $data['target_role_id'] ?? null);
        \App\Core\SessionManager::set('username',       $customer['email'] ?? '');
        \App\Core\SessionManager::set('email',          $customer['email'] ?? '');
        \App\Core\SessionManager::set('first_name',     $customer['first_name'] ?? '');
        \App\Core\SessionManager::set('last_name',      $customer['last_name'] ?? '');
        \App\Core\SessionManager::set('is_super_admin', false);
        \App\Core\SessionManager::set('logged_in',      true);
        \App\Core\SessionManager::set('login_time',     time());

        // Layout'taki üst bilgi şeridi için flag'ler.
        \App\Core\SessionManager::set('logged_in_as',      $customer['email'] ?? $targetCustomerId);
        \App\Core\SessionManager::set('superadmin_backup', [
            'admin_impersonation' => true,
            'admin_user_id'       => $data['created_by_user_id'] ?? null,
            'admin_email'         => $data['created_by_email']   ?? null,
        ]);
        \App\Core\SessionManager::set(
            'admin_return_url',
            $data['return_url'] ?? ('https://' . ($_ENV['BASE_DOMAIN'] ?? 'qordy.com') . '/qodmin/dashboard')
        );
        \App\Core\SessionManager::set('impersonation', true);

        // Effective tenant tenant db seçimi.
        try {
            \App\Core\SessionManager::setTenantSession($customer['customer_id']);
        } catch (\Throwable $e) {}

        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Impersonation handoff accepted', [
                'target_customer_id' => $customer['customer_id'],
                'admin_user_id'      => $data['created_by_user_id'] ?? null,
                'host'               => $_SERVER['HTTP_HOST'] ?? null,
            ]);
        }

        session_write_close();

        header('Location: ' . BASE_URL . '/business/dashboard');
        exit;
    }

    /**
     * "Qodmin'e dön" butonu.
     *
     * Subdomain tarafında tıklanırsa oturumu temizleyip süper admin'in
     * qordy.com'daki Qodmin oturumuna geri yönlendirir (o oturum hiç
     * değişmediği için orada hâlâ giriş yapmış durumda olur).
     *
     * Aynı endpoint qordy.com'da tıklanırsa eski akış (in-session backup)
     * ile geri dönme davranışını korur.
     */
    public function restoreSession() {
        \App\Core\SessionManager::ensureSession();

        $host        = $_SERVER['HTTP_HOST'] ?? '';
        $baseDomain  = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
        $returnUrl   = $_SESSION['admin_return_url'] ?? ('https://' . $baseDomain . '/qodmin/dashboard');
        $impersonating = !empty($_SESSION['logged_in_as']) || !empty($_SESSION['impersonation']);

        // Subdomain üzerindeyiz — müşteri session'ını temizle ve parent
        // domain'deki qodmin paneline yönlendir.
        if (strcasecmp($host, $baseDomain) !== 0 && str_ends_with(strtolower($host), '.' . strtolower($baseDomain))) {
            if ($impersonating) {
                try {
                    \App\Core\DependencyFactory::getActivityLogService()->log(
                        'impersonation_end',
                        $_SESSION['customer_id'] ?? '',
                        $_SESSION['superadmin_backup']['admin_user_id'] ?? ($_SESSION['user_id'] ?? ''),
                        'customer',
                        $_SESSION['customer_id'] ?? '',
                        ['admin_return' => true, 'host' => $host]
                    );
                } catch (\Throwable $e) {}
            }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            @session_destroy();
            header('Location: ' . $returnUrl);
            exit;
        }

        // qordy.com tarafında - eski in-session backup akışı (geriye dönük
        // uyumluluk için tutuluyor).
        if (!empty($_SESSION['superadmin_backup']) && is_array($_SESSION['superadmin_backup'])
            && !empty($_SESSION['superadmin_backup']['user_id'])) {
            $backup = $_SESSION['superadmin_backup'];
            session_regenerate_id(true);
            $_SESSION = $backup;
        }
        header('Location: ' . BASE_URL . '/qodmin/dashboard');
        exit;
    }
    
    /**
     * Show create business form (GET)
     */
    public function create() {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        // Get active packages for dropdown
        $packages = [];
        try {
            $packageService = \App\Core\DependencyFactory::getPackageService();
            $packages = $packageService->getActivePackages();
            
            // Ensure packages is always an array
            if (!is_array($packages)) {
                $packages = [];
            }
            
            // Log if no packages found
            if (empty($packages) && class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('BusinessesController::create - No active packages found');
            }
        } catch (\Exception $e) {
            // Log error but don't break the page
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BusinessesController::create - Error loading packages', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            $packages = [];
        }
        
        $this->view('superadmin/business_create', [
            'page' => 'superadmin-business-create',
            'packages' => $packages
        ]);
    }
    
    /**
     * Store new business (POST)
     */
    public function store() {
        // Check if this is an AJAX/API request
        // Enhanced detection: check multiple indicators
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        $isAjax = (
            $this->isApiRequest() ||
            strtolower($requestedWith) === 'xmlhttprequest' ||
            strpos($uri, '/qodmin/businesses') !== false ||
            strpos(strtolower($acceptHeader), 'application/json') !== false ||
            ($method === 'POST' && (
                strpos(strtolower($contentType), 'multipart/form-data') !== false ||
                strpos(strtolower($contentType), 'application/json') !== false
            ))
        );

        // Ensure JSON response for AJAX requests
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
        }

        // Login control - don't redirect for AJAX requests
        if (!$this->requireLogin(!$isAjax)) {
            if ($isAjax) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Oturum açmanız gerekiyor'
                ], 401);
            }
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        // Super admin control
        if (!$this->isSuperAdmin()) {
            if ($isAjax) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        try {
            // Handle file uploads separately from POST data
            $input = $_POST;

            // Initialize services early (needed for validation)
            $subdomainService = new \App\Services\SubdomainService();

            // Validate required fields
            $errors = [];
            
            if (empty($input['company_name'])) {
                $errors[] = 'İşletme adı zorunludur';
            } else {
                $companyName = trim($input['company_name']);
                if (strlen($companyName) < 2) {
                    $errors[] = 'İşletme adı en az 2 karakter olmalıdır';
                }
                if (strlen($companyName) > 255) {
                    $errors[] = 'İşletme adı en fazla 255 karakter olabilir';
                }
            }
            
            if (empty($input['owner_type'])) {
                $errors[] = 'İşletme sahibi türü seçilmelidir';
            } else {
                $ownerType = $input['owner_type'];
                
                if ($ownerType === 'existing') {
                    if (empty($input['owner_user_id'])) {
                        $errors[] = 'Bir işletme sahibi seçilmelidir';
                    }
                } elseif ($ownerType === 'new') {
                    // Validate email
                    if (empty($input['new_owner_email'])) {
                        $errors[] = 'E-posta adresi zorunludur';
                    } else {
                        $email = trim($input['new_owner_email']);
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = 'Geçerli bir e-posta adresi girilmelidir';
                        }
                        if (strlen($email) > 255) {
                            $errors[] = 'E-posta adresi en fazla 255 karakter olabilir';
                        }
                    }
                    
                    // Validate first name
                    if (empty($input['new_owner_first_name'])) {
                        $errors[] = 'Ad zorunludur';
                    } else {
                        $firstName = trim($input['new_owner_first_name']);
                        if (strlen($firstName) < 2) {
                            $errors[] = 'Ad en az 2 karakter olmalıdır';
                        }
                        if (strlen($firstName) > 100) {
                            $errors[] = 'Ad en fazla 100 karakter olabilir';
                        }
                    }
                    
                    // Validate last name
                    if (empty($input['new_owner_last_name'])) {
                        $errors[] = 'Soyad zorunludur';
                    } else {
                        $lastName = trim($input['new_owner_last_name']);
                        if (strlen($lastName) < 2) {
                            $errors[] = 'Soyad en az 2 karakter olmalıdır';
                        }
                        if (strlen($lastName) > 100) {
                            $errors[] = 'Soyad en fazla 100 karakter olabilir';
                        }
                    }
                    
                    // Validate phone if provided
                    if (!empty($input['new_owner_phone'])) {
                        $phone = trim($input['new_owner_phone']);
                        if (strlen($phone) > 20) {
                            $errors[] = 'Telefon numarası en fazla 20 karakter olabilir';
                        }
                    }
                } else {
                    $errors[] = 'Geçersiz işletme sahibi türü';
                }
            }
            
            // Validate subdomain if provided
            if (!empty($input['subdomain'])) {
                $subdomain = trim($input['subdomain']);
                $subdomainValidation = $subdomainService->validateSubdomain($subdomain);
                if (!$subdomainValidation['valid']) {
                    $errors[] = $subdomainValidation['error'];
                }
            }
            
            // Return validation errors if any
            if (!empty($errors)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Form doğrulama hataları',
                    'errors' => $errors
                ], 400);
            }
            
            // Get owner type after validation
            $ownerType = $input['owner_type'];

            // Get services (subdomainService already initialized above)
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $userService = \App\Core\DependencyFactory::getUserService();
            $packageService = \App\Core\DependencyFactory::getPackageService();
            $emailService = \App\Core\DependencyFactory::getEmailService();

            // If package is selected, validate it exists
            $selectedPackage = null;
            if (!empty($input['package_id'])) {
                $selectedPackage = $packageService->getPackageById($input['package_id']);
                if (!$selectedPackage) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Seçilen paket bulunamadı'
                    ], 400);
                }
            }

            // Handle logo upload if provided (will be moved to business-specific folder after creation)
            $tempLogoPath = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logoFile = $_FILES['logo'];

                // Validate upload error
                if ($logoFile['error'] !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'Dosya boyutu çok büyük (php.ini limiti)',
                        UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu çok büyük (form limiti)',
                        UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi',
                        UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi',
                        UPLOAD_ERR_NO_TMP_DIR => 'Geçici dizin bulunamadı',
                        UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
                        UPLOAD_ERR_EXTENSION => 'Dosya yükleme uzantı tarafından durduruldu'
                    ];
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $errorMessages[$logoFile['error']] ?? 'Dosya yükleme hatası'
                    ], 400);
                }

                // Validate file type (check both MIME type and extension)
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $extension = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
                
                // Validate MIME type
                if (!in_array($logoFile['type'], $allowedTypes)) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Geçersiz dosya türü. Sadece resim dosyaları (JPG, PNG, GIF, WEBP) kabul edilir.'
                    ], 400);
                }
                
                // Validate extension
                if (!in_array($extension, $allowedExtensions)) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Geçersiz dosya uzantısı. Sadece JPG, PNG, GIF, WEBP kabul edilir.'
                    ], 400);
                }

                // Validate file size (max 2MB)
                $maxSize = 2 * 1024 * 1024; // 2MB
                if ($logoFile['size'] > $maxSize) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Dosya boyutu çok büyük. Maksimum 2MB olabilir.'
                    ], 400);
                }
                
                // Validate minimum file size (prevent empty/corrupted files)
                if ($logoFile['size'] < 100) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Dosya çok küçük veya bozuk görünüyor.'
                    ], 400);
                }

                // Create temporary upload directory if not exists
                $tempUploadDir = __DIR__ . '/../../../public/uploads/temp/';
                // Normalize path (handle both / and \ separators)
                $tempUploadDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $tempUploadDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                // Ensure directory exists with proper error handling
                if (!is_dir($tempUploadDir)) {
                    try {
                        // Create directory with recursive flag
                        if (!mkdir($tempUploadDir, 0777, true) && !is_dir($tempUploadDir)) {
                            $error = error_get_last();
                            $errorMessage = $error ? $error['message'] : 'Unknown error';
                            $realPath = realpath(dirname($tempUploadDir)) ?: dirname($tempUploadDir);
                            
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('Temp logo upload directory creation failed', [
                                    'directory' => $tempUploadDir,
                                    'real_path' => $realPath,
                                    'parent_writable' => is_writable(dirname($tempUploadDir)),
                                    'error' => $errorMessage,
                                    'php_user' => function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : 'unknown'
                                ]);
                            }
                            
                            return $this->jsonResponse([
                                'success' => false,
                                'message' => 'Logo yükleme dizini oluşturulamadı. Lütfen sistem yöneticisine başvurun. (Dizin: ' . basename($tempUploadDir) . ')'
                            ], 500);
                        }
                        
                        // Explicitly set directory permissions after creation
                        @chmod($tempUploadDir, 0777);
                        
                        // Also ensure parent directory is writable
                        $parentDir = dirname($tempUploadDir);
                        if (is_dir($parentDir) && !is_writable($parentDir)) {
                            @chmod($parentDir, 0775);
                        }
                        
                    } catch (\Exception $e) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Temp logo upload directory creation exception', [
                                'directory' => $tempUploadDir,
                                'real_path' => realpath($tempUploadDir) ?: 'Could not resolve real path',
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                        
                        return $this->jsonResponse([
                            'success' => false,
                            'message' => 'Logo yükleme dizini oluşturulamadı: ' . $e->getMessage() . '. Lütfen sistem yöneticisine başvurun.'
                        ], 500);
                    }
                } else {
                    // Directory exists, but ensure it's writable
                    if (!is_writable($tempUploadDir)) {
                        // Try to fix permissions
                        @chmod($tempUploadDir, 0777);
                    }
                }

                // Verify directory is writable after ensuring it exists
                if (!is_writable($tempUploadDir)) {
                    $perms = is_dir($tempUploadDir) ? substr(sprintf('%o', fileperms($tempUploadDir)), -4) : 'N/A';
                    $realPath = realpath($tempUploadDir) ?: 'Could not resolve real path';
                    $parentDir = dirname($tempUploadDir);
                    $parentWritable = is_dir($parentDir) ? is_writable($parentDir) : false;

                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Temp logo upload directory is not writable', [
                            'directory' => $tempUploadDir,
                            'real_path' => $realPath,
                            'permissions' => $perms,
                            'is_writable' => is_writable($tempUploadDir),
                            'parent_directory' => $parentDir,
                            'parent_writable' => $parentWritable,
                            'php_user' => function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : 'unknown',
                            'directory_owner' => is_dir($tempUploadDir) ? (fileowner($tempUploadDir) . ':' . filegroup($tempUploadDir)) : 'N/A'
                        ]);
                    }

                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Logo yükleme dizinine yazma izni yok. Dizin mevcut ancak yazılabilir değil. Lütfen sistem yöneticisine başvurun. (İzinler: ' . $perms . ')'
                    ], 500);
                }

                // Generate unique filename
                $extension = pathinfo($logoFile['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . uniqid() . '.' . $extension;
                $tempDestination = $tempUploadDir . $filename;

                // Move uploaded file to temp directory
                if (!move_uploaded_file($logoFile['tmp_name'], $tempDestination)) {
                    $error = error_get_last();
                    $errorMessage = $error ? $error['message'] : 'Unknown error';
                    $perms = file_exists($tempUploadDir) ? substr(sprintf('%o', fileperms($tempUploadDir)), -4) : 'N/A';
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Temp logo upload failed', [
                            'destination' => $tempDestination,
                            'directory' => $tempUploadDir,
                            'directory_permissions' => $perms,
                            'is_writable' => is_writable($tempUploadDir),
                            'error' => $errorMessage,
                            'file_size' => $logoFile['size'],
                            'tmp_name' => $logoFile['tmp_name']
                        ]);
                    }
                    
                    // Check if error is permission-related
                    $isPermissionError = strpos(strtolower($errorMessage), 'permission') !== false || 
                                        strpos(strtolower($errorMessage), 'denied') !== false;
                    
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $isPermissionError 
                            ? 'Logo yüklenemedi: Dizin yazma izni yok. Lütfen sistem yöneticisine başvurun.'
                            : 'Logo yüklenemedi. Lütfen tekrar deneyin.'
                    ], 500);
                }

                // Set proper file permissions
                @chmod($tempDestination, 0644);
                $tempLogoPath = $tempDestination;
            }

            // Generate subdomain from company name
            $subdomain = $input['subdomain'] ?? null;
            if (empty($subdomain)) {
                $subdomain = $subdomainService->generateSubdomain($input['company_name']);
            }

            // Validate subdomain
            $validation = $subdomainService->validateSubdomain($subdomain);
            if (!$validation['valid']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $validation['error']
                ], 400);
            }

            // Check if subdomain is available (exclude current customer if updating)
            $excludeCustomerId = null; // For new businesses, no exclusion needed
            if (!$subdomainService->isAvailable($subdomain, $excludeCustomerId)) {
                // Try to get unique subdomain, but first check if it exists in Plesk for same customer
                $subdomain = $subdomainService->getUniqueSubdomain($subdomain, $excludeCustomerId);
            }

            // Determine owner user ID
            $ownerUserId = null;
            $ownerEmail = '';
            $ownerFirstName = '';
            $ownerLastName = '';
            $ownerPhone = '';
            $temporaryPassword = null; // FIXED: Initialize to avoid undefined variable warning

            if ($ownerType === 'existing') {
                // Validate selected user exists
                $selectedUser = $userService->findByUserId($input['owner_user_id']);
                if (!$selectedUser) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Seçilen kullanıcı bulunamadı'
                    ], 400);
                }

                $ownerUserId = $input['owner_user_id'];
                // Get email from email field or name field (name field stores email for login)
                $ownerEmail = $selectedUser['email'] ?? $selectedUser['name'] ?? '';
                $ownerFirstName = $selectedUser['first_name'] ?? '';
                $ownerLastName = $selectedUser['last_name'] ?? '';
                $ownerPhone = $selectedUser['phone'] ?? '';
                
                // Validate email is not empty
                if (empty($ownerEmail)) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Seçilen kullanıcının e-posta adresi bulunamadı'
                    ], 400);
                }
            } elseif ($ownerType === 'new') {
                // Generate a temporary password for the new user
                $temporaryPassword = $this->generateMediumDifficultyPassword();

                // Get BUSINESS_MANAGER role_id
                $roleService = \App\Core\DependencyFactory::getRoleService();
                $roleData = $roleService->getByRoleCode('BUSINESS_MANAGER');
                if (!$roleData) {
                    // Fallback to MANAGER
                    $roleData = $roleService->getByRoleCode('MANAGER');
                }
                
                $roleId = $roleData['role_id'] ?? null;
                if (!$roleId) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Sistem rolü bulunamadı. Lütfen sistem yöneticisine başvurun.'
                    ], 500);
                }
                
                // Create new user
                // Note: business_id will be set later by CustomerService.register()
                $newUser = [
                    'user_id' => 'USR_' . uniqid(),
                    'name' => $input['new_owner_email'], // Store email as name for login
                    'pin' => password_hash($temporaryPassword, PASSWORD_DEFAULT), // Store hashed password
                    'password' => password_hash($temporaryPassword, PASSWORD_DEFAULT), // Also set password field
                    'role' => 'BUSINESS_MANAGER', // FIXED: Use BUSINESS_MANAGER instead of BUSINESS_OWNER
                    'role_id' => $roleId, // Set role_id from role table
                    'first_name' => $input['new_owner_first_name'],
                    'last_name' => $input['new_owner_last_name'],
                    'email' => $input['new_owner_email'],
                    'phone' => $input['new_owner_phone'] ?? null,
                    'requires_password_change' => true, // Force password change on first login
                    'status' => 'active',
                    // Note: business_id NOT set here because customer doesn't exist yet
                    // It will be set by CustomerService.register() after customer is created
                ];

                $userCreated = $userService->create($newUser);
                if (!$userCreated) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to create user for business owner', [
                            'user_data' => [
                                'email' => $input['new_owner_email'],
                                'first_name' => $input['new_owner_first_name'],
                                'last_name' => $input['new_owner_last_name']
                            ]
                        ]);
                    }
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'İşletme sahibi oluşturulamadı. Lütfen tekrar deneyin.'
                    ], 500);
                }

                $ownerUserId = $newUser['user_id'];
                $ownerEmail = trim($input['new_owner_email']);
                $ownerFirstName = trim($input['new_owner_first_name']);
                $ownerLastName = trim($input['new_owner_last_name']);
                $ownerPhone = trim($input['new_owner_phone'] ?? '');
                
                // Verify user was created successfully
                $createdUser = $userService->findByUserId($ownerUserId);
                if (!$createdUser) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('User created but not found after creation', [
                            'user_id' => $ownerUserId,
                            'email' => $ownerEmail
                        ]);
                    }
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Kullanıcı oluşturuldu ancak doğrulanamadı. Lütfen tekrar deneyin.'
                    ], 500);
                }
                
                // Note: Email will be sent after all operations complete successfully
            }

            // CRITICAL: Start database transaction for atomic operations
            // If subdomain creation fails, all operations will be rolled back
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Extend execution time for long-running operations (subdomain creation, database setup, etc.)
            set_time_limit(300); // 5 minutes
            ini_set('max_execution_time', '300');
            
            $db->beginTransaction();
            
            try {
                // Prepare customer data (logo_path will be set after business creation)
                $customerData = [
                    'email' => $ownerEmail,
                    'password' => password_hash($this->generateMediumDifficultyPassword(), PASSWORD_DEFAULT), // Generate password for customer account
                    'company_name' => trim($input['company_name']),
                    'first_name' => $ownerFirstName,
                    'last_name' => $ownerLastName,
                    'phone' => $ownerPhone,
                    'subdomain' => $subdomain,
                    'logo_path' => null, // Will be set after business creation
                    'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1
                ];

                // Register customer (pass owner_user_id to avoid duplicate user creation)
                $result = $customerService->register($customerData, $ownerUserId);

                if (!$result['success']) {
                    // Customer registration failed - rollback and return error
                    $db->rollBack();
                    
                    // Clean up temp logo file if exists
                    if (isset($tempLogoPath) && $tempLogoPath && file_exists($tempLogoPath)) {
                        @unlink($tempLogoPath);
                    }
                    
                    $errorMessage = $result['error'] ?? 'İşletme oluşturulamadı';
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Business creation failed in CustomerService', [
                            'error' => $errorMessage,
                            'customer_data' => [
                                'email' => $customerData['email'] ?? 'unknown',
                                'company_name' => $customerData['company_name'] ?? 'unknown'
                            ]
                        ]);
                    }
                    
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $errorMessage
                    ], 500);
                }

                $customerId = $result['customer_id'];
                $warnings = []; // Track any non-critical issues

                // CRITICAL: Create business record in businesses table for foreign key constraints
                // This must be done before subdomain creation (subdomains table has FK to businesses)
                try {
                    $checkBusiness = $db->prepare("SELECT tenant_id FROM businesses WHERE tenant_id = :customer_id LIMIT 1");
                    $checkBusiness->execute(['customer_id' => $customerId]);
                    $businessExists = $checkBusiness->fetch();
                    
                    if (!$businessExists) {
                        // Insert into businesses table
                        $insertBusiness = $db->prepare("
                            INSERT INTO businesses (
                                tenant_id, 
                                business_name, 
                                business_type, 
                                subdomain, 
                                owner_user_id,
                                contact_email,
                                created_at, 
                                updated_at
                            ) VALUES (
                                :tenant_id, 
                                :business_name, 
                                :business_type, 
                                :subdomain, 
                                :owner_user_id,
                                :contact_email,
                                NOW(), 
                                NOW()
                            )
                        ");
                        $insertBusiness->execute([
                            'tenant_id' => $customerId,
                            'business_name' => trim($input['company_name']),
                            'business_type' => $input['business_type'] ?? 'restaurant',
                            'subdomain' => $subdomain,
                            'owner_user_id' => $ownerUserId,
                            'contact_email' => $ownerEmail
                        ]);
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('Business record created in businesses table', [
                                'business_id' => $customerId,
                                'business_name' => trim($input['company_name'])
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail - subdomain creation will handle it
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Could not create business record in businesses table', [
                            'customer_id' => $customerId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Move logo to business-specific folder if uploaded
                if ($tempLogoPath && file_exists($tempLogoPath)) {
                    $businessUploadDir = __DIR__ . '/../../../public/uploads/businesses/' . $customerId . '/';
                    // Normalize path (handle both / and \ separators)
                    $businessUploadDir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $businessUploadDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                    
                    // Create business-specific directory
                    if (!is_dir($businessUploadDir)) {
                        try {
                            if (!mkdir($businessUploadDir, 0775, true) && !is_dir($businessUploadDir)) {
                                $error = error_get_last();
                                $errorMessage = $error ? $error['message'] : 'Unknown error';
                                
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::error('Business logo directory creation failed', [
                                        'directory' => $businessUploadDir,
                                        'customer_id' => $customerId,
                                        'real_path' => realpath(dirname($businessUploadDir)) ?: dirname($businessUploadDir),
                                        'parent_writable' => is_writable(dirname($businessUploadDir)),
                                        'error' => $errorMessage
                                    ]);
                                }
                                $warnings[] = 'Logo klasörü oluşturulamadı';
                            } else {
                                // Explicitly set directory permissions after creation
                                @chmod($businessUploadDir, 0775);
                            }
                        } catch (\Exception $e) {
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('Business logo directory creation exception', [
                                    'directory' => $businessUploadDir,
                                    'customer_id' => $customerId,
                                    'real_path' => realpath(dirname($businessUploadDir)) ?: dirname($businessUploadDir),
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                            }
                            $warnings[] = 'Logo klasörü oluşturulamadı: ' . $e->getMessage();
                        }
                    } else {
                        // Directory exists, ensure it's writable
                        if (!is_writable($businessUploadDir)) {
                            @chmod($businessUploadDir, 0775);
                        }
                    }

                    // Move logo file to business directory
                    if (is_writable($businessUploadDir) || (!is_dir($businessUploadDir) && is_writable(dirname($businessUploadDir)))) {
                        $extension = pathinfo($tempLogoPath, PATHINFO_EXTENSION);
                        $logoFilename = 'logo.' . $extension;
                        $businessLogoPath = $businessUploadDir . $logoFilename;
                        
                        $logoMoved = false;
                        if (@rename($tempLogoPath, $businessLogoPath)) {
                            $logoMoved = true;
                        } else {
                            // If rename failed, try copy and delete
                            if (@copy($tempLogoPath, $businessLogoPath)) {
                                @unlink($tempLogoPath);
                                $logoMoved = true;
                            }
                        }
                        
                        if ($logoMoved) {
                            // Set proper file permissions
                            @chmod($businessLogoPath, 0644);
                            
                            // Update customer record with logo path
                            // FIXED: Logo path should be relative to web root (public is already the web root)
                            $logoPath = '/uploads/businesses/' . $customerId . '/' . $logoFilename;
                            $customerRepository = new \App\Repositories\CustomerRepository(\App\Core\DependencyFactory::getDatabase());
                            $customerRepository->update($customerId, ['logo_path' => $logoPath]);
                            
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::info('Business logo uploaded successfully', [
                                    'customer_id' => $customerId,
                                    'logo_path' => $logoPath,
                                    'file_path' => $businessLogoPath
                                ]);
                            }
                        } else {
                            $error = error_get_last();
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('Failed to move logo to business directory', [
                                    'temp_path' => $tempLogoPath,
                                    'destination' => $businessLogoPath,
                                    'customer_id' => $customerId,
                                    'error' => $error ? $error['message'] : 'Unknown error'
                                ]);
                            }
                            $warnings[] = 'Logo işletme klasörüne taşınamadı';
                            // Clean up temp file
                            if (file_exists($tempLogoPath)) {
                                @unlink($tempLogoPath);
                            }
                        }
                    } else {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Business logo directory is not writable', [
                                'directory' => $businessUploadDir,
                                'customer_id' => $customerId
                            ]);
                        }
                        $warnings[] = 'Logo klasörüne yazma izni yok';
                        // Clean up temp file
                        if (file_exists($tempLogoPath)) {
                            @unlink($tempLogoPath);
                        }
                    }
                } elseif ($tempLogoPath && file_exists($tempLogoPath)) {
                    // Clean up temp file if business creation failed
                    @unlink($tempLogoPath);
                }

                // CRITICAL: Create subdomain configuration BEFORE other operations
                // If subdomain creation fails, rollback entire transaction
                $subdomainResult = null;
                try {
                    $subdomainResult = $subdomainService->createSubdomainConfig($subdomain, $customerId);
                    
                    // Check if subdomain creation actually succeeded
                    if (!$subdomainResult || (isset($subdomainResult['success']) && !$subdomainResult['success'])) {
                        // Subdomain creation failed - rollback transaction and return error
                        $db->rollBack();
                        
                        // Clean up temp logo file if exists
                        if (isset($tempLogoPath) && $tempLogoPath && file_exists($tempLogoPath)) {
                            @unlink($tempLogoPath);
                        }
                        
                        $errorMsg = $subdomainResult['message'] ?? 'Subdomain oluşturulamadı';
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Subdomain creation failed - transaction rolled back', [
                                'subdomain' => $subdomain,
                                'customer_id' => $customerId,
                                'result' => $subdomainResult,
                                'error' => $errorMsg
                            ]);
                        }
                        
                        return $this->jsonResponse([
                            'success' => false,
                            'message' => 'İşletme oluşturulamadı: ' . $errorMsg . ' (Tüm işlemler geri alındı)'
                        ], 500);
                    } else {
                        // Log success
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('Subdomain created successfully', [
                                'subdomain' => $subdomain,
                                'customer_id' => $customerId,
                                'url' => $subdomainResult['url'] ?? null
                            ]);
                        }
                    }
                } catch (\Exception $subdomainException) {
                    // Subdomain creation threw exception - rollback transaction and return error
                    $db->rollBack();
                    
                    // Clean up temp logo file if exists
                    if (isset($tempLogoPath) && $tempLogoPath && file_exists($tempLogoPath)) {
                        @unlink($tempLogoPath);
                    }
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Subdomain configuration exception - transaction rolled back', [
                            'error' => $subdomainException->getMessage(),
                            'subdomain' => $subdomain,
                            'customer_id' => $customerId,
                            'trace' => $subdomainException->getTraceAsString()
                        ]);
                    }
                    
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Subdomain oluşturulurken hata oluştu: ' . $subdomainException->getMessage() . ' (Tüm işlemler geri alındı)'
                    ], 500);
                }

                // If package was selected, create subscription for the business
                if ($selectedPackage) {
                    try {
                        // Use SubscriptionService::createSubscription() method which handles all the logic properly
                        // For super admin assignment, we'll create with 'monthly' pricing type and auto-activate
                        $pricingType = 'monthly'; // Default for super admin assignments
                        
                        // Check if package has monthly pricing, if not try yearly or one_time
                        if (empty($selectedPackage['price_monthly']) || $selectedPackage['price_monthly'] <= 0) {
                            if (!empty($selectedPackage['price_yearly']) && $selectedPackage['price_yearly'] > 0) {
                                $pricingType = 'yearly';
                            } elseif (!empty($selectedPackage['price_one_time']) && $selectedPackage['price_one_time'] > 0) {
                                $pricingType = 'one_time';
                            }
                        }
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('Creating subscription for business during creation', [
                                'customer_id' => $customerId,
                                'package_id' => $input['package_id'],
                                'pricing_type' => $pricingType,
                                'package_name' => $selectedPackage['name'] ?? 'Unknown'
                            ]);
                        }
                        
                        // Create subscription using proper service method
                        $subscriptionResult = $subscriptionService->createSubscription(
                            $customerId,
                            $input['package_id'],
                            $pricingType
                        );

                        if (!$subscriptionResult || !$subscriptionResult['success']) {
                            $warnings[] = 'Paket ataması tamamlanamadı: ' . ($subscriptionResult['error'] ?? 'Bilinmeyen hata');
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('Subscription creation failed for customer', [
                                    'customer_id' => $customerId,
                                    'package_id' => $input['package_id'] ?? 'none',
                                    'pricing_type' => $pricingType,
                                    'error' => $subscriptionResult['error'] ?? 'Unknown',
                                    'result' => $subscriptionResult
                                ]);
                            }
                        } else {
                            // CRITICAL: Auto-activate subscription when super admin assigns package
                            // Super admin ataması olduğu için direkt aktif et (ödeme gerektirmez)
                            $subscriptionId = $subscriptionResult['subscription_id'] ?? null;
                            if ($subscriptionId) {
                                $activationResult = $subscriptionService->activateSubscription($subscriptionId);
                                if (!$activationResult['success']) {
                                    $warnings[] = 'Paket aktifleştirilemedi: ' . ($activationResult['error'] ?? 'Bilinmeyen hata');
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::error('Failed to auto-activate subscription', [
                                            'subscription_id' => $subscriptionId,
                                            'customer_id' => $customerId,
                                            'package_id' => $input['package_id'],
                                            'error' => $activationResult['error'] ?? 'Unknown',
                                            'result' => $activationResult
                                        ]);
                                    }
                                } else {
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::info('Subscription auto-activated successfully for business', [
                                            'subscription_id' => $subscriptionId,
                                            'customer_id' => $customerId,
                                            'package_id' => $input['package_id'],
                                            'package_name' => $selectedPackage['name'] ?? 'Unknown'
                                        ]);
                                    }
                                }
                            } else {
                                $warnings[] = 'Paket ataması yapıldı ancak subscription_id alınamadı';
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::warning('Subscription created but no subscription_id returned', [
                                        'customer_id' => $customerId,
                                        'package_id' => $input['package_id'],
                                        'result' => $subscriptionResult
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $subscriptionException) {
                        $warnings[] = 'Paket ataması sırasında hata oluştu: ' . $subscriptionException->getMessage();
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Subscription creation error during business creation', [
                                'error' => $subscriptionException->getMessage(),
                                'customer_id' => $customerId,
                                'package_id' => $input['package_id'] ?? 'none',
                                'trace' => $subscriptionException->getTraceAsString()
                            ]);
                        }
                    }
                }

                // Initialize tenant database and set up business owner
                try {
                    $tenantService = new \App\Services\TenantService();

                    // Update the selected user to be associated with this business (if not already done)
                    // CustomerService::register() now handles this, but we check anyway
                    $user = $userService->findByUserId($ownerUserId);
                    if ($user) {
                        // Check if business_id needs to be set
                        $needsUpdate = false;
                        $userUpdateData = [];
                        
                        if (empty($user['tenant_id']) || $user['tenant_id'] !== $customerId) {
                            $userUpdateData['tenant_id'] = $customerId;
                            $needsUpdate = true;
                        }
                        
                        // Also ensure email matches if it's different
                        if (!empty($ownerEmail) && (!isset($user['email']) || $user['email'] !== $ownerEmail)) {
                            $userUpdateData['email'] = $ownerEmail;
                            $needsUpdate = true;
                        }

                        if ($needsUpdate && !empty($userUpdateData)) {
                            $userUpdated = $userService->update($ownerUserId, $userUpdateData);

                            if (!$userUpdated) {
                                $warnings[] = 'Kullanıcı ilişkilendirmesi tamamlanamadı';
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::error('Business association failed for user', [
                                        'user_id' => $ownerUserId,
                                        'business_id' => $customerId,
                                        'update_data' => $userUpdateData
                                    ]);
                                }
                            } else {
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::info('User business association updated', [
                                        'user_id' => $ownerUserId,
                                        'business_id' => $customerId
                                    ]);
                                }
                            }
                        }
                    } else {
                        $warnings[] = 'Kullanıcı bulunamadı - ilişkilendirme yapılamadı';
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('User not found for business association', [
                                'user_id' => $ownerUserId,
                                'business_id' => $customerId
                            ]);
                        }
                    }

                    // Initialize tenant with default data
                    $tenantInitialized = $tenantService->initializeTenant($customerId, $ownerUserId);

                    if (!$tenantInitialized) {
                        $warnings[] = 'İşletme ortamı başlatması tamamlanamadı';
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Tenant initialization failed', [
                                'customer_id' => $customerId,
                                'owner_user_id' => $ownerUserId
                            ]);
                        }
                    }
                } catch (\Exception $tenantException) {
                    $warnings[] = 'İşletme ortamı başlatılırken bir hata oluştu';
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Tenant service error during business creation', [
                            'customer_id' => $customerId,
                            'owner_user_id' => $ownerUserId,
                            'error' => $tenantException->getMessage(),
                            'trace' => $tenantException->getTraceAsString()
                        ]);
                    }
                }

                // Assign permissions to business owner from selected package
                if ($selectedPackage) {
                    try {
                        $packageRepository = new \App\Repositories\PackageRepository(\App\Core\DependencyFactory::getDatabase());
                        $packagePermissionKeys = $packageRepository->getPackagePermissionKeys($input['package_id']);

                        if (!empty($packagePermissionKeys)) {
                            $permissionAssigned = $this->assignPermissionsToUser($ownerUserId, $packagePermissionKeys);
                            if (!$permissionAssigned) {
                                $warnings[] = 'Paket izinleri atanamadı';
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::error('Package permission assignment failed', [
                                        'user_id' => $ownerUserId,
                                        'package_id' => $input['package_id'],
                                        'permission_count' => count($packagePermissionKeys)
                                    ]);
                                }
                            } else {
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::info('Package permissions assigned successfully', [
                                        'user_id' => $ownerUserId,
                                        'package_id' => $input['package_id'],
                                        'package_name' => $selectedPackage['name'] ?? 'Unknown',
                                        'permission_count' => count($packagePermissionKeys)
                                    ]);
                                }
                            }
                        } else {
                            $warnings[] = 'Pakete ait izin bulunamadı - varsayılan izinler kullanılacak';
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('No permissions found for package', [
                                    'package_id' => $input['package_id'],
                                    'package_name' => $selectedPackage['name'] ?? 'Unknown'
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        $warnings[] = 'İzin ataması sırasında hata oluştu';
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Error during permission assignment', [
                                'error' => $e->getMessage(),
                                'user_id' => $ownerUserId,
                                'package_id' => $input['package_id'],
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                } else {
                    // Paket seçilmedi - kullanıcıya bilgi ver
                    $warnings[] = 'Paket seçilmedi - varsayılan izinler kullanılacak';
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('No package selected for business', [
                            'customer_id' => $customerId,
                            'user_id' => $ownerUserId
                        ]);
                    }
                }

                // TÜM İŞLEMLER TAMAMLANDI - Şimdi e-posta gönder (sadece yeni kullanıcı için)
                if ($ownerType === 'new' && !empty($temporaryPassword)) {
                    try {
                        $emailSent = $this->sendWelcomeEmail(
                            $emailService,
                            $ownerEmail,
                            $ownerFirstName,
                            $input['company_name'],
                            $temporaryPassword,
                            $subdomain
                        );

                        if (!$emailSent) {
                            $warnings[] = 'Hoş geldin e-postası gönderilemedi';
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('Welcome email could not be sent to new business owner', [
                                    'email' => $ownerEmail,
                                    'business_name' => $input['company_name']
                                ]);
                            }
                        }
                    } catch (\Exception $emailException) {
                        $warnings[] = 'Hoş geldin e-postası gönderilirken hata oluştu';
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Error sending welcome email', [
                                'error' => $emailException->getMessage(),
                                'email' => $ownerEmail,
                                'business_name' => $input['company_name'],
                                'trace' => $emailException->getTraceAsString()
                            ]);
                        }
                    }
                }

                // Send business created email to owner (both new and existing users)
                try {
                    $this->sendBusinessCreatedEmail(
                        $ownerEmail,
                        $ownerFirstName,
                        $ownerLastName,
                        $input['company_name'],
                        $customerId,
                        $subdomain,
                        $selectedPackage['name'] ?? null
                    );
                } catch (\Exception $emailException) {
                    $warnings[] = 'İşletme oluşturuldu e-postası gönderilirken hata oluştu';
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Error sending business created email', [
                            'error' => $emailException->getMessage(),
                            'email' => $ownerEmail,
                            'business_name' => $input['company_name'],
                            'customer_id' => $customerId,
                            'trace' => $emailException->getTraceAsString()
                        ]);
                    }
                }

                // CRITICAL: Commit transaction only after all operations succeed
                $db->commit();
                
                $response = [
                    'success' => true,
                    'message' => 'İşletme başarıyla oluşturuldu',
                    'customer_id' => $customerId,
                    'subdomain' => $subdomain,
                    'subdomain_url' => $subdomainResult['url'] ?? null,
                    'dns_required' => $subdomainResult['dns_required'] ?? false
                ];

                // Add warnings if any
                if (!empty($warnings)) {
                    $response['warnings'] = $warnings;
                    $response['message'] .= ' (bazı işlemler tamamlanamadı)';
                }

                return $this->jsonResponse($response);
                
            } catch (\Exception $transactionException) {
                // Rollback transaction on any error
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                
                // Clean up temp logo file if exists
                if (isset($tempLogoPath) && $tempLogoPath && file_exists($tempLogoPath)) {
                    @unlink($tempLogoPath);
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Business creation transaction error - rolled back', [
                        'error' => $transactionException->getMessage(),
                        'trace' => $transactionException->getTraceAsString(),
                        'subdomain' => $subdomain ?? 'unknown',
                        'customer_id' => $customerId ?? 'unknown'
                    ]);
                }
                
                // Re-throw to outer catch block
                throw $transactionException;
            }

        } catch (\Exception $e) {
            // Ensure transaction is rolled back if still active
            try {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollBack();
                }
            } catch (\Exception $rollbackException) {
                // Ignore rollback errors
            }
            
            // Clean up temp logo file if exists
            if (isset($tempLogoPath) && $tempLogoPath && file_exists($tempLogoPath)) {
                @unlink($tempLogoPath);
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Cleaned up temp logo file after error', [
                        'temp_path' => $tempLogoPath
                    ]);
                }
            }
            
            // Ensure we return JSON for AJAX requests even in error cases
            $isAjax = $this->isApiRequest() || strpos($_SERVER['REQUEST_URI'] ?? '', '/qodmin/businesses') !== false;

            if ($isAjax) {
                // For AJAX requests, always return JSON
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Business creation error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }

                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'İşletme oluşturulurken bir hata oluştu: ' . $e->getMessage() . ' (Tüm işlemler geri alındı)'
                ], 500);
            } else {
                // For non-AJAX requests, log and redirect
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Business creation error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                }

                $this->toastNotificationService->setFlash('error', 'İşletme oluşturulurken bir hata oluştu: ' . $e->getMessage());
                header('Location: ' . BASE_URL . '/qodmin/businesses/create');
                exit;
            }
        }
    }

    /**
     * Generate a medium difficulty password
     * @return string
     */
    private function generateMediumDifficultyPassword(): string {
        $length = 12;
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $password = '';

        // Ensure at least one character from each category
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%&*';

        // random_int() for CSPRNG-backed password generation; rand() is
        // predictable once the seed leaks.
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // str_shuffle() uses rand() too — use Fisher–Yates with random_int.
        $bytes = str_split($password);
        for ($i = count($bytes) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$bytes[$i], $bytes[$j]] = [$bytes[$j], $bytes[$i]];
        }
        return implode('', $bytes);
    }

    /**
     * Assign permissions to user via their role
     * @param string $userId User ID
     * @param array $permissionKeys Array of permission keys
     * @return bool Success
     */
    private function assignPermissionsToUser(string $userId, array $permissionKeys): bool {
        try {
            $userService = \App\Core\DependencyFactory::getUserService();
            $user = $userService->findByUserId($userId);
            
            if (!$user) {
                return false;
            }
            
            // Get user's role_id
            $roleId = $user['role_id'] ?? null;
            if (!$roleId) {
                // Try to get role_id from role code
                $roleCode = $user['role'] ?? 'BUSINESS_MANAGER';
                try {
                    $roleService = \App\Core\DependencyFactory::getRoleService();
                    $roleData = $roleService->getByRoleCode($roleCode);
                    if ($roleData && isset($roleData['role_id'])) {
                        $roleId = $roleData['role_id'];
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Error getting role_id for permission assignment', [
                            'error' => $e->getMessage(),
                            'user_id' => $userId,
                            'role_code' => $roleCode
                        ]);
                    }
                    return false;
                }
            }
            
            if (!$roleId) {
                return false;
            }
            
            // Get permission model
            $permissionModel = new \App\Models\SystemPermission(\App\Core\DependencyFactory::getDatabase());
            
            // Assign each permission to the role
            $assignedCount = 0;
            foreach ($permissionKeys as $permissionKey) {
                $permission = $permissionModel->getByKey($permissionKey);
                if ($permission && isset($permission['permission_id'])) {
                    $assigned = $permissionModel->assignToRole($roleId, $permission['permission_id']);
                    if ($assigned) {
                        $assignedCount++;
                    }
                }
            }
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Permissions assigned to user', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned_count' => $assignedCount,
                    'total_permissions' => count($permissionKeys)
                ]);
            }
            
            return $assignedCount > 0;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error assigning permissions to user', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Send welcome email to new business owner
     * @param mixed $emailService
     * @param string $email
     * @param string $firstName
     * @param string $companyName
     * @param string $password
     * @param string $subdomain
     * @return bool
     */
    private function sendWelcomeEmail($emailService, string $email, string $firstName, string $companyName, string $password, string $subdomain): bool {
        try {
            $baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
            $subdomainUrl = "https://{$subdomain}.{$baseDomain}";
            
            $subject = "Hoş Geldiniz - {$companyName} İşletmesi Oluşturuldu";
            $body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Hoş Geldiniz</title>
                </head>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                        <h1 style='color: white; margin: 0; font-size: 28px;'>Hoş Geldiniz!</h1>
                    </div>
                    <div style='background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;'>
                        <h2 style='color: #1f2937; margin-top: 0;'>Merhaba {$firstName},</h2>
                        <p><strong>{$companyName}</strong> işletmeniz başarıyla oluşturuldu ve sisteminiz hazır!</p>
                        
                        <div style='background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                            <p style='margin: 5px 0;'><strong>İşletme Paneli:</strong> <a href='{$subdomainUrl}' style='color: #f97316; text-decoration: none;'>{$subdomainUrl}</a></p>
                            <p style='margin: 5px 0;'><strong>E-posta:</strong> {$email}</p>
                            <p style='margin: 5px 0;'><strong>Geçici Şifre:</strong> <code style='background: #fff; padding: 4px 8px; border-radius: 4px; font-family: monospace;'>{$password}</code></p>
                        </div>
                        
                        <p style='color: #dc2626; font-weight: bold; background: #fee2e2; padding: 15px; border-radius: 8px; border-left: 4px solid #dc2626;'>
                            ⚠️ İlk girişinizde şifrenizi değiştirmeniz zorunludur.
                        </p>
                        
                        <p style='margin-top: 20px;'>Hemen giriş yaparak sisteminizi kullanmaya başlayabilirsiniz!</p>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$subdomainUrl}/login' style='display: inline-block; background: #6366f1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Panele Giriş Yap</a>
                        </div>
                        
                        <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>
                            Herhangi bir soruda bizlere ulaşmaktan çekinmeyin.
                        </p>
                        <p style='color: #6b7280; font-size: 14px;'>
                            İyi çalışmalar dileriz!
                        </p>
                    </div>
                    <div style='text-align: center; margin-top: 20px; color: #9ca3af; font-size: 12px;'>
                        <p>Bu otomatik bir e-postadır. Lütfen yanıtlamayın.</p>
                    </div>
                </body>
                </html>
            ";

            return $emailService->sendEmail($email, $subject, $body);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error sending welcome email', [
                    'error' => $e->getMessage(),
                    'email' => $email
                ]);
            }
            return false;
        }
    }
    
    /**
     * Get business owners for dropdown
     */
    public function getBusinessOwners() {
        // Always return JSON for API endpoint
        header('Content-Type: application/json; charset=utf-8');
        
        $this->requireLogin();

        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $userService = \App\Core\DependencyFactory::getUserService();
            
            // Clear cache to get fresh data
            try {
                $cache = \App\Core\DependencyFactory::getCacheService();
                if (method_exists($cache, 'forget')) {
                    $cache->forget('users:all');
                } elseif (method_exists($cache, 'delete')) {
                    $cache->delete('users:all');
                }
            } catch (\Exception $e) {
                // Cache clear failed, continue
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Cache clear failed in getBusinessOwners', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Get users with business owner roles from users table ONLY
            // Frontend'den kayıt olanlar zaten users tablosunda BUSINESS_MANAGER olarak oluşturuluyor
            $users = $userService->getAll();
            
            if (!is_array($users)) {
                $users = [];
            }
            
            // Filter to only include business owners and business managers
            $businessOwners = array_filter($users, function($user) {
                if (!is_array($user) || empty($user['user_id'])) {
                    return false;
                }
                
                $role = strtoupper($user['role'] ?? '');
                $roleId = strtoupper($user['role_id'] ?? '');
                
                // Check for business-related roles
                $validRoles = [
                    'BUSINESS_OWNER', 'ROLE_BUSINESS_OWNER',
                    'BUSINESS_MANAGER', 'ROLE_BUSINESS_MANAGER',
                    'MANAGER', 'ROLE_MANAGER'
                ];
                
                return in_array($role, $validRoles) || in_array($roleId, $validRoles);
            });
            
            // Format the response - only from users table
            $formattedOwners = [];
            foreach ($businessOwners as $user) {
                if (!is_array($user) || empty($user['user_id'])) {
                    continue;
                }
                
                // Build display name
                $displayName = '';
                $role = strtoupper($user['role'] ?? $user['role_id'] ?? '');
                
                // For BUSINESS_MANAGER, use first_name + last_name
                if (strpos($role, 'BUSINESS_MANAGER') !== false || strpos($role, 'MANAGER') !== false) {
                    $firstName = trim($user['first_name'] ?? '');
                    $lastName = trim($user['last_name'] ?? $user['surname'] ?? '');
                    $displayName = trim($firstName . ' ' . $lastName);
                }
                
                // Fallback to name field or email
                if (empty($displayName)) {
                    $displayName = trim($user['name'] ?? $user['email'] ?? 'Bilinmeyen');
                }
                
                // Get email
                $email = trim($user['email'] ?? $user['name'] ?? '');
                
                // Only add if user_id exists and display name is not empty
                if (!empty($user['user_id']) && !empty($displayName)) {
                    $formattedOwners[] = [
                        'user_id' => $user['user_id'],
                        'name' => $displayName,
                        'role' => $user['role'] ?? $user['role_id'] ?? 'BUSINESS_MANAGER',
                        'email' => $email
                    ];
                }
            }
            
            // Sort by name for better UX
            usort($formattedOwners, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            return $this->jsonResponse([
                'success' => true,
                'data' => $formattedOwners,
                'count' => count($formattedOwners)
            ]);

        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Get business owners error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
                ]);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'İşletme sahipleri alınırken bir hata oluştu: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Delete a business PERMANENTLY
     * CRITICAL: This deletes ALL data for the business
     * Toggle business active/passive status
     * 
     * @param string $businessId Business/Customer ID
     * @return void (JSON response)
     */
    public function toggleStatus(string $businessId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Yetkisiz erişim'
            ], 403);
        }
        
        try {
            if (empty($businessId)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Geçersiz işletme ID'
                ], 400);
            }
            
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $stmt = $db->prepare("SELECT customer_id, company_name, email, first_name, last_name, is_active FROM customers WHERE customer_id = :id LIMIT 1");
            $stmt->execute(['id' => $businessId]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$customer) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'İşletme bulunamadı'
                ], 404);
            }
            
            $currentStatus = (int)($customer['is_active'] ?? 1);
            $newStatus = $currentStatus === 1 ? 0 : 1;
            $statusText = $newStatus === 1 ? 'aktif' : 'pasif';
            
            $requestData = json_decode(file_get_contents('php://input'), true) ?? [];
            $qrMenuStatus = $requestData['qr_menu_status'] ?? null;
            $validQrStatuses = ['active', 'menu_only', 'passive'];
            
            if ($newStatus === 0 && $qrMenuStatus && in_array($qrMenuStatus, $validQrStatuses)) {
                $stmt = $db->prepare("UPDATE customers SET is_active = :status, qr_menu_status = :qr_status WHERE customer_id = :id");
                $stmt->execute(['status' => $newStatus, 'qr_status' => $qrMenuStatus, 'id' => $businessId]);
            } elseif ($newStatus === 1) {
                $stmt = $db->prepare("UPDATE customers SET is_active = :status, qr_menu_status = 'active' WHERE customer_id = :id");
                $stmt->execute(['status' => $newStatus, 'id' => $businessId]);
            } else {
                $stmt = $db->prepare("UPDATE customers SET is_active = :status, qr_menu_status = 'passive' WHERE customer_id = :id");
                $stmt->execute(['status' => $newStatus, 'id' => $businessId]);
            }

            if ($newStatus === 0) {
                $st = $db->prepare("UPDATE subscriptions SET status = 'suspended', updated_at = NOW() WHERE tenant_id = :bid AND status IN ('active','pending')");
                $st->execute(['bid' => $businessId]);
            } else {
                $st = $db->prepare("UPDATE subscriptions SET status = 'active', updated_at = NOW() WHERE tenant_id = :bid AND status = 'suspended'");
                $st->execute(['bid' => $businessId]);
            }
            
            $qrStatusMessages = [
                'menu_only' => ' (QR Menü: Sadece Görüntüleme)',
                'passive' => ' (QR Menü: Tamamen Kapalı)',
                'active' => ''
            ];
            $qrMsg = $qrStatusMessages[$qrMenuStatus ?? 'active'] ?? '';
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('SuperAdmin: Business status toggled', [
                    'business_id' => $businessId,
                    'company_name' => $customer['company_name'] ?? '',
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'qr_menu_status' => $qrMenuStatus ?? 'default',
                    'admin_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
            }
            
            // Send deactivation email
            if ($newStatus === 0 && !empty($customer['email'])) {
                try {
                    $emailService = \App\Core\DependencyFactory::getEmailService();
                    $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                    
                    $emailType = new \App\Services\Email\EmailType\BusinessDeactivatedEmail($settingsService, [
                        'email' => $customer['email'],
                        'first_name' => $customer['first_name'] ?? '',
                        'last_name' => $customer['last_name'] ?? '',
                        'business_name' => $customer['company_name'] ?? 'İşletme',
                        'qr_menu_status' => $qrMenuStatus ?? 'passive',
                        'deactivated_at' => date('d.m.Y H:i'),
                    ]);
                    
                    $emailService->sendEmailType($emailType);
                } catch (\Throwable $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to send deactivation email', [
                            'business_id' => $businessId,
                            'email' => $customer['email'] ?? '',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            return $this->jsonResponse([
                'success' => true,
                'message' => ($customer['company_name'] ?? 'İşletme') . ' başarıyla ' . $statusText . ' yapıldı' . $qrMsg,
                'is_active' => $newStatus,
                'qr_menu_status' => $qrMenuStatus ?? ($newStatus === 1 ? 'active' : 'passive')
            ]);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin: Failed to toggle business status', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return $this->jsonResponse([
                'success' => false,
                'message' => 'İşletme durumu değiştirilemedi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update QR menu status independently (without toggling is_active)
     */
    public function updateQrMenuStatus(string $businessId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Yetkisiz erişim'], 403);
        }
        
        try {
            $requestData = json_decode(file_get_contents('php://input'), true) ?? [];
            $qrMenuStatus = $requestData['qr_menu_status'] ?? null;
            $validStatuses = ['active', 'menu_only', 'passive'];
            
            if (!$qrMenuStatus || !in_array($qrMenuStatus, $validStatuses)) {
                return $this->jsonResponse(['success' => false, 'message' => 'Geçersiz QR menü durumu'], 400);
            }
            
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $stmt = $db->prepare("SELECT customer_id, company_name FROM customers WHERE customer_id = :id LIMIT 1");
            $stmt->execute(['id' => $businessId]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$customer) {
                return $this->jsonResponse(['success' => false, 'message' => 'İşletme bulunamadı'], 404);
            }
            
            $stmt = $db->prepare("UPDATE customers SET qr_menu_status = :qr_status WHERE customer_id = :id");
            $stmt->execute(['qr_status' => $qrMenuStatus, 'id' => $businessId]);
            
            $statusLabels = [
                'active' => 'Aktif',
                'menu_only' => 'Sadece Menü Görüntüleme',
                'passive' => 'Tamamen Kapalı'
            ];
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('SuperAdmin: QR menu status updated', [
                    'business_id' => $businessId,
                    'company_name' => $customer['company_name'] ?? '',
                    'qr_menu_status' => $qrMenuStatus,
                    'admin_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
            }
            
            return $this->jsonResponse([
                'success' => true,
                'message' => ($customer['company_name'] ?? 'İşletme') . ' QR menü durumu: ' . ($statusLabels[$qrMenuStatus] ?? $qrMenuStatus),
                'qr_menu_status' => $qrMenuStatus
            ]);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin: Failed to update QR menu status', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
            return $this->jsonResponse(['success' => false, 'message' => 'QR menü durumu güncellenemedi: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * İşletmeye Meta WhatsApp / QR-sıra-meta özellikleri kullanım izni verir veya kaldırır.
     * Bu bayrak `customers.meta_whatsapp_enabled` sütununda tutulur.
     *
     * 0 = kapalı: İşletme WhatsApp template ayarlarını düzenleyemez, sıra
     *     bildirimlerinde WhatsApp atlanır, sadece e-posta gider.
     * 1 = açık: Tüm Meta özellikleri kullanılabilir.
     */
    public function updateMetaWhatsAppPermission(string $businessId) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Yetkisiz erişim'], 403);
        }

        try {
            $requestData = json_decode(file_get_contents('php://input'), true) ?? [];
            $enabled = !empty($requestData['enabled']) ? 1 : 0;

            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT customer_id, company_name FROM customers WHERE customer_id = :id LIMIT 1");
            $stmt->execute(['id' => $businessId]);
            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$customer) {
                return $this->jsonResponse(['success' => false, 'message' => 'İşletme bulunamadı'], 404);
            }

            $upd = $db->prepare("UPDATE customers SET meta_whatsapp_enabled = :v, updated_at = NOW() WHERE customer_id = :id");
            $upd->execute(['v' => $enabled, 'id' => $businessId]);

            // İzin kapatılırsa, kendi QR-sıra ayarlarında WhatsApp'ı da kapat;
            // işletme panelinde hayalet aktif görünmesin.
            if ($enabled === 0) {
                try {
                    $db->prepare('UPDATE queue_settings SET whatsapp_enabled = 0, whatsapp_template_name = NULL WHERE tenant_id = :id')
                        ->execute(['id' => $businessId]);
                } catch (\Throwable $e) { /* queue_settings yoksa sessiz geç */ }
            }

            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('SuperAdmin: meta_whatsapp_enabled updated', [
                    'business_id' => $businessId,
                    'enabled'     => $enabled,
                ]);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => ($customer['company_name'] ?? 'İşletme') . ' için Meta WhatsApp izni ' . ($enabled ? 'açıldı' : 'kapatıldı'),
                'meta_whatsapp_enabled' => $enabled,
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin: Failed to update meta permission', [
                    'business_id' => $businessId,
                    'error'       => $e->getMessage(),
                ]);
            }
            return $this->jsonResponse(['success' => false, 'message' => 'İzin güncellenemedi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * SECURITY: Only deletes data belonging to the specified business
     * 
     * @param string $businessId Business/Customer ID
     * @return void (JSON response)
     */
    public function deleteBusiness(string $businessId) {
        // Require login and super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Yetkisiz erişim'
            ], 403);
        }
        
        try {
            // Validate business_id is not empty
            if (empty($businessId)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Geçersiz işletme ID'
                ], 400);
            }
            
            // Get business info before deletion
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getById($businessId);
            
            if (!$customer) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'İşletme bulunamadı'
                ], 404);
            }
            
            // SECURITY: Verify this is a valid business customer (not system/admin data)
            if (strpos($businessId, 'CUST_') !== 0) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Güvenlik: Sadece işletme kayıtları silinebilir'
                ], 403);
            }
            
            // Create deletion service
            require_once __DIR__ . '/../../services/BusinessDeletionService.php';
            $deletionService = new \App\Services\BusinessDeletionService(
                \App\Core\DependencyFactory::getDatabase()
            );
            
            $subdomain = $customer['subdomain'] ?? '';
            
            // Execute deletion (service ensures only this business's data is deleted)
            $result = $deletionService->deleteBusinessCompletely($businessId, $subdomain);
            
            if ($result['success']) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'İşletme başarıyla silindi',
                    'deleted_records' => $result['deleted_records']
                ]);
            } else {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $result['message'] ?? 'Silme işlemi başarısız',
                    'error' => $result['error'] ?? ''
                ], 500);
            }
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business deletion failed', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Silme işlemi sırasında hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Send business created email to owner
     * @param string $email Owner email
     * @param string $firstName Owner first name
     * @param string $lastName Owner last name
     * @param string $businessName Business name
     * @param string $businessId Business ID
     * @param string $subdomain Subdomain
     * @param string|null $packageName Package name (optional)
     * @return bool Success status
     */
    private function sendBusinessCreatedEmail(string $email, string $firstName, string $lastName, string $businessName, string $businessId, string $subdomain, ?string $packageName = null): bool {
        try {
            // Load BusinessCreatedEmail class if not already loaded
            if (!class_exists('\App\Services\Email\EmailType\BusinessCreatedEmail')) {
                require_once __DIR__ . '/../../services/Email/EmailType/BusinessCreatedEmail.php';
            }
            
            $emailService = \App\Core\DependencyFactory::getEmailService();
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            
            $emailType = new \App\Services\Email\EmailType\BusinessCreatedEmail($settingsService, [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'business_name' => $businessName,
                'business_id' => $businessId,
                'subdomain' => $subdomain,
                'package_name' => $packageName
            ]);
            
            return $emailService->sendEmailType($emailType);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error sending business created email', [
                    'error' => $e->getMessage(),
                    'email' => $email,
                    'business_id' => $businessId
                ]);
            }
            // Don't fail business creation if email fails
            return false;
        }
    }
    
    /**
     * Get business statistics for API endpoint
     * GET /api/qodmin/businesses/{id}/stats
     */
    public function getBusinessStats($id = null) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Unauthorized'
            ], 403);
            return;
        }
        
        $businessId = $id ?? $_GET['id'] ?? null;
        if (empty($businessId)) {
            $this->apiResponse([
                'success' => false,
                'error' => 'Business ID required'
            ], 400);
            return;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Initialize stats with defaults
            $stats = [
                'daily_revenue' => 0,
                'hourly_sales_total' => 0,
                'occupied_tables_count' => 0,
                'total_tables_count' => 0,
                'active_orders_count' => 0,
                'occupancy_rate' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Get daily revenue (today's orders)
            try {
                $todayStart = date('Y-m-d 00:00:00');
                $todayEnd = date('Y-m-d 23:59:59');
                $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE tenant_id = ? AND created_at >= ? AND created_at <= ? AND status != 'CANCELLED' AND (is_paid = 1 OR status = 'SERVED')");
                $stmt->execute([$businessId, $todayStart, $todayEnd]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $stats['daily_revenue'] = (float)($result['revenue'] ?? 0);
            } catch (\Exception $e) {
                // Ignore errors
            }
            
            // Get hourly sales (current hour)
            try {
                $currentHour = date('Y-m-d H:00:00');
                $nextHour = date('Y-m-d H:59:59', strtotime('+1 hour'));
                $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE tenant_id = ? AND created_at >= ? AND created_at < ? AND status != 'CANCELLED' AND (is_paid = 1 OR status = 'SERVED')");
                $stmt->execute([$businessId, $currentHour, $nextHour]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $stats['hourly_sales_total'] = (float)($result['revenue'] ?? 0);
            } catch (\Exception $e) {
                // Ignore errors
            }
            
            // Get table counts (tables table uses tenant_id, status: FREE, OCCUPIED, PAYMENT_PENDING, etc.)
            try {
                $checkTable = $db->query("SHOW TABLES LIKE 'tables'");
                if ($checkTable->rowCount() > 0) {
                    // Total tables
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tables WHERE tenant_id = ?");
                    $stmt->execute([$businessId]);
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $stats['total_tables_count'] = (int)($result['count'] ?? 0);
                    
                    // Occupied tables (tables with status OCCUPIED - dolu masa)
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tables WHERE tenant_id = ? AND status = 'OCCUPIED'");
                    $stmt->execute([$businessId]);
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $stats['occupied_tables_count'] = (int)($result['count'] ?? 0);
                    
                    // Calculate occupancy rate
                    if ($stats['total_tables_count'] > 0) {
                        $stats['occupancy_rate'] = round(($stats['occupied_tables_count'] / $stats['total_tables_count']) * 100, 1);
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
            
            // Get active orders count
            try {
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE tenant_id = ? AND status NOT IN ('completed', 'cancelled', 'voided')");
                $stmt->execute([$businessId]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $stats['active_orders_count'] = (int)($result['count'] ?? 0);
            } catch (\Exception $e) {
                // Ignore errors
            }
            
            $this->apiResponse([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin getBusinessStats error', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
            
            $this->apiResponse([
                'success' => false,
                'error' => 'Failed to fetch business stats',
                'data' => [
                    'daily_revenue' => 0,
                    'hourly_sales_total' => 0,
                    'occupied_tables_count' => 0,
                    'total_tables_count' => 0,
                    'active_orders_count' => 0,
                    'occupancy_rate' => 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ], 500);
        }
    }
}
