<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * MediaFile Repository
 * 
 * Handles database operations for media files
 * Provides query methods for retrieving, creating, updating, and deleting media records
 */
class MediaFileRepository extends BaseRepository {
    
    protected $table = 'media_files';
    protected $primaryKey = 'id';
    
    /**
     * Constructor
     * @param \PDO $database
     */
    public function __construct($database) {
        parent::__construct($database);
    }
    
    /**
     * Find media files by entity
     * @param string $entityType
     * @param string $entityId
     * @param string|null $fileType Optional filter by file type
     * @return array
     */
    public function findByEntity($entityType, $entityId, $fileType = null) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE entity_type = :entity_type 
                AND entity_id = :entity_id";
        
        $params = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ];
        
        if ($fileType !== null) {
            $sql .= " AND file_type = :file_type";
            $params['file_type'] = $fileType;
        }
        
        $sql .= " ORDER BY is_primary DESC, sort_order ASC, created_at DESC";
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Find primary image for entity
     * @param string $entityType
     * @param string $entityId
     * @param string $fileType
     * @return array|null
     */
    public function findPrimaryImage($entityType, $entityId, $fileType = 'original') {
        $sql = "SELECT * FROM {$this->table} 
                WHERE entity_type = :entity_type 
                AND entity_id = :entity_id 
                AND file_type = :file_type 
                AND is_primary = 1 
                LIMIT 1";
        
        return $this->fetchOne($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'file_type' => $fileType,
        ]);
    }
    
    /**
     * Find all by entity type
     * @param string $entityType
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findAllByType($entityType, $limit = 100, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE entity_type = :entity_type 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':entity_type', $entityType, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Find all media files with pagination
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function findPaginated($page = 1, $perPage = 20, $filters = []) {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }
        
        if (!empty($filters['entity_id'])) {
            $sql .= " AND entity_id = :entity_id";
            $params['entity_id'] = $filters['entity_id'];
        }
        
        if (!empty($filters['file_type'])) {
            $sql .= " AND file_type = :file_type";
            $params['file_type'] = $filters['file_type'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Count total records
     * @param array $filters
     * @return int
     */
    public function countTotal($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }
        
        if (!empty($filters['entity_id'])) {
            $sql .= " AND entity_id = :entity_id";
            $params['entity_id'] = $filters['entity_id'];
        }
        
        if (!empty($filters['file_type'])) {
            $sql .= " AND file_type = :file_type";
            $params['file_type'] = $filters['file_type'];
        }
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Set image as primary
     * @param string $entityType
     * @param string $entityId
     * @param int $mediaId
     * @return bool
     */
    public function setPrimary($entityType, $entityId, $mediaId) {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Remove primary flag from all images of this entity
            $sql = "UPDATE {$this->table} 
                    SET is_primary = 0 
                    WHERE entity_type = :entity_type 
                    AND entity_id = :entity_id";
            
            $this->execute($sql, [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
            
            // Set the specified image as primary
            $sql = "UPDATE {$this->table} 
                    SET is_primary = 1 
                    WHERE id = :id";
            
            $this->execute($sql, ['id' => $mediaId]);
            
            // Commit transaction
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Set primary error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update sort order
     * @param array $orderMap Array of id => sort_order
     * @return bool
     */
    public function updateSortOrder($orderMap) {
        try {
            $this->db->beginTransaction();
            
            $sql = "UPDATE {$this->table} SET sort_order = :sort_order WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            foreach ($orderMap as $id => $order) {
                $stmt->execute([
                    'id' => $id,
                    'sort_order' => $order,
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Update sort order error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete by entity
     * @param string $entityType
     * @param string $entityId
     * @return bool
     */
    public function deleteByEntity($entityType, $entityId) {
        $sql = "DELETE FROM {$this->table} 
                WHERE entity_type = :entity_type 
                AND entity_id = :entity_id";
        
        return $this->execute($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }
    
    /**
     * Find orphaned files (no entity_id or entity doesn't exist)
     * @param int $olderThanHours
     * @return array
     */
    public function findOrphaned($olderThanHours = 24) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE (entity_id IS NULL OR entity_id = '') 
                AND created_at < DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        
        return $this->fetchAll($sql, ['hours' => $olderThanHours]);
    }
    
    /**
     * Get statistics by entity type
     * @return array
     */
    public function getStatistics() {
        $sql = "SELECT 
                    entity_type,
                    COUNT(DISTINCT entity_id) as entity_count,
                    COUNT(*) as file_count,
                    SUM(file_size) as total_size,
                    AVG(file_size) as avg_size,
                    MIN(created_at) as first_upload,
                    MAX(created_at) as last_upload
                FROM {$this->table}
                GROUP BY entity_type
                ORDER BY file_count DESC";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Get total storage size
     * @param string|null $entityType
     * @return int Bytes
     */
    public function getTotalSize($entityType = null) {
        $sql = "SELECT SUM(file_size) as total_size FROM {$this->table}";
        $params = [];
        
        if ($entityType !== null) {
            $sql .= " WHERE entity_type = :entity_type";
            $params['entity_type'] = $entityType;
        }
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)($result['total_size'] ?? 0) : 0;
    }
    
    /**
     * Count files by entity
     * @param string $entityType
     * @param string $entityId
     * @param bool $originalOnly Count only original files (not variants)
     * @return int
     */
    public function countByEntity($entityType, $entityId, $originalOnly = true) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE entity_type = :entity_type 
                AND entity_id = :entity_id";
        
        $params = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ];
        
        if ($originalOnly) {
            $sql .= " AND file_type = 'original'";
        }
        
        $result = $this->fetchOne($sql, $params);
        return $result ? (int)$result['total'] : 0;
    }
    
    /**
     * Find related images (all variants of same upload)
     * @param int $id
     * @return array
     */
    public function findRelated($id) {
        $media = $this->findById($id);
        if (!$media) {
            return [];
        }
        
        // Get stored filename without size suffix
        $baseFilename = pathinfo($media['stored_filename'], PATHINFO_FILENAME);
        
        // Remove size suffix (e.g., -thumbnail, -medium, -large)
        $baseFilename = preg_replace('/-(?:thumbnail|medium|large|original)$/', '', $baseFilename);
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE entity_type = :entity_type 
                AND entity_id = :entity_id 
                AND stored_filename LIKE :pattern
                ORDER BY file_type";
        
        return $this->fetchAll($sql, [
            'entity_type' => $media['entity_type'],
            'entity_id' => $media['entity_id'],
            'pattern' => '%' . $baseFilename . '%',
        ]);
    }
    
    /**
     * Search media files
     * @param string $query
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public function search($query, $filters = [], $limit = 50) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($query)) {
            $sql .= " AND (
                original_filename LIKE :query 
                OR stored_filename LIKE :query 
                OR alt_text LIKE :query 
                OR title LIKE :query
            )";
            $params['query'] = '%' . $query . '%';
        }
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }
        
        if (!empty($filters['uploaded_by'])) {
            $sql .= " AND uploaded_by = :uploaded_by";
            $params['uploaded_by'] = $filters['uploaded_by'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Bulk insert media files
     * @param array $records Array of media file data
     * @return bool
     */
    public function bulkInsert($records) {
        if (empty($records)) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            foreach ($records as $record) {
                $this->create($record);
            }
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Bulk insert error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update metadata (alt_text, title, etc.)
     * @param int $id
     * @param array $metadata
     * @return bool
     */
    public function updateMetadata($id, $metadata) {
        $allowedFields = ['alt_text', 'title', 'sort_order', 'is_primary'];
        $data = [];
        
        foreach ($allowedFields as $field) {
            if (isset($metadata[$field])) {
                $data[$field] = $metadata[$field];
            }
        }
        
        if (empty($data)) {
            return false;
        }
        
        return $this->update($id, $data);
    }
}

