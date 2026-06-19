<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\CustomerRepository;

class CustomerService extends BaseService {
    
    public function __construct(CustomerRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Register new customer
     * @param array $data Customer data
     * @param string|null $ownerUserId Existing owner user ID (optional, if provided, skip user creation)
     * @return array ['success' => bool, 'customer_id' => string|null, 'user_id' => string|null, 'error' => string|null]
     */
    public function register(array $data, ?string $ownerUserId = null): array {
        // Validasyon
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'customer_id' => null,
                'user_id' => null,
                'error' => 'Geçerli bir e-posta adresi giriniz.'
            ];
        }
        
        if (empty($data['password']) || strlen($data['password']) < 8) {
            return [
                'success' => false,
                'customer_id' => null,
                'user_id' => null,
                'error' => 'Şifre en az 8 karakter olmalıdır.'
            ];
        }
        
        // Mevcut kullanıcı seçildiğinde, eğer kullanıcının email'i ile zaten bir customer kaydı varsa
        // yeni customer kaydı oluşturmak yerine mevcut customer kaydını kullan
        $customerId = null;
        if ($ownerUserId) {
            $existingCustomer = $this->repository->findByEmail($data['email'] ?? '');
            if ($existingCustomer && !empty($existingCustomer['customer_id'])) {
                // Mevcut customer kaydını kullan
                $customerId = $existingCustomer['customer_id'];
                
                // Customer bilgilerini güncelle (company_name, subdomain vb.)
                $updateData = [];
                if (isset($data['company_name'])) {
                    $updateData['company_name'] = $data['company_name'];
                }
                if (isset($data['subdomain'])) {
                    $updateData['subdomain'] = $data['subdomain'];
                }
                if (isset($data['phone'])) {
                    $updateData['phone'] = $data['phone'];
                }
                if (isset($data['first_name'])) {
                    $updateData['first_name'] = $data['first_name'];
                }
                if (isset($data['last_name'])) {
                    $updateData['last_name'] = $data['last_name'];
                }
                if (isset($data['is_active'])) {
                    $updateData['is_active'] = $data['is_active'];
                }
                
                if (!empty($updateData)) {
                    $this->repository->update($customerId, $updateData);
                }
            }
        }
        
        // Eğer mevcut customer kaydı yoksa, yeni kayıt oluştur
        if (!$customerId) {
            // Email unique kontrolü - Sadece yeni kullanıcı oluşturulurken kontrol et
            if ($this->repository->emailExists($data['email'] ?? '')) {
                return [
                    'success' => false,
                    'customer_id' => null,
                    'user_id' => null,
                    'error' => 'Bu e-posta adresi zaten kullanılıyor.'
                ];
            }
            
            // Password hash'leme
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            if (isset($data['password'])) {
                $data['password'] = $passwordHash;
            }
            
            // Email verification token oluştur
            $data['email_verification_token'] = bin2hex(random_bytes(32));
            $data['customer_id'] = 'CUST_' . uniqid();
            
            // Business bilgilerini customers tablosuna ekle
            if (!isset($data['subscription_id'])) {
                $data['subscription_id'] = null;
            }
            if (!isset($data['setup_completed'])) {
                $data['setup_completed'] = 0;
            }
            if (!isset($data['business_type'])) {
                $data['business_type'] = 'restaurant';
            }
            
            // Müşteri oluştur (customers tablosuna)
            $customerId = $this->repository->create($data);
            
            if (!$customerId) {
                return [
                    'success' => false,
                    'customer_id' => null,
                    'user_id' => null,
                    'error' => 'Kayıt işlemi başarısız. Lütfen tekrar deneyiniz.'
                ];
            }
        }
        
        // Also create/update business record (for foreign key constraints)
        // Business bilgileri artık customers tablosunda tutuluyor
        // subscription_id, setup_completed, business_type alanları customers tablosuna eklendi
        // Migration 054_merge_businesses_to_customers.php ile businesses verileri customers'a merge edildi
        
        // Users tablosuna da kayıt ekle (admin panel erişimi için)
        // ONLY if owner_user_id is NOT provided
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            require_once __DIR__ . '/../helpers/functions.php';
            
            $userService = \App\Core\DependencyFactory::getUserService();
            
            $userId = null;
            
