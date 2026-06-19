<?php
namespace App\Core;

/**
 * Merkezi Konfigürasyon Yönetimi Sistemi
 * Dinamik ve merkezi konfigürasyon yönetimi için
 */
class ConfigManager {
    private static $config = [];
    private static $loaded = false;
    private static $configDir = '';
    private static $cacheEnabled = false;
    private static $cacheTtl = 300; // 5 dakika

    /**
     * Konfigürasyon sistemini başlat
     */
    public static function initialize(): void {
        self::$configDir = __DIR__ . '/../config/';
        self::loadConfig();
    }

    /**
     * Konfigürasyon dosyalarını yükle
     */
    private static function loadConfig(): void {
        if (self::$loaded) {
            return;
        }

        // Cache kontrolü
        if (self::$cacheEnabled) {
            $cacheKey = 'app_config_cache';
            $cachedConfig = self::getFromCache($cacheKey);
            
            if ($cachedConfig) {
                self::$config = $cachedConfig;
                self::$loaded = true;
                return;
            }
        }

        // Ana konfigürasyon dosyalarını yükle
        $configFiles = [
            'app.php',
            'database.php',
            'security.php',
            'cache.php',
            'logging.php',
            'api.php',
            'payment.php',
            'email.php',
            'sms.php'
        ];

        foreach ($configFiles as $file) {
            $filePath = self::$configDir . $file;
            if (file_exists($filePath)) {
                $config = include $filePath;
                if (is_array($config)) {
                    self::$config = array_merge_recursive(self::$config, $config);
                }
            }
        }

        // Ortam bazlı konfigürasyon (development, production, staging)
        $env = isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'development';
        $envConfigFile = self::$configDir . "app_{$env}.php";
        if (file_exists($envConfigFile)) {
            $envConfig = include $envConfigFile;
            if (is_array($envConfig)) {
                self::$config = array_merge_recursive(self::$config, $envConfig);
            }
        }

        // Veritabanı konfigürasyonu (veritabanından gelen ayarlar)
        self::loadDatabaseConfig();

        // Cache'e yaz
        if (self::$cacheEnabled) {
            self::setToCache($cacheKey, self::$config);
        }

        self::$loaded = true;
    }

    /**
     * Veritabanı konfigürasyonunu yükle
     */
    private static function loadDatabaseConfig(): void {
        try {
            // Sistem ayarlarını veritabanından yükle
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE is_active = 1");
            $stmt->execute();
            $dbSettings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($dbSettings as $setting) {
                $key = $setting['setting_key'];
                $value = $setting['setting_value'];
                
                // JSON değerleri decode et
                $decodedValue = json_decode($value, true);
                if ($decodedValue !== null) {
                    $value = $decodedValue;
                }
                
                // Anahtar hiyerarşisine göre konfigürasyonu ekle
                self::setNestedConfig($key, $value);
            }
        } catch (\Exception $e) {
            // Veritabanı hatası durumunda sadece logla
            error_log("Database config load error: " . $e->getMessage());
        }
    }

    /**
     * İç içe konfigürasyon ayarı
     */
    private static function setNestedConfig(string $key, $value): void {
        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Konfigürasyon değeri al
     */
    public static function get(string $key, $default = null) {
        if (!self::$loaded) {
            self::initialize();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Konfigürasyon değeri ayarla
     */
    public static function set(string $key, $value): void {
        if (!self::$loaded) {
            self::initialize();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }

        // Cache'i güncelle
        if (self::$cacheEnabled) {
            $cacheKey = 'app_config_cache';
            self::setToCache($cacheKey, self::$config);
        }
    }

    /**
     * Konfigürasyon var mı kontrol et
     */
    public static function has(string $key): bool {
        if (!self::$loaded) {
            self::initialize();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Tüm konfigürasyonu getir
     */
    public static function all(): array {
        if (!self::$loaded) {
            self::initialize();
        }
        return self::$config;
    }

    /**
     * Cache'den değer al (CacheService kullanarak)
     */
    private static function getFromCache(string $key) {
        try {
            $cacheService = DependencyFactory::getCacheService();
            return $cacheService->get($key);
        } catch (\Exception $e) {
            error_log("Config cache get error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache'e değer yaz (CacheService kullanarak)
     */
    private static function setToCache(string $key, $data): bool {
        try {
            $cacheService = DependencyFactory::getCacheService();
            return $cacheService->set($key, $data, self::$cacheTtl);
        } catch (\Exception $e) {
            error_log("Config cache set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cache sistemini etkinleştir/devre dışı bırak
     */
    public static function setCacheEnabled(bool $enabled): void {
        self::$cacheEnabled = $enabled;
    }

    /**
     * Cache TTL ayarla
     */
    public static function setCacheTtl(int $ttl): void {
        self::$cacheTtl = $ttl;
    }

    /**
     * Konfigürasyon dosyası yükle
     */
    public static function loadFile(string $fileName): array {
        $filePath = self::$configDir . $fileName . '.php';
        if (file_exists($filePath)) {
            return include $filePath;
        }
        return [];
    }

    /**
     * Ortam değişkenlerini yükle
     */
    public static function loadEnvironment(): void {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Tırnak işaretlerini kaldır
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    
                    $_ENV[$key] = $value;
                    if (!defined(strtoupper($key))) {
                        define(strtoupper($key), $value);
                    }
                }
            }
        }
    }

    /**
     * Konfigürasyonu veritabanına kaydet
     */
    public static function saveToDatabase(string $key, $value): bool {
        try {
            $db = DependencyFactory::getDatabase();
            
            // Değeri JSON'a çevir
            $value = is_array($value) || is_object($value) ? json_encode($value) : $value;
            
            // Varolan kaydı güncelle veya yeni oluştur
            $stmt = $db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_at = NOW()
            ");
            
            return $stmt->execute([$key, $value]);
        } catch (\Exception $e) {
            error_log("Config save to database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Konfigürasyonu temizle
     */
    public static function clear(): void {
        self::$config = [];
        self::$loaded = false;
        
        // Cache dosyalarını temizle (CacheService kullanarak)
        if (self::$cacheEnabled) {
            try {
                $cacheService = DependencyFactory::getCacheService();
                $cacheService->delete('app_config_cache');
            } catch (\Exception $e) {
                error_log("Config cache clear error: " . $e->getMessage());
            }
        }
    }
}