<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\SystemSettingsRepository;

class SystemSettingsService extends BaseService {
    
    public function __construct(SystemSettingsRepository $repository) {
        parent::__construct($repository);
    }

    public function getSetting(string $key, ?string $default = null): ?string {
        try {
            $value = $this->repository->getByKey($key);
            return $value !== null ? $value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Resolve the current tenant id for cache key scoping.
     * Returns a non-empty string; falls back to "global" ONLY for genuinely
     * tenant-less contexts (CLI, pre-auth requests). Never returns a value
     * that could be shared between two logged-in tenants.
     */
    private function currentTenantCacheScope(): string {
        $tenantId = null;
        if (class_exists('\App\Core\TenantContext')) {
            $tenantId = \App\Core\TenantContext::getId();
        }
        if (empty($tenantId)) {
            $tenantId = $_SESSION['business_id'] ?? ($_SESSION['customer_id'] ?? null);
        }
        return !empty($tenantId) ? (string)$tenantId : 'global';
    }

    public function setSetting(string $key, string $value): bool {
        try {
            $result = $this->repository->setValue($key, $value);
            $cache = \App\Core\DependencyFactory::getCacheService();
            $scope = $this->currentTenantCacheScope();
            $cache->delete('system_settings:all:' . $scope);
            $cache->delete('system_settings:all');
            return $result;
        } catch (\Exception $e) {
            \App\Repositories\SystemSettingsRepository::$lastSetValueError = $e->getMessage();
            \App\Core\Logger::error('SystemSettingsService::setSetting error: ' . $e->getMessage());
            return false;
        }
    }

    public function getSettings(): array {
        try {
            $cache = \App\Core\DependencyFactory::getCacheService();
            $scope = $this->currentTenantCacheScope();
            $cacheKey = 'system_settings:all:' . $scope;
            
            return $cache->remember($cacheKey, function() {
                $rows = $this->repository->getAll();
                if (!empty($rows) && isset($rows[0]) && !isset($rows[0]['setting_key'])) {
                    return $rows[0];
                }
                $settings = [];
                foreach ($rows as $row) {
                    if (isset($row['setting_key'], $row['setting_value'])) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
                return !empty($settings) ? $settings : ($rows[0] ?? []);
            }, 300);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAllSettings(): array {
        return $this->getSettings();
    }

    /**
     * Return platform-wide settings row (tenant_id IS NULL).
     * Super-admin ekranları (ör. /qodmin/settings) bu metodu kullanmalıdır;
     * aksi halde yetkili kullanıcının tenant bağlamına düşer ve platform
     * ayarları (Meta API, SMTP, 2FA, vs.) yanlış satırdan okunur.
     */
    public function getPlatformSettings(): array {
        try {
            if (method_exists($this->repository, 'getPlatformSettings')) {
                return $this->repository->getPlatformSettings();
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('SystemSettingsService::getPlatformSettings error: ' . $e->getMessage());
        }
        return [];
    }

    /** @var string|null Last error message when updateSettings fails */
    private static $lastUpdateError = null;

    public function updateSettings(array $data): bool {
        self::$lastUpdateError = null;
        try {
            $success = true;
            foreach ($data as $key => $value) {
                if (!$this->setSetting($key, (string)$value)) {
                    $success = false;
                    $repoErr = \App\Repositories\SystemSettingsRepository::$lastSetValueError;
                    self::$lastUpdateError = $repoErr ?: "Ayar kaydedilemedi: {$key}. Veritabanı sütunları eksik olabilir.";
                    break;
                }
            }
            return $success;
        } catch (\Exception $e) {
            self::$lastUpdateError = $e->getMessage();
            \App\Core\Logger::error('SystemSettingsService::updateSettings error: ' . $e->getMessage());
            return false;
        }
    }

    public static function getLastUpdateError(): ?string {
        return self::$lastUpdateError;
    }

    public function getSiteName(): string {
        return $this->getSetting('site_name', 'Qordy');
    }

    public function getAppEnv(): string {
        return $this->getSetting('app_env', 'production');
    }

    public function getAppDebug(): bool {
        return $this->getSetting('app_debug', '0') === '1' || $this->getSetting('app_debug', 'false') === 'true';
    }

    public function getTimezone(): string {
        return $this->getSetting('timezone', 'Europe/Istanbul');
    }

    /**
     * Platform support email. Falls back to a sensible destek@<apex> form
     * derived from UrlService so it is never hardcoded to qordy.com.
     */
    public function getSupportEmail(): string {
        $configured = $this->getSetting('support_email', '') ?: $this->getSetting('contact_email', '');
        if (!empty($configured) && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }
        $apex = 'qordy.com';
        try {
            $urlService = \App\Core\DependencyFactory::getUrlService();
            if ($urlService) {
                $resolved = $urlService->getApexDomain();
                if (!empty($resolved)) {
                    $apex = $resolved;
                }
            }
        } catch (\Throwable $ignored) {}
        return 'destek@' . $apex;
    }

    /**
     * Canonical default weekly working-hours schedule.
     * Single source of truth so controllers and views can't drift.
     */
    public function getDefaultWorkingHoursDays(): array {
        $defaultStart = $this->getSetting('working_hours_start', '09:00') ?: '09:00';
        $defaultEnd = $this->getSetting('working_hours_end', '02:00') ?: '02:00';
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $schedule = [];
        foreach ($days as $day) {
            $schedule[$day] = [
                'enabled' => true,
                'start' => $defaultStart,
                'end' => $defaultEnd,
            ];
        }
        return $schedule;
    }

    /**
     * Resolve the weekly schedule for the current tenant. Falls back to the
     * canonical default schedule when the stored value is missing or malformed.
     */
    public function resolveWorkingHoursDays(?array $settings = null): array {
        if ($settings === null) {
            $settings = $this->getSettings();
        }
        $raw = $settings['working_hours_days'] ?? null;
        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
        return $this->getDefaultWorkingHoursDays();
    }

    public function getGeminiApiKey(): ?string {
        return $this->getSetting('gemini_api_key');
    }

    public function getOpusmaxApiKey(): ?string {
        return $this->getSetting('opusmax_api_key');
    }

    public function getLogLevel(): string {
        return $this->getSetting('log_level', 'ERROR');
    }

    public function getWebsocketPort(): int {
        return (int)$this->getSetting('websocket_port', '8080');
    }

    public function getSessionLifetime(): int {
        $lifetime = $this->getSetting('session_lifetime', '28800');
        return (int)$lifetime;
    }

    public function getSessionSecureCookie(): bool {
        return $this->getSetting('session_secure_cookie', '1') === '1' || $this->getSetting('session_secure_cookie', 'true') === 'true';
    }

    public function getSessionHttpOnly(): bool {
        return $this->getSetting('session_http_only', '1') === '1' || $this->getSetting('session_http_only', 'true') === 'true';
    }

    public function getSessionSameSite(): string {
        return $this->getSetting('session_same_site', 'Lax');
    }

    public function getBusinessDateRange(): array {
        $settings = $this->getSettings();
        
        $workingHoursEnabled = ($settings['working_hours_enabled'] ?? '0') === '1';
        
        $timezone = new \DateTimeZone($this->getTimezone());
        $now = new \DateTime('now', $timezone);
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');
        
        if (!$workingHoursEnabled) {
            return [
                'start' => $today . ' 00:00:00',
                'end' => $today . ' 23:59:59',
                'start_datetime' => $today . ' 00:00:00',
                'end_datetime' => $today . ' 23:59:59',
                'date' => $today,
            ];
        }
        
        // Read per-day schedule
        $daysJson = $settings['working_hours_days'] ?? null;
        $days = ($daysJson && is_string($daysJson)) ? json_decode($daysJson, true) : null;
        $dayMap = ['Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun'];
        $todayKey = $dayMap[$now->format('D')] ?? 'mon';
        $yesterday = (clone $now)->modify('-1 day');
        $yesterdayKey = $dayMap[$yesterday->format('D')] ?? 'mon';
        $yesterdayDate = $yesterday->format('Y-m-d');
        
        $globalStart = $settings['working_hours_start'] ?? '09:00';
        $globalEnd = $settings['working_hours_end'] ?? '00:00';
        
        // Get yesterday's schedule (for overnight check)
        $yStart = $globalStart;
        $yEnd = $globalEnd;
        if (is_array($days) && isset($days[$yesterdayKey])) {
            $yStart = $days[$yesterdayKey]['start'] ?? $globalStart;
            $yEnd = $days[$yesterdayKey]['end'] ?? $globalEnd;
        }
        
        // Get today's schedule
        $tStart = $globalStart;
        $tEnd = $globalEnd;
        if (is_array($days) && isset($days[$todayKey])) {
            $tStart = $days[$todayKey]['start'] ?? $globalStart;
            $tEnd = $days[$todayKey]['end'] ?? $globalEnd;
        }
        
        // Case 1: It's after midnight but before yesterday's end time
        // (we're still in yesterday's business day)
        $yIsOvernight = $yEnd < $yStart;
        if ($yIsOvernight && $currentTime < $yEnd) {
            $startDt = $yesterdayDate . ' ' . $yStart . ':00';
            $endDt = $today . ' ' . $yEnd . ':00';
            return [
                'start' => $startDt,
                'end' => $endDt,
                'start_datetime' => $startDt,
                'end_datetime' => $endDt,
                'date' => $yesterdayDate,
            ];
        }
        
        // Case 2: We're in today's business day
        $tIsOvernight = $tEnd < $tStart;
        $startDt = $today . ' ' . $tStart . ':00';
        if ($tIsOvernight) {
            $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
            $endDt = $tomorrow . ' ' . $tEnd . ':00';
        } else {
            $endDt = $today . ' ' . $tEnd . ':00';
        }
        
        return [
            'start' => $startDt,
            'end' => $endDt,
            'start_datetime' => $startDt,
            'end_datetime' => $endDt,
            'date' => $today,
        ];
    }

    public function getBusinessDateRangeForDate(string $date): array {
        // Check logged business day range first (accurate for historical dates)
        try {
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId) {
                $db = \App\Core\DependencyFactory::getDatabase();
                $stmt = $db->prepare("SELECT start_datetime, end_datetime FROM business_day_log WHERE tenant_id = ? AND business_date = ? LIMIT 1");
                $stmt->execute([$tenantId, $date]);
                $logged = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($logged) {
                    return [
                        'date' => $date,
                        'start_datetime' => $logged['start_datetime'],
                        'end_datetime' => $logged['end_datetime']
                    ];
                }
            }
        } catch (\Exception $e) {}
        
        // Fallback: compute from current working hours settings
        $settings = $this->getSettings();
        $enabled = ($settings['working_hours_enabled'] ?? '0') === '1';
        
        if (!$enabled) {
            return [
                'date' => $date,
                'start_datetime' => $date . ' 00:00:00',
                'end_datetime' => $date . ' 23:59:59'
            ];
        }
        
        $daysJson = $settings['working_hours_days'] ?? null;
        $days = ($daysJson && is_string($daysJson)) ? json_decode($daysJson, true) : null;
        
        $dayMap = ['Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun'];
        $dt = new \DateTime($date, new \DateTimeZone('Europe/Istanbul'));
        $dayKey = $dayMap[$dt->format('D')] ?? 'mon';
        
        $startTime = '09:00';
        $endTime = '02:00';
        
        if (is_array($days) && isset($days[$dayKey])) {
            $startTime = $days[$dayKey]['start'] ?? '09:00';
            $endTime = $days[$dayKey]['end'] ?? '02:00';
        }
        
        $isOvernight = $endTime < $startTime;
        
        if ($isOvernight) {
            $nextDay = (clone $dt)->modify('+1 day')->format('Y-m-d');
            return [
                'date' => $date,
                'start_datetime' => $date . ' ' . $startTime . ':00',
                'end_datetime' => $nextDay . ' ' . $endTime . ':00'
            ];
        }
        
        return [
            'date' => $date,
            'start_datetime' => $date . ' ' . $startTime . ':00',
            'end_datetime' => $date . ' ' . $endTime . ':00'
        ];
    }
    
    public function logBusinessDayRange(string $businessId, string $date, string $startDatetime, string $endDatetime, string $source = 'auto'): void {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->prepare("INSERT INTO business_day_log (tenant_id, business_date, start_datetime, end_datetime, source) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE start_datetime = VALUES(start_datetime), end_datetime = VALUES(end_datetime), source = VALUES(source)");
            $stmt->execute([$businessId, $date, $startDatetime, $endDatetime, $source]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to log business day range', ['error' => $e->getMessage()]);
            }
        }
    }
    
    public function checkWorkingHours(): array {
        $settings = $this->getSettings();
        
        $enabled = ($settings['working_hours_enabled'] ?? '0') === '1';
        if (!$enabled) {
            return ['open' => true, 'message' => ''];
        }
        
        $timezone = new \DateTimeZone($this->getTimezone());
        $now = new \DateTime('now', $timezone);
        $currentTime = $now->format('H:i');
        
        // Map PHP day (Mon,Tue...) to settings keys (mon,tue...)
        $dayMap = ['Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun'];
        $todayKey = $dayMap[$now->format('D')] ?? 'mon';
        
        $workingDaysJson = $settings['working_hours_days'] ?? '{}';
        $workingDays = json_decode($workingDaysJson, true) ?: [];
        
        $globalStart = $settings['working_hours_start'] ?? '09:00';
        $globalEnd = $settings['working_hours_end'] ?? '23:00';
        
        // Get today's schedule
        $dayConfig = $workingDays[$todayKey] ?? null;
        $startTime = $globalStart;
        $endTime = $globalEnd;
        if (is_array($dayConfig)) {
            $startTime = $dayConfig['start'] ?? $globalStart;
            $endTime = $dayConfig['end'] ?? $globalEnd;
        }
        
        // Day explicitly closed (toggle OFF) = tatil günü
        if (is_array($dayConfig) && isset($dayConfig['enabled']) && $dayConfig['enabled'] === false) {
            // But: if ALL days are closed, likely misconfiguration - fail-open so user isn't blocked
            $allClosed = true;
            foreach ($workingDays as $d) {
                if (is_array($d) && ($d['enabled'] ?? true) !== false) {
                    $allClosed = false;
                    break;
                }
            }
            if (!$allClosed) {
                return [
                    'open' => false,
                    'message' => 'Bugün kapalıyız.',
                    'start' => $startTime,
                    'end' => $endTime,
                ];
            }
        }
        
        // When no day config or enabled not set: treat as open (fail-open for better UX)
        // Also: when ALL days are closed, last saved config might be empty - treat as open
        $currentMinutes = (int)substr($currentTime, 0, 2) * 60 + (int)substr($currentTime, 3, 2);
        $startMinutes = (int)substr($startTime, 0, 2) * 60 + (int)substr($startTime, 3, 2);
        $endMinutes = (int)substr($endTime, 0, 2) * 60 + (int)substr($endTime, 3, 2);
        
        $isOvernight = $endMinutes < $startMinutes; // e.g. 09:00-04:00
        $withinHours = false;
        if ($isOvernight) {
            // Open from start to midnight, or midnight to end
            $withinHours = ($currentMinutes >= $startMinutes) || ($currentMinutes < $endMinutes);
        } else {
            $withinHours = ($currentMinutes >= $startMinutes) && ($currentMinutes <= $endMinutes);
        }
        
        if (!$withinHours) {
            return [
                'open' => false,
                'message' => "Çalışma saatlerimiz: {$startTime} - {$endTime}",
                'start' => $startTime,
                'end' => $endTime,
            ];
        }
        
        return ['open' => true, 'message' => '', 'start' => $startTime, 'end' => $endTime];
    }
}
