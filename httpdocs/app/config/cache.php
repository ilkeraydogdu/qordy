<?php
/**
 * Cache Configuration
 * Defines cache driver and settings
 * All values must be set in .env file
 */

// Validate required cache environment variables
if (!isset($_ENV['CACHE_DRIVER']) || empty($_ENV['CACHE_DRIVER'])) {
    throw new \Exception('CACHE_DRIVER environment variable is required. Please set it in .env file.');
}
if (!isset($_ENV['CACHE_TTL']) || empty($_ENV['CACHE_TTL'])) {
    throw new \Exception('CACHE_TTL environment variable is required. Please set it in .env file.');
}
if (!isset($_ENV['CACHE_PREFIX']) || empty($_ENV['CACHE_PREFIX'])) {
    throw new \Exception('CACHE_PREFIX environment variable is required. Please set it in .env file.');
}
if (!isset($_ENV['CACHE_PATH']) || empty($_ENV['CACHE_PATH'])) {
    throw new \Exception('CACHE_PATH environment variable is required. Please set it in .env file.');
}

// Validate Redis configuration if using Redis driver
if ($_ENV['CACHE_DRIVER'] === 'redis') {
    if (!isset($_ENV['REDIS_HOST']) || empty($_ENV['REDIS_HOST'])) {
        throw new \Exception('REDIS_HOST environment variable is required when CACHE_DRIVER=redis. Please set it in .env file.');
    }
    if (!isset($_ENV['REDIS_PORT']) || empty($_ENV['REDIS_PORT'])) {
        throw new \Exception('REDIS_PORT environment variable is required when CACHE_DRIVER=redis. Please set it in .env file.');
    }
    if (!isset($_ENV['REDIS_DATABASE'])) {
        throw new \Exception('REDIS_DATABASE environment variable is required when CACHE_DRIVER=redis. Please set it in .env file.');
    }
    if (!isset($_ENV['REDIS_TIMEOUT']) || empty($_ENV['REDIS_TIMEOUT'])) {
        throw new \Exception('REDIS_TIMEOUT environment variable is required when CACHE_DRIVER=redis. Please set it in .env file.');
    }
}

return [
    // Cache driver: 'file' or 'redis'
    'driver' => $_ENV['CACHE_DRIVER'],
    
    // Default TTL in seconds
    'default_ttl' => (int)$_ENV['CACHE_TTL'],
    
    // Cache key prefix
    'prefix' => $_ENV['CACHE_PREFIX'],
    
    // File cache configuration
    'file' => [
        'path' => $_ENV['CACHE_PATH'],
        'ttl' => (int)$_ENV['CACHE_TTL'],
    ],
    
    // Redis cache configuration (only used when CACHE_DRIVER=redis)
    'redis' => [
        'host' => isset($_ENV['REDIS_HOST']) ? $_ENV['REDIS_HOST'] : '127.0.0.1',
        'port' => isset($_ENV['REDIS_PORT']) ? (int)$_ENV['REDIS_PORT'] : 6379,
        'password' => isset($_ENV['REDIS_PASSWORD']) && !empty($_ENV['REDIS_PASSWORD']) ? $_ENV['REDIS_PASSWORD'] : null,
        'database' => isset($_ENV['REDIS_DATABASE']) ? (int)$_ENV['REDIS_DATABASE'] : 0,
        'timeout' => isset($_ENV['REDIS_TIMEOUT']) ? (float)$_ENV['REDIS_TIMEOUT'] : 2.5,
        'ttl' => (int)$_ENV['CACHE_TTL'],
    ],
    
    // Redis session configuration (only used when SESSION_DRIVER=redis)
    'session' => [
        'driver' => isset($_ENV['SESSION_DRIVER']) ? $_ENV['SESSION_DRIVER'] : 'php', // 'php' or 'redis'
        'prefix' => isset($_ENV['SESSION_PREFIX']) ? $_ENV['SESSION_PREFIX'] : 'session:',
        'database' => isset($_ENV['REDIS_SESSION_DATABASE']) ? (int)$_ENV['REDIS_SESSION_DATABASE'] : 1, // Use DB 1 for sessions (separate from cache)
        'ttl' => isset($_ENV['SESSION_LIFETIME']) ? (int)$_ENV['SESSION_LIFETIME'] : 28800, // Default: 8 hours
    ],
];

