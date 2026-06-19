<?php
namespace App\Services;

/**
 * Theme Service
 * Extends DesignSystem with additional theme management capabilities
 * Centralized color management and CSS variables support
 */
class ThemeService extends DesignSystem {
    private static $themeInstance = null;
    
    /**
     * Get ThemeService instance (singleton)
     * @return ThemeService
     */
    public static function getInstance() {
        if (self::$themeInstance === null) {
            self::$themeInstance = new self();
        }
        return self::$themeInstance;
    }
    
    /**
     * Get theme color by name
     * @param string $name Color name (e.g., 'background', 'primary', 'slate.500')
     * @param mixed $default Default value if not found
     * @return string Color value
     */
    public function getThemeColor($name, $default = null) {
        // Support dot notation for nested colors (e.g., 'slate.500')
        if (strpos($name, '.') !== false) {
            $parts = explode('.', $name, 2);
            $category = $parts[0];
            $shade = $parts[1];
            return $this->getColor($category, $shade) ?? $default;
        }
        
        // Direct color access
        return $this->getColor($name) ?? $default;
    }
    
    /**
     * Get CSS variables string for theme colors
     * @return string CSS variables
     */
    public function getCssVariables() {
        $vars = [];
        $colors = [
            'background' => $this->getBackground(),
            'backgroundDark' => $this->getColor('backgroundDark'),
            'primary' => $this->getPrimary(),
            'error' => $this->getError(),
            'success' => $this->getSuccess(),
        ];
        
        foreach ($colors as $name => $value) {
            if ($value) {
                $vars[] = "--theme-{$name}: {$value};";
            }
        }
        
        return ':root { ' . implode(' ', $vars) . ' }';
    }
    
    /**
     * Get background color (commonly used)
     * @return string
     */
    public function getBackgroundColor() {
        return $this->getBackground();
    }
    
    /**
     * Get theme color for meta tag (theme-color)
     * @return string
     */
    public function getThemeColorMeta() {
        return $this->getColor('backgroundDark', '#1e293b');
    }
}

