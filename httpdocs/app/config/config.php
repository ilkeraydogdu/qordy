<?php
namespace App\Config;

// Set default timezone to Europe/Istanbul (will be updated from DB settings if different)
date_default_timezone_set('Europe/Istanbul');
// Load Composer autoloader FIRST (primary autoloader with PSR-4 optimization)
$composerAutoloader = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoloader)) {
 require_once $composerAutoloader;
}

// CRITICAL FIX: Load .env into $_ENV and putenv() BEFORE any code that
// depends on environment variables. Previously .env was only parsed by
// EnvironmentValidator::validate() in a very narrow code path, and
// ConfigManager::loadEnvironment() was never invoked from config.php.
// That meant $_ENV['ENCRYPTION_KEY'] was empty at runtime, the system
// fell back to the legacy encryption key, and PIN decryption failed —
// causing every PIN login to return 'user not found'.
if (php_sapi_name() !== 'cli') {
 $envFile = __DIR__ . '/../../.env';
 if (file_exists($envFile)) {
 $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
 foreach ($envLines as $envLine) {
 $envLine = trim($envLine);
 if ($envLine === '' || $envLine[0] === '#') continue;
 if (strpos($envLine, '=') === false) continue;
 list($envKey, $envVal) = explode('=', $envLine, 2);
 $envKey = trim($envKey);
 $envVal = trim($envVal);
 if ((substr($envVal, 0, 1) === '"' && substr($envVal, -1) === '"') ||
 (substr($envVal, 0, 1) === "'" && substr($envVal, -1) === "'")) {
 $envVal = substr($envVal, 1, -1);
 }
 if (!array_key_exists($envKey, $_ENV)) {
 $_ENV[$envKey] = $envVal;
 }
 putenv("{$envKey}={$envVal}");
 }
 }
}

// CRITICAL FIX: Plesk Nginx reverse proxy terminates SSL but does
// NOT forward X-Forwarded-Proto to Apache. Without this fix PHP sees
// the request as HTTP, BASE_URL becomes http://, and after a
// successful login the response Location: header points to http://.
// Browsers then drop the secure session cookie when downgrading
// to HTTP, the server sees an unauthenticated request, and the
// user appears to be stuck in a login loop (the symptom Knaka
// reported as "page just refreshes").
//
// We define isHttps() and getProtocol() EARLY (before any other
// code or the functions.php helper) so that the original
// function_exists() guards in functions.php skip the broken
// versions and our correct detection wins.
if (!function_exists('isHttps')) {
 function isHttps(): bool {
 if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
 return true;
 }
 if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
 && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
 return true;
 }
 if (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])
 && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
 return true;
 }
 if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
 return true;
 }
 // Plesk Nginx fallback: if the source is the public internet and
 // the host is a known production domain, Plesk has terminated
 // SSL at the Nginx layer.
 $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
 $isPrivateSource = (
 strpos($remoteAddr, '127.') === 0
 || strpos($remoteAddr, '10.') === 0
 || strpos($remoteAddr, '192.168.') === 0
 || strpos($remoteAddr, '::1') === 0
 || strpos($remoteAddr, 'fc00:') === 0
 || strpos($remoteAddr, 'fe80:') === 0
 );
 $host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
 $isProductionHost = (
 $host !== ''
 && (strpos($host, 'qordy.com') !== false || strpos($host, 'lezzetqr') !== false)
 );
 if (!$isPrivateSource && $isProductionHost) {
 return true;
 }
 $envAppUrl = $_ENV['APP_URL'] ?? null;
 if (is_string($envAppUrl) && stripos($envAppUrl, 'https://') === 0) {
 return true;
 }
 return false;
 }
}
if (!function_exists('getProtocol')) {
 function getProtocol(): string {
 return isHttps() ? 'https' : 'http';
 }
}

