<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\BlogPostRepository;
use App\Repositories\BlogCategoryRepository;
use App\Services\SEOContentService;

class BlogService extends BaseService {
    private $categoryRepository;
    private $seoContentService;
    
    public function __construct(BlogPostRepository $postRepository, BlogCategoryRepository $categoryRepository) {
        parent::__construct($postRepository);
        $this->categoryRepository = $categoryRepository;
        try {
            $this->seoContentService = \App\Core\DependencyFactory::getSEOContentService();
        } catch (\Exception $e) {
            $this->seoContentService = null;
        }
    }
    
    /**
     * Get repository
     * @return BlogPostRepository
     */
    public function getRepository() {
        return $this->repository;
    }
    
    /**
     * Get published posts
     */
    public function getPublished($limit = 10, $offset = 0): array {
        return $this->repository->getPublished($limit, $offset);
    }
    
    /**
     * Get post by slug
     */
    public function getBySlug(string $slug): ?array {
        $post = $this->repository->getBySlug($slug);
        if ($post) {
            $this->repository->incrementViewCount($post['post_id']);
        }
        return $post;
    }
    
    /**
     * Get categories
     */
    public function getCategories(): array {
        return $this->categoryRepository->getActive();
    }
    
    /**
     * Get category by slug
     */
    public function getCategoryBySlug(string $slug): ?array {
        return $this->categoryRepository->getBySlug($slug);
    }
    
    /**
     * Create blog post with AI SEO optimization
     */
    public function createPost(array $data): string {
        if (empty($data['post_id'])) {
            $data['post_id'] = generateId('post');
        }
        
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }
        
        // Use AI to optimize SEO if available
        if ($this->seoContentService && !empty($data['content'])) {
            $keywords = !empty($data['meta_keywords']) ? explode(',', $data['meta_keywords']) : [];
            $optimized = $this->seoContentService->optimizeMetaTags(
                'blog',
                $data['title'],
                $data['excerpt'] ?? substr(strip_tags($data['content']), 0, 160),
                $keywords,
                'tr'
            );
            
            if (!empty($optimized['title'])) {
                $data['meta_title'] = $optimized['title'];
            }
            if (!empty($optimized['description'])) {
                $data['meta_description'] = $optimized['description'];
            }
        }
        
        // Set published_at if status is published
        if (($data['status'] ?? 'draft') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        
        $postId = $this->repository->create($data);
        
        // Set tags if provided
        if (!empty($data['tags']) && is_array($data['tags'])) {
            $this->repository->setTags($postId, $data['tags']);
        }
        
        return $postId;
    }
    
    /**
     * Generate URL-friendly slug
     */
    private function generateSlug(string $title): string {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
}
