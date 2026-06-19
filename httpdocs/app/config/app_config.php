<?php
/**
 * Application Configuration
 * Centralized configuration for hardcoded values
 */

return [
    // CDN URLs
    'cdn' => [
        'tailwindcss' => 'https://cdn.tailwindcss.com',
        'google_fonts' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap',
    ],
    
    // Placeholder Images
    'placeholders' => [
        'menu_item' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80',
        'default' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80',
    ],
    
    // Default Values (fallbacks)
    'defaults' => [
        'currency' => 'TRY',
        'timezone' => 'Europe/Istanbul',
        'language' => 'tr',
        'supported_languages' => ['tr', 'en'],
        'app_name' => 'Qordy', // Sistem ayarlarından yapılandırılabilir
    ],
    
    // Magic Numbers (timeouts, limits)
    'limits' => [
        'session_timeout' => 86400, // 24 hours in seconds
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes in seconds
        'smtp_port' => 587,
        'low_stock_threshold' => 5,
    ],
    
    // Asset Versioning
    'assets' => [
        'version' => '1.0.0',
        'enable_versioning' => false, // Set to true in production
    ],
];

