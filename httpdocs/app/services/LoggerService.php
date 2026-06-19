<?php
namespace App\Services;

use App\Core\Logger;

/**
 * Logger Service
 * Wraps the core Logger class to provide a service-based interface
 * Allows for dependency injection and easier testing
 */
class LoggerService {
    /**
     * Log an info message
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = []): void {
        Logger::info($message, $context);
    }
    
    /**
     * Log an error message
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = []): void {
        Logger::error($message, $context);
    }
    
    /**
     * Log a warning message
     * @param string $message
     * @param array $context
     */
    public function warning(string $message, array $context = []): void {
        Logger::warning($message, $context);
    }
    
    /**
     * Log a debug message
     * @param string $message
     * @param array $context
     */
    public function debug(string $message, array $context = []): void {
        Logger::debug($message, $context);
    }
    
    /**
     * Log an exception
     * @param \Throwable $exception
     * @param array $context
     */
    public function exception(\Throwable $exception, array $context = []): void {
        Logger::exception($exception, $context);
    }
    
    /**
     * Log with custom level
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log(string $level, string $message, array $context = []): void {
        Logger::log($level, $message, $context);
    }
    
    /**
     * Get log entries (if supported)
     * @param int $limit
     * @param string $level
     * @return array
     */
    public function getLogs(int $limit = 100, string $level = null): array {
        // This could be extended to read from log files or database
        // For now, return empty array as Logger doesn't support reading logs
        return [];
    }
    
    /**
     * Clear logs (if supported)
     * @return bool
     */
    public function clearLogs(): bool {
        // This could be extended to clear log files
        // For now, return false as Logger doesn't support clearing logs
        return false;
    }
}
