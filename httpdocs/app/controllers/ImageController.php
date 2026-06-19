<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Services\ImageService;
use App\Services\StorageManagerService;
use App\Repositories\MediaFileRepository;
use App\Core\DependencyFactory;

/**
 * Image Controller
 * 
 * Handles HTTP requests for image management
 * Provides RESTful API endpoints for upload, update, delete, and retrieval
 */
class ImageController extends BaseController {
    
    private $imageService;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize image service via DependencyFactory
        $this->imageService = DependencyFactory::getImageService();
    }
    
    /**
     * Upload image
     * POST /api/images/upload
     * 
     * Expected parameters:
     * - file: Image file
     * - entity_type: Type of entity (product, logo, category, avatar, etc.)
     * - entity_id: ID of entity (optional for temporary upload)
     * - alt_text: SEO alt text (optional)
     * - title: SEO title (optional)
     * - is_primary: Set as primary image (optional, default: auto)
     */
    public function upload() {
        try {
            // Check authentication
            if (!isLoggedIn()) {
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Authentication required'],
                ], 401);
                return;
            }
            
            // Validate request
            if (!isset($_FILES['file'])) {
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['No file uploaded'],
                ], 400);
                return;
            }
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $entityType = $requestData['entity_type'] ?? 'other';
            $entityId = $requestData['entity_id'] ?? null;
            
            // Validate entity_id is required (no temp directory usage)
            if (empty($entityId)) {
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Entity ID is required. Please create the entity first, then upload the image.'],
                ], 400);
                return;
            }
            
            // Special handling for product images (bypass ImageService, use StorageManagerService directly)
            if ($entityType === 'product') {
                try {
                    // Get menu item to get business_id and product name
                    $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
                    $menuItem = $menuItemService->getMenuItemById($entityId);
                    
                    if (!$menuItem) {
                        $this->apiResponse([
                            'success' => false,
                            'errors' => ['Menu item not found'],
                        ], 404);
                        return;
                    }
                    
                    // menu_items table uses tenant_id column; fall back to legacy business_id
                    $businessId = $menuItem['tenant_id'] ?? $menuItem['business_id'] ?? null;
                    if (empty($businessId)) {
                        $this->apiResponse([
                            'success' => false,
                            'errors' => ['Business ID not found for menu item'],
                        ], 400);
                        return;
                    }

                    // CRITICAL: Tenant isolation - prevent cross-tenant image uploads.
                    // SuperAdmin is allowed to upload for any tenant; every other role
                    // must be inside the same tenant as the target menu item.
                    $isSuperAdmin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['SUPER_ADMIN', 'ADMIN'], true);
                    if (!$isSuperAdmin) {
                        try {
                            $currentTenantId = \App\Core\TenantContext::getId();
                        } catch (\Exception $e) {
                            $currentTenantId = null;
                        }
                        if (!$currentTenantId || $currentTenantId !== $businessId) {
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('ImageController::upload - Cross-tenant image upload blocked', [
                                    'menu_item_id' => $entityId,
                                    'item_tenant_id' => $businessId,
                                    'current_tenant_id' => $currentTenantId,
                                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                                ]);
                            }
                            $this->apiResponse([
                                'success' => false,
                                'errors' => ['Unauthorized: tenant mismatch'],
                            ], 403);
                            return;
                        }
                    }
                    
                    // Get product name
                    $productName = $menuItem['name'] ?? 'product';
                    if (empty($productName)) {
                        $productName = 'product';
                    }
                    
                    // Initialize StorageManagerService
                    $storageService = new StorageManagerService();
                    
                    // Upload product image using StorageManagerService
                    $uploadResult = $storageService->storeProductImage($_FILES['file'], $businessId, $productName);
                    
                    if (!$uploadResult['success']) {
                        $this->apiResponse([
                            'success' => false,
                            'errors' => [$uploadResult['error'] ?? 'Failed to upload image'],
                        ], 400);
                        return;
                    }
                    
                    // Update menu item's image_url
                    $imageUrl = $uploadResult['url'];
                    $updateResult = $menuItemService->updateMenuItem($entityId, [
                        'image_url' => $imageUrl
                    ]);
                    
                    if (!$updateResult) {
                        // Log warning but don't fail the upload
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('Failed to update menu item image_url after product image upload', [
                                'menu_item_id' => $entityId,
                                'image_url' => $imageUrl
                            ]);
                        }
                    } else {
                        // Log success
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('Product image uploaded and menu item image_url updated successfully', [
                                'menu_item_id' => $entityId,
                                'image_url' => $imageUrl,
                                'business_id' => $businessId
                            ]);
                        }
                    }
                    
                    // Return success response
                    $this->apiResponse([
                        'success' => true,
                        'data' => [
                            'url' => $imageUrl,
                            'path' => $uploadResult['path'],
                            'filename' => $uploadResult['filename']
                        ],
                        'message' => 'Image uploaded successfully'
                    ], 201);
                    return;
                    
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Error uploading product image', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'menu_item_id' => $entityId
                        ]);
                    }
                    $this->apiResponse([
                        'success' => false,
                        'errors' => ['Internal server error: ' . $e->getMessage()],
                    ], 500);
                    return;
                }
            }
            
            // For non-product entities, use ImageService as before
            // Prepare options
            $options = [
                'alt_text' => $requestData['alt_text'] ?? null,
                'title' => $requestData['title'] ?? null,
                'name' => $requestData['name'] ?? null,
                'is_primary' => isset($requestData['is_primary']) ? (bool)$requestData['is_primary'] : null,
                'sort_order' => $requestData['sort_order'] ?? 0,
                'uploaded_by' => $this->getCurrentUserId(),
            ];
            
            // Upload image
            $result = $this->imageService->upload($_FILES['file'], $entityType, $entityId, $options);

            if ($result['success']) {
                // Add URL to response for easier frontend usage
                $primaryImage = $result['data']['primary_image'] ?? null;
                $imageUrl = null;
                
                if ($primaryImage && isset($primaryImage['file_path'])) {
                    $config = include __DIR__ . '/../config/image.php';
                    $baseUrl = $config['storage']['base_url'] ?? '/uploads';
                    $filePath = $primaryImage['file_path'];
                    $imageUrl = $baseUrl . '/' . ltrim($filePath, '/');
                    $result['data']['url'] = $imageUrl;
                } elseif (isset($result['data']['media_records']) && is_array($result['data']['media_records']) && count($result['data']['media_records']) > 0) {
                    // Fallback: Use first media record if primary_image is not available
                    $firstRecord = $result['data']['media_records'][0];
                    if (isset($firstRecord['file_path'])) {
                        $config = include __DIR__ . '/../config/image.php';
                        $baseUrl = $config['storage']['base_url'] ?? '/uploads';
                        $filePath = $firstRecord['file_path'];
                        $imageUrl = $baseUrl . '/' . ltrim($filePath, '/');
                        $result['data']['url'] = $imageUrl;
                    }
                }
                
                // Log if primary_image is missing (for debugging)
                if (!$primaryImage && class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Primary image not found in upload result', [
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'has_media_records' => isset($result['data']['media_records']) ? count($result['data']['media_records']) : 0
                    ]);
                }

                // If entity type is 'product' (menu item), update the menu item's image_url field
                if ($entityType === 'product' && $imageUrl) {
                    try {
                        $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
                        $menuItem = $menuItemService->getMenuItemById($entityId);

                        if ($menuItem) {
                            // Update the menu item's image_url field with the uploaded image URL
                            $updateResult = $menuItemService->updateMenuItem($entityId, [
                                'image_url' => $imageUrl
                            ]);

                            if ($updateResult) {
                                // Log success for debugging
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::info('Menu item image_url updated successfully', [
                                        'menu_item_id' => $entityId,
                                        'image_url' => $imageUrl
                                    ]);
                                }
                            } else {
                                // Log the error but don't fail the image upload
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::warning('Failed to update menu item image_url after image upload', [
                                        'menu_item_id' => $entityId,
                                        'image_url' => $imageUrl
                                    ]);
                                }
                            }
                        } else {
                            // Log if menu item not found
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('Menu item not found for image_url update', [
                                    'menu_item_id' => $entityId,
                                    'image_url' => $imageUrl
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        // Log the error but don't fail the image upload
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Error updating menu item image_url after image upload', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'menu_item_id' => $entityId,
                                'image_url' => $imageUrl ?? null
                            ]);
                        }
                    }
                } elseif ($entityType === 'product' && !$imageUrl) {
                    // Log if image URL could not be generated
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Could not generate image URL for menu item', [
                            'menu_item_id' => $entityId,
                            'has_primary_image' => $primaryImage !== null,
                            'has_media_records' => isset($result['data']['media_records']) ? count($result['data']['media_records']) : 0
                        ]);
                    }
                }

                $this->apiResponse($result, 201);
            } else {
                $this->apiResponse($result, 400);
            }
            
        } catch (\Exception $e) {
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ], 500);
        }
    }
    
    /**
     * Update image metadata
     * PUT /api/images/:id
     */
    public function update($id) {
        try {
            // Check authentication
            if (!isLoggedIn()) {
                // Status code handled by apiResponse(401);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Authentication required'],
                ]);
                return;
            }
            
            // Get JSON data
            $data = \App\Core\RequestParser::getRequestData();
            
            if (empty($data)) {
                // Status code handled by apiResponse(400);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Invalid JSON data'],
                ]);
                return;
            }
            
            $result = $this->imageService->updateMetadata($id, $data);
            
            if ($result['success']) {
                // Status code handled by apiResponse(200);
                $this->apiResponse($result);
            } else {
                // Status code handled by apiResponse(400);
                $this->apiResponse($result);
            }
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Delete image
     * DELETE /api/images/:id
     */
    public function delete($id) {
        try {
            if (!isLoggedIn()) {
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Authentication required'],
                ]);
                return;
            }

            // Yetki kontrolü: Super admin her kaydı silebilir. Diğer kullanıcılar
            // yalnızca kendi yükledikleri veya kendi tenant'larına ait
            // entity'ye bağlı medya kaydını silebilir.
            if (!$this->canDeleteMedia((string)$id)) {
                \App\Core\Logger::warning('ImageController::delete forbidden attempt', [
                    'media_id' => $id,
                    'user_id'  => $this->getCurrentUserId(),
                ]);
                $this->apiResponse([
                    'success' => false,
                    'errors'  => ['Bu görseli silme yetkiniz yok'],
                ], 403);
                return;
            }

            $queryParams = \App\Core\RequestParser::getQueryParams();
            $deleteAllVariants = isset($queryParams['all_variants']) ? (bool)$queryParams['all_variants'] : true;

            $result = $this->imageService->deleteImage($id, $deleteAllVariants);
            
            if ($result['success']) {
                // Status code handled by apiResponse(200);
                $this->apiResponse($result);
            } else {
                // Status code handled by apiResponse(400);
                $this->apiResponse($result);
            }
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Silme işlemi için yetki kontrolü.
     *
     *  - Super admin → her zaman true.
     *  - Yükleyenin kendisi → true.
     *  - Entity bağlı bir tenant_id'ye ait ve aktif tenant eşleşirse → true.
     *  - Diğer durumlar → false (savunma amaçlı).
     */
    private function canDeleteMedia(string $mediaId): bool {
        try {
            if (method_exists($this, 'isSuperAdmin') && $this->isSuperAdmin()) {
                return true;
            }

            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT entity_type, entity_id, uploaded_by FROM media_files WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $mediaId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                // Kayıt yoksa 404'ü ImageService döndürsün; burada engel değil.
                return true;
            }

            $currentUserId = (string) $this->getCurrentUserId();
            if ($currentUserId !== '' && !empty($row['uploaded_by']) && (string)$row['uploaded_by'] === $currentUserId) {
                return true;
            }

            // Tenant eşleştirmesi: entity_type'a göre ilgili tablodaki tenant_id
            // kolonunu bulup aktif tenant ile kıyasla.
            $tenantId = null;
            try { $tenantId = \App\Core\TenantResolver::resolve(); } catch (\Throwable $e) { $tenantId = null; }
            if (!$tenantId) {
                return false;
            }

            $map = [
                'product'     => ['menu_items',  'menu_item_id'],
                'menu_item'   => ['menu_items',  'menu_item_id'],
                'category'    => ['categories',  'category_id'],
                'business'    => ['businesses',  'tenant_id'],
                'logo'        => ['businesses',  'tenant_id'],
                'customer'    => ['customers',   'customer_id'],
                'avatar'      => ['users',       'user_id'],
            ];
            $entityType = (string) $row['entity_type'];
            $entityId   = (string) $row['entity_id'];
            if (!isset($map[$entityType])) {
                return false;
            }
            [$table, $pk] = $map[$entityType];

            // Tenant sütunu (tenant_id/business_id/customer_id) DbSchema ile tespit et.
            $tenantCol = \App\Core\DbSchema::pickTenantColumn($table);
            if (!$tenantCol) {
                return false;
            }

            $own = $db->prepare("SELECT {$tenantCol} FROM `{$table}` WHERE `{$pk}` = :eid LIMIT 1");
            $own->execute(['eid' => $entityId]);
            $rowOwner = $own->fetch(\PDO::FETCH_ASSOC);
            if ($rowOwner && (string)($rowOwner[$tenantCol] ?? '') === (string)$tenantId) {
                return true;
            }

            return false;
        } catch (\Throwable $e) {
            \App\Core\Logger::error('ImageController::canDeleteMedia failed', [
                'error'    => $e->getMessage(),
                'media_id' => $mediaId,
            ]);
            return false;
        }
    }

    /**
     * Get images by entity
     * GET /api/images/entity/:type/:id
     */
    public function getByEntity($entityType, $entityId) {
        try {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $size = $queryParams['size'] ?? null;
            $images = $this->imageService->getByEntity($entityType, $entityId, $size);
            
            // Status code handled by apiResponse(200);
            $this->apiResponse([
                'success' => true,
                'data' => $images,
            ]);
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Get primary image for entity
     * GET /api/images/entity/:type/:id/primary
     */
    public function getPrimaryImage($entityType, $entityId) {
        try {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $size = $queryParams['size'] ?? 'original';
            $image = $this->imageService->getPrimaryImage($entityType, $entityId, $size);
            
            if ($image) {
                // Status code handled by apiResponse(200);
                $this->apiResponse([
                    'success' => true,
                    'data' => $image,
                ]);
            } else {
                // Status code handled by apiResponse(404);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Image not found'],
                ]);
            }
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Set image as primary
     * POST /api/images/:id/set-primary
     */
    public function setPrimary($id) {
        try {
            // Check authentication
            if (!isLoggedIn()) {
                // Status code handled by apiResponse(401);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Authentication required'],
                ]);
                return;
            }
            
            $data = \App\Core\RequestParser::getRequestData();
            $entityType = $data['entity_type'] ?? null;
            $entityId = $data['entity_id'] ?? null;
            
            if (!$entityType || !$entityId) {
                // Status code handled by apiResponse(400);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['entity_type and entity_id are required'],
                ]);
                return;
            }
            
            $result = $this->imageService->setPrimary($entityType, $entityId, $id);
            
            if ($result['success']) {
                // Status code handled by apiResponse(200);
                $this->apiResponse($result);
            } else {
                // Status code handled by apiResponse(400);
                $this->apiResponse($result);
            }
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Update sort order
     * POST /api/images/sort-order
     */
    public function updateSortOrder() {
        try {
            // Check authentication
            if (!isLoggedIn()) {
                // Status code handled by apiResponse(401);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Authentication required'],
                ]);
                return;
            }
            
            $data = \App\Core\RequestParser::getRequestData();
            $orderMap = $data['order'] ?? [];
            
            if (empty($orderMap)) {
                // Status code handled by apiResponse(400);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Order map is required'],
                ]);
                return;
            }
            
            $result = $this->imageService->updateSortOrder($orderMap);
            
            if ($result['success']) {
                // Status code handled by apiResponse(200);
                $this->apiResponse($result);
            } else {
                // Status code handled by apiResponse(400);
                $this->apiResponse($result);
            }
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Delete all images for entity
     * DELETE /api/images/entity/:type/:id
     */
    public function deleteByEntity($entityType, $entityId) {
        try {
            // Check authentication
            if (!isLoggedIn()) {
                // Status code handled by apiResponse(401);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Authentication required'],
                ]);
                return;
            }
            
            $result = $this->imageService->deleteByEntity($entityType, $entityId);
            
            if ($result['success']) {
                // Status code handled by apiResponse(200);
                $this->apiResponse($result);
            } else {
                // Status code handled by apiResponse(400);
                $this->apiResponse($result);
            }
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Get responsive image URLs
     * GET /api/images/entity/:type/:id/responsive
     */
    public function getResponsiveUrls($entityType, $entityId) {
        try {
            $urls = $this->imageService->getResponsiveUrls($entityType, $entityId);
            
            // Status code handled by apiResponse(200);
            $this->apiResponse([
                'success' => true,
                'data' => $urls,
            ]);
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Get statistics
     * GET /api/images/statistics
     */
    public function statistics() {
        try {
            // Check authentication and admin role
            if (!isLoggedIn() || !hasRole(ROLE_MANAGER)) {
                // Status code handled by apiResponse(403);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Insufficient permissions'],
                ]);
                return;
            }
            
            $stats = $this->imageService->getStatistics();
            
            // Status code handled by apiResponse(200);
            $this->apiResponse([
                'success' => true,
                'data' => $stats,
            ]);
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
    
    /**
     * Clean up orphaned files
     * POST /api/images/cleanup-orphaned
     */
    public function cleanupOrphaned() {
        try {
            // Check authentication and admin role
            if (!isLoggedIn() || !hasRole(ROLE_MANAGER)) {
                // Status code handled by apiResponse(403);
                $this->apiResponse([
                    'success' => false,
                    'errors' => ['Insufficient permissions'],
                ]);
                return;
            }
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $hours = $requestData['hours'] ?? 24;
            $result = $this->imageService->cleanupOrphaned($hours);
            
            // Status code handled by apiResponse(200);
            $this->apiResponse($result);
            
        } catch (\Exception $e) {
            // Status code handled by apiResponse(500);
            $this->apiResponse([
                'success' => false,
                'errors' => ['Internal server error: ' . $e->getMessage()],
            ]);
        }
    }
}

