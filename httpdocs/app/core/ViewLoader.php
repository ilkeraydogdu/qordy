<?php
namespace App\Core;

/**
 * View Loader
 * Centralized view loading system that automatically loads required helpers
 * Eliminates the need for manual require_once statements in view files
 */
class ViewLoader {
    private static $loadedHelpers = false;
    
    /**
     * Load view file with automatic helper loading
     * @param string $viewPath Path to view file (relative to app/views/)
     * @param array $data Data to pass to view
     * @return void
     */
    public static function load(string $viewPath, array $data = []): void {
        // Ensure helpers are loaded
        self::ensureHelpersLoaded();
        
        // Extract variables from data array
        extract($data);
        
        // Load view file
        $fullPath = __DIR__ . '/../views/' . ltrim($viewPath, '/');
        if (file_exists($fullPath)) {
            require $fullPath;
        } else {
            throw new \Exception("View file not found: {$viewPath}");
        }
    }
    
    /**
     * Ensure all required helpers are loaded
     */
    private static function ensureHelpersLoaded(): void {
        if (self::$loadedHelpers) {
            return;
        }
        
        // Load helpers using HelperLoader
        HelperLoader::loadHelpers();
        
        // Ensure BASE_URL is defined
        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/config.php';
        }
        
        self::$loadedHelpers = true;
    }
    
    /**
     * Check if a helper function exists
     * @param string $functionName Function name
     * @return bool
     */
    public static function helperExists(string $functionName): bool {
        self::ensureHelpersLoaded();
        return function_exists($functionName);
    }
}

