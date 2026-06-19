<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\MediaFileRepository;
use App\Core\Logger;

/**
 * Image Service
 * 
 * Main orchestrator for image management system
 * Coordinates validation, storage, processing, and database operations
 */
class ImageService extends BaseService {
    
    private $validatorService;
    private $storageService;
    private $processorService;
    private $config;
    
    /**
     * Constructor
     * @param MediaFileRepository $repository
     */
    public function __construct($repository) {
        parent::__construct($repository);
        
        $this->validatorService = new ImageValidatorService();
        $this->storageService = new StorageManagerService();
        $this->processorService = new ImageProcessorService();
        $this->config = include __DIR__ . '/../config/image.php';
    }
    
    /**
     * Upload and process image
     * @param array $file $_FILES array element
     * @param string $entityType
     * @param string|null $entityId
     * @param array $options Additional options (alt_text, title, is_primary, etc.)
     * @return array Result with success status and data/errors
     */
    public function upload($file, $entityType, $entityId = null, $options = []) {
        try {
            // Require entity_id - no temp directory usage
            if (empty($entityId)) {
                return [
                    'success' => false,
                    'errors' => ['Entity ID is required for image upload. Please create the entity first, then upload the image.'],
                ];
            }
            
            // Validate the image
            $validation = $this->validatorService->validateImage($file, $entityType);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors'],
                ];
            }
            
            // Check file count limit for entity
            if (!$this->canUploadMore($entityType, $entityId)) {
                return [
                    'success' => false,
                    'errors' => ['Maximum number of images reached for this entity'],
                ];
            }
            
            // Generate SEO-friendly name
            $seoName = $options['seo_name'] ?? $this->generateSeoName($file['name'], $options);
            
            // Store original file
            $stored = $this->storageService->store($file, $entityType, $entityId, $seoName);
            
            if (!$stored['success']) {
                return [
                    'success' => false,
                    'errors' => [$stored['error']],
                ];
            }
            
            // Process image (create variants)
            $baseFilename = pathinfo($stored['filename'], PATHINFO_FILENAME);
            $processed = $this->processorService->processImage(
                $stored['full_path'],
                $entityType,
                $entityId,
                $baseFilename
            );
            
            if (!$processed['success']) {
                // Clean up stored file
                $this->storageService->delete($stored['path']);
                return [
                    'success' => false,
                    'errors' => [$processed['error']],
                ];
            }
            
            // Delete the original uploaded file (we have processed versions)
            $this->storageService->delete($stored['path']);
            
            // Save to database
            $mediaRecords = $this->createMediaRecords(
                $processed['images'],
                $entityType,
                $entityId,
                $file,
                $validation['info'],
                $options
            );
            
            // Log upload
            if ($this->config['logging']['log_uploads']) {
                Logger::info("Image uploaded", [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'file_count' => count($mediaRecords),
                    'uploaded_by' => $options['uploaded_by'] ?? null,
                ]);
            }
            
