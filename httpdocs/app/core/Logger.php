<?php
namespace App\Core;

/**
 * Centralized Logging System
 * Handles all application logging with different log levels
 */
class Logger {
    private static $logDir = __DIR__ . '/../../logs';
    private static $logFile = 'app.log';
    private static $errorLogFile = 'error.log';
    private static $maxFileSize = 10 * 1024 * 1024; // 10MB
    private static $maxFiles = 5; // Keep 5 rotated files
    private static $logLevel = 'INFO';
    private static $initialized = false;
    
    /**
     * Patterns that should NEVER be written to the database.
     * These are expected behaviors, bot noise, or operational messages - not real errors.
     * They still go to file logs for debugging.
     */
    private static $dbSuppressPatterns = [
        '/Route not found: (GET|POST|PUT|DELETE) \/ws\b/',
        '/404 Error: Route not found: (GET|POST|PUT|DELETE) \/ws\b/',
        '/Route not found: .*(wp-admin|wp-login|wordpress|wp-includes|wp-content|\.php\.bak|xmlrpc|\.env|phpmyadmin|adminer|\.git|\.well-known\/security)/',
        '/404 Error: Route not found: .*(wp-admin|wp-login|wordpress|wp-includes|wp-content|\.php\.bak|xmlrpc|\.env|phpmyadmin|adminer|\.git|\.well-known\/security)/',
        '/Authorization: Package permission denied/',
        '/QR access denied - tenant mismatch/',
        '/Authorization: User not logged in for permission check/',
        '/ReceiptService::generateReceipt - No order items found/',
        '/ReceiptService::generateReceipt duplicate receipt_number retry/',
        '/CSRF token validation failed/',
        '/BaseRepository::create - Removed non-existent columns/',
        '/UserRepository::findByPin - No matching user found/',
        '/Authentication failed: Invalid PIN/',
        '/PIN login failed - authentication returned false/',
        '/Route not found: .*(169\.254\.169\.254|meta-data|\.well-known\/assetlinks|\.well-known\/apple-app-site-association|favicon\.ico|robots\.txt|sitemap\.xml)/',
        '/Route not found: (GET|HEAD) \/mobile-app/',
        '/404 Error: Route not found: (GET|HEAD) \/mobile-app/',
        '/404 Error: Route not found: .*(169\.254\.169\.254|meta-data|\.well-known\/assetlinks|\.well-known\/apple-app-site-association|favicon\.ico|robots\.txt|sitemap\.xml)/',
        '/generatePreparationReceipt - Dedup check failed/',
    ];
    
    private static $recentDbHashes = [];
    private static $dbRateLimitSeconds = 300;
    
    /**
     * Initialize logger - create log directory if it doesn't exist
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;
        
        try {
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }
            
            if (!is_writable(self::$logDir)) {
                $altLogDir = __DIR__ . '/../../storage/logs';
                if (is_dir($altLogDir) || @mkdir($altLogDir, 0755, true)) {
                    self::$logDir = $altLogDir;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }
        
        try {
            if (class_exists('\App\Core\DependencyFactory')) {
                $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                self::$logLevel = strtoupper($settingsService->getLogLevel());
            }
        } catch (\Exception $e) {
            self::$logLevel = isset($_ENV['LOG_LEVEL']) ? strtoupper($_ENV['LOG_LEVEL']) : 'INFO';
        }
    }
    
    /**
     * Check if log level should be logged
     * @param string $level
     * @return bool
     */
    private static function shouldLog($level): bool {
        self::init();
        
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        $currentLevel = $levels[self::$logLevel] ?? 0;
        $messageLevel = $levels[$level] ?? 0;
        
        return $messageLevel >= $currentLevel;
    }
    
    /**
     * Rotate log file if it exceeds max size
     * @param string $logPath
     */
    private static function rotateLog($logPath) {
        if (!file_exists($logPath) || filesize($logPath) < self::$maxFileSize) {
            return;
        }
        
        // Rotate existing files
        for ($i = self::$maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $logPath . '.' . $i;
            $newFile = $logPath . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                if ($i + 1 <= self::$maxFiles) {
                    // Check if target directory exists and is writable before renaming
                    $targetDir = dirname($newFile);
                    if (is_dir($targetDir) && is_writable($targetDir)) {
                        @rename($oldFile, $newFile);
                    }
                } else {
                    @unlink($oldFile);
                }
            }
        }
        
