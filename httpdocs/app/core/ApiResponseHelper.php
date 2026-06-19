<?php
namespace App\Core;

/**
 * Centralized API Response Helper
 * Provides consistent API response formatting across the application
 */
class ApiResponseHelper {
    /**
     * Send a standardized JSON API response
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @param bool $exit Whether to exit after sending response
     * @return void
     */
    public static function send(array $data, int $statusCode = 200, bool $exit = true): void {
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
     * Send error response
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string|null $code Error code
     * @param array $additionalData Additional data to include
     * @return void
     */
    public static function error(string $message, int $statusCode = 400, ?string $code = null, array $additionalData = []): void {
        $response = array_merge([
            'success' => false,
            'error' => $message,
        ], $additionalData);
        
        if ($code !== null) {
            $response['code'] = $code;
        }
        
        self::send($response, $statusCode);
    }
    
    /**
     * Send success response
     * @param mixed $data Response data
     * @param string|null $message Success message
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function success($data = null, ?string $message = null, int $statusCode = 200): void {
        $response = [
            'success' => true,
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::send($response, $statusCode);
    }
}

