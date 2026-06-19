<?php
/**
 * Image Helper Functions
 * 
 * Convenient functions for working with the image management system
 * throughout the application
 */

use App\Services\ImageService;
use App\Repositories\MediaFileRepository;
use App\Core\DependencyFactory;

/**
 * Get image service instance
 * @return ImageService
 */
function getImageService() {
    static $imageService = null;
    
    if ($imageService === null) {
        $db = DependencyFactory::getDatabase();
        $repository = new MediaFileRepository($db);
        $imageService = new ImageService($repository);
    }
    
    return $imageService;
}

/**
 * Upload an image
 * Quick helper for uploading images
 * 
 * @param array $file $_FILES element
 * @param string $entityType Entity type (product, logo, category, avatar)
 * @param string|null $entityId Entity ID
 * @param array $options Additional options
 * @return array Result array
 */
function uploadImage($file, $entityType, $entityId = null, $options = []) {
    $imageService = getImageService();
    return $imageService->upload($file, $entityType, $entityId, $options);
}

/**
 * Get image URL
 * Quick helper to get image URL for an entity
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @param string $size Size variant (thumbnail, medium, large, original)
 * @param bool $webp Prefer WebP format
 * @return string|null Image URL or null if not found
 */
function getImageUrl($entityType, $entityId, $size = 'medium', $webp = false) {
    $imageService = getImageService();
    return $imageService->getImageUrl($entityType, $entityId, $size, $webp);
}

/**
 * Get primary image
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @param string $size Size variant
 * @return array|null Image data or null
 */
function getPrimaryImage($entityType, $entityId, $size = 'original') {
    $imageService = getImageService();
    return $imageService->getPrimaryImage($entityType, $entityId, $size);
}

/**
 * Get all images for entity
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @param string|null $size Optional size filter
 * @return array Array of images
 */
function getEntityImages($entityType, $entityId, $size = null) {
    $imageService = getImageService();
    return $imageService->getByEntity($entityType, $entityId, $size);
}

/**
 * Get responsive image URLs
 * Returns all size variants for responsive image display
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @return array Responsive URLs array
 */
function getResponsiveImages($entityType, $entityId) {
    $imageService = getImageService();
    return $imageService->getResponsiveUrls($entityType, $entityId);
}

/**
 * Delete images for entity
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @return array Result array
 */
function deleteImages($entityType, $entityId) {
    $imageService = getImageService();
    return $imageService->deleteByEntity($entityType, $entityId);
}

/**
 * Generate image tag with proper attributes
 * Creates an <img> tag with SEO and performance attributes
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @param array $options Options (size, class, alt, lazy, webp)
 * @return string HTML img tag or empty string
 */
function imageTag($entityType, $entityId, $options = []) {
    $size = $options['size'] ?? 'medium';
    $class = $options['class'] ?? '';
    $webp = $options['webp'] ?? false;
    $lazy = $options['lazy'] ?? true;
    
    $image = getPrimaryImage($entityType, $entityId, $size);
    
    if (!$image) {
        return '';
    }
    
    $url = getImageUrl($entityType, $entityId, $size, $webp);
    $alt = $options['alt'] ?? $image['alt_text'] ?? '';
    $title = $options['title'] ?? $image['title'] ?? '';
    
    $attributes = [
        'src="' . htmlspecialchars($url) . '"',
        'alt="' . htmlspecialchars($alt) . '"',
    ];
    
    if ($title) {
        $attributes[] = 'title="' . htmlspecialchars($title) . '"';
    }
    
    if ($class) {
        $attributes[] = 'class="' . htmlspecialchars($class) . '"';
    }
    
    if ($lazy) {
        $attributes[] = 'loading="lazy"';
    }
    
    if (!empty($image['dimensions'])) {
        list($width, $height) = explode('x', $image['dimensions']);
        $attributes[] = 'width="' . $width . '"';
        $attributes[] = 'height="' . $height . '"';
    }
    
    return '<img ' . implode(' ', $attributes) . ' />';
}

/**
 * Generate responsive picture element
 * Creates a <picture> element with WebP and multiple sizes
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @param array $options Options (class, alt, sizes)
 * @return string HTML picture tag or empty string
 */
