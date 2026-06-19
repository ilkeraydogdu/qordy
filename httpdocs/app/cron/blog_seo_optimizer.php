<?php
/**
 * Blog SEO Optimizer Cron Job
 * Automatically optimizes existing blog posts using Gemini AI
 * Runs monthly on the 1st at 05:00
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/core/DependencyFactory.php';
require_once __DIR__ . '/../../app/services/SEOMonitoringService.php';

try {
    $blogRepo = \App\Core\DependencyFactory::getBlogPostRepository();
    $monitoringService = new \App\Services\SEOMonitoringService();
    $seoContentService = \App\Core\DependencyFactory::getSEOContentService();
    
    echo "Starting blog SEO optimization...\n";
    echo "=====================================\n\n";
    
    // Get all published posts
    $posts = $blogRepo->getPublished(100, 0);
    
    if (empty($posts)) {
        echo "No published posts found.\n";
        exit(0);
    }
    
    echo "Found " . count($posts) . " published posts.\n";
    echo "Analyzing SEO scores...\n\n";
    
    $optimized = 0;
    $needsOptimization = [];
    
    foreach ($posts as $post) {
        // Analyze SEO
        $analysis = $monitoringService->analyzePageSEO(
            BASE_URL . '/blog/' . $post['slug'],
            $post['content'],
            [
                'title' => $post['meta_title'] ?? $post['title'],
                'description' => $post['meta_description'] ?? $post['excerpt'],
                'keywords' => $post['meta_keywords'] ?? ''
            ]
        );
        
        echo "Post: {$post['title']}\n";
        echo "  SEO Score: {$analysis['score']}/100\n";
        
        // If score is below 70, optimize
        if ($analysis['score'] < 70) {
            echo "  ⚠️  Score below 70, optimizing...\n";
            
            $needsOptimization[] = [
                'post' => $post,
                'analysis' => $analysis
            ];
            
            // Optimize meta tags if SEO service is available
            if ($seoContentService) {
                $keywords = !empty($post['meta_keywords']) ? explode(',', $post['meta_keywords']) : [];
                $optimizedTags = $seoContentService->optimizeMetaTags(
                    'blog',
                    $post['title'],
                    $post['excerpt'] ?? substr(strip_tags($post['content']), 0, 160),
                    $keywords,
                    'tr'
                );
                
                // Update post with optimized meta tags
                $updateData = [];
                if (!empty($optimizedTags['title']) && $optimizedTags['title'] !== $post['meta_title']) {
                    $updateData['meta_title'] = $optimizedTags['title'];
                }
                if (!empty($optimizedTags['description']) && $optimizedTags['description'] !== $post['meta_description']) {
                    $updateData['meta_description'] = $optimizedTags['description'];
                }
                
                if (!empty($updateData)) {
                    $blogRepo->update($post['post_id'], $updateData);
                    $optimized++;
                    echo "  ✅ Meta tags optimized\n";
                }
            }
        } else {
            echo "  ✅ Score is good\n";
        }
        
        echo "\n";
    }
    
    echo "=====================================\n";
    echo "SEO optimization completed.\n";
    echo "Posts analyzed: " . count($posts) . "\n";
    echo "Posts optimized: {$optimized}\n";
    echo "Posts needing attention: " . count($needsOptimization) . "\n";
    
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::info('Blog SEO optimization completed', [
            'analyzed' => count($posts),
            'optimized' => $optimized,
            'needs_attention' => count($needsOptimization)
        ]);
    }
    
} catch (\Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::error('Blog SEO optimizer cron error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    exit(1);
}
