<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\SubscriptionRepository;
use App\Repositories\PackageRepository;

class SubscriptionService extends BaseService {
    
    protected $packageRepository;
    
    public function __construct(SubscriptionRepository $repository, PackageRepository $packageRepository) {
        parent::__construct($repository);
        $this->packageRepository = $packageRepository;
    }
    
    /**
     * Create new subscription
     * @param string $customerId
     * @param string $packageId
     * @param string $pricingType one_time, monthly, yearly
     * @return array ['success' => bool, 'subscription_id' => string|null, 'error' => string|null]
     */
    public function createSubscription(string $customerId, string $packageId, string $pricingType): array {
        // Paket kontrolü
        $package = $this->packageRepository->findById($packageId);
        if (!$package) {
            return [
                'success' => false,
                'subscription_id' => null,
                'error' => 'Paket bulunamadı.'
            ];
        }
        
        if (!$package['is_active']) {
            return [
                'success' => false,
                'subscription_id' => null,
                'error' => 'Bu paket aktif değil.'
            ];
        }
        
        // Fiyatlandırma tipi kontrolü
        $priceField = 'price_' . ($pricingType === 'one_time' ? 'one_time' : ($pricingType === 'monthly' ? 'monthly' : 'yearly'));
        if (empty($package[$priceField]) || $package[$priceField] <= 0) {
            return [
                'success' => false,
                'subscription_id' => null,
                'error' => 'Bu paket için seçilen fiyatlandırma tipi mevcut değil.'
            ];
        }
        
        // CRITICAL FIX: SADECE ÖDEME BAŞARILI OLDUKTAN SONRA ESKİ PAKETİ İPTAL ET
        // Burada iptal ETMEYİN - activateSubscription'da yapılacak
        // Müşteri sadece paket sayfasını görüntülüyor olabilir
        // $activeSubscription = $this->repository->getActiveSubscription($customerId);
        // if ($activeSubscription) {
        //     $this->cancelSubscription($activeSubscription['subscription_id']);
        // }
        
        // Abonelik süresi hesapla
        $startDate = date('Y-m-d H:i:s');
        $endDate = null;
        
        if ($pricingType === 'monthly') {
            $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));
        } elseif ($pricingType === 'yearly') {
            $endDate = date('Y-m-d H:i:s', strtotime('+1 year'));
        } elseif ($pricingType === 'one_time') {
            if (isset($package['duration_days']) && $package['duration_days'] > 0) {
                $endDate = date('Y-m-d H:i:s', strtotime('+' . $package['duration_days'] . ' days'));
            } else {
                // Default to 1 year for one-time purchases
                $endDate = date('Y-m-d H:i:s', strtotime('+1 year'));
            }
        }
        
        require_once __DIR__ . '/../helpers/functions.php';
        
        // Ödeme ekranı ve iyzico ile aynı: indirimli tutar (discount_percentage)
        $packageService = \App\Core\DependencyFactory::getPackageService();
        $amount = (float) $packageService->getDiscountedPrice($package, $pricingType);
        if ($amount <= 0) {
            $amount = floatval($package[$priceField] ?? 0);
        }
        
        // Map pricing_type to billing_cycle for database
        $billingCycle = ($pricingType === 'one_time') ? 'monthly' : $pricingType; // one_time defaults to monthly for database enum
        
        // Canonical tenant column is `tenant_id`; legacy DBs may still carry
        // business_id / customer_id. DbSchema is the single source of truth.
        $hasTenantId   = \App\Core\DbSchema::hasColumn('subscriptions', 'tenant_id');
        $hasBusinessId = \App\Core\DbSchema::hasColumn('subscriptions', 'business_id');
        $hasCustomerId = \App\Core\DbSchema::hasColumn('subscriptions', 'customer_id');
        $hasStartDate          = \App\Core\DbSchema::hasColumn('subscriptions', 'start_date');
        $hasEndDate            = \App\Core\DbSchema::hasColumn('subscriptions', 'end_date');
        $hasCurrentPeriodStart = \App\Core\DbSchema::hasColumn('subscriptions', 'current_period_start');
        $hasCurrentPeriodEnd   = \App\Core\DbSchema::hasColumn('subscriptions', 'current_period_end');
        $hasIsTrial            = \App\Core\DbSchema::hasColumn('subscriptions', 'is_trial');
        $hasTrialConverted     = \App\Core\DbSchema::hasColumn('subscriptions', 'trial_converted');
        $hasTrialEndsAt        = \App\Core\DbSchema::hasColumn('subscriptions', 'trial_ends_at');
        $hasTrialStartedAt     = \App\Core\DbSchema::hasColumn('subscriptions', 'trial_started_at');

        // KRITIK: Checkout sırasında asla TRIAL satırını kaybetmeyelim.
        // Kullanıcı "Hemen Başla" deyip ödeme akışına girdiğinde trial'ı pending'e
        // çevirirsek, ödemeden vazgeçerse hem paket'i hem trial'ı kaybeder.
        // Strateji: Sadece eski "pending" satırları yeniden kullan/temizle;
        // AKTIF TRIAL satırına dokunma. Ödeme başarılı olunca activateSubscription
        // trial satırını iptal edecek.
        $allSubs = $this->repository->getCustomerSubscriptions($customerId);
        $pendingOrdered = [];
        foreach ($allSubs as $sub) {
            $st = $sub['status'] ?? '';
            if ($st === 'pending') {
                $pendingOrdered[] = $sub;
            }
        }

        $checkoutUpdate = [
            'package_id' => $packageId,
            'billing_cycle' => $billingCycle,
            'amount' => $amount,
            'currency' => 'TRY',
            'status' => 'pending',
        ];
        if ($hasCurrentPeriodStart) {
            $checkoutUpdate['current_period_start'] = $startDate;
        } elseif ($hasStartDate) {
            $checkoutUpdate['start_date'] = $startDate;
        }
        if ($hasCurrentPeriodEnd && $endDate) {
            $checkoutUpdate['current_period_end'] = $endDate;
        } elseif ($hasEndDate && $endDate) {
            $checkoutUpdate['end_date'] = $endDate;
        }
        if ($hasIsTrial) {
            $checkoutUpdate['is_trial'] = 0;
        }
        // trial_converted'e burada DOKUNMUYORUZ: ödeme bitmeden trial iptal
        // edilmemeli. activateSubscription ödeme success'te işaretleyecek.
        if ($hasTrialEndsAt) {
            $checkoutUpdate['trial_ends_at'] = null;
        }
        if ($hasTrialStartedAt) {
            $checkoutUpdate['trial_started_at'] = null;
        }

        $reusedId = null;
        if (!empty($pendingOrdered)) {
            $keep = $pendingOrdered[0];
            $reusedId = $keep['subscription_id'];
            $this->repository->update($reusedId, $checkoutUpdate);
            foreach ($pendingOrdered as $p) {
                if (($p['subscription_id'] ?? '') !== $reusedId) {
                    $this->markSubscriptionCancelledQuietly($p['subscription_id']);
                }
            }
        }

        if ($reusedId) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Subscription checkout reused existing row', [
                    'subscription_id' => $reusedId,
                    'customer_id' => $customerId,
                    'package_id' => $packageId,
                ]);
            }
            return [
                'success' => true,
                'subscription_id' => $reusedId,
                'error' => null,
            ];
        }

        $subscriptionData = [
            'subscription_id' => generateId('sub'),
            'package_id' => $packageId,
            'billing_cycle' => $billingCycle,
            'amount' => $amount,
            'currency' => 'TRY',
            'status' => 'pending', // Ödeme sonrası active olacak
        ];

        // Canonical tenant id first; legacy columns still set for rollback safety.
        if ($hasTenantId) {
            $subscriptionData['tenant_id'] = $customerId;
        }
        if ($hasCustomerId) {
            $subscriptionData['customer_id'] = $customerId;
        }
        if ($hasBusinessId) {
            $subscriptionData['business_id'] = $customerId;
        }

        // Set period dates based on available columns
        if ($hasCurrentPeriodStart) {
            $subscriptionData['current_period_start'] = $startDate;
        } elseif ($hasStartDate) {
            $subscriptionData['start_date'] = $startDate;
        }

        if ($hasCurrentPeriodEnd && $endDate) {
            $subscriptionData['current_period_end'] = $endDate;
        } elseif ($hasEndDate && $endDate) {
            $subscriptionData['end_date'] = $endDate;
        }

        $subscriptionId = $this->repository->create($subscriptionData);

        if ($subscriptionId) {
            // Checkout aşaması: trial aktifken iptal etme (ödeme success'te iptal edilecek)
            $this->cancelOtherActiveOrPendingSubscriptions($customerId, $subscriptionId, true);
            return [
                'success' => true,
                'subscription_id' => $subscriptionId,
                'error' => null
            ];
        }

        return [
            'success' => false,
            'subscription_id' => null,
            'error' => 'Abonelik oluşturulurken bir hata oluştu.'
        ];
    }

    /**
     * Süper admin tarafından oluşturulmuş özel ödeme bağlantısıyla
     * gelen, sabit süreli (ay) ve özel fiyatlı bir aboneliği aktif
     * olarak kayıt eder. Standart "pending -> active" akışını by-pass
     * eder çünkü ödeme zaten callback tarafında tahsil edilmiştir.
     *
     * @return array ['success' => bool, 'subscription_id' => string|null, 'error' => string|null]
     */
    public function createCustomSubscription(
        string $customerId,
        string $packageId,
        float $customPrice,
        int $durationMonths,
        string $currency = 'TRY'
    ): array {
        $package = $this->packageRepository->findById($packageId);
        if (!$package) {
            return [
                'success' => false,
                'subscription_id' => null,
                'error' => 'Paket bulunamadı.',
            ];
        }

        $durationMonths = max(1, (int)$durationMonths);
        $startDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime("+{$durationMonths} months"));

        require_once __DIR__ . '/../helpers/functions.php';

        $hasTenantId           = \App\Core\DbSchema::hasColumn('subscriptions', 'tenant_id');
        $hasBusinessId         = \App\Core\DbSchema::hasColumn('subscriptions', 'business_id');
        $hasCustomerId         = \App\Core\DbSchema::hasColumn('subscriptions', 'customer_id');
        $hasStartDate          = \App\Core\DbSchema::hasColumn('subscriptions', 'start_date');
        $hasEndDate            = \App\Core\DbSchema::hasColumn('subscriptions', 'end_date');
        $hasCurrentPeriodStart = \App\Core\DbSchema::hasColumn('subscriptions', 'current_period_start');
        $hasCurrentPeriodEnd   = \App\Core\DbSchema::hasColumn('subscriptions', 'current_period_end');
        $hasIsTrial            = \App\Core\DbSchema::hasColumn('subscriptions', 'is_trial');
        $hasTrialConverted     = \App\Core\DbSchema::hasColumn('subscriptions', 'trial_converted');
        $hasTrialEndsAt        = \App\Core\DbSchema::hasColumn('subscriptions', 'trial_ends_at');
        $hasTrialStartedAt     = \App\Core\DbSchema::hasColumn('subscriptions', 'trial_started_at');

        $subscriptionData = [
            'subscription_id' => generateId('sub'),
            'package_id'      => $packageId,
            // billing_cycle enum on this schema is monthly|yearly; a
            // multi-month custom plan is closest to "yearly" semantics.
            'billing_cycle'   => 'yearly',
            'amount'          => $customPrice,
            'currency'        => $currency,
            'status'          => 'active',
        ];

        if ($hasTenantId) {
            $subscriptionData['tenant_id'] = $customerId;
        }
        if ($hasCustomerId) {
            $subscriptionData['customer_id'] = $customerId;
        }
        if ($hasBusinessId) {
            $subscriptionData['business_id'] = $customerId;
        }

        if ($hasCurrentPeriodStart) {
            $subscriptionData['current_period_start'] = $startDate;
        } elseif ($hasStartDate) {
            $subscriptionData['start_date'] = $startDate;
        }

        if ($hasCurrentPeriodEnd) {
            $subscriptionData['current_period_end'] = $endDate;
        } elseif ($hasEndDate) {
            $subscriptionData['end_date'] = $endDate;
        }

        if ($hasIsTrial) {
            $subscriptionData['is_trial'] = 0;
        }
        if ($hasTrialConverted) {
            $subscriptionData['trial_converted'] = 1;
        }
        if ($hasTrialEndsAt) {
            $subscriptionData['trial_ends_at'] = null;
        }
        if ($hasTrialStartedAt) {
            $subscriptionData['trial_started_at'] = null;
        }

        try {
            $subscriptionId = $this->repository->create($subscriptionData);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SubscriptionService::createCustomSubscription insert failed', [
                    'customer_id' => $customerId,
                    'package_id'  => $packageId,
                    'error'       => $e->getMessage(),
                ]);
            }
            return [
                'success' => false,
                'subscription_id' => null,
                'error' => 'Abonelik oluşturulurken bir hata oluştu.',
            ];
        }

        if (!$subscriptionId) {
            return [
                'success' => false,
                'subscription_id' => null,
                'error' => 'Abonelik oluşturulamadı.',
            ];
        }

        $this->cancelOtherActiveOrPendingSubscriptions($customerId, $subscriptionId);

        return [
            'success'         => true,
            'subscription_id' => $subscriptionId,
            'error'           => null,
            'current_period_end' => $endDate,
        ];
    }

    /**
     * Aboneliği iptal et; işletme hesabını pasifleştirme (ödeme öncesi deneme / yinelenen taslak temizliği için).
     */
    private function markSubscriptionCancelledQuietly(string $subscriptionId): void {
        try {
            $this->repository->update($subscriptionId, [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('markSubscriptionCancelledQuietly failed', [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * İşletme başına aynı anda yalnızca bir aktif veya ödeme-bekleyen abonelik (deneme veya ücretli).
     * $keepSubscriptionId dışındaki tüm active/pending kayıtları işletmeyi pasifleştirmeden iptal eder.
     *
     * @param bool $keepActiveTrial true ise is_trial=1 AND status='active' olan satır korunur.
     *                              Checkout sırasında ödeme tamamlanmadan trial'ı kaybetmemek için.
     */
    public function cancelOtherActiveOrPendingSubscriptions(string $customerId, ?string $keepSubscriptionId, bool $keepActiveTrial = false): void {
        try {
            foreach ($this->repository->getCustomerSubscriptions($customerId) as $sub) {
                $sid = $sub['subscription_id'] ?? '';
                $st = strtolower($sub['status'] ?? '');
                if ($st !== 'active' && $st !== 'pending') {
                    continue;
                }
                if ($keepSubscriptionId !== null && $sid === $keepSubscriptionId) {
                    continue;
                }
                if ($keepActiveTrial && !empty($sub['is_trial']) && $st === 'active') {
                    continue;
                }
                $this->markSubscriptionCancelledQuietly($sid);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('cancelOtherActiveOrPendingSubscriptions failed', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    
    /**
     * Get customer's active subscription
     * @param string $customerId
     * @return array|null
     */
    public function getCustomerSubscription(string $customerId): ?array {
        try {
            $active = $this->repository->getActiveSubscription($customerId);
            if ($active) {
                return $active;
            }
            return $this->repository->getPendingSubscriptionForAccess($customerId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SubscriptionService::getCustomerSubscription error', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get all customer subscriptions
     * @param string $customerId
     * @return array
     */
    public function getCustomerSubscriptions(string $customerId): array {
        return $this->repository->getCustomerSubscriptions($customerId);
    }
    
    /**
     * Get subscription by ID
     * @param string $subscriptionId
     * @return array|null
     */
    public function getSubscriptionById(string $subscriptionId): ?array {
        return $this->repository->getSubscriptionWithPackage($subscriptionId);
    }
    
    /**
     * Activate subscription
     * @param string $subscriptionId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function activateSubscription(string $subscriptionId): array {
        $subscription = $this->repository->findById($subscriptionId);
        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'Abonelik bulunamadı.'
            ];
        }
        
        $db = \App\Core\DependencyFactory::getDatabase();
        
        try {
            $db->beginTransaction();
            
            $customerId = $subscription['tenant_id'] ?? $subscription['customer_id'] ?? $subscription['business_id'] ?? null;
            if ($customerId) {
                // Paralel aktif deneme + ücretli veya çift pending olmasın; işletmeyi burada kapatma
                $this->cancelOtherActiveOrPendingSubscriptions($customerId, $subscriptionId);
            }
            
            $package = $this->packageRepository->findById($subscription['package_id']);
            $startDate = !empty($subscription['current_period_start']) ? $subscription['current_period_start'] : date('Y-m-d H:i:s');
            
            $billingCycle = $subscription['billing_cycle'] ?? 'monthly';
            if ($billingCycle === 'monthly') {
                $endDate = date('Y-m-d H:i:s', strtotime('+1 month', strtotime($startDate)));
            } elseif ($billingCycle === 'yearly') {
                $endDate = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($startDate)));
            } else {
                $endDate = null;
            }
            
            $updateData = [
                'status' => 'active',
                'current_period_start' => $startDate,
                'current_period_end' => $endDate
            ];
            
            $result = $this->repository->update($subscriptionId, $updateData);
            
            if ($result) {
                // Reactivate the business when subscription is activated
                $customerId = $subscription['tenant_id'] ?? $subscription['customer_id'] ?? $subscription['business_id'] ?? null;
                if ($customerId) {
                    try {
                        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                        $customerRepo->update($customerId, [
                            'is_active' => 1,
                            'status' => 'active'
                        ]);
                    } catch (\Exception $e) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('Could not reactivate business on subscription activate', [
                                'subscription_id' => $subscriptionId,
                                'customer_id' => $customerId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    // Paket aktifleştiğinde SADECE işletme sahibi kullanıcısına
                    // paketin "owner role"ünü ata. Paket rolü tanımlanmamışsa
                    // backward-compat olarak BUSINESS_OWNER / BUSINESS_MANAGER'a düş.
                    //
                    // CRITICAL: Eski sürüm burada
                    //   UPDATE users ... WHERE tenant_id = ? AND r.role_code IN ('TRIAL','BUSINESS_OWNER')
                    // çalıştırıyordu. İşletmedeki tüm TRIAL/BUSINESS_OWNER kullanıcıları
                    // paket sahibi rolüne çeviriyor ve personel rollerini bozuyordu.
                    if (empty($subscription['is_trial'])) {
                        try {
                            $db2 = \App\Core\DependencyFactory::getDatabase();
                            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
                            $roleService = \App\Core\DependencyFactory::getRoleService();

                            $ownerRoleId = null;
                            $ownerRoleCode = null;

                            // 1) Paketin owner rolü varsa onu kullan
                            if (method_exists($packageRepo, 'getPackageOwnerRoleId')) {
                                $ownerRoleId = $packageRepo->getPackageOwnerRoleId($subscription['package_id']);
                            }

                            // 2) Yoksa BUSINESS_OWNER → BUSINESS_MANAGER fallback
                            if (!$ownerRoleId) {
                                $fallback = $roleService->getByRoleCode('BUSINESS_OWNER')
                                         ?: $roleService->getByRoleCode('BUSINESS_MANAGER');
                                if ($fallback && !empty($fallback['role_id'])) {
                                    $ownerRoleId = $fallback['role_id'];
                                    $ownerRoleCode = $fallback['role_code'] ?? null;
                                }
                            } else {
                                // role_code çek
                                $codeStmt = $db2->prepare("SELECT role_code FROM roles WHERE role_id = ? LIMIT 1");
                                $codeStmt->execute([$ownerRoleId]);
                                $codeRow = $codeStmt->fetch(\PDO::FETCH_ASSOC);
                                if ($codeRow) $ownerRoleCode = $codeRow['role_code'];
                            }

                            if ($ownerRoleId && $ownerRoleCode) {
                                require_once __DIR__ . '/BusinessOwnerResolver.php';
                                $ownerResolver = new \App\Services\BusinessOwnerResolver($db2);
                                $ownerUserId = $ownerResolver->resolve((string)$customerId);

                                if (!empty($ownerUserId)) {
                                    $db2->prepare(
                                        "UPDATE users
                                         SET role = ?, role_id = ?
                                         WHERE user_id = ? LIMIT 1"
                                    )->execute([$ownerRoleCode, $ownerRoleId, $ownerUserId]);

                                    if (class_exists('\\App\\Core\\Logger')) {
                                        \App\Core\Logger::info('activateSubscription: owner role assigned', [
                                            'customer_id'   => $customerId,
                                            'owner_user_id' => $ownerUserId,
                                            'role_code'     => $ownerRoleCode,
                                        ]);
                                    }
                                } elseif (class_exists('\\App\\Core\\Logger')) {
                                    \App\Core\Logger::warning('activateSubscription: owner not resolvable, skipping role assignment', [
                                        'customer_id' => $customerId,
                                    ]);
                                }
                            }
                        } catch (\Exception $re) {
                            if (class_exists('\\App\\Core\\Logger')) {
                                \App\Core\Logger::warning('Could not assign package owner role on subscription activate', [
                                    'customer_id' => $customerId,
                                    'error' => $re->getMessage()
                                ]);
                            }
                        }
                    }
                }

                $db->commit();
                return ['success' => true, 'error' => null];
            }
            
            $db->rollBack();
            return ['success' => false, 'error' => 'Abonelik aktifleştirilirken bir hata oluştu.'];
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'error' => 'Hata: ' . $e->getMessage()];
        }
    }
    
    /**
     * Cancel subscription and deactivate the associated business
     * @param string $subscriptionId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function cancelSubscription(string $subscriptionId): array {
        $subscription = $this->repository->findById($subscriptionId);
        
        $result = $this->repository->update($subscriptionId, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            if ($subscription) {
                $customerId = $subscription['tenant_id'] ?? $subscription['customer_id'] ?? $subscription['business_id'] ?? null;
                if ($customerId) {
                    // Aynı işletmede kalan aktif deneme / bekleyen ödeme satırları tutarsız (iptal + aktif deneme birlikte)
                    $this->cancelOtherActiveOrPendingSubscriptions($customerId, null);
                    try {
                        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
                        $customerRepo->update($customerId, [
                            'is_active' => 0,
                            'status' => 'inactive'
                        ]);
                    } catch (\Exception $e) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('Could not deactivate business on subscription cancel', [
                                'subscription_id' => $subscriptionId,
                                'customer_id' => $customerId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

            return [
                'success' => true,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Abonelik iptal edilirken bir hata oluştu.'
        ];
    }
    
    /**
     * Renew subscription
     * @param string $subscriptionId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function renewSubscription(string $subscriptionId): array {
        $subscription = $this->repository->getSubscriptionWithPackage($subscriptionId);
        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'Abonelik bulunamadı.'
            ];
        }
        
        if ($subscription['status'] !== 'active') {
            return [
                'success' => false,
                'error' => 'Sadece aktif abonelikler yenilenebilir.'
            ];
        }
        
        // Yeni bitiş tarihi hesapla
        $currentEndDate = $subscription['current_period_end'] ? strtotime($subscription['current_period_end']) : time();
        $newEndDate = null;
        $billingCycle = $subscription['billing_cycle'] ?? 'monthly';
        
        if ($billingCycle === 'monthly') {
            $newEndDate = date('Y-m-d H:i:s', strtotime('+1 month', $currentEndDate));
        } elseif ($billingCycle === 'yearly') {
            $newEndDate = date('Y-m-d H:i:s', strtotime('+1 year', $currentEndDate));
        }
        
        $result = $this->repository->update($subscriptionId, [
            'current_period_end' => $newEndDate
        ]);
        
        if ($result) {
            return [
                'success' => true,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Abonelik yenilenirken bir hata oluştu.'
        ];
    }
    
    /**
     * Check and update expired subscriptions
     * @return int Number of expired subscriptions updated
     */
    public function checkSubscriptionExpiry(): int {
        $expiredSubscriptions = $this->repository->getExpiredSubscriptions();
        $count = 0;
        
        foreach ($expiredSubscriptions as $subscription) {
            // Süresi dolmuş, expired yap
            $this->repository->update($subscription['subscription_id'], ['status' => 'expired']);
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Get subscription permissions
     * @param string $subscriptionId
     * @return array
     */
    public function getSubscriptionPermissions(string $subscriptionId): array {
        try {
            $subscription = $this->repository->getSubscriptionWithPackage($subscriptionId);
            if (!$subscription) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('SubscriptionService::getSubscriptionPermissions - Subscription not found', [
                        'subscription_id' => $subscriptionId
                    ]);
                }
                return [];
            }
            
            $packageId = $subscription['package_id'] ?? null;
            if (!$packageId) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('SubscriptionService::getSubscriptionPermissions - No package_id in subscription', [
                        'subscription_id' => $subscriptionId,
                        'subscription' => $subscription
                    ]);
                }
                return [];
            }
            
            return $this->packageRepository->getPackagePermissionKeys($packageId);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SubscriptionService::getSubscriptionPermissions error', [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Upgrade subscription to a new package
     * @param string $subscriptionId
     * @param string $newPackageId
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function upgradeSubscription(string $subscriptionId, string $newPackageId): array {
        $subscription = $this->repository->findById($subscriptionId);
        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'Abonelik bulunamadı.'
            ];
        }
        
        $newPackage = $this->packageRepository->findById($newPackageId);
        if (!$newPackage) {
            return [
                'success' => false,
                'error' => 'Yeni paket bulunamadı.'
            ];
        }
        
        if (!$newPackage['is_active']) {
            return [
                'success' => false,
                'error' => 'Yeni paket aktif değil.'
            ];
        }
        
        // Update subscription with new package
        $result = $this->repository->update($subscriptionId, [
            'package_id' => $newPackageId
        ]);
        
        if ($result) {
            return [
                'success' => true,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Abonelik yükseltilirken bir hata oluştu.'
        ];
    }
}
