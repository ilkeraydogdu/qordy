<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class SystemController extends Controller {
    
    public function syncDynamicPermissions() {
        $this->requirePermission('permissions.manage');
        
        try {
            $dynamicPermissionService = \App\Core\DependencyFactory::getDynamicPermissionService();
            $results = $dynamicPermissionService->discoverAllDynamicPermissions();
            
            $response = [
                'success' => true,
                'results' => $results
            ];
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.permissions_synced', $response, 200);
        } catch (\Exception $e) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.permission_sync_failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync system_permissions from navigation_items.
     * Ensures every active nav item has a system_permission entry and
     * removes orphaned permissions that are no longer backed by a nav item.
     * Accessible via: POST /api/qodmin/permissions/sync-nav
     */
    public function syncNavigationPermissions() {
        $this->requireSuperAdmin();

        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            require_once __DIR__ . '/../../services/NavigationPermissionSync.php';
            $syncService = new \App\Services\NavigationPermissionSync($db);
            $stats = $syncService->syncAll();

            $this->json([
                'success' => true,
                'message' => 'İzinler senkronize edildi.',
                'stats'   => $stats,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Run seed_navigation.php (which also auto-syncs permissions).
     * Accessible via: POST /api/qodmin/navigation/seed
     */
    public function seedNavigation() {
        $this->requireSuperAdmin();

        try {
            $scriptPath = __DIR__ . '/../../scripts/seed_navigation.php';
            if (!file_exists($scriptPath)) {
                $this->json(['success' => false, 'error' => 'Seed script not found.'], 404);
                return;
            }

            ob_start();
            // Bootstrap vars the script expects
            include $scriptPath;
            $output = ob_get_clean();

            $this->json([
                'success' => true,
                'message' => 'Navigasyon ve izinler yeniden oluşturuldu.',
                'output'  => $output,
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Run seed_role_permissions.php — seeds BUSINESS_MANAGER + TRIAL role permissions.
     * Accessible via: POST /api/qodmin/navigation/seed-roles
     */
    public function seedRolePermissions() {
        $this->requireSuperAdmin();

        try {
            $scriptPath = __DIR__ . '/../../scripts/seed_role_permissions.php';
            if (!file_exists($scriptPath)) {
                $this->json(['success' => false, 'error' => 'Role permissions seed script not found.'], 404);
                return;
            }

            ob_start();
            include $scriptPath;
            $output = ob_get_clean();

            $this->json([
                'success' => true,
                'message' => 'Rol izinleri güncellendi.',
                'output'  => $output,
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createShiftTables() {
        $this->requirePermission('settings.view');
        
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $sql1 = "CREATE TABLE IF NOT EXISTS `staff_schedules` (
                `schedule_id` VARCHAR(50) PRIMARY KEY,
                `staff_id` VARCHAR(50) NOT NULL,
                `day_of_week` TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `is_working` TINYINT(1) DEFAULT 1 COMMENT '0=Off day, 1=Working day',
                `break_start` TIME NULL COMMENT 'Break start time',
                `break_end` TIME NULL COMMENT 'Break end time',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_staff_id` (`staff_id`),
                INDEX `idx_day_of_week` (`day_of_week`),
                UNIQUE KEY `unique_staff_day` (`staff_id`, `day_of_week`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Personel haftalık çalışma programı'";
            
            $db->exec($sql1);
            
            $sql2 = "CREATE TABLE IF NOT EXISTS `shift_schedules` (
                `schedule_id` VARCHAR(50) PRIMARY KEY,
                `staff_id` VARCHAR(50) NOT NULL,
                `staff_type` VARCHAR(20) DEFAULT 'USER' COMMENT 'USER or GUEST_STAFF',
                `guest_staff_id` VARCHAR(50) NULL COMMENT 'Reference to guest_staff table',
                `staff_name` VARCHAR(200) NULL COMMENT 'Staff name for guest staff',
                `staff_phone` VARCHAR(20) NULL COMMENT 'Staff phone for guest staff',
                `shift_date` DATE NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `shift_type` VARCHAR(20) DEFAULT 'REGULAR' COMMENT 'REGULAR, OVERTIME, HOLIDAY',
                `status` VARCHAR(20) DEFAULT 'PLANNED' COMMENT 'PLANNED, CONFIRMED, CANCELLED',
                `notes` TEXT NULL,
                `created_by` VARCHAR(50) NULL COMMENT 'User who created this schedule',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_staff_id` (`staff_id`),
                INDEX `idx_shift_date` (`shift_date`),
                INDEX `idx_status` (`status`),
                INDEX `idx_staff_date` (`staff_id`, `shift_date`),
                INDEX `idx_guest_staff_id` (`guest_staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planlanmış vardiyalar'";
            
            $db->exec($sql2);
            
            $sql3 = "CREATE TABLE IF NOT EXISTS `guest_staff` (
                `guest_staff_id` VARCHAR(50) PRIMARY KEY,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NOT NULL,
                `phone` VARCHAR(20) NOT NULL,
                `email` VARCHAR(255) NULL,
                `notes` TEXT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_phone` (`phone`),
                INDEX `idx_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Misafir/Geçici çalışanlar'";
            
            $db->exec($sql3);
            
            try {
                $stmt = $db->prepare("INSERT IGNORE INTO migrations (migration, batch) VALUES (?, ?)");
                $batch = 1;
                $stmt->execute(['016_create_staff_schedules_table', $batch]);
                $stmt->execute(['017_create_shift_schedules_table', $batch]);
                $stmt->execute(['018_create_guest_staff_table', $batch]);
                $stmt->execute(['019_enhance_shift_schedules_for_guest_staff', $batch]);
            } catch (\Exception $e) {
                // Migrations table may not exist, that's okay
            }
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.tables_created', [], 200);
        } catch (\Exception $e) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.table_creation_failed', ['error' => $e->getMessage()], 500);
        }
    }
    
    public function resetSystem() {
        if (!$this->hasPermission('settings.reset')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \App\Core\Logger::info("System reset initiated by user: " . ($_SESSION['username'] ?? 'Unknown') . " (ID: " . ($_SESSION['user_id'] ?? 'N/A') . ")");
            \App\Core\Logger::error("=== SYSTEM RESET STARTED ===");
            
            try {
                \App\Core\Logger::error("STEP 1: Creating database backup...");
                $backupResult = $this->createDatabaseBackup();
                if (!$backupResult['success']) {
                    \App\Core\Logger::error("Warning: Database backup failed, but continuing with reset: " . $backupResult['error']);
                    $backupResult = ['success' => false, 'filename' => null, 'error' => $backupResult['error']];
                } else {
                    \App\Core\Logger::error("STEP 1: Database backup created successfully: " . ($backupResult['filename'] ?? 'N/A'));
                }
                
                \App\Core\Logger::error("STEP 2: Backing up logo and favicon...");
                try {
                    $logoFaviconBackup = $this->backupLogoAndFavicon();
                    if (isset($logoFaviconBackup['success']) && $logoFaviconBackup['success']) {
                        \App\Core\Logger::error("STEP 2: Logo/Favicon backup created: " . ($logoFaviconBackup['filename'] ?? 'N/A'));
                    } else {
                        \App\Core\Logger::error("STEP 2: Logo/Favicon backup skipped or failed");
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 2: Logo/Favicon backup error (non-critical): " . $e->getMessage());
                    $logoFaviconBackup = ['success' => false];
                }
                
                \App\Core\Logger::error("STEP 3: Deleting uploaded images...");
                try {
                    $this->deleteUploadedImages();
                    \App\Core\Logger::error("STEP 3: Uploaded images deleted successfully");
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 3: Error deleting uploaded images (non-critical): " . $e->getMessage());
                }
                
                \App\Core\Logger::error("STEP 4: Getting database connection...");
                $pdo = \App\Core\DependencyFactory::getDatabase();
                
                \App\Core\Logger::error("STEP 5: Starting database transaction...");
                $pdo->beginTransaction();
                \App\Core\Logger::error("STEP 5: Transaction started");
                
                $preservedTables = [
                    'system_settings',
                    'roles',
                    'system_permissions',
                    'role_permissions',
                    'system_constants',
                    'system_labels',
                    'migrations',
                    'leave_types',
                    'users'
                ];
                
                \App\Core\Logger::error("STEP 6: Getting all tables from database...");
                $stmt = $pdo->prepare("SHOW TABLES");
                $stmt->execute();
                $allTables = [];
                while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                    $allTables[] = $row[0];
                }
                \App\Core\Logger::error("STEP 6: Found " . count($allTables) . " tables in database");
                
                $tables = array_filter($allTables, function($table) use ($preservedTables) {
                    return !in_array($table, $preservedTables);
                });
                $tables = array_values($tables);
                \App\Core\Logger::error("STEP 6: " . count($tables) . " tables will be truncated (excluding " . count($preservedTables) . " preserved tables)");
                
                \App\Core\Logger::error("STEP 7: Disabling foreign key checks...");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                \App\Core\Logger::error("STEP 8: Truncating " . count($tables) . " tables...");
                $truncatedCount = 0;
                $failedCount = 0;
                $failedTables = [];
                
                foreach ($tables as $table) {
                    try {
                        $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$sanitizedTable}`");
                        $stmt->execute();
                        $countBefore = $stmt->fetchColumn();
                        \App\Core\Logger::error("Table {$sanitizedTable}: {$countBefore} records before deletion");
                        
                        $pdo->exec("DELETE FROM `{$table}`");
                        
                        try {
                            $pdo->exec("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                        } catch (\Exception $autoIncEx) {
                            \App\Core\Logger::error("Table {$table}: Could not reset auto-increment (non-critical): " . $autoIncEx->getMessage());
                        }
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$sanitizedTable}`");
                        $stmt->execute();
                        $countAfter = $stmt->fetchColumn();
                        
                        if ((int)$countAfter === 0) {
                            $truncatedCount++;
                            \App\Core\Logger::error("Table {$table}: Successfully cleared ({$countBefore} records deleted)");
                        } else {
                            throw new \Exception("Still has {$countAfter} records after DELETE");
                        }
                    } catch (\Exception $e) {
                        $failedCount++;
                        $failedTables[] = [
                            'table' => $table,
                            'error' => $e->getMessage()
                        ];
                        \App\Core\Logger::error("Table {$table}: Failed to clear - " . $e->getMessage());
                    }
                }
                
                \App\Core\Logger::error("STEP 8: Truncated {$truncatedCount} tables, {$failedCount} failed");
                if (!empty($failedTables)) {
                    \App\Core\Logger::error("STEP 8: Failed tables: " . json_encode($failedTables));
                }
                
                \App\Core\Logger::error("STEP 9: Re-enabling foreign key checks...");
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                \App\Core\Logger::error("STEP 10: Deleting users (except current admin)...");
                try {
                    $userRepository = \App\Core\DependencyFactory::getUserRepository();
                    $currentUserId = $_SESSION['user_id'] ?? '';
                    if (!empty($currentUserId)) {
                        $userRepository->deleteAllExcept($currentUserId);
                        \App\Core\Logger::error("STEP 10: Users deleted (kept user ID: {$currentUserId})");
                    } else {
                        \App\Core\Logger::error("Warning: No current user ID found during system reset");
                        $userRepository->deleteAll();
                        \App\Core\Logger::error("STEP 10: All users deleted (no current user ID found)");
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 10: Error deleting users: " . $e->getMessage());
                    throw $e;
                }
                
                \App\Core\Logger::error("STEP 11: Clearing cache...");
                try {
                    $cacheService = \App\Core\DependencyFactory::getCacheService();
                    $cacheService->clear();
                    \App\Core\Logger::error("STEP 11: Cache cleared successfully");
                } catch (\Exception $e) {
                    \App\Core\Logger::error("STEP 11: Could not clear cache during system reset (non-critical): " . $e->getMessage());
                }
                
                \App\Core\Logger::error("STEP 12: Checking transaction status...");
                try {
                    if ($pdo->inTransaction()) {
                        \App\Core\Logger::error("STEP 12: Committing transaction...");
                        $pdo->commit();
                        \App\Core\Logger::error("STEP 12: Transaction committed successfully");
                    } else {
                        \App\Core\Logger::error("STEP 12: No active transaction to commit (may have been auto-committed by DDL statements)");
                    }
                } catch (\Exception $commitEx) {
                    \App\Core\Logger::error("STEP 12: Transaction commit failed (non-critical, data already deleted): " . $commitEx->getMessage());
                }
                
                \App\Core\Logger::error("=== SYSTEM RESET COMPLETED SUCCESSFULLY ===");
                \App\Core\Logger::info("System reset by user: " . ($_SESSION['username'] ?? 'Unknown') . " | Backup: " . (isset($backupResult) && isset($backupResult['filename']) ? $backupResult['filename'] : 'N/A') . " | Tables truncated: {$truncatedCount}, Failed: {$failedCount}");
                
                $responseMessage = 'Sistem başarıyla sıfırlandı. ';
                $responseMessage .= count($tables) . ' tablodan ' . $truncatedCount . ' tanesi temizlendi';
                
                if ($failedCount > 0) {
                    $responseMessage .= ', ' . $failedCount . ' tablo temizlenemedi';
                }
                
                $responseMessage .= '. Veritabanı yedeği: ' . (isset($backupResult) && isset($backupResult['filename']) ? $backupResult['filename'] : 'N/A');
                
                if (isset($logoFaviconBackup) && isset($logoFaviconBackup['success']) && $logoFaviconBackup['success']) {
                    $responseMessage .= ' | Logo/Favicon yedeği: ' . ($logoFaviconBackup['filename'] ?? 'N/A');
                }
                
                $responseData = [
                    'backup_file' => isset($backupResult) && isset($backupResult['filename']) ? $backupResult['filename'] : null,
                    'logo_favicon_backup' => isset($logoFaviconBackup) && isset($logoFaviconBackup['filename']) ? $logoFaviconBackup['filename'] : null,
                    'tables_truncated' => $truncatedCount,
                    'tables_failed' => $failedCount,
                    'total_tables' => count($tables)
                ];
                
                if (!empty($failedTables)) {
                    $responseData['failed_tables'] = $failedTables;
                }
                
                $this->toastNotificationService->sendApiResponse('success', $responseMessage, $responseData, 200);
            } catch (\Throwable $e) {
                \App\Core\Logger::error("=== SYSTEM RESET FAILED - ROLLING BACK ===");
                if (isset($pdo) && $pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                        \App\Core\Logger::error("Transaction rolled back successfully");
                    } catch (\Exception $rollbackEx) {
                        \App\Core\Logger::error("Error during rollback: " . $rollbackEx->getMessage());
                    }
                }
                
                $errorMessage = $e->getMessage();
                $errorTrace = $e->getTraceAsString();
                $errorFile = $e->getFile();
                $errorLine = $e->getLine();
                
                \App\Core\Logger::error("System reset failed: {$errorMessage} | File: {$errorFile}:{$errorLine} | Trace: {$errorTrace}");
                
                $userMessage = 'Sistem sıfırlama başarısız oldu: ' . $errorMessage;
                $this->toastNotificationService->sendApiResponse('error', $userMessage, [
                    'error_details' => [
                        'message' => $errorMessage,
                        'file' => basename($errorFile),
                        'line' => $errorLine
                    ]
                ], 500);
            }
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
        }
    }
    
    private function createDatabaseBackup(): array {
        try {
            $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
            $dbPort = $_ENV['DB_PORT'] ?? '3306';
            
            if (strpos($dbHost, ':') !== false) {
                list($dbHost, $dbPort) = explode(':', $dbHost, 2);
            }
            
            $dbConfig = [
                'host' => trim($dbHost),
                'port' => trim($dbPort),
                'name' => $_ENV['DB_NAME'] ?? '',
                'user' => $_ENV['DB_USER'] ?? '',
                'pass' => $_ENV['DB_PASS'] ?? '',
            ];
            
            if (empty($dbConfig['name']) || empty($dbConfig['user'])) {
                return ['success' => false, 'error' => 'Veritabanı bilgileri eksik'];
            }
            
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFilename = "db_backup_{$dbConfig['name']}_{$timestamp}.sql";
            $backupPath = $backupDir . '/' . $backupFilename;
            
            $host = escapeshellarg($dbConfig['host']);
            $port = escapeshellarg($dbConfig['port']);
            $user = escapeshellarg($dbConfig['user']);
            $pass = escapeshellarg($dbConfig['pass']);
            $db = escapeshellarg($dbConfig['name']);
            
            $dumpCommand = null;
            $commands = ['/usr/bin/mariadb-dump', '/usr/bin/mysqldump', 'mariadb-dump', 'mysqldump'];
            
            foreach ($commands as $cmd) {
                $fullPath = trim(shell_exec("which {$cmd} 2>/dev/null"));
                if (!empty($fullPath) && is_executable($fullPath)) {
                    $dumpCommand = $fullPath;
                    break;
                }
                if (file_exists($cmd) && is_executable($cmd)) {
                    $dumpCommand = $cmd;
                    break;
                }
            }
            
            if (!$dumpCommand) {
                $dumpCommand = '/usr/bin/mariadb-dump';
            }
            
            $command = "{$dumpCommand} -h {$host} -P {$port} -u {$user} -p{$pass} {$db} > " . escapeshellarg($backupPath) . " 2>&1";
            $logCommand = "{$dumpCommand} -h {$host} -P {$port} -u {$user} -p*** {$db} > " . escapeshellarg($backupPath) . " 2>&1";
            \App\Core\Logger::error("Database backup command: {$logCommand}");
            
            exec($command, $output, $returnCode);
            
            \App\Core\Logger::error("Database backup exec result - return code: {$returnCode}, output lines: " . count($output));
            
            if (!file_exists($backupPath)) {
                $error = implode("\n", $output);
                \App\Core\Logger::error("Database backup failed - file not created: {$error}");
                return ['success' => false, 'error' => 'Yedek dosyası oluşturulamadı: ' . ($error ?: 'Bilinmeyen hata')];
            }
            
            $fileSize = filesize($backupPath);
            if ($fileSize < 500) {
                $backupContent = file_get_contents($backupPath);
                if (stripos($backupContent, 'error') !== false || 
                    stripos($backupContent, 'access denied') !== false ||
                    stripos($backupContent, 'unknown server host') !== false ||
                    stripos($backupContent, 'mysqldump:') !== false) {
                    $error = trim($backupContent);
                    \App\Core\Logger::error("Database backup failed - contains errors: {$error}");
                    @unlink($backupPath);
                    return ['success' => false, 'error' => 'Yedek hatası: ' . substr($error, 0, 200)];
                }
            }
            
            if (!empty($output)) {
                $outputText = implode("\n", $output);
                if (stripos($outputText, 'error') !== false || 
                    stripos($outputText, 'access denied') !== false ||
                    stripos($outputText, 'unknown server host') !== false) {
                    \App\Core\Logger::error("Database backup failed - command output contains errors: {$outputText}");
                    @unlink($backupPath);
                    return ['success' => false, 'error' => 'Yedek komutu hatası: ' . substr($outputText, 0, 200)];
                }
            }
            
            if ($returnCode !== 0 && $fileSize < 500) {
                $error = implode("\n", $output);
                if (empty($error)) {
                    $backupContent = file_get_contents($backupPath);
                    $error = $backupContent;
                }
                \App\Core\Logger::error("Database backup failed - return code {$returnCode}, file size: {$fileSize}");
                @unlink($backupPath);
                return ['success' => false, 'error' => 'Yedek başarısız (kod: ' . $returnCode . '): ' . substr($error, 0, 200)];
            }
            
            if (function_exists('gzencode')) {
                $compressedPath = $backupPath . '.gz';
                $backupContent = file_get_contents($backupPath);
                $compressed = gzencode($backupContent, 9);
                file_put_contents($compressedPath, $compressed);
                unlink($backupPath);
                $backupPath = $compressedPath;
                $backupFilename .= '.gz';
            }
            
            return [
                'success' => true,
                'filename' => $backupFilename,
                'path' => $backupPath,
                'size' => filesize($backupPath)
            ];
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Database backup exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function backupLogoAndFavicon(): array {
        try {
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupFilename = "logo_favicon_backup_{$timestamp}.tar.gz";
            $backupPath = $backupDir . '/' . $backupFilename;
            
            $imagesDir = __DIR__ . '/../../public/assets/images';
            $logoPath = $imagesDir . '/logo.png';
            $faviconPath = $imagesDir . '/favicon.ico';
            
            $filesToBackup = [];
            if (file_exists($logoPath)) {
                $filesToBackup[] = $logoPath;
            }
            if (file_exists($faviconPath)) {
                $filesToBackup[] = $faviconPath;
            }
            
            $logoFormats = ['logo.jpg', 'logo.jpeg', 'logo.svg', 'logo.webp'];
            $faviconFormats = ['favicon.png', 'favicon.svg'];
            
            foreach ($logoFormats as $format) {
                $path = $imagesDir . '/' . $format;
                if (file_exists($path)) {
                    $filesToBackup[] = $path;
                }
            }
            
            foreach ($faviconFormats as $format) {
                $path = $imagesDir . '/' . $format;
                if (file_exists($path)) {
                    $filesToBackup[] = $path;
                }
            }
            
            if (empty($filesToBackup)) {
                return ['success' => false, 'error' => 'Logo veya favicon bulunamadı'];
            }
            
            $tempDir = sys_get_temp_dir() . '/logo_favicon_backup_' . uniqid();
            mkdir($tempDir, 0755, true);
            
            foreach ($filesToBackup as $file) {
                $basename = basename($file);
                copy($file, $tempDir . '/' . $basename);
            }
            
            $tarCommand = trim(shell_exec("which tar 2>/dev/null"));
            if (!empty($tarCommand) && is_executable($tarCommand)) {
                $command = "cd " . escapeshellarg($tempDir) . " && {$tarCommand} -czf " . escapeshellarg($backupPath) . " * 2>&1";
                exec($command, $output, $returnCode);
                
                array_map('unlink', glob("{$tempDir}/*"));
                rmdir($tempDir);
                
                if ($returnCode === 0 && file_exists($backupPath)) {
                    return [
                        'success' => true,
                        'filename' => $backupFilename,
                        'path' => $backupPath,
                        'size' => filesize($backupPath)
                    ];
                }
            }
            
            $zipPath = str_replace('.tar.gz', '.zip', $backupPath);
            $backupFilename = str_replace('.tar.gz', '.zip', $backupFilename);
            
            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                    foreach ($filesToBackup as $file) {
                        $zip->addFile($file, basename($file));
                    }
                    $zip->close();
                    
                    array_map('unlink', glob("{$tempDir}/*"));
                    rmdir($tempDir);
                    
                    return [
                        'success' => true,
                        'filename' => $backupFilename,
                        'path' => $zipPath,
                        'size' => filesize($zipPath)
                    ];
                }
            }
            
            $backupDirFiles = $backupDir . '/logo_favicon_' . $timestamp;
            mkdir($backupDirFiles, 0755, true);
            
            foreach ($filesToBackup as $file) {
                copy($file, $backupDirFiles . '/' . basename($file));
            }
            
            array_map('unlink', glob("{$tempDir}/*"));
            rmdir($tempDir);
            
            return [
                'success' => true,
                'filename' => 'logo_favicon_' . $timestamp . '/',
                'path' => $backupDirFiles,
                'size' => 0
            ];
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Logo/Favicon backup exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function deleteUploadedImages(): void {
        try {
            $uploadsDir = __DIR__ . '/../../public/uploads';
            
            if (!is_dir($uploadsDir)) {
                return;
            }
            
            $this->deleteDirectory($uploadsDir, true);
            
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error deleting uploaded images: " . $e->getMessage());
        }
    }
    
    private function deleteDirectory(string $dir, bool $keepDir = false): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..', 'index.php']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path, false);
            } else {
                @unlink($path);
            }
        }
        
        if (!$keepDir) {
            @rmdir($dir);
        }
    }
}

