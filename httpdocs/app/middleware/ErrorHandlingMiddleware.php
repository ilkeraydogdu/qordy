<?php
namespace App\Middleware;

use App\Core\DependencyFactory;
use App\Core\ErrorHandler;

/**
 * Error Handling Middleware
 * Catches and handles errors/exceptions during request processing
 * Provides consistent error responses and logging
 */
class ErrorHandlingMiddleware {
    /**
     * Handle the request
     * @param callable $next Next middleware or route handler
     * @return mixed
     */
    public function handle(callable $next) {
        try {
            // Execute the next middleware/handler
            return $next();
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Handle exceptions
     * @param \Throwable $exception
     * @return void
     */
    private function handleException(\Throwable $exception): void {
        $loggerService = DependencyFactory::getLoggerService();
        
        // Log the exception
        $loggerService->exception($exception, [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? 'guest'
        ]);
        
        // Check if this is an API request
        $isApiRequest = $this->isApiRequest();
        
        if ($isApiRequest) {
            $this->handleApiError($exception);
            return; // handleApiError already exits
        }
        
        // For non-API requests, let ErrorHandler handle it (ErrorHandler::handleException exits)
        ErrorHandler::handleException($exception);
    }
    
    /**
     * Check if current request is an API request
     * @return bool
     */
    private function isApiRequest(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        
        // Check if this is an API request based on URL path or headers
        return strpos($uri, '/api/') === 0 ||
               strpos($uri, '/pos/') === 0 ||
               strpos($uri, '/kitchen/') === 0 ||
               strpos($uri, '/waiter/') === 0 ||
               strpos($uri, '/cashier/') === 0 ||
               strpos($contentType, 'application/json') !== false ||
               strpos($acceptHeader, 'application/json') !== false;
    }
    
    /**
     * Handle API errors
     * @param \Throwable $exception
     * @return void Outputs JSON response and exits
     */
    private function handleApiError(\Throwable $exception): void {
        // Get APP_ENV and APP_DEBUG from database instead of .env
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $isProduction = ($settingsService->getAppEnv() === 'production');
            $isDebug = $settingsService->getAppDebug();
        } catch (\Exception $e) {
            // Fallback to .env if database is not available
            $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
            $isDebug = (isset($_ENV['APP_DEBUG']) && ($_ENV['APP_DEBUG'] === 'true' || $_ENV['APP_DEBUG'] === '1'));
        }
        $showDetails = !$isProduction || $isDebug;
        
        $response = [
            'error' => true,
            'message' => $showDetails ? $exception->getMessage() : 'An error occurred while processing your request.',
            'code' => 500
        ];
        
        if ($showDetails) {
            $response['details'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        \App\Core\ApiResponseHelper::send($response, 500);
    }
    
    /**
     * Handle validation errors
     * @param array $errors Validation errors
     * @return void Outputs JSON response and exits
     */
    public function handleValidationError(array $errors): void {
        \App\Core\ApiResponseHelper::error('Validation failed', 422, 'VALIDATION_ERROR', ['errors' => $errors]);
    }
    
    /**
     * Handle not found errors
     * @param string $message
     * @return void Outputs response and exits
     */
    public function handleNotFound(string $message = 'Resource not found'): void {
        $isApiRequest = $this->isApiRequest();
        
        if ($isApiRequest) {
            \App\Core\ApiResponseHelper::error($message, 404, 'NOT_FOUND');
        }
        
        ErrorHandler::handle404($message);
    }
    
    /**
     * Handle unauthorized errors
     * @param string $message
     * @return void Outputs response and exits
     */
    public function handleUnauthorized(string $message = 'Unauthorized'): void {
        $isApiRequest = $this->isApiRequest();
        
        if ($isApiRequest) {
            \App\Core\ApiResponseHelper::error($message, 401, 'UNAUTHORIZED');
        }
        
        http_response_code(401);
        header('Location: ' . BASE_URL . '/unauthorized');
        exit;
    }
    
    /**
     * Handle forbidden errors
     * @param string $message
     * @return void Outputs response and exits
     */
    public function handleForbidden(string $message = 'Forbidden'): void {
        $isApiRequest = $this->isApiRequest();
        
        if ($isApiRequest) {
            \App\Core\ApiResponseHelper::error($message, 403, 'FORBIDDEN');
        }
        
        http_response_code(403);
        header('Location: ' . BASE_URL . '/unauthorized');
        exit;
    }
}