            if ($ownerUserId) {
                // Use existing user, don't create a new one
                $userId = $ownerUserId;
                
                // Associate user with business
                $userService->update($userId, ['tenant_id' => $customerId]);
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Using existing user for customer', [
                        'customer_id' => $customerId,
                        'user_id' => $userId
                    ]);
                }
            } else {
                // Create new user only if owner_user_id not provided
                $roleService = \App\Core\DependencyFactory::getRoleService();

                // New registrations start with BUSINESS_OWNER role. Paketi satın alan
                // kullanıcı işletme sahibi olur; trial bile olsa bu rol atanır.
                // Backward-compat: rol yoksa TRIAL / BUSINESS_MANAGER / MANAGER'a düşer.
                $roleData = $roleService->getByRoleCode('BUSINESS_OWNER');
                $assignedRoleCode = 'BUSINESS_OWNER';
                if (!$roleData) {
                    $roleData = $roleService->getByRoleCode('TRIAL');
                    $assignedRoleCode = 'TRIAL';
                }
                if (!$roleData) {
                    $roleData = $roleService->getByRoleCode('BUSINESS_MANAGER');
                    $assignedRoleCode = 'BUSINESS_MANAGER';
                }
                if (!$roleData) {
                    $roleData = $roleService->getByRoleCode('MANAGER');
                    $assignedRoleCode = 'MANAGER';
                }

                if (!$roleData || empty($roleData['role_id'])) {
                    return [
                        'success' => false,
                        'customer_id' => $customerId,
                        'user_id' => null,
                        'error' => 'Rol bilgisi bulunamadı. Lütfen sistem yöneticisi ile iletişime geçiniz.'
                    ];
                }
                
                // User oluştur
                // Name olarak email kullan (müşteriler email ile giriş yapacak)
                $firstName = trim($data['first_name'] ?? '');
                $lastName = trim($data['last_name'] ?? '');
                $fullName = trim($firstName . ' ' . $lastName);
                if (empty($fullName)) {
                    $fullName = $data['email']; // İsim yoksa email kullan
                }

                // Ensure requires_password_change is always an integer (0 or 1) for database
                $requiresPasswordChange = false;
                if (isset($data['requires_password_change'])) {
                    $requiresPasswordChange = !empty($data['requires_password_change']) && 
                                             $data['requires_password_change'] !== '0' && 
                                             $data['requires_password_change'] !== '';
                }
                
                // CRITICAL: Generate a PIN for BUSINESS_MANAGER users
                // PIN should be separate from password - generate a 4-digit PIN
                // Use last 4 digits of customer_id or generate random PIN
                if (isset($data['pin']) && !empty($data['pin'])) {
                    $defaultPin = trim($data['pin']);
                } else {
                    $pinFromId = substr(str_replace(['CUST_', '_'], '', $customerId), -4);
                    $defaultPin = (strlen($pinFromId) === 4 && ctype_digit($pinFromId))
                        ? $pinFromId
                        : str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                }
                
                // Hash the PIN for storage (PINs should be hashed, not encrypted, for password_verify)
                $pinHash = password_hash($defaultPin, PASSWORD_DEFAULT);
                
                $userData = [
                    'user_id' => generateId('u'),
                    'name' => $data['email'], // Email'i name olarak sakla (findByEmail için)
                    'pin' => $pinHash, // PIN hash'i (PIN girişi için)
                    'password' => $passwordHash, // Password hash'i (email/password girişi için) - if password column exists
                    'role' => $assignedRoleCode,
                    'role_id' => $roleData['role_id'],
                    'requires_password_change' => $requiresPasswordChange ? 1 : 0, // Convert to integer for database
                    'tenant_id' => $customerId
                ];

                // Check if additional columns exist in users table and add them if available
                try {
                    // Drop optional fields that aren't on the users schema.
                    if (!\App\Core\DbSchema::hasColumn('users', 'requires_password_change')) {
                        unset($userData['requires_password_change']);
                    }
                    if (\App\Core\DbSchema::hasColumn('users', 'email')) {
                        $userData['email'] = $data['email'];
                    }
                    if (\App\Core\DbSchema::hasColumn('users', 'first_name') && !empty($firstName)) {
                        $userData['first_name'] = $firstName;
                    }
                    if (\App\Core\DbSchema::hasColumn('users', 'last_name') && !empty($lastName)) {
                        $userData['last_name'] = $lastName;
                    }
                    if (\App\Core\DbSchema::hasColumn('users', 'phone') && !empty($data['phone'] ?? '')) {
                        $userData['phone'] = $data['phone'];
                    }
                } catch (\Exception $e) {
                    // If there's an error checking columns, log but continue
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Error checking users table columns', [
                            'error' => $e->getMessage()
                        ]);
                    }
                    // Remove requires_password_change if check failed
                    unset($userData['requires_password_change']);
                }

                $userId = $userService->create($userData);
                
                if (!$userId) {
                    // User oluşturulamadı ama customer oluşturuldu - customer'ı sil
                    // Şimdilik sadece log yaz, customer'ı silme (veri kaybı olmasın)
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('CustomerService::register - User creation failed', [
                            'customer_id' => $customerId,
                            'email' => $data['email']
                        ]);
                    }
                    
                    return [
                        'success' => false,
                        'customer_id' => $customerId,
                        'user_id' => null,
                        'error' => 'Kullanıcı kaydı oluşturulamadı. Lütfen tekrar deneyiniz.'
                    ];
                }
            }
            
            // Create subdomain on Plesk if subdomain was provided
            $subdomain = $data['subdomain'] ?? null;
            if (!empty($subdomain)) {
                try {
                    $subdomainService = new \App\Services\SubdomainService();
                    $subdomainResult = $subdomainService->createSubdomainConfig($subdomain, $customerId);
                    if (!$subdomainResult['success']) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('CustomerService::register - Subdomain creation failed but registration continues', [
                                'customer_id' => $customerId,
                                'subdomain' => $subdomain,
                                'error' => $subdomainResult['message'] ?? 'Unknown error'
                            ]);
                        }
                    } else {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('CustomerService::register - Subdomain created successfully', [
                                'customer_id' => $customerId,
                                'subdomain' => $subdomain,
                                'url' => $subdomainResult['url'] ?? null
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('CustomerService::register - Subdomain creation exception but registration continues', [
                            'customer_id' => $customerId,
                            'subdomain' => $subdomain,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $result = [
                'success' => true,
                'customer_id' => $customerId ?: $data['customer_id'],
                'user_id' => $userId,
                'error' => null
            ];

            $this->createTenantDatabaseAsync($customerId, $subdomain);

            // Otomatik ücretsiz deneme aboneliği. Web/mobil/API hangi
            // kanaldan kayıt gelirse gelsin burada tetiklenir — böylece
            // kayıt eden her işletme süper admin'in system_settings'te
            // yapılandırdığı trial paketiyle başlar. Zaten trial varsa
            // idempotent çalışır (hasUsedTrial guard).
            try {
                $trialService = \App\Core\DependencyFactory::getTrialService();
                if ($trialService && !$trialService->hasUsedTrial($customerId)) {
                    $trialResult = $trialService->createTrialSubscription($customerId);
                    if (class_exists('\App\Core\Logger')) {
                        if (!empty($trialResult['success'])) {
                            \App\Core\Logger::info('Auto trial created on register', [
                                'customer_id'    => $customerId,
                                'trial_ends_at'  => $trialResult['trial_ends_at'] ?? null,
                            ]);
                        } else {
                            \App\Core\Logger::warning('Auto trial creation skipped', [
                                'customer_id' => $customerId,
                                'reason'      => $trialResult['error'] ?? 'unknown',
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Auto trial on register failed', [
                        'customer_id' => $customerId,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            // Hoş geldin bildirimi (mail + WhatsApp). Süper admin toggle'ları
            // welcome_email_enabled / welcome_whatsapp_enabled ile kontrol
            // edilir. Başarısızlıklar kayıt akışını bloklamaz.
            $this->sendWelcomeNotifications([
                'customer_id' => $customerId,
                'email'       => $data['email'] ?? '',
                'phone'       => $data['phone'] ?? '',
                'first_name'  => $data['first_name'] ?? '',
                'last_name'   => $data['last_name'] ?? '',
                'company_name'=> $data['company_name'] ?? '',
                'subdomain'   => $subdomain,
            ]);

            return $result;

        } catch (\Exception $e) {
            // User oluşturma hatası - customer kaydı başarılı ama user kaydı başarısız
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('CustomerService::register - Exception during user creation', [
                    'error' => $e->getMessage(),
                    'customer_id' => $customerId,
                    'email' => $data['email']
                ]);
            }

            // Still attempt to create tenant database even if user creation had issues
            $this->createTenantDatabaseAsync($customerId, $data['subdomain'] ?? null);

            return [
                'success' => false,
                'customer_id' => $customerId,
                'user_id' => null,
                'error' => 'Kullanıcı kaydı oluşturulurken bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send welcome email + WhatsApp message to a newly registered customer.
     *
     * Both channels can be toggled globally from the super-admin settings:
     *   welcome_email_enabled     (default "1")
     *   welcome_whatsapp_enabled  (default "1")
     *
     * @param array $ctx Keys: customer_id, email, phone, first_name, last_name, company_name, subdomain
     */
    private function sendWelcomeNotifications(array $ctx): void {
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $emailEnabled = $settingsService->getSetting('welcome_email_enabled', '1') !== '0';
            $whatsappEnabled = $settingsService->getSetting('welcome_whatsapp_enabled', '1') !== '0';

            // --- Email ---
            if ($emailEnabled && !empty($ctx['email']) && filter_var($ctx['email'], FILTER_VALIDATE_EMAIL)) {
                try {
                    require_once __DIR__ . '/Email/EmailType/WelcomeEmail.php';
                    $emailService = \App\Core\DependencyFactory::getEmailService();
                    $emailType = new \App\Services\Email\EmailType\WelcomeEmail($settingsService, [
                        'email'       => $ctx['email'],
                        'first_name'  => $ctx['first_name'] ?? '',
                        'last_name'   => $ctx['last_name'] ?? '',
                        'customer_id' => $ctx['customer_id'] ?? null,
                    ]);
                    $emailService->sendEmailType($emailType);
                } catch (\Throwable $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Welcome email failed', [
                            'customer_id' => $ctx['customer_id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // --- WhatsApp ---
            if ($whatsappEnabled && !empty($ctx['phone'])) {
                try {
                    $fullName = trim(($ctx['first_name'] ?? '') . ' ' . ($ctx['last_name'] ?? ''));
                    if ($fullName === '') {
                        $fullName = $ctx['company_name'] ?? 'Değerli Kullanıcı';
                    }
                    $wa = \App\Core\DependencyFactory::getWhatsAppService();
                    $wa->sendWelcomeMessage((string)$ctx['phone'], $fullName, $ctx['subdomain'] ?? null);
                } catch (\Throwable $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Welcome WhatsApp failed', [
                            'customer_id' => $ctx['customer_id'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('sendWelcomeNotifications failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create tenant database asynchronously
     * @param string $customerId Customer ID
     * @param string|null $subdomain Subdomain for the tenant
     */
    private function createTenantDatabaseAsync(string $customerId, ?string $subdomain): void {
        // This would normally run in a background job, but for now we'll run synchronously
        // CRITICAL: Check if tenant_database column exists before attempting creation
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            if (!\App\Core\DbSchema::hasColumn('customers', 'tenant_database')) {
                // Column doesn't exist - skip tenant database creation
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('tenant_database column does not exist, skipping tenant database creation', [
                        'customer_id' => $customerId,
                        'subdomain' => $subdomain
                    ]);
                }
                return;
            }
            
            $tenantService = new \App\Services\TenantService();

            // If subdomain is not provided, generate from customer ID
            if (empty($subdomain)) {
                $customer = $this->repository->findById($customerId);
                $subdomain = $customer['subdomain'] ?? null;

                if (empty($subdomain)) {
                    $subdomainService = new \App\Services\SubdomainService();
                    $subdomain = $subdomainService->generateSubdomain($customer['company_name'] ?? $customer['email']);
                }
            }

            $result = $tenantService->createTenantDatabase($subdomain, $customerId);

            if (!$result['success']) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Tenant database creation failed during registration', [
                        'customer_id' => $customerId,
                        'subdomain' => $subdomain,
                        'error' => $result['error'] ?? 'Unknown error'
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail registration
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error during tenant database creation', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Authenticate customer
     * @param string $email
     * @param string $password
     * @return array|null Customer data on success, null on failure
     */
    public function authenticate(string $email, string $password): ?array {
        // Normalize email (trim and lowercase)
        $email = trim(strtolower($email));
        
        if (empty($email) || empty($password)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("CustomerService::authenticate - Empty email or password", [
                    'email_provided' => !empty($email),
                    'password_provided' => !empty($password)
                ]);
            }
            return null;
        }
        
        $customer = $this->repository->findByEmail($email);
        
        if (!$customer) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("CustomerService::authenticate - Customer not found", [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return null;
        }
        
        // Check if password field exists and is not empty
        if (empty($customer['password'])) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("CustomerService::authenticate - Customer has no password set", [
                    'email' => $email,
                    'customer_id' => $customer['customer_id'] ?? 'unknown'
                ]);
            }
            return null;
        }
        
        // Verify password
        $passwordVerified = password_verify($password, $customer['password']);
        
        if (!$passwordVerified) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("CustomerService::authenticate - Password verification failed", [
                    'email' => $email,
                    'customer_id' => $customer['customer_id'] ?? 'unknown',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'password_hash_length' => strlen($customer['password'] ?? ''),
                    'password_hash_prefix' => substr($customer['password'] ?? '', 0, 7)
                ]);
            }
            return null;
        }
        
        if (isset($customer['is_active']) && (int)$customer['is_active'] === 0) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("CustomerService::authenticate - Business is deactivated", [
                    'email' => $email,
                    'customer_id' => $customer['customer_id'] ?? 'unknown',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            throw new \App\Exceptions\BusinessRuleException('İşletme devre dışı bırakılmış', [
 'code' => 'BUSINESS_DEACTIVATED',
 'customer_id' => $customer['customer_id'] ?? null,
 ]);
        }
        
        $this->repository->updateLastLogin($customer['customer_id']);
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("CustomerService::authenticate - Authentication successful", [
                'email' => $email,
                'customer_id' => $customer['customer_id'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        return $customer;
    }
    
    /**
     * Get customer by ID
     * @param string $customerId
     * @return array|null
     */
    public function getById(string $customerId): ?array {
        return $this->repository->findById($customerId);
    }
    
    /**
     * Get customer by ID (alias for getById for backward compatibility)
     * @param string $customerId
     * @return array|null
     */
    public function getCustomerById(string $customerId): ?array {
        return $this->getById($customerId);
    }
    
    /**
     * Find customer by email
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array {
        return $this->repository->findByEmail($email);
    }
    
    /**
     * Update customer profile
     * @param string $customerId
     * @param array $data
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function updateProfile(string $customerId, array $data): array {
        // Email değişikliği kontrolü
        if (isset($data['email'])) {
            $currentCustomer = $this->repository->findById($customerId);
            if ($currentCustomer && $currentCustomer['email'] !== $data['email']) {
                // Yeni email unique mi kontrol et
                if ($this->repository->emailExists($data['email'], $customerId)) {
                    return [
                        'success' => false,
                        'error' => 'Bu e-posta adresi zaten kullanılıyor.'
                    ];
                }
            }
        }
        
        // Şifre değişikliği kontrolü ve hash'leme
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                return [
                    'success' => false,
                    'error' => 'Şifre en az 6 karakter olmalıdır.'
                ];
            }
            // Password hash'leme
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            // Şifre değişikliği yoksa password'u data'dan çıkar
            unset($data['password']);
        }
        
        // Email değişikliği varsa email_verified'i false yap ve yeni token oluştur
        if (isset($data['email'])) {
            $currentCustomer = $this->repository->findById($customerId);
            if ($currentCustomer && $currentCustomer['email'] !== $data['email']) {
                $data['email_verified'] = false;
                $data['email_verification_token'] = bin2hex(random_bytes(32));
            }
        }
        
        // Boş değerleri temizle
        $data = array_filter($data, function($value) {
            return $value !== '' && $value !== null;
        });
        
        if (empty($data)) {
            return [
                'success' => false,
                'error' => 'Güncellenecek veri bulunamadı.'
            ];
        }
        
        $result = $this->repository->update($customerId, $data);
        
        if ($result) {
            return [
                'success' => true,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Profil güncellenirken bir hata oluştu.'
        ];
    }
    
    /**
     * Verify email with token
     * @param string $token
     * @return bool
     */
    public function verifyEmail(string $token): bool {
        return $this->repository->findByToken($token) !== null;
    }
    
    /**
     * Check if customer is super admin
     * @param array $customer Customer data
     * @return bool True if super admin
     */
    private function isSuperAdminCustomer(array $customer): bool {
        $role = strtoupper(trim($customer['role'] ?? ''));
        $roleId = strtoupper(trim($customer['role_id'] ?? ''));
        
        // Check by role
        if ($role === 'SUPER_ADMIN' || 
            $role === 'ROLE_SUPER_ADMIN' ||
            $role === 'QODMIN' ||
            $role === 'ROLE_QODMIN') {
            return true;
        }
        
        // Check by role_id
        if ($roleId === 'SUPER_ADMIN' ||
            $roleId === 'ROLE_SUPER_ADMIN' ||
            $roleId === 'QODMIN' ||
            $roleId === 'ROLE_QODMIN') {
            return true;
        }
        
        // REMOVED: Hardcoded email check
        // A customer can have the same email as a super admin if they manage a business
        // Only filter by role, not by email
        
        return false;
    }
    
    /**
     * Get all businesses (customers)
     * Alias for getAll() - returns all customers as businesses
     * Excludes super admin users from the list
     * @return array All businesses/customers (excluding super admins)
     */
    public function getAllBusinesses(): array {
        $allCustomers = $this->repository->getAll();
        
        // Filter out super admin users and re-index array
        $filtered = array_filter($allCustomers, function($customer) {
            return !$this->isSuperAdminCustomer($customer);
        });
        
        // Re-index array to ensure sequential keys
        return array_values($filtered);
    }
    
    /**
     * Get all customers with subscriptions
     * Excludes super admin users from the list
     * @return array Customers with subscription data (excluding super admins)
     */
    public function getAllWithSubscriptions(): array {
        try {
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $customers = $this->repository->getAll();

            $businessCustomers = array_filter($customers, function($customer) {
                return !$this->isSuperAdminCustomer($customer);
            });
            
            // Re-index array to ensure sequential keys
            $businessCustomers = array_values($businessCustomers);
            
            $subscriptionRepo = \App\Core\DependencyFactory::getSubscriptionRepository();

            foreach ($businessCustomers as &$customer) {
                $customerId = $customer['customer_id'] ?? '';

                // Active subscription (status='active' only)
                $subscription = $subscriptionService->getCustomerSubscription($customerId);
                $customer['subscription'] = $subscription;
                $customer['subscription_id'] = $subscription['subscription_id'] ?? null;
                $customer['package_name'] = $subscription['package_name'] ?? null;
                $customer['is_trial'] = (bool)($subscription['is_trial'] ?? false);

                // Latest subscription regardless of status — used for detailed status display
                $latestSub = $subscription ?? $subscriptionRepo->getLatestSubscription($customerId);
                $customer['latest_subscription_status'] = $latestSub['status'] ?? null;
                $customer['latest_subscription_is_trial'] = (bool)($latestSub['is_trial'] ?? false);
                $customer['latest_subscription_end'] = $latestSub['current_period_end'] ?? null;
            }
            
            return $businessCustomers;
        } catch (\Exception $e) {
            // If subscription service fails, return customers without subscription data (still filtered)
            $allCustomers = $this->repository->getAll();
            $filtered = array_filter($allCustomers, function($customer) {
                return !$this->isSuperAdminCustomer($customer);
            });
            
            // Re-index array to ensure sequential keys
            return array_values($filtered);
        }
    }
    
    /**
     * Get total business count
     * Excludes super admin users from the count
     * @return int Total number of businesses (customers, excluding super admins)
     */
    public function getTotalBusinessCount(): int {
        try {
            $allBusinesses = $this->getAllBusinesses();
            return count($allBusinesses);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get business performance analytics
     * Excludes super admin users from analytics
     * @return array Business performance data (excluding super admins)
     */
    public function getBusinessPerformanceAnalytics(): array {
        try {
            $businesses = $this->getAllBusinesses();
            
            $analytics = [];
            foreach ($businesses as $business) {
                $businessId = $business['customer_id'] ?? '';
                if (empty($businessId)) {
                    continue;
                }
                
                $db = \App\Core\DependencyFactory::getDatabase();
                $col = 'tenant_id';
                $revenueData = ['revenue' => 0, 'order_count' => 0];
                try {
                    $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount),0) as revenue, COUNT(*) as order_count FROM orders WHERE {$col} = :bid AND status != 'CANCELLED'");
                    $stmt->execute(['bid' => $businessId]);
                    $revenueData = $stmt->fetch(\PDO::FETCH_ASSOC) ?: $revenueData;
                } catch (\Exception $e) {}
                $rev = (float)($revenueData['revenue'] ?? 0);
                $cnt = (int)($revenueData['order_count'] ?? 0);
                $analytics[] = [
                    'business_id' => $businessId,
                    'business_name' => $business['company_name'] ?? $business['email'] ?? 'Unknown',
                    'revenue' => $rev,
                    'order_count' => $cnt,
                    'avg_order_value' => $cnt > 0 ? round($rev / $cnt, 2) : 0
                ];
            }
            
            return $analytics;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get customer by subdomain
     * @param string $subdomain
     * @return array|null
     */
    public function getBySubdomain(string $subdomain): ?array {
        try {
            return $this->repository->findBySubdomain($subdomain);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting customer by subdomain', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Generate a unique config code for printer bridge
     * @param string $customerId Customer ID
     * @return array ['success' => bool, 'config_code' => string|null, 'error' => string|null]
     */
    public function generateConfigCode(string $customerId): array {
        try {
            // Get customer info
            $customer = $this->getCustomerById($customerId);
            if (!$customer) {
                return [
                    'success' => false,
                    'config_code' => null,
                    'error' => 'Customer not found'
                ];
            }
            
            // Generate unique config code
            $businessName = $customer['company_name'] ?? $customer['email'] ?? 'Business';
            $configCode = hash('sha256', $businessName . microtime() . random_bytes(16));
            
            // Ensure uniqueness (check if code already exists)
            $maxAttempts = 10;
            $attempts = 0;
            $db = \App\Core\DependencyFactory::getDatabase();
            
            while ($attempts < $maxAttempts) {
                // Check if config_code exists in businesses table
                $checkStmt = $db->prepare("SELECT id FROM businesses WHERE config_code = ? LIMIT 1");
                $checkStmt->execute([$configCode]);
                $exists = $checkStmt->fetch();
                
                if (!$exists) {
                    break; // Code is unique
                }
                
                // Regenerate if exists
                $configCode = hash('sha256', $businessName . microtime() . random_bytes(16) . $attempts);
                $attempts++;
            }
            
            if ($attempts >= $maxAttempts) {
                return [
                    'success' => false,
                    'config_code' => null,
                    'error' => 'Failed to generate unique config code'
                ];
            }
            
            // Update businesses table with config_code
            // First check if businesses record exists for this customer
            // businesses table has: id (PK), business_id (customer_id), business_name, config_code, etc.
            $businessStmt = $db->prepare("SELECT id, tenant_id FROM businesses WHERE tenant_id = ? LIMIT 1");
            $businessStmt->execute([$customerId]);
            $business = $businessStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($business) {
                // Update existing businesses record using id (primary key)
                $updateStmt = $db->prepare("UPDATE businesses SET config_code = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$configCode, $business['id']]);
            } else {
                // Create businesses record if it doesn't exist
                // Note: id is auto-increment, business_id is the customer_id
                $businessName = $customer['company_name'] ?? trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?: $customer['email'];
                $insertStmt = $db->prepare("
                    INSERT INTO businesses (tenant_id, business_name, config_code, subdomain, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $insertStmt->execute([
                    $customerId,
                    $businessName,
                    $configCode,
                    $customer['subdomain'] ?? null
                ]);
            }
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Config code generated', [
                    'customer_id' => $customerId,
                    'config_code_prefix' => substr($configCode, 0, 8)
                ]);
            }
            
            return [
                'success' => true,
                'config_code' => $configCode,
                'error' => null
            ];
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error generating config code', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
            return [
                'success' => false,
                'config_code' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get config code for a customer
     * @param string $customerId Customer ID
     * @return array ['success' => bool, 'config_code' => string|null, 'error' => string|null]
     */
    public function getConfigCode(string $customerId): array {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Get config_code from businesses table
            // businesses table has: id (PK), business_id (customer_id), config_code
            $stmt = $db->prepare("SELECT config_code FROM businesses WHERE tenant_id = ? LIMIT 1");
            $stmt->execute([$customerId]);
            $business = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($business && !empty($business['config_code'])) {
                return [
                    'success' => true,
                    'config_code' => $business['config_code'],
                    'error' => null
                ];
            }
            
            return [
                'success' => true,
                'config_code' => null,
                'error' => null
            ];
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error getting config code', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
            return [
                'success' => false,
                'config_code' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}
