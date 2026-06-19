<?php
namespace App\Services;

use App\Core\BaseService;

/**
 * Image Validator Service
 * 
 * Provides comprehensive security validation for uploaded images
 * including file type checking, MIME verification, magic bytes validation,
 * and malicious content detection
 */
class ImageValidatorService extends BaseService {
    
    private $config;
    private $errors = [];
    
    /**
     * Constructor
     */
    public function __construct($repository = null) {
        // This service doesn't need a repository
        $this->config = include __DIR__ . '/../config/image.php';
    }
    
    /**
     * Validate uploaded file
     * @param array $file $_FILES array element
     * @param string $entityType Entity type for specific rules
     * @return array ['valid' => bool, 'errors' => array, 'info' => array]
     */
    public function validateImage($file, $entityType = 'other') {
        $this->errors = [];
        
        // Check if file was uploaded
        if (!$this->checkFileUploaded($file)) {
            return $this->getResult(false);
        }
        
        // Check for upload errors
        if (!$this->checkUploadError($file)) {
            return $this->getResult(false);
        }
        
        // Check file size
        if (!$this->checkFileSize($file, $entityType)) {
            return $this->getResult(false);
        }
        
        // Check file extension
        if (!$this->checkExtension($file)) {
            return $this->getResult(false);
        }
        
        // Check MIME type
        if (!$this->checkMimeType($file)) {
            return $this->getResult(false);
        }
        
        // Check magic bytes (file signature)
        if ($this->config['security']['check_magic_bytes']) {
            if (!$this->checkMagicBytes($file)) {
                return $this->getResult(false);
            }
        }
        
        // Verify actual image content
        if ($this->config['security']['check_image_content']) {
            if (!$this->checkImageContent($file)) {
                return $this->getResult(false);
            }
        }
        
        // Check for double extensions
        if ($this->config['security']['prevent_double_extension']) {
            if (!$this->checkDoubleExtension($file)) {
                return $this->getResult(false);
            }
        }
        
        // Check filename security
        if (!$this->checkFilename($file)) {
            return $this->getResult(false);
        }
        
        // Check image dimensions if required
        if (!$this->checkDimensions($file, $entityType)) {
            return $this->getResult(false);
        }
        
        // Get image info
        $imageInfo = $this->getImageInfo($file);
        
        return $this->getResult(true, $imageInfo);
    }
    
