<?php
namespace App\Services;

/**
 * Storage Manager Service
 * 
 * Manages file storage with organized folder structure, SEO-friendly naming,
 * automatic directory creation, and cleanup operations
 */
class StorageManagerService {
    
    private $config;
    private $basePath;
    private $baseUrl;
    
    /**
     * Constructor
     */
    public function __construct($repository = null) {
        $this->config = include __DIR__ . '/../config/image.php';
        $this->basePath = $this->config['storage']['base_path'];
        $this->baseUrl = $this->config['storage']['base_url'];
        
        // CRITICAL: Normalize base path (resolve relative paths, remove .., etc.)
        $this->basePath = realpath($this->basePath) ?: $this->basePath;
        // If still relative, resolve from config directory
        if (!file_exists($this->basePath) || !is_dir($this->basePath)) {
            $configPath = __DIR__ . '/../config/image.php';
            $resolvedPath = realpath(dirname($configPath) . '/../../' . ltrim($this->config['storage']['base_path'], '/'));
            if ($resolvedPath && is_dir($resolvedPath)) {
                $this->basePath = $resolvedPath;
            }
        }
        
        // Ensure base upload directory exists and is writable
        if (!$this->ensureDirectoryExists($this->basePath)) {
            \App\Core\Logger::error("StorageManagerService: Failed to create base directory", [
                'base_path' => $this->basePath,
                'config_path' => $this->config['storage']['base_path']
            ]);
        }
    }
    
    /**
     * Store uploaded file
     * @param array $file $_FILES array element
     * @param string $entityType
     * @param string $entityId
     * @param string $seoName SEO-friendly name for the file
     * @return array ['success' => bool, 'path' => string, 'url' => string, 'filename' => string]
     */
    public function store($file, $entityType, $entityId, $seoName = null) {
        try {
            // Validate file input
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new \Exception('Invalid file upload');
            }
            
            // Generate storage path
            $storagePath = $this->generateStoragePath($entityType, $entityId);
            
            // Create directory if doesn't exist
            $fullPath = $this->basePath . '/' . $storagePath;
            // Normalize path (remove double slashes, resolve .., etc.)
            $fullPath = str_replace(['//', '\\'], ['/', '/'], $fullPath);
            $fullPath = rtrim($fullPath, '/');
            
            // CRITICAL: Ensure the target directory exists and is writable
            // ensureDirectoryExists handles creation and writability check
            if (!$this->ensureDirectoryExists($fullPath)) {
                throw new \Exception("Failed to create or access directory: {$fullPath}");
            }
            
            // Generate SEO-friendly filename
            $filename = $this->generateFilename($file['name'], $seoName);
            
            // Full file path
            $filePath = $fullPath . '/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                throw new \Exception("Failed to move uploaded file to {$filePath}. Error: {$errorMsg}");
            }
            
            // Set proper permissions
            @chmod($filePath, 0644);
            
            // Generate relative path and URL
            $relativePath = $storagePath . '/' . $filename;
            $url = $this->baseUrl . '/' . $relativePath;
            
