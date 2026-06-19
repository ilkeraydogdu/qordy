<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';

use App\Core\Controller;

class ActivityLogsController extends Controller {

    public function index() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $rawFilters = [
            'business_id' => $_GET['business_id'] ?? '',
            'user_id' => trim((string)($_GET['user_id'] ?? '')),
            'action' => $_GET['action'] ?? '',
            'entity_type' => trim((string)($_GET['entity_type'] ?? '')),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $queryFilters = array_filter($rawFilters, fn($v) => $v !== '' && $v !== null);

        $activityService = \App\Core\DependencyFactory::getActivityLogService();
        $limit = isset($_GET['export']) && $_GET['export'] === 'csv' ? 5000 : 300;
        $logs = $activityService->query($queryFilters, $limit, 0);

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="activity-logs-' . date('Y-m-d-His') . '.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'action', 'business_id', 'user_id', 'actor_type', 'entity_type', 'entity_id', 'ip_address', 'metadata'], ';');
            foreach ($logs as $row) {
                fputcsv($out, [
                    $row['created_at'] ?? '',
                    $row['action'] ?? '',
                    $row['business_id'] ?? '',
                    $row['user_id'] ?? '',
                    $row['actor_type'] ?? '',
                    $row['entity_type'] ?? '',
                    $row['entity_id'] ?? '',
                    $row['ip_address'] ?? '',
                    $row['metadata'] ?? '',
                ], ';');
            }
            fclose($out);
            exit;
        }

        $db = \App\Core\DependencyFactory::getDatabase();
        $businesses = [];
        try {
            $businesses = $db->query("SELECT customer_id, company_name, email FROM customers ORDER BY company_name ASC LIMIT 500")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $this->view('superadmin/activity_logs', [
            'logs' => $logs,
            'filters' => $rawFilters,
            'businesses' => $businesses,
            'page' => 'activity-logs',
            'title' => 'Aktivite Günlüğü - Qodmin'
        ]);
    }
}
