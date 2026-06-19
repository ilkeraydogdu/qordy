<?php
/**
 * Daily SEO Checker Cron Job
 * Runs daily at 02:00 to check SEO performance
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../app/core/DependencyFactory.php';
require_once __DIR__ . '/../../app/services/SEOMonitoringService.php';

try {
    $monitoringService = new \App\Services\SEOMonitoringService();
    
    // Get dashboard data
    $dashboardData = $monitoringService->getDashboardData();
    
    // Log results
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::info('Daily SEO check completed', [
            'total_pages' => $dashboardData['total_pages'],
            'average_score' => $dashboardData['average_score']
        ]);
    }
    
    echo "SEO check completed successfully\n";
    
} catch (\Exception $e) {
    if (class_exists('\App\Core\Logger')) {
        \App\Core\Logger::error('SEO checker cron error: ' . $e->getMessage());
    }
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
