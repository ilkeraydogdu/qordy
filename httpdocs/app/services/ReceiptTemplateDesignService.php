<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ReceiptTemplateLayoutRepository;

/**
 * Receipt Template Design Service
 * Handles dynamic receipt template layout operations
 */
class ReceiptTemplateDesignService extends BaseService {
    private $receiptTemplateService;
    
    public function __construct(ReceiptTemplateLayoutRepository $repository) {
        parent::__construct($repository);
        $this->receiptTemplateService = \App\Core\DependencyFactory::getReceiptTemplateService();
    }
    
    /**
     * Get layout by ID
     * @param string $layoutId Layout ID
     * @return array|null Layout data or null
     */
    public function getLayoutById(string $layoutId): ?array {
        return $this->repository->findById($layoutId);
    }
    
    /**
     * Get layout by business ID
     * @param string|null $businessId Business ID
     * @return array|null Layout data or null
     */
    public function getLayoutByBusinessId(?string $businessId): ?array {
        $layout = $this->repository->getByBusinessId($businessId);
        
        // If no layout exists, create default layout
        if (!$layout) {
            $layout = $this->createDefaultLayout($businessId);
        }
        
        return $layout;
    }
    
    /**
     * Get all layouts by business ID
     * @param string|null $businessId Business ID
     * @return array Layouts
     */
    public function getAllLayouts(?string $businessId = null): array {
        return $this->repository->getAllByBusinessId($businessId);
    }
    
    /**
     * Create new layout
     * @param array $layoutData Layout data
     * @param bool $skipValidation Skip validation (for internal use)
     * @return string|array Layout ID on success, array with errors on failure
     */
    public function createLayout(array $layoutData, bool $skipValidation = false) {
        // Validate layout data unless skipped (for default layout creation)
        if (!$skipValidation) {
            $validation = $this->validateLayout($layoutData);
            if (!$validation['valid']) {
                return ['success' => false, 'errors' => $validation['errors']];
            }
        }
        
        // Validate required fields
        if (empty($layoutData['layout_name'])) {
            return ['success' => false, 'errors' => ['Layout adı gereklidir']];
        }
        
        // Ensure layout_data exists and is array
        if (!isset($layoutData['layout_data']) || !is_array($layoutData['layout_data'])) {
            $layoutData['layout_data'] = $this->getDefaultLayoutStructure();
        }
        
        // Ensure social_media is array if provided
        if (isset($layoutData['social_media']) && !is_array($layoutData['social_media'])) {
            $layoutData['social_media'] = [];
        }
        
        // Ensure icon_positions is array if provided
        if (isset($layoutData['icon_positions']) && !is_array($layoutData['icon_positions'])) {
            $layoutData['icon_positions'] = [];
        }
        
        // Set defaults
        if (!isset($layoutData['is_active'])) {
            $layoutData['is_active'] = 1;
        }
        if (!isset($layoutData['is_default'])) {
            $layoutData['is_default'] = 0;
        }
        
        $layoutId = $this->repository->create($layoutData);

        // If this is set as default, update default status
        if ($layoutId && isset($layoutData['is_default']) && $layoutData['is_default']) {
            // receipt_template_layouts stores tenant under tenant_id; accept both inputs.
            $businessId = $layoutData['tenant_id'] ?? $layoutData['business_id'] ?? null;
            $this->repository->setAsDefault($layoutId, $businessId);
        }
        
        return $layoutId;
    }
    
    /**
     * Update layout
     * @param string $layoutId Layout ID
     * @param array $layoutData Layout data
     * @param bool $skipValidation Skip validation
     * @return bool|array True on success, array with errors on validation failure
     */
    public function updateLayout(string $layoutId, array $layoutData, bool $skipValidation = false) {
        // Validate layout data if layout_data is being updated
        if (!$skipValidation && isset($layoutData['layout_data'])) {
            $validation = $this->validateLayout($layoutData);
            if (!$validation['valid']) {
                return ['success' => false, 'errors' => $validation['errors']];
            }
        }
        
        // Ensure JSON fields are arrays
        if (isset($layoutData['layout_data']) && !is_array($layoutData['layout_data'])) {
            unset($layoutData['layout_data']);
        }
        if (isset($layoutData['social_media']) && !is_array($layoutData['social_media'])) {
            $layoutData['social_media'] = [];
        }
        if (isset($layoutData['icon_positions']) && !is_array($layoutData['icon_positions'])) {
            $layoutData['icon_positions'] = [];
        }
        
        $result = $this->repository->update($layoutId, $layoutData);
        
        // If this is set as default, update default status
        if ($result && isset($layoutData['is_default']) && $layoutData['is_default']) {
            $layout = $this->repository->findById($layoutId);
            $businessId = $layout['tenant_id'] ?? null;
            $this->repository->setAsDefault($layoutId, $businessId);
        }
        
        return $result;
    }
    
