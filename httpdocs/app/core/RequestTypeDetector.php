<?php
namespace App\Core;

/**
 * Request Type Detector
 * Determines if a request is an API request or a form request
 * This allows different security policies for different request types
 */
class RequestTypeDetector {
    /**
     * Check if the current request is an API request
     * @return bool
     */
    public static function isAPIRequest(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        
        // Check if path starts with /api/
        if (strpos($path, '/api/') === 0) {
            return true;
        }
        
        // Check for POST requests to /admin/shifts/* paths (these are API endpoints)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($path, '/admin/shifts/') !== false) {
            return true;
        }
        
        // Check X-Requested-With header for XMLHttpRequest (AJAX requests)
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($requestedWith) === 'xmlhttprequest') {
            return true;
        }
        
        // Check Content-Type header for JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }
        
        // Check Accept header for JSON
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the current request is a form request
     * @return bool
     */
    public static function isFormRequest(): bool {
        // If it's an API request, it's not a form request
        if (self::isAPIRequest()) {
            return false;
        }
        
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        
        // Login endpoint is not a form request for CSRF purposes (protected by PIN)
        if (strpos($path, '/login') === 0) {
            return false;
        }
        
        // Check Content-Type for form data
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            return true;
        }
        
        if (strpos($contentType, 'multipart/form-data') !== false) {
            return true;
        }
        
        // If POST request with form-like data, assume it's a form
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get request type
     * @return string 'api'|'form'|'other'
     */
    public static function getRequestType(): string {
        if (self::isAPIRequest()) {
            return 'api';
        }
        
        if (self::isFormRequest()) {
            return 'form';
        }
        
        return 'other';
    }
}

