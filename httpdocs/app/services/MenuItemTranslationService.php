<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\MenuItemTranslationRepository;

/**
 * Menu Item Translation Service
 * Handles business logic for menu item translations
 */
class MenuItemTranslationService extends BaseService {
    
    public function __construct(MenuItemTranslationRepository $repository) {
        parent::__construct($repository);
    }

    /**
     * Get menu item with translation for current language
     * @param string $menuItemId Menu item ID
     * @param string|null $languageCode Language code (default: current language)
     * @return array Menu item with translation
     */
    public function getMenuItemWithTranslation(string $menuItemId, ?string $languageCode = null): array {
        require_once __DIR__ . '/../helpers/translations.php';
        
        if ($languageCode === null) {
            $languageCode = getCurrentLanguage();
        }
        
        // Get base menu item
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $menuItem = $menuItemService->getMenuItemById($menuItemId);
        
        if (!$menuItem) {
            return [];
        }
        
        // Get translation
        $translation = $this->repository->getTranslation($menuItemId, $languageCode);
        
        // Merge translation data with menu item
        if ($translation) {
            $menuItem['name'] = $translation['name'] ?? $menuItem['name'];
            $menuItem['description'] = $translation['description'] ?? $menuItem['description'];
            
            // Parse JSON fields
            if (!empty($translation['ingredients'])) {
                $menuItem['ingredients'] = json_decode($translation['ingredients'], true) ?? [];
            }
            if (!empty($translation['extras'])) {
                $menuItem['extras'] = json_decode($translation['extras'], true) ?? [];
            }
            
            // SEO fields
            $menuItem['meta_title'] = $translation['meta_title'] ?? null;
            $menuItem['meta_description'] = $translation['meta_description'] ?? null;
            $menuItem['meta_keywords'] = $translation['meta_keywords'] ?? null;
            $menuItem['slug'] = $translation['slug'] ?? null;
        }
        
        return $menuItem;
    }

    /**
     * Get all menu items with translations for current language
     * @param string|null $languageCode Language code (default: current language)
     * @return array Menu items with translations
     */
    public function getAllMenuItemsWithTranslations(?string $languageCode = null): array {
        require_once __DIR__ . '/../helpers/translations.php';
        require_once __DIR__ . '/../core/DependencyFactory.php';
        
        if ($languageCode === null) {
            $languageCode = getCurrentLanguage();
        }
        
        $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $menuItems = $menuItemService->getAllMenuItems();
        
        $result = [];
        foreach ($menuItems as $item) {
            $translation = $this->repository->getTranslation($item['menu_item_id'], $languageCode);
            
            if ($translation) {
                $item['name'] = $translation['name'];
                $item['description'] = $translation['description'] ?? $item['description'];
                
                if (!empty($translation['ingredients'])) {
                    $item['ingredients'] = json_decode($translation['ingredients'], true) ?? [];
                }
                if (!empty($translation['extras'])) {
                    $item['extras'] = json_decode($translation['extras'], true) ?? [];
                }
            }
            
            $result[] = $item;
        }
        
        return $result;
    }

