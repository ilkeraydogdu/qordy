<?php
namespace App\Services;

require_once __DIR__ . '/../repositories/SystemLabelRepository.php';

/**
 * Translation Service - MVC, OOP, Centralized, Dynamic Multi-Language Support
 * Fully dynamic database-driven translation system - NO hardcoded fallbacks
 */
class TranslationService {
    private $labelRepository;
    private $cache = [];
    private $currentLanguage = 'tr';
    private static $instance = null;
    
    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Ensure session is started before accessing it
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $this->labelRepository = new \App\Repositories\SystemLabelRepository(
            \App\Core\DependencyFactory::getDatabase()
        );
        $this->currentLanguage = $this->getCurrentLanguage();
    }
    
    /**
     * Get current language from session
     * @return string
     */
    public function getCurrentLanguage() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Support both 'lang' and 'language' for backward compatibility
        return $_SESSION['lang'] ?? $_SESSION['language'] ?? 'tr';
    }
    
    /**
     * Set current language
     * @param string $lang
     * @return void
     */
    public function setLanguage($lang) {
        if (in_array($lang, ['tr', 'en'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            // Set both keys for backward compatibility
            $_SESSION['lang'] = $lang;
            $_SESSION['language'] = $lang;
            $this->currentLanguage = $lang;
            $this->cache = []; // Clear cache when language changes
        }
    }
    
    /**
     * Translate a key (supports dot notation: 'menu.title')
     * @param string $key - Translation key (e.g., 'menu.title' or 'welcome')
     * @param string|null $lang - Language code (default: current language)
     * @param array $params - Parameters for string replacement
     * @return string
     */
    public function translate($key, $lang = null, $params = []) {
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }
        
        // Check cache first
        $cacheKey = $lang . '.' . $key;
        if (isset($this->cache[$cacheKey])) {
            $translation = $this->cache[$cacheKey];
        } else {
            // Parse dot notation (e.g., 'menu.title' -> type='menu', key='title')
            // For multi-level keys like 'auth.error.pin_already_active' -> type='auth', key='error.pin_already_active'
            $keys = explode('.', $key);
            $type = $keys[0] ?? 'common';
            // Join all parts after the first one as the label key
            $labelKey = count($keys) > 1 ? implode('.', array_slice($keys, 1)) : $key;
            
            // Get from database - fully dynamic, no fallback
            $translation = $this->labelRepository->getValue($type, $labelKey, $lang);
            
            // Cache the result (null if not found)
            $this->cache[$cacheKey] = $translation;
        }
        
        // Return null if translation not found or empty
        if ($translation === null || $translation === '') {
            return null;
        }
        
        // Replace parameters if provided
        if (!empty($params)) {
            foreach ($params as $paramKey => $paramValue) {
                // Convert null to empty string to avoid PHP 8.1+ deprecation warning
                $paramValue = $paramValue ?? '';
                $translation = str_replace(':' . $paramKey, (string)$paramValue, $translation);
            }
        }
        
        return $translation;
    }
    
    /**
     * Get all translations for a type
     * @param string $type - Translation type (e.g., 'menu', 'order', 'common')
     * @param string|null $lang - Language code
     * @return array
     */
    public function getTranslationsByType($type, $lang = null) {
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }
        
        $cacheKey = $lang . '.' . $type;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $labels = $this->labelRepository->getByType($type);
        $translations = [];
        
        foreach ($labels as $label) {
            $valueField = $lang === 'en' ? 'label_value_en' : 'label_value_tr';
            $value = $label[$valueField] ?? $label['label_value_tr'] ?? '';
            // Only add if value is not empty
            if (!empty($value)) {
                $translations[$label['label_key']] = $value;
            }
        }
        
        // Cache the result
        $this->cache[$cacheKey] = $translations;
        
        return $translations;
    }
    
    /**
     * Get language usage statistics
     * @return array Statistics with counts for Turkish, English, and both
     */
    public function getLanguageStatistics() {
        return $this->labelRepository->getLanguageStatistics();
    }
    
    /**
     * Get all translations for current language
     * @param string|null $lang
     * @return array
     */
    public function getAllTranslations($lang = null) {
        if ($lang === null) {
            $lang = $this->currentLanguage;
        }
        
        return $this->labelRepository->getAllAsArray($lang);
    }
    
    /**
     * Create or update a translation
     * @param string $type
     * @param string $key
     * @param string $valueTr
     * @param string $valueEn
     * @param string|null $color
     * @return bool
     */
    public function setTranslation($type, $key, $valueTr, $valueEn = '', $color = null) {
        $result = $this->labelRepository->upsert($type, $key, $valueTr, $valueEn, $color);
        // Clear cache for this type
        unset($this->cache[$this->currentLanguage . '.' . $type]);
        return $result;
    }
    
    /**
     * Bulk create/update translations
     * @param array $translations
     * @return int
     */
    public function bulkSetTranslations($translations) {
        $result = $this->labelRepository->bulkUpsert($translations);
        // Clear all cache
        $this->cache = [];
        return $result;
    }
    
    /**
     * Get available languages - Dynamic from settings
     * @return array
     */
    public function getAvailableLanguages() {
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $supportedLanguagesJson = $settingsService->getSetting('supported_languages') ?? '["tr","en"]';
            $supportedLanguages = json_decode($supportedLanguagesJson, true);
            
            if (!is_array($supportedLanguages) || empty($supportedLanguages)) {
                $supportedLanguages = ['tr', 'en'];
            }
            
            $allLanguages = [
                'tr' => ['code' => 'tr', 'name' => 'Türkçe', 'flag' => '🇹🇷'],
                'en' => ['code' => 'en', 'name' => 'English', 'flag' => '🇺🇸']
            ];
            
            $result = [];
            foreach ($supportedLanguages as $lang) {
                if (isset($allLanguages[$lang])) {
                    $result[$lang] = $allLanguages[$lang];
                }
            }
            
            return !empty($result) ? $result : $allLanguages;
        } catch (\Exception $e) {
            // Fallback to default languages
            return [
                'tr' => ['code' => 'tr', 'name' => 'Türkçe', 'flag' => '🇹🇷'],
                'en' => ['code' => 'en', 'name' => 'English', 'flag' => '🇺🇸']
            ];
        }
    }
    
    /**
     * Get language switcher HTML
     * @return string
     */
    public function getLanguageSwitcher() {
        $currentLang = $this->currentLanguage;
        $languages = $this->getAvailableLanguages();
        
        $html = '<div class="language-switcher flex items-center gap-2">';
        foreach ($languages as $code => $lang) {
            $active = $code === $currentLang ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-400';
            $html .= sprintf(
                '<button onclick="changeLanguage(\'%s\')" class="px-4 py-2 rounded-xl font-bold text-sm transition-all %s hover:bg-slate-800 hover:text-white">%s %s</button>',
                $code,
                $active,
                $lang['flag'],
                $lang['name']
            );
        }
        $html .= '</div>';
        
        return $html;
    }
}

