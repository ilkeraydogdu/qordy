<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class SystemLogsController extends Controller {
    
    public function index() {
        // Require super admin role
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            // Get log files from multiple locations
            $logDirs = [
                __DIR__ . '/../../../app/logs/',
                __DIR__ . '/../../../storage/logs/',
                __DIR__ . '/../../../logs/',
            ];
            
            $logs = [];
            
            foreach ($logDirs as $logDir) {
                if (is_dir($logDir)) {
                    $files = scandir($logDir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                            $fullPath = $logDir . $file;
                            $logs[] = [
                                'name' => $file,
                                'path' => $fullPath,
                                'size' => filesize($fullPath),
                                'modified' => filemtime($fullPath),
                                'size_formatted' => $this->formatBytes(filesize($fullPath))
                            ];
                        }
                    }
                }
            }
            
            // Also check PHP error log
            $phpErrorLog = ini_get('error_log');
            if ($phpErrorLog && file_exists($phpErrorLog)) {
                $logs[] = [
                    'name' => 'PHP Error Log',
                    'path' => $phpErrorLog,
                    'size' => filesize($phpErrorLog),
                    'modified' => filemtime($phpErrorLog),
                    'size_formatted' => $this->formatBytes(filesize($phpErrorLog))
                ];
            }
            
            // Sort by modified date (newest first)
            usort($logs, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });
            
            // Read log content if requested
            $logContent = '';
            $selectedLog = $_GET['log'] ?? '';
            if ($selectedLog) {
                // Find log by name
                foreach ($logs as $log) {
                    if ($log['name'] === $selectedLog && file_exists($log['path'])) {
                        // Read last 2000 lines
                        $lines = file($log['path']);
                        $lines = array_slice($lines, -2000);
                        $logContent = implode('', $lines);
                        break;
                    }
                }
            }
            
            $data = [
                'logs' => $logs,
                'selectedLog' => $selectedLog,
                'logContent' => $logContent,
                'page' => 'superadmin-system-logs'
            ];
            
            $this->view('superadmin/system_logs', $data);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SuperAdmin System Logs error', [
                    'error' => $e->getMessage()
                ]);
            }
            
            $data = [
                'logs' => [],
                'selectedLog' => '',
                'logContent' => '',
                'page' => 'superadmin-system-logs'
            ];
            
            $this->view('superadmin/system_logs', $data);
        }
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
}