// Register custom autoloader as FALLBACK (handles case-insensitive file systems)
// This MUST run AFTER Composer autoloader to catch classes Composer couldn't find
if (!defined('ERROR_PAGE_LOADING')) {
    spl_autoload_register(function ($class) {
        // Skip if already loaded
        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            return;
        }
        
        // Only handle App\ namespace classes
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/../';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relativeClass = substr($class, $len);
        $parts = explode('\\', $relativeClass);
        
        if (count($parts) === 0) {
            return;
        }
        
        $filename = array_pop($parts);
        
        // Try exact PSR-4 match first (Services/CacheService.php)
        $exactFile = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($exactFile)) {
            require_once $exactFile;
            return;
        }
        
        // Strategy 1: First directory lowercase, rest original case
        // Example: Services/CacheService -> services/CacheService.php
        if (count($parts) > 0) {
            $firstDir = strtolower($parts[0]);
            $restDirs = array_slice($parts, 1);
            $mixedPath = $firstDir . (count($restDirs) > 0 ? '/' . implode('/', $restDirs) : '');
            $fileMixed1 = $baseDir . $mixedPath . '/' . $filename . '.php';
            if (file_exists($fileMixed1)) {
                require_once $fileMixed1;
                return;
            }
        }
        
        // Strategy 2: All directories lowercase, original filename
        // Example: Services/CacheService -> services/CacheService.php
        $dirPath = strtolower(implode('/', $parts));
        $fileLower1 = $baseDir . ($dirPath ? $dirPath . '/' : '') . $filename . '.php';
        if (file_exists($fileLower1)) {
            require_once $fileLower1;
            return;
        }
        
        // Strategy 3: All directories lowercase AND lowercase filename
        // Example: Services/CacheService -> services/cacheservice.php
        $fileLower2 = $baseDir . ($dirPath ? $dirPath . '/' : '') . strtolower($filename) . '.php';
        if (file_exists($fileLower2)) {
            require_once $fileLower2;
            return;
        }
        
        // Strategy 4: Try with first letter uppercase for filename
        // Example: Services/cacheservice -> services/CacheService.php
        $fileUpper = $baseDir . ($dirPath ? $dirPath . '/' : '') . ucfirst($filename) . '.php';
        if (file_exists($fileUpper)) {
            require_once $fileUpper;
            return;
        }
    }, false, false); // prepend=false - Run AFTER Composer autoloader as fallback
}

// Load environment variables
$envFile = __DIR__ . '/../../.env';
$envExampleFile = __DIR__ . '/../../.env.example';

// CRITICAL: Never auto-create .env in production
// If .env doesn't exist, try to create it from .env.example (only in development/CLI)
if (!file_exists($envFile) && file_exists($envExampleFile)) {
    $appEnv = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'development';
    
    // Only auto-create in development environment or CLI mode
    // Production must have .env file configured manually
    if ($appEnv === 'production') {
        throw new \Exception('.env file is required in production. Please create it manually from .env.example and configure all required variables.');
    }
    
    // Only auto-create in CLI mode or if directory is writable (development only)
    if (php_sapi_name() === 'cli' || is_writable(dirname($envFile))) {
        @copy($envExampleFile, $envFile);
    }
}

// Load .env file if it exists
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue; // Skip comments and empty lines
        if (strpos($line, '=') === false) continue; // Skip lines without =
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

// Validate that required environment variables are set
if (!isset($_ENV['DB_HOST']) || empty($_ENV['DB_HOST'])) {
    error_log('ERROR: DB_HOST not set in .env file');
    throw new \Exception('DB_HOST environment variable is required. Please set it in .env file.');
}
if (!isset($_ENV['DB_NAME']) || empty($_ENV['DB_NAME'])) {
    error_log('ERROR: DB_NAME not set in .env file');
    throw new \Exception('DB_NAME environment variable is required. Please set it in .env file.');
}
if (!isset($_ENV['DB_USER']) || empty($_ENV['DB_USER'])) {
    error_log('ERROR: DB_USER not set in .env file');
    throw new \Exception('DB_USER environment variable is required. Please set it in .env file.');
}
if (!isset($_ENV['DB_PASS'])) {
    error_log('ERROR: DB_PASS not set in .env file');
    throw new \Exception('DB_PASS environment variable is required. Please set it in .env file.');
}

