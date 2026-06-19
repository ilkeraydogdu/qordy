<?php
namespace App\Services;

use App\Core\DependencyFactory;
use App\Core\Logger;

class TrialService {
    
    private $db;
    private $subscriptionService;
    private $packageService;
    private $systemSettingsService;
    
    public function __construct() {
        $this->db = DependencyFactory::getDatabase();
        $this->subscriptionService = DependencyFactory::getSubscriptionService();
        $this->packageService = DependencyFactory::getPackageService();
        $this->systemSettingsService = DependencyFactory::getSystemSettingsService();
    }
    
    /**
     * Trial ayarlarini oku
     */
    public function getTrialSettings(): array {
        try {
            // grace_period_days opsiyonel: eski kurulumlarda henüz migrate edilmemiş olabilir.
            $hasGrace = \App\Core\DbSchema::hasColumn('system_settings', 'grace_period_days');
            $graceSelect = $hasGrace ? ', grace_period_days' : '';
            $stmt = $this->db->query("SELECT
                trial_enabled, trial_duration_days, trial_package_id,
                trial_max_products, trial_max_tables, trial_max_staff,
                trial_max_categories, trial_features{$graceSelect}
                FROM system_settings LIMIT 1");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return $this->getDefaultSettings();
            }

            $row['trial_features'] = !empty($row['trial_features'])
                ? json_decode($row['trial_features'], true)
                : $this->getDefaultFeatures();

            if (!isset($row['grace_period_days']) || $row['grace_period_days'] === null) {
                $row['grace_period_days'] = 7;
            }

            return $row;
        } catch (\Exception $e) {
            Logger::error('TrialService::getTrialSettings error', ['error' => $e->getMessage()]);
            return $this->getDefaultSettings();
        }
    }

    /**
     * Sistem ayarından grace period gün sayısı (varsayılan 7).
     */
    public function getGracePeriodDays(): int {
        $settings = $this->getTrialSettings();
        return max(0, intval($settings['grace_period_days'] ?? 7));
    }