function pictureTag($entityType, $entityId, $options = []) {
    $responsive = getResponsiveImages($entityType, $entityId);
    
    if (empty($responsive['original'])) {
        return '';
    }
    
    $class = $options['class'] ?? '';
    $alt = $options['alt'] ?? '';
    $sizes = $options['sizes'] ?? '(max-width: 400px) 400px, (max-width: 800px) 800px, 1200px';
    
    $html = '<picture>';
    
    // WebP sources
    if (!empty($responsive['webp'])) {
        $srcset = [];
        if (isset($responsive['webp']['thumbnail'])) {
            $srcset[] = $responsive['webp']['thumbnail'] . ' 150w';
        }
        if (isset($responsive['webp']['medium'])) {
            $srcset[] = $responsive['webp']['medium'] . ' 400w';
        }
        if (isset($responsive['webp']['large'])) {
            $srcset[] = $responsive['webp']['large'] . ' 1200w';
        }
        
        if (!empty($srcset)) {
            $html .= '<source type="image/webp" srcset="' . implode(', ', $srcset) . '" sizes="' . $sizes . '" />';
        }
    }
    
    // Regular format sources
    $srcset = [];
    if (isset($responsive['thumbnail'])) {
        $srcset[] = $responsive['thumbnail'] . ' 150w';
    }
    if (isset($responsive['medium'])) {
        $srcset[] = $responsive['medium'] . ' 400w';
    }
    if (isset($responsive['large'])) {
        $srcset[] = $responsive['large'] . ' 1200w';
    }
    
    if (!empty($srcset)) {
        $html .= '<source srcset="' . implode(', ', $srcset) . '" sizes="' . $sizes . '" />';
    }
    
    // Fallback img tag
    $imgClass = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
    $imgAlt = ' alt="' . htmlspecialchars($alt) . '"';
    $html .= '<img src="' . $responsive['medium'] . '"' . $imgClass . $imgAlt . ' loading="lazy" />';
    
    $html .= '</picture>';
    
    return $html;
}

/**
 * Generate background image style
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @param string $size Size variant
 * @return string CSS style attribute or empty string
 */
function backgroundImageStyle($entityType, $entityId, $size = 'large') {
    $url = getImageUrl($entityType, $entityId, $size);
    
    if (!$url) {
        return '';
    }
    
    return 'style="background-image: url(\'' . htmlspecialchars($url, ENT_QUOTES) . '\');"';
}

/**
 * Get placeholder image URL
 * Returns a placeholder when no image is available
 * 
 * @param string $type Type of placeholder (product, avatar, logo, etc.)
 * @return string Placeholder URL
 */
function getPlaceholderImage($type = 'default') {
    $placeholders = [
        'product' => '/assets/images/placeholder-product.png',
        'avatar' => '/assets/images/placeholder-avatar.png',
        'logo' => '/assets/images/placeholder-logo.png',
        'category' => '/assets/images/placeholder-category.png',
        'default' => '/assets/images/placeholder.png',
    ];
    
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    return $baseUrl . ($placeholders[$type] ?? $placeholders['default']);
}

/**
 * Get image or placeholder
 * Returns image URL or placeholder if not found
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @param string $size Size variant
 * @return string Image URL or placeholder
 */
function getImageOrPlaceholder($entityType, $entityId, $size = 'medium') {
    $url = getImageUrl($entityType, $entityId, $size);
    
    if ($url) {
        return $url;
    }
    
    return getPlaceholderImage($entityType);
}

/**
 * Check if entity has images
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @return bool True if has images
 */
function hasImages($entityType, $entityId) {
    $image = getPrimaryImage($entityType, $entityId);
    return $image !== null;
}

/**
 * Format file size
 * Convert bytes to human-readable format
 * 
 * @param int $bytes File size in bytes
 * @param int $decimals Decimal places
 * @return string Formatted size
 */
function formatFileSize($bytes, $decimals = 2) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, $decimals) . ' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, $decimals) . ' MB';
    } else {
        return round($bytes / 1073741824, $decimals) . ' GB';
    }
}

/**
 * Generate srcset attribute
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @return string srcset attribute value
 */
function generateSrcset($entityType, $entityId) {
    $responsive = getResponsiveImages($entityType, $entityId);
    $srcset = [];
    
    if (isset($responsive['thumbnail'])) {
        $srcset[] = $responsive['thumbnail'] . ' 150w';
    }
    if (isset($responsive['medium'])) {
        $srcset[] = $responsive['medium'] . ' 400w';
    }
    if (isset($responsive['large'])) {
        $srcset[] = $responsive['large'] . ' 1200w';
    }
    
    return implode(', ', $srcset);
}

/**
 * Get image count for entity
 * 
 * @param string $entityType Entity type
 * @param string $entityId Entity ID
 * @return int Number of images
 */
function getImageCount($entityType, $entityId) {
    $images = getEntityImages($entityType, $entityId, 'original');
    return count($images);
}

