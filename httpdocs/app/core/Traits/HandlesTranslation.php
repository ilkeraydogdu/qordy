<?php
namespace App\Core\Traits;

/**
 * HandlesTranslation Trait
 * Provides translation methods for controllers
 */
trait HandlesTranslation {
    /**
     * Translate a key (shortcut method)
     * @param string $key Translation key
     * @param array $params Parameters for translation
     * @return string Translated text
     */
    protected function t(string $key, array $params = []): string {
        if (!isset($this->translationService)) {
            $this->translationService = getTranslationService();
        }
        return $this->translationService->translate($key, null, $params);
    }
    
    /**
     * Get current language
     * @return string Language code
     */
    protected function getCurrentLanguage(): string {
        if (!isset($this->translationService)) {
            $this->translationService = getTranslationService();
        }
        return $this->translationService->getCurrentLanguage();
    }
    
    /**
     * Set language
     * @param string $languageCode Language code
     * @return void
     */
    protected function setLanguage(string $languageCode): void {
        if (!isset($this->translationService)) {
            $this->translationService = getTranslationService();
        }
        $this->translationService->setLanguage($languageCode);
    }
}

