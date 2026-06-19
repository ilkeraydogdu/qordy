<?php
namespace App\Services;

use App\Core\BaseService;

/**
 * Image Processor Service
 * 
 * Handles image manipulation including resizing, optimization,
 * WebP conversion, watermarking, and quality adjustment
 */
class ImageProcessorService extends BaseService {
    
    private $config;
    private $storageService;
    
    /**
     * Constructor
     */
    public function __construct($repository = null) {
        $this->config = include __DIR__ . '/../config/image.php';
        $this->storageService = new StorageManagerService();
    }
    
    /**
     * Process uploaded image - create all size variants
     * @param string $sourcePath Full path to source image
     * @param string $entityType
     * @param string $entityId
     * @param string $baseFilename Base filename without extension
     * @return array Array of processed images with metadata
     */
    public function processImage($sourcePath, $entityType, $entityId, $baseFilename) {
        $results = [];
        
        try {
            // Get image info
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                throw new \Exception('Invalid image file');
            }
            
            // Create image resource from source
            $sourceImage = $this->createImageResource($sourcePath, $imageInfo[2]);
            if (!$sourceImage) {
                throw new \Exception('Failed to create image resource');
            }
            
            // Get source dimensions
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            // Process each size variant
            $sizes = $this->config['processing']['sizes'];
            
            foreach ($sizes as $sizeName => $sizeConfig) {
                // Skip if size is larger than source
                if ($sizeConfig['width'] && $sizeConfig['width'] > $sourceWidth && 
                    $sizeConfig['height'] && $sizeConfig['height'] > $sourceHeight) {
                    continue;
                }
                
                // Process this size variant
                $result = $this->processSize(
                    $sourceImage,
                    $sourcePath,
                    $imageInfo,
                    $entityType,
                    $entityId,
                    $baseFilename,
                    $sizeName,
                    $sizeConfig
                );
                
                if ($result['success']) {
                    $results[] = $result;
                }
            }
            
            // Clean up
            imagedestroy($sourceImage);
            
            return [
                'success' => true,
                'images' => $results,
            ];
            
        } catch (\Exception $e) {
            error_log("Image processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Process single size variant
     * @param resource $sourceImage
     * @param string $sourcePath
     * @param array $imageInfo
     * @param string $entityType
     * @param string $entityId
     * @param string $baseFilename
     * @param string $sizeName
     * @param array $sizeConfig
     * @return array
     */
    private function processSize($sourceImage, $sourcePath, $imageInfo, $entityType, $entityId, $baseFilename, $sizeName, $sizeConfig) {
        $results = [];
        
        try {
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            // Calculate dimensions
            if ($sizeName === 'original') {
                // Check if we need to resize original
                $maxWidth = $this->config['processing']['max_dimensions']['width'];
                $maxHeight = $this->config['processing']['max_dimensions']['height'];
                
                if ($sourceWidth > $maxWidth || $sourceHeight > $maxHeight) {
                    list($newWidth, $newHeight) = $this->calculateDimensions(
                        $sourceWidth, $sourceHeight, $maxWidth, $maxHeight, false
                    );
                } else {
                    $newWidth = $sourceWidth;
                    $newHeight = $sourceHeight;
                }
            } else {
                // Calculate new dimensions
                list($newWidth, $newHeight) = $this->calculateDimensions(
                    $sourceWidth,
                    $sourceHeight,
                    $sizeConfig['width'],
                    $sizeConfig['height'],
                    $sizeConfig['crop']
                );
            }
            
            // Create resized image
            $resizedImage = $this->resize(
                $sourceImage,
                $sourceWidth,
                $sourceHeight,
                $newWidth,
                $newHeight,
                $sizeConfig['crop']
            );
            
            // Apply watermark if needed
            if ($this->shouldApplyWatermark($entityType, $newWidth, $newHeight)) {
                $resizedImage = $this->applyWatermark($resizedImage, $newWidth, $newHeight);
            }
            
            // Get original extension
            $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            
            // Save main format (JPG/PNG/GIF)
            $filename = $baseFilename . '-' . $sizeName . '.' . $extension;
            $saved = $this->storageService->storeProcessed(
                $resizedImage,
                $entityType,
                $entityId,
                $filename,
                $extension
            );
            
            if ($saved['success']) {
                $results = [
                    'success' => true,
                    'size' => $sizeName,
                    'format' => $extension,
                    'path' => $saved['path'],
                    'url' => $saved['url'],
                    'filename' => $saved['filename'],
                    'dimensions' => $newWidth . 'x' . $newHeight,
                    'file_size' => $this->storageService->getSize($saved['path']),
                ];
            }
            
            // Convert to WebP if enabled
            if ($this->config['processing']['convert_to_webp'] && function_exists('imagewebp')) {
                $webpFilename = $baseFilename . '-' . $sizeName . '.webp';
                $webpSaved = $this->storageService->storeProcessed(
                    $resizedImage,
                    $entityType,
                    $entityId,
                    $webpFilename,
                    'webp'
                );
                
                if ($webpSaved['success']) {
                    $results['webp'] = [
                        'path' => $webpSaved['path'],
                        'url' => $webpSaved['url'],
                        'filename' => $webpSaved['filename'],
                        'file_size' => $this->storageService->getSize($webpSaved['path']),
                    ];
                }
            }
            
            // Clean up
            imagedestroy($resizedImage);
            
            return $results;
            
        } catch (\Exception $e) {
            error_log("Size processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Create image resource from file
     * @param string $path
     * @param int $type IMAGETYPE constant
     * @return resource|false
     */
    private function createImageResource($path, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    return imagecreatefromwebp($path);
                }
                return false;
            default:
                return false;
        }
    }
    
    /**
     * Calculate new dimensions maintaining aspect ratio
     * @param int $sourceWidth
     * @param int $sourceHeight
     * @param int $targetWidth
     * @param int $targetHeight
     * @param bool $crop
     * @return array [width, height]
     */
    private function calculateDimensions($sourceWidth, $sourceHeight, $targetWidth, $targetHeight, $crop = false) {
        if ($targetWidth === null && $targetHeight === null) {
            return [$sourceWidth, $sourceHeight];
        }
        
        $sourceRatio = $sourceWidth / $sourceHeight;
        
        if ($targetWidth === null) {
            $targetWidth = $targetHeight * $sourceRatio;
        }
        if ($targetHeight === null) {
            $targetHeight = $targetWidth / $sourceRatio;
        }
        
        $targetRatio = $targetWidth / $targetHeight;
        
        if ($crop) {
            // Crop to exact dimensions
            return [$targetWidth, $targetHeight];
        } else {
            // Fit within dimensions (maintain aspect ratio)
            if ($sourceRatio > $targetRatio) {
                // Image is wider
                $newWidth = $targetWidth;
                $newHeight = (int)($targetWidth / $sourceRatio);
            } else {
                // Image is taller
                $newHeight = $targetHeight;
                $newWidth = (int)($targetHeight * $sourceRatio);
            }
            
            return [$newWidth, $newHeight];
        }
    }
    
    /**
     * Resize image
     * @param resource $sourceImage
     * @param int $sourceWidth
     * @param int $sourceHeight
     * @param int $targetWidth
     * @param int $targetHeight
     * @param bool $crop
     * @return resource
     */
    private function resize($sourceImage, $sourceWidth, $sourceHeight, $targetWidth, $targetHeight, $crop = false) {
        // Create new image
        $newImage = imagecreatetruecolor($targetWidth, $targetHeight);
        
        // Preserve transparency
        $this->preserveTransparency($newImage);
        
        if ($crop) {
            // Calculate crop area
            $sourceRatio = $sourceWidth / $sourceHeight;
            $targetRatio = $targetWidth / $targetHeight;
            
            if ($sourceRatio > $targetRatio) {
                // Source is wider - crop width
                $cropHeight = $sourceHeight;
                $cropWidth = (int)($sourceHeight * $targetRatio);
                $cropX = (int)(($sourceWidth - $cropWidth) / 2);
                $cropY = 0;
            } else {
                // Source is taller - crop height
                $cropWidth = $sourceWidth;
                $cropHeight = (int)($sourceWidth / $targetRatio);
                $cropX = 0;
                $cropY = (int)(($sourceHeight - $cropHeight) / 2);
            }
            
            imagecopyresampled(
                $newImage, $sourceImage,
                0, 0,
                $cropX, $cropY,
                $targetWidth, $targetHeight,
                $cropWidth, $cropHeight
            );
        } else {
            // Simple resize
            imagecopyresampled(
                $newImage, $sourceImage,
                0, 0, 0, 0,
                $targetWidth, $targetHeight,
                $sourceWidth, $sourceHeight
            );
        }
        
        return $newImage;
    }
    
    /**
     * Preserve image transparency
     * @param resource $image
     */
    private function preserveTransparency($image) {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        
        // Fill with transparent background
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
    }
    
    /**
     * Check if watermark should be applied
     * @param string $entityType
     * @param int $width
     * @param int $height
     * @return bool
     */
    private function shouldApplyWatermark($entityType, $width, $height) {
        $watermarkConfig = $this->config['watermark'];
        
        if (!$watermarkConfig['enabled']) {
            return false;
        }
        
        // Check entity-specific watermark setting
        if (isset($this->config['entity_types'][$entityType]['watermark'])) {
            if (!$this->config['entity_types'][$entityType]['watermark']) {
                return false;
            }
        }
        
        // Check minimum size
        $minWidth = $watermarkConfig['min_image_size']['width'];
        $minHeight = $watermarkConfig['min_image_size']['height'];
        
        if ($width < $minWidth || $height < $minHeight) {
            return false;
        }
        
        // Check if watermark image exists
        if (!file_exists($watermarkConfig['image_path'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Apply watermark to image
     * @param resource $image
     * @param int $width
     * @param int $height
     * @return resource
     */
    private function applyWatermark($image, $width, $height) {
        try {
            $watermarkConfig = $this->config['watermark'];
            $watermarkPath = $watermarkConfig['image_path'];
            
            // Load watermark
            $watermarkInfo = getimagesize($watermarkPath);
            $watermark = $this->createImageResource($watermarkPath, $watermarkInfo[2]);
            
            if (!$watermark) {
                return $image;
            }
            
            $watermarkWidth = imagesx($watermark);
            $watermarkHeight = imagesy($watermark);
            
            // Calculate position
            $position = $watermarkConfig['position'];
            $margin = $watermarkConfig['margin'];
            
            switch ($position) {
                case 'top-left':
                    $destX = $margin;
                    $destY = $margin;
                    break;
                case 'top-right':
                    $destX = $width - $watermarkWidth - $margin;
                    $destY = $margin;
                    break;
                case 'bottom-left':
                    $destX = $margin;
                    $destY = $height - $watermarkHeight - $margin;
                    break;
                case 'bottom-right':
                default:
                    $destX = $width - $watermarkWidth - $margin;
                    $destY = $height - $watermarkHeight - $margin;
                    break;
                case 'center':
                    $destX = ($width - $watermarkWidth) / 2;
                    $destY = ($height - $watermarkHeight) / 2;
                    break;
            }
            
            // Apply with opacity
            $opacity = $watermarkConfig['opacity'];
            imagecopymerge(
                $image,
                $watermark,
                (int)$destX,
                (int)$destY,
                0, 0,
                $watermarkWidth,
                $watermarkHeight,
                $opacity
            );
            
            imagedestroy($watermark);
            
            return $image;
            
        } catch (\Exception $e) {
            error_log("Watermark error: " . $e->getMessage());
            return $image;
        }
    }
    
    /**
     * Optimize image (future enhancement - can add additional optimization)
     * @param string $path
     * @return bool
     */
    public function optimize($path) {
        // Placeholder for additional optimization
        // Could integrate with tools like jpegoptim, optipng, etc.
        return true;
    }
    
    /**
     * Get image dimensions
     * @param string $path
     * @return array|false [width, height] or false
     */
    public function getDimensions($path) {
        $info = @getimagesize($path);
        if (!$info) {
            return false;
        }
        return [$info[0], $info[1]];
    }
    
    /**
     * Check if image processing is available
     * @return array Status of required functions
     */
    public function checkAvailability() {
        return [
            'gd_available' => extension_loaded('gd'),
            'jpeg_support' => function_exists('imagecreatefromjpeg'),
            'png_support' => function_exists('imagecreatefrompng'),
            'gif_support' => function_exists('imagecreatefromgif'),
            'webp_support' => function_exists('imagewebp'),
            'webp_read_support' => function_exists('imagecreatefromwebp'),
        ];
    }
}

