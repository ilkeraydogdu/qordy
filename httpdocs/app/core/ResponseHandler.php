<?php
namespace App\Core;

/**
 * ResponseHandler
 * Centralized response handling for HTTP responses
 */
class ResponseHandler {
    /**
     * Send JSON response
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @param bool $exit Whether to exit after sending
     * @return void
     */
    public static function json(array $data, int $statusCode = 200, bool $exit = true): void {
        http_response_code($statusCode);
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($exit) {
            exit;
        }
    }
    
    /**
     * Send success response
     * @param mixed $data Response data
     * @param string|null $message Success message
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function success($data = null, ?string $message = null, int $statusCode = 200): void {
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
        
        self::json($response, $statusCode);
    }
    
    /**
     * Send error response
     * @param string $error Error message
     * @param string|null $code Error code
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     * @return void
     */
    public static function error(
        string $error,
        ?string $code = null,
        int $statusCode = 400,
        array $errors = []
    ): void {
        // CRITICAL: Log permission denied errors with stack trace for debugging
        if ($statusCode === 403 && strpos($error, 'yetkiniz') !== false) {
            if (class_exists('\App\Core\Logger')) {
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
                \App\Core\Logger::warning("ResponseHandler::error - Permission denied (403)", [
                    'error' => $error,
                    'code' => $code,
                    'status' => $statusCode,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                    'backtrace' => array_map(function($trace) {
                        return ($trace['class'] ?? '') . ($trace['type'] ?? '') . ($trace['function'] ?? '') . 
                               ' in ' . ($trace['file'] ?? 'unknown') . ':' . ($trace['line'] ?? 0);
                    }, $backtrace)
                ]);
            }
        }
        
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
        
        self::json($response, $statusCode);
    }
    
    /**
     * Send validation error response
     * @param array $errors Validation errors
     * @param string|null $message Error message
     * @return void
     */
    public static function validationError(array $errors, ?string $message = null): void {
        self::error(
            $message ?? 'Validation failed',
            'VALIDATION_ERROR',
            400,
            $errors
        );
    }
    
    /**
     * Send unauthorized response
     * @param string|null $message Error message
     * @return void
     */
    public static function unauthorized(?string $message = null): void {
        self::error(
            $message ?? 'Yetkilendirme hatası',
            'UNAUTHORIZED',
            401
        );
    }
    
    /**
     * Send not found response
     * @param string|null $message Error message
     * @return void
     */
    public static function notFound(?string $message = null): void {
        self::error(
            $message ?? 'Resource not found',
            'NOT_FOUND',
            404
        );
    }
    
    /**
     * Send server error response
     * @param string|null $message Error message
     * @return void
     */
    public static function serverError(?string $message = null): void {
        self::error(
            $message ?? 'Internal server error',
            'SERVER_ERROR',
            500
        );
    }
    
    /**
     * Send paginated response
     * @param array $data Data array
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param int $total Total items count
     * @param array $meta Additional metadata
     * @return void
     */
    public static function paginated(
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
        
        self::json($response);
    }
    
    /**
     * Redirect to URL
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code (301 or 302)
     * @return void
     */
    public static function redirect(string $url, int $statusCode = 302): void {
        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Send file download response
     * @param string $filePath Path to file
     * @param string|null $filename Download filename
     * @param string|null $contentType Content type
     * @return void
     */
    public static function download(string $filePath, ?string $filename = null, ?string $contentType = null): void {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
            return;
        }
        
        $filename = $filename ?? basename($filePath);
        
        if ($contentType === null) {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $contentType = self::getContentType($extension);
        }
        
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Get content type by file extension
     * @param string $extension File extension
     * @return string Content type
     */
    private static function getContentType(string $extension): string {
        $types = [
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip'
        ];
        
        return $types[strtolower($extension)] ?? 'application/octet-stream';
    }
}