    /**
     * Delete layout
     * @param string $layoutId Layout ID
     * @return bool Success
     */
    public function deleteLayout(string $layoutId): bool {
        return $this->repository->delete($layoutId);
    }
    
    /**
     * Set layout as default
     * @param string $layoutId Layout ID
     * @param string|null $businessId Business ID
     * @return bool Success
     */
    public function setAsDefault(string $layoutId, ?string $businessId = null): bool {
        return $this->repository->setAsDefault($layoutId, $businessId);
    }
    
    /**
     * Create default layout for business
     * @param string|null $businessId Business ID
     * @return array|null Default layout or null
     */
    public function createDefaultLayout(?string $businessId = null): ?array {
        $defaultLayoutData = [
            // receipt_template_layouts uses tenant_id column
            'tenant_id' => $businessId,
            'business_id' => $businessId,
            'template_id' => null,
            'layout_name' => $businessId ? 'İşletme Varsayılan Layout' : 'Sistem Varsayılan Layout',
            'layout_data' => $this->getDefaultLayoutStructure(),
            'social_media' => [],
            'icon_positions' => [
                'order_date_icon' => 'calendar',
                'payment_time_icon' => 'clock',
                'receipt_icon' => 'receipt',
                'waiter_icon' => 'user',
                'table_icon' => 'table'
            ],
            'is_default' => 1,
            'is_active' => 1
        ];
        
        // Skip validation for default layout (we know it's valid)
        $layoutId = $this->createLayout($defaultLayoutData, true);
        
        // Handle both old return format (string) and new format (array with errors)
        if (is_array($layoutId) && isset($layoutId['success']) && !$layoutId['success']) {
            error_log("Failed to create default layout: " . json_encode($layoutId['errors']));
            return null;
        }
        
        if ($layoutId && !is_array($layoutId)) {
            return $this->repository->findById($layoutId);
        }
        
        return null;
    }
    
    /**
     * Get default layout structure (components with positions)
     * @return array Default layout structure
     */
    public function getDefaultLayoutStructure(): array {
        return [
            'components' => [
                [
                    'type' => 'header',
                    'position' => ['x' => 0, 'y' => 0],
                    'settings' => [
                        'alignment' => 'center',
                        'font_size' => 'large',
                        'bold' => true
                    ]
                ],
                [
                    'type' => 'business_name',
                    'position' => ['x' => 0, 'y' => 1],
                    'settings' => [
                        'alignment' => 'center',
                        'font_size' => 'large',
                        'bold' => true
                    ]
                ],
                [
                    'type' => 'social_media',
                    'position' => ['x' => 0, 'y' => 2],
                    'settings' => [
                        'alignment' => 'center',
                        'show_icons' => true
                    ]
                ],
                [
                    'type' => 'divider',
                    'position' => ['x' => 0, 'y' => 3]
                ],
                [
                    'type' => 'order_info',
                    'position' => ['x' => 0, 'y' => 4],
                    'settings' => [
                        'show_order_date' => true,
                        'show_payment_time' => true,
                        'show_receipt_number' => true,
                        'order_date_icon' => 'calendar',
                        'payment_time_icon' => 'clock',
                        'receipt_icon' => 'receipt'
                    ]
                ],
                [
                    'type' => 'waiter_table_info',
                    'position' => ['x' => 0, 'y' => 5],
                    'settings' => [
                        'show_waiter' => true,
                        'show_table' => true,
                        'waiter_icon' => 'user',
                        'table_icon' => 'table'
                    ]
                ],
                [
                    'type' => 'divider',
                    'position' => ['x' => 0, 'y' => 6]
                ],
                [
                    'type' => 'items_header',
                    'position' => ['x' => 0, 'y' => 7],
                    'settings' => [
                        'columns' => ['name', 'quantity', 'price'],
                        'alignment' => 'left'
                    ]
                ],
                [
                    'type' => 'items_list',
                    'position' => ['x' => 0, 'y' => 8],
                    'settings' => [
                        'show_item_name' => true,
                        'show_quantity' => true,
                        'show_price' => true
                    ]
                ],
                [
                    'type' => 'divider',
                    'position' => ['x' => 0, 'y' => 9]
                ],
                [
                    'type' => 'totals',
                    'position' => ['x' => 0, 'y' => 10],
                    'settings' => [
                        'show_subtotal' => true,
                        'show_tax' => true,
                        'show_discount' => true,
                        'show_total' => true,
                        'total_bold' => true,
                        'total_size' => 'large'
                    ]
                ],
                [
                    'type' => 'divider',
                    'position' => ['x' => 0, 'y' => 11]
                ],
                [
                    'type' => 'payment_method',
                    'position' => ['x' => 0, 'y' => 12],
                    'settings' => [
                        'alignment' => 'center'
                    ]
                ],
                [
                    'type' => 'footer',
                    'position' => ['x' => 0, 'y' => 13],
                    'settings' => [
                        'alignment' => 'center',
                        'thank_you_message' => 'Bizi tercih ettiğiniz için teşekkürler!'
                    ]
                ]
            ],
            'settings' => [
                'paper_width' => 80,
                'font_family' => 'monospace',
                'show_qr_code' => false,
                'show_barcode' => false
            ]
        ];
    }
    
