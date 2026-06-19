<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PackageRepository;

class PackageService extends BaseService {
    
    public function __construct(PackageRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Get all packages
     * @return array
     */
    public function getAllPackages(): array {
        return $this->repository->getAll();
    }
    
    /**
     * Get active packages
     * @return array
     */
    public function getActivePackages(): array {
        return $this->repository->getActivePackages();
    }
    
    /**
     * Get package by ID
     * @param string $packageId
     * @return array|null
     */
    public function getPackageById(string $packageId): ?array {
        return $this->repository->findById($packageId);
    }
    
    /**
     * Create new package
     * @param array $data
     * @return array ['success' => bool, 'package_id' => string|null, 'error' => string|null]
     */
    public function createPackage(array $data): array {
        try {
            // Validasyon
            if (empty($data['name'])) {
                return [
                    'success' => false,
                    'package_id' => null,
                    'error' => 'Paket adı gereklidir.'
                ];
            }
            
            // Fiyat kontrolü - en az bir fiyatlandırma tipi olmalı
            $hasPrice = !empty($data['price_one_time']) || 
                       !empty($data['price_monthly']) || 
                       !empty($data['price_yearly']);
            
            if (!$hasPrice) {
                return [
                    'success' => false,
                    'package_id' => null,
                    'error' => 'En az bir fiyatlandırma tipi belirtilmelidir.'
                ];
            }
            
            // Package ID oluştur - eğer yoksa veya boşsa
            if (empty($data['package_id'])) {
                require_once __DIR__ . '/../helpers/functions.php';
                $data['package_id'] = generateId('pkg');
            }
            
            // Features JSON encode
            if (isset($data['features']) && is_array($data['features'])) {
                $data['features'] = json_encode($data['features']);
            }
            
            // Clean up and normalize data
            // Remove fields that shouldn't be in database
            unset($data['id']); // Remove auto-increment id if present
            unset($data['created_at']); // Let database set this
            unset($data['updated_at']); // Let database set this
            unset($data['package_name']); // Use 'name' instead
            unset($data['package_code']); // Not in our schema
            unset($data['currency']); // Not in our schema
            unset($data['max_users']); // Not in our schema
            unset($data['max_tables']); // Not in our schema
            unset($data['max_menu_items']); // Not in our schema
            unset($data['max_orders_per_month']); // Not in our schema
            unset($data['is_featured']); // Not in our schema
            unset($data['sort_order']); // Not in our schema
            unset($data['trial_days']); // Not in our schema
            unset($data['duration_months']); // Use duration_days instead
            unset($data['csrf_token']); // Remove CSRF token
            unset($data['_method']); // Remove method override
            
            // Normalize empty strings to null for optional fields
            $optionalFields = ['description', 'duration_days', 'discount_percentage', 'price_one_time', 'price_monthly', 'price_yearly', 'features'];
            foreach ($optionalFields as $field) {
                if (isset($data[$field]) && $data[$field] === '') {
                    $data[$field] = null;
                }
            }
            
            // Ensure numeric fields are properly typed
            if (isset($data['price_one_time']) && $data['price_one_time'] !== null) {
                $data['price_one_time'] = (float)$data['price_one_time'];
            }
            if (isset($data['price_monthly']) && $data['price_monthly'] !== null) {
                $data['price_monthly'] = (float)$data['price_monthly'];
            }
            if (isset($data['price_yearly']) && $data['price_yearly'] !== null) {
                $data['price_yearly'] = (float)$data['price_yearly'];
            }
            if (isset($data['duration_days']) && $data['duration_days'] !== null) {
                $data['duration_days'] = (int)$data['duration_days'];
            }
            if (isset($data['discount_percentage']) && $data['discount_percentage'] !== null) {
                $data['discount_percentage'] = (float)$data['discount_percentage'];
            }
            
            if (isset($data['billing_options'])) {
                $bo = $data['billing_options'];
                if (is_string($bo)) {
                    $bo = trim($bo);
                    if ($bo === '') {
                        $data['billing_options'] = null;
                    } else {
                        $decoded = json_decode($bo, true);
                        $data['billing_options'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : null;
                    }
                } elseif (is_array($bo)) {
                    $data['billing_options'] = json_encode($bo, JSON_UNESCAPED_UNICODE);
                }
            }
            
            // Boolean değerleri düzelt (MySQL için 0/1)
            $data['auto_renew'] = isset($data['auto_renew']) ? ((bool)$data['auto_renew'] ? 1 : 0) : 0;
            $data['is_active'] = isset($data['is_active']) ? ((bool)$data['is_active'] ? 1 : 0) : 1;
            
            // Extract permissions before creating package
            $permissions = $data['permissions'] ?? [];
            unset($data['permissions']);
            
            // Log data before insert
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackageService::createPackage - Data before insert', [
                    'data_keys' => array_keys($data),
                    'data' => $data
                ]);
            }
            
            $packageId = $this->repository->create($data);
            
            // Log package creation result
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackageService::createPackage - Package creation attempt', [
                    'package_id' => $packageId,
                    'package_id_type' => gettype($packageId),
                    'package_name' => $data['name'] ?? 'N/A',
                    'data_keys' => array_keys($data),
                    'primary_key_in_data' => isset($data['package_id']) ? $data['package_id'] : 'NOT SET'
                ]);
            }
            
            // Check if creation was successful
            // $packageId can be: string (package_id), int (auto-increment), true (success but no ID), false (failure)
            if ($packageId === false || $packageId === null) {
                return [
                    'success' => false,
                    'package_id' => null,
                    'error' => 'Paket oluşturulurken veritabanı hatası oluştu.'
                ];
            }
            
            // If packageId is true, it means insert succeeded but we don't have the ID
            // This shouldn't happen for packages table (string primary key), but handle it anyway
            if ($packageId === true) {
                // Try to get the package_id from data
                $packageId = $data['package_id'] ?? null;
                if (empty($packageId)) {
                    return [
                        'success' => false,
                        'package_id' => null,
                        'error' => 'Paket oluşturuldu ancak paket ID alınamadı.'
                    ];
                }
            }
            
            // Ensure packageId is a string
            $packageId = (string)$packageId;
            
            if (!empty($packageId)) {
                // Verify package was actually created
                $createdPackage = $this->repository->findById($packageId);
                if (!$createdPackage) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('PackageService::createPackage - Package not found after creation', [
                            'package_id' => $packageId,
                            'package_name' => $data['name'] ?? 'N/A'
                        ]);
                    }
                    return [
                        'success' => false,
                        'package_id' => null,
                        'error' => 'Paket oluşturuldu ancak veritabanında bulunamadı.'
                    ];
                }
                
                // Assign permissions to package
                if (!empty($permissions) && is_array($permissions)) {
                    foreach ($permissions as $permissionId) {
                        // Skip disabled permissions (empty or null)
                        if (empty($permissionId)) {
                            continue;
                        }
                        try {
                            $this->repository->assignPermission($packageId, $permissionId);
                        } catch (\Exception $e) {
                            // Log permission assignment error but don't fail the package creation
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('PackageService::createPackage - Permission assignment error', [
                                    'error' => $e->getMessage(),
                                    'package_id' => $packageId,
                                    'permission_id' => $permissionId
                                ]);
                            }
                        }
                    }
                }
                
                // Clear cache to ensure fresh data (if cache exists)
                try {
                    if (method_exists($this->repository, 'clearCache')) {
                        $this->repository->clearCache();
                    }
                } catch (\Exception $cacheError) {
                    // Cache error is not critical - continue without cache
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('PackageService::createPackage - Cache clear failed', [
                            'error' => $cacheError->getMessage()
                        ]);
                    }
                }
                
                return [
                    'success' => true,
                    'package_id' => $packageId,
                    'error' => null
                ];
            }
            
            return [
                'success' => false,
                'package_id' => null,
                'error' => 'Paket oluşturulurken bir hata oluştu.'
            ];
        } catch (\PDOException $e) {
            // Database error - get detailed error info
            $errorInfo = $e->errorInfo ?? [];
            $sqlState = $errorInfo[0] ?? 'unknown';
            $errorCode = $errorInfo[1] ?? 'unknown';
            $errorMessage = $errorInfo[2] ?? $e->getMessage();
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageService::createPackage - Database error', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql_state' => $sqlState,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'data_keys' => array_keys($data ?? [])
                ]);
            }
            
            // Return more specific error message
            $userMessage = 'Veritabanı hatası: Paket oluşturulamadı.';
            if (strpos($errorMessage, 'doesn\'t have a default value') !== false) {
                $userMessage = 'Veritabanı hatası: Bazı zorunlu alanlar eksik. Lütfen tüm alanları doldurun.';
            } elseif (strpos($errorMessage, 'Duplicate entry') !== false) {
                $userMessage = 'Bu paket ID\'si zaten kullanılıyor. Lütfen farklı bir paket oluşturun.';
            } elseif (strpos($errorMessage, 'Unknown column') !== false) {
                $userMessage = 'Veritabanı yapısı hatası: Tablo yapısı güncel değil. Lütfen migration çalıştırın.';
            }
            
            return [
                'success' => false,
                'package_id' => null,
                'error' => $userMessage
            ];
        } catch (\Exception $e) {
            // Other errors
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageService::createPackage - Error', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return [
                'success' => false,
                'package_id' => null,
                'error' => 'Paket oluşturulurken beklenmeyen bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update package
     * @param string $packageId
     * @param array $data
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function updatePackage(string $packageId, array $data): array {
        // Clean up and normalize data - REMOVE FIELDS THAT SHOULDN'T BE IN DATABASE
        unset($data['id']); // Remove auto-increment id if present
        unset($data['created_at']); // Let database handle this
        unset($data['updated_at']); // Let database handle this
        unset($data['csrf_token']); // Remove CSRF token
        unset($data['_method']); // Remove method override
        unset($data['url']); // Remove URL field
        unset($data['package_id']); // Don't update primary key
        unset($data['package_name']); // Use 'name' instead
        unset($data['package_code']); // Not in our schema
        unset($data['currency']); // Not in our schema
        unset($data['max_users']); // Not in our schema
        unset($data['max_tables']); // Not in our schema
        unset($data['max_menu_items']); // Not in our schema
        unset($data['max_orders_per_month']); // Not in our schema
        unset($data['is_featured']); // Not in our schema
        unset($data['sort_order']); // Not in our schema
        unset($data['trial_days']); // Not in our schema
        unset($data['duration_months']); // Use duration_days instead
        
        // Normalize empty strings to null for optional fields
        $optionalFields = ['description', 'duration_days', 'discount_percentage', 'price_one_time', 'price_monthly', 'price_yearly', 'features'];
        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        
        // Ensure numeric fields are properly typed
        if (isset($data['price_one_time']) && $data['price_one_time'] !== null) {
            $data['price_one_time'] = (float)$data['price_one_time'];
        }
        if (isset($data['price_monthly']) && $data['price_monthly'] !== null) {
            $data['price_monthly'] = (float)$data['price_monthly'];
        }
        if (isset($data['price_yearly']) && $data['price_yearly'] !== null) {
            $data['price_yearly'] = (float)$data['price_yearly'];
        }
        if (isset($data['duration_days']) && $data['duration_days'] !== null) {
            $data['duration_days'] = (int)$data['duration_days'];
        }
        if (isset($data['discount_percentage']) && $data['discount_percentage'] !== null) {
            $data['discount_percentage'] = (float)$data['discount_percentage'];
        }
        
        if (array_key_exists('billing_options', $data)) {
            $bo = $data['billing_options'];
            if (is_string($bo)) {
                $bo = trim($bo);
                if ($bo === '') {
                    $data['billing_options'] = null;
                } else {
                    $decoded = json_decode($bo, true);
                    $data['billing_options'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : null;
                }
            } elseif (is_array($bo)) {
                $data['billing_options'] = json_encode($bo, JSON_UNESCAPED_UNICODE);
            }
        }
        
        // Features JSON encode
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        
        // Boolean değerleri düzelt (MySQL için 0/1)
        if (isset($data['auto_renew'])) {
            $data['auto_renew'] = ((bool)$data['auto_renew'] ? 1 : 0);
        }
        if (isset($data['is_active'])) {
            $data['is_active'] = ((bool)$data['is_active'] ? 1 : 0);
        }
        
        // Extract permissions before updating package
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);
        
        // Log data before update
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('PackageService::updatePackage - Data before update', [
                'package_id' => $packageId,
                'data_keys' => array_keys($data),
                'data' => $data
            ]);
        }
        
        try {
            $result = $this->repository->update($packageId, $data);
            
            // Log update result
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackageService::updatePackage - Repository result', [
                    'package_id' => $packageId,
                    'result' => $result,
                    'result_type' => gettype($result)
                ]);
            }
            
            // Update permissions if provided
            if ($result && is_array($permissions)) {
                // Update permissions - remove all existing and add new ones
                try {
                    // Remove all existing permissions
                    $existingPermissions = $this->repository->getPackagePermissions($packageId);
                    foreach ($existingPermissions as $perm) {
                        $permId = $perm['permission_id'] ?? null;
                        if ($permId !== null && $permId !== '') {
                            $this->repository->removePermission($packageId, (string)$permId);
                        }
                    }
                    
                    // Add new permissions
                    if (!empty($permissions)) {
                        foreach ($permissions as $permissionId) {
                            // Skip empty permission IDs
                            if (empty($permissionId)) {
                                continue;
                            }
                            $this->repository->assignPermission($packageId, $permissionId);
                        }
                    }
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('PackageService::updatePackage - Permissions updated', [
                            'package_id' => $packageId,
                            'permissions_count' => count($permissions)
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the update
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('PackageService::updatePackage - Permission update error', [
                            'error' => $e->getMessage(),
                            'package_id' => $packageId,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            }
            
            // Clear cache
            try {
                if (method_exists($this->repository, 'clearCache')) {
                    $this->repository->clearCache();
                }
            } catch (\Exception $cacheError) {
                // Cache error is not critical
            }
            
            if ($result) {
                return [
                    'success' => true,
                    'error' => null
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Paket güncellenirken bir hata oluştu.'
            ];
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageService::updatePackage - PDOException', [
                    'message' => $e->getMessage(),
                    'package_id' => $packageId
                ]);
            }
            return [
                'success' => false,
                'error' => 'Veritabanı hatası: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageService::updatePackage - Exception', [
                    'message' => $e->getMessage(),
                    'package_id' => $packageId
                ]);
            }
            return [
                'success' => false,
                'error' => 'Paket güncellenirken hata oluştu: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete package (hard delete)
     * @param string $packageId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function deletePackage(string $packageId): array {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM subscriptions WHERE package_id = :pid AND status = 'active'");
            $stmt->execute(['pid' => $packageId]);
            $activeCount = (int)($stmt->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0);
            if ($activeCount > 0) {
                return [
                    'success' => false,
                    'error' => "Bu pakete bağlı {$activeCount} aktif abonelik var. Silmeden önce abonelikleri iptal edin."
                ];
            }
            
            try {
                $permissions = $this->repository->getPackagePermissions($packageId);
                foreach ($permissions as $perm) {
                    $this->repository->removePermission($packageId, $perm['permission_id']);
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('PackageService::deletePackage - Permissions deleted', [
                        'package_id' => $packageId,
                        'permissions_count' => count($permissions)
                    ]);
                }
            } catch (\Exception $e) {
                // Log but don't fail if permissions deletion fails
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('PackageService::deletePackage - Failed to delete permissions', [
                        'package_id' => $packageId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Hard delete - actually delete from database
            $result = $this->repository->delete($packageId);
            
            // Log delete result
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackageService::deletePackage - Repository result', [
                    'package_id' => $packageId,
                    'result' => $result,
                    'result_type' => gettype($result)
                ]);
            }
            
            // Clear cache
            try {
                if (method_exists($this->repository, 'clearCache')) {
                    $this->repository->clearCache();
                }
            } catch (\Exception $cacheError) {
                // Cache error is not critical
            }
            
            if ($result) {
                return [
                    'success' => true,
                    'error' => null
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Paket silinirken bir hata oluştu: Repository false döndü.'
            ];
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageService::deletePackage - PDOException', [
                    'message' => $e->getMessage(),
                    'package_id' => $packageId
                ]);
            }
            return [
                'success' => false,
                'error' => 'Veritabanı hatası: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageService::deletePackage - Exception', [
                    'message' => $e->getMessage(),
                    'package_id' => $packageId,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [
                'success' => false,
                'error' => 'Paket silinirken hata oluştu: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get package permissions
     * @param string $packageId
     * @return array
     */
    public function getPackagePermissions(string $packageId): array {
        return $this->repository->getPackagePermissions($packageId);
    }
    
    /**
     * Get package permission keys
     * @param string $packageId
     * @return array
     */
    public function getPackagePermissionKeys(string $packageId): array {
        return $this->repository->getPackagePermissionKeys($packageId);
    }
    
    /**
     * Assign permission to package
     * @param string $packageId
     * @param string $permissionId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function assignPermissionToPackage(string $packageId, string $permissionId): array {
        $result = $this->repository->assignPermission($packageId, $permissionId);
        
        if ($result) {
            return [
                'success' => true,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Yetkilendirme atanırken bir hata oluştu.'
        ];
    }
    
    /**
     * Remove permission from package
     * @param string $packageId
     * @param string $permissionId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function removePermissionFromPackage(string $packageId, string $permissionId): array {
        $result = $this->repository->removePermission($packageId, $permissionId);
        
        if ($result) {
            return [
                'success' => true,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Yetkilendirme kaldırılırken bir hata oluştu.'
        ];
    }
    
    /**
     * Calculate discount percentage for a package and pricing type
     * Combines package discount_percentage and yearly discount (if applicable)
     * @param array $package Package data
     * @param string $pricingType one_time, monthly, yearly
     * @return float Discount percentage (0-100)
     */
    public function calculateDiscount(array $package, string $pricingType): float {
        // Not: price_yearly zaten aylık×12'ye göre indirimli olarak saklanır.
        // Eski implementasyon yıllık indirimi bir kez daha eklediği için ödeme
        // ekranında kullanıcıya çifte-indirim gösteriyordu (14.400 → 10.080 gibi).
        // Artık sadece paket seviyesindeki discount_percentage uygulanır;
        // yıllık "indirim" üst katmanda görsel olarak monthly*12 ile kıyaslanarak
        // gösterilebilir ama hesaplamaya girmez.
        return floatval($package['discount_percentage'] ?? 0);
    }

    /**
     * Görsel "yıllık vs aylık" karşılaştırma yüzdesi — sadece pazarlama için.
     * Gerçek fiyat hesaplamasına GİRMEZ.
     */
    public function calculateYearlyComparisonDiscount(array $package): float {
        $monthlyPrice = floatval($package['price_monthly'] ?? 0);
        $yearlyPrice = floatval($package['price_yearly'] ?? 0);
        if ($monthlyPrice <= 0 || $yearlyPrice <= 0) {
            return 0.0;
        }
        $saved = ($monthlyPrice * 12) - $yearlyPrice;
        if ($saved <= 0) {
            return 0.0;
        }
        return round($saved / ($monthlyPrice * 12) * 100, 2);
    }
    
    /**
     * Get discounted price for a package and pricing type
     * @param array $package Package data
     * @param string $pricingType one_time, monthly, yearly
     * @return float Discounted price
     */
    public function getDiscountedPrice(array $package, string $pricingType): float {
        $priceField = 'price_' . ($pricingType === 'one_time' ? 'one_time' : ($pricingType === 'monthly' ? 'monthly' : 'yearly'));
        $basePrice = floatval($package[$priceField] ?? 0);
        
        if ($basePrice <= 0) {
            return 0;
        }
        
        $discountPercent = $this->calculateDiscount($package, $pricingType);
        $discountAmount = $basePrice * ($discountPercent / 100);
        
        return $basePrice - $discountAmount;
    }
    
    /**
     * Format features JSON to array for display
     * @param string|null $features JSON string or array
     * @return array Features array
     */
    public function formatFeaturesForDisplay($features): array {
        if (empty($features)) {
            return [];
        }
        
        if (is_array($features)) {
            return $features;
        }
        
        if (is_string($features)) {
            $decoded = json_decode($features, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return [];
    }

    /**
     * Derive feature labels dynamically from package_permissions -> navigation_items.
     * Maps each assigned permission prefix to its navigation item's Turkish label.
     * Returns a deduplicated, sorted list of feature names for display (landing page, etc.)
     */
    public function getPackageFeaturesFromPermissions(string $packageId): array {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();

            $sql = "
                SELECT DISTINCT ni.label_tr, ni.nav_key, ni.parent_id
                FROM package_permissions pp
                INNER JOIN system_permissions sp ON pp.permission_id = sp.permission_id
                INNER JOIN navigation_items ni ON (
                    ni.permission_key = sp.permission_key
                    OR ni.permission_key = CONCAT(SUBSTRING_INDEX(sp.permission_key, '.', 1), '.view')
                    OR ni.permission_key LIKE CONCAT(SUBSTRING_INDEX(sp.permission_key, '.', 1), '.%')
                )
                WHERE pp.package_id = :pkg_id
                  AND ni.parent_id != 'ROOT'
                  AND ni.parent_id != 'SAAS_MANAGEMENT'
                  AND ni.is_active = 1
                ORDER BY ni.display_order, ni.label_tr
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['pkg_id' => $packageId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $features = [];
            $seen = [];
            foreach ($rows as $row) {
                $label = $row['label_tr'] ?? '';
                if (!empty($label) && !isset($seen[$label])) {
                    $features[] = $label;
                    $seen[$label] = true;
                }
            }

            return $features;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('PackageService::getPackageFeaturesFromPermissions failed', [
                    'package_id' => $packageId,
                    'error' => $e->getMessage()
                ]);
            }
            return $this->formatFeaturesForDisplay(null);
        }
    }
}
