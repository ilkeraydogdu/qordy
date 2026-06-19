<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class BlogCategoryRepository extends BaseRepository {
    protected $table = 'blog_categories';
    protected $primaryKey = 'category_id';

    /** Cached per-request flag: is the blog_categories table provisioned? */
    private static $tableReady;

    public function __construct($database) {
        parent::__construct($database);
    }

    private function tableReady(): bool {
        if (self::$tableReady !== null) { return self::$tableReady; }
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'blog_categories'");
            self::$tableReady = (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            self::$tableReady = false;
        }
        return self::$tableReady;
    }

    public function getActive(): array {
        if (!$this->tableReady()) { return []; }
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_active = 1 
                ORDER BY sort_order ASC, name ASC";
        return $this->fetchAll($sql);
    }

    public function getBySlug(string $slug): ?array {
        if (!$this->tableReady()) { return null; }
        $sql = "SELECT * FROM {$this->table} WHERE slug = :slug AND is_active = 1 LIMIT 1";
        return $this->fetchOne($sql, ['slug' => $slug]);
    }

    public function getWithPostCount(): array {
        if (!$this->tableReady()) { return []; }
        $sql = "SELECT bc.*, COUNT(bp.post_id) as post_count 
                FROM {$this->table} bc
                LEFT JOIN blog_posts bp ON bc.category_id = bp.category_id 
                    AND bp.status = 'published'
                    AND (bp.published_at IS NULL OR bp.published_at <= NOW())
                WHERE bc.is_active = 1
                GROUP BY bc.category_id
                ORDER BY bc.sort_order ASC, bc.name ASC";
        return $this->fetchAll($sql);
    }
}
