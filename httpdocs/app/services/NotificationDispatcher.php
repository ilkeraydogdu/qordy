<?php
namespace App\Services;

/**
 * NotificationDispatcher — channel-agnostic facade.
 *
 * A thin orchestration layer that routes a logical notification event to
 * any combination of: in-app (NotificationService), email (EmailService),
 * WhatsApp (WhatsAppService) and FCM push (PushService). Failures in one
 * channel never abort the rest; every attempt is returned in the result
 * array so the caller can log / audit. All feature-level dispatchers
 * (LowStockDispatcher, WeeklyScheduleNotifier, ...) route through this
 * class so we have a single place to evolve fan-out behaviour.
 */
class NotificationDispatcher
{
    /** @var NotificationService|null */
    private $notificationService;
    /** @var EmailService|null */
    private $emailService;
    /** @var WhatsAppService|null */
    private $whatsAppService;
    /** @var PushService|null */
    private $pushService;

    public function __construct(
        ?NotificationService $notificationService = null,
        ?EmailService $emailService = null,
        ?WhatsAppService $whatsAppService = null,
        ?PushService $pushService = null
    ) {
        $this->notificationService = $notificationService;
        $this->emailService = $emailService;
        $this->whatsAppService = $whatsAppService;
        $this->pushService = $pushService;
    }

