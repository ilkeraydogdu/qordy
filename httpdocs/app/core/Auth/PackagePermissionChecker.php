<?php
namespace App\Core\Auth;

require_once __DIR__ . '/../DependencyFactory.php';

/**
 * PackagePermissionChecker - Check permissions based on customer's package
 */
class PackagePermissionChecker {
    private $authorization;
    
    public function __construct($authorization) {
        $this->authorization = $authorization;
    }
    
    /**
     * Check if customer has permission based on their package
     * @param string $permission Permission key (e.g., 'menu.create', 'orders.view')
     * @return bool
     */
    public function hasPackagePermission($permission) {
        try {
            $customerId = $this->authorization->getCurrentCustomerId();
            
            if (!$customerId) {
                return false;
            }
            
            // Get active subscription with package details
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $subscription = $subscriptionService->getCustomerSubscription($customerId);
            
            if (!$subscription || empty($subscription['package_id'])) {
                return false;
            }
            
            // Check package features table
            $packageId = $subscription['package_id'];
            $hasFeature = $this->checkPackageFeature($packageId, $permission);
            
            if ($hasFeature !== null) {
                return $hasFeature;
            }

            // Yeni akış: paketin rolleri üzerinden role_permissions'a bak
            $viaRole = $this->hasPermissionViaPackageRoles($packageId, $permission);
            if ($viaRole !== null) {
                return $viaRole;
            }

            // Backward-compat: eski package_permissions tablosu
            $permissions = $subscription['permissions'] ?? [];

            if (empty($permissions)) {
                return false;
            }

            return in_array($permission, $permissions, true);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagePermissionChecker: hasPackagePermission error', [
                    'permission' => $permission,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Check a specific feature in package_features table
     * @param string $packageId
     * @param string $featureKey
     * @return bool|null Returns null if feature not found
     */
    private function checkPackageFeature($packageId, $featureKey) {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();

            // package_features şeması kurulumlar arasında dalgalanıyor:
            // bazıları feature_type + feature_value, bazıları yalnız
            // feature_value + is_enabled taşıyor. `feature_type` kolonunu
            // çalışma zamanında tespit edip sorguyu buna göre kuruyoruz;
            // bu sayede "Unknown column 'feature_type'" hataları düşer.
            $hasType = self::columnExists($db, 'package_features', 'feature_type');

            $sql = $hasType
                ? "SELECT feature_value, feature_type, is_enabled FROM package_features WHERE package_id = :package_id AND feature_key = :feature_key"
                : "SELECT feature_value, is_enabled FROM package_features WHERE package_id = :package_id AND feature_key = :feature_key";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'package_id' => $packageId,
                'feature_key' => $featureKey
            ]);

            $feature = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$feature) {
                return null; // Feature not defined
            }

            // is_enabled = 0 ise paketin bu özelliği kapalıdır.
            if (array_key_exists('is_enabled', $feature) && $feature['is_enabled'] !== null) {
                if (!(int)$feature['is_enabled']) {
                    return false;
                }
            }

            $value = $feature['feature_value'] ?? '';
            $type  = $hasType ? ($feature['feature_type'] ?? null) : self::inferFeatureType($value);

            switch ($type) {
                case 'boolean':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                case 'unlimited':
                    return true;
                case 'number':
                    return (int)$value > 0;
                case 'string':
                    return !empty($value);
                default:
                    // Tip bilinmiyorsa güvenli taraf: değer "truthy"ise aç.
                    return !empty($value) && !in_array(strtolower((string)$value), ['0','false','no','off'], true);
            }

        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagePermissionChecker: checkPackageFeature error', [
                    'package_id' => $packageId,
                    'feature_key' => $featureKey,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * feature_type kolonu olmadığı kurulumlar için değer içeriğinden tip çıkarımı.
     */
    private static function inferFeatureType($value): string {
        if ($value === null || $value === '') {
            return 'boolean';
        }
        $v = strtolower(trim((string)$value));
        if (in_array($v, ['1','0','true','false','yes','no','on','off'], true)) {
            return 'boolean';
        }
        if ($v === 'unlimited' || $v === 'sinirsiz' || $v === 'sınırsız') {
            return 'unlimited';
        }
        if (is_numeric($v)) {
            return 'number';
        }
        return 'string';
    }

