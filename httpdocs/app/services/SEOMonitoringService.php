<?php
namespace App\Services;

require_once __DIR__ . '/SEOContentService.php';

/**
 * SEO Monitoring Service
 * Tracks SEO performance and provides analytics
 */
class SEOMonitoringService {
    private $seoContentService;
    private $db;
    
    public function __construct() {
        try {
            $this->seoContentService = \App\Core\DependencyFactory::getSEOContentService();
            $this->db = \App\Core\DependencyFactory::getDatabase();
        } catch (\Exception $e) {
            $this->seoContentService = null;
            $this->db = null;
        }
    }
    
    /**
     * Analyze page SEO score
     * @param string $url
     * @param string $content
     * @param array $metaTags
     * @return array
     */
    public function analyzePageSEO($url, $content, $metaTags = []) {
        $score = 0;
        $issues = [];
        $suggestions = [];
        
        // Check meta title
        if (empty($metaTags['title'])) {
            $issues[] = 'Meta title eksik';
        } elseif (strlen($metaTags['title']) > 60) {
            $issues[] = 'Meta title çok uzun (60 karakterden fazla)';
            $score -= 10;
        } else {
            $score += 20;
        }
        
        // Check meta description
        if (empty($metaTags['description'])) {
            $issues[] = 'Meta description eksik';
        } elseif (strlen($metaTags['description']) > 160) {
            $issues[] = 'Meta description çok uzun (160 karakterden fazla)';
            $score -= 10;
        } else {
            $score += 20;
        }
        
        // Check keywords
        if (empty($metaTags['keywords'])) {
            $issues[] = 'Meta keywords eksik';
        } else {
            $score += 10;
        }
        
        // Check content length
        $contentLength = strlen(strip_tags($content));
        if ($contentLength < 300) {
            $issues[] = 'İçerik çok kısa (en az 300 karakter önerilir)';
            $score -= 15;
        } elseif ($contentLength > 2000) {
            $score += 15;
        }
        
        // Check headings
        if (strpos($content, '<h1>') === false && strpos($content, '<h2>') === false) {
            $issues[] = 'Başlık etiketleri (H1, H2) eksik';
            $score -= 10;
        } else {
            $score += 10;
        }
        
        // Use AI for content quality if available
        if ($this->seoContentService) {
            $keywords = !empty($metaTags['keywords']) ? explode(',', $metaTags['keywords']) : [];
            $quality = $this->seoContentService->scoreContentQuality($content, $keywords, 'tr');
            $score += ($quality['score'] ?? 0) * 0.25; // 25% weight
            
            if (!empty($quality['suggestions'])) {
                $suggestions = array_merge($suggestions, $quality['suggestions']);
            }
        }
        
        // Ensure score is between 0-100
        $score = max(0, min(100, $score));
        
        return [
            'url' => $url,
            'score' => round($score),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'content_length' => $contentLength,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get SEO dashboard data
     * @return array
     */
    public function getDashboardData() {
        // This would typically fetch from a database table
        // For now, return sample structure
        return [
            'total_pages' => 0,
            'average_score' => 0,
            'pages_needing_attention' => [],
            'top_keywords' => [],
            'recent_analyses' => []
        ];
    }
}
