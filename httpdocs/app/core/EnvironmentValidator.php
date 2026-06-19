<?php
namespace App\Core;

/**
 * Environment Configuration Validator
 * Validates required environment variables on application startup
 */
class EnvironmentValidator {
    private static $required = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER'
    ];
    
    private static $optional = [
        'DB_PASS',
        'APP_ENV',
        'APP_DEBUG',
        'APP_URL',
        'GEMINI_API_KEY'
    ];
    
    /**
     * Validate environment configuration
     * Uses default values if .env file doesn't exist (for easier setup)
     * @throws \Exception if required variables are missing after applying defaults
     */
    public static function validate(): void {
        $envFile = __DIR__ . '/../../.env';
        $envExampleFile = __DIR__ . '/../../.env.example';
        
        // If .env doesn't exist, try to create it from .env.example
        if (!file_exists($envFile)) {
            if (file_exists($envExampleFile)) {
                // Try to auto-create .env from .env.example
                if (copy($envExampleFile, $envFile)) {
                    // Reload environment variables
                    self::loadEnvFile($envFile);
                }
            }
        }
        
        // Check .env file exists
        if (!file_exists($envFile)) {
            throw new \Exception(".env file not found. Please copy .env.example to .env and configure all required environment variables.");
        }
        
        $missing = [];
        
        // Check required variables (must be in .env file)
        foreach (self::$required as $var) {
            if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
                $missing[] = $var;
            }
        }
        
        // Throw exception if required variables are missing
        if (!empty($missing)) {
            $message = "Missing required environment variables in .env file: " . implode(', ', $missing);
            $message .= "\n\nPlease set all required variables in your .env file.";
            throw new \Exception($message);
        }
        
        // Validate values
        self::validateValues();
    }
    
    /**
     * Apply default values for environment variables
     * NOTE: No longer applies defaults - all values must be in .env file
     */
    private static function applyDefaults(): void {
        // Default values are no longer applied
        // All environment variables must be set in .env file
    }
    
    /**
     * Load environment variables from file
     */
    private static function loadEnvFile(string $envFile): void {
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
        }
    }
    
    /**
     * Validate environment variable values
     */
    private static function validateValues(): void {
        // Validate APP_ENV
        if (isset($_ENV['APP_ENV'])) {
            $validEnvs = ['development', 'production', 'testing'];
            if (!in_array($_ENV['APP_ENV'], $validEnvs)) {
                throw new \Exception("Invalid APP_ENV value. Must be one of: " . implode(', ', $validEnvs));
            }
        }
        
        // Validate database name (alphanumeric and underscore only)
        if (isset($_ENV['DB_NAME']) && !preg_match('/^[a-zA-Z0-9_]+$/', $_ENV['DB_NAME'])) {
            throw new \Exception("Invalid DB_NAME. Only alphanumeric characters and underscores are allowed.");
        }
        
        // Validate APP_URL format (only if set and not empty)
        // NOTE: APP_URL in .env is OPTIONAL and only used for CLI scripts
        // Web requests always use dynamic URL detection from HTTP_HOST via BaseUrlService
        // So we allow empty/invalid APP_URL to prevent site crashes - it will auto-detect for web requests
        if (isset($_ENV['APP_URL']) && !empty(trim($_ENV['APP_URL']))) {
            $appUrl = trim($_ENV['APP_URL']);
            // Only validate if it's actually set and not empty
            // Allow empty string or comment it out - web requests will auto-detect
            if (!filter_var($appUrl, FILTER_VALIDATE_URL)) {
                // Log warning but don't crash - web requests use dynamic detection anyway
                error_log("WARNING: Invalid APP_URL format in .env: " . $appUrl . " - Web requests will use auto-detected URL from HTTP_HOST");
                // Don't throw exception - allow the site to run with dynamic URL detection
            }
        }
    }
    
    /**
     * Get environment variable with default
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        return $_ENV[$key] ?? $default;
    }
    
    /**
     * Check if environment is production
     * @return bool
     */
    public static function isProduction(): bool {
        return (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
    }
    
    /**
     * Check if debugging is enabled
     * @return bool
     */
    public static function isDebug(): bool {
        return (isset($_ENV['APP_DEBUG']) && ($_ENV['APP_DEBUG'] === 'true' || $_ENV['APP_DEBUG'] === '1'));
    }
}

