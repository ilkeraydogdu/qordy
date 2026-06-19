<?php
namespace App\Services;

require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * SubdomainService - Multi-tenant subdomain management
 * Handles subdomain creation, validation, and DNS/domain configuration
 */
class SubdomainService {

    /**
     * Lowercase ASCII slug: Turkish → latin, strip non-alphanumeric.
     * Used for tenant keys and fuzzy matching typed business names.
     */
    public function slugifyTenantKey(string $input): string {
        $subdomain = strtolower($input);
        $turkishChars = [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
        ];
        $subdomain = strtr($subdomain, $turkishChars);
        // Latin aksanlı karakterler (ör. "Café") — slug kaybını önler
        $latin = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
        ];
        $subdomain = strtr($subdomain, $latin);
        $subdomain = preg_replace('/[^a-z0-9]/', '', $subdomain);
        return substr($subdomain, 0, 63);
    }

    /**
     * Normalize what a user typed on mobile (company name, slug, or URL)
     * into the canonical tenant subdomain key (a-z0-9, no dots).
     */
    public function normalizeTenantInput(string $raw): string {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $s)) {
            $host = parse_url($s, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $s = $host;
            }
        }
        $s = strtolower($s);
        $s = preg_replace('#/.*$#', '', $s);
        $base = strtolower($_ENV['BASE_DOMAIN'] ?? 'qordy.com');
        $suffix = '.' . $base;
        if (str_ends_with($s, $suffix)) {
            $s = substr($s, 0, -strlen($suffix));
        }
        if (strpos($s, '.') !== false) {
            $parts = explode('.', $s);
            $s = $parts[0] ?? $s;
        }
        return $this->slugifyTenantKey($s);
    }
    
    /**
     * Generate subdomain from company name
     * Rules:
     * - Only lowercase letters and numbers (no hyphens)
     * - Turkish characters converted to English equivalents
     * - Spaces removed
     * - Special characters removed
     * 
     * Examples:
     * - "CADDE CADE" -> "caddecafe"
     * - "Pofuduk DİJİTAL" -> "pofudukdijital"
     * 
     * @param string $companyName
     * @return string Clean subdomain string
     */
    public function generateSubdomain(string $companyName): string {
        $subdomain = $this->slugifyTenantKey($companyName);
        if ($subdomain === '') {
            $subdomain = 'business' . uniqid();
        }
        return $subdomain;
    }
    
    /**
     * Validate subdomain format
     * @param string $subdomain
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateSubdomain(string $subdomain): array {
        // Check length
        if (strlen($subdomain) < 3) {
            return ['valid' => false, 'error' => 'Subdomain en az 3 karakter olmalıdır'];
        }
        
        if (strlen($subdomain) > 63) {
            return ['valid' => false, 'error' => 'Subdomain en fazla 63 karakter olabilir'];
        }
        
        // Check format (only lowercase letters and numbers - NO hyphens)
        if (!preg_match('/^[a-z0-9]+$/', $subdomain)) {
            return ['valid' => false, 'error' => 'Subdomain sadece küçük harf ve rakam içerebilir (tire karakteri kullanılamaz)'];
        }
        
        // Check reserved subdomains
        $reserved = ['www', 'admin', 'api', 'qodmin', 'business', 'mail', 'ftp', 'cpanel', 'plesk', 'webmail'];
        if (in_array($subdomain, $reserved)) {
            return ['valid' => false, 'error' => 'Bu subdomain rezerve edilmiştir'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Check if subdomain is available
     * @param string $subdomain
     * @param string|null $excludeCustomerId Exclude this customer from check
     * @return bool
     */
    public function isAvailable(string $subdomain, ?string $excludeCustomerId = null): bool {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // First check database
            $sql = "SELECT customer_id FROM customers WHERE subdomain = :subdomain";
            $params = ['subdomain' => $subdomain];
            
            if ($excludeCustomerId) {
                $sql .= " AND customer_id != :exclude_id";
                $params['exclude_id'] = $excludeCustomerId;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // If found in database, not available
            if ($stmt->rowCount() > 0) {
                return false;
            }
            
            // Also check Plesk if available
            if (\App\Services\PleskService::isAvailable()) {
                $baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
                $fullDomain = $subdomain . '.' . $baseDomain;
                
                // Check if subdomain exists in Plesk
                $checkCmd = "/usr/sbin/plesk bin subdomain --info " . escapeshellarg($subdomain) . 
                           " -domain " . escapeshellarg($baseDomain) . " 2>&1";
                $checkOutput = shell_exec($checkCmd);
                
                // If subdomain exists in Plesk (output contains domain info), it's not available
                if ($checkOutput && (strpos($checkOutput, 'Domain name:') !== false || 
                                     strpos($checkOutput, $fullDomain) !== false ||
                                     strpos($checkOutput, 'Domain ID:') !== false)) {
                    // Check if this subdomain belongs to the excluded customer
                    if ($excludeCustomerId) {
                        // Try to find customer by subdomain in database
                        $checkStmt = $db->prepare("SELECT customer_id FROM customers WHERE subdomain = :subdomain");
                        $checkStmt->execute(['subdomain' => $subdomain]);
                        $existingCustomer = $checkStmt->fetch();
                        
                        // If it belongs to excluded customer, consider it available
                        if ($existingCustomer && $existingCustomer['customer_id'] === $excludeCustomerId) {
                            return true;
                        }
                    }
                    
                    // Subdomain exists in Plesk and doesn't belong to excluded customer
                    return false;
                }
            }
            
            // Not found in database or Plesk, available
            return true;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Subdomain availability check failed', [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Get unique subdomain (with auto-increment if needed)
     * @param string $baseSubdomain
     * @param string|null $excludeCustomerId Exclude this customer from check
     * @return string Unique subdomain
     */
    public function getUniqueSubdomain(string $baseSubdomain, ?string $excludeCustomerId = null): string {
        $subdomain = $baseSubdomain;
        $counter = 1;
        
        // First check if base subdomain is available
        if ($this->isAvailable($subdomain, $excludeCustomerId)) {
            return $subdomain;
        }
        
        // If not available, try with counter
        while (!$this->isAvailable($subdomain, $excludeCustomerId)) {
            $subdomain = $baseSubdomain . '-' . $counter;
            $counter++;
            
            // Prevent infinite loop
            if ($counter > 1000) {
                $subdomain = $baseSubdomain . '-' . uniqid();
                break;
            }
        }
        
        return $subdomain;
    }
    
    /**
     * Create subdomain configuration (for Plesk/Server)
     * This method integrates with Plesk API for automatic subdomain creation
     * @param string $subdomain
     * @param string $customerId
     * @return array ['success' => bool, 'message' => string, 'url' => string|null]
     */
    public function createSubdomainConfig(string $subdomain, string $customerId): array {
        try {
            // Get base domain from config
            $baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
            $fullDomain = $subdomain . '.' . $baseDomain;
            
            // Check if Plesk is available (auto-detect)
            $pleskAvailable = \App\Services\PleskService::isAvailable();
            
            if ($pleskAvailable) {
                // Use Plesk API for automatic subdomain creation
                require_once __DIR__ . '/PleskService.php';
                $pleskService = new \App\Services\PleskService();
                
                try {
                    $result = $pleskService->createSubdomain($subdomain, $customerId);
                    
                    if ($result['success']) {
                        // Verify subdomain was actually created and saved to database
                        $db = \App\Core\DependencyFactory::getDatabase();
                        $verifyStmt = $db->prepare("SELECT 1 FROM subdomains WHERE subdomain_name = ? AND tenant_id = ? LIMIT 1");
                        $verifyStmt->execute([$subdomain, $customerId]);
                        
                        if ($verifyStmt->rowCount() === 0) {
                            // Subdomain not saved to database - treat as failure
                            throw new \Exception('Subdomain Plesk\'te oluşturuldu ancak veritabanına kaydedilemedi');
                        }
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('Subdomain created successfully via Plesk', [
                                'subdomain' => $subdomain,
                                'full_domain' => $fullDomain,
                                'customer_id' => $customerId,
                                'plesk_domain_id' => $result['plesk_domain_id'] ?? null
                            ]);
                        }
                        
                        return [
                            'success' => true,
                            'message' => 'Subdomain başarıyla oluşturuldu',
                            'url' => 'https://' . $fullDomain,
                            'plesk_domain_id' => $result['plesk_domain_id'] ?? null
                        ];
                    } else {
                        // Plesk creation failed - throw exception (no fallback)
                        $errorMsg = $result['message'] ?? 'Plesk subdomain oluşturulamadı';
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Plesk subdomain creation failed', [
                                'subdomain' => $subdomain,
                                'full_domain' => $fullDomain,
                                'customer_id' => $customerId,
                                'error' => $errorMsg,
                                'result' => $result
                            ]);
                        }
                        throw new \Exception('Subdomain oluşturulamadı: ' . $errorMsg);
                    }
                } catch (\Exception $e) {
                    // Plesk creation threw exception - re-throw (no fallback)
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Plesk subdomain creation exception', [
                            'subdomain' => $subdomain,
                            'full_domain' => $fullDomain,
                            'customer_id' => $customerId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                    throw $e; // Re-throw exception
                }
            } else {
                // Plesk not available - throw exception (required for business creation)
                // Check why Plesk is not available for better error message
                $pleskPath = '/usr/sbin/plesk';
                $pleskExists = file_exists($pleskPath);
                $pleskExecutable = $pleskExists && is_executable($pleskPath);
                
                $errorMsg = 'Plesk servisi mevcut değil - subdomain oluşturulamadı';
                if (!$pleskExists) {
                    $errorMsg .= ' (Plesk CLI bulunamadı: ' . $pleskPath . ')';
                } elseif (!$pleskExecutable) {
                    $errorMsg .= ' (Plesk CLI çalıştırılabilir değil)';
                } else {
                    $errorMsg .= ' (Plesk CLI test komutu başarısız oldu)';
                }
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Plesk service not available', [
                        'subdomain' => $subdomain,
                        'full_domain' => $fullDomain,
                        'customer_id' => $customerId,
                        'plesk_exists' => $pleskExists,
                        'plesk_executable' => $pleskExecutable,
                        'plesk_path' => $pleskPath
                    ]);
                }
                throw new \Exception($errorMsg);
            }
            
            // NOTE: This code should never be reached
            // All paths above either return success or throw exception
            throw new \Exception('Subdomain oluşturma işlemi tamamlanamadı - beklenmeyen hata');
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Subdomain config creation failed', [
                    'subdomain' => $subdomain,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Subdomain yapılandırması oluşturulamadı: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get subdomain URL for a customer
     * @param string $customerId
     * @return string|null
     */
    public function getSubdomainUrl(?string $customerId): ?string {
        if (!$customerId) {
            return null;
        }
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT subdomain FROM customers WHERE customer_id = :id");
            $stmt->execute(['id' => $customerId]);
            $customer = $stmt->fetch();
            
            if ($customer && !empty($customer['subdomain'])) {
                $baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
                return 'https://' . $customer['subdomain'] . '.' . $baseDomain;
            }
            
            return null;
            
        } catch (\Exception $e) {
            return null;
        }
    }
}
