<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class ErrorLogController extends Controller {
    
    protected $javascriptErrorLogService;
    protected $unifiedErrorLogService;
    
    public function __construct() {
        parent::__construct();
        $this->javascriptErrorLogService = \App\Core\DependencyFactory::getJavaScriptErrorLogService();
        $this->unifiedErrorLogService = \App\Core\DependencyFactory::getUnifiedErrorLogService();
    }
 /**
 * SECURITY: Whitelist of log directories the controller is allowed to read.
 * Prevents path traversal via /etc/passwd, /dev/shm, /proc, etc.
 */
 private const ALLOWED_LOG_DIRS = [
 '/var/www/vhosts/qordy.com/httpdocs/logs',
 '/var/log',
 '/tmp',
 ];

 /**
 * Validate that a file path is within an allowed log directory and is a regular file.
 * @param string $filePath
 * @return bool
 */
 private function isAllowedLogPath(string $filePath): bool {
 $real = realpath($filePath);
 if ($real === false) {
 return false;
 }
 // Must be a regular file (not directory, not symlink to elsewhere)
 if (!is_file($real)) {
 return false;
 }
 foreach (self::ALLOWED_LOG_DIRS as $allowedDir) {
 $allowedReal = realpath($allowedDir);
 if ($allowedReal !== false && strpos($real, $allowedReal . DIRECTORY_SEPARATOR) === 0) {
 return true;
 }
 }
 return false;
 }

    
    public function errorLogs() {
        // Require super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
        $perPage = 50;
        
        // Log source: 'database' or 'server'
        $logSource = $queryParams['log_source'] ?? 'database';
        
        // Domain filter for server logs
        $domainFilter = $queryParams['domain'] ?? 'all';
        
        $filters = [
            'source' => $queryParams['source'] ?? 'all',
            'type' => $queryParams['type'] ?? '',
            'level' => $queryParams['level'] ?? '',
            'date_from' => $queryParams['date_from'] ?? '',
            'date_to' => $queryParams['date_to'] ?? '',
            'user_id' => $queryParams['user_id'] ?? '',
            'resolved' => isset($queryParams['resolved']) && $queryParams['resolved'] !== '' ? (bool)$queryParams['resolved'] : null,
            'search' => $queryParams['search'] ?? '',
            'min_occurrences' => $queryParams['min_occurrences'] ?? '',
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== '' && $value !== null;
        });
        
        // Get database logs
        $dbResult = $this->unifiedErrorLogService->getAllErrorLogs($page, $perPage, $filters);
        $statistics = $this->unifiedErrorLogService->getUnifiedStatistics();
        
        // Get server logs - only for qordy.com (no subdomains)
        $serverLogs = $this->getServerLogs('qordy.com', $queryParams);
        
        $data = [
            'title' => 'Hata Logları - Super Admin',
            'error_logs' => $dbResult['logs'],
            'pagination' => [
                'total' => $dbResult['total'],
                'page' => $dbResult['page'],
                'per_page' => $dbResult['per_page'],
                'total_pages' => $dbResult['total_pages']
            ],
            'filters' => [
                'source' => $queryParams['source'] ?? 'all',
                'type' => $queryParams['type'] ?? '',
                'level' => $queryParams['level'] ?? '',
                'date_from' => $queryParams['date_from'] ?? '',
                'date_to' => $queryParams['date_to'] ?? '',
                'user_id' => $queryParams['user_id'] ?? '',
                'resolved' => isset($queryParams['resolved']) ? (bool)$queryParams['resolved'] : null
            ],
            'statistics' => $statistics,
            'log_source' => $logSource,
            'server_logs' => $serverLogs,
            'queryParams' => $queryParams
        ];
        
        $this->view('superadmin/error_logs', $data);
    }
    
    /**
     * Get server log files and their content - only for qordy.com
     */
    private function getServerLogs($domain = 'qordy.com', $queryParams = []) {
        $logs = [];
        
        // Base paths
        $basePath = '/var/www/vhosts/qordy.com';
        $domainLogsPath = $basePath . '/logs';
        $appLogsPath = $basePath . '/httpdocs/logs';
        
        // Main domain error log
        $errorLogPath = $domainLogsPath . '/error_log';
        if (file_exists($errorLogPath) && is_readable($errorLogPath)) {
            $logs[] = [
                'name' => 'qordy.com - Error Log',
                'domain' => 'qordy.com',
                'path' => $errorLogPath,
                'type' => 'error',
                'size' => filesize($errorLogPath),
                'modified' => filemtime($errorLogPath),
                'size_formatted' => $this->formatBytes(filesize($errorLogPath)),
                'lines' => $this->readLogFile($errorLogPath, $queryParams)
            ];
        }
        
        // Application logs
        $appLogFiles = [
            'app.log' => 'Uygulama Logu',
            'error.log' => 'Hata Logu',
            'php_errors.log' => 'PHP Hata Logu'
        ];
        
        foreach ($appLogFiles as $file => $label) {
            $filePath = $appLogsPath . '/' . $file;
            if (file_exists($filePath) && is_readable($filePath)) {
                $logs[] = [
                    'name' => 'qordy.com - ' . $label,
                    'domain' => 'qordy.com',
                    'path' => $filePath,
                    'type' => strpos($file, 'error') !== false ? 'error' : 'app',
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath),
                    'size_formatted' => $this->formatBytes(filesize($filePath)),
                    'lines' => $this->readLogFile($filePath, $queryParams)
                ];
            }
        }
        
        // Sort by modified date (newest first)
        usort($logs, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        return $logs;
    }
    
    /**
     * Read log file with filtering
     */
    private function readLogFile($filePath, $queryParams = []) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }
        
        $maxLines = isset($queryParams['max_lines']) ? (int)$queryParams['max_lines'] : 500;
        $searchTerm = $queryParams['search'] ?? '';
        $levelFilter = $queryParams['level'] ?? '';
        
        // Read file line by line (for large files)
        $lines = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            return [];
        }
        
        // Read from end of file for recent logs
        $fileSize = filesize($filePath);
        $chunkSize = min(1024 * 1024, $fileSize); // 1MB chunks
        
        // If file is small, read normally
        if ($fileSize < 10 * 1024 * 1024) { // Less than 10MB
            $command = "tail -n {$maxLines} " . escapeshellarg($filePath) . " 2>/dev/null";
            $output = shell_exec($command);
            $allLines = $output ? array_slice(explode("\n", trim($output)), 0, $maxLines) : [];
            $allLines = array_reverse($allLines); // Most recent first
            
            foreach ($allLines as $lineNum => $line) {
                if (count($lines) >= $maxLines) {
                    break;
                }
                
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                // Apply filters
                if ($searchTerm && stripos($line, $searchTerm) === false) {
                    continue;
                }
                
                if ($levelFilter) {
                    $levelUpper = strtoupper($levelFilter);
                    if (stripos($line, $levelUpper) === false) {
                        continue;
                    }
                }
                
                $lines[] = [
                    'line' => $line,
                    'line_number' => count($allLines) - $lineNum,
                    'timestamp' => $this->extractTimestamp($line)
                ];
            }
        } else {
            // For large files, read last N lines
            $command = "tail -n {$maxLines} " . escapeshellarg($filePath) . " 2>/dev/null";
            $output = shell_exec($command);
            if ($output) {
                $allLines = explode("\n", trim($output));
                $allLines = array_reverse($allLines);
                
                foreach ($allLines as $lineNum => $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    
                    // Apply filters
                    if ($searchTerm && stripos($line, $searchTerm) === false) {
                        continue;
                    }
                    
                    if ($levelFilter) {
                        $levelUpper = strtoupper($levelFilter);
                        if (stripos($line, $levelUpper) === false) {
                            continue;
                        }
                    }
                    
                    $lines[] = [
                        'line' => $line,
                        'line_number' => count($allLines) - $lineNum,
                        'timestamp' => $this->extractTimestamp($line)
                    ];
                }
            }
        }
        
        fclose($handle);
        
        return $lines;
    }
    
    /**
     * Extract timestamp from log line
     */
    private function extractTimestamp($line) {
        // Try various timestamp formats
        $patterns = [
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', // [2026-01-11 13:16:32]
            '/\[(\d{2}\/\w+\/\d{4}:\d{2}:\d{2}:\d{2})\]/', // [11/Jan/2026:13:16:32]
            '/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', // 2026-01-11 13:16:32
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Export all logs (database + server) as text
     * GET /qodmin/error-logs/export-all
     */
    public function exportAllLogs() {
        // Require super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $filters = [
            'source' => $queryParams['source'] ?? 'all',
            'type' => $queryParams['type'] ?? '',
            'level' => $queryParams['level'] ?? '',
            'date_from' => $queryParams['date_from'] ?? '',
            'date_to' => $queryParams['date_to'] ?? '',
            'user_id' => $queryParams['user_id'] ?? '',
            'resolved' => isset($queryParams['resolved']) && $queryParams['resolved'] !== '' ? (bool)$queryParams['resolved'] : null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== '' && $value !== null;
        });
        
        $output = [];
        $output[] = "===========================================";
        $output[] = "QORDY.COM - TÜM HATA LOGLARI";
        $output[] = "Export Tarihi: " . date('Y-m-d H:i:s');
        $output[] = "===========================================";
        $output[] = "";
        
        // Get all database logs (without pagination limit)
        $dbResult = $this->unifiedErrorLogService->getAllErrorLogs(1, 999999, $filters);
        $dbLogs = $dbResult['logs'];
        
        $output[] = "===========================================";
        $output[] = "VERİTABANI LOGLARI";
        $output[] = "Toplam: " . count($dbLogs) . " kayıt";
        $output[] = "===========================================";
        $output[] = "";
        
        if (empty($dbLogs)) {
            $output[] = "Veritabanında hata logu bulunamadı.";
            $output[] = "";
        } else {
            foreach ($dbLogs as $index => $log) {
                $source = $log['source'] ?? 'unknown';
                $errorType = $log['error_type'] ?? ($log['type'] ?? ($log['level'] ?? 'UNKNOWN'));
                $isResolved = !empty($log['resolved_at']);
                
                $output[] = "--- Log #" . ($index + 1) . " ---";
                $output[] = "Kaynak: " . strtoupper($source);
                $output[] = "Tarih: " . ($log['created_at'] ?? 'N/A');
                $output[] = "Tip/Seviye: " . $errorType;
                $output[] = "Durum: " . ($isResolved ? 'Çözüldü' : 'Açık');
                $output[] = "Mesaj: " . ($log['message'] ?? 'N/A');
                $output[] = "Dosya: " . ($log['file'] ?? $log['filename'] ?? 'N/A');
                $output[] = "Satır: " . ($log['line'] ?? $log['lineno'] ?? '-');
                if (!empty($log['stack'])) {
                    $output[] = "Stack Trace:";
                    $output[] = $log['stack'];
                }
                if (!empty($log['url'])) {
                    $output[] = "URL: " . $log['url'];
                }
                $output[] = "";
            }
        }
        
        // Get server logs
        $serverLogsParams = array_merge($queryParams, ['max_lines' => 10000]);
        $serverLogs = $this->getServerLogs('qordy.com', $serverLogsParams);
        
        $output[] = "===========================================";
        $output[] = "SUNUCU LOGLARI";
        $output[] = "Toplam: " . count($serverLogs) . " dosya";
        $output[] = "===========================================";
        $output[] = "";
        
        if (empty($serverLogs)) {
            $output[] = "Sunucu log dosyası bulunamadı.";
            $output[] = "";
        } else {
            foreach ($serverLogs as $logFile) {
                $output[] = "===========================================";
                $output[] = "Dosya: " . $logFile['name'];
                $output[] = "Yol: " . $logFile['path'];
                $output[] = "Boyut: " . $logFile['size_formatted'];
                $output[] = "Son Değişiklik: " . date('Y-m-d H:i:s', $logFile['modified']);
                $output[] = "===========================================";
                $output[] = "";
                
                if (!empty($logFile['lines'])) {
                    foreach ($logFile['lines'] as $lineData) {
                        $lineNumber = $lineData['line_number'] ?? '';
                        $timestamp = $lineData['timestamp'] ?? '';
                        $line = $lineData['line'] ?? '';
                        
                        $outputLine = "";
                        if ($lineNumber) {
                            $outputLine .= "[" . $lineNumber . "] ";
                        }
                        if ($timestamp) {
                            $outputLine .= "[" . $timestamp . "] ";
                        }
                        $outputLine .= $line;
                        $output[] = $outputLine;
                    }
                } else {
                    $output[] = "Bu log dosyasında kayıt bulunamadı.";
                }
                $output[] = "";
                $output[] = "";
            }
        }
        
        $output[] = "===========================================";
        $output[] = "EXPORT TAMAMLANDI";
        $output[] = "===========================================";
        
        $text = implode("\n", $output);
        
        // Return as JSON for JavaScript to handle
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'data' => $text,
            'database_count' => count($dbLogs),
            'server_files_count' => count($serverLogs)
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