    /**
     * Valid component types for receipt templates
     */
    private const VALID_COMPONENT_TYPES = [
        'header',
        'business_name',
        'business_info',
        'social_media',
        'divider',
        'order_info',
        'waiter_table_info',
        'items_header',
        'items_list',
        'totals',
        'payment_method',
        'footer',
        'qr_code',
        'barcode',
        'custom_text',
        'logo',
        'spacer'
    ];
    
    /**
     * Valid alignment values
     */
    private const VALID_ALIGNMENTS = ['left', 'center', 'right'];
    
    /**
     * Valid font sizes
     */
    private const VALID_FONT_SIZES = ['small', 'normal', 'medium', 'large', 'xlarge'];
    
    /**
     * Validate layout data with comprehensive JSON schema validation
     * @param array $layoutData Layout data
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateLayout(array $layoutData): array {
        $errors = [];
        
        // Check required fields
        if (empty($layoutData['layout_name'])) {
            $errors[] = 'Layout adı gereklidir';
        } elseif (strlen($layoutData['layout_name']) > 255) {
            $errors[] = 'Layout adı en fazla 255 karakter olabilir';
        }
        
        // Validate layout_data structure
        if (isset($layoutData['layout_data'])) {
            if (!is_array($layoutData['layout_data'])) {
                $errors[] = 'Layout data dizi olmalıdır';
            } else {
                $layoutErrors = $this->validateLayoutDataStructure($layoutData['layout_data']);
                $errors = array_merge($errors, $layoutErrors);
            }
        }
        
        // Validate social_media if provided
        if (isset($layoutData['social_media'])) {
            if (!is_array($layoutData['social_media'])) {
                $errors[] = 'Social media verisi dizi olmalıdır';
            } else {
                $socialErrors = $this->validateSocialMedia($layoutData['social_media']);
                $errors = array_merge($errors, $socialErrors);
            }
        }
        
        // Validate icon_positions if provided
        if (isset($layoutData['icon_positions'])) {
            if (!is_array($layoutData['icon_positions'])) {
                $errors[] = 'Icon positions verisi dizi olmalıdır';
            } else {
                $iconErrors = $this->validateIconPositions($layoutData['icon_positions']);
                $errors = array_merge($errors, $iconErrors);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate layout_data structure (components and settings)
     * @param array $layoutData Layout data
     * @return array Validation errors
     */
    private function validateLayoutDataStructure(array $layoutData): array {
        $errors = [];
        
        // Validate components
        if (!isset($layoutData['components'])) {
            $errors[] = 'Layout data components alanı gereklidir';
        } elseif (!is_array($layoutData['components'])) {
            $errors[] = 'Layout data components dizi olmalıdır';
        } else {
            foreach ($layoutData['components'] as $index => $component) {
                $componentErrors = $this->validateComponent($component, $index);
                $errors = array_merge($errors, $componentErrors);
            }
        }
        
        // Validate settings if present
        if (isset($layoutData['settings'])) {
            if (!is_array($layoutData['settings'])) {
                $errors[] = 'Layout settings dizi olmalıdır';
            } else {
                $settingsErrors = $this->validateLayoutSettings($layoutData['settings']);
                $errors = array_merge($errors, $settingsErrors);
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate a single component
     * @param mixed $component Component data
     * @param int $index Component index
     * @return array Validation errors
     */
    private function validateComponent($component, int $index): array {
        $errors = [];
        $prefix = "Component [{$index}]";
        
        if (!is_array($component)) {
            $errors[] = "{$prefix}: Geçersiz component yapısı (dizi olmalı)";
            return $errors;
        }
        
        // Validate type
        if (!isset($component['type'])) {
            $errors[] = "{$prefix}: 'type' alanı gereklidir";
        } elseif (!is_string($component['type'])) {
            $errors[] = "{$prefix}: 'type' string olmalıdır";
        } elseif (!in_array($component['type'], self::VALID_COMPONENT_TYPES)) {
            $errors[] = "{$prefix}: Geçersiz component tipi '{$component['type']}'. Geçerli tipler: " . implode(', ', self::VALID_COMPONENT_TYPES);
        }
        
        // Validate position if present
        if (isset($component['position'])) {
            if (!is_array($component['position'])) {
                $errors[] = "{$prefix}: 'position' dizi olmalıdır";
            } else {
                if (!isset($component['position']['x']) || !is_numeric($component['position']['x'])) {
                    $errors[] = "{$prefix}: 'position.x' sayısal değer olmalıdır";
                }
                if (!isset($component['position']['y']) || !is_numeric($component['position']['y'])) {
                    $errors[] = "{$prefix}: 'position.y' sayısal değer olmalıdır";
                }
            }
        }
        
        // Validate settings if present
        if (isset($component['settings'])) {
            if (!is_array($component['settings'])) {
                $errors[] = "{$prefix}: 'settings' dizi olmalıdır";
            } else {
                $settingsErrors = $this->validateComponentSettings($component['settings'], $prefix);
                $errors = array_merge($errors, $settingsErrors);
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate component settings
     * @param array $settings Component settings
     * @param string $prefix Error prefix
     * @return array Validation errors
     */
    private function validateComponentSettings(array $settings, string $prefix): array {
        $errors = [];
        
        // Validate alignment if present
        if (isset($settings['alignment'])) {
            if (!is_string($settings['alignment']) || !in_array($settings['alignment'], self::VALID_ALIGNMENTS)) {
                $errors[] = "{$prefix}: Geçersiz alignment değeri. Geçerli değerler: " . implode(', ', self::VALID_ALIGNMENTS);
            }
        }
        
        // Validate font_size if present
        if (isset($settings['font_size'])) {
            if (!is_string($settings['font_size']) || !in_array($settings['font_size'], self::VALID_FONT_SIZES)) {
                $errors[] = "{$prefix}: Geçersiz font_size değeri. Geçerli değerler: " . implode(', ', self::VALID_FONT_SIZES);
            }
        }
        
        // Validate boolean fields
        $booleanFields = ['bold', 'show_icons', 'show_order_date', 'show_payment_time', 
                         'show_receipt_number', 'show_waiter', 'show_table', 
                         'show_subtotal', 'show_tax', 'show_discount', 'show_total', 'total_bold'];
        foreach ($booleanFields as $field) {
            if (isset($settings[$field]) && !is_bool($settings[$field]) && !in_array($settings[$field], [0, 1, '0', '1', true, false], true)) {
                $errors[] = "{$prefix}: '{$field}' boolean değer olmalıdır";
            }
        }
        
        // Validate columns if present (for items_header)
        if (isset($settings['columns'])) {
            if (!is_array($settings['columns'])) {
                $errors[] = "{$prefix}: 'columns' dizi olmalıdır";
            }
        }
        
        // Validate thank_you_message if present
        if (isset($settings['thank_you_message'])) {
            if (!is_string($settings['thank_you_message'])) {
                $errors[] = "{$prefix}: 'thank_you_message' string olmalıdır";
            } elseif (strlen($settings['thank_you_message']) > 500) {
                $errors[] = "{$prefix}: 'thank_you_message' en fazla 500 karakter olabilir";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate layout settings
     * @param array $settings Layout settings
     * @return array Validation errors
     */
    private function validateLayoutSettings(array $settings): array {
        $errors = [];
        
        // Validate paper_width
        if (isset($settings['paper_width'])) {
            if (!is_numeric($settings['paper_width'])) {
                $errors[] = "Layout settings: 'paper_width' sayısal değer olmalıdır";
            } elseif ($settings['paper_width'] < 40 || $settings['paper_width'] > 120) {
                $errors[] = "Layout settings: 'paper_width' 40-120 mm arasında olmalıdır";
            }
        }
        
        // Validate font_family
        if (isset($settings['font_family'])) {
            $validFonts = ['monospace', 'sans-serif', 'serif'];
            if (!is_string($settings['font_family']) || !in_array($settings['font_family'], $validFonts)) {
                $errors[] = "Layout settings: Geçersiz font_family. Geçerli değerler: " . implode(', ', $validFonts);
            }
        }
        
        // Validate boolean fields
        $booleanFields = ['show_qr_code', 'show_barcode'];
        foreach ($booleanFields as $field) {
            if (isset($settings[$field]) && !is_bool($settings[$field]) && !in_array($settings[$field], [0, 1, '0', '1', true, false], true)) {
                $errors[] = "Layout settings: '{$field}' boolean değer olmalıdır";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate social media data
     * @param array $socialMedia Social media data
     * @return array Validation errors
     */
    private function validateSocialMedia(array $socialMedia): array {
        $errors = [];
        $validPlatforms = ['instagram', 'facebook', 'twitter', 'tiktok', 'youtube', 'website', 'phone', 'email'];
        
        foreach ($socialMedia as $platform => $value) {
            if (!in_array($platform, $validPlatforms)) {
                $errors[] = "Social media: Geçersiz platform '{$platform}'. Geçerli platformlar: " . implode(', ', $validPlatforms);
            }
            if (!is_string($value)) {
                $errors[] = "Social media: '{$platform}' değeri string olmalıdır";
            } elseif (strlen($value) > 255) {
                $errors[] = "Social media: '{$platform}' değeri en fazla 255 karakter olabilir";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate icon positions data
     * @param array $iconPositions Icon positions data
     * @return array Validation errors
     */
    private function validateIconPositions(array $iconPositions): array {
        $errors = [];
        $validIcons = ['calendar', 'clock', 'receipt', 'user', 'table', 'check', 'x', 'info', 'warning', 'star'];
        $validPositionKeys = ['order_date_icon', 'payment_time_icon', 'receipt_icon', 'waiter_icon', 'table_icon'];
        
        foreach ($iconPositions as $key => $icon) {
            if (!in_array($key, $validPositionKeys)) {
                // Allow custom keys but warn
                continue;
            }
            if (!is_string($icon)) {
                $errors[] = "Icon positions: '{$key}' değeri string olmalıdır";
            } elseif (!in_array($icon, $validIcons)) {
                $errors[] = "Icon positions: Geçersiz icon '{$icon}' for '{$key}'. Geçerli iconlar: " . implode(', ', $validIcons);
            }
        }
        
        return $errors;
    }
    
    /**
     * Get valid component types
     * @return array Valid component types
     */
    public function getValidComponentTypes(): array {
        return self::VALID_COMPONENT_TYPES;
    }
    
    /**
     * Get valid alignments
     * @return array Valid alignments
     */
    public function getValidAlignments(): array {
        return self::VALID_ALIGNMENTS;
    }
    
    /**
     * Get valid font sizes
     * @return array Valid font sizes
     */
    public function getValidFontSizes(): array {
        return self::VALID_FONT_SIZES;
    }
    
    /**
     * Get layout for receipt generation (business-specific or system default)
     * @param string|null $businessId Business ID
     * @return array Layout data
     */
    public function getLayoutForReceipt(?string $businessId = null): array {
        $layout = $this->getLayoutByBusinessId($businessId);
        
        if (!$layout) {
            // Return default structure if no layout found
            return [
                'layout_id' => null,
                'layout_data' => $this->getDefaultLayoutStructure(),
                'social_media' => [],
                'icon_positions' => [
                    'order_date_icon' => 'calendar',
                    'payment_time_icon' => 'clock',
                    'receipt_icon' => 'receipt',
                    'waiter_icon' => 'user',
                    'table_icon' => 'table'
                ]
            ];
        }
        
        return [
            'layout_id' => $layout['layout_id'],
            'layout_data' => $layout['layout_data'] ?? $this->getDefaultLayoutStructure(),
            'social_media' => $layout['social_media'] ?? [],
            'icon_positions' => $layout['icon_positions'] ?? []
        ];
    }
}