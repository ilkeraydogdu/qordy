<?php
/**
 * Update Domain URLs in Database
 * 
 * This script updates all URLs in the database that contain old domains
 * to use the current domain detected automatically.
 * 
 * Usage:
 * php scripts/update_domain_urls.php
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/DependencyFactory.php';

use App\Services\BaseUrlService;
use App\Services\TableService;
use App\Core\DependencyFactory;

echo "[" . date('Y-m-d H:i:s') . "] Starting domain URL update...\n";

try {
    // Old domains to replace
    $oldDomains = ['caddecafe.pfdk.me', 'pfdk.me'];
    
    // New domain (can be passed as argument or detected from workspace path)
    $newDomain = $argv[1] ?? 'caddecafe.qordy.com';
    $newProtocol = 'https'; // Default to https
    
    // Try to detect from workspace path if not provided
    if (empty($argv[1])) {
        $workspacePath = __DIR__ . '/..';
        if (strpos($workspacePath, 'caddecafe.qordy.com') !== false) {
            $newDomain = 'caddecafe.qordy.com';
        }
    }
    
    $newBaseUrl = $newProtocol . '://' . $newDomain;
    
    echo "[" . date('Y-m-d H:i:s') . "] Old domains to replace: " . implode(', ', $oldDomains) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] New domain: {$newDomain}\n";
    echo "[" . date('Y-m-d H:i:s') . "] New base URL: {$newBaseUrl}\n";
    
    // Initialize database connection
    $pdo = DependencyFactory::getDatabase();
    
    // Get TableService for URL regeneration
    $tableService = DependencyFactory::getTableService();
    
    // Find all tables with old domain in URL or QR code URL
    $tablesUpdated = 0;
    $qrCodesUpdated = 0;
    
    // Get all tables
    $stmt = $pdo->query("SELECT table_id, url, qr_code_url FROM tables");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "[" . date('Y-m-d H:i:s') . "] Found " . count($tables) . " tables to check\n";
    
    foreach ($tables as $table) {
        $tableId = $table['table_id'];
        $oldUrl = $table['url'] ?? '';
        $oldQrUrl = $table['qr_code_url'] ?? '';
        $needsUpdate = false;
        $updateData = [];
        
        // Check URL for old domain
        if (!empty($oldUrl)) {
            foreach ($oldDomains as $oldDomain) {
                if (strpos($oldUrl, $oldDomain) !== false) {
                    $needsUpdate = true;
                    // Replace old domain with new domain in URL
                    $newUrl = str_replace($oldDomain, $newDomain, $oldUrl);
                    // Also replace http with https if needed
                    $newUrl = str_replace('http://', 'https://', $newUrl);
                    $updateData['url'] = $newUrl;
                    echo "[" . date('Y-m-d H:i:s') . "] Table {$tableId}: Replacing domain in URL: {$oldUrl} -> {$newUrl}\n";
                    break;
                }
            }
        }
        
        // Check QR code URL for old domain
        if (!empty($oldQrUrl)) {
            foreach ($oldDomains as $oldDomain) {
                if (strpos($oldQrUrl, $oldDomain) !== false) {
                    $needsUpdate = true;
                    // Regenerate QR code URL with new domain
                    $tableUrl = $updateData['url'] ?? $oldUrl;
                    if (empty($tableUrl)) {
                        // If URL is empty, replace domain in old URL
                        $tableUrl = str_replace($oldDomain, $newDomain, $oldUrl);
                        $tableUrl = str_replace('http://', 'https://', $tableUrl);
                        $updateData['url'] = $tableUrl;
                    }
                    
                    // Generate new QR code URL with updated table URL
                    $newQrUrl = rtrim(BASE_URL, '/') . '/qr?size=500&data=' . urlencode($tableUrl);
                    $updateData['qr_code_url'] = $newQrUrl;
                    echo "[" . date('Y-m-d H:i:s') . "] Table {$tableId}: Updating QR code URL from {$oldQrUrl} to {$newQrUrl}\n";
                    break;
                }
            }
        }
        
        // Update table if needed
        if ($needsUpdate && !empty($updateData)) {
            try {
                $result = $tableService->updateTable($tableId, $updateData);
                if ($result) {
                    $tablesUpdated++;
                    if (isset($updateData['url'])) {
                        echo "[" . date('Y-m-d H:i:s') . "] ✓ Table {$tableId}: URL updated successfully\n";
                    }
                    if (isset($updateData['qr_code_url'])) {
                        $qrCodesUpdated++;
                        echo "[" . date('Y-m-d H:i:s') . "] ✓ Table {$tableId}: QR code URL updated successfully\n";
                    }
                } else {
                    echo "[" . date('Y-m-d H:i:s') . "] ✗ Table {$tableId}: Failed to update\n";
                }
            } catch (\Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] ✗ Table {$tableId}: Error updating: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Summary
    echo "\n[" . date('Y-m-d H:i:s') . "] ========================================\n";
    echo "[" . date('Y-m-d H:i:s') . "] Update Summary:\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - Tables checked: " . count($tables) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - Tables updated: {$tablesUpdated}\n";
    echo "[" . date('Y-m-d H:i:s') . "]   - QR codes updated: {$qrCodesUpdated}\n";
    echo "[" . date('Y-m-d H:i:s') . "] ========================================\n";
    
    if ($tablesUpdated > 0 || $qrCodesUpdated > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ✓ Successfully updated domain URLs\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ✓ No URLs needed updating\n";
    }
    
    exit(0);
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