            return [
                'success' => true,
                'path' => $relativePath,
                'url' => $url,
                'filename' => $filename,
                'full_path' => $filePath,
            ];
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("StorageManagerService::store - Upload failed", [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
                'file_name' => $file['name'] ?? 'unknown',
                'file_size' => $file['size'] ?? 0,
                'file_type' => $file['type'] ?? 'unknown'
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Store processed image (from GD/Imagick resource)
     * @param resource|object $imageResource GD resource or Imagick object
     * @param string $entityType
     * @param string $entityId
     * @param string $filename
     * @param string $format (jpg, png, webp, etc.)
     * @return array
     */
    public function storeProcessed($imageResource, $entityType, $entityId, $filename, $format = 'jpg') {
        try {
            // Generate storage path
            $storagePath = $this->generateStoragePath($entityType, $entityId);
            
            // Create directory if doesn't exist
            $fullPath = $this->basePath . '/' . $storagePath;
            $this->ensureDirectoryExists($fullPath);
            
            // Full file path
            $filePath = $fullPath . '/' . $filename;
            
            // Save image based on format
            $success = false;
            $quality = $this->config['processing']['quality'];
            
            switch (strtolower($format)) {
                case 'jpg':
                case 'jpeg':
                    $success = imagejpeg($imageResource, $filePath, $quality['jpeg']);
                    break;
                case 'png':
                    $success = imagepng($imageResource, $filePath, $quality['png']);
                    break;
                case 'gif':
                    $success = imagegif($imageResource, $filePath);
                    break;
                case 'webp':
                    if (function_exists('imagewebp')) {
                        $success = imagewebp($imageResource, $filePath, $quality['webp']);
                    }
                    break;
            }
            
            if (!$success) {
                throw new \Exception("Failed to save {$format} image");
            }
            
            // Set proper permissions
            chmod($filePath, 0644);
            
            // Generate relative path and URL
            $relativePath = $storagePath . '/' . $filename;
            $url = $this->baseUrl . '/' . $relativePath;
            
            return [
                'success' => true,
                'path' => $relativePath,
                'url' => $url,
                'filename' => $filename,
                'full_path' => $filePath,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Store product image (special handling for products)
     * Stores directly to businesses/{business_id}/product/{product_name}.png
     * @param array $file $_FILES array element
     * @param string $businessId Business ID
     * @param string $productName Product name (will be converted to SEO-friendly filename)
     * @return array ['success' => bool, 'path' => string, 'url' => string, 'filename' => string]
     */
    public function storeProductImage($file, $businessId, $productName) {
        try {
            // Validate file input
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new \Exception('Invalid file upload');
            }
            
            // Validate business ID
            if (empty($businessId)) {
                throw new \Exception('Business ID is required for product image upload');
            }
            
            // Validate product name
            if (empty($productName)) {
                throw new \Exception('Product name is required for product image upload');
            }
            
            // Generate storage path: business/{business_id}/product
            $sanitizedBusinessId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $businessId);
            $storagePath = "business/{$sanitizedBusinessId}/product";
            
            // Create directory if doesn't exist
            $fullPath = $this->basePath . '/' . $storagePath;
            $fullPath = str_replace(['//', '\\'], ['/', '/'], $fullPath);
            $fullPath = rtrim($fullPath, '/');
            
            // Ensure the target directory exists and is writable
            if (!$this->ensureDirectoryExists($fullPath)) {
                throw new \Exception("Failed to create or access directory: {$fullPath}");
            }
            
            // Generate SEO-friendly filename from product name
            if (!function_exists('productNameToFilename')) {
                require_once __DIR__ . '/../helpers/seo.php';
            }
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'png';
            $filename = productNameToFilename($productName, $extension);
            
            // Full file path
            $filePath = $fullPath . '/' . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                throw new \Exception("Failed to move uploaded file to {$filePath}. Error: {$errorMsg}");
            }
            
            // Set proper permissions
            @chmod($filePath, 0644);
            
            // Generate relative path and URL
            $relativePath = $storagePath . '/' . $filename;
            $url = $this->baseUrl . '/' . $relativePath;
            
            return [
                'success' => true,
                'path' => $relativePath,
                'url' => $url,
                'filename' => $filename,
                'full_path' => $filePath,
            ];
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("StorageManagerService::storeProductImage - Upload failed", [
                'business_id' => $businessId,
                'product_name' => $productName,
                'error' => $e->getMessage(),
                'file_name' => $file['name'] ?? 'unknown',
                'file_size' => $file['size'] ?? 0,
                'file_type' => $file['type'] ?? 'unknown'
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Delete file from storage
     * @param string $relativePath Relative path from base upload directory
     * @return bool
     */
    public function delete($relativePath) {
        try {
            $fullPath = $this->basePath . '/' . $relativePath;
            
            if (!file_exists($fullPath)) {
                return true; // Already deleted
            }
            
            if (!is_file($fullPath)) {
                throw new \Exception('Path is not a file');
            }
            
            return unlink($fullPath);
            
        } catch (\Exception $e) {
            error_log("Storage delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete entire directory for an entity
     * @param string $entityType
     * @param string $entityId
     * @return bool
     */
    public function deleteEntityDirectory($entityType, $entityId) {
        try {
            $storagePath = $this->generateStoragePath($entityType, $entityId);
            $fullPath = $this->basePath . '/' . $storagePath;
            
            if (!file_exists($fullPath)) {
                return true; // Already deleted
            }
            
            return $this->deleteDirectory($fullPath);
            
        } catch (\Exception $e) {
            error_log("Storage delete directory error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Move file to new location
     * @param string $oldPath
     * @param string $newPath
     * @return bool
     */
    public function move($oldPath, $newPath) {
        try {
            $oldFullPath = $this->basePath . '/' . $oldPath;
            $newFullPath = $this->basePath . '/' . $newPath;
            
            if (!file_exists($oldFullPath)) {
                throw new \Exception('Source file does not exist');
            }
            
            // Create destination directory if needed
            $newDir = dirname($newFullPath);
            $this->ensureDirectoryExists($newDir);
            
            return rename($oldFullPath, $newFullPath);
            
        } catch (\Exception $e) {
            error_log("Storage move error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Copy file to new location
     * @param string $sourcePath
     * @param string $destPath
     * @return bool
     */
    public function copy($sourcePath, $destPath) {
        try {
            $sourceFullPath = $this->basePath . '/' . $sourcePath;
            $destFullPath = $this->basePath . '/' . $destPath;
            
            if (!file_exists($sourceFullPath)) {
                throw new \Exception('Source file does not exist');
            }
            
            // Create destination directory if needed
            $destDir = dirname($destFullPath);
            $this->ensureDirectoryExists($destDir);
            
            return copy($sourceFullPath, $destFullPath);
            
        } catch (\Exception $e) {
            error_log("Storage copy error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if file exists
     * @param string $relativePath
     * @return bool
     */
    public function exists($relativePath) {
        $fullPath = $this->basePath . '/' . $relativePath;
        return file_exists($fullPath) && is_file($fullPath);
    }
    
    /**
     * Get file size
     * @param string $relativePath
     * @return int|false Size in bytes or false on error
     */
    public function getSize($relativePath) {
        $fullPath = $this->basePath . '/' . $relativePath;
        if (!file_exists($fullPath)) {
            return false;
        }
        return filesize($fullPath);
    }
    
    /**
     * Get file URL
     * @param string $relativePath
     * @param bool $useCDN
     * @return string
     */
    public function getUrl($relativePath, $useCDN = false) {
        if ($useCDN && $this->config['cdn']['enabled']) {
            return $this->config['cdn']['url'] . '/' . $relativePath;
        }
        
        return $this->baseUrl . '/' . $relativePath;
    }
    
    /**
     * Generate storage path for entity
     * Format: entityType/year/month/entityId
     * @param string $entityType
     * @param string $entityId
     * @return string
     */
    private function generateStoragePath($entityType, $entityId) {
        $year = date('Y');
        $month = date('m');
        
        $entityPath = $this->config['entity_types'][$entityType]['path'] ?? 'other';
        
        // Create unique folder per entity
        $sanitizedId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $entityId);
        
        return "{$entityPath}/{$year}/{$month}/{$sanitizedId}";
    }
    
    /**
     * Generate SEO-friendly filename
     * @param string $originalName
     * @param string $seoName Optional SEO name
     * @return string
     */
    private function generateFilename($originalName, $seoName = null) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if ($seoName) {
            // Use provided SEO name
            $name = $this->sanitizeName($seoName);
        } else {
            // Use original filename
            $name = pathinfo($originalName, PATHINFO_FILENAME);
            $name = $this->sanitizeName($name);
        }
        
        // Add unique identifier
        $unique = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        
        // Combine and limit length
        $maxLength = 100;
        if (strlen($name) > $maxLength) {
            $name = substr($name, 0, $maxLength);
        }
        
        return $name . '-' . $unique . '.' . $extension;
    }
    
    /**
     * Sanitize name for SEO
     * @param string $name
     * @return string
     */
    private function sanitizeName($name) {
        // Convert to lowercase
        $name = strtolower($name);
        
        // Transliterate non-ASCII characters
        if ($this->config['seo']['transliterate']) {
            $name = $this->transliterate($name);
        }
        
        // Replace non-alphanumeric with separator
        $separator = $this->config['seo']['filename_separator'];
        $name = preg_replace('/[^a-z0-9]+/', $separator, $name);
        
        // Remove multiple separators
        $name = preg_replace('/' . preg_quote($separator) . '+/', $separator, $name);
        
        // Trim separators from ends
        $name = trim($name, $separator);
        
        return $name;
    }
    
    /**
     * Transliterate non-ASCII characters
     * @param string $text
     * @return string
     */
    private function transliterate($text) {
        // Turkish specific replacements
        $turkish = [
            'ç' => 'c', 'Ç' => 'c',
            'ğ' => 'g', 'Ğ' => 'g',
            'ı' => 'i', 'İ' => 'i',
            'ö' => 'o', 'Ö' => 'o',
            'ş' => 's', 'Ş' => 's',
            'ü' => 'u', 'Ü' => 'u',
        ];
        
        $text = str_replace(array_keys($turkish), array_values($turkish), $text);
        
        // Generic transliteration
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        return $text;
    }
    
    /**
     * Ensure directory exists, create if not
     * Simplified and more reliable version
     * @param string $path
     * @return bool
     */
    private function ensureDirectoryExists($path) {
        // Normalize path
        $path = rtrim(str_replace(['\\', '//'], ['/', '/'], $path), '/');
        
        // If directory already exists, check if it's writable
        if (is_dir($path)) {
            if (is_writable($path)) {
                return true;
            }
            // Try to fix permissions
            @chmod($path, 0775);
            if (!is_writable($path)) {
                @chmod($path, 0777);
                if (!is_writable($path)) {
                    \App\Core\Logger::error("StorageManagerService::ensureDirectoryExists - Directory exists but not writable", [
                        'path' => $path,
                        'permissions' => substr(sprintf('%o', fileperms($path)), -4)
                    ]);
                    return false;
                }
            }
            return true;
        }
        
        // Directory doesn't exist - create it recursively
        // PHP's mkdir with recursive=true creates all parent directories automatically
        try {
            // Ensure base path exists first
            $basePath = $this->basePath;
            if (!is_dir($basePath)) {
                if (!@mkdir($basePath, 0775, true) && !is_dir($basePath)) {
                    throw new \Exception("Failed to create base directory: {$basePath}");
                }
                @chmod($basePath, 0775);
            }
            
            // Ensure base path is writable
            if (!is_writable($basePath)) {
                @chmod($basePath, 0775);
                if (!is_writable($basePath)) {
                    @chmod($basePath, 0777);
                    if (!is_writable($basePath)) {
                        throw new \Exception("Base directory is not writable: {$basePath}");
                    }
                }
            }
            
            // Create the directory recursively (mkdir handles all parent directories)
            // Use umask to ensure proper permissions
            $oldUmask = umask(0002); // This allows group write
            $created = @mkdir($path, 0775, true);
            umask($oldUmask);
            
            // Check if directory was created or already exists
            if (!$created && !is_dir($path)) {
                // Try with 0777 if 0775 failed
                $oldUmask = umask(0000);
                $created = @mkdir($path, 0777, true);
                umask($oldUmask);
                
                if (!$created && !is_dir($path)) {
                    $error = error_get_last();
                    $errorMsg = $error ? $error['message'] : 'Unknown error';
                    $parentPath = dirname($path);
                    $parentExists = is_dir($parentPath);
                    $parentWritable = $parentExists ? is_writable($parentPath) : false;
                    $parentPerms = $parentExists ? substr(sprintf('%o', fileperms($parentPath)), -4) : 'N/A';
                    
                    throw new \Exception("Failed to create directory: {$path}. Error: {$errorMsg}. Parent exists: " . ($parentExists ? 'yes' : 'no') . ", Parent writable: " . ($parentWritable ? 'yes' : 'no') . ", Parent perms: {$parentPerms}");
                }
            }
            
            // Ensure directory is writable after creation
            if (is_dir($path)) {
                @chmod($path, 0775);
                if (!is_writable($path)) {
                    @chmod($path, 0777);
                    if (!is_writable($path)) {
                        throw new \Exception("Directory created but not writable: {$path}");
                    }
                }
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("StorageManagerService::ensureDirectoryExists - Failed", [
                'path' => $path,
                'error' => $e->getMessage(),
                'base_path' => $this->basePath,
                'base_path_exists' => is_dir($this->basePath),
                'base_path_writable' => is_dir($this->basePath) ? is_writable($this->basePath) : false,
                'parent_path' => dirname($path),
                'parent_exists' => is_dir(dirname($path)),
                'parent_writable' => is_dir(dirname($path)) ? is_writable(dirname($path)) : false
            ]);
            error_log("Directory creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete directory recursively
     * @param string $path
     * @return bool
     */
    private function deleteDirectory($path) {
        if (!is_dir($path)) {
            return false;
        }
        
        $files = array_diff(scandir($path), ['.', '..']);
        
        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        
        return rmdir($path);
    }
    
    /**
     * Get disk space usage
     * @param string $entityType Optional entity type filter
     * @return array
     */
    public function getDiskUsage($entityType = null) {
        $path = $this->basePath;
        
        if ($entityType && isset($this->config['entity_types'][$entityType])) {
            $path .= '/' . $this->config['entity_types'][$entityType]['path'];
        }
        
        if (!is_dir($path)) {
            return ['total_size' => 0, 'file_count' => 0];
        }
        
        $size = 0;
        $count = 0;
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $count++;
            }
        }
        
        return [
            'total_size' => $size,
            'total_size_mb' => round($size / 1024 / 1024, 2),
            'file_count' => $count,
        ];
    }
    
    /**
     * Clean up temporary files
     * @return array
     */
    public function cleanupTempFiles() {
        $tempPath = $this->config['storage']['temp_path'];
        $maxAge = $this->config['cleanup']['temp_file_max_age'];

        if (!is_dir($tempPath)) {
            return ['deleted' => 0, 'errors' => 0];
        }

        $deleted = 0;
        $errors = 0;
        $now = time();

        $files = array_diff(scandir($tempPath), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $tempPath . '/' . $file;

            if (is_file($filePath)) {
                $age = $now - filemtime($filePath);

                if ($age > $maxAge) {
                    if (unlink($filePath)) {
                        $deleted++;
                    } else {
                        $errors++;
                    }
                }
            }
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }

}

