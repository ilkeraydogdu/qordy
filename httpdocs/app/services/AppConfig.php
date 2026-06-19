<?php
namespace App\Services;

/**
 * Application Config Service
 * Centralized management of application configuration values
 * Provides fallback values for currency, timezone, language, and magic numbers
 */
class AppConfig {
    private static $instance = null;
    private $config;
    private $settingsService;
    
    private function __construct() {
        $configPath = __DIR__ . '/../config/app_config.php';
        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } else {
            // Fallback defaults
            $this->config = [
                'defaults' => [
                    'currency' => 'TRY',
                    'timezone' => 'Europe/Istanbul',
                    'language' => 'tr',
                    'supported_languages' => ['tr', 'en'],
                    'app_name' => 'Qordy',
                ],
                'limits' => [
                    'session_timeout' => 86400, // 24 hours in seconds
                    'max_login_attempts' => 5,
                    'lockout_duration' => 900,
                    'smtp_port' => 587,
                    'low_stock_threshold' => 5,
                ],
            ];
        }
        
        // Try to get settings service for dynamic values (with error handling)
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            // Only try to get settings service if database is available
            // This prevents errors on landing pages that don't need database
            if (class_exists('\App\Core\DependencyFactory')) {
                try {
                    $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                } catch (\Exception $dbException) {
                    // Database not available - use fallback config only
                    $this->settingsService = null;
                }
            } else {
                $this->settingsService = null;
            }
        } catch (\Exception $e) {
            $this->settingsService = null;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get config value
     * @param string $key Config key (supports dot notation, e.g., 'defaults.currency')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Get currency (from settings or fallback)
     * @return string Currency code
     */
    public function getCurrency() {
        if ($this->settingsService) {
            $currency = $this->settingsService->getSetting('currency');
            if ($currency) {
                return $currency;
            }
        }
        return $this->get('defaults.currency', 'TRY');
    }
    
    /**
     * Get timezone (from settings or fallback)
     * @return string Timezone
     */
    public function getTimezone() {
        if ($this->settingsService) {
            $timezone = $this->settingsService->getSetting('timezone');
            if ($timezone) {
                return $timezone;
            }
        }
        return $this->get('defaults.timezone', 'Europe/Istanbul');
    }
    
    /**
     * Get default language (from settings or fallback)
     * @return string Language code
     */
    public function getDefaultLanguage() {
        if ($this->settingsService) {
            $language = $this->settingsService->getSetting('default_language');
            if ($language) {
                return $language;
            }
        }
        return $this->get('defaults.language', 'tr');
    }
    
    /**
     * Get supported languages (from settings or fallback)
     * @return array Array of language codes
     */
    public function getSupportedLanguages() {
        if ($this->settingsService) {
            $languagesJson = $this->settingsService->getSetting('supported_languages');
            if ($languagesJson) {
                $languages = json_decode($languagesJson, true);
                if (is_array($languages) && !empty($languages)) {
                    return $languages;
                }
            }
        }
        return $this->get('defaults.supported_languages', ['tr', 'en']);
    }
    
    /**
     * Get app name (from settings or fallback)
     * @return string App name
     */
    public function getAppName() {
        // Always return Qordy for now (can be overridden by database settings later)
        // This ensures consistent branding across the application
        if ($this->settingsService) {
            try {
                $appName = $this->settingsService->getSetting('site_name');
                if ($appName && $appName !== 'Qordy - Akıllı Restoran Sistemi') {
                    return $appName;
                }
            } catch (\Exception $e) {
                // Fallback to default if database query fails
            }
        }
        return $this->get('defaults.app_name', 'Qordy');
    }
    
    /**
     * Get limit value (magic number)
     * @param string $key Limit key (e.g., 'session_timeout', 'max_login_attempts')
     * @param mixed $default Default value
     * @return mixed
     */
    public function getLimit($key, $default = null) {
        return $this->get('limits.' . $key, $default);
    }
    
    /**
     * Get session timeout
     * @return int Timeout in seconds
     */
    public function getSessionTimeout() {
        if ($this->settingsService) {
            $timeout = $this->settingsService->getSetting('session_timeout');
            if ($timeout !== null) {
                return (int)$timeout * 60; // Convert minutes to seconds
            }
        }
        return $this->getLimit('session_timeout', 86400); // 24 hours in seconds
    }
    
    /**
     * Get max login attempts
     * @return int
     */
    public function getMaxLoginAttempts() {
        return $this->getLimit('max_login_attempts', 5);
    }
    
    /**
     * Get lockout duration
     * @return int Duration in seconds
     */
    public function getLockoutDuration() {
        return $this->getLimit('lockout_duration', 900);
    }
}