        // Move current log to .1
        if (file_exists($logPath)) {
            $targetDir = dirname($logPath);
            if (is_dir($targetDir) && is_writable($targetDir)) {
                @rename($logPath, $logPath . '.1');
            }
        }
    }
    
    /**
     * Rotate structured JSON log files (max 50MB, keep 3 files)
     * @param string $logPath
     */
    private static function rotateStructuredLog($logPath) {
        $maxSize = 50 * 1024 * 1024; // 50MB
        $maxFiles = 3;
        
        if (!file_exists($logPath) || @filesize($logPath) < $maxSize) {
            return;
        }
        
        // Remove oldest
        $oldest = $logPath . '.' . $maxFiles;
        if (file_exists($oldest)) {
            @unlink($oldest);
        }
        
        // Rotate existing files
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $logPath . '.' . $i;
            $newFile = $logPath . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                @rename($oldFile, $newFile);
            }
        }
        
        // Move current to .1
        if (file_exists($logPath)) {
            @rename($logPath, $logPath . '.1');
        }
    }
    
    /**
     * Log with custom level
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function log(string $level, string $message, array $context = []): void {
        self::writeLog($level, $message, $context);
    }
    
    /**
     * Write log message with structured logging
     * @param string $level - Log level (INFO, ERROR, WARNING, DEBUG)
     * @param string $message - Log message
     * @param array $context - Additional context data
     */
    private static function writeLog($level, $message, $context = []) {
        self::init();

        // Check if this level should be logged
        if (!self::shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        $username = $_SESSION['username'] ?? 'unknown';
        $rawRole = $_SESSION['role'] ?? 'none';
        
        // Validate and filter invalid roles for logging
        // This prevents invalid roles like "SUBDOMAIN" from appearing in logs
        $validRoles = ['MANAGER', 'BUSINESS_MANAGER', 'ADMIN', 'ADMINISTRATOR', 'WAITER', 'KITCHEN', 'CASHIER', 'CUSTOMER', 'SUPER_ADMIN', 'QODMIN', 'TRIAL'];
        $normalizedRole = !empty($rawRole) ? strtoupper(str_replace('ROLE_', '', trim($rawRole))) : '';
        $isValidRole = !empty($normalizedRole) && in_array($normalizedRole, $validRoles, true);
        
        // Use "INVALID_ROLE" for invalid roles to make debugging easier
        $userRole = $isValidRole ? $rawRole : 'INVALID_ROLE';

        // Build structured log entry
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request' => [
                'ip' => $ip,
                'method' => $requestMethod,
                'uri' => $requestUri,
                'user_agent' => $userAgent,
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'none'
            ],
            'user' => [
                'id' => $userId,
                'username' => $username,
                'role' => $userRole
            ],
            'session' => [
                'logged_in' => $_SESSION['logged_in'] ?? false,
                'login_time' => $_SESSION['login_time'] ?? null
            ],
            'server' => [
                'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
                'http_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                'request_time' => $_SERVER['REQUEST_TIME'] ?? time()
            ]
        ];

        // Format log message with more detailed information
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] [{$level}] [{$ip}] [User:{$username}({$userId})-{$userRole}] {$message}{$contextStr}\n";

        $logFile = ($level === 'ERROR') ? self::$errorLogFile : self::$logFile;
        $logPath = self::$logDir . '/' . $logFile;

        // Rotate if needed
        self::rotateLog($logPath);

        // Write to log file (with error suppression to prevent breaking app)
        try {
            @file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Silently fail - don't break application if logging fails
        }

        // Also write structured JSON log (for log aggregation tools)
        try {
            $jsonLogPath = self::$logDir . '/' . str_replace('.log', '_structured.json', $logFile);
            // Rotate structured JSON log too (max 50MB per file, keep 3 files)
            self::rotateStructuredLog($jsonLogPath);
            @file_put_contents($jsonLogPath, json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Silently fail - structured logging is optional
        }

        // Also log to PHP error log for debugging (only errors and warnings)
        if (in_array($level, ['ERROR', 'WARNING'])) {
            error_log("[{$level}] User: {$username}({$userId}), IP: {$ip}, Message: {$message}");
        }

        // Write to database for ERROR and WARNING levels
        // Only write to database if level is ERROR or WARNING to avoid flooding the database
        if (in_array($level, ['ERROR', 'WARNING'])) {
            self::writeToDatabase($level, $message, $context);
        }
    }

    /**
     * Check if a message matches any DB suppression pattern (bot noise, expected behavior, etc.)
     */
    private static function isDbSuppressed(string $message): bool {
        foreach (self::$dbSuppressPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a deduplication hash for an error (same message+file+line = same error)
     */
    public static function generateErrorHash(string $message, ?string $file = null, ?int $line = null): string {
        $normalized = preg_replace('/\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2}/', '[TIMESTAMP]', $message);
        $normalized = preg_replace('/\b\d+\.\d+\.\d+\.\d+\b/', '[IP]', $normalized);
        $normalized = preg_replace('/(?:ID|id)[\s:=]+[a-zA-Z0-9_-]{10,}/', 'ID=[HASH]', $normalized);
        return md5($normalized . '|' . ($file ?? '') . '|' . ($line ?? ''));
    }

    /**
     * Check rate limit: skip DB write if same hash was written recently
     */
    private static function isRateLimited(string $hash): bool {
        $now = time();
        if (isset(self::$recentDbHashes[$hash]) && ($now - self::$recentDbHashes[$hash]) < self::$dbRateLimitSeconds) {
            return true;
        }
        self::$recentDbHashes[$hash] = $now;
        if (count(self::$recentDbHashes) > 200) {
            self::$recentDbHashes = array_slice(self::$recentDbHashes, -100, null, true);
        }
        return false;
    }

    /**
     * Write log entry to database with smart filtering and deduplication
     */
    private static function writeToDatabase($level, $message, $context = []) {
        try {
            if (!class_exists('\App\Core\DependencyFactory', false)) {
                return;
            }

            if (self::isDbSuppressed($message)) {
                return;
            }

            $file = $context['file'] ?? null;
            $line = $context['line'] ?? null;
            $trace = $context['trace'] ?? null;

            $errorHash = self::generateErrorHash($message, $file, is_numeric($line) ? (int)$line : null);

            if (self::isRateLimited($errorHash)) {
                return;
            }

            $dbContext = $context;
            unset($dbContext['file'], $dbContext['line'], $dbContext['trace']);

            $phpErrorLogService = \App\Core\DependencyFactory::getPhpErrorLogService();
            $phpErrorLogService->logError([
                'level' => $level,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'trace' => $trace,
                'context' => !empty($dbContext) ? $dbContext : null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'ip' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'user_id' => $_SESSION['user_id'] ?? null,
                'error_hash' => $errorHash
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to write log to database: " . $e->getMessage());
        }
    }

    /**
     * Get client IP address (handles proxies)
     */
    private static function getClientIP(): string {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Log authentication event
     */
    public static function auth($message, $context = []) {
        self::writeLog('AUTH', $message, $context);
    }

    /**
     * Log security event
     */
    public static function security($message, $context = []) {
        self::writeLog('SECURITY', $message, $context);
    }

    /**
     * Log database query
     */
    public static function query($message, $context = []) {
        self::writeLog('QUERY', $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::writeLog('INFO', $message, $context);
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = []) {
        self::writeLog('ERROR', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = []) {
        self::writeLog('WARNING', $message, $context);
    }
    
    /**
     * Log debug message
     */
    public static function debug($message, $context = []) {
        self::writeLog('DEBUG', $message, $context);
    }
    
    /**
     * Log exception
     */
    public static function exception(\Throwable $e, $context = []) {
        $message = get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        $context['trace'] = $e->getTraceAsString();
        $context['file'] = $e->getFile();
        $context['line'] = $e->getLine();
        self::writeLog('ERROR', $message, $context);
        
        // Also write exception directly to database for better tracking
        try {
            if (class_exists('\App\Core\DependencyFactory', false)) {
                $phpErrorLogService = \App\Core\DependencyFactory::getPhpErrorLogService();
                $phpErrorLogService->logException($e, $context);
            }
        } catch (\Throwable $dbError) {
            // Silently fail - don't break exception logging if database write fails
            error_log("Failed to write exception to database: " . $dbError->getMessage());
        }
    }
    
    /**
     * Get recent log entries
     * @param int $lines - Number of lines to retrieve
     * @param string $level - Filter by level (optional)
     */
    public static function getRecentLogs($lines = 100, $level = null) {
        self::init();
        
        $logFile = ($level === 'ERROR') ? self::$errorLogFile : self::$logFile;
        $logPath = self::$logDir . '/' . $logFile;
        
        if (!file_exists($logPath)) {
            return [];
        }
        
        $file = file($logPath);
        return array_slice($file, -$lines);
    }
}