// Validate environment configuration (only in non-CLI contexts to avoid breaking scripts)
if (php_sapi_name() !== 'cli') {
    try {
        require_once __DIR__ . '/../core/EnvironmentValidator.php';
        \App\Core\EnvironmentValidator::validate();
    } catch (\Exception $e) {
        // Show user-friendly error page instead of plain die()
        // Get APP_ENV from database instead of .env
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $isProduction = ($settingsService->getAppEnv() === 'production');
        } catch (\Exception $e) {
            // Fallback to .env if database is not available
            $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production');
        }
        
        if ($isProduction) {
            error_log("Environment validation failed: " . $e->getMessage());
            // Show generic error in production
            http_response_code(500);
            die("Application configuration error. Please contact the administrator.");
        } else {
            // Show detailed error in development
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo "<!DOCTYPE html>
<html>
<head>
    <title>Configuration Error - Qordy</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .error-box { background: #fee; border: 2px solid #fcc; padding: 20px; border-radius: 5px; }
        .error-title { color: #c00; font-size: 24px; margin-bottom: 10px; }
        .error-message { color: #333; line-height: 1.6; }
        .error-steps { background: #fff; padding: 15px; margin-top: 15px; border-radius: 3px; }
        .error-steps h3 { margin-top: 0; }
        .error-steps ol { line-height: 2; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class='error-box'>
        <div class='error-title'>⚠️ Environment Configuration Error</div>
        <div class='error-message'>" . htmlspecialchars($e->getMessage()) . "</div>
        <div class='error-steps'>
            <h3>Quick Fix:</h3>
            <ol>
                <li>Copy <code>.env.example</code> to <code>.env</code> in the project root</li>
                <li>Edit <code>.env</code> and configure your database settings:
                    <pre>DB_HOST=localhost
DB_NAME=qordy
DB_USER=root
DB_PASS=</pre>
                </li>
                <li>Refresh this page</li>
            </ol>
            <p><strong>Note:</strong> The application is using default values for now, but you should configure <code>.env</code> for production use.</p>
        </div>
    </div>
</body>
</html>";
            exit;
        }
    }
}

// CDN and External Resource URLs
if (!defined('TAILWIND_CDN_URL')) {
    define('TAILWIND_CDN_URL', $_ENV['TAILWIND_CDN_URL'] ?? 'https://cdn.tailwindcss.com');
}
if (!defined('GOOGLE_FONTS_URL')) {
    define('GOOGLE_FONTS_URL', $_ENV['GOOGLE_FONTS_URL'] ?? 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap');
}

// Database configuration (must be set in .env file)
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);

// Application configuration - Use BaseUrlService for automatic domain detection
// IMPORTANT: Web requests ALWAYS use dynamic URL detection from HTTP_HOST
// .env APP_URL is ONLY used for CLI scripts, never for web requests
try {
    require_once __DIR__ . '/../services/BaseUrlService.php';
    $baseUrl = \App\Services\BaseUrlService::getBaseUrl();
    define('BASE_URL', $baseUrl);
    define('APP_URL', $baseUrl);
} catch (\Exception $e) {
    // Fallback: Always auto-detect from server variables for web requests
    // Never use .env APP_URL for web requests - it's only for CLI scripts
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $fallbackUrl = $protocol . '://' . $host;
    define('BASE_URL', $fallbackUrl);
    define('APP_URL', $fallbackUrl);
    error_log('WARNING: BaseUrlService failed, using auto-detected URL: ' . $fallbackUrl . ' (Error: ' . $e->getMessage() . ')');
}

if (!defined('DEMO_SUBDOMAIN')) {
    define('DEMO_SUBDOMAIN', $_ENV['DEMO_SUBDOMAIN'] ?? 'demo');
}

// Link Shortener (pfdk.me)
if (!defined('PFDK_API_URL')) {
    define('PFDK_API_URL', $_ENV['PFDK_API_URL'] ?? 'https://pfdk.me/api/v1');
}
if (!defined('PFDK_API_KEY')) {
    define('PFDK_API_KEY', $_ENV['PFDK_API_KEY'] ?? '');
}
if (!defined('PFDK_SHORT_DOMAIN')) {
    define('PFDK_SHORT_DOMAIN', $_ENV['PFDK_SHORT_DOMAIN'] ?? 'https://pfdk.me');
}
if (!defined('DEMO_BANNER_DELAY_MS')) {
    define('DEMO_BANNER_DELAY_MS', (int)($_ENV['DEMO_BANNER_DELAY_MS'] ?? 180000));
}

// Encryption key for PIN encryption/decryption.
//
// CRITICAL: MUST come from the environment. A hardcoded fallback in
// source control means the key is effectively public — anyone who
// reads this file can decrypt every stored PIN. If the env value is
// missing we prefer to fail fast so the operator notices, rather than
// silently drifting onto a shared default.
if (!defined('ENCRYPTION_KEY')) {
    $envKey = $_ENV['ENCRYPTION_KEY'] ?? $_ENV['APP_ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ?: null;

    // Legacy fallback: previous deploys had this value baked into the
    // file so existing PIN ciphertexts were encrypted against it.
    // We still accept it as a *last* resort (to avoid locking out
    // every tenant on an upgrade) but we log loudly so the missing
    // .env entry is noticed and rotated.
    $legacyFallback = '0d739340008bfbeda8a3d2a7f1b851f4c7cabc77f0082add9c8f8e9ac9f73689';

    if (!$envKey) {
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error(
                'ENCRYPTION_KEY missing from .env — falling back to legacy key. ' .
                'Rotate by adding ENCRYPTION_KEY=... to .env and re-encrypting PINs.'
            );
        } else {
            error_log('ENCRYPTION_KEY missing from .env — using legacy fallback. Rotate ASAP.');
        }
        $envKey = $legacyFallback;
    }

    define('ENCRYPTION_KEY', $envKey);
}

// Site name and API keys are loaded from database, not .env
// Define with default values first, will be loaded from database when available
// Note: PHP constants cannot be redefined, so we use helper functions for database values
if (!defined('SITE_NAME')) {
    // Default site name - will be retrieved from database via getSiteName()
    // Use AppConfig service if available for fallback
    $defaultSiteName = 'Restoran Yönetim Sistemi';
    try {
        if (class_exists('\App\Services\AppConfig')) {
            $appConfig = \App\Services\AppConfig::getInstance();
            $defaultSiteName = $appConfig->get('defaults.app_name', $defaultSiteName);
        }
    } catch (\Exception $e) {
        // Use default if AppConfig not available
    }
    define('SITE_NAME', $defaultSiteName);
}
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', ''); // Default, will be retrieved from database via getGeminiApiKey()
}

// Define constants with dynamic loading from database
// This prevents undefined constant errors while allowing database overrides

// Skip database loading during error page rendering to prevent class redeclaration
if (!defined('ERROR_PAGE_LOADING')) {
    try {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $constantsService = \App\Core\DependencyFactory::getConstantsService();

        // Load from database with fallback values
        $roleConstants = $constantsService->getAsKeyValue('ROLE');
        $orderStatusConstants = $constantsService->getAsKeyValue('ORDER_STATUS');
        $tableStatusConstants = $constantsService->getAsKeyValue('TABLE_STATUS');
        $paymentMethodConstants = $constantsService->getAsKeyValue('PAYMENT_METHOD');

    // Define role constants
    if (!defined('ROLE_CUSTOMER')) define('ROLE_CUSTOMER', $roleConstants['CUSTOMER'] ?? 'ROLE_CUSTOMER');
    if (!defined('ROLE_WAITER')) define('ROLE_WAITER', $roleConstants['WAITER'] ?? 'ROLE_WAITER');
    if (!defined('ROLE_KITCHEN')) define('ROLE_KITCHEN', $roleConstants['KITCHEN'] ?? 'ROLE_KITCHEN');
    if (!defined('ROLE_MANAGER')) define('ROLE_MANAGER', $roleConstants['MANAGER'] ?? 'ROLE_MANAGER');
    if (!defined('ROLE_CASHIER')) define('ROLE_CASHIER', $roleConstants['CASHIER'] ?? 'ROLE_CASHIER');

    // Define order status constants
    if (!defined('ORDER_STATUS_PENDING')) define('ORDER_STATUS_PENDING', $orderStatusConstants['PENDING'] ?? 'PENDING');
    if (!defined('ORDER_STATUS_PREPARING')) define('ORDER_STATUS_PREPARING', $orderStatusConstants['PREPARING'] ?? 'PREPARING');
    if (!defined('ORDER_STATUS_READY')) define('ORDER_STATUS_READY', $orderStatusConstants['READY'] ?? 'READY');
    if (!defined('ORDER_STATUS_SERVED')) define('ORDER_STATUS_SERVED', $orderStatusConstants['SERVED'] ?? 'SERVED');
    if (!defined('ORDER_STATUS_CANCELLED')) define('ORDER_STATUS_CANCELLED', $orderStatusConstants['CANCELLED'] ?? 'CANCELLED');
    if (!defined('ORDER_STATUS_ISSUE')) define('ORDER_STATUS_ISSUE', $orderStatusConstants['ISSUE'] ?? 'ISSUE');
    if (!defined('ORDER_STATUS_ON_DELIVERY')) define('ORDER_STATUS_ON_DELIVERY', $orderStatusConstants['ON_DELIVERY'] ?? 'ON_DELIVERY');
    if (!defined('ORDER_STATUS_DELIVERED')) define('ORDER_STATUS_DELIVERED', $orderStatusConstants['DELIVERED'] ?? 'DELIVERED');

    // Define table status constants
    if (!defined('TABLE_STATUS_FREE')) define('TABLE_STATUS_FREE', $tableStatusConstants['FREE'] ?? 'FREE');
    if (!defined('TABLE_STATUS_OCCUPIED')) define('TABLE_STATUS_OCCUPIED', $tableStatusConstants['OCCUPIED'] ?? 'OCCUPIED');
    if (!defined('TABLE_STATUS_PAYMENT_PENDING')) define('TABLE_STATUS_PAYMENT_PENDING', $tableStatusConstants['PAYMENT_PENDING'] ?? 'PAYMENT_PENDING');
    if (!defined('TABLE_STATUS_DIRTY')) define('TABLE_STATUS_DIRTY', $tableStatusConstants['DIRTY'] ?? 'DIRTY');
    if (!defined('TABLE_STATUS_RESERVED')) define('TABLE_STATUS_RESERVED', $tableStatusConstants['RESERVED'] ?? 'RESERVED');

    // Define payment method constants
    if (!defined('PAYMENT_METHOD_CASH')) define('PAYMENT_METHOD_CASH', $paymentMethodConstants['CASH'] ?? 'CASH');
    if (!defined('PAYMENT_METHOD_CREDIT_CARD')) define('PAYMENT_METHOD_CREDIT_CARD', $paymentMethodConstants['CREDIT_CARD'] ?? 'CREDIT_CARD');
    if (!defined('PAYMENT_METHOD_ONLINE_PAYMENT')) define('PAYMENT_METHOD_ONLINE_PAYMENT', $paymentMethodConstants['ONLINE_PAYMENT'] ?? 'ONLINE_PAYMENT');
    if (!defined('PAYMENT_METHOD_OTHER')) define('PAYMENT_METHOD_OTHER', $paymentMethodConstants['OTHER'] ?? 'OTHER');

    // Load SITE_NAME from database (system_settings table)
    // Note: GEMINI_API_KEY is kept for dashboard AI features only
    try {
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        
        // Apply timezone from database settings
        $dbTimezone = $settingsService->getTimezone();
        if (!empty($dbTimezone) && in_array($dbTimezone, \DateTimeZone::listIdentifiers())) {
            date_default_timezone_set($dbTimezone);
        }
        
        $siteName = $settingsService->getSiteName();
        $geminiApiKey = $settingsService->getGeminiApiKey();
        
        // Update constants if they were defined with default values
        // Note: PHP constants cannot be redefined, so we use a workaround
        // We'll use runtime_value() function or directly use settingsService when needed
        // For now, keep the constant but note that it should be retrieved from database
        // Update SITE_NAME constant if it's still using default value
        $defaultSiteName = 'Restoran Yönetim Sistemi';
        try {
            if (class_exists('\App\Services\AppConfig')) {
                $appConfig = \App\Services\AppConfig::getInstance();
                $defaultSiteName = $appConfig->get('defaults.app_name', $defaultSiteName);
            }
        } catch (\Exception $e) {
            // Use default if AppConfig not available
        }
        if (!empty($siteName) && SITE_NAME === $defaultSiteName) {
            // Constant already defined, but we can't redefine it
            // The actual value should be retrieved from SystemSettingsService when needed
        }
        if (!empty($geminiApiKey) && GEMINI_API_KEY === '') {
            // Constant already defined, but we can't redefine it
            // The actual value should be retrieved from SystemSettingsService when needed
        }
    } catch (\Exception $settingsException) {
        // If database settings cannot be loaded, use default values
        // This is acceptable during initial setup or if database is not available
        // Use Logger if available, otherwise fallback to error_log
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error("Could not load settings from database: " . $settingsException->getMessage());
        } else {
            error_log("Could not load settings from database: " . $settingsException->getMessage());
        }
    }

    } catch (\Exception $e) {
    // Fallback: Define constants with default values if database loading fails
    if (!defined('ROLE_CUSTOMER')) define('ROLE_CUSTOMER', 'ROLE_CUSTOMER');
    if (!defined('ROLE_WAITER')) define('ROLE_WAITER', 'ROLE_WAITER');
    if (!defined('ROLE_KITCHEN')) define('ROLE_KITCHEN', 'ROLE_KITCHEN');
    if (!defined('ROLE_MANAGER')) define('ROLE_MANAGER', 'ROLE_MANAGER');
    if (!defined('ROLE_CASHIER')) define('ROLE_CASHIER', 'ROLE_CASHIER');

    if (!defined('ORDER_STATUS_PENDING')) define('ORDER_STATUS_PENDING', 'PENDING');
    if (!defined('ORDER_STATUS_PREPARING')) define('ORDER_STATUS_PREPARING', 'PREPARING');
    if (!defined('ORDER_STATUS_READY')) define('ORDER_STATUS_READY', 'READY');
    if (!defined('ORDER_STATUS_SERVED')) define('ORDER_STATUS_SERVED', 'SERVED');
    if (!defined('ORDER_STATUS_CANCELLED')) define('ORDER_STATUS_CANCELLED', 'CANCELLED');
    if (!defined('ORDER_STATUS_ISSUE')) define('ORDER_STATUS_ISSUE', 'ISSUE');
    if (!defined('ORDER_STATUS_ON_DELIVERY')) define('ORDER_STATUS_ON_DELIVERY', 'ON_DELIVERY');
    if (!defined('ORDER_STATUS_DELIVERED')) define('ORDER_STATUS_DELIVERED', 'DELIVERED');

    if (!defined('TABLE_STATUS_FREE')) define('TABLE_STATUS_FREE', 'FREE');
    if (!defined('TABLE_STATUS_OCCUPIED')) define('TABLE_STATUS_OCCUPIED', 'OCCUPIED');
    if (!defined('TABLE_STATUS_PAYMENT_PENDING')) define('TABLE_STATUS_PAYMENT_PENDING', 'PAYMENT_PENDING');
    if (!defined('TABLE_STATUS_DIRTY')) define('TABLE_STATUS_DIRTY', 'DIRTY');
    if (!defined('TABLE_STATUS_RESERVED')) define('TABLE_STATUS_RESERVED', 'RESERVED');

    if (!defined('PAYMENT_METHOD_CASH')) define('PAYMENT_METHOD_CASH', 'CASH');
    if (!defined('PAYMENT_METHOD_CREDIT_CARD')) define('PAYMENT_METHOD_CREDIT_CARD', 'CREDIT_CARD');
    if (!defined('PAYMENT_METHOD_ONLINE_PAYMENT')) define('PAYMENT_METHOD_ONLINE_PAYMENT', 'ONLINE_PAYMENT');
    if (!defined('PAYMENT_METHOD_OTHER')) define('PAYMENT_METHOD_OTHER', 'OTHER');
    }
} else {
    // During error page loading, use default constants only
    if (!defined('ROLE_CUSTOMER')) define('ROLE_CUSTOMER', 'ROLE_CUSTOMER');
    if (!defined('ROLE_WAITER')) define('ROLE_WAITER', 'ROLE_WAITER');
    if (!defined('ROLE_KITCHEN')) define('ROLE_KITCHEN', 'ROLE_KITCHEN');
    if (!defined('ROLE_MANAGER')) define('ROLE_MANAGER', 'ROLE_MANAGER');
    if (!defined('ROLE_CASHIER')) define('ROLE_CASHIER', 'ROLE_CASHIER');

    if (!defined('ORDER_STATUS_PENDING')) define('ORDER_STATUS_PENDING', 'PENDING');
    if (!defined('ORDER_STATUS_PREPARING')) define('ORDER_STATUS_PREPARING', 'PREPARING');
    if (!defined('ORDER_STATUS_READY')) define('ORDER_STATUS_READY', 'READY');
    if (!defined('ORDER_STATUS_SERVED')) define('ORDER_STATUS_SERVED', 'SERVED');
    if (!defined('ORDER_STATUS_CANCELLED')) define('ORDER_STATUS_CANCELLED', 'CANCELLED');
    if (!defined('ORDER_STATUS_ISSUE')) define('ORDER_STATUS_ISSUE', 'ISSUE');
    if (!defined('ORDER_STATUS_ON_DELIVERY')) define('ORDER_STATUS_ON_DELIVERY', 'ON_DELIVERY');
    if (!defined('ORDER_STATUS_DELIVERED')) define('ORDER_STATUS_DELIVERED', 'DELIVERED');

    if (!defined('TABLE_STATUS_FREE')) define('TABLE_STATUS_FREE', 'FREE');
    if (!defined('TABLE_STATUS_OCCUPIED')) define('TABLE_STATUS_OCCUPIED', 'OCCUPIED');
    if (!defined('TABLE_STATUS_PAYMENT_PENDING')) define('TABLE_STATUS_PAYMENT_PENDING', 'PAYMENT_PENDING');
    if (!defined('TABLE_STATUS_DIRTY')) define('TABLE_STATUS_DIRTY', 'DIRTY');
    if (!defined('TABLE_STATUS_RESERVED')) define('TABLE_STATUS_RESERVED', 'RESERVED');

    if (!defined('PAYMENT_METHOD_CASH')) define('PAYMENT_METHOD_CASH', 'CASH');
    if (!defined('PAYMENT_METHOD_CREDIT_CARD')) define('PAYMENT_METHOD_CREDIT_CARD', 'CREDIT_CARD');
    if (!defined('PAYMENT_METHOD_ONLINE_PAYMENT')) define('PAYMENT_METHOD_ONLINE_PAYMENT', 'ONLINE_PAYMENT');
    if (!defined('PAYMENT_METHOD_OTHER')) define('PAYMENT_METHOD_OTHER', 'OTHER');
}

// Constants are now loaded dynamically from database via ConstantsService
// For backward compatibility, define helper functions that use ConstantsService
if (!function_exists('getConstant')) {
    function getConstant(string $type, string $key): ?string {
        // Skip database access during error page loading
        if (defined('ERROR_PAGE_LOADING')) {
            return null;
        }
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $constantsService = \App\Core\DependencyFactory::getConstantsService();
            return $constantsService->getValue($type, $key);
        } catch (\Exception $e) {
            return null;
        }
    }
}

// Include authentication helpers (skip during error page loading to prevent class redeclaration)
if (!defined('ERROR_PAGE_LOADING')) {
    require_once __DIR__ . '/../helpers/auth.php';
    require_once __DIR__ . '/../helpers/ui.php';
    require_once __DIR__ . '/../helpers/translations.php';
}

// Initialize language from session or default
// Ensure session is started before getting language
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../core/SessionManager.php';
    \App\Core\SessionManager::ensureSession(true);
}

