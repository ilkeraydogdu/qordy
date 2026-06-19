<?php
/**
 * Blog Content Generator Cron Job
 * Automatically generates SEO-optimized blog content using Gemini AI
 * Runs weekly on Wednesdays at 04:00
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/core/DependencyFactory.php';
require_once __DIR__ . '/../../app/services/BlogContentGeneratorService.php';

try {
    $generator = new \App\Services\BlogContentGeneratorService();
    
    echo "Starting blog content generation...\n";
    echo "=====================================\n\n";
    
    // Check if there are unpublished topics
    $unpublishedTopics = $generator->getUnpublishedTopics();
    
    if (empty($unpublishedTopics)) {
        echo "All topics have been published. No new content to generate.\n";
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Blog content generator: All topics published');
        }
        
        exit(0);
    }
    
    echo "Found " . count($unpublishedTopics) . " unpublished topics.\n";
    echo "Generating highest priority topic...\n\n";
    
    // Generate next blog post
    $result = $generator->generateNextPost();
    
    if ($result['success']) {
        echo "✅ Blog post generated successfully!\n";
        echo "   Title: {$result['title']}\n";
        echo "   Slug: {$result['slug']}\n";
        echo "   Post ID: {$result['post_id']}\n";
        echo "   URL: " . BASE_URL . "/blog/{$result['slug']}\n";
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('Blog content generated successfully', [
                'post_id' => $result['post_id'],
                'title' => $result['title'],
                'slug' => $result['slug']
            ]);
        }
    } else {
        echo "❌ Error generating blog post: {$result['error']}\n";
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error('Blog content generation failed', [
                'error' => $result['error']
            ]);
        }
        
        exit(1);
    }
    
    echo "\n=====================================\n";
    echo "Blog content generation completed.\n";
    
} catch (\Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::error('Blog content generator cron error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    exit(1);
}
