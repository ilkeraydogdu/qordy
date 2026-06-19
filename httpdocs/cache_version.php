<?php
/**
 * Cache Version Manager
 * This file is automatically updated when caches are cleared
 * Used to invalidate browser caches by appending version to static assets
 */

// Auto-increment version when this file is included
$cacheVersion = time(); // Use timestamp for unique version
define('CACHE_VERSION', $cacheVersion);

/**
 * Get versioned URL for static assets
 * @param string $url Asset URL
 * @return string Versioned URL
 */
function getVersionedUrl($url) {
    $separator = strpos($url, '?') === false ? '?' : '&';
    return $url . $separator . 'v=' . CACHE_VERSION;
}