// Get current language from session or default
$currentLang = $_SESSION['lang'] ?? $_SESSION['language'] ?? 'tr';

// Define CURRENT_LANGUAGE constant if not already defined
if (!defined('CURRENT_LANGUAGE')) {
    define('CURRENT_LANGUAGE', $currentLang);
}

// Also ensure getCurrentLanguage function works correctly
if (!function_exists('getCurrentLanguage')) {
    require_once __DIR__ . '/../helpers/translations.php';
}

// Currency formatting (defined in helpers/functions.php, but keep here as fallback)
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount = 0) {
        if (is_nan($amount) || $amount === null || $amount === '') return '0 ₺';
        try {
            return number_format((float)$amount, 2, ',', '.') . ' ₺';
        } catch (Exception $e) {
            return $amount . ' ₺';
        }
    }
}

function formatDate($timestamp) {
    try {
        return date('d/m/Y H:i', $timestamp);
    } catch (Exception $e) {
        return '-';
    }
}

function getDuration($startTime = null) {
    if (!$startTime) return '';
    $diff = floor((time() - $startTime) / 60);
    if ($diff < 60) return $diff . ' dk';
    $h = floor($diff / 60);
    $m = $diff % 60;
    return $h . 's ' . $m . 'dk';
}

function generateId() {
    return bin2hex(random_bytes(4)) . bin2hex(random_bytes(4)); // 16 character hex ID
}

