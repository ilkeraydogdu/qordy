<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

/**
 * MediaFile Model
 * 
 * Manages media files and their metadata including relationships
 * with various entities (products, categories, users, etc.)
 */
class MediaFile extends \App\Core\Model {
    protected $table = 'media_files';
    
    /**
     * Get all media files
     * @return array
     */
    public function getAll() {
        return $this->query()
            ->select(['*'])
            ->from($this->table)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get media file by ID
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        return $this->query()
            ->from($this->table)
            ->where('id', $id)
            ->first();
    }
    
    /**
     * Get all media files for a specific entity
     * @param string $entityType
     * @param string $entityId
     * @return array
     */
    public function getByEntity($entityType, $entityId) {
        return $this->query()
            ->from($this->table)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->get();
    }
    
    /**
     * Get primary image for an entity
     * @param string $entityType
     * @param string $entityId
     * @param string $fileType (original, thumbnail, medium, large, etc.)
     * @return array|null
     */
    public function getPrimaryImage($entityType, $entityId, $fileType = 'original') {
        return $this->query()
            ->from($this->table)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('file_type', $fileType)
            ->where('is_primary', 1)
            ->first();
    }
    
    /**
     * Get all images of specific file type for an entity
     * @param string $entityType
     * @param string $entityId
     * @param string $fileType
     * @return array
     */
    public function getByFileType($entityType, $entityId, $fileType) {
        return $this->query()
            ->from($this->table)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('file_type', $fileType)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }
    
    /**
     * Get all media files by type (entity type)
     * @param string $entityType
     * @return array
     */
    public function getAllByType($entityType) {
        return $this->query()
            ->from($this->table)
            ->where('entity_type', $entityType)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Create new media file record
     * @param array $data
     * @return int|bool
     */
    public function create($data) {
        return $this->query()
            ->insert($data);
    }
    
    /**
     * Update media file
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateMedia($id, $data) {
        return $this->query()
            ->where('id', $id)
            ->update($data);
    }
    
    /**
     * Delete media file record
     * @param int $id
     * @return bool
     */
    public function deleteMedia($id) {
        return $this->query()
            ->where('id', $id)
            ->delete();
    }
    
    /**
     * Delete all media files for an entity
     * @param string $entityType
     * @param string $entityId
     * @return bool
     */
    public function deleteByEntity($entityType, $entityId) {
        return $this->query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete();
    }
    
    /**
     * Set primary image for an entity
     * First removes primary flag from all images, then sets the specified one
     * @param string $entityType
     * @param string $entityId
     * @param int $mediaId
     * @return bool
     */
    public function setPrimaryImage($entityType, $entityId, $mediaId) {
        // Remove primary flag from all images of this entity
        $this->query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->update(['is_primary' => 0]);
        
        // Set the specified image as primary
        return $this->query()
            ->where('id', $mediaId)
            ->update(['is_primary' => 1]);
    }
    
    /**
     * Update sort order for multiple images
     * @param array $orderMap Array of id => sort_order
     * @return bool
     */
    public function updateSortOrder($orderMap) {
        $success = true;
        foreach ($orderMap as $id => $order) {
            $result = $this->query()
                ->where('id', $id)
                ->update(['sort_order' => $order]);
            if (!$result) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * Get related images (all variants of same upload)
     * Images are grouped by stored_filename base (without size suffix)
     * @param int $id
     * @return array
     */
    public function getRelatedImages($id) {
        $media = $this->getById($id);
        if (!$media) {
            return [];
        }
        
        return $this->query()
            ->from($this->table)
            ->where('entity_type', $media['entity_type'])
            ->where('entity_id', $media['entity_id'])
            ->where('stored_filename', 'LIKE', '%' . pathinfo($media['stored_filename'], PATHINFO_FILENAME) . '%')
            ->get();
    }
    
    /**
     * Count media files by entity
     * @param string $entityType
     * @param string $entityId
     * @return int
     */
    public function countByEntity($entityType, $entityId) {
        $result = $this->query()
            ->from($this->table)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('file_type', 'original') // Only count originals
            ->get();
        return count($result);
    }
    
    /**
     * Get total storage size for an entity
     * @param string $entityType
     * @param string $entityId
     * @return int Total size in bytes
     */
    public function getTotalSize($entityType, $entityId) {
        $sql = "SELECT SUM(file_size) as total_size FROM {$this->table} 
                WHERE entity_type = :entity_type AND entity_id = :entity_id";
        $result = $this->fetch($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ]);
        return $result ? (int)($result['total_size'] ?? 0) : 0;
    }
    
    /**
     * Find orphaned files (no entity_id)
     * @param int $olderThanHours
     * @return array
     */
    public function findOrphaned($olderThanHours = 24) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE entity_id IS NULL 
                AND created_at < DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        return $this->fetchAll($sql, ['hours' => $olderThanHours]);
    }
    
    /**
     * Get statistics for media files
     * @return array
     */
    public function getStatistics() {
        $sql = "SELECT 
                    entity_type,
                    COUNT(*) as total_files,
                    SUM(file_size) as total_size,
                    AVG(file_size) as avg_size
                FROM {$this->table}
                WHERE file_type = 'original'
                GROUP BY entity_type";
        return $this->fetchAll($sql);
    }
    
    /**
     * Relationship: Get MenuItem (for product images)
     * @param string $menuItemId
     * @return array|null
     */
    public function getMenuItem($menuItemId) {
        require_once __DIR__ . '/MenuItem.php';
        $menuItemModel = new MenuItem();
        return $menuItemModel->getById($menuItemId);
    }
    
    /**
     * Relationship: Get Category (for category images)
     * @param string $categoryId
     * @return array|null
     */
    public function getCategory($categoryId) {
        require_once __DIR__ . '/Category.php';
        $categoryModel = new Category();
        return $categoryModel->getById($categoryId);
    }
    
    /**
     * Relationship: Get User (for avatar images)
     * @param string $userId
     * @return array|null
     */
    public function getUser($userId) {
        require_once __DIR__ . '/User.php';
        $userModel = new User();
        return $userModel->getById($userId);
    }
}

