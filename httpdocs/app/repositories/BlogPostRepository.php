<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class BlogPostRepository extends BaseRepository {
    protected $table = 'blog_posts';
    protected $primaryKey = 'post_id';

    /**
     * Blog tables are optional — the core product works without them.
     * Check once per request and skip queries if they don't exist, so
     * fresh environments / minimal schemas don't emit SQL errors into
     * the access log for `/blog-archive*`.
     */
    private static $tablesReady;

    public function __construct($database) {
        parent::__construct($database);
    }

    private function tablesReady(): bool {
        if (self::$tablesReady !== null) { return self::$tablesReady; }
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'blog_posts'");
            $ok1 = (bool)$stmt->fetch();
            $stmt = $this->db->query("SHOW TABLES LIKE 'blog_categories'");
            $ok2 = (bool)$stmt->fetch();
            self::$tablesReady = $ok1 && $ok2;
        } catch (\Throwable $e) {
            self::$tablesReady = false;
        }
        return self::$tablesReady;
    }

    public function getPublished($limit = 10, $offset = 0): array {
        if (!$this->tablesReady()) { return []; }
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug 
                FROM {$this->table} bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.category_id
                WHERE bp.status = 'published' 
                AND (bp.published_at IS NULL OR bp.published_at <= NOW())
                ORDER BY bp.published_at DESC, bp.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get post by slug
     * @param string $slug
     * @return array|null
     */
    public function getBySlug(string $slug): ?array {
        if (!$this->tablesReady()) { return null; }
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug 
                FROM {$this->table} bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.category_id
                WHERE bp.slug = :slug 
                LIMIT 1";
        
        $result = $this->fetchOne($sql, ['slug' => $slug]);
        
        if ($result) {
            // Get tags
            $result['tags'] = $this->getTags($result['post_id']);
        }
        
        return $result;
    }

    /**
     * Get posts by category
     * @param string $categoryId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByCategory(string $categoryId, $limit = 10, $offset = 0): array {
        if (!$this->tablesReady()) { return []; }
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug 
                FROM {$this->table} bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.category_id
                WHERE bp.category_id = :category_id 
                AND bp.status = 'published'
                AND (bp.published_at IS NULL OR bp.published_at <= NOW())
                ORDER BY bp.published_at DESC, bp.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category_id', $categoryId);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get featured posts
     * @param int $limit
     * @return array
     */
    public function getFeatured($limit = 5): array {
        if (!$this->tablesReady()) { return []; }
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug 
                FROM {$this->table} bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.category_id
                WHERE bp.is_featured = 1 
                AND bp.status = 'published'
                AND (bp.published_at IS NULL OR bp.published_at <= NOW())
                ORDER BY bp.sort_order ASC, bp.published_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get related posts
     * @param string $postId
     * @param string $categoryId
     * @param int $limit
     * @return array
     */
    public function getRelated(string $postId, string $categoryId, $limit = 5): array {
        if (!$this->tablesReady()) { return []; }
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug 
                FROM {$this->table} bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.category_id
                WHERE bp.category_id = :category_id 
                AND bp.post_id != :post_id
                AND bp.status = 'published'
                AND (bp.published_at IS NULL OR bp.published_at <= NOW())
                ORDER BY bp.published_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category_id', $categoryId);
        $stmt->bindValue(':post_id', $postId);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Increment view count
     * @param string $postId
     * @return bool
     */
    public function incrementViewCount(string $postId): bool {
        if (!$this->tablesReady()) { return false; }
        $sql = "UPDATE {$this->table} SET view_count = view_count + 1 WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, ['id' => $postId]);
    }

    /**
     * Get tags for a post
     * @param string $postId
     * @return array
     */
    public function getTags(string $postId): array {
        if (!$this->tablesReady()) { return []; }
        $sql = "SELECT tag FROM blog_post_tags WHERE post_id = :post_id ORDER BY tag ASC";
        try {
            $results = $this->fetchAll($sql, ['post_id' => $postId]);
        } catch (\Throwable $e) { return []; }
        return array_column($results, 'tag');
    }

    /**
     * Set tags for a post
     * @param string $postId
     * @param array $tags
     * @return bool
     */
    public function setTags(string $postId, array $tags): bool {
        // Delete existing tags
        $this->db->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$postId]);
        
        // Insert new tags
        $stmt = $this->db->prepare("INSERT INTO blog_post_tags (post_id, tag) VALUES (?, ?)");
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (!empty($tag)) {
                $stmt->execute([$postId, $tag]);
            }
        }
        
        return true;
    }

    /**
     * Search posts
     * @param string $query
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function search(string $query, $limit = 10, $offset = 0): array {
        if (!$this->tablesReady()) { return []; }
        $sql = "SELECT bp.*, bc.name as category_name, bc.slug as category_slug 
                FROM {$this->table} bp
                LEFT JOIN blog_categories bc ON bp.category_id = bc.category_id
                WHERE bp.status = 'published'
                AND (bp.published_at IS NULL OR bp.published_at <= NOW())
                AND (bp.title LIKE :query OR bp.content LIKE :query OR bp.excerpt LIKE :query)
                ORDER BY bp.published_at DESC
                LIMIT :limit OFFSET :offset";
        
        $searchQuery = '%' . $query . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $searchQuery);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of published posts
     * @return int
     */
    public function getPublishedCount(): int {
        if (!$this->tablesReady()) { return 0; }
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE status = 'published' 
                AND (published_at IS NULL OR published_at <= NOW())";
        $result = $this->fetchOne($sql);
        return (int)($result['count'] ?? 0);
    }
}
