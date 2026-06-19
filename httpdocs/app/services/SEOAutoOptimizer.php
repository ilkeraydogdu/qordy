<?php
namespace App\Services;

require_once __DIR__ . '/SEOContentService.php';
require_once __DIR__ . '/SEOMonitoringService.php';

/**
 * SEO Auto Optimizer
 * Automatically optimizes SEO content using AI
 */
class SEOAutoOptimizer {
    private $seoContentService;
    private $monitoringService;
    private $db;
    
    public function __construct() {
        try {
            $this->seoContentService = \App\Core\DependencyFactory::getSEOContentService();
            $this->monitoringService = new SEOMonitoringService();
            $this->db = \App\Core\DependencyFactory::getDatabase();
        } catch (\Exception $e) {
            $this->seoContentService = null;
            $this->monitoringService = null;
            $this->db = null;
        }
    }
    
    /**
     * Run weekly SEO optimization
     * @return array Results
     */
    public function runWeeklyOptimization() {
        $results = [
            'analyzed' => 0,
            'optimized' => 0,
            'suggestions' => []
        ];
        
        if (!$this->seoContentService || !$this->db) {
            return $results;
        }
        
        try {
            // Analyze blog posts
            $blogRepo = new \App\Repositories\BlogPostRepository($this->db);
            $posts = $blogRepo->getPublished(100, 0);
            
            foreach ($posts as $post) {
                $results['analyzed']++;
                
                // Analyze SEO
                $analysis = $this->monitoringService->analyzePageSEO(
                    BASE_URL . '/blog/' . $post['slug'],
                    $post['content'],
                    [
                        'title' => $post['meta_title'] ?? $post['title'],
                        'description' => $post['meta_description'] ?? $post['excerpt'],
                        'keywords' => $post['meta_keywords'] ?? ''
                    ]
                );
                
                // If score is low, generate suggestions
                if ($analysis['score'] < 70) {
                    $results['suggestions'][] = [
                        'type' => 'blog_post',
                        'id' => $post['post_id'],
                        'url' => BASE_URL . '/blog/' . $post['slug'],
                        'score' => $analysis['score'],
                        'issues' => $analysis['issues'],
                        'suggestions' => $analysis['suggestions']
                    ];
                }
            }
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SEOAutoOptimizer error: " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Generate content suggestions for low-performing pages
     * @param array $pageData
     * @return array Suggestions
     */
    public function generateSuggestions($pageData) {
        if (!$this->seoContentService) {
            return [];
        }
        
        $keywords = !empty($pageData['keywords']) ? explode(',', $pageData['keywords']) : [];
        
        return $this->seoContentService->optimizeMetaTags(
            $pageData['page'] ?? 'default',
            $pageData['title'] ?? '',
            $pageData['description'] ?? '',
            $keywords,
            $pageData['lang'] ?? 'tr'
        );
    }
}