            return [
                'success' => true,
                'data' => [
                    'media_records' => $mediaRecords,
                    'primary_image' => $this->getPrimaryFromRecords($mediaRecords),
                    'variants' => $this->groupBySize($mediaRecords),
                ],
            ];
            
        } catch (\Exception $e) {
            Logger::error("Image upload error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'errors' => ['An error occurred during upload: ' . $e->getMessage()],
            ];
        }
    }
    
    /**
     * Update image metadata
     * @param int $mediaId
     * @param array $metadata
     * @return array
     */
    public function updateMetadata($mediaId, $metadata) {
        try {
            $result = $this->repository->updateMetadata($mediaId, $metadata);
            
            if ($result) {
                return [
                    'success' => true,
                    'data' => $this->repository->findById($mediaId),
                ];
            }
            
            return [
                'success' => false,
                'errors' => ['Failed to update metadata'],
            ];
            
        } catch (\Exception $e) {
            Logger::error("Update metadata error", [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
    
    /**
     * Delete image and all its variants
     * @param int $mediaId
     * @param bool $deleteAllVariants
     * @return array
     */
    public function deleteImage($mediaId, $deleteAllVariants = true) {
        try {
            $media = $this->repository->findById($mediaId);
            if (!$media) {
                return [
                    'success' => false,
                    'errors' => ['Media file not found'],
                ];
            }
            
            $filesToDelete = [];
            
            if ($deleteAllVariants) {
                // Get all related variants
                $related = $this->repository->findRelated($mediaId);
                foreach ($related as $variant) {
                    $filesToDelete[] = $variant;
                }
            } else {
                $filesToDelete[] = $media;
            }
            
            // Delete files from storage
            foreach ($filesToDelete as $file) {
                $this->storageService->delete($file['file_path']);
            }
            
            // Delete from database
            if ($deleteAllVariants) {
                foreach ($filesToDelete as $file) {
                    $this->repository->delete($file['id']);
                }
            } else {
                $this->repository->delete($mediaId);
            }
            
            // Log deletion
            if ($this->config['logging']['log_deletions']) {
                Logger::info("Image deleted", [
                    'media_id' => $mediaId,
                    'variants_deleted' => count($filesToDelete),
                    'entity_type' => $media['entity_type'],
                    'entity_id' => $media['entity_id'],
                ]);
            }
            
            return [
                'success' => true,
                'data' => [
                    'deleted_count' => count($filesToDelete),
                ],
            ];
            
        } catch (\Exception $e) {
            Logger::error("Image deletion error", [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
    
    /**
     * Delete all images for an entity
     * @param string $entityType
     * @param string $entityId
     * @return array
     */
    public function deleteByEntity($entityType, $entityId) {
        try {
            $images = $this->repository->findByEntity($entityType, $entityId);
            
            if (empty($images)) {
                return [
                    'success' => true,
                    'data' => ['deleted_count' => 0],
                ];
            }
            
            // Delete files from storage
            foreach ($images as $image) {
                $this->storageService->delete($image['file_path']);
            }
            
            // Delete directory
            $this->storageService->deleteEntityDirectory($entityType, $entityId);
            
            // Delete from database
            $this->repository->deleteByEntity($entityType, $entityId);
            
            return [
                'success' => true,
                'data' => ['deleted_count' => count($images)],
            ];
            
        } catch (\Exception $e) {
            Logger::error("Delete by entity error", [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
    
    /**
     * Get images for an entity
     * @param string $entityType
     * @param string $entityId
     * @param string|null $size Filter by size (thumbnail, medium, large, original)
     * @return array
     */
    public function getByEntity($entityType, $entityId, $size = null) {
        $fileType = $size ?? 'original';
        return $this->repository->findByEntity($entityType, $entityId, $fileType);
    }
    
    /**
     * Get primary image for entity
     * @param string $entityType
     * @param string $entityId
     * @param string $size
     * @return array|null
     */
    public function getPrimaryImage($entityType, $entityId, $size = 'original') {
        return $this->repository->findPrimaryImage($entityType, $entityId, $size);
    }
    
    /**
     * Set image as primary
     * @param string $entityType
     * @param string $entityId
     * @param int $mediaId
     * @return array
     */
    public function setPrimary($entityType, $entityId, $mediaId) {
        try {
            $result = $this->repository->setPrimary($entityType, $entityId, $mediaId);
            
            if ($result) {
                return ['success' => true];
            }
            
            return [
                'success' => false,
                'errors' => ['Failed to set primary image'],
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
    
    /**
     * Update sort order
     * @param array $orderMap
     * @return array
     */
    public function updateSortOrder($orderMap) {
        try {
            $result = $this->repository->updateSortOrder($orderMap);
            
            if ($result) {
                return ['success' => true];
            }
            
            return [
                'success' => false,
                'errors' => ['Failed to update sort order'],
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
    
    /**
     * Get image URL
     * @param string $entityType
     * @param string $entityId
     * @param string $size
     * @param bool $webp Prefer WebP format
     * @return string|null
     */
    public function getImageUrl($entityType, $entityId, $size = 'medium', $webp = false) {
        $fileType = $webp ? 'webp_' . $size : $size;
        $image = $this->repository->findPrimaryImage($entityType, $entityId, $fileType);
        
        if (!$image) {
            // Fallback to non-WebP
            $image = $this->repository->findPrimaryImage($entityType, $entityId, $size);
        }
        
        return $image ? $this->storageService->getUrl($image['file_path']) : null;
    }
    
    /**
     * Get responsive image URLs (all sizes)
     * @param string $entityType
     * @param string $entityId
     * @return array
     */
    public function getResponsiveUrls($entityType, $entityId) {
        $images = $this->repository->findByEntity($entityType, $entityId);
        
        $urls = [
            'thumbnail' => null,
            'medium' => null,
            'large' => null,
            'original' => null,
            'webp' => [],
        ];
        
        foreach ($images as $image) {
            if (!$image['is_primary']) {
                continue;
            }
            
            $url = $this->storageService->getUrl($image['file_path']);
            
            if (strpos($image['file_type'], 'webp_') === 0) {
                $size = str_replace('webp_', '', $image['file_type']);
                $urls['webp'][$size] = $url;
            } else {
                $urls[$image['file_type']] = $url;
            }
        }
        
        return $urls;
    }
    
    /**
     * Get statistics
     * @return array
     */
    public function getStatistics() {
        return $this->repository->getStatistics();
    }
    
    /**
     * Clean up orphaned files
     * @param int $olderThanHours
     * @return array
     */
    public function cleanupOrphaned($olderThanHours = 24) {
        try {
            $orphaned = $this->repository->findOrphaned($olderThanHours);
            $deleted = 0;
            
            foreach ($orphaned as $file) {
                $this->storageService->delete($file['file_path']);
                $this->repository->delete($file['id']);
                $deleted++;
            }
            
            return [
                'success' => true,
                'data' => ['deleted_count' => $deleted],
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }
    
    /**
     * Check if entity can upload more images
     * @param string $entityType
     * @param string $entityId
     * @return bool
     */
    private function canUploadMore($entityType, $entityId) {
        $maxFiles = $this->config['entity_types'][$entityType]['max_files'] ?? 10;
        $currentCount = $this->repository->countByEntity($entityType, $entityId, true);
        
        return $currentCount < $maxFiles;
    }
    
    /**
     * Generate SEO-friendly name
     * @param string $filename
     * @param array $options
     * @return string
     */
    private function generateSeoName($filename, $options = []) {
        if (isset($options['name'])) {
            return $options['name'];
        }
        
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return $this->validatorService->sanitizeFilename($name);
    }
    
    /**
     * Create media records in database
     * @param array $processedImages
     * @param string $entityType
     * @param string|null $entityId
     * @param array $originalFile
     * @param array $imageInfo
     * @param array $options
     * @return array
     */
    private function createMediaRecords($processedImages, $entityType, $entityId, $originalFile, $imageInfo, $options) {
        $records = [];
        $isPrimary = $options['is_primary'] ?? true;
        
        // If entity already has images, this shouldn't be primary by default
        if ($entityId && $isPrimary) {
            $existingCount = $this->repository->countByEntity($entityType, $entityId, true);
            if ($existingCount > 0) {
                $isPrimary = false;
            }
        }
        
        foreach ($processedImages as $image) {
            if (!$image['success']) {
                continue;
            }
            
            // Create main format record
            $data = [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'file_type' => $image['size'],
                'original_filename' => $originalFile['name'],
                'stored_filename' => $image['filename'],
                'file_path' => $image['path'],
                'file_size' => $image['file_size'],
                'mime_type' => $imageInfo['mime'] ?? $originalFile['type'],
                'dimensions' => $image['dimensions'],
                'alt_text' => $options['alt_text'] ?? null,
                'title' => $options['title'] ?? null,
                'is_primary' => ($image['size'] === 'original') ? $isPrimary : 0,
                'sort_order' => $options['sort_order'] ?? 0,
                'uploaded_by' => $options['uploaded_by'] ?? null,
            ];
            
            $id = $this->repository->create($data);
            if ($id) {
                $data['id'] = $id;
                $records[] = $data;
            }
            
            // Create WebP record if exists
            if (isset($image['webp'])) {
                $webpData = $data;
                // Remove id if it exists (should not be in data, but just in case to prevent duplicate key error)
                unset($webpData['id']);
                $webpData['file_type'] = 'webp_' . $image['size'];
                $webpData['stored_filename'] = $image['webp']['filename'];
                $webpData['file_path'] = $image['webp']['path'];
                $webpData['file_size'] = $image['webp']['file_size'];
                $webpData['mime_type'] = 'image/webp';
                $webpData['is_primary'] = 0; // WebP variants are never primary
                
                $webpId = $this->repository->create($webpData);
                if ($webpId) {
                    $webpData['id'] = $webpId;
                    $records[] = $webpData;
                }
            }
        }
        
        return $records;
    }
    
    /**
     * Get primary image from records
     * @param array $records
     * @return array|null
     */
    private function getPrimaryFromRecords($records) {
        foreach ($records as $record) {
            if ((int)($record['is_primary'] ?? 0) === 1) {
                return $record;
            }
        }
        return $records[0] ?? null;
    }

    /**
     * Group records by size
     * @param array $records
     * @return array
     */
    private function groupBySize($records) {
        $grouped = [];
        foreach ($records as $record) {
            $size = $record['file_type'];
            $grouped[$size] = $record;
        }
        return $grouped;
    }

    /**
     * Delete a record (override parent method to maintain compatibility)
     * @param string $id Record ID
     * @return bool Success
     */
    public function delete(string $id): bool {
        // Use the existing deleteImage functionality but with default parameters
        $result = $this->deleteImage($id, true);
        return $result['success'] ?? false;
    }
}

