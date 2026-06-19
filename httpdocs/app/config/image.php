<?php
namespace App\Config;

/**
 * Image Management Configuration
 * 
 * Centralized configuration for the image management system
 * including upload limits, processing settings, and security rules
 */
return [
    
    /**
     * Storage Configuration
     */
    'storage' => [
        'base_path' => __DIR__ . '/../../public/assets/images',
        'base_url' => '/assets/images',
        'temp_path' => __DIR__ . '/../../storage/temp',
    ],
    
    /**
     * Entity Type Configuration
     * Define upload limits and settings per entity type
     */
    'entity_types' => [
        'product' => [
            'max_size' => 5 * 1024 * 1024, // 5MB
            'max_files' => 10,
            'path' => 'products',
            'watermark' => false,
            'required_dimensions' => null, // No specific requirement
        ],
        'logo' => [
            'max_size' => 2 * 1024 * 1024, // 2MB
            'max_files' => 1,
            'path' => 'logos',
            'watermark' => false,
            'required_dimensions' => null,
        ],
        'category' => [
            'max_size' => 3 * 1024 * 1024, // 3MB
            'max_files' => 1,
            'path' => 'categories',
            'watermark' => false,
            'required_dimensions' => null,
        ],
        'avatar' => [
            'max_size' => 2 * 1024 * 1024, // 2MB
            'max_files' => 1,
            'path' => 'avatars',
            'watermark' => false,
            'required_dimensions' => ['min_width' => 100, 'min_height' => 100],
        ],
        'banner' => [
            'max_size' => 5 * 1024 * 1024, // 5MB
            'max_files' => 5,
            'path' => 'banners',
            'watermark' => false,
            'required_dimensions' => ['min_width' => 800, 'min_height' => 300],
        ],
        'other' => [
            'max_size' => 3 * 1024 * 1024, // 3MB
            'max_files' => 5,
            'path' => 'other',
            'watermark' => false,
            'required_dimensions' => null,
        ],
        'waste' => [
            'max_size' => 5 * 1024 * 1024, // 5MB — kitchens upload phone photos.
            'max_files' => 6,
            'path' => 'waste',
            'watermark' => false,
            'required_dimensions' => null,
        ],
    ],
    
    /**
     * Allowed File Types
     */
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'allowed_mime_types' => [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ],
    
    /**
     * Magic Bytes for File Validation
     * First few bytes that identify file types
     */
    'magic_bytes' => [
        'image/jpeg' => [
            "\xFF\xD8\xFF\xE0", // JPEG JFIF
            "\xFF\xD8\xFF\xE1", // JPEG Exif
            "\xFF\xD8\xFF\xDB", // JPEG
        ],
        'image/png' => [
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", // PNG
        ],
        'image/gif' => [
            "GIF87a", // GIF87a
            "GIF89a", // GIF89a
        ],
        'image/webp' => [
            "RIFF", // WebP (first 4 bytes, followed by file size, then "WEBP")
        ],
    ],
    
    /**
     * Image Processing Configuration
     */
    'processing' => [
        // Image sizes to generate
        'sizes' => [
            'thumbnail' => [
                'width' => 150,
                'height' => 150,
                'crop' => true,
            ],
            'medium' => [
                'width' => 400,
                'height' => 400,
                'crop' => false,
            ],
            'large' => [
                'width' => 1200,
                'height' => 1200,
                'crop' => false,
            ],
            'original' => [
                'width' => null, // Keep original size
                'height' => null,
                'crop' => false,
            ],
        ],
        
        // Quality settings
        'quality' => [
            'jpeg' => 85,
            'png' => 9, // PNG compression level (0-9)
            'webp' => 90,
        ],
        
        // WebP conversion
        'convert_to_webp' => true,
        
        // Maximum dimensions for original images
        'max_dimensions' => [
            'width' => 2400,
            'height' => 2400,
        ],
    ],
    
    /**
     * Watermark Configuration
     */
    'watermark' => [
        'enabled' => false, // Global enable/disable
        'image_path' => __DIR__ . '/../../public/assets/images/watermark.png',
        'position' => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, center
        'opacity' => 50, // 0-100
        'margin' => 10, // Margin from edges in pixels
        'min_image_size' => [
            'width' => 400,
            'height' => 400,
        ], // Only apply watermark to images larger than this
    ],
    
    /**
     * SEO Configuration
     */
    'seo' => [
        'filename_separator' => '-',
        'max_filename_length' => 100,
        'transliterate' => true, // Convert non-ASCII characters
        'lowercase' => true,
        'remove_stop_words' => false,
        'default_alt_format' => '{entity_name} - {entity_type}',
        'default_title_format' => '{entity_name}',
    ],
    
    /**
     * Security Configuration
     */
    'security' => [
        'check_mime_type' => true,
        'check_magic_bytes' => true,
        'check_image_content' => true, // Verify it's actually a valid image
        'prevent_double_extension' => true,
        'sanitize_filename' => true,
        'max_filename_length' => 255,
        'blocked_filenames' => [
            '.htaccess',
            'index.php',
            'web.config',
            '.env',
            'config.php',
        ],
    ],
    
    /**
     * Performance Configuration
     */
    'performance' => [
        'enable_lazy_loading' => true,
        'memory_limit' => '256M', // Memory limit for image processing
        'max_execution_time' => 60, // Seconds
    ],
    
    /**
     * CDN Configuration (optional)
     */
    'cdn' => [
        'enabled' => false,
        'url' => '', // CDN URL - Sistem ayarlarından yapılandırılmalı
        'regions' => [],
    ],
    
    /**
     * Cleanup Configuration
     */
    'cleanup' => [
        'delete_temp_files' => true,
        'temp_file_max_age' => 3600, // 1 hour in seconds
        'orphaned_files_check' => true,
    ],
    
    /**
     * Logging Configuration
     */
    'logging' => [
        'enabled' => true,
        'log_uploads' => true,
        'log_deletions' => true,
        'log_errors' => true,
    ],
];

