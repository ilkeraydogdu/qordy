<?php
/**
 * Authentication and Authorization Helper Functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Deprecated: Use AuthHelperService instead
function isLoggedIn() {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->isLoggedIn();
}

// Deprecated: Use AuthHelperService instead
function hasRole($role) {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->hasRole($role);
}

// Deprecated: Use AuthHelperService instead
function hasAnyRole($roles) {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->hasAnyRole($roles);
}

// Deprecated: Use AuthHelperService instead
function requireLogin() {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    $authHelper->requireLogin();
}

// Deprecated: Use AuthHelperService instead
function requireRole($role) {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    $authHelper->requireRole($role);
}

// Deprecated: Use AuthHelperService instead
function requireAnyRole($roles) {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    $authHelper->requireAnyRole($roles);
}

// Deprecated: Use AuthHelperService instead
function authenticateUser($pin) {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->authenticateUser($pin);
}

// Deprecated: Use AuthHelperService instead
function logoutUser() {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    $authHelper->logoutUser();
}

// Deprecated: Use AuthHelperService instead
function isSessionValid() {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->isSessionValid();
}

// Deprecated: Use AuthHelperService instead
function refreshSession() {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    $authHelper->refreshSession();
}

// Deprecated: Use AuthHelperService instead
function getCurrentUser() {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->getCurrentUser();
}

// Deprecated: Use AuthHelperService instead
// These functions are now in security.php - only define if not already defined
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken() {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->generateCSRFToken();
    }
}

// Deprecated: Use AuthHelperService instead
// These functions are now in security.php - only define if not already defined
if (!function_exists('validateCSRFToken')) {
function validateCSRFToken($token) {
    require_once __DIR__ . '/../core/DependencyFactory.php';
    $authHelper = \App\Core\DependencyFactory::getAuthHelperService();
    return $authHelper->validateCSRFToken($token);
    }
}