    /**
     * Dispatch a notification to any combination of channels.
     *
     * @param array $payload {
     *   @type string       $tenant_id         required
     *   @type string|null  $user_id           recipient user (for push/in_app)
     *   @type string|null  $email             email recipient (overrides lookups)
     *   @type string|null  $phone             whatsapp recipient (E.164 digits)
     *   @type string       $title             display title
     *   @type string       $body              display body
     *   @type string[]     $channels          any of: in_app, email, whatsapp, push
     *   @type array        $data              extra payload (in_app + push)
     *   @type string|null  $template_name     WhatsApp template override
     *   @type array|null   $template_params   WhatsApp template body params
     * }
     * @return array{ results: array<string, array{status:string, detail?:string}> }
     */
    public function dispatch(array $payload): array
    {
        $channels = (array)($payload['channels'] ?? ['in_app']);
        $channels = array_values(array_unique(array_filter(array_map('strval', $channels))));
        $results = [];

        foreach ($channels as $ch) {
            $ch = strtolower(trim($ch));
            try {
                switch ($ch) {
                    case 'in_app':
                        $results['in_app'] = $this->sendInApp($payload);
                        break;
                    case 'email':
                        $results['email'] = $this->sendEmail($payload);
                        break;
                    case 'whatsapp':
                    case 'meta':
                        $results['whatsapp'] = $this->sendWhatsApp($payload);
                        break;
                    case 'push':
                        $results['push'] = $this->sendPush($payload);
                        break;
                    default:
                        $results[$ch] = ['status' => 'skipped', 'detail' => 'unknown channel'];
                }
            } catch (\Throwable $e) {
                $results[$ch] = ['status' => 'failed', 'detail' => $e->getMessage()];
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('NotificationDispatcher: channel error', [
                        'channel' => $ch,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['results' => $results];
    }

    /**
     * Send an in-app notification. Falls back gracefully when the service
     * requires a structured table context (create() expects tableId/name).
     *
     * @return array{status:string, detail?:string, id?:string|null}
     */
    private function sendInApp(array $payload): array
    {
        if (!$this->notificationService) {
            return ['status' => 'skipped', 'detail' => 'notification service unavailable'];
        }

        $title = (string)($payload['title'] ?? '');
        $body  = (string)($payload['body']  ?? '');
        $tenantId = (string)($payload['tenant_id'] ?? '');
        $userId = $payload['user_id'] ?? null;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        // NotificationService::create validates a fixed set of types. For
        // generic system events we encode content under the SYSTEM type.
        $notifData = array_merge($data, [
            'title'    => $title,
            'body'     => $body,
            'tenant_id' => $tenantId,
            'user_id'  => $userId,
        ]);

        $id = $this->notificationService->create(
            'SYSTEM',
            (string)($data['table_id'] ?? $tenantId),
            $title,
            $notifData,
            false
        );

        return [
            'status' => $id ? 'sent' : 'failed',
            'id'     => is_string($id) ? $id : null,
        ];
    }

    /** @return array{status:string, detail?:string} */
    private function sendEmail(array $payload): array
    {
        if (!$this->emailService) {
            return ['status' => 'skipped', 'detail' => 'email service unavailable'];
        }
        $to = (string)($payload['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'skipped', 'detail' => 'missing/invalid email'];
        }
        $subject = (string)($payload['title'] ?? 'Qordy Bildirimi');
        $bodyText = (string)($payload['body'] ?? '');
        $bodyHtml = '<!doctype html><html><body style="font-family:Arial,sans-serif;line-height:1.5;color:#111">'
            . '<h2 style="color:#f97316">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<div>' . nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8')) . '</div>'
            . '<hr style="margin-top:24px"><p style="color:#888;font-size:12px">Qordy otomatik bildirimi</p>'
            . '</body></html>';
        $ok = $this->emailService->sendEmail($to, $subject, $bodyHtml);
        return ['status' => $ok ? 'sent' : 'failed'];
    }

    /** @return array{status:string, detail?:string} */
    private function sendWhatsApp(array $payload): array
    {
        if (!$this->whatsAppService) {
            return ['status' => 'skipped', 'detail' => 'whatsapp service unavailable'];
        }
        $phone = (string)($payload['phone'] ?? '');
        $phone = preg_replace('/\D/', '', $phone) ?? '';
        if ($phone === '' || strlen($phone) < 10) {
            return ['status' => 'skipped', 'detail' => 'missing/invalid phone'];
        }

        // Prefer generic text over a template when no approved template is
        // passed — this will only deliver inside the 24h re-engagement window
        // but is safe as a best-effort fallback.
        $body = trim((string)($payload['title'] ?? '') . "\n\n" . (string)($payload['body'] ?? ''));
        if ($body === '') {
            return ['status' => 'skipped', 'detail' => 'empty body'];
        }

        $method = [$this->whatsAppService, 'sendPlainText'];
        if (!is_callable($method)) {
            // Older WhatsAppService exposed only sendVerificationCode; try a
            // shim using reflection so we don't hard-fail.
            if (method_exists($this->whatsAppService, 'sendTextMessage')) {
                $result = $this->whatsAppService->sendTextMessage($phone, $body);
            } else {
                return ['status' => 'skipped', 'detail' => 'plain-text send not supported'];
            }
        } else {
            $result = $this->whatsAppService->sendPlainText($phone, $body);
        }

        $ok = is_array($result) ? (bool)($result['success'] ?? false) : (bool)$result;
        return [
            'status' => $ok ? 'sent' : 'failed',
            'detail' => is_array($result) ? (string)($result['message'] ?? '') : '',
        ];
    }

    /** @return array{status:string, detail?:string} */
    private function sendPush(array $payload): array
    {
        if (!$this->pushService) {
            return ['status' => 'skipped', 'detail' => 'push service unavailable'];
        }
        $title = (string)($payload['title'] ?? '');
        $body  = (string)($payload['body'] ?? '');
        $data  = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $tenantId = (string)($payload['tenant_id'] ?? '');
        $userId = $payload['user_id'] ?? null;

        try {
            if ($userId && method_exists($this->pushService, 'sendToUser')) {
                $this->pushService->sendToUser((string)$userId, $title, $body, $data);
                return ['status' => 'sent'];
            }
            if ($tenantId !== '' && method_exists($this->pushService, 'sendToTenantRoles')) {
                $roles = $payload['roles'] ?? ['BUSINESS_OWNER'];
                $this->pushService->sendToTenantRoles($tenantId, $roles, $title, $body, $data);
                return ['status' => 'sent'];
            }
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'detail' => $e->getMessage()];
        }
        return ['status' => 'skipped', 'detail' => 'no routing target'];
    }
}
