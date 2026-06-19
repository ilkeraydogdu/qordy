<?php
/**
 * Weekly SEO Auto Optimizer Cron Job
 * Runs weekly on Mondays at 03:00 to optimize SEO content
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/core/DependencyFactory.php';
require_once __DIR__ . '/../../app/services/SEOAutoOptimizer.php';

try {
    $optimizer = new \App\Services\SEOAutoOptimizer();
    
    // Run weekly optimization
    $results = $optimizer->runWeeklyOptimization();
    
    // Log results
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::info('Weekly SEO optimization completed', [
            'analyzed' => $results['analyzed'],
            'optimized' => $results['optimized'],
            'suggestions_count' => count($results['suggestions'])
        ]);
    }
    
    echo "SEO optimization completed:\n";
    echo "- Analyzed: {$results['analyzed']} pages\n";
    echo "- Optimized: {$results['optimized']} pages\n";
    echo "- Suggestions: " . count($results['suggestions']) . " pages need attention\n";
    
} catch (\Exception $e) {
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::error('SEO auto optimizer cron error: ' . $e->getMessage());
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