    /**
     * Bir tablodaki kolon var mı (PackagePermissionChecker instance'ı başına cache'lenir).
     */
    private static $columnCache = [];
    private static function columnExists(\PDO $db, string $table, string $column): bool {
        $key = $table.'|'.$column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        $exists = \App\Core\DbSchema::hasColumn($table, $column);
        return self::$columnCache[$key] = $exists;
    }
    
    /**
     * Paketin rolleri üzerinden permission kontrolü.
     * role_permissions'taki permission_key'lere bakar.
     *
     * @return bool|null  null = package_roles tablosu yok veya rol eşlemesi hiç yok
     */
    private function hasPermissionViaPackageRoles(string $packageId, string $permission): ?bool {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();

            $check = $db->query("SHOW TABLES LIKE 'package_roles'");
            if ($check->rowCount() === 0) return null;

            // package_roles'ta hiç satır yoksa (eski paketler) null dönsün
            $hasAny = $db->prepare("SELECT 1 FROM package_roles WHERE package_id = ? LIMIT 1");
            $hasAny->execute([$packageId]);
            if (!$hasAny->fetch()) return null;

            // Permission'ı key ya da id ile eşleştir
            $sql = "
                SELECT 1
                FROM package_roles pr
                INNER JOIN role_permissions rp ON rp.role_id = pr.role_id
                LEFT JOIN system_permissions sp ON sp.permission_id = rp.permission_id
                WHERE pr.package_id = ?
                  AND (
                        sp.permission_key = ?
                     OR rp.permission_id = ?
                  )
                LIMIT 1
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$packageId, $permission, $permission]);
            return (bool)$stmt->fetch();
        } catch (\Exception $e) {
            if (class_exists('\\App\\Core\\Logger')) {
                \App\Core\Logger::warning('hasPermissionViaPackageRoles error', [
                    'package_id' => $packageId,
                    'permission' => $permission,
                    'error' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }

    /**
     * Check if customer is within limit for a feature
     * @param string $featureKey Feature key (e.g., 'menu.max_items')
     * @param int $currentCount Current usage count
     * @return bool
     */
    public function isWithinLimit($featureKey, $currentCount) {
        try {
            $customerId = $this->authorization->getCurrentCustomerId();
            
            if (!$customerId) {
                return false;
            }
            
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $subscription = $subscriptionService->getCustomerSubscription($customerId);
            
            if (!$subscription || empty($subscription['package_id'])) {
                return false;
            }
            
            $packageId = $subscription['package_id'];
            $limit = $this->getFeatureLimit($packageId, $featureKey);
            
            if ($limit === 'unlimited') {
                return true;
            }
            
            if ($limit === null) {
                return true; // No limit defined, allow
            }
            
            return $currentCount < (int)$limit;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagePermissionChecker: isWithinLimit error', [
                    'feature_key' => $featureKey,
                    'current_count' => $currentCount,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Get limit value for a feature
     * @param string $packageId
     * @param string $featureKey
     * @return int|string|null Returns 'unlimited', numeric limit, or null
     */
    private function getFeatureLimit($packageId, $featureKey) {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $hasType = self::columnExists($db, 'package_features', 'feature_type');
            $sql = $hasType
                ? "SELECT feature_value, feature_type FROM package_features WHERE package_id = :package_id AND feature_key = :feature_key"
                : "SELECT feature_value FROM package_features WHERE package_id = :package_id AND feature_key = :feature_key";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'package_id' => $packageId,
                'feature_key' => $featureKey
            ]);

            $feature = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$feature) {
                return null;
            }

            $type = $hasType ? ($feature['feature_type'] ?? null) : self::inferFeatureType($feature['feature_value'] ?? '');
            if ($type === 'unlimited') {
                return 'unlimited';
            }

            return $feature['feature_value'];

        } catch (\Exception $e) {
            return null;
        }
    }
}
