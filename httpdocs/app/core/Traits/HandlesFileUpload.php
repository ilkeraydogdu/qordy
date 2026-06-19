<?php
namespace App\Core\Traits;

/**
 * HandlesFileUpload Trait
 * Provides file upload methods for controllers
 */
trait HandlesFileUpload {
    /**
     * MIME -> güvenli uzantı eşlemesi. Gelen $_FILES['type'] ve
     * kullanıcı dosya adındaki uzantıya GÜVENMEYİZ; gerçek dosya
     * içeriği finfo ile tespit edilir ve bu eşlemeden uzantı
     * atanır. Bu sayede `logo.php` gibi bir dosya image/png MIME
     * ile üst üste bindirilmeye çalışılsa bile `.png` olarak yazılır
     * ve sunucuda PHP olarak çalıştırılamaz.
     */
    private static array $mimeExtensionAllowlist = [
        'image/png'                     => 'png',
        'image/jpeg'                    => 'jpg',
        'image/jpg'                     => 'jpg',
        'image/gif'                     => 'gif',
        'image/webp'                    => 'webp',
        'image/svg+xml'                 => 'svg',
        'image/x-icon'                  => 'ico',
        'image/vnd.microsoft.icon'      => 'ico',
        'application/pdf'               => 'pdf',
        'application/msword'            => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain'                    => 'txt',
    ];

