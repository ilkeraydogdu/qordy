<?php
namespace App\Services;

use App\Core\Logger;
use App\Core\DependencyFactory;

/**
 * QueueNotificationService - sends "sıra geldi" messages through WhatsApp (Meta Cloud API)
 * and email. Encapsulates the delicate 24-hour WhatsApp messaging rule + graceful fallback.
 *
 * Template-based WhatsApp is preferred (queue settings field `whatsapp_template_name`). When
 * no approved template is configured, we skip WhatsApp silently and keep the email channel.
 */
class QueueNotificationService
{
    private const META_API_VERSION = 'v21.0';
    private const META_BASE_URL    = 'https://graph.facebook.com';

    /**
     * Notify the guest that their table is ready.
     *
     * @return array [
     *   'whatsapp' => ['status' => sent|skipped|failed, 'at' => ISO, 'error' => ?string],
     *   'email'    => ['status' => sent|skipped|failed, 'at' => ISO, 'error' => ?string],
     * ]
     */
    public function notifyTableReady(array $entry, array $settings, array $business, string $tableLabel = ''): array
    {
        $result = [
            'whatsapp' => ['status' => 'skipped', 'at' => null, 'error' => null],
            'email'    => ['status' => 'skipped', 'at' => null, 'error' => null],
        ];

        $language     = $entry['language']   ?? ($settings['default_language'] ?? 'tr');
        $guestName    = $entry['name']       ?? '';
        $queueNumber  = $entry['queue_number'] ?? '';
        $businessName = $business['company_name'] ?? $business['first_name'] ?? 'Qordy';
        $tableLabel   = trim($tableLabel);

        // 1) WhatsApp (Meta Cloud API) — sadece süper admin işletmeye izin verdiyse.
        //    meta_whatsapp_enabled=0 ise WhatsApp tamamen devre dışı bırakılır
        //    ve mail kanalı (aşağıda) devreye girer.
        $metaAllowed = $this->isMetaWhatsappAllowedForBusiness($business);

        if (!empty($settings['whatsapp_enabled']) && !empty($entry['phone']) && $metaAllowed) {
            try {
                // Template is now owned by the super admin (single Meta
                // corporate template shared across all tenants). Legacy
                // per-tenant queue_settings.whatsapp_template_name is used as
                // a fallback so old data keeps working until migrated.
                $globalTemplate = null;
                try {
                    $sys = DependencyFactory::getSystemSettingsService();
                    $globalTemplate = trim((string) $sys->getSetting('meta_queue_template_name', ''));
                    if ($globalTemplate === '') {
                        $globalTemplate = null;
                    }
                } catch (\Throwable $e) {
                    $globalTemplate = null;
                }
                $templateName = $globalTemplate
                    ?? (!empty($settings['whatsapp_template_name']) ? $settings['whatsapp_template_name'] : null);

                $result['whatsapp'] = $this->sendWhatsApp(
                    (string) $entry['phone'],
                    (string) $language,
                    (string) $guestName,
                    (string) $queueNumber,
                    $businessName,
                    $templateName,
                    $tableLabel
                );
            } catch (\Throwable $e) {
                $result['whatsapp'] = [
                    'status' => 'failed',
                    'at'     => date('c'),
                    'error'  => $e->getMessage(),
                ];
            }
        } elseif (!empty($settings['whatsapp_enabled']) && !$metaAllowed) {
            // İşletme kendi tarafında açık ama süper admin iznini kaldırmış.
            $result['whatsapp'] = [
                'status' => 'skipped',
                'at'     => date('c'),
                'error'  => 'meta_not_permitted_for_tenant',
            ];
        }

        // 2) Email fallback / secondary channel
        if (!empty($settings['email_enabled']) && !empty($entry['email'])) {
            try {
                $result['email'] = $this->sendEmail(
                    $entry['email'],
                    $language,
                    $guestName,
                    (string) $queueNumber,
                    $businessName,
                    $tableLabel
                );
            } catch (\Throwable $e) {
                $result['email'] = [
                    'status' => 'failed',
                    'at'     => date('c'),
                    'error'  => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Misafir sıraya katıldığında "sıranız alındı" bildirimi. Email/WhatsApp
     * izni açıksa anında bilgilendirme; sıra, tahmini süre ve karşılama içerir.
     *
     * @return array ['whatsapp' => ..., 'email' => ...]
     */
    public function notifyJoined(array $entry, array $settings, array $business, int $position, int $etaMinutes): array
    {
        $result = [
            'whatsapp' => ['status' => 'skipped', 'at' => null, 'error' => null],
            'email'    => ['status' => 'skipped', 'at' => null, 'error' => null],
        ];

        $language     = $entry['language'] ?? ($settings['default_language'] ?? 'tr');
        $guestName    = $entry['name'] ?? '';
        $queueNumber  = $entry['queue_number'] ?? '';
        $businessName = $business['company_name'] ?? $business['first_name'] ?? 'Qordy';
        $statusUrl    = $this->statusUrlForEntry($entry, $business);

        if (!empty($settings['email_enabled']) && !empty($entry['email'])) {
            try {
                $subject = $this->t($language, 'join_subject', [
                    'business' => $businessName,
                    'number'   => $queueNumber,
                ]);
                $body = $this->buildJoinEmailBody($language, $guestName, (string) $queueNumber, $businessName, $position, $etaMinutes, $statusUrl);
                $sent = DependencyFactory::getEmailService()->sendEmail($entry['email'], $subject, $body);
                $result['email'] = ['status' => $sent ? 'sent' : 'failed', 'at' => date('c'), 'error' => $sent ? null : 'email_send_failed'];
            } catch (\Throwable $e) {
                $result['email'] = ['status' => 'failed', 'at' => date('c'), 'error' => $e->getMessage()];
            }
        }

        // WhatsApp join bildirimi: bağımsız bir şablon (meta_queue_join_template_name)
        // opsiyonel olarak süper admin tarafından tanımlanmışsa gönderilir; yoksa
        // 24-saat politikasından dolayı atlanır. Template bulunmadığında sessizce skip.
        $metaAllowed = $this->isMetaWhatsappAllowedForBusiness($business);
        if (!empty($settings['whatsapp_enabled']) && !empty($entry['phone']) && $metaAllowed) {
            try {
                $joinTpl = null;
                try {
                    $sys = DependencyFactory::getSystemSettingsService();
                    $t = trim((string) $sys->getSetting('meta_queue_join_template_name', ''));
                    if ($t !== '') {
                        $joinTpl = $t;
                    }
                } catch (\Throwable $e) {
                    $joinTpl = null;
                }
                if ($joinTpl) {
                    $result['whatsapp'] = $this->sendWhatsAppJoin(
                        (string) $entry['phone'],
                        (string) $language,
                        (string) $guestName,
                        (string) $queueNumber,
                        $businessName,
                        (string) $position,
                        (string) $etaMinutes,
                        $joinTpl
                    );
                }
            } catch (\Throwable $e) {
                $result['whatsapp'] = ['status' => 'failed', 'at' => date('c'), 'error' => $e->getMessage()];
            }
        }

        return $result;
    }

    private function statusUrlForEntry(array $entry, array $business): string
    {
        try {
            $url = DependencyFactory::getUrlService();
            $tenantId = $business['customer_id'] ?? $business['id'] ?? null;
            if ($tenantId) {
                return $url->buildTenantUrl((string) $tenantId, '/sira/bilet/' . rawurlencode($entry['queue_id'] ?? ''));
            }
        } catch (\Throwable $e) { /* noop */ }
        return '';
    }

    /**
     * Bir işletmenin (tenant) WhatsApp/Meta sıra mesajlarını kullanmaya izinli
     * olup olmadığını döner. İki aşamalı kontrol:
     *   1) Platform-geneli master switch: `meta_queue_messaging_enabled`
     *      (süper admin /qodmin/settings Meta sekmesinden yönetir). Kapalıysa
     *      hiçbir işletme WhatsApp sıra mesajı gönderemez.
     *   2) İşletme-bazlı izin: `customers.meta_whatsapp_enabled`. Süper admin
     *      belirli işletmeyi açıkça yetkilendirmediyse mesaj gönderilmez.
     *
     * @param array $business customers satırı (en azından customer_id veya id)
     */
    private function isMetaWhatsappAllowedForBusiness(array $business): bool
    {
        // 1) Master switch: platform-wide feature toggle.
        if (!$this->isMetaQueueMessagingGloballyEnabled()) {
            return false;
        }

        // 2) Per-business authorization.
        if (isset($business['meta_whatsapp_enabled'])) {
            return (int) $business['meta_whatsapp_enabled'] === 1;
        }

        $cid = $business['customer_id'] ?? $business['id'] ?? null;
        if (!$cid) {
            return false;
        }

        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare('SELECT meta_whatsapp_enabled FROM customers WHERE customer_id = :cid LIMIT 1');
            $stmt->execute(['cid' => $cid]);
            $val = $stmt->fetchColumn();
            return (int) $val === 1;
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                Logger::warning('isMetaWhatsappAllowedForBusiness failed', [
                    'customer_id' => $cid,
                    'error'       => $e->getMessage(),
                ]);
            }
            return false;
        }
    }

    /**
     * Platform-wide master switch for Meta queue messaging. Lives on the
     * tenant-less system_settings row (tenant_id IS NULL). Defaults to
     * enabled (1) for backward compatibility if the column is missing.
     */
    private function isMetaQueueMessagingGloballyEnabled(): bool
    {
        try {
            $sys = DependencyFactory::getSystemSettingsService();
            $v = $sys->getSetting('meta_queue_messaging_enabled', '1');
            // Explicit "0" / "false" disables; everything else (incl. null) = on.
            return !in_array(trim((string) $v), ['0', 'false', 'off', 'no'], true);
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function sendWhatsApp(
        string $phone,
        string $language,
        string $guestName,
        string $queueNumber,
        string $businessName,
        ?string $templateName,
        string $tableLabel = ''
    ): array {
        $settings = DependencyFactory::getSystemSettingsService();
        $accessToken = trim($settings->getSetting('meta_access_token', ''));
        $phoneNumberId = trim($settings->getSetting('meta_phone_number_id', ''));

        if ($accessToken === '' || $phoneNumberId === '') {
            return ['status' => 'skipped', 'at' => date('c'), 'error' => 'meta_not_configured'];
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phoneDigits) < 10) {
            return ['status' => 'failed', 'at' => date('c'), 'error' => 'invalid_phone'];
        }

        $url = self::META_BASE_URL . '/' . self::META_API_VERSION . '/' . $phoneNumberId . '/messages';

        if ($templateName) {
            $params = [
                ['type' => 'text', 'text' => $guestName !== '' ? $guestName : 'Guest'],
                ['type' => 'text', 'text' => (string) $queueNumber],
                ['type' => 'text', 'text' => $businessName],
            ];
            if ($tableLabel !== '') {
                $params[] = ['type' => 'text', 'text' => $tableLabel];
            }
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $phoneDigits,
                'type'              => 'template',
                'template'          => [
                    'name'     => $templateName,
                    'language' => ['code' => $this->mapMetaLanguageCode($language)],
                    'components' => [
                        ['type' => 'body', 'parameters' => $params],
                    ],
                ],
            ];
        } else {
            // Fallback: plain text. Only works if the user is in an open 24-hour conversation window.
            // Otherwise Meta will reject – we capture and surface that error to logs.
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $phoneDigits,
                'type'              => 'text',
                'text' => [
                    'body' => $this->buildPlainBody($language, $guestName, $queueNumber, $businessName, $tableLabel),
                ],
            ];
        }

        $response = $this->httpPost($url, $payload, $accessToken);

        if ($response['ok']) {
            return ['status' => 'sent', 'at' => date('c'), 'error' => null];
        }

        Logger::warning('QueueNotificationService WhatsApp failed', [
            'code' => $response['http_code'] ?? 0,
            'body' => $response['body'] ?? null,
        ]);

        return [
            'status' => 'failed',
            'at'     => date('c'),
            'error'  => $response['error'] ?? 'http_error',
        ];
    }

    private function sendEmail(
        string $email,
        string $language,
        string $guestName,
        string $queueNumber,
        string $businessName,
        string $tableLabel = ''
    ): array {
        $emailService = DependencyFactory::getEmailService();

        $subject = $this->t($language, 'subject', [
            'business' => $businessName,
            'number'   => $queueNumber,
        ]);

        $body = $this->buildEmailBody($language, $guestName, $queueNumber, $businessName, $tableLabel);

        $sent = $emailService->sendEmail($email, $subject, $body);
        return [
            'status' => $sent ? 'sent' : 'failed',
            'at'     => date('c'),
            'error'  => $sent ? null : 'email_send_failed',
        ];
    }

    /**
     * Minimal in-house i18n for notification copy (WhatsApp text fallback + email body).
     */
    private function buildPlainBody(string $lang, string $name, string $number, string $biz, string $tableLabel = ''): string
    {
        $text = $this->t($lang, 'plain_body', [
            'name'     => $name ?: '',
            'number'   => $number,
            'business' => $biz,
        ]);
        if ($tableLabel !== '') {
            $text .= "\n" . $this->t($lang, 'table_line', ['table' => $tableLabel]);
        }
        return $text;
    }

    private function buildEmailBody(string $lang, string $name, string $number, string $biz, string $tableLabel = ''): string
    {
        $heading = $this->t($lang, 'email_heading');
        $msg     = $this->t($lang, 'email_body', [
            'name'     => $name ?: '',
            'number'   => $number,
            'business' => $biz,
        ]);
        $footer  = $this->t($lang, 'email_footer', ['business' => $biz]);

        $tableBlock = '';
        if ($tableLabel !== '') {
            $tableBlock = '<div style="margin:16px 0;padding:12px 16px;background:#ecfeff;border:1px solid #a5f3fc;border-radius:10px;color:#155e75;text-align:center;font-weight:600;">'
                . htmlspecialchars($this->t($lang, 'table_line', ['table' => $tableLabel]))
                . '</div>';
        }

        return '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f5f6fa;padding:24px;">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;">'
            . '<div style="background:#0f172a;color:#fff;padding:20px 24px;font-size:18px;font-weight:700;">'
            . htmlspecialchars($biz) . '</div>'
            . '<div style="padding:24px;">'
            . '<h2 style="margin:0 0 12px;color:#0f172a;">' . htmlspecialchars($heading) . '</h2>'
            . '<p style="color:#334155;font-size:15px;line-height:1.5;">' . nl2br(htmlspecialchars($msg)) . '</p>'
            . '<div style="margin:24px 0;padding:16px 20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;color:#9a3412;font-weight:700;text-align:center;font-size:22px;">#' . htmlspecialchars($number) . '</div>'
            . $tableBlock
            . '</div>'
            . '<div style="padding:16px 24px;color:#94a3b8;font-size:12px;border-top:1px solid #f1f5f9;">' . htmlspecialchars($footer) . '</div>'
            . '</div></body></html>';
    }

    private function buildJoinEmailBody(string $lang, string $name, string $number, string $biz, int $position, int $eta, string $statusUrl = ''): string
    {
        $heading = $this->t($lang, 'join_email_heading', ['business' => $biz]);
        $body    = $this->t($lang, 'join_email_body', [
            'name'     => $name ?: '',
            'business' => $biz,
            'position' => (string) max(1, $position),
            'eta'      => (string) max(0, $eta),
        ]);
        $cta     = $this->t($lang, 'join_email_cta');
        $footer  = $this->t($lang, 'email_footer', ['business' => $biz]);

        $ctaBlock = ($statusUrl !== '')
            ? '<div style="margin:20px 0 4px;text-align:center;"><a href="' . htmlspecialchars($statusUrl) . '" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#f97316;color:#fff;font-weight:800;text-decoration:none;">'
              . htmlspecialchars($cta) . '</a></div>'
              . '<p style="margin:6px 0 0;color:#94a3b8;font-size:11px;text-align:center;word-break:break-all;">' . htmlspecialchars($statusUrl) . '</p>'
            : '';

        return '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f5f6fa;padding:24px;">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;">'
            . '<div style="background:#0f172a;color:#fff;padding:20px 24px;font-size:18px;font-weight:700;">' . htmlspecialchars($biz) . '</div>'
            . '<div style="padding:24px;">'
            . '<h2 style="margin:0 0 10px;color:#0f172a;">' . htmlspecialchars($heading) . '</h2>'
            . '<p style="color:#334155;font-size:15px;line-height:1.55;">' . nl2br(htmlspecialchars($body)) . '</p>'
            . '<div style="display:flex;gap:10px;margin-top:16px;">'
            . '<div style="flex:1;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;text-align:center;">'
            . '<div style="font-size:10px;font-weight:700;letter-spacing:.15em;color:#64748b;text-transform:uppercase;">' . htmlspecialchars($this->t($lang, 'join_email_position_label')) . '</div>'
            . '<div style="margin-top:4px;font-size:22px;font-weight:900;color:#0f172a;">' . (int) max(1, $position) . '</div></div>'
            . '<div style="flex:1;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;text-align:center;">'
            . '<div style="font-size:10px;font-weight:700;letter-spacing:.15em;color:#64748b;text-transform:uppercase;">' . htmlspecialchars($this->t($lang, 'join_email_eta_label')) . '</div>'
            . '<div style="margin-top:4px;font-size:22px;font-weight:900;color:#0f172a;">' . (int) max(0, $eta) . ' ' . htmlspecialchars($this->t($lang, 'minutes')) . '</div></div>'
            . '</div>'
            . '<div style="margin:22px 0;padding:16px 20px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;color:#9a3412;font-weight:800;text-align:center;font-size:22px;">#' . htmlspecialchars($number) . '</div>'
            . $ctaBlock
            . '</div>'
            . '<div style="padding:16px 24px;color:#94a3b8;font-size:12px;border-top:1px solid #f1f5f9;">' . htmlspecialchars($footer) . '</div>'
            . '</div></body></html>';
    }

    private function sendWhatsAppJoin(
        string $phone,
        string $language,
        string $guestName,
        string $queueNumber,
        string $businessName,
        string $position,
        string $etaMinutes,
        string $templateName
    ): array {
        $settings = DependencyFactory::getSystemSettingsService();
        $accessToken = trim($settings->getSetting('meta_access_token', ''));
        $phoneNumberId = trim($settings->getSetting('meta_phone_number_id', ''));

        if ($accessToken === '' || $phoneNumberId === '') {
            return ['status' => 'skipped', 'at' => date('c'), 'error' => 'meta_not_configured'];
        }
        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phoneDigits) < 10) {
            return ['status' => 'failed', 'at' => date('c'), 'error' => 'invalid_phone'];
        }

