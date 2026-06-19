<?php
namespace App\Core\Traits;

use App\Core\Authorization;

/**
 * HasPermissions Trait
 * Provides permission checking methods for controllers
 */
trait HasPermissions {
    /**
     * Check if user has a specific permission
     * @param string $permission Permission name
     * @return bool
     */
    protected function hasPermission(string $permission): bool {
        if (!isset($this->auth) || $this->auth === null) {
            return false;
        }
        return $this->auth->hasPermission($permission);
    }
    
    /**
     * Require a specific permission, redirect or return API error if not authorized
     * @param string $permission Permission name
     * @param bool $redirect Whether to redirect for non-API requests
     * @return bool True if authorized, false if not (response already sent)
     */
    protected function requirePermission(string $permission, bool $redirect = true): bool {
        try {
            // CRITICAL: Super Admin bypass - check before auth check
            if (method_exists($this, 'isSuperAdmin') && $this->isSuperAdmin()) {
                return true;
            }
            
            if (!isset($this->auth) || $this->auth === null) {
                if ($redirect) {
                    // Safely check if isApiRequest method exists before calling
                    $isApiRequest = method_exists($this, 'isApiRequest') ? $this->isApiRequest() : false;
                    
                    if ($isApiRequest) {
                        \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401);
                        return false;
                    }
                    
                    // CRITICAL: Use current host (with subdomain) for redirect
                    $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                    exit;
                }
                return false;
            }
            
            $hasPerm = $this->hasPermission($permission);
            
            if (!$hasPerm) {
                // Log detailed permission denial info
                if (class_exists('\App\Core\Logger')) {
                    $currentRole = method_exists($this->auth, 'getCurrentRole') ? $this->auth->getCurrentRole() : 'unknown';
                    $customerId = method_exists($this->auth, 'getCurrentCustomerId') ? $this->auth->getCurrentCustomerId() : null;
                    
                    \App\Core\Logger::warning("HasPermissions::requirePermission - Permission denied", [
                        'permission' => $permission,
                        'current_role' => $currentRole,
                        'customer_id' => $customerId,
                        'is_logged_in' => method_exists($this->auth, 'isLoggedIn') ? $this->auth->isLoggedIn() : false,
                        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
                    ]);
                }
                
                // Safely check if isApiRequest method exists before calling
                $isApiRequest = method_exists($this, 'isApiRequest') ? $this->isApiRequest() : false;
                
                if ($isApiRequest) {
                    \App\Core\ResponseHandler::error('Bu işlem için yetkiniz bulunmamaktadır', 'FORBIDDEN', 403);
                    return false;
                }
                
                return $this->auth->requirePermission($permission, $redirect);
            }
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug("HasPermissions::requirePermission - Permission granted", [
                    'permission' => $permission,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            // Safely check if isApiRequest method exists before calling
            $isApiRequest = method_exists($this, 'isApiRequest') ? $this->isApiRequest() : false;
            if ($isApiRequest) {
                \App\Core\Logger::error("requirePermission exception: " . $e->getMessage());
                \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401, ['message' => $e->getMessage()]);
                return false;
            }
            throw $e;
        }
    }
    
    /**
     * Require any of the given permissions
     * @param array $permissions Array of permission names
     * @param bool $redirect Whether to redirect for non-API requests
     * @return bool True if authorized, false if not
     */
    protected function requireAnyPermission(array $permissions, bool $redirect = true): bool {
        if (!isset($this->auth) || $this->auth === null) {
            if ($redirect) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                exit;
            }
            return false;
        }
        return $this->auth->requireAnyPermission($permissions, $redirect);
    }
    
    /**
     * Check permission and return API error if not authorized
     * @param string $permission Permission to check
     * @param string|null $errorMessage Custom error message
     * @return bool True if authorized, false if not (response already sent)
     */
    protected function checkPermissionOrFail(string $permission, ?string $errorMessage = null): bool {
        if (!isset($this->auth) || $this->auth === null) {
            try {
                $this->auth = Authorization::getInstance();
            } catch (\Exception $e) {
                error_log('Failed to reinitialize Authorization in checkPermissionOrFail: ' . $e->getMessage());
            }
        }
        
        $hasPerm = $this->hasPermission($permission);
        
        if (!$hasPerm) {
            // Use isApiRequest() method from HandlesAPIResponse trait
            // This method will be available when both traits are used together
            if (method_exists($this, 'isApiRequest') && $this->isApiRequest()) {
                \App\Core\ResponseHandler::error($errorMessage ?? 'Yetkilendirme hatası', 'UNAUTHORIZED', 401);
            } else {
                // CRITICAL: Use current host (with subdomain) for redirect to preserve subdomain
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $unauthorizedUrl = $protocol . '://' . $currentHost . '/unauthorized';
                header('Location: ' . $unauthorizedUrl);
                exit;
            }
            return false;
        }
        return true;
    }
    
    /**
     * Check if user has a specific role
     * @param string $role Role name
     * @return bool
     */
    protected function hasRole(string $role): bool {
        return isset($this->auth) && $this->auth !== null && $this->auth->hasRole($role);
    }
    
    /**
     * Check if current user is super admin
     * @return bool True if user is super admin
     */
    protected function isSuperAdmin(): bool {
        return isset($this->auth) && $this->auth !== null && $this->auth->isSuperAdmin();
    }
    
    /**
     * Require a specific role
     * @param string $requiredRole Role name
     * @param bool $redirect Whether to redirect if not authorized
     * @return bool True if authorized, false if not
     */
    protected function requireRole(string $requiredRole, bool $redirect = true): bool {
        if (!isset($this->auth) || $this->auth === null) {
            if ($redirect) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                exit;
            }
            return false;
        }
        return $this->auth->requireRole($requiredRole, $redirect);
    }
    
    /**
     * Require any of the given roles
     * @param array $requiredRoles Array of role names
     * @param bool $redirect Whether to redirect if not authorized
     * @return bool True if authorized, false if not
     */
    protected function requireAnyRole(array $requiredRoles, bool $redirect = true): bool {
        if (!isset($this->auth) || $this->auth === null) {
            if ($redirect) {
                // CRITICAL: Use current host (with subdomain) for redirect
                $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                header('Location: ' . $protocol . '://' . $currentHost . '/unauthorized');
                exit;
            }
            return false;
        }
        return $this->auth->requireAnyRole($requiredRoles, $redirect);
    }
    
    // isApiRequest method is provided by HandlesAPIResponse trait
    // apiResponse method is provided by HandlesAPIResponse trait
}

