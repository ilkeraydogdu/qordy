<?php
/**
 * Comprehensive Cache Clearing Script
 * Clears ALL cache files and directories
 */

// Bootstrap
require_once __DIR__ . '/../app/core/DependencyFactory.php';
require_once __DIR__ . '/../app/core/HelperLoader.php';

\App\Core\HelperLoader::ensureLoaded();

$deleted = 0;
$errors = 0;
$totalSize = 0;

echo "🧹 Starting comprehensive cache cleanup...\n\n";

// 1. Clear CacheService cache
try {
    $cache = \App\Core\DependencyFactory::getCacheService();
    if ($cache->clear()) {
        echo "✅ CacheService cache cleared\n";
        $deleted++;
    }
    
    // Reset stats
    if (method_exists($cache, 'resetStats')) {
        $cache->resetStats();
        echo "✅ Cache statistics reset\n";
    }
} catch (\Exception $e) {
    echo "⚠️  CacheService error: " . $e->getMessage() . "\n";
    $errors++;
}

// 2. Clear all cache files
$cacheFiles = [
    __DIR__ . '/../public/cache/cache.json',
    __DIR__ . '/../public/cache/cache.lock',
    __DIR__ . '/../public/cache/cache_stats.json',
    __DIR__ . '/../cache/cache.json',
    __DIR__ . '/../cache/cache.lock',
    __DIR__ . '/../cache/cache_stats.json',
];

foreach ($cacheFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        if (@unlink($file)) {
            echo "✅ Deleted: " . basename($file) . " (" . formatBytes($size) . ")\n";
            $deleted++;
            $totalSize += $size;
        } else {
            echo "❌ Failed: " . basename($file) . "\n";
            $errors++;
        }
    }
}

// 3. Clear storage/cache directory
$storageCacheDir = __DIR__ . '/../storage/cache';
if (is_dir($storageCacheDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageCacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $file) {
        if ($file->isFile() && ($file->getExtension() === 'cache' || $file->getExtension() === 'json')) {
            $size = $file->getSize();
            if (@unlink($file->getRealPath())) {
                echo "✅ Deleted: storage/cache/" . $file->getFilename() . " (" . formatBytes($size) . ")\n";
                $deleted++;
                $totalSize += $size;
            } else {
                $errors++;
            }
        }
    }
}

// 4. Clear public/cache directory (except .gitkeep)
$publicCacheDir = __DIR__ . '/../public/cache';
if (is_dir($publicCacheDir)) {
    $files = glob($publicCacheDir . '/*');
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== '.gitkeep') {
            $size = filesize($file);
            if (@unlink($file)) {
                echo "✅ Deleted: public/cache/" . basename($file) . " (" . formatBytes($size) . ")\n";
                $deleted++;
                $totalSize += $size;
            } else {
                $errors++;
            }
        }
    }
}

// 5. Clear cache directory in root
$rootCacheDir = __DIR__ . '/../cache';
if (is_dir($rootCacheDir)) {
    $files = glob($rootCacheDir . '/*');
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== '.gitkeep') {
            $size = filesize($file);
            if (@unlink($file)) {
                echo "✅ Deleted: cache/" . basename($file) . " (" . formatBytes($size) . ")\n";
                $deleted++;
                $totalSize += $size;
            } else {
                $errors++;
            }
        }
    }
}

// 6. Clear Redis cache if available
try {
    if (class_exists('\App\Services\RedisCache')) {
        $redisCache = new \App\Services\RedisCache([]);
        if (method_exists($redisCache, 'clear')) {
            $redisCache->clear();
            echo "✅ Redis cache cleared\n";
            $deleted++;
        }
    }
} catch (\Exception $e) {
    // Redis not available or not configured - ignore
}

echo "\n📊 Summary:\n";
echo "   ✅ Files deleted: $deleted\n";
echo "   ❌ Errors: $errors\n";
echo "   💾 Total size freed: " . formatBytes($totalSize) . "\n";
echo "\n✨ Cache cleanup completed!\n";

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
