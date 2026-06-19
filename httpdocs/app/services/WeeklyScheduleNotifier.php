<?php
namespace App\Services;

/**
 * WeeklyScheduleNotifier — sends each staff member their upcoming week
 * of shifts via every channel the tenant has configured (WhatsApp/Meta,
 * email, in-app, push). Intended to be driven by the weekly cron script
 * (`app/scripts/notify_weekly_schedules.php`).
 *
 * Design notes:
 *  - Uses the existing ShiftScheduleRepository to load rows for the
 *    ISO week that starts on Monday. Guest staff rows (staff_type =
 *    GUEST_STAFF) are included because `yevmiyeciler` also need their
 *    schedules pushed over WhatsApp.
 *  - Delegates actual fan-out to NotificationDispatcher so all
 *    channel-specific errors / logging remain centralised.
 *  - Tenant scoping is preserved by the caller: this service processes
 *    whichever tenant context is active in the current request / CLI
 *    session and never mixes rows across tenants.
 */
class WeeklyScheduleNotifier
{
    /** @var ShiftScheduleService */
    private $shiftService;
    /** @var NotificationDispatcher */
    private $dispatcher;

    public function __construct(
        ShiftScheduleService $shiftService,
        NotificationDispatcher $dispatcher
    ) {
        $this->shiftService = $shiftService;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Send the weekly schedule for the Monday–Sunday window that
     * contains $reference (default: next Monday).
     *
     * @param string|null $reference Any date in the desired week. When
     *                               null, we target NEXT week's Monday.
     * @return array<int, array{staff_id:string, channels:array}> log rows
     */
    public function notifyUpcomingWeek(?string $reference = null): array
    {
        [$start, $end] = $this->resolveWeek($reference);

        $rows = $this->shiftService->getByDateRange($start, $end) ?: [];
        $byStaff = $this->groupByStaff($rows);

        $tenantId = $this->currentTenantId();
        $out = [];

        foreach ($byStaff as $staffId => $group) {
            $message = $this->buildMessage($group, $start, $end);
            $staffMeta = $this->extractStaffMeta($group[0]);

            $channels = ['in_app'];
            if (!empty($staffMeta['phone'])) { $channels[] = 'whatsapp'; }
            if (!empty($staffMeta['email'])) { $channels[] = 'email'; }
            $channels[] = 'push';

            $result = $this->dispatcher->dispatch([
                'tenant_id'       => $tenantId,
                'user_id'         => $staffMeta['staff_type'] === 'USER' ? $staffId : null,
                'email'           => $staffMeta['email'],
                'phone'           => $staffMeta['phone'],
                'title'           => 'Haftalık Vardiya Programı',
                'body'            => $message,
                'channels'        => $channels,
                'data'            => [
                    'type'       => 'WEEKLY_SCHEDULE',
                    'week_start' => $start,
                    'week_end'   => $end,
                    'shifts'     => $group,
                ],
                // WhatsApp template fallback — configurable in env/DB.
                'template_name'   => 'qordy_weekly_schedule',
                'template_params' => [
                    $staffMeta['name'] ?: 'Ekip arkadaşımız',
                    $start,
                    $end,
                    $message,
                ],
            ]);

            $out[] = [
                'staff_id' => (string)$staffId,
                'channels' => $result['results'] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Return the Monday–Sunday pair covering $reference. When $reference
     * is null we jump to next week so the notifier can be run any day
     * and still publish the "coming week".
     *
     * @return array{0:string,1:string}
     */
    private function resolveWeek(?string $reference): array
    {
        if ($reference === null || $reference === '') {
            $ts = strtotime('next monday');
        } else {
            $date = new \DateTime($reference);
            $dow = (int)$date->format('w'); // 0 (Sun) .. 6 (Sat)
            $daysToMonday = ($dow === 0) ? 6 : $dow - 1;
            $date->modify('-' . $daysToMonday . ' days');
            $ts = $date->getTimestamp();
        }
        $start = date('Y-m-d', $ts);
        $end   = date('Y-m-d', strtotime('+6 days', $ts));
        return [$start, $end];
    }

    /**
     * Group rows by staff_id. Each group preserves order by shift_date.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByStaff(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $sid = (string)($row['staff_id'] ?? '');
            if ($sid === '') { continue; }
            $out[$sid][] = $row;
        }
        return $out;
    }

    /**
     * Compose the human-readable body used across all channels.
     *
     * @param array<int, array<string,mixed>> $group
     */
    private function buildMessage(array $group, string $start, string $end): string
    {
        $lines = [];
        $lines[] = sprintf("Haftalık vardiya (%s → %s):", $start, $end);
        foreach ($group as $row) {
            $date = (string)($row['shift_date'] ?? '');
            $from = (string)($row['start_time'] ?? '');
            $to   = (string)($row['end_time'] ?? '');
            $title = (string)($row['shift_name'] ?? $row['shift_type'] ?? 'Vardiya');
            $lines[] = sprintf("• %s  %s-%s  (%s)", $date, $from, $to, $title);
        }
        if (count($lines) === 1) {
            $lines[] = '• Bu hafta için planlanmış vardiya bulunmuyor.';
        }
        return implode("\n", $lines);
    }

    /**
     * Pull name/phone/email off the first shift row. We use the joined
     * columns provided by ShiftScheduleRepository::getByDateRange and
     * fall back to direct user / guest lookups when missing.
     *
     * @param array<string,mixed> $row
     * @return array{name:string, phone:string, email:string, staff_type:string}
     */
    private function extractStaffMeta(array $row): array
    {
        $staffType = strtoupper((string)($row['staff_type'] ?? 'USER'));
        $name  = (string)($row['staff_name'] ?? '');
        $phone = (string)($row['staff_phone'] ?? '');
        $email = (string)($row['staff_email'] ?? '');

        try {
            if ($staffType === 'USER' && !empty($row['staff_id'])) {
                $user = \App\Core\DependencyFactory::getUserRepository()->findById((string)$row['staff_id']);
                if (is_array($user)) {
                    $name  = $name  ?: (string)($user['name']  ?? '');
                    $phone = $phone ?: (string)($user['phone'] ?? '');
                    $email = $email ?: (string)($user['email'] ?? '');
                }
            } elseif ($staffType === 'GUEST_STAFF' && !empty($row['staff_id'])) {
                $guest = \App\Core\DependencyFactory::getGuestStaffRepository()->findById((string)$row['staff_id']);
                if (is_array($guest)) {
                    $name  = $name  ?: trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
                    $phone = $phone ?: (string)($guest['phone'] ?? '');
                    $email = $email ?: (string)($guest['email'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            // lookups are best-effort; the dispatcher will simply skip channels with missing addresses.
        }

        return [
            'name'       => $name,
            'phone'      => $phone,
            'email'      => $email,
            'staff_type' => $staffType,
        ];
    }

    /**
     * Resolve the active tenant id. Returns an empty string when no
     * context is set (still safe — NotificationDispatcher rejects empty
     * tenant for in-app writes).
     */
    private function currentTenantId(): string
    {
        try {
            if (class_exists('\App\Core\TenantContext')) {
                $tid = \App\Core\TenantContext::get();
                if (is_string($tid) && $tid !== '') { return $tid; }
            }
        } catch (\Throwable $e) { /* ignore */ }
        return (string)($_SESSION['business_id'] ?? '');
    }
}
