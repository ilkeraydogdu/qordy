<?php
namespace App\Core;

require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/Traits/HandlesAPIResponse.php';
require_once __DIR__ . '/HelperLoader.php';

use App\Core\Traits\HandlesAPIResponse;

/**
 * Base Controller with centralized services
 * Provides access to TranslationService, NotificationService, etc.
 */
abstract class BaseController {
    use HandlesAPIResponse;
    
    protected $translationService;
    protected $notificationService;
    protected $seoService;
    protected $filterService;
    protected $searchService;
    
    public function __construct() {
        // Ensure helpers are loaded before using helper functions
        HelperLoader::ensureLoaded();
        
        try {
            $this->translationService = getTranslationService();
        } catch (\Error $e) {
            // Fallback: load helpers again if function not found
            require_once __DIR__ . '/../helpers/functions.php';
            $this->translationService = getTranslationService();
        }
        
        try {
            $this->notificationService = getNotificationService();
        } catch (\Error $e) {
            // Fallback: load helpers again if function not found
            require_once __DIR__ . '/../helpers/functions.php';
            $this->notificationService = getNotificationService();
        }
        
        try {
            $this->seoService = getSEOService();
        } catch (\Error $e) {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->seoService = getSEOService();
        }
        
        try {
            $this->filterService = getFilterService();
        } catch (\Error $e) {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->filterService = getFilterService();
        }
        
        try {
            $this->searchService = getSearchService();
        } catch (\Error $e) {
            require_once __DIR__ . '/../helpers/functions.php';
            $this->searchService = getSearchService();
        }
    }
    
    /**
     * Translate a key
     * @param string $key
     * @param array $params
     * @return string
     */
    protected function t($key, $params = []) {
        return $this->translationService->translate($key, null, $params);
    }
    
    /**
     * Get current language
     * @return string
     */
    protected function getCurrentLanguage() {
        return $this->translationService->getCurrentLanguage();
    }
    
    /**
     * Get current user ID from session
     * @return string|null User ID or null if not logged in
     */
    protected function getCurrentUserId(): ?string {
        require_once __DIR__ . '/SessionManager.php';
        \App\Core\SessionManager::ensureSession();
        return \App\Core\SessionManager::get('user_id');
    }
}

