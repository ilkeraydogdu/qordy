<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\CustomerRepository;
use App\Core\DependencyFactory;

/**
 * BusinessService - Merkezi İşletme Bilgisi Servisi
 * Tek kaynak prensibi: customers tablosu ana kaynak
 * Hardcode fallback YOK - tüm bilgiler veritabanından gelecek
 */
class BusinessService extends BaseService {
    public function __construct(CustomerRepository $repository) {
        parent::__construct($repository);
    }

    
    /**
     * Get business information from single source (customers table)
     * @param string $businessId Business ID (customer_id)
     * @return array Business info with company_name, logo_path, logo_url, etc.
     */
    public function getBusinessInfo(string $businessId): array {
        $cache = DependencyFactory::getCacheService();
        $cacheKey = "business_info:{$businessId}";
        
        return $cache->remember($cacheKey, function() use ($businessId) {
            $db = DependencyFactory::getDatabase();
            
            // Get from customers table (primary source)
            $customer = $this->repository->findById($businessId);
            
            if (!$customer) {
                // Customer not found - return empty structure
                return [
                    'business_id' => $businessId,
                    'company_name' => '',
                    'business_name' => '',
                    'name' => '',
                    'logo_path' => '',
                    'logo_url' => '',
                    'email' => '',
                    'first_name' => '',
                    'last_name' => '',
                    'city' => '',
                    'has_data' => false
                ];
            }
            
            $companyName = trim($customer['company_name'] ?? '');
            $logoPath = trim($customer['logo_path'] ?? '');
            $logoUrl = trim($customer['logo_url'] ?? '');
            
            return [
                'business_id' => $businessId,
                'company_name' => $companyName,
                'business_name' => $companyName, // Alias for compatibility
                'name' => $companyName, // Alias for compatibility
                'logo_path' => $logoPath,
                'logo_url' => $logoUrl ?: $logoPath, // Use logo_path if logo_url is empty
                'email' => trim($customer['email'] ?? ''),
                'first_name' => trim($customer['first_name'] ?? ''),
                'last_name' => trim($customer['last_name'] ?? ''),
                'city' => trim($customer['city'] ?? ''),
                'has_data' => !empty($companyName)
            ];
        }, 3600); // Cache for 1 hour
    }
    
    /**
     * Update business info in customers table
     * @param string $businessId Business ID
     * @param array $data Data to update (company_name, logo_path, logo_url)
     * @return bool Success
     */
    public function updateBusinessInfo(string $businessId, array $data): bool {
        try {
            $updateFields = [];
            $updateValues = [];
            
            if (isset($data['company_name'])) {
                $updateFields[] = "company_name = ?";
                $updateValues[] = trim($data['company_name']);
            }
            
            if (isset($data['logo_path'])) {
                $updateFields[] = "logo_path = ?";
                $updateValues[] = trim($data['logo_path']);
            }
            
            if (isset($data['logo_url'])) {
                $updateFields[] = "logo_url = ?";
                $updateValues[] = trim($data['logo_url']);
            }
            
            if (empty($updateFields)) {
                return false;
            }
            
            $updateValues[] = $businessId;
            $sql = "UPDATE customers SET " . implode(", ", $updateFields) . " WHERE customer_id = ?";
            
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare($sql);
            $result = $stmt->execute($updateValues);
            
            if ($result) {
                // Invalidate cache
                $cache = DependencyFactory::getCacheService();
                $cache->delete("business_info:{$businessId}");
            }
            
            return $result;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('BusinessService::updateBusinessInfo - Error', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
}