    /**
     * Check if file was uploaded via HTTP POST
     * @param array $file
     * @return bool
     */
    private function checkFileUploaded($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->errors[] = 'No file was uploaded';
            return false;
        }
        
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }
        
        return true;
    }
    
    /**
     * Check for PHP upload errors
     * @param array $file
     * @return bool
     */
    private function checkUploadError($file) {
        if (!isset($file['error'])) {
            $this->errors[] = 'Upload error information missing';
            return false;
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                return true;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $this->errors[] = 'File size exceeds maximum allowed size';
                return false;
            case UPLOAD_ERR_PARTIAL:
                $this->errors[] = 'File was only partially uploaded';
                return false;
            case UPLOAD_ERR_NO_FILE:
                $this->errors[] = 'No file was uploaded';
                return false;
            case UPLOAD_ERR_NO_TMP_DIR:
                $this->errors[] = 'Missing temporary folder';
                return false;
            case UPLOAD_ERR_CANT_WRITE:
                $this->errors[] = 'Failed to write file to disk';
                return false;
            case UPLOAD_ERR_EXTENSION:
                $this->errors[] = 'File upload stopped by extension';
                return false;
            default:
                $this->errors[] = 'Unknown upload error';
                return false;
        }
    }
    
    /**
     * Check file size against limits
     * @param array $file
     * @param string $entityType
     * @return bool
     */
    private function checkFileSize($file, $entityType) {
        if (!isset($file['size']) || $file['size'] <= 0) {
            $this->errors[] = 'File is empty';
            return false;
        }
        
        $maxSize = $this->config['entity_types'][$entityType]['max_size'] ?? 5 * 1024 * 1024;
        
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 2);
            $this->errors[] = "File size exceeds maximum allowed ({$maxSizeMB}MB)";
            return false;
        }
        
        return true;
    }
    
    /**
     * Check file extension
     * @param array $file
     * @return bool
     */
    private function checkExtension($file) {
        $filename = $file['name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (empty($extension)) {
            $this->errors[] = 'File has no extension';
            return false;
        }
        
        if (!in_array($extension, $this->config['allowed_extensions'])) {
            $allowed = implode(', ', $this->config['allowed_extensions']);
            $this->errors[] = "File type not allowed. Allowed types: {$allowed}";
            return false;
        }
        
        return true;
    }
    
    /**
     * Check MIME type
     * @param array $file
     * @return bool
     */
    private function checkMimeType($file) {
        // Check reported MIME type
        if (isset($file['type'])) {
            $reportedMime = strtolower($file['type']);
            if (!in_array($reportedMime, $this->config['allowed_mime_types'])) {
                $this->errors[] = 'Invalid MIME type (reported)';
                return false;
            }
        }
        
        // Check actual MIME type using finfo
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actualMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($actualMime, $this->config['allowed_mime_types'])) {
                $this->errors[] = 'Invalid MIME type (actual)';
                return false;
            }
            
            // Verify extension matches MIME type
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!$this->verifyMimeExtensionMatch($actualMime, $extension)) {
                $this->errors[] = 'File extension does not match content type';
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verify MIME type matches extension
     * @param string $mime
     * @param string $extension
     * @return bool
     */
    private function verifyMimeExtensionMatch($mime, $extension) {
        $validMatches = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
        ];
        
        if (isset($validMatches[$mime])) {
            return in_array($extension, $validMatches[$mime]);
        }
        
        return false;
    }
    
    /**
     * Check file magic bytes (file signature)
     * @param array $file
     * @return bool
     */
    private function checkMagicBytes($file) {
        $handle = fopen($file['tmp_name'], 'rb');
        if (!$handle) {
            $this->errors[] = 'Unable to read file';
            return false;
        }
        
        $bytes = fread($handle, 16); // Read first 16 bytes
        fclose($handle);
        
        $valid = false;
        foreach ($this->config['magic_bytes'] as $mime => $signatures) {
            foreach ($signatures as $signature) {
                if (strncmp($bytes, $signature, strlen($signature)) === 0) {
                    $valid = true;
                    break 2;
                }
            }
        }
        
        // Special check for WebP (RIFF....WEBP format)
        if (!$valid && strlen($bytes) >= 12) {
            if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
                $valid = true;
            }
        }
        
        if (!$valid) {
            $this->errors[] = 'Invalid file signature (magic bytes)';
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify file is actually a valid image
     * @param array $file
     * @return bool
     */
    private function checkImageContent($file) {
        $imageInfo = @getimagesize($file['tmp_name']);
        
        if ($imageInfo === false) {
            $this->errors[] = 'File is not a valid image';
            return false;
        }
        
        // Verify it's a supported image type
        $supportedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($imageInfo[2], $supportedTypes)) {
            $this->errors[] = 'Unsupported image type';
            return false;
        }
        
        return true;
    }
    
    /**
     * Check for double extensions (security risk)
     * @param array $file
     * @return bool
     */
    private function checkDoubleExtension($file) {
        $filename = $file['name'];
        
        // Count dots in filename (excluding the last extension)
        $parts = explode('.', $filename);
        
        // If there are multiple dots and any look suspicious
        if (count($parts) > 2) {
            $suspiciousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'asp', 'aspx', 'jsp'];
            
            // Check all parts except the last one
            for ($i = 0; $i < count($parts) - 1; $i++) {
                if (in_array(strtolower($parts[$i]), $suspiciousExtensions)) {
                    $this->errors[] = 'Suspicious filename detected';
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Check filename for security issues
     * @param array $file
     * @return bool
     */
    private function checkFilename($file) {
        $filename = basename($file['name']);
        
        // Check length
        if (strlen($filename) > $this->config['security']['max_filename_length']) {
            $this->errors[] = 'Filename too long';
            return false;
        }
        
        // Check for blocked filenames
        $filenameLower = strtolower($filename);
        foreach ($this->config['security']['blocked_filenames'] as $blocked) {
            if ($filenameLower === strtolower($blocked)) {
                $this->errors[] = 'Filename not allowed';
                return false;
            }
        }
        
        // Check for directory traversal attempts
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            $this->errors[] = 'Invalid filename';
            return false;
        }
        
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            $this->errors[] = 'Invalid filename (null byte)';
            return false;
        }
        
        return true;
    }
    
    /**
     * Check image dimensions
     * @param array $file
     * @param string $entityType
     * @return bool
     */
    private function checkDimensions($file, $entityType) {
        $requiredDims = $this->config['entity_types'][$entityType]['required_dimensions'] ?? null;
        
        if (!$requiredDims) {
            return true; // No requirements
        }
        
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            return true; // Already checked in checkImageContent
        }
        
        list($width, $height) = $imageInfo;
        
        if (isset($requiredDims['min_width']) && $width < $requiredDims['min_width']) {
            $this->errors[] = "Image width must be at least {$requiredDims['min_width']}px";
            return false;
        }
        
        if (isset($requiredDims['min_height']) && $height < $requiredDims['min_height']) {
            $this->errors[] = "Image height must be at least {$requiredDims['min_height']}px";
            return false;
        }
        
        if (isset($requiredDims['max_width']) && $width > $requiredDims['max_width']) {
            $this->errors[] = "Image width must not exceed {$requiredDims['max_width']}px";
            return false;
        }
        
        if (isset($requiredDims['max_height']) && $height > $requiredDims['max_height']) {
            $this->errors[] = "Image height must not exceed {$requiredDims['max_height']}px";
            return false;
        }
        
        return true;
    }
    
    /**
     * Get image information
     * @param array $file
     * @return array
     */
    private function getImageInfo($file) {
        $imageInfo = @getimagesize($file['tmp_name']);
        
        if (!$imageInfo) {
            return [];
        }
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime'],
            'dimensions' => $imageInfo[0] . 'x' . $imageInfo[1],
        ];
    }
    
    /**
     * Get validation result
     * @param bool $valid
     * @param array $info
     * @return array
     */
    private function getResult($valid, $info = []) {
        return [
            'valid' => $valid,
            'errors' => $this->errors,
            'info' => $info,
        ];
    }
    
    /**
     * Get errors
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Sanitize filename
     * @param string $filename
     * @return string
     */
    public function sanitizeFilename($filename) {
        // Get extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // Remove special characters
        $nameWithoutExt = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $nameWithoutExt);

        // Remove multiple dashes
        $nameWithoutExt = preg_replace('/-+/', '-', $nameWithoutExt);

        // Trim dashes from ends
        $nameWithoutExt = trim($nameWithoutExt, '-');

        // Lowercase if configured
        if ($this->config['seo']['lowercase']) {
            $nameWithoutExt = strtolower($nameWithoutExt);
        }

        // Limit length
        $maxLength = $this->config['seo']['max_filename_length'] - strlen($extension) - 1;
        if (strlen($nameWithoutExt) > $maxLength) {
            $nameWithoutExt = substr($nameWithoutExt, 0, $maxLength);
        }

        // Add unique identifier
        $unique = substr(md5(uniqid()), 0, 8);

        return $nameWithoutExt . '-' . $unique . '.' . $extension;
    }

    /**
     * Delete a record (override parent method to maintain compatibility)
     * @param string $id Record ID
     * @return bool Success
     */
    public function delete(string $id): bool {
        // This service doesn't actually delete records, so return false
        return false;
    }

    /**
     * Validate data (override parent method to maintain compatibility)
     * @param array $data Data to validate
     * @return bool True if valid
     */
    public function validate(array $data): bool {
        // For backward compatibility, we'll validate if the data contains a file
        if (isset($data['file'])) {
            $result = $this->validateImage($data['file'], $data['entity_type'] ?? 'other');
            return $result['valid'] ?? false;
        }
        return false;
    }
}

