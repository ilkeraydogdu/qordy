<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Menu Item Translation Repository
 * Handles database operations for menu item translations
 * 
 * @package App\Repositories
 */
class MenuItemTranslationRepository extends BaseRepository {
    protected $table = 'menu_item_translations';
    protected $primaryKey = 'translation_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get translation for a menu item in a specific language
     * @param string $menuItemId Menu item ID
     * @param string $languageCode Language code (tr, en, etc.)
     * @return array|null Translation data or null
     */
    public function getTranslation(string $menuItemId, string $languageCode): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE menu_item_id = :menu_item_id AND language_code = :language_code 
                LIMIT 1";
        return $this->fetchOne($sql, [
            'menu_item_id' => $menuItemId,
            'language_code' => $languageCode
        ]);
    }

    /**
     * Get all translations for a menu item
     * @param string $menuItemId Menu item ID
     * @return array All translations for the menu item
     */
    public function getTranslationsByMenuItem(string $menuItemId): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE menu_item_id = :menu_item_id 
                ORDER BY language_code";
        return $this->fetchAll($sql, ['menu_item_id' => $menuItemId]);
    }

    /**
     * Create or update translation
     * @param array $translationData Translation data
     * @return bool Success
     */
    public function upsertTranslation(array $translationData): bool {
        if (empty($translationData['translation_id'])) {
            $translationData['translation_id'] = generateId('mit');
        }
        
        // Check if translation exists
        $existing = $this->getTranslation(
            $translationData['menu_item_id'], 
            $translationData['language_code']
        );
        
        if ($existing) {
            // Update existing translation
            return $this->update($existing['translation_id'], $translationData);
        } else {
            // Create new translation
            return $this->create($translationData);
        }
    }

    /**
     * Delete translation
     * @param string $menuItemId Menu item ID
     * @param string $languageCode Language code
     * @return bool Success
     */
    public function deleteTranslation(string $menuItemId, string $languageCode): bool {
        $sql = "DELETE FROM {$this->table} 
                WHERE menu_item_id = :menu_item_id AND language_code = :language_code";
        return $this->execute($sql, [
            'menu_item_id' => $menuItemId,
            'language_code' => $languageCode
        ]);
    }

    /**
     * Delete all translations for a menu item
     * @param string $menuItemId Menu item ID
     * @return bool Success
     */
    public function deleteTranslationsByMenuItem(string $menuItemId): bool {
        $sql = "DELETE FROM {$this->table} WHERE menu_item_id = :menu_item_id";
        return $this->execute($sql, ['menu_item_id' => $menuItemId]);
    }

    /**
     * Get menu item by slug and language
     * @param string $slug URL slug
     * @param string $languageCode Language code
     * @return array|null Menu item translation or null
     */
    public function getBySlug(string $slug, string $languageCode): ?array {
        $sql = "SELECT mit.*, mi.* 
                FROM {$this->table} mit
                INNER JOIN menu_items mi ON mit.menu_item_id = mi.menu_item_id
                WHERE mit.slug = :slug AND mit.language_code = :language_code
                LIMIT 1";
        return $this->fetchOne($sql, [
            'slug' => $slug,
            'language_code' => $languageCode
        ]);
    }

    /**
     * Generate slug from name
     * @param string $name Menu item name
     * @param string $languageCode Language code
     * @return string URL-friendly slug
     */
    public function generateSlug(string $name, string $languageCode): string {
        // Use centralized generateSlug helper function
        require_once __DIR__ . '/../helpers/functions.php';
        $slug = generateSlug($name);
        
        // Check if slug already exists (menu item specific duplicate check)
        $existing = $this->getBySlug($slug, $languageCode);
        if ($existing) {
            // Append number if exists
            $counter = 1;
            $originalSlug = $slug;
            while ($existing) {
                $slug = $originalSlug . '-' . $counter;
                $existing = $this->getBySlug($slug, $languageCode);
                $counter++;
            }
        }
        
        return $slug;
    }
}

