<?php
namespace App\Services;

/**
 * Menu Dictionary Service
 * Provides custom translations for menu items
 * Uses a dictionary lookup before falling back to AI translation
 */
class MenuDictionaryService {
    
    /**
     * Menu dictionary: Turkish (normalized) => English translations
     * This dictionary contains common Turkish menu items and their preferred English translations
     */
    private static $dictionary = [
        // Main dishes
        'mantarli tavuk' => 'Mushroom Chicken',
        'tavuklu mantar' => 'Chicken with Mushrooms',
        'adana kebap' => 'Adana Kebab',
        'adana kebab' => 'Adana Kebab',
        'iskender' => 'Iskender Kebab',
        'iskender kebap' => 'Iskender Kebab',
        'doner' => 'Doner Kebab',
        'doner kebap' => 'Doner Kebab',
        'lahmacun' => 'Lahmacun',
        'pide' => 'Turkish Pide',
        'kofte' => 'Meatballs',
        'kofte kebap' => 'Meatball Kebab',
        'kuzu tandir' => 'Lamb Tandoori',
        'tavuk tandir' => 'Chicken Tandoori',
        'kuzu sis' => 'Lamb Shish',
        'tavuk sis' => 'Chicken Shish',
        'tavuk sote' => 'Chicken Saute',
        'tavuk guvec' => 'Chicken Casserole',
        'kuzu guvec' => 'Lamb Casserole',
        
        // Beverages
        'nargile' => 'Shisha',
        'cay' => 'Turkish Tea',
        'kahve' => 'Turkish Coffee',
        'turk kahvesi' => 'Turkish Coffee',
        'ayran' => 'Ayran',
        'limonata' => 'Lemonade',
        'portakal suyu' => 'Orange Juice',
        'elma suyu' => 'Apple Juice',
        
        // Desserts
        'baklava' => 'Baklava',
        'kunefe' => 'Kunefe',
        'sutlac' => 'Rice Pudding',
        'kazandibi' => 'Caramelized Milk Pudding',
        'tulumba tatlisi' => 'Tulumba Dessert',
        'revani' => 'Revani',
        
        // Appetizers
        'humus' => 'Hummus',
        'baba ganus' => 'Baba Ganoush',
        'cacik' => 'Cacik',
        'haydari' => 'Haydari',
        'ezme' => 'Spicy Tomato Salad',
        'patlican salatasi' => 'Eggplant Salad',
        'coban salatasi' => 'Shepherd Salad',
        
        // Soups
        'mercimek corbasi' => 'Lentil Soup',
        'ezogelin corbasi' => 'Ezogelin Soup',
        'tavuk corbasi' => 'Chicken Soup',
        'yayla corbasi' => 'Yogurt Soup',
        
        // Breakfast items
        'menemen' => 'Menemen',
        'sucuklu yumurta' => 'Eggs with Sucuk',
        'pastirmali yumurta' => 'Eggs with Pastirma',
        'sucuk' => 'Sucuk',
        'pastirma' => 'Pastirma',
        'beyaz peynir' => 'White Cheese',
        'kasar peyniri' => 'Kashar Cheese',
        
        // Other common items
        'pilav' => 'Rice',
        'bulgur pilavi' => 'Bulgur Pilaf',
        'patates kizartmasi' => 'French Fries',
        'patates' => 'Potatoes',
        'salata' => 'Salad',
        'ekmek' => 'Bread',
        'pide ekmegi' => 'Pita Bread',
    ];
    
    /**
     * Get translation from dictionary
     * 
     * @param string $turkishText Turkish text (will be normalized)
     * @param string $targetLanguage Target language code (currently only 'en' is supported)
     * @return string|null Translated text or null if not found
     */
    public function getTranslation(string $turkishText, string $targetLanguage = 'en'): ?string {
        if ($targetLanguage !== 'en') {
            // Currently only English dictionary is available
            return null;
        }
        
        // Normalize the input text
        require_once __DIR__ . '/../helpers/text_normalization.php';
        $normalized = normalizeTextForTranslation($turkishText);
        
        // Look up in dictionary
        return self::$dictionary[$normalized] ?? null;
    }
    
    /**
     * Check if a translation exists in dictionary
     * 
     * @param string $turkishText Turkish text (will be normalized)
     * @param string $targetLanguage Target language code
     * @return bool True if translation exists
     */
    public function hasTranslation(string $turkishText, string $targetLanguage = 'en'): bool {
        return $this->getTranslation($turkishText, $targetLanguage) !== null;
    }
    
    /**
     * Add a custom translation to dictionary (runtime only, not persisted)
     * Useful for adding custom menu items
     * 
     * @param string $turkishText Turkish text (will be normalized)
     * @param string $englishText English translation
     * @return void
     */
    public function addTranslation(string $turkishText, string $englishText): void {
        require_once __DIR__ . '/../helpers/text_normalization.php';
        $normalized = normalizeTextForTranslation($turkishText);
        self::$dictionary[$normalized] = $englishText;
    }
    
    /**
     * Get all dictionary entries (for debugging/admin purposes)
     * 
     * @return array Dictionary entries
     */
    public function getAllEntries(): array {
        return self::$dictionary;
    }
}

