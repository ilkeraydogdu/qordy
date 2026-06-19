<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
require_once __DIR__ . '/../../services/BlogContentGeneratorService.php';

class BlogManagementController extends \App\Core\Controller {
    protected $generatorService;
    protected $blogService;
    
    public function __construct() {
        parent::__construct();
        $this->requirePermission('admin.settings'); // Only admins can manage blog
        
        $this->generatorService = \App\Core\DependencyFactory::getBlogContentGeneratorService();
        $this->blogService = \App\Core\DependencyFactory::getBlogService();
    }
    
    /**
     * Blog management dashboard
     */
    public function index() {
        $unpublishedTopics = $this->generatorService->getUnpublishedTopics();
        $allTopics = $this->generatorService->getAllTopics();
        
        $data = [
            'unpublished_topics' => $unpublishedTopics,
            'all_topics' => $allTopics,
            'published_count' => count($allTopics) - count($unpublishedTopics),
            'total_count' => count($allTopics)
        ];
        
        $this->view('admin/blog_management', $data);
    }
    
    /**
     * Generate blog post via API
     */
    public function generatePost() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $topicId = $input['topic_id'] ?? null;
        
        if ($topicId !== null) {
            // Generate specific topic
            $allTopics = $this->generatorService->getAllTopics();
            $topic = $allTopics[$topicId] ?? null;
            
            if (!$topic) {
                echo json_encode(['success' => false, 'error' => 'Topic not found']);
                return;
            }
            
            $result = $this->generatorService->generateBlogPost($topic);
        } else {
            // Generate next priority topic
            $result = $this->generatorService->generateNextPost();
        }
        
        echo json_encode($result);
    }
    
    /**
     * Get unpublished topics
     */
    public function getUnpublishedTopics() {
        header('Content-Type: application/json');
        
        $topics = $this->generatorService->getUnpublishedTopics();
        
        echo json_encode([
            'success' => true,
            'topics' => $topics,
            'count' => count($topics)
        ]);
    }
    
    /**
     * Optimize all blog posts
     */
    public function optimizeAll() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Invalid request method']);
            return;
        }
        
        try {
            $blogRepo = \App\Core\DependencyFactory::getBlogPostRepository();
            $monitoringService = new \App\Services\SEOMonitoringService();
            $seoContentService = \App\Core\DependencyFactory::getSEOContentService();
            
            $posts = $blogRepo->getPublished(100, 0);
            $optimized = 0;
            
            foreach ($posts as $post) {
                if ($seoContentService) {
                    $keywords = !empty($post['meta_keywords']) ? explode(',', $post['meta_keywords']) : [];
                    $optimizedTags = $seoContentService->optimizeMetaTags(
                        'blog',
                        $post['title'],
                        $post['excerpt'] ?? substr(strip_tags($post['content']), 0, 160),
                        $keywords,
                        'tr'
                    );
                    
                    $updateData = [];
                    if (!empty($optimizedTags['title'])) {
                        $updateData['meta_title'] = $optimizedTags['title'];
                    }
                    if (!empty($optimizedTags['description'])) {
                        $updateData['meta_description'] = $optimizedTags['description'];
                    }
                    
                    if (!empty($updateData)) {
                        $blogRepo->update($post['post_id'], $updateData);
                        $optimized++;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'optimized' => $optimized,
                'total' => count($posts)
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
