<?php
namespace App\Services;

class WhatsAppMessageLogService {
    private $repository;
    
    /**
     * Meta WhatsApp Business API tier limits (business-initiated conversations/month)
     * Tier 1: 1,000 | Tier 2: 10,000 | Tier 3: 100,000 | Tier 4: Unlimited
     * Default to Tier 1 (1,000/month) - can be overridden via settings
     */
    private const DEFAULT_MONTHLY_LIMIT = 1000;
    private const DEFAULT_DAILY_LIMIT = 250;

    public function __construct($repository) {
        $this->repository = $repository;
    }

    public function logMessage(array $data): ?string {
        $logData = [
            'tenant_id' => $data['tenant_id'] ?? $data['business_id'] ?? ($_SESSION['business_id'] ?? null),
            'message_type' => $data['message_type'] ?? 'other',
            // Direction: outbound (bizim gönderdiğimiz) veya inbound (müşteriden gelen).
            // DB'ye bu kolon additive migration ile eklendi (whatsapp_inbound_columns.sql),
            // eski tablo hali için repository tarafı da tolere ediyor.
            'direction' => in_array(($data['direction'] ?? 'outbound'), ['outbound','inbound'], true)
                ? $data['direction']
                : 'outbound',
            'recipient_phone' => $data['recipient_phone'] ?? '',
            'sender_phone' => $data['sender_phone'] ?? null,
            'template_name' => $data['template_name'] ?? null,
            'message_content' => $data['message_content'] ?? null,
            'meta_message_id' => $data['meta_message_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'error_code' => $data['error_code'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'http_status_code' => $data['http_status_code'] ?? null,
            'api_response_time_ms' => $data['api_response_time_ms'] ?? null,
            'sent_by' => $data['sent_by'] ?? ($_SESSION['user_id'] ?? 'system'),
        ];

        try {
            return $this->repository->create($logData);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('WhatsAppMessageLogService: Failed to log message', [
                    'error' => $e->getMessage(),
                    'data' => $logData
                ]);
            }
            return null;
        }
    }

    public function getDashboardStats(?string $businessId = null): array {
        $today = $this->repository->getDailyStats($businessId);
        $weeklyData = $this->repository->getWeeklyStats($businessId);
        $monthlyTotal = $this->repository->getMonthlyTotal($businessId);
        $successRate = $this->repository->getSuccessRate($businessId, 30);
        $hourly = $this->repository->getHourlyDistribution($businessId);
        $totalAll = $this->repository->getTotalCount($businessId);

        $monthlyLimit = $this->getMonthlyLimit($businessId);
        $dailyLimit = $this->getDailyLimit($businessId);
        $todayTotal = (int)($today['total'] ?? 0);
        
        $successTotal = (int)($successRate['total'] ?? 0);
        $successCount = (int)($successRate['success'] ?? 0);
        $rate = $successTotal > 0 ? round(($successCount / $successTotal) * 100, 1) : 0;

        $hourlyLabels = [];
        $hourlyCounts = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyLabels[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
            $hourlyCounts[] = 0;
        }
        foreach ($hourly as $row) {
            $hourlyCounts[(int)$row['hour']] = (int)$row['count'];
        }

        $weeklyLabels = [];
        $weeklySuccess = [];
        $weeklyFailed = [];
        foreach ($weeklyData as $row) {
            $weeklyLabels[] = date('d M', strtotime($row['date']));
            $weeklySuccess[] = (int)$row['success'];
            $weeklyFailed[] = (int)$row['failed'];
        }

        return [
            'today' => [
                'total' => $todayTotal,
                'sent' => (int)($today['sent'] ?? 0),
                'delivered' => (int)($today['delivered'] ?? 0),
                'read' => (int)($today['read_count'] ?? 0),
                'failed' => (int)($today['failed'] ?? 0),
                'pending' => (int)($today['pending'] ?? 0),
                'otp' => (int)($today['otp_count'] ?? 0),
                'test' => (int)($today['test_count'] ?? 0),
                'template' => (int)($today['template_count'] ?? 0),
                'avg_response_time' => round((float)($today['avg_response_time'] ?? 0)),
                'first_message_at' => $today['first_message_at'] ?? null,
                'last_message_at' => $today['last_message_at'] ?? null,
            ],
            'limits' => [
                'daily_limit' => $dailyLimit,
                'daily_used' => $todayTotal,
                'daily_remaining' => max(0, $dailyLimit - $todayTotal),
                'daily_percentage' => $dailyLimit > 0 ? round(($todayTotal / $dailyLimit) * 100, 1) : 0,
                'monthly_limit' => $monthlyLimit,
                'monthly_used' => $monthlyTotal,
                'monthly_remaining' => max(0, $monthlyLimit - $monthlyTotal),
                'monthly_percentage' => $monthlyLimit > 0 ? round(($monthlyTotal / $monthlyLimit) * 100, 1) : 0,
            ],
            'success_rate' => [
                'rate' => $rate,
                'total' => $successTotal,
                'success' => $successCount,
                'failed' => (int)($successRate['failed'] ?? 0),
            ],
            'weekly_chart' => [
                'labels' => $weeklyLabels,
                'success' => $weeklySuccess,
                'failed' => $weeklyFailed,
            ],
            'hourly_chart' => [
                'labels' => $hourlyLabels,
                'counts' => $hourlyCounts,
            ],
            'total_all_time' => $totalAll,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function getMessageHistory(?string $businessId = null, int $page = 1, int $perPage = 20, array $filters = []): array {
        $offset = ($page - 1) * $perPage;
        $messages = $this->repository->getRecentMessages($businessId, $perPage, $offset, $filters);
        $total = $this->repository->getTotalCount($businessId, $filters);
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'filters' => $filters,
        ];
    }

    public function getTopRecipients(?string $businessId = null): array {
        return $this->repository->getTopRecipients($businessId, 10);
    }

    public function updateStatus(string $metaMessageId, string $status): bool {
        return $this->repository->updateMessageStatus($metaMessageId, $status);
    }

    private function getMonthlyLimit(?string $businessId): int {
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $limit = $settingsService->getSetting('whatsapp_monthly_limit');
            if ($limit && is_numeric($limit)) {
                return (int)$limit;
            }
        } catch (\Exception $e) {
            // ignore
        }
        return self::DEFAULT_MONTHLY_LIMIT;
    }

    private function getDailyLimit(?string $businessId): int {
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $limit = $settingsService->getSetting('whatsapp_daily_limit');
            if ($limit && is_numeric($limit)) {
                return (int)$limit;
            }
        } catch (\Exception $e) {
            // ignore
        }
        return self::DEFAULT_DAILY_LIMIT;
    }
}
