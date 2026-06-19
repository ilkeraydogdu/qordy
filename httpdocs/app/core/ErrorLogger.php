<?php
namespace App\Core;

/**
 * Centralized Error Logging System
 * Tüm hataları log dosyasına kaydeder, console'u temiz tutar
 */
class ErrorLogger {
    private static $instance = null;
    private $logFile;
    private $enabled = true;
    
    private function __construct() {
        $this->logFile = __DIR__ . '/../../logs/errors.log';
        
        // Log dizinini oluştur
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Log dosyasını oluştur
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log error with context
     */
    public function log($message, $context = [], $level = 'ERROR') {
        if (!$this->enabled) return;
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        
        // Format log entry
        $logEntry = sprintf(
            "[%s] [%s] [%s] [%s %s] %s\n",
            $timestamp,
            $level,
            $ip,
            $method,
            $uri,
            $message
        );
        
        // Add context if provided
        if (!empty($context)) {
            $logEntry .= "Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        // Add stack trace for errors
        if ($level === 'ERROR' || $level === 'CRITICAL') {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            $logEntry .= "Stack: " . json_encode($trace, JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        $logEntry .= str_repeat('-', 80) . "\n";
        
        // Write to log file
        error_log($logEntry, 3, $this->logFile);
    }
    
    /**
     * Log API error
     */
    public function logApiError($endpoint, $error, $statusCode = 500) {
        $this->log(
            "API Error: {$endpoint}",
            [
                'error' => $error,
                'status_code' => $statusCode,
                'session' => [
                    'business_id' => $_SESSION['business_id'] ?? null,
                    'customer_id' => $_SESSION['customer_id'] ?? null,
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'role' => $_SESSION['role'] ?? null
                ]
            ],
            'ERROR'
        );
    }
    
    /**
     * Log authentication error
     */
    public function logAuthError($message, $context = []) {
        $this->log(
            "Auth Error: {$message}",
            array_merge($context, [
                'session_keys' => array_keys($_SESSION ?? []),
                'cookies' => array_keys($_COOKIE ?? [])
            ]),
            'WARNING'
        );
    }
    
    /**
     * Log database error
     */
    public function logDatabaseError($query, $error) {
        $this->log(
            "Database Error",
            [
                'query' => $query,
                'error' => $error
            ],
            'CRITICAL'
        );
    }
    
    /**
     * Log validation error
     */
    public function logValidationError($field, $error) {
        $this->log(
            "Validation Error: {$field}",
            ['error' => $error],
            'WARNING'
        );
    }
    
    /**
     * Get recent errors (for admin panel)
     */
    public function getRecentErrors($limit = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    /**
     * Clear old logs (keep last 7 days)
     */
    public function cleanOldLogs() {
        if (!file_exists($this->logFile)) return;
        
        $sevenDaysAgo = strtotime('-7 days');
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($logTime >= $sevenDaysAgo) {
                    $newLines[] = $line;
                }
            }
        }
        
        file_put_contents($this->logFile, implode("\n", $newLines) . "\n");
    }
}