    /**
     * Save translations for a menu item
     * @param string $menuItemId Menu item ID
     * @param array $translations Array of translations [language_code => [name, description, ...]]
     * @return bool Success
     */
    public function saveTranslations(string $menuItemId, array $translations): bool {
        $success = true;
        
        foreach ($translations as $langCode => $translationData) {
            // Generate slug if name is provided
            if (!empty($translationData['name']) && empty($translationData['slug'])) {
                $translationData['slug'] = $this->repository->generateSlug(
                    $translationData['name'], 
                    $langCode
                );
            }
            
            // Convert arrays to JSON
            if (isset($translationData['ingredients']) && is_array($translationData['ingredients'])) {
                $translationData['ingredients'] = json_encode($translationData['ingredients'], JSON_UNESCAPED_UNICODE);
            }
            if (isset($translationData['extras']) && is_array($translationData['extras'])) {
                $translationData['extras'] = json_encode($translationData['extras'], JSON_UNESCAPED_UNICODE);
            }
            
            $translationData['menu_item_id'] = $menuItemId;
            $translationData['language_code'] = $langCode;
            
            if (!$this->repository->upsertTranslation($translationData)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Delete translations for a menu item
     * @param string $menuItemId Menu item ID
     * @return bool Success
     */
    public function deleteTranslations(string $menuItemId): bool {
        return $this->repository->deleteTranslationsByMenuItem($menuItemId);
    }

    /**
     * Get menu item by slug
     * @param string $slug URL slug
     * @param string|null $languageCode Language code (default: current language)
     * @return array|null Menu item with translation or null
     */
    public function getMenuItemBySlug(string $slug, ?string $languageCode = null): ?array {
        require_once __DIR__ . '/../helpers/translations.php';
        
        if ($languageCode === null) {
            $languageCode = getCurrentLanguage();
        }
        
        $translation = $this->repository->getBySlug($slug, $languageCode);
        
        if (!$translation) {
            return null;
        }
        
        return $this->getMenuItemWithTranslation($translation['menu_item_id'], $languageCode);
    }
    
    /**
     * Generate translations for all supported languages using AI
     * @param array $turkishContent Turkish content [name, description, ingredients, extras]
     * @param array $supportedLanguages Array of language codes
     * @param string $categoryName Category name for SEO
     * @return array Translations for all languages
     */
    public function generateAllTranslations(array $turkishContent, array $supportedLanguages, string $categoryName = ''): array {
        // Yeni merkezi AI servis yapısını kullan
        return \App\Services\AIService::generateAllTranslations($turkishContent, $supportedLanguages, $categoryName);
    }
    
    /**
     * Get translations formatted for edit form
     * @param string $menuItemId Menu item ID
     * @return array Translations organized by language
     */
    public function getTranslationsForEdit(string $menuItemId): array {
        $allTranslations = $this->repository->getTranslationsByMenuItem($menuItemId);
        
        $formatted = [];
        foreach ($allTranslations as $translation) {
            $lang = $translation['language_code'];
            $formatted[$lang] = [
                'name' => $translation['name'] ?? '',
                'description' => $translation['description'] ?? '',
                'meta_title' => $translation['meta_title'] ?? '',
                'meta_description' => $translation['meta_description'] ?? '',
                'meta_keywords' => $translation['meta_keywords'] ?? '',
                'slug' => $translation['slug'] ?? '',
                'ingredients' => !empty($translation['ingredients']) 
                    ? json_decode($translation['ingredients'], true) 
                    : [],
                'extras' => !empty($translation['extras']) 
                    ? json_decode($translation['extras'], true) 
                    : []
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Validate translations data
     * @param array $translations Translations array
     * @return array Validation errors (empty if valid)
     */
    public function validateTranslations(array $translations): array {
        $errors = [];
        
        foreach ($translations as $langCode => $translationData) {
            // Name is required for each language
            if (empty($translationData['name'])) {
                $errors[$langCode][] = 'Name is required';
            }
            
            // Meta title length check
            if (!empty($translationData['meta_title']) && strlen($translationData['meta_title']) > 60) {
                $errors[$langCode][] = 'Meta title must be 60 characters or less';
            }
            
            // Meta description length check
            if (!empty($translationData['meta_description']) && strlen($translationData['meta_description']) > 160) {
                $errors[$langCode][] = 'Meta description must be 160 characters or less';
            }
            
            // Validate ingredients array
            if (isset($translationData['ingredients']) && !is_array($translationData['ingredients'])) {
                $errors[$langCode][] = 'Ingredients must be an array';
            }
            
            // Validate extras array
            if (isset($translationData['extras']) && !is_array($translationData['extras'])) {
                $errors[$langCode][] = 'Extras must be an array';
            }
        }
        
        return $errors;
    }
}