    /**
     * Her koşulda tehlikeli olan uzantılar (sunucuda çalıştırılabilir).
     * Kullanıcı adından gelen uzantı ne olursa olsun reddedilir.
     */
    private static array $forbiddenExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht',
        'phps', 'inc', 'ini', 'htaccess', 'htpasswd', 'user.ini',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'dll', 'so',
        'asp', 'aspx', 'jsp', 'jspx',
    ];

    /**
     * Dosyanın gerçek MIME tipini finfo ile tespit eder.
     * Başarısız olursa null döner.
     */
    private function detectRealMime(string $tmpPath): ?string {
        if (!is_readable($tmpPath)) {
            return null;
        }
        if (!function_exists('finfo_open')) {
            return null;
        }
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = @finfo_file($finfo, $tmpPath);
        @finfo_close($finfo);
        return is_string($mime) && $mime !== '' ? strtolower($mime) : null;
    }

    /**
     * Upload a file with validation
     * @param string $fieldName Form field name
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @param string $uploadDir Upload directory path
     * @param string|null $filename Custom filename (without extension)
     * @return array ['success' => bool, 'filepath' => string|null, 'url' => string|null, 'error' => string|null]
     */
    protected function uploadFile(
        string $fieldName,
        array $allowedTypes,
        int $maxSize,
        string $uploadDir,
        ?string $filename = null
    ): array {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES[$fieldName])) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Invalid request or file not provided'
            ];
        }
        
        $file = $_FILES[$fieldName];

        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Upload failed (code ' . ($file['error'] ?? 'unknown') . ')'
            ];
        }

        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Invalid upload source'
            ];
        }

        if ($file['size'] > $maxSize) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'File too large'
            ];
        }

        // Kullanıcı dosya adındaki uzantı sunucuda çalıştırılabilir mi?
        $userExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($userExt !== '' && in_array($userExt, self::$forbiddenExtensions, true)) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Forbidden file extension'
            ];
        }

        // Gerçek MIME'i finfo ile tespit et, browser'dan gelen $_FILES['type']'a GÜVENME.
        $realMime = $this->detectRealMime($file['tmp_name']);
        if ($realMime === null) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Unable to detect file type'
            ];
        }

        $normalizedAllowed = array_map('strtolower', $allowedTypes);
        if (!in_array($realMime, $normalizedAllowed, true)) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Invalid file type (detected: ' . $realMime . ')'
            ];
        }

        // SVG özel durumu: XSS / script içerebilir. Script etiketi varsa reddet.
        if ($realMime === 'image/svg+xml') {
            $svg = @file_get_contents($file['tmp_name'], false, null, 0, 65536);
            if ($svg !== false && preg_match('/<\s*script/i', $svg)) {
                return [
                    'success' => false,
                    'filepath' => null,
                    'url' => null,
                    'error' => 'SVG with embedded script rejected'
                ];
            }
        }

        // Uzantıyı sadece MIME'den türet — kullanıcı adından DEĞİL.
        $safeExt = self::$mimeExtensionAllowlist[$realMime] ?? null;
        if ($safeExt === null) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Unsupported MIME mapping'
            ];
        }

        // Hedef dizini normalize et ve üst dizine çıkma denemelerini engelle.
        $baseDir = realpath($uploadDir);
        if ($baseDir === false) {
            if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return [
                    'success' => false,
                    'filepath' => null,
                    'url' => null,
                    'error' => 'Failed to create upload directory'
                ];
            }
            $baseDir = realpath($uploadDir);
        }
        if ($baseDir === false) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Invalid upload directory'
            ];
        }

        if ($filename === null) {
            // Tamamen rastgele isim — kullanıcı girdisine yer verme.
            $filename = bin2hex(random_bytes(8)) . '_' . time();
        } else {
            // Verilen isim de güvensizdir; basename + karakter süzgeci uygula.
            $filename = basename($filename);
            $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?? 'upload';
            $filename = ltrim($filename, '.');
            if ($filename === '') {
                $filename = bin2hex(random_bytes(8));
            }
        }

        $finalFilename = $filename . '.' . $safeExt;
        $filepath = rtrim($baseDir, '/') . '/' . $finalFilename;

        // Containment: yazılacak nihai yol, izin verilen kök klasörün
        // altında olmalı (symlink / .. hilelerine karşı).
        $realParent = realpath(dirname($filepath));
        if ($realParent === false || strpos($realParent, $baseDir) !== 0) {
            return [
                'success' => false,
                'filepath' => null,
                'url' => null,
                'error' => 'Path traversal blocked'
            ];
        }
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            @chmod($filepath, 0644);
            $url = BASE_URL . '/' . str_replace(__DIR__ . '/../../public/', 'public/', $filepath);
            return [
                'success' => true,
                'filepath' => $filepath,
                'url' => $url,
                'filename' => $finalFilename,
                'error' => null
            ];
        }
        
        return [
            'success' => false,
            'filepath' => null,
            'url' => null,
            'error' => 'Failed to move uploaded file'
        ];
    }
    
    /**
     * Upload image file (common use case)
     * @param string $fieldName Form field name
     * @param string $uploadDir Upload directory path
     * @param string|null $filename Custom filename (without extension)
     * @param int $maxSize Maximum file size in bytes (default: 5MB)
     * @return array ['success' => bool, 'filepath' => string|null, 'url' => string|null, 'error' => string|null]
     */
    protected function uploadImage(
        string $fieldName,
        string $uploadDir,
        ?string $filename = null,
        int $maxSize = 5242880 // 5MB
    ): array {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/webp'];
        return $this->uploadFile($fieldName, $allowedTypes, $maxSize, $uploadDir, $filename);
    }
    
    /**
     * Upload logo file
     * @param string $fieldName Form field name (default: 'logo')
     * @return array ['success' => bool, 'filepath' => string|null, 'url' => string|null, 'error' => string|null]
     */
    protected function uploadLogo(string $fieldName = 'logo'): array {
        $uploadDir = __DIR__ . '/../../public/assets/images/';
        return $this->uploadImage($fieldName, $uploadDir, 'logo', 5242880); // 5MB
    }
    
    /**
     * Upload favicon file
     * @param string $fieldName Form field name (default: 'favicon')
     * @return array ['success' => bool, 'filepath' => string|null, 'url' => string|null, 'error' => string|null]
     */
    protected function uploadFavicon(string $fieldName = 'favicon'): array {
        $uploadDir = __DIR__ . '/../../public/assets/images/';
        $allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];
        return $this->uploadFile($fieldName, $allowedTypes, 1048576, $uploadDir, 'favicon'); // 1MB
    }
    
    /**
     * Upload document file (PDF, DOC, etc.)
     * @param string $fieldName Form field name
     * @param string $uploadDir Upload directory path
     * @param string|null $filename Custom filename (without extension)
     * @param int $maxSize Maximum file size in bytes (default: 10MB)
     * @return array ['success' => bool, 'filepath' => string|null, 'url' => string|null, 'error' => string|null]
     */
    protected function uploadDocument(
        string $fieldName,
        string $uploadDir,
        ?string $filename = null,
        int $maxSize = 10485760 // 10MB
    ): array {
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        return $this->uploadFile($fieldName, $allowedTypes, $maxSize, $uploadDir, $filename);
    }
    
    /**
     * Validate uploaded file
     * @param string $fieldName Form field name
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array ['valid' => bool, 'error' => string|null]
     */
    protected function validateUploadedFile(
        string $fieldName,
        array $allowedTypes,
        int $maxSize
    ): array {
        if (!isset($_FILES[$fieldName])) {
            return ['valid' => false, 'error' => 'File not provided'];
        }
        
        $file = $_FILES[$fieldName];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            return [
                'valid' => false,
                'error' => $errorMessages[$file['error']] ?? 'Unknown upload error'
            ];
        }
        
        // Check file type
        if (!in_array($file['type'], $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        return ['valid' => true, 'error' => null];
    }
}