    /**
     * Grace periyodu içinde mail gönderilecek günler.
     * system_settings.grace_reminder_days JSON array olarak tanımlanabilir
     * (örn. "[1,3,5,6,7]"). Tanımlı değilse makul bir varsayılan set kullanılır.
     *
     * @return int[]
     */
    public function getGraceReminderDays(): array {
        $default = [1, 3, 5, 6, 7];
        try {
            if (!\App\Core\DbSchema::hasColumn('system_settings', 'grace_reminder_days')) return $default;
            $row = $this->db->query("SELECT grace_reminder_days FROM system_settings LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            if (!$row || empty($row['grace_reminder_days'])) return $default;
            $parsed = json_decode($row['grace_reminder_days'], true);
            if (!is_array($parsed) || empty($parsed)) return $default;
            $days = array_values(array_unique(array_map('intval', $parsed)));
            sort($days);
            return array_values(array_filter($days, fn($d) => $d >= 1 && $d <= 31));
        } catch (\Throwable $e) {
            return $default;
        }
    }
    
    /**
     * Trial ayarlarini guncelle
     */
    public function updateTrialSettings(array $settings): bool {
        try {
            $allowed = [
                'trial_enabled', 'trial_duration_days', 'trial_package_id',
                'trial_max_products', 'trial_max_tables', 'trial_max_staff',
                'trial_max_categories', 'trial_features'
            ];
            
            $sets = [];
            $params = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $settings)) {
                    $value = $settings[$key];
                    if ($key === 'trial_features' && is_array($value)) {
                        $value = json_encode($value);
                    }
                    $sets[] = "$key = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($sets)) return false;
            
            $sql = "UPDATE system_settings SET " . implode(', ', $sets) . " LIMIT 1";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            Logger::error('TrialService::updateTrialSettings error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Column on `subscriptions` that stores the tenant/business id.
     *
     * IMPORTANT: this MUST match the priority used by
     * SubscriptionRepository::subscriptionCustomerIdColumn(), otherwise
     * a subscription row created by this service can become invisible
     * to SubscriptionService::getCustomerSubscription() (or vice-versa)
     * when a schema has multiple candidate columns and only one of
     * them happens to be populated on a given row. That mismatch is
     * what produced the "admin sees QORDY PRO PLUS active / user sees
     * Paket Satın Al" bug we were chasing.
     */
    private function getBusinessIdColumn(): string {
        return \App\Core\DbSchema::pickTenantColumn('subscriptions') ?? 'tenant_id';
    }
    
    /**
     * Yeni kayit olan kullaniciya otomatik trial abonelik olustur
     */
    public function createTrialSubscription(string $customerId): array {
        try {
            $settings = $this->getTrialSettings();
            
            if (!$settings['trial_enabled']) {
                return ['success' => false, 'error' => 'Trial sistemi devre disi.'];
            }
            
            if ($this->hasUsedTrial($customerId)) {
                return ['success' => false, 'error' => 'Bu hesap daha once trial kullanmis.'];
            }
            
            $durationDays = intval($settings['trial_duration_days'] ?: 7);
            $trialPackageId = $settings['trial_package_id'] ?? '';
            
            if (empty($trialPackageId)) {
                $trialPackageId = $this->getFirstActivePackageId();
            }
            
            if (empty($trialPackageId)) {
                return ['success' => false, 'error' => 'Trial paketi ayarlanmamis.'];
            }
            
            require_once __DIR__ . '/../helpers/functions.php';
            
            $now = date('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
            $subscriptionId = generateId('sub');
            
            // Build a superset payload; DbSchema::filterToColumns() will drop
            // any keys that don't exist on the live schema. One-liner instead
            // of 12 manual `if (hasColumn(...))` checks.
            $raw = [
                'subscription_id'      => $subscriptionId,
                'package_id'           => $trialPackageId,
                'status'               => 'active',
                'is_trial'             => 1,
                'trial_started_at'     => $now,
                'trial_ends_at'        => $endDate,
                'trial_converted'      => 0,
                'tenant_id'            => $customerId,
                'business_id'          => $customerId,
                'customer_id'          => $customerId,
                'billing_cycle'        => 'monthly',
                'amount'               => 0,
                'currency'             => 'TRY',
                'pricing_type'         => 'monthly',
                'current_period_start' => $now,
                'current_period_end'   => $endDate,
                'start_date'           => $now,
                'end_date'             => $endDate,
            ];
            $data = \App\Core\DbSchema::filterToColumns('subscriptions', $raw);

            $cols = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO subscriptions ($cols) VALUES ($placeholders)";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            if ($result) {
                try {
                    \App\Core\DependencyFactory::getSubscriptionService()
                        ->cancelOtherActiveOrPendingSubscriptions($customerId, $subscriptionId);
                } catch (\Exception $e) {
                    Logger::warning('Trial created but parallel subscription cleanup failed', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);
                }
                Logger::info('Trial subscription created', [
                    'customer_id' => $customerId,
                    'subscription_id' => $subscriptionId,
                    'trial_ends_at' => $endDate,
                    'package_id' => $trialPackageId,
                ]);
                
                return [
                    'success' => true,
                    'subscription_id' => $subscriptionId,
                    'trial_ends_at' => $endDate,
                    'duration_days' => $durationDays,
                ];
            }
            
            return ['success' => false, 'error' => 'Trial abonelik olusturulamadi.'];
            
        } catch (\Exception $e) {
            Logger::error('TrialService::createTrialSubscription error', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Bir hata olustu: ' . $e->getMessage()];
        }
    }
    
    /**
     * Kullanicinin aktif trial'i var mi?
     */
    public function getActiveTrialSubscription(string $customerId): ?array {
        try {
            $bizCol = $this->getBusinessIdColumn();
            $stmt = $this->db->prepare("
                SELECT s.*, p.name as package_name, p.features as package_features
                FROM subscriptions s
                LEFT JOIN packages p ON s.package_id = p.package_id
                WHERE s.{$bizCol} = ?
                AND s.is_trial = 1
                AND s.status = 'active'
                ORDER BY s.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            Logger::error('TrialService::getActiveTrialSubscription error', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Trial aktif mi?
     */
    public function isTrialActive(string $customerId): bool {
        $trial = $this->getActiveTrialSubscription($customerId);
        if (!$trial) return false;
        
        $endsAt = $trial['trial_ends_at'] ?? $trial['current_period_end'] ?? null;
        if (!$endsAt) return false;
        
        return strtotime($endsAt) > time();
    }
    
    /**
     * Kalan gun sayisi
     */
    public function getTrialRemainingDays(string $customerId): int {
        $trial = $this->getActiveTrialSubscription($customerId);
        if (!$trial) return 0;
        
        $endsAt = $trial['trial_ends_at'] ?? $trial['current_period_end'] ?? null;
        if (!$endsAt) return 0;
        
        $remaining = (strtotime($endsAt) - time()) / 86400;
        return max(0, (int)ceil($remaining));
    }
    
    /**
     * Trial bilgilerini getir
     */
    public function getTrialInfo(string $customerId): ?array {
        $trial = $this->getActiveTrialSubscription($customerId);
        if (!$trial) {
            $expired = $this->getExpiredTrial($customerId);
            if ($expired) {
                return [
                    'status' => 'expired',
                    'subscription' => $expired,
                    'remaining_days' => 0,
                    'is_active' => false,
                    'started_at' => $expired['trial_started_at'],
                    'ends_at' => $expired['trial_ends_at'] ?? $expired['current_period_end'] ?? null,
                ];
            }
            return null;
        }
        
        $remainingDays = $this->getTrialRemainingDays($customerId);
        $endsAt = $trial['trial_ends_at'] ?? $trial['current_period_end'] ?? null;
        
        return [
            'status' => $remainingDays > 0 ? 'active' : 'expired',
            'subscription' => $trial,
            'remaining_days' => $remainingDays,
            'is_active' => $remainingDays > 0,
            'started_at' => $trial['trial_started_at'],
            'ends_at' => $endsAt,
            'package_name' => $trial['package_name'] ?? 'Deneme',
        ];
    }
    
    /**
     * Suresi dolmus trial'i bul
     */
    private function getExpiredTrial(string $customerId): ?array {
        try {
            $bizCol = $this->getBusinessIdColumn();
            $stmt = $this->db->prepare("
                SELECT s.*
                FROM subscriptions s
                WHERE s.{$bizCol} = ?
                AND s.is_trial = 1
                AND (s.status = 'expired' OR s.status = 'cancelled')
                ORDER BY s.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Daha once trial kullanmis mi?
     */
    public function hasUsedTrial(string $customerId): bool {
        try {
            $bizCol = $this->getBusinessIdColumn();
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM subscriptions
                WHERE {$bizCol} = ?
                AND (
                    is_trial = 1
                    OR COALESCE(trial_converted, 0) = 1
                    OR trial_started_at IS NOT NULL
                )
            ");
            $stmt->execute([$customerId]);
            return $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            try {
                $bizCol = $this->getBusinessIdColumn();
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM subscriptions
                    WHERE {$bizCol} = ?
                    AND is_trial = 1
                ");
                $stmt->execute([$customerId]);
                return $stmt->fetchColumn() > 0;
            } catch (\Exception $e2) {
                return false;
            }
        }
    }
    
    /**
     * Kullanicinin herhangi bir aktif aboneligi (trial veya gercek) var mi?
     */
    public function hasAnyActiveSubscription(string $customerId): bool {
        try {
            $bizCol = $this->getBusinessIdColumn();
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM subscriptions
                WHERE {$bizCol} = ?
                AND status = 'active'
            ");
            $stmt->execute([$customerId]);
            return $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Kullanicinin gercek (trial olmayan) aktif aboneligi var mi?
     */
    public function hasPaidSubscription(string $customerId): bool {
        try {
            $bizCol = $this->getBusinessIdColumn();
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM subscriptions
                WHERE {$bizCol} = ?
                AND status = 'active'
                AND is_trial = 0
            ");
            $stmt->execute([$customerId]);
            return $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Trial'i expire et
     */
    public function expireTrial(string $subscriptionId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET status = 'expired' 
                WHERE subscription_id = ? AND is_trial = 1
            ");
            return $stmt->execute([$subscriptionId]);
        } catch (\Exception $e) {
            Logger::error('TrialService::expireTrial error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Trial'dan odemeye gecis
     */
    public function convertTrialToSubscription(string $customerId, string $subscriptionId): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET trial_converted = 1 
                WHERE subscription_id = ? AND is_trial = 1
            ");
            $stmt->execute([$subscriptionId]);
            
            Logger::info('Trial converted to paid', [
                'customer_id' => $customerId,
                'trial_subscription_id' => $subscriptionId,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error('TrialService::convertTrialToSubscription error', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Trial'i uzat (admin islemi)
     */
    public function extendTrial(string $subscriptionId, int $extraDays): array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE subscription_id = ? AND is_trial = 1");
            $stmt->execute([$subscriptionId]);
            $trial = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$trial) {
                return ['success' => false, 'error' => 'Trial abonelik bulunamadi.'];
            }
            
            $currentEnd = $trial['trial_ends_at'] ?? $trial['current_period_end'] ?? null;
            $baseTime = $currentEnd ? max(strtotime($currentEnd), time()) : time();
            $newEnd = date('Y-m-d H:i:s', strtotime("+{$extraDays} days", $baseTime));
            
            $updateSql = "UPDATE subscriptions SET trial_ends_at = ?, status = 'active'";
            $params = [$newEnd];
            
            $hasCol = fn(string $col): bool => \App\Core\DbSchema::hasColumn('subscriptions', $col);

            if ($hasCol('current_period_end')) {
                $updateSql .= ", current_period_end = ?";
                $params[] = $newEnd;
            }
            if ($hasCol('end_date')) {
                $updateSql .= ", end_date = ?";
                $params[] = $newEnd;
            }
            
            $updateSql .= " WHERE subscription_id = ?";
            $params[] = $subscriptionId;
            
            $stmt = $this->db->prepare($updateSql);
            $stmt->execute($params);
            
            Logger::info('Trial extended', [
                'subscription_id' => $subscriptionId,
                'extra_days' => $extraDays,
                'new_end' => $newEnd,
            ]);
            
            return ['success' => true, 'new_end_date' => $newEnd];
            
        } catch (\Exception $e) {
            Logger::error('TrialService::extendTrial error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Toplu trial süresi kontrolü (cron için).
     *
     * Süresi dolmuş aktif trial'ları `grace_period` durumuna taşır ve
     * `grace_ends_at` tarihini (trial_ends_at + graceDays) olarak atar.
     * Eski davranış (direkt expired) yerine artık grace üzerinden geçilir.
     */
    public function checkAndExpireTrials(): int {
        try {
            $now = date('Y-m-d H:i:s');
            $graceDays = $this->getGracePeriodDays();

            $hasGraceCol = $this->subscriptionHasColumn('grace_ends_at');
            $hasGraceStatus = $this->statusEnumHas('grace_period');

            // Migrasyon çalıştırılmamış kurulumlarda backward-compat:
            // sadece expired'a düşür (eski davranış).
            if (!$hasGraceCol || !$hasGraceStatus) {
                $stmt = $this->db->prepare("
                    UPDATE subscriptions
                    SET status = 'expired'
                    WHERE is_trial = 1
                    AND status = 'active'
                    AND (
                        (trial_ends_at IS NOT NULL AND trial_ends_at <= ?)
                        OR (trial_ends_at IS NULL AND current_period_end IS NOT NULL AND current_period_end <= ?)
                    )
                ");
                $stmt->execute([$now, $now]);
                $count = $stmt->rowCount();
                if ($count > 0) {
                    Logger::info("Expired $count trial subscriptions (legacy path, grace column missing)");
                }
                return $count;
            }

            // Normal akış: trial bitince grace_period'a al, grace_ends_at hesapla.
            $stmt = $this->db->prepare("
                UPDATE subscriptions
                SET status = 'grace_period',
                    grace_ends_at = DATE_ADD(
                        COALESCE(trial_ends_at, current_period_end, ?),
                        INTERVAL ? DAY
                    )
                WHERE is_trial = 1
                AND status = 'active'
                AND (
                    (trial_ends_at IS NOT NULL AND trial_ends_at <= ?)
                    OR (trial_ends_at IS NULL AND current_period_end IS NOT NULL AND current_period_end <= ?)
                )
            ");
            $stmt->execute([$now, $graceDays, $now, $now]);
            $count = $stmt->rowCount();

            if ($count > 0) {
                Logger::info("Moved $count trial subscriptions to grace_period (grace={$graceDays}d)");
            }

            return $count;
        } catch (\Exception $e) {
            Logger::error('TrialService::checkAndExpireTrials error', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Grace period'u bitmiş abonelikleri suspended'a alır (cron için).
     */
    public function checkAndSuspendGraceExpired(): int {
        try {
            $hasGraceCol = $this->subscriptionHasColumn('grace_ends_at');
            $hasSuspended = $this->statusEnumHas('suspended');
            if (!$hasGraceCol || !$hasSuspended) {
                return 0; // migrasyon çalıştırılmamış
            }

            $now = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("
                UPDATE subscriptions
                SET status = 'suspended'
                WHERE status = 'grace_period'
                AND grace_ends_at IS NOT NULL
                AND grace_ends_at <= ?
            ");
            $stmt->execute([$now]);
            $count = $stmt->rowCount();

            if ($count > 0) {
                Logger::info("Suspended $count subscriptions whose grace period expired");
            }
            return $count;
        } catch (\Exception $e) {
            Logger::error('TrialService::checkAndSuspendGraceExpired error', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * subscriptions tablosunda verilen kolon var mı?
     */
    private function subscriptionHasColumn(string $col): bool {
        return \App\Core\DbSchema::hasColumn('subscriptions', $col);
    }

    /**
     * subscriptions.status ENUM'unda verilen değer destekleniyor mu?
     */
    private function statusEnumHas(string $value): bool {
        try {
            $meta = \App\Core\DbSchema::columnMeta('subscriptions', 'status');
            $type = strtolower((string)($meta['Type'] ?? ''));
            return strpos($type, "'" . strtolower($value) . "'") !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * İşletmenin abonelik fazını ve kalan gün bilgisini döndürür.
     * Mobil /subscription/status endpoint'i bunu kullanır.
     *
     * Dönüş:
     *   phase: 'trial'|'active'|'grace'|'suspended'|'expired'|'none'
     *   daysLeft: trial için kalan gün, active için kalan gün (end_date varsa)
     *   graceDaysLeft: grace fazındaki kalan gün
     *   readOnly: true ise mobil mutasyonları engellemeli
     */
    public function getSubscriptionPhase(string $customerId): array {
        try {
            $bizCol = $this->getBusinessIdColumn();
            $stmt = $this->db->prepare("
                SELECT s.*, p.name as package_name
                FROM subscriptions s
                LEFT JOIN packages p ON s.package_id = p.package_id
                WHERE s.{$bizCol} = ?
                AND s.status IN ('active', 'grace_period', 'suspended', 'expired', 'pending')
                ORDER BY FIELD(s.status,'active','grace_period','suspended','pending','expired'),
                         s.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            $sub = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$sub) {
                return [
                    'phase' => 'none',
                    'daysLeft' => 0,
                    'graceDaysLeft' => 0,
                    'readOnly' => false,
                    'subscription' => null,
                ];
            }

            $now = time();
            $trialEnd = $sub['trial_ends_at'] ?? null;
            $periodEnd = $sub['current_period_end'] ?? ($sub['end_date'] ?? null);
            $graceEnd = $sub['grace_ends_at'] ?? null;
            $status = $sub['status'];
            $isTrial = (int)($sub['is_trial'] ?? 0) === 1;

            $daysLeft = 0;
            $graceDaysLeft = 0;
            $phase = 'none';
            $readOnly = false;

            if ($status === 'active') {
                if ($isTrial && $trialEnd) {
                    $phase = 'trial';
                    $daysLeft = max(0, (int)ceil((strtotime($trialEnd) - $now) / 86400));
                } else {
                    $phase = 'active';
                    if ($periodEnd) {
                        $daysLeft = max(0, (int)ceil((strtotime($periodEnd) - $now) / 86400));
                    }
                }
            } elseif ($status === 'grace_period') {
                $phase = 'grace';
                $readOnly = true;
                if ($graceEnd) {
                    $graceDaysLeft = max(0, (int)ceil((strtotime($graceEnd) - $now) / 86400));
                }
            } elseif ($status === 'suspended') {
                $phase = 'suspended';
                $readOnly = true;
            } elseif ($status === 'expired') {
                $phase = 'expired';
                $readOnly = true;
            } elseif ($status === 'pending') {
                $phase = 'pending';
            }

            return [
                'phase' => $phase,
                'daysLeft' => $daysLeft,
                'graceDaysLeft' => $graceDaysLeft,
                'readOnly' => $readOnly,
                'trial_ends_at' => $trialEnd,
                'current_period_end' => $periodEnd,
                'grace_ends_at' => $graceEnd,
                'status' => $status,
                'is_trial' => $isTrial,
                'package_name' => $sub['package_name'] ?? null,
                'subscription_id' => $sub['subscription_id'] ?? null,
            ];
        } catch (\Exception $e) {
            Logger::error('TrialService::getSubscriptionPhase error', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            return [
                'phase' => 'none',
                'daysLeft' => 0,
                'graceDaysLeft' => 0,
                'readOnly' => false,
                'subscription' => null,
            ];
        }
    }
    
    /**
     * Determine whether staff login should be blocked because the
     * business subscription is suspended / expired (i.e. payment
     * problem). Returns `['suspended' => bool, 'reason' => string,
     * 'phase' => string]`.
     *
     * The result is intentionally boolean-first so the caller can
     * gate session creation without having to reason about phase
     * semantics directly.
     */
    public function isBusinessSuspendedForStaff(string $customerId): array {
        $phase = $this->getSubscriptionPhase($customerId);
        $current = $phase['phase'] ?? 'none';

        // "suspended" and "expired" are the unrecoverable payment
        // states where staff must be blocked outright. Grace is a
        // read-only phase but still operational, so we let the
        // existing TrialMiddleware/ReadonlyMiddleware handle that.
        if (in_array($current, ['suspended', 'expired'], true)) {
            return [
                'suspended' => true,
                'phase'     => $current,
                'reason'    => $current === 'expired'
                    ? 'İşletme aboneliğinin süresi dolmuş. Paket yenilenmeden giriş yapılamaz.'
                    : 'İşletme hesabı ödeme nedeniyle geçici olarak askıya alındı. Lütfen işletme yöneticinizle iletişime geçin.',
            ];
        }

        return [
            'suspended' => false,
            'phase'     => $current,
            'reason'    => '',
        ];
    }

    /**
     * Grace period'da (deneme bitti, askıya alınmadı) olan aboneleri döndürür.
     * `grace_day`   : bekleme periyodunda kaçıncı gün (1..7)
     * `grace_days_left` : askıya alınmasına kalan tam gün
     * `subdomain`   : müşteri subdomain'i (packages list linki için)
     *
     * Cron'dan grace-reminder e-postası gönderen akış tarafından kullanılır.
     *
     * @param array|null $graceDaysFilter ör. [1,3,5,7] — sadece bu günler döner.
     *                                    null ise tüm grace aboneleri döner.
     * @return array
     */
    public function getGraceSubscribers(?array $graceDaysFilter = null): array {
        try {
            $hasGraceStatus = $this->statusEnumHas('grace_period');
            if (!$hasGraceStatus) {
                return [];
            }
            $bizCol = $this->getBusinessIdColumn();
            $hasSubdomain = \App\Core\DbSchema::hasColumn('customers', 'subdomain');

            $subdomainSelect = $hasSubdomain ? ', c.subdomain' : '';

            $stmt = $this->db->prepare("
                SELECT s.subscription_id, s.{$bizCol} AS business_id, s.trial_ends_at,
                       s.grace_ends_at, s.status,
                       c.customer_id, c.email, c.first_name, c.last_name, c.company_name
                       {$subdomainSelect}
                FROM subscriptions s
                LEFT JOIN customers c ON s.{$bizCol} = c.customer_id
                WHERE s.is_trial = 1
                  AND s.status = 'grace_period'
                  AND s.trial_ends_at IS NOT NULL
                ORDER BY s.trial_ends_at ASC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $graceDays = $this->getGracePeriodDays();
            $now = time();
            $out = [];
            foreach ($rows as $r) {
                $trialEnd = $r['trial_ends_at'] ? strtotime($r['trial_ends_at']) : null;
                if (!$trialEnd) continue;

                // 0. gün = deneme bittiği gün; 1. gün = ertesi gün, ...
                $elapsedHours = ($now - $trialEnd) / 3600;
                $day = max(1, (int) ceil($elapsedHours / 24));
                if ($day > $graceDays + 1) continue; // cron kaçırıldı, artık suspended olmalı

                $graceEndTs = $r['grace_ends_at']
                    ? strtotime($r['grace_ends_at'])
                    : ($trialEnd + $graceDays * 86400);
                $left = max(0, (int) ceil(($graceEndTs - $now) / 86400));

                if ($graceDaysFilter !== null && !in_array($day, $graceDaysFilter, true)) {
                    continue;
                }

                $r['grace_day'] = $day;
                $r['grace_days_left'] = $left;
                $out[] = $r;
            }
            return $out;
        } catch (\Exception $e) {
            Logger::error('TrialService::getGraceSubscribers error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Yakinda bitecek trial'lari getir (email uyarisi icin)
     */
    public function getTrialsExpiringSoon(int $withinDays = 3): array {
        try {
            $future = date('Y-m-d H:i:s', strtotime("+{$withinDays} days"));
            $now = date('Y-m-d H:i:s');
            $bizCol = $this->getBusinessIdColumn();
            
            $stmt = $this->db->prepare("
                SELECT s.*, c.email, c.first_name, c.last_name, c.company_name,
                       p.name as package_name
                FROM subscriptions s
                LEFT JOIN customers c ON s.{$bizCol} = c.customer_id
                LEFT JOIN packages p ON s.package_id = p.package_id
                WHERE s.is_trial = 1 
                AND s.status = 'active'
                AND s.trial_ends_at IS NOT NULL
                AND s.trial_ends_at > ?
                AND s.trial_ends_at <= ?
                ORDER BY s.trial_ends_at ASC
            ");
            $stmt->execute([$now, $future]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error('TrialService::getTrialsExpiringSoon error', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Trial kullanicilari listesi (admin icin)
     */
    public function getTrialUsers(string $filter = 'all', int $page = 1, int $perPage = 20): array {
        try {
            $where = "s.is_trial = 1";
            $params = [];
            $bizCol = $this->getBusinessIdColumn();
            
            switch ($filter) {
                case 'active':
                    $where .= " AND s.status = 'active'";
                    break;
                case 'expired':
                    $where .= " AND s.status = 'expired'";
                    break;
                case 'converted':
                    $where .= " AND s.trial_converted = 1";
                    break;
            }
            
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) FROM subscriptions s WHERE $where
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            $offset = ($page - 1) * $perPage;
            $stmt = $this->db->prepare("
                SELECT s.*, c.email, c.first_name, c.last_name, c.company_name, c.phone,
                       p.name as package_name
                FROM subscriptions s
                LEFT JOIN customers c ON s.{$bizCol} = c.customer_id
                LEFT JOIN packages p ON s.package_id = p.package_id
                WHERE $where
                ORDER BY s.created_at DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute($params);
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return [
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ];
        } catch (\Exception $e) {
            Logger::error('TrialService::getTrialUsers error', ['error' => $e->getMessage()]);
            return ['users' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
        }
    }
    
    /**
     * Trial istatistikleri (admin dashboard icin)
     */
    public function getTrialStats(): array {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM subscriptions WHERE is_trial = 1");
            $stats['total_trials'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM subscriptions WHERE is_trial = 1 AND status = 'active'");
            $stats['active_trials'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM subscriptions WHERE is_trial = 1 AND status = 'expired'");
            $stats['expired_trials'] = $stmt->fetchColumn();
            
            $stmt = $this->db->query("SELECT COUNT(*) FROM subscriptions WHERE is_trial = 1 AND trial_converted = 1");
            $stats['converted_trials'] = $stmt->fetchColumn();
            
            $stats['conversion_rate'] = $stats['total_trials'] > 0
                ? round(($stats['converted_trials'] / $stats['total_trials']) * 100, 1)
                : 0;
            
            return $stats;
        } catch (\Exception $e) {
            Logger::error('TrialService::getTrialStats error', ['error' => $e->getMessage()]);
            return [
                'total_trials' => 0, 'active_trials' => 0,
                'expired_trials' => 0, 'converted_trials' => 0,
                'conversion_rate' => 0,
            ];
        }
    }
    
    /**
     * Trial limitleri kontrol
     */
    public function getTrialLimits(): array {
        $settings = $this->getTrialSettings();
        return [
            'max_products' => intval($settings['trial_max_products'] ?? 10),
            'max_tables' => intval($settings['trial_max_tables'] ?? 5),
            'max_staff' => intval($settings['trial_max_staff'] ?? 2),
            'max_categories' => intval($settings['trial_max_categories'] ?? 3),
            'features' => $settings['trial_features'] ?? $this->getDefaultFeatures(),
        ];
    }
    
    /**
     * Belirli limit kontrol
     */
    public function checkTrialLimit(string $customerId, string $limitType, int $currentCount): bool {
        $limits = $this->getTrialLimits();
        $key = 'max_' . $limitType;
        if (!isset($limits[$key])) return true;
        return $currentCount < $limits[$key];
    }
    
    private function getFirstActivePackageId(): string {
        try {
            $stmt = $this->db->query("SELECT package_id FROM packages WHERE is_active = 1 ORDER BY price_monthly ASC LIMIT 1");
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? $row['package_id'] : '';
        } catch (\Exception $e) {
            return '';
        }
    }
    
    private function getDefaultSettings(): array {
        return [
            'trial_enabled' => 1,
            'trial_duration_days' => 7,
            'grace_period_days' => 7,
            'trial_package_id' => '',
            'trial_max_products' => 10,
            'trial_max_tables' => 5,
            'trial_max_staff' => 2,
            'trial_max_categories' => 3,
            'trial_features' => $this->getDefaultFeatures(),
        ];
    }
    
    private function getDefaultFeatures(): array {
        return [];
    }
}