// Service provider is initialized in App.php when the App instance is created
// No need to initialize it here in config.php

// Helper functions for getting services
function getUserService() {
    return \App\Core\DependencyFactory::getUserService();
}

function getUserRepository() {
    return \App\Core\DependencyFactory::getUserRepository();
}

function getRepository($name) {
    return \App\Core\DependencyFactory::getRepository($name);
}

function getService($name) {
    return \App\Core\DependencyFactory::getService($name);
}

// Helper functions for getting settings from database (instead of .env)
function getSiteName(): string {
    try {
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $siteName = $settingsService->getSiteName();
        if (!empty($siteName)) {
            return $siteName;
        }
    } catch (\Exception $e) {
        // Fallback to constant if database is not available
    }
    // Get site name from constant or use AppConfig fallback
    if (defined('SITE_NAME')) {
        return SITE_NAME;
    }
    try {
        if (class_exists('\App\Services\AppConfig')) {
            $appConfig = \App\Services\AppConfig::getInstance();
            return $appConfig->get('defaults.app_name', 'Restoran Yönetim Sistemi');
        }
    } catch (\Exception $e) {
        // Use default if AppConfig not available
    }
    return 'Restoran Yönetim Sistemi';
}

function getGeminiApiKey(): string {
    try {
        $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $apiKey = $settingsService->getGeminiApiKey();
        if (!empty($apiKey)) {
            return $apiKey;
        }
    } catch (\Exception $e) {
        // Fallback to constant if database is not available
    }
    return defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
}

// Service provider helper function removed - ServiceProvider is not a static class
// Use DependencyFactory helper functions (getService, getRepository, etc.) instead
// or access services through App::getInstance()->getServiceProvider()->get($name)

/**
 * Get base URL using centralized BaseUrlService
 * This function provides a convenient way to get the base URL throughout the application
 * 
 * @return string Base URL
 */
if (!function_exists('getBaseUrl')) {
    function getBaseUrl(): string {
        try {
            return \App\Services\BaseUrlService::getBaseUrl();
        } catch (\Exception $e) {
            // Fallback to constant if service is not available
            // Fallback to BASE_URL constant or auto-detect from server
            if (defined('BASE_URL')) {
                return BASE_URL;
            }
            // Last resort: auto-detect from server variables
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            return $protocol . '://' . $host;
        }
    }
}