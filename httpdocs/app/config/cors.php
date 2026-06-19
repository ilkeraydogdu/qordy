<?php
/**
 * CORS Configuration
 * Cross-Origin Resource Sharing settings
 */

return [
    'enabled' => true,
    'allowed_origins' => [
        // Add your frontend domains here
        // 'http://localhost:3000',
        // 'https://yourdomain.com',
    ],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-Token',
        'Accept',
        'Origin'
    ],
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset'
    ],
    'max_age' => 86400, // 24 hours
    'allow_credentials' => true,
    'allow_all_origins' => false // Set to true only in development
];

