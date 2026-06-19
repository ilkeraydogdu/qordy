<?php
namespace App\Core\Traits;

/**
 * HandlesAPIResponse Trait
 * Provides API response formatting methods for controllers
 */
trait HandlesAPIResponse {
    /**
     * Send API response
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    protected function apiResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Alias for apiResponse for compatibility
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void {
        $this->apiResponse($data, $statusCode);
    }
    
    /**
     * Send success API response
     * @param mixed $data Response data
     * @param string|null $message Success message
     * @param int $statusCode HTTP status code (default: 200)
     * @return void
     */
    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): void {
        $response = ['success' => true];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            if (is_array($data)) {
                $response = array_merge($response, $data);
            } else {
                $response['data'] = $data;
            }
        }
        
        $this->apiResponse($response, $statusCode);
    }
    
    /**
     * Send error API response
     * @param string $error Error message
     * @param string|null $code Error code
     * @param int $statusCode HTTP status code (default: 400)
     * @param array $errors Additional error details
     * @return void
     */
    protected function errorResponse(
        string $error,
        ?string $code = null,
        int $statusCode = 400,
        array $errors = []
    ): void {
        $response = [
            'success' => false,
            'error' => $error
        ];
        
        if ($code !== null) {
            $response['code'] = $code;
        }
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        $this->apiResponse($response, $statusCode);
    }
    
    /**
     * Send validation error API response
     * @param array $errors Validation errors
     * @param string|null $message Error message
     * @return void
     */
    protected function validationErrorResponse(array $errors, ?string $message = null): void {
        $this->errorResponse(
            $message ?? 'Validation failed',
            'VALIDATION_ERROR',
            400,
            $errors
        );
    }
    
    /**
     * Send unauthorized API response
     * @param string|null $message Error message
     * @return void
     */
    protected function unauthorizedResponse(?string $message = null): void {
        $this->errorResponse(
            $message ?? 'Yetkilendirme hatası',
            'UNAUTHORIZED',
            401
        );
    }
    
    /**
     * Send not found API response
     * @param string|null $message Error message
     * @return void
     */
    protected function notFoundResponse(?string $message = null): void {
        $this->errorResponse(
            $message ?? 'Resource not found',
            'NOT_FOUND',
            404
        );
    }
    
    /**
     * Send server error API response
     * @param string|null $message Error message
     * @return void
     */
    protected function serverErrorResponse(?string $message = null): void {
        $this->errorResponse(
            $message ?? 'Internal server error',
            'SERVER_ERROR',
            500
        );
    }
    
    /**
     * Check if current request is an API request
     * @return bool
     */
    protected function isApiRequest(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Check for explicit API routes (CRITICAL: Must check first)
        if (strpos($uri, '/api/') !== false) {
            return true;
        }
        
        // Check for AJAX-specific headers
        if (strtolower($requestedWith) === 'xmlhttprequest') {
            return true;
        }
        
        // Check Content-Type header (including multipart/form-data for FormData submissions)
        if (!empty($contentType)) {
            $contentTypeLower = strtolower($contentType);
            if (strpos($contentTypeLower, 'application/json') !== false ||
                strpos($contentTypeLower, 'multipart/form-data') !== false ||
                strpos($contentTypeLower, 'application/x-www-form-urlencoded') !== false) {
                // For POST/PUT/PATCH requests with these content types, likely AJAX
                if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                    return true;
                }
            }
        }
        
        // Check Accept header
        if (!empty($acceptHeader) && strpos(strtolower($acceptHeader), 'application/json') !== false) {
            return true;
        }
        
        // Check for specific routes that should be treated as API
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Business-related POST routes
            if (strpos($uri, '/qodmin/businesses') !== false && $method === 'POST') {
                return true;
            }
            // Admin shift routes
            if (strpos($uri, '/admin/shifts/') !== false && $method === 'POST') {
                return true;
            }
            if (strpos($uri, '/admin/shifts/save-schedule') !== false ||
                strpos($uri, '/admin/shifts/create-schedule') !== false ||
                strpos($uri, '/admin/shifts/create-weekly-schedule') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Send paginated API response
     * @param array $data Data array
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param int $total Total items count
     * @param array $meta Additional metadata
     * @return void
     */
    protected function paginatedResponse(
        array $data,
        int $page,
        int $perPage,
        int $total,
        array $meta = []
    ): void {
        $totalPages = ceil($total / $perPage);
        
        $response = [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        $this->apiResponse($response);
    }
}