        $url = self::META_BASE_URL . '/' . self::META_API_VERSION . '/' . $phoneNumberId . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phoneDigits,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $this->mapMetaLanguageCode($language)],
                'components' => [
                    ['type' => 'body', 'parameters' => [
                        ['type' => 'text', 'text' => $guestName !== '' ? $guestName : 'Guest'],
                        ['type' => 'text', 'text' => $businessName],
                        ['type' => 'text', 'text' => (string) $queueNumber],
                        ['type' => 'text', 'text' => (string) $position],
                        ['type' => 'text', 'text' => (string) $etaMinutes],
                    ]],
                ],
            ],
        ];
        $response = $this->httpPost($url, $payload, $accessToken);
        if ($response['ok']) {
            return ['status' => 'sent', 'at' => date('c'), 'error' => null];
        }
        return ['status' => 'failed', 'at' => date('c'), 'error' => $response['error'] ?? 'http_error'];
    }

    private function t(string $lang, string $key, array $params = []): string
    {
        $dict = [
            'tr' => [
                'subject'      => 'Sıranız geldi - {{business}} #{{number}}',
                'plain_body'   => 'Merhaba {{name}}, sıranız geldi. Lütfen {{business}} işletmesine gelin. Bilet no: #{{number}}',
                'email_heading'=> 'Sıranız geldi!',
                'email_body'   => "Merhaba {{name}},\n\n{{business}} işletmesinde masanız hazır. Lütfen kapıdaki personele bilet numaranızı gösterin.",
                'email_footer' => '{{business}} - Qordy ile gönderildi',
                'table_line'   => 'Masa: {{table}}',
                'join_subject' => 'Sıranız alındı - {{business}} #{{number}}',
                'join_email_heading' => 'Sıranız alındı · {{business}}',
                'join_email_body' => "Sayın {{name}},\n\n{{business}} işletmemize hoş geldiniz. Yoğunluk nedeniyle kısa bir süre beklemeniz gerekecek; bu süre zarfında size en iyi hizmeti sunabilmek için hazırlık yapıyoruz.\n\nMasanız hazır olduğunda sizi hem e-posta ile hem de (varsa) WhatsApp üzerinden bilgilendireceğiz. Sayfayı açık tutmanız yeterli.",
                'join_email_cta' => 'Canlı sıra durumunu gör',
                'join_email_position_label' => 'Sıradaki yeriniz',
                'join_email_eta_label' => 'Tahmini bekleme',
                'minutes' => 'dk',
            ],
            'en' => [
                'subject'      => 'Your table is ready - {{business}} #{{number}}',
                'plain_body'   => 'Hi {{name}}, your table at {{business}} is ready. Ticket: #{{number}}',
                'email_heading'=> 'Your table is ready!',
                'email_body'   => "Hi {{name}},\n\nYour table at {{business}} is ready. Please show your ticket number to the staff at the door.",
                'email_footer' => '{{business}} - Sent via Qordy',
                'table_line'   => 'Table: {{table}}',
                'join_subject' => 'You are in the queue - {{business}} #{{number}}',
                'join_email_heading' => 'You are in line · {{business}}',
                'join_email_body' => "Dear {{name}},\n\nWelcome to {{business}}. Due to demand, you will have a short wait; we are preparing to give you the best experience.\n\nWe will notify you via email and (when available) WhatsApp the moment your table is ready. Just keep this page open.",
                'join_email_cta' => 'See live queue status',
                'join_email_position_label' => 'Your position',
                'join_email_eta_label' => 'Estimated wait',
                'minutes' => 'min',
            ],
            'de' => [
                'subject'      => 'Ihr Tisch ist bereit - {{business}} #{{number}}',
                'plain_body'   => 'Hallo {{name}}, Ihr Tisch bei {{business}} ist frei. Ticket: #{{number}}',
                'email_heading'=> 'Ihr Tisch ist bereit!',
                'email_body'   => "Hallo {{name}},\n\nIhr Tisch bei {{business}} ist jetzt frei. Bitte zeigen Sie dem Personal Ihre Ticketnummer.",
                'email_footer' => '{{business}} - Gesendet über Qordy',
                'table_line'   => 'Tisch: {{table}}',
                'join_subject' => 'Sie stehen in der Schlange - {{business}} #{{number}}',
                'join_email_heading' => 'Sie stehen in der Schlange · {{business}}',
                'join_email_body' => "Sehr geehrte(r) {{name}},\n\nWillkommen bei {{business}}. Aufgrund des Andrangs bitten wir um etwas Geduld.\n\nSobald Ihr Tisch frei ist, benachrichtigen wir Sie per E-Mail und (falls verfügbar) WhatsApp. Halten Sie diese Seite offen.",
                'join_email_cta' => 'Live-Status ansehen',
                'join_email_position_label' => 'Ihre Position',
                'join_email_eta_label' => 'Geschätzte Wartezeit',
                'minutes' => 'Min.',
            ],
            'ar' => [
                'subject'      => 'طاولتك جاهزة - {{business}} #{{number}}',
                'plain_body'   => 'مرحبًا {{name}}, طاولتك في {{business}} جاهزة. رقم: #{{number}}',
                'email_heading'=> 'طاولتك جاهزة!',
                'email_body'   => "مرحبًا {{name}},\n\nطاولتك في {{business}} جاهزة الآن. يرجى إظهار رقم تذكرتك للموظفين.",
                'email_footer' => '{{business}} - عبر Qordy',
                'table_line'   => 'طاولة: {{table}}',
                'join_subject' => 'تم انضمامك إلى الطابور - {{business}} #{{number}}',
                'join_email_heading' => 'أنت في الطابور · {{business}}',
                'join_email_body' => "عزيزي {{name}},\n\nمرحبًا بك في {{business}}. بسبب الازدحام، ستنتظر قليلاً؛ نُعدّ لك أفضل تجربة ممكنة.\n\nسنبلغك عبر البريد الإلكتروني و(إن توفر) واتساب حالما تصبح طاولتك جاهزة.",
                'join_email_cta' => 'شاهد حالة الطابور مباشرة',
                'join_email_position_label' => 'موقعك',
                'join_email_eta_label' => 'الوقت المتوقع',
                'minutes' => 'د',
            ],
        ];

        $pack = $dict[$lang] ?? $dict['en'];
        $text = $pack[$key] ?? ($dict['en'][$key] ?? $key);
        foreach ($params as $p => $v) {
            $text = str_replace('{{' . $p . '}}', (string) $v, $text);
        }
        return $text;
    }

    private function mapMetaLanguageCode(string $lang): string
    {
        $map = ['tr' => 'tr', 'en' => 'en', 'de' => 'de', 'ar' => 'ar'];
        return $map[$lang] ?? 'en';
    }

    private function httpPost(string $url, array $payload, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'http_code' => 0, 'error' => $err ?: 'curl_error'];
        }

        $ok = $code >= 200 && $code < 300;
        return ['ok' => $ok, 'http_code' => $code, 'body' => $body];
    }
}